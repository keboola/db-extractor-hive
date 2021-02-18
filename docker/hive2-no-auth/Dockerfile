FROM centos:8

ARG MYSQL_CONNECTOR_URL="https://dev.mysql.com/get/Downloads/Connector-J/mysql-connector-java-5.1.49.tar.gz"

# Install dependencies
RUN \
    # disable the notoriuosly unstable EPEL repo...
    rm -rf /etc/yum.repos.d/epel* && \
    # install tools
    yum install -y wget which && \
    # add bigtop repo
    wget -nv -O /etc/yum.repos.d/bigtop.repo https://archive.apache.org/dist/bigtop/bigtop-1.5.0/repos/centos-8/bigtop.repo && \
    yum install -y \
        nano \
        hive \
        hive-server2 \
        hive-hbase \
        hive-metastore \
        hadoop-conf-pseudo \
        hadoop \
        tez \
        zookeeper-server \
        openssh \
        openssh-clients \
        openssh-server \
        java-1.8.0-openjdk \
        python3-setuptools \
        python3-pip \
        mysql-server \
        && \
    # cleanup
    yum -y clean all && rm -rf /tmp/* /var/tmp/*

# Install supervisord
RUN pip3 install supervisor && \
    mkdir /etc/supervisord.d/ && \
    # Alias log directories so that files in `/common` can use `/var/log/hadoop/*` format
    mkdir -p /var/log/hadoop && \
    ln -s /var/log/hadoop-hdfs /var/log/hadoop/hdfs && \
    ln -s /var/log/hadoop-mapreduce /var/log/hadoop/mapreduce && \
    ln -s /var/log/hadoop-yarn /var/log/hadoop/yarn

# Install MySQL connector
RUN mkdir -p /usr/share/java/ && \
    wget -nv $MYSQL_CONNECTOR_URL -O /usr/share/java/mysql-connector-java.tar.gz && \
    tar zxvf /usr/share/java/mysql-connector-java.tar.gz -C /usr/share/java/ && \
    cp /usr/share/java/mysql-connector-java-*/mysql-connector-java-*-bin.jar /usr/lib/hive/lib  && \
    rm -rf /usr/share/java/mysql-connector-java-*

# Setup Socks proxy
RUN ssh-keygen -A && \
    ssh-keygen -t rsa -b 4096 -C "foo@bar.com" -N "" -f /root/.ssh/id_rsa && \
    cp /root/.ssh/id_rsa.pub /root/.ssh/authorized_keys
ADD files/socks-proxy.sh /root/socks-proxy.sh

# Copy supervisord startup script and base configs
COPY files/startup.sh /root/startup.sh
COPY files/supervisord.conf /etc/supervisord.conf
COPY files/supervisord.d/bootstrap.conf /etc/supervisord.d/bootstrap.conf
COPY files/change_timezone.sh /root/change_timezone.sh
COPY files/supervisord.d/* /etc/supervisord.d/
COPY files/setup-hdfs.sh /root/
COPY files/setup-metastore.sh /root/
COPY files/bootstrap.sh /root/

# Setup HDFS
RUN /root/setup-hdfs.sh

# Copy configuration
COPY /files/conf/hive-env.sh /etc/hive/conf
COPY /files/conf/hadoop-env.sh /etc/hadoop/conf
COPY /files/conf/hive-site.xml /etc/hive/conf
COPY /files/conf/tez-site.xml /etc/tez/conf
COPY /files/conf/core-site.xml /etc/hadoop/conf
COPY /files/conf/hdfs-site.xml /etc/hadoop/conf
COPY /files/conf/mapred-site.xml /etc/hadoop/conf
COPY /files/conf/yarn-site.xml /etc/hadoop/conf

# Setup metastore
RUN /root/setup-metastore.sh

## Copy test data
COPY files/data/ /tmp/data
RUN tail -n +2 /tmp/data/sales.csv        > /tmp/data/sales_without_header.csv && \
    tail -n +2 /tmp/data/incremental.csv  > /tmp/data/incremental_without_header.csv
RUN chown -R hive:hdfs /tmp/data

# HDFS ports
EXPOSE 1004 1006 8020 50010 50020 50070 50075 50470

# YARN ports
EXPOSE 8030 8031 8032 8033 8040 8041 8042 8088 10020 19888

# HIVE ports
EXPOSE 9083 10000

# SOCKS ports
EXPOSE 1180

CMD /root/startup.sh
