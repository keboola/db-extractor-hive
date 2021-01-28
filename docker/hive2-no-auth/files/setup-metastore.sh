#!/bin/bash -ex

setcap -r /usr/libexec/mysqld
mysqld --initialize-insecure --user=mysql

mysqld --user=mysql &
sleep 10s

echo "CREATE USER 'root'@'%' IDENTIFIED BY 'root';" | mysql
echo "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES;" | mysql
echo "CREATE DATABASE metastore" | mysql
/usr/bin/mysqladmin -u root password 'root'

echo "Starting metastore initialization.";
schematool -dbType mysql -initSchema -verbose
echo "OK. Metastore initialized.";

killall mysqld
sleep 10s
chown mysql:mysql /var/log/mysql/

/etc/init.d/zookeeper-server init
mkdir -p /var/log/zookeeper
chown -R zookeeper:zookeeper /var/log/zookeeper
