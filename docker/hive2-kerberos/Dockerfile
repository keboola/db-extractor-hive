FROM keboola-hive2-no-auth

# INSTALL KERBEROS
RUN yum install -y krb5-libs krb5-server krb5-workstation

# ADD KERBEROS CONFIGURATION
ADD files/kerberos/krb5.conf /etc/krb5.conf
ADD files/kerberos/kdc.conf /var/kerberos/krb5kdc/kdc.conf
ADD files/kerberos/kadm5.acl /var/kerberos/krb5kdc/kadm5.acl

# CREATE KERBEROS DATABASE
RUN /usr/sbin/kdb5_util create -s -P password

# ADD SUPERVISORD KERBEROS SCRIPT
ADD files/supervisord.d/kerberos.conf /etc/supervisord.d/kerberos.conf

# ADD HADOOP PRINCIPALS
RUN /usr/sbin/kadmin.local -q "addprinc -randkey hdfs/localhost@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey mapred/localhost@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey yarn/localhost@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey HTTP/localhost@KEBOOLA.COM"

# CREATE HADOOP KEYTAB FILES
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/hadoop/conf/hdfs.keytab hdfs/localhost HTTP/localhost"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/hadoop/conf/mapred.keytab mapred/localhost HTTP/localhost"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/hadoop/conf/yarn.keytab yarn/localhost HTTP/localhost"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/hadoop/conf/HTTP.keytab HTTP/localhost"
RUN chown hdfs:hadoop /etc/hadoop/conf/hdfs.keytab
RUN chown mapred:hadoop /etc/hadoop/conf/mapred.keytab
RUN chown yarn:hadoop /etc/hadoop/conf/yarn.keytab
RUN chown hdfs:hadoop /etc/hadoop/conf/HTTP.keytab
RUN chmod 644 /etc/hadoop/conf/*.keytab

# ENABLE HADOOP SECURITY
ADD files/conf/core-site.xml /etc/hadoop/conf/core-site.xml
ADD files/conf/hdfs-site.xml /etc/hadoop/conf/hdfs-site.xml

# ENABLE MAPRED SECURITY
ADD files/conf/mapred-site.xml /etc/hadoop/conf/mapred-site.xml
ADD files/conf/taskcontroller.cfg /etc/hadoop/conf/taskcontroller.cfg

# ENABLE YARN SECURITY
ADD files/conf/yarn-site.xml /etc/hadoop/conf/yarn-site.xml
ADD files/conf/container-executor.cfg /etc/hadoop/conf/container-executor.cfg
RUN chmod 6050 /etc/hadoop/conf/container-executor.cfg

# CREATE HIVE PRINCIPAL AND KEYTAB
RUN /usr/sbin/kadmin.local -q "addprinc -randkey hive/localhost@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/hive/conf/hive.keytab hive/localhost"

# ENABLE HIVE SECURITY
ADD files/conf/hive-site.xml /etc/hive/conf/hive-site.xml
RUN chown hive:hadoop /etc/hive/conf/hive.keytab
RUN chmod 644 /etc/hive/conf/hive.keytab

# ENABLE AUTHORIZATION IN HIVE SERVER
ADD files/conf/hiveserver2-site.xml /etc/hive/conf/hiveserver2-site.xml

# CREATE PRESTO PRINCIPAL AND KEYTAB
RUN /usr/sbin/kadmin.local -q "addprinc -randkey presto-server/presto-master.docker.cluster@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey presto-server/presto-worker.docker.cluster@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey presto-server/presto-worker-1.docker.cluster@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey presto-server/presto-worker-2.docker.cluster@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey HTTP/presto-master.docker.cluster@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey presto-client/presto-master.docker.cluster@KEBOOLA.COM"
RUN /usr/sbin/kadmin.local -q "addprinc -randkey hive/presto-master.docker.cluster@KEBOOLA.COM"
RUN mkdir -p /etc/presto/conf
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/presto/conf/presto-server.keytab presto-server/presto-master.docker.cluster presto-server/presto-worker.docker.cluster presto-server/presto-worker-1.docker.cluster presto-server/presto-worker-2.docker.cluster"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/presto/conf/presto-server-HTTP.keytab HTTP/presto-master.docker.cluster"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/presto/conf/presto-client.keytab presto-client/presto-master.docker.cluster"
RUN /usr/sbin/kadmin.local -q "xst -norandkey -k /etc/presto/conf/hive-presto-master.keytab hive/presto-master.docker.cluster"
RUN chmod 644 /etc/presto/conf/*.keytab

# CREATE SSL KEYSTORE
RUN keytool -genkeypair \
    -alias presto \
    -keyalg RSA \
    -keystore /etc/presto/conf/keystore.jks \
    -keypass password \
    -storepass password \
    -dname "CN=presto-master, OU=, O=, L=, S=, C="
RUN chmod 644 /etc/presto/conf/keystore.jks

# CREATE SSL FOR HIVE
RUN keytool -genkey \
    -alias hbase \
    -keyalg RSA \
    -keysize 1024 \
    -storepass PASS12345 \
    -validity 10000 \
    -keystore /etc/hive/conf/hive.jks \
    -dname "CN=hive2-kerberos, OU=, O=, L=, S=, C="

# CONVERT SSL KEYSTORE TO PEM FORMATS -> FOR TESTS
RUN echo "" | keytool -list -keystore /etc/hive/conf/hive.jks -rfc | sed -ne '/-BEGIN CERTIFICATE-/,/-END CERTIFICATE-/p' > /etc/hive/conf/hive.pem

# IMPORT TEST DATA
RUN BEELINE_ARGS="-u 'jdbc:hive2://localhost:10000/default;auth=kerberos;ssl=true;sslTrustStore=/etc/hive/conf/hive.jks;principal=hive/localhost@KEBOOLA.COM;'" /tmp/data/data-init.sh
RUN rm -rf /tmp/data

# EXPOSE KERBEROS PORTS
EXPOSE	88
EXPOSE	749

CMD /root/startup.sh
