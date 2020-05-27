#!/bin/bash

# Check if HIVE_VERSION is defined
if [ -z "$HIVE_VERSION" ]; then
  echo "HIVE_VERSION env variable is not defined."
  echo "Please specify HIVE_VERSION env variable using export HIVE_VERSION=..."
  exit 1
fi

# Convert version separated by dots to integer to compare
function version { echo "$@" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }'; }

# Default values
HIVE_DB_HOST="hive-server"
HIVE_DB_PORT="10000"
HIVE_DB_DATABASE="default"
HIVE_DB_USER="hive"
HIVE_DB_PASSWORD="p#a!s@sw:o&r%^d"
HIVE_SERVER_IMAGE_DIR="docker/hive-v1-v2"

HADOOP_NAMENODE_TAG="namenode:2.0.0-hadoop2.7.4-java8"
HADOOP_DATANODE_TAG="datanode:2.0.0-hadoop2.7.4-java8"
HADOOP_RESOURCEMANAGER_TAG="resourcemanager:2.0.0-hadoop2.7.4-java8"
HADOOP_NODEMANAGER_TAG="resourcemanager:2.0.0-hadoop2.7.4-java8"
HADOOP_HISTORYSERVER_TAG="historyserver:2.0.0-hadoop2.7.4-java8"

HADOOP_NAMENODE_WAIT_PORT=50070
HADOOP_DATANODE_WAIT_PORT=50075
HADOOP_RESOURCEMANAGER_WAIT_PORT=8088

# Version 2.0 ... 2.2
# Bug in Hive DB LDAP auth, required full name as username
if [[ "$(version "2.0.0")" -le "$(version $HIVE_VERSION)" ]] &&
   [[ "$(version "2.3.0")" -gt "$(version $HIVE_VERSION)" ]]; then
    export HIVE_DB_USER="uid=${HIVE_DB_USER},dc=keboola-test,dc=com"
fi

# Version 3.0+ requires Hadoop 3
if [[ "$(version "3.0.0")" -le "$(version $HIVE_VERSION)" ]]; then
  echo "Hive DB versions 3.0+ are not supported by testing environment."
  exit 1
fi

# Write ENV to file
cat > .env << EndOfEnv
HIVE_VERSION=$HIVE_VERSION
HIVE_DB_HOST=$HIVE_DB_HOST
HIVE_DB_PORT=$HIVE_DB_PORT
HIVE_DB_DATABASE=$HIVE_DB_DATABASE
HIVE_DB_USER=$HIVE_DB_USER
HIVE_DB_PASSWORD=$HIVE_DB_PASSWORD
HIVE_SERVER_IMAGE_DIR=$HIVE_SERVER_IMAGE_DIR

HADOOP_NAMENODE_TAG=$HADOOP_NAMENODE_TAG
HADOOP_DATANODE_TAG=$HADOOP_DATANODE_TAG
HADOOP_RESOURCEMANAGER_TAG=$HADOOP_RESOURCEMANAGER_TAG
HADOOP_NODEMANAGER_TAG=$HADOOP_NODEMANAGER_TAG
HADOOP_HISTORYSERVER_TAG=$HADOOP_HISTORYSERVER_TAG

HADOOP_NAMENODE_WAIT_PORT=$HADOOP_NAMENODE_WAIT_PORT
HADOOP_DATANODE_WAIT_PORT=$HADOOP_DATANODE_WAIT_PORT
HADOOP_RESOURCEMANAGER_WAIT_PORT=$HADOOP_RESOURCEMANAGER_WAIT_PORT

EndOfEnv

# Create external network
# Issue in docker + hadoop: https://github.com/docker/compose/issues/229#issuecomment-234669078
docker network inspect dbwriterhive >/dev/null 2>&1 || docker network create  dbwriterhive >/dev/null 2>&1
