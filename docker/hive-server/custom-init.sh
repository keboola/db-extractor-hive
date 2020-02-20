#!/bin/bash
set -Eeuo pipefail

# Run original startup script (hive server) in background
startup.sh &
bg_pid=$!

# Wait for server ready
echo "custom-init.sh: Waiting for server ...";
until
  beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e 'SHOW TABLES;' >/dev/null 2>&1
do sleep 1; done
echo "custom-init.sh: OK. Server ready";

# Import data
echo "custom-init.sh: Importing testing data ...";
beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e "
  CREATE TABLE internal (product_name string, price double, comment string);
  TRUNCATE TABLE internal;
  INSERT INTO internal VALUES
    ('car1', 12.34, null),
    ('car2', 56.78, 'comment');
";
beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e "
  CREATE EXTERNAL TABLE IF NOT EXISTS incremental (id int, timestamp_col timestamp, date_col date, float_col float, double_col double, string_col varchar(255))
  ROW FORMAT DELIMITED FIELDS TERMINATED BY ',' LINES TERMINATED BY '\n';;
  LOAD DATA LOCAL INPATH '/fixtures/incremental_without_header.csv' OVERWRITE INTO TABLE incremental;
";
beeline -u "jdbc:hive2://localhost:$HIVE_DB_PORT" -n "$HIVE_DB_USER" -p "$HIVE_DB_PASSWORD" -e "
  CREATE EXTERNAL TABLE IF NOT EXISTS sales (usergender varchar(255), usercity varchar(255), usersentiment int, zipcode varchar(255), sku varchar(255), createdat varchar(255), category varchar(255), price float, county varchar(255), countycode varchar(255), userstate varchar(255), categorygroup varchar(255))
  ROW FORMAT DELIMITED FIELDS TERMINATED BY ',' LINES TERMINATED BY '\n';;
  LOAD DATA LOCAL INPATH '/fixtures/sales_without_header.csv' OVERWRITE INTO TABLE sales;
";
echo "custom-init.sh: OK. Testing data imported.";

# Wait for server exit
wait $bg_pid
exit $?
