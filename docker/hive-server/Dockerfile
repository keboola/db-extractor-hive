FROM bde2020/hive:2.3.2-postgresql-metastore

COPY ./sales.csv           /fixtures/sales.csv
COPY ./incremental.csv     /fixtures/incremental.csv
RUN tail -n +2    /fixtures/sales.csv     > /fixtures/sales_without_header.csv && \
    tail -n +2 /fixtures/incremental.csv  > /fixtures/incremental_without_header.csv

COPY custom-init.sh  /usr/local/bin/custom-init.sh
CMD custom-init.sh
