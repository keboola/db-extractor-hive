#!/bin/bash -ex

/usr/sbin/krb5kdc -n -P /var/run/krb5kdc.pid & sleep 2

su -c "echo 'Y' | hdfs namenode -format" hdfs
su -c "echo 'Y' | hdfs datanode -format" hdfs

killall krb5kdc || true
