# Apache Hive DB extractor

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
        - `kinitPrincipal` - string (required): 
          - Name of the principal for the `kinit`, eg. `init/localhost@KEBOOLA.COM`.
          - It may be shortened if it is supported by your `krb5.conf`, eg. `init/localhost`.
        - `servicePrincipal` - string (required): 
          - Name of the principal for the ODBC connection, eg. `hive/localhost@KEBOOLA.COM`.
          - A fully qualified name must be used: `[service]/[host]@[realm]`
        - `config` - string (required): Content of the `krb5.conf` file.
        - `#keytab` - string (required): `base64` encoded content of the `*.keytab` file.
    - `connectThrough` - bool (optional, default `false`) 
        - If enabled:
            - Value from the `KBC_REALUSER` environment variable is used as the `DelegationUID` in the connection string.
            - if `KBC_REALUSER` is not set, a UserException is thrown.
        - To use this feature:
          - The SAML login to the Keboola Connection must be used.
          - SAML token must contain the required data and the stack must be set correctly.
    - `thriftTransport` - int (optional, default Binary (`0`) if you are connecting to Hive Server 1. SASL (`1`)
      if you are connecting to Hive Server 2.)
      - Binary (`0`) 
      - SASL (`1`)
      - HTTP (`2`)
    - `httpPath` - string (optional, default `/hive2` if using Windows Azure HDInsight Service (6). `/` if using non-Windows Azure HDInsight Service with Thrift Transport set to HTTP (2).)
    - `batchSize` - positive 32-bit int (optional, default `10000`)
      - it sets `RowsFetchedPerBlock` parameter
    - `verboseLogging` - bool (optional, default `false`)
      - when enabled it sets `LogLevel` to `6` (logs all driver activity)
      - `artifacts` feature in KBC has to be enabled as the logs are uploaded there
    - `ssl` - object (optional):
        - `enabled` bool (optional): Default `false`.
        - `ca` or `#ca` string (optional): 
          - Bundle of the trusted certificates in PEM/JKS format, see `caFileType`.
          - A special format `internal:my-certs.jks` may be used. 
            - Then the certificate is read from the component, from the `/var/bundled-files/my-certs.jks`.
            - Currently, the `/var/bundled-files/` directory is empty.
            - The component can be forked and bundled with a larger client certificate.
        - `caFileType` enum (optional, default `pem`):
          - `pem` - `ca` value is in [CRT/PEM format](https://serverfault.com/questions/9708/what-is-a-pem-file-and-how-does-it-differ-from-other-openssl-generated-key-file). 
          - `jks` - `ca` value is in base64 encoded [JKS format](https://en.wikipedia.org/wiki/Java_KeyStore).
        - `verifyServerCert` bool (optional): Default `true`.
        - `ignoreCertificateCn` bool (optional): Default `false`.
    - `ssh` - object (optional): Settings for SSH tunnel
        - `enabled` - bool (required):  Enables SSH tunnel
        - `sshHost` - string (required): IP address or hostname of SSH server
        - `sshPort` - integer (optional): SSH server port (default port is `22`)
        - `localPort` - integer (optional): SSH tunnel local port in Docker container (default `33006`)
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

## Global config

[Image or Stack Parameters](https://developers.keboola.com/extend/common-interface/config-file/#image-parameters) 
can be used to set global configuration for the extractor. This can be used if e.g. all configurations on the stack use the same Kerberos authentication.

The global configuration is stored under key `image_parameters.global_config` and has LOWER priority than the values in the `parameters`.

Example of the configuration that the extractor gets:
```
{
  "action": "testConnection",
  "image_parameters": {
    "global_config": {
      "db": {
        "authType": "IS OVERWRITTEN BY USER CONFIG",
        "kerberos": "IS OVERWRITTEN BY USER CONFIG"
        "ssl": {
          "enabled": true,
          "ca": "...",
          "caFileType": "jks"
        }
      }
    }
  },
  "parameters": {
    "db": {
      "host": "...",
      "port":  "...",
      "database": "...",
      "authType": "kerberos",
      "kerberos": {
        "principal": "...",
        "config": "...",
        "#keytab": "..."
      },
    }
  }
}

```

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

## License

MIT licensed, see [LICENSE](./LICENSE) file.
