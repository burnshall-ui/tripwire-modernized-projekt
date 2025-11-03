<?php

class SystemView {
    private array $data;
    private ?Container $container = null;

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    public function setContainer(Container $container): void {
        $this->container = $container;
    }

    public function setData(string $key, $value): void {
        $this->data[$key] = $value;
    }

    public function getData(string $key = null) {
        return $key ? ($this->data[$key] ?? null) : $this->data;
    }

    public function renderHead(): void {
        $system = $this->data['system'] ?? 'Unknown';
        $systemID = $this->data['systemID'] ?? '0';

        // Load SecurityHelper to generate CSRF token
        require_once(__DIR__ . '/../services/SecurityHelper.php');
        $csrfToken = SecurityHelper::getCsrfToken();
        ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <meta name="system" content="<?= htmlspecialchars($system) ?>">
    <meta name="systemID" content="<?= htmlspecialchars($systemID) ?>">
    <meta name="server" content="<?= htmlspecialchars(CDN_DOMAIN) ?>">
    <meta name="app_name" content="<?= htmlspecialchars(APP_NAME) ?>">
    <meta name="version" content="<?= htmlspecialchars(VERSION) ?>">
    <link rel="shortcut icon" href="//<?= CDN_DOMAIN ?>/images/favicon.png" />

    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/jquery.duration-picker.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/jquery.jbox.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/jquery.jbox-notice.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/gridster.min.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/jquery-ui-1.12.1.min.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/jquery-ui-custom.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/introjs.min.css">
    <link rel="stylesheet" type="text/css" href="//<?= CDN_DOMAIN ?>/css/app.min.css?v=<?= VERSION ?>">

    <title></title>
</head>
<?php flush(); ?>
<body class="transition">
        <?php
    }

    public function renderTopbar(): void {
        $userData = $this->data['user'] ?? [];
        $system = $this->data['system'] ?? 'Unknown';
        ?>
    <div id="wrapper">
        <div id="inner-wrapper">
            <div id="topbar">
                <span class="align-left">
                    <h1 id="logo">
                        <a href="."><?= htmlspecialchars(APP_NAME) ?></a>
                        <span id="version"><?= htmlspecialchars(VERSION) ?></span>
                        <span>|</span>
                        <!-- <span data-tooltip="System activity update countdown"><input id="APIclock" class="hidden" /></span> -->
                    </h1>
                    <h3 id="serverStatus" class="pointer" data-tooltip="EVE server status and player count"></h3>
                    <h3 id="systemSearch">| <i id="search" data-icon="search" data-tooltip="Toggle system search"></i>
                        <span id="currentSpan" class="hidden"><span class="pointer">Current System: </span><a id="EVEsystem" href=""></a><i id="follow" data-icon="follow" data-tooltip="Follow my in-game system" style="padding-left: 10px;"></i></span>
                        <span id="searchSpan"><form id="systemSearch" method="GET" action=".?"><input type="text" size="18" class="systemsAutocomplete" name="system" /></form></span>
                        <span id="APItimer" class="hidden"></span>
                    </h3>
                </span>
                <span class="align-right">
                    <span id="login">
                        <h3><a id="user" href=""><?= htmlspecialchars($userData['characterName'] ?? '') ?></a></h3>
                        <div id="panel">
                            <div id="content">
                                <div id="triangle"></div>
        <?php
    }

    public function renderUserPanel(): void {
        $userData = $this->data['user'] ?? [];
        ?>
                                <table id="logoutTable">
                                    <tr>
                                        <td>
                                            <table id="track">
                                                <tr><th colspan="2">Tracking</th></tr>
                                                <tr>
                                                    <td id="tracking">
                                                        <table id="tracking-clone" class="hidden">
                                                            <tr>
                                                                <td rowspan="5" class="avatar"><img src="" /></td>
                                                                <td id="characterName" class="text"></td>
                                                            </tr>
                                                            <tr><td class="text" id="corporationName"></td></tr>
                                                            <tr><td class="text" id="lastSeen"></td></tr>
                                                            <tr><td class="text" id="tracking-status"></td></tr>
                                                            <tr><td class="text" id="tracking-location"></td></tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <table id="logout">
                                                <tr><th colspan="2">Account</th></tr>
                                                <tr>
                                                    <td rowspan="4" class="avatar">
                                                        <img src="https://image.eveonline.com/Character/<?= htmlspecialchars($userData['characterID'] ?? '0') ?>_64.jpg" />
                                                    </td>
                                                    <td id="characterName" class="text"><?= htmlspecialchars($userData['characterName'] ?? '') ?></td>
                                                </tr>
                                                <tr><td class="text" id="corporationName"><?= htmlspecialchars($userData['corporationName'] ?? '') ?></td></tr>
                                                <tr><td class="text" id="allianceName"></td></tr>
                                                <tr><td class="text" id="admin">
                                                    <?php if ($this->checkAdminPermissions($userData)): ?>
                                                        <i id="admin" style="font-size: 1.7em;" data-icon="user" data-tooltip="Mask Admin"></i>
                                                    <?php endif; ?>
                                                </td></tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </span>
                </span>
            </div>
        </div>
    </div>
        <?php
    }

    private function checkAdminPermissions(array $userData): bool {
        if (!$this->container) {
            return false;
        }

        try {
            $userService = $this->container->get('userService');
            return isset($userData['mask']) && $userService->checkAdminPermission($userData['mask']);
        } catch (Exception $e) {
            error_log("Error checking admin permissions: " . $e->getMessage());
            return false;
        }
    }

    public function renderFooter(): void {
        ?>
            <div id="map"></div>

            <div id="dialogs">
                <div id="signatureDialog" class="dialog"></div>
                <div id="wormholeDialog" class="dialog"></div>
                <div id="optionsDialog" class="dialog"></div>
                <div id="adminDialog" class="dialog"></div>
            </div>

            <textarea id="clipboard"></textarea>

            <script type="text/javascript">
                var init = <?= htmlspecialchars(json_encode($this->data['session'] ?? []), ENT_QUOTES, 'UTF-8') ?>;
            </script>

            <!-- JS Includes -->
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery-3.3.1.min.js"></script>

            <!-- CSRF Protection - Add token to all AJAX requests -->
            <script type="text/javascript">
            (function($) {
                // Get CSRF token from meta tag
                var csrfToken = $('meta[name="csrf-token"]').attr('content');

                if (csrfToken) {
                    // Setup global AJAX default to include CSRF token
                    $.ajaxSetup({
                        data: { csrf_token: csrfToken },
                        beforeSend: function(xhr, settings) {
                            // Also send via header for better compatibility
                            xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                        }
                    });

                    console.log('[CSRF] Protection enabled - Token will be sent with all AJAX requests');
                }
            })(jQuery);
            </script>

            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery-ui-1.12.1.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.tablesorter.combined.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.duration-picker.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.gridster.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.jbox-0.4.9.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.jbox-notice-0.4.9.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.inlinecomplete.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/jquery.ui-contextmenu.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/mustache.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/intro.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/dragscroll.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/moment.min.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/zoom.js"></script>
            <script type="text/javascript" src="//<?= CDN_DOMAIN ?>/js/app.min.js"></script>

        </body>
    </html>
        <?php
    }
}
