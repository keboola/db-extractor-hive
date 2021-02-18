#!/bin/bash -ex

# format namenode
chown hdfs:hdfs /var/lib/hadoop-hdfs/cache/

# workaround for 'could not open session' bug as suggested here:
# https://github.com/docker/docker/issues/7056#issuecomment-49371610
rm -f /etc/security/limits.d/hdfs.conf
su -c "echo 'N' | hdfs namenode -format" hdfs

# start hdfs
su -c "hdfs datanode  2>&1 > /var/log/hadoop/hdfs/hadoop-hdfs-datanode.log" hdfs&
su -c "hdfs namenode  2>&1 > /var/log/hadoop/hdfs/hadoop-hdfs-namenode.log" hdfs&

# wait for process starting
sleep 10

# exec cloudera hdfs init script
/usr/lib/hadoop/libexec/init-hdfs.sh

# init hive directories
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -mkdir /user/hive/warehouse'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -chmod -R 1777 /user/hive/warehouse'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -chown hive /user/hive/warehouse'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -chmod g+w /tmp'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -mkdir /tmp/hadoop-yarn/staging'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -chmod -R 1777 /tmp/hadoop-yarn'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -chmod -R 1777 /tmp/hadoop-yarn/staging'
su -s /bin/bash hdfs -c '/usr/bin/hadoop fs -chmod -R 1777 /tmp'

# stop hdfs
killall java || true
