FROM centos:7

# Security: Update packages and install security updates
RUN yum update -y && \
    yum install -y epel-release && \
    yum update -y --security

RUN yum install -y http://www.percona.com/downloads/percona-release/redhat/0.1-4/percona-release-0.1-4.noarch.rpm && \
    yum install -y Percona-Server-server-57 && \
    yum -y update

COPY ./.docker/mysql/my.cnf /etc/my.cnf
COPY ./.docker/mysql/setup.sql /root/setup.sql

# Create mysql user instead of running as root
RUN useradd -r -s /bin/false mysql && \
    mkdir -p /var/lib/mysql /var/run/mysqld && \
    chown -R mysql:mysql /var/lib/mysql /var/run/mysqld

RUN yum clean all

# Secure MySQL startup
CMD mysqld --user=mysql --initialize-insecure && \
    mysqld --user=mysql && tail -F /var/log/mysql/error.log
