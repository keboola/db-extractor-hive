FROM bde2020/hive:2.3.2-postgresql-metastore

COPY ./sales.csv     /fixtures/sales.csv
COPY custom-init.sh  /usr/local/bin/custom-init.sh
CMD custom-init.sh
