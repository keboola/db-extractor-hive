export JAVA_HOME=/usr/lib/jvm/jre-openjdk

export HADOOP_USER_NAME=hdfs

export HADOOP_HOME=/usr/lib/hadoop
export HADOOP_LIBEXEC_DIR=$HADOOP_HOME/libexec
export HADOOP_COMMON_HOME=$HADOOP_HOME
export HADOOP_INSTALL=$HADOOP_HOME
export HADOOP_PREFIX=$HADOOP_HOME
export HADOOP_CONF_DIR=$HADOOP_HOME/etc/hadoop

export HIVE_HOME=/usr/lib/hive
export CLASSPATH=$CLASSPATH:/usr/lib/hadoop/*:/usr/lib/hadoop/lib/*:.
export CLASSPATH=$CLASSPATH:/usr/lib/hive/conf:/usr/lib/hive/*:/usr/lib/hive/lib/*:.

export TEZ_HOME=/usr/lib/tez
export TEZ_CONF_DIR=/etc/tez/conf
export TEZ_JARS=$TEZ_HOME

# For enabling hive to use the Tez engine
if [ -z "$HIVE_AUX_JARS_PATH" ]; then
  export HIVE_AUX_JARS_PATH="$TEZ_JARS"
else
  export HIVE_AUX_JARS_PATH="$HIVE_AUX_JARS_PATH:$TEZ_JARS"
fi

export HADOOP_CLASSPATH=${CLASSPATH}:${TEZ_CONF_DIR}:${TEZ_JARS}/*:${TEZ_JARS}/lib/*
