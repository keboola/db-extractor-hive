#!/bin/bash
set -Eeuo pipefail

# Run original startup script (hive server) in background
startup.sh &
bg_pid=$!

# Wait for server ready
echo "Waiting for server ...";
until
  beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e 'SHOW TABLES;' >/dev/null 2>&1
do sleep 1; done
echo "OK. Server ready";

# Import data
echo "Importing testing data ...";
beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e "
  CREATE TABLE internal (split_by double,mandt string,matnr string,werks string,lgort string,menge_cesta double,menge_dispo double,menge_sklad double,meins string,bwkey string,bukrs string,waers string,verpr double);
  INSERT INTO internal VALUES (12, 'a', 'bcsd', 'sdf', 'ddd', 234.3, 23.3, 234.3, 'ss', 'sdf', 'sdf', 'sdf', 234.234);
";
beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e "
  CREATE EXTERNAL TABLE IF NOT EXISTS sales (usergender varchar(255), usercity varchar(255), usersentiment int, zipcode varchar(255), sku varchar(255), createdat varchar(255), category varchar(255), price float, county varchar(255), countycode varchar(255), userstate varchar(255), categorygroup varchar(255));
  LOAD DATA LOCAL INPATH '/fixtures/sales.csv' OVERWRITE INTO TABLE sales;
";
echo "OK. Testing data imported.";

# Wait for server exit
wait $bg_pid
exit $?
