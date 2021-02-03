# Apache Hive DB extractor

[![Build Status](https://travis-ci.com/keboola/db-extractor-hive.svg?branch=master)](https://travis-ci.com/keboola/db-extractor-hive)

[KBC](https://www.keboola.com/product/) Docker app for extracting data from [Apache Hive](https://hive.apache.org/) database.

See [Extractors for SQL Databases](https://help.keboola.com/components/extractors/database/sqldb/) for more documentation.

# Usage

## Configuration

The configuration `config.json` contains following properties in `parameters` key: 

*Note:* `query` or `table` must be specified.

- `db` - object (required): Connection settings
    - `host` - string (required): IP address or hostname of Apache Hive DB server
    - `port` - integer (required): Server port (default port is `10000`)
    - `database` - string (required): Database to connect to.
    - `authType` - enum (optional): Type of the authentication.
        - `password` (default) - user/password LDAP authentication.
        - `kerberos` - Kerberos authentication.
    - `user` - string (required if `authType = password`): User with correct access rights
    - `#password` - string (required if `authType = password`): Password for given `user`
    - `kerberos` - object (required if `authType = kerberos`)
        - `principal` - string (required): Name of the Kerberos principal - used for the `kinit`.
        - `config` - string (required): Content of the `krb5.conf` file.
        - `#keytab` - string (required): `base64` encoded content of the `*.keytab` file.
    - `ssl` - object (optional):
        - `enabled` bool (optional): Default `false`.
        - `ca` or `#ca` string (optional): bundle of the trusted certificates in PEM/JKS format, see `caFileType`.
        - `caFileType` enum (optional, default `pem`):
          - `pem` - `ca` value is in [CRT/PEM format](https://serverfault.com/questions/9708/what-is-a-pem-file-and-how-does-it-differ-from-other-openssl-generated-key-file). 
          - `jks` - `ca` value is in base64 encoded [JKS format](https://en.wikipedia.org/wiki/Java_KeyStore).
        - `verifyServerCert` bool (optional): Default `true`.
        - `ignoreCertificateCn` bool (optional): Default `false`.
    - `ssh` - object (optional): Settings for SSH tunnel
        - `enabled` - bool (required):  Enables SSH tunnel
        - `sshHost` - string (required): IP address or hostname of SSH server
        - `sshPort` - integer (optional): SSH server port (default port is `22`)
        - `localPort` - integer (required): SSH tunnel local port in Docker container (default `33006`)
        - `user` - string (optional): SSH user (default same as `db.user`)
        - `compression`  - bool (optional): Enables SSH tunnel compression (default `false`)
        - `keys` - object (optional): SSH keys
            - `public` - string (optional): Public SSH key
            - `#private` - string (optional): Private SSH key
- `query` - string (optional): SQL query whose output will be extracted
- `table` - object (optional): Table whose will be extracted
    - `tableName` - string (required)
    - `schema` - string (required)
- `columns` - array (optional): List of columns to export (default all columns)
- `outputTable` - string (required): Name of the output table 
- `incremental` - bool (optional):  Enables [Incremental Loading](https://help.keboola.com/storage/tables/#incremental-loading)
- `incrementalFetchingColumn` - string (optional): Name of column for [Incremental Fetching](https://help.keboola.com/components/extractors/database/#incremental-fetching)
- `incrementalFetchingLimit` - integer (optional): Max number of rows fetched per one run
- `primaryKey` - string (optional): Sets primary key to specified column in output table
- `retries` - integer (optional): Number of retries if an error occurred

## Examples

Simple query:
```json
{
  "parameters": {
    "db": {
      "host": "hive-server",
      "port": 10000,
      "database": "default",
      "user": "admin",
      "#password": "******"
    },
    "query": "SELECT * FROM sales LIMIT 100",
    "outputTable": "in.c-main.sales"
  }
}
```

Export only specified columns:
```json
{
  "parameters": {
    "db": { "host": "..." },
    "table": {
      "tableName": "internal",
      "schema": "default"
    },
    "columns": ["price", "product_name"],
    "outputTable": "in.c-main.internal"
  }
}
```

Incremental fetching using timestamp column:
```json
{
  "parameters": {
    "db": { "host": "..." },
    "outputTable": "in.c-main.incremental",
    "primaryKey": ["id"],
    "incremental": true,
    "incrementalFetchingColumn": "timestamp_col",
    "incrementalFetchingLimit": 100
  }
}
```

## Development
 
Clone this repository and init the workspace with following command.

AWS secrets are required to download the ODBC driver.

```
git clone https://github.com/keboola/db-extractor-hive
cd db-extractor-hive
export AWS_ACCESS_KEY_ID=
export AWS_SECRET_ACCESS_KEY=
docker-compose build
docker-compose run --rm wait
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
