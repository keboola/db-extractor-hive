FROM keboola-hive2-no-auth:latest

# Import test data
RUN BEELINE_ARGS="-u 'jdbc:hive2://localhost:10000'" /tmp/data/data-init.sh
RUN rm -rf /tmp/data

# Modified configuration
COPY files/conf/hive-site.xml /etc/hive/conf/hive-site.xml
