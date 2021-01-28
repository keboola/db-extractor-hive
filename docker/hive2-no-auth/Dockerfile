FROM centos:8

ENV DOCKERIZE_VERSION v0.3.0

ARG JDK_URL="http://download.oracle.com/otn-pub/java/jdk/8u131-b11/d54c1d3a095b4ff2b6607d096fa80163/jdk-8u131-linux-x64.rpm"
ARG JDK_RPM="jdk-8u131-linux-x64.rpm"
ARG JDK_PATH="/usr/java/jdk1.8.0_131"
ARG MYSQL_CONNECTOR_URL="https://dev.mysql.com/get/Downloads/Connector-J/mysql-connector-java-5.1.49.tar.gz"

RUN \
    # disable the notoriuosly unstable EPEL repo...
    rm -rf /etc/yum.repos.d/epel* && \
    yum install -y wget && \

    # install and trim Oracle JDK
    wget -nv --header "Cookie: oraclelicense=accept-securebackup-cookie" $JDK_URL && \
    rpm -ivh $JDK_RPM && rm $JDK_RPM && \
    rm -rf $JDK_PATH/*src.zip \
           $JDK_PATH/lib/missioncontrol \
           $JDK_PATH/lib/visualvm \
           $JDK_PATH/lib/*javafx* \
           $JDK_PATH/jre/lib/plugin.jar \
           $JDK_PATH/jre/lib/ext/jfxrt.jar \
           $JDK_PATH/jre/bin/javaws \
           $JDK_PATH/jre/lib/javaws.jar \
           $JDK_PATH/jre/lib/desktop \
           $JDK_PATH/jre/plugin \
           $JDK_PATH/jre/lib/deploy* \
           $JDK_PATH/jre/lib/*javafx* \
           $JDK_PATH/jre/lib/*jfx* \
           $JDK_PATH/jre/lib/amd64/libdecora_sse.so \
           $JDK_PATH/jre/lib/amd64/libprism_*.so \
           $JDK_PATH/jre/lib/amd64/libfxplugins.so \
           $JDK_PATH/jre/lib/amd64/libglass.so \
           $JDK_PATH/jre/lib/amd64/libgstreamer-lite.so \
           $JDK_PATH/jre/lib/amd64/libjavafx*.so \
           $JDK_PATH/jre/lib/amd64/libjfx*.so && \
    # install dockerize
    wget -nv https://github.com/jwilder/dockerize/releases/download/$DOCKERIZE_VERSION/dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
        && tar -C /usr/local/bin -xzvf dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
        && rm dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz && \
    # cleanup
    yum -y clean all && rm -rf /tmp/* /var/tmp/*

ENV JAVA_HOME $JDK_PATH/jre/
ENV PATH="/usr/lib/hive/bin:${PATH}"

# Setup CDH repo, pin the CDH distribution to a concrete version
RUN wget -nv http://archive.cloudera.com/cdh5/one-click-install/redhat/6/x86_64/cloudera-cdh-5-0.x86_64.rpm \
  && yum --nogpgcheck localinstall -y cloudera-cdh-5-0.x86_64.rpm \
  && rm cloudera-cdh-5-0.x86_64.rpm \
  && rpm --import http://archive.cloudera.com/cdh5/redhat/6/x86_64/cdh/RPM-GPG-KEY-cloudera \
  && sed -i '/^baseurl=/c\baseurl=https://archive.cloudera.com/cdh5/redhat/6/x86_64/cdh/5.9.1/' /etc/yum.repos.d/cloudera-cdh5.repo \

# Install hive, as it's needed by all child images
  && yum install -y hive

# Install supervisord
RUN yum install -y python3-setuptools python3-pip \
  && pip3 install supervisor \
  && mkdir /etc/supervisord.d/ \

# Cleanup
  && yum -y clean all && rm -rf /tmp/* /var/tmp/* \

# Alias log directories so that files in `/common` can use `/var/log/hadoop/*` format
  && mkdir -p /var/log/hadoop \
  && ln -s /var/log/hadoop-hdfs /var/log/hadoop/hdfs \
  && ln -s /var/log/hadoop-mapreduce /var/log/hadoop/mapreduce \
  && ln -s /var/log/hadoop-yarn /var/log/hadoop/yarn

# Copy supervisord startup script and base configs
COPY files/startup.sh /root/startup.sh
COPY files/supervisord.conf /etc/supervisord.conf
COPY files/supervisord.d/bootstrap.conf /etc/supervisord.d/bootstrap.conf
COPY files/change_timezone.sh /root/change_timezone.sh

# Add supervisord configs in child images
COPY files/supervisord.d/* /etc/supervisord.d/

### HIVE ###
# INSTALL HADOOP AND HIVE
RUN \
  yum install -y \
    hive \
    hive-server2 \
    zookeeper-server \
    hadoop-conf-pseudo \
  && yum -y clean all && rm -rf /tmp/* /var/tmp/*

# INSTALL MYSQL (Hive Metastore)
RUN \
    yum install -y mysql-server && \
    yum -y clean all && rm -rf /tmp/* /var/tmp/* && \
    mkdir -p /usr/share/java/ && \
    wget -nv $MYSQL_CONNECTOR_URL -O /usr/share/java/mysql-connector-java.tar.gz && \
    tar zxvf /usr/share/java/mysql-connector-java.tar.gz -C /usr/share/java/ && \
    cp /usr/share/java/mysql-connector-java-*/mysql-connector-java-*-bin.jar /usr/lib/hive/lib  && \
    rm -rf /usr/share/java/mysql-connector-java-*

# SETUP SOCKS PROXY
RUN yum install -y openssh openssh-clients openssh-server
RUN ssh-keygen -A
RUN ssh-keygen -t rsa -b 4096 -C "foo@bar.com" -N "" -f /root/.ssh/id_rsa
RUN cp /root/.ssh/id_rsa.pub /root/.ssh/authorized_keys
ADD files/socks-proxy.sh /root/socks-proxy.sh

# ADD SETUP AND BOOTSTRAP SCRIPTS
COPY files/bootstrap.sh /root/

# CREATE CONF DIRECTORIES
RUN mkdir /tmp/hadoop_conf
COPY files/conf/ /tmp/hadoop_conf

# RUN SETUP script
COPY files/setup-hdfs.sh /root/
RUN /root/setup-hdfs.sh
COPY files/setup-metastore.sh /root/
RUN /root/setup-metastore.sh
RUN rm -rf /tmp/* /var/tmp/*

# IMPORT TEST DATA
COPY files/data/ /tmp/data
RUN tail -n +2 /tmp/data/sales.csv        > /tmp/data/sales_without_header.csv && \
    tail -n +2 /tmp/data/incremental.csv  > /tmp/data/incremental_without_header.csv
RUN chown -R hive:hdfs /tmp/data
RUN /tmp/data/data-init.sh
RUN rm -rf /tmp/data

# HDFS PORTS
EXPOSE 1004 1006 8020 50010 50020 50070 50075 50470

# YARN PORTS
EXPOSE 8030 8031 8032 8033 8040 8041 8042 8088 10020 19888

# HIVE PORT
EXPOSE 9083 10000

# SOCKS PORT
EXPOSE 1180

CMD /root/startup.sh
