English | [简体中文](./README-CN.md)

PDO_TAOS is a PDO driver for [TDengine](https://github.com/taosdata/TDengine) database.

[TDengine ](https://github.com/taosdata/TDengine)has agreed to use library files to develop PHP extension.

# What is TDengine？

TDengine is an open-sourced big data platform under [GNU AGPL v3.0](http://www.gnu.org/licenses/agpl-3.0.html), designed and optimized for the Internet of Things (IoT), Connected Cars, Industrial IoT, and IT Infrastructure and Application Monitoring. Besides the 10x faster time-series database, it provides caching, stream computing, message queuing and other functionalities to reduce the complexity and cost of development and operation.

- **10x Faster on Insert/Query Speeds**: Through the innovative design on storage, on a single-core machine, over 20K requests can be processed, millions of data points can be ingested, and over 10 million data points can be retrieved in a second. It is 10 times faster than other databases.

- **1/5 Hardware/Cloud Service Costs**: Compared with typical big data solutions, less than 1/5 of computing resources are required. Via column-based storage and tuned compression algorithms for different data types, less than 1/10 of storage space is needed.

- **Full Stack for Time-Series Data**: By integrating a database with message queuing, caching, and stream computing features together, it is no longer necessary to integrate Kafka/Redis/HBase/Spark or other software. It makes the system architecture much simpler and more robust.

- **Powerful Data Analysis**: Whether it is 10 years or one minute ago, data can be queried just by specifying the time range. Data can be aggregated over time, multiple time streams or both. Ad Hoc queries or analyses can be executed via TDengine shell, Python, R or Matlab.

- **Seamless Integration with Other Tools**: Telegraf, Grafana, Matlab, R, and other tools can be integrated with TDengine without a line of code. MQTT, OPC, Hadoop, Spark, and many others will be integrated soon.

- **Zero Management, No Learning Curve**: It takes only seconds to download, install, and run it successfully; there are no other dependencies. Automatic partitioning on tables or DBs. Standard SQL is used, with C/C++, Python, JDBC, Go and RESTful connectors.

# Install PDO_TAOS
```bash
phpize
./configure
make && make install
```

## Install TDengine
When PDO_TAOS is compiled, libtaos.so is required. The default path is: /usr/lib/libtaos.so, pointing to: /usr/local/taos/driver/libtaos.so.2.x.x.x. So you need to install the TDengine database first. 



## Enable pdo_taos extension

Edit the php.ini file or add a new configuration file in the php.d directory.  

```ini
extension=pdo_taos.so
```



# Examples

## 0. Create the database

Create the database on the client.

## 1. Connect to TDengine database 

```php
$dbh = new PDO("taos:host=127.0.0.1;dbname=test", "root", "taosdata");
```



## 2. CREATE create data table 

Creating a data table is similar to `MySQ`L syntax, and the data types are similar, but there are differences. 

`TDengine` has its own database type, `NCHAR` is similar to MySQL's `VARCHAR`, and `BINARY` is similar to MySQL's `TEXT`. 

`TDengine` does not have keywords such as `PRIMARY KEY`, `AUTO INCREMENT`, `DEFAULT NULL`, etc. 

The first column of `TDengine` must be of type `TIMESTAMP`.  



Example:

```php
$dbh = new PDO("taos:host=127.0.0.1;dbname=demo", "root", "taosdata");

$sql = "create table device_log_1000 (
created_timestamp TIMESTAMP,
v_bool BOOL,
v_tinyint TINYINT,
v_smallint SMALLINT,
v_int INT,
v_bigint BIGINT,
v_float FLOAT,
v_double DOUBLE,
v_binary BINARY(60),
v_nchar NCHAR(40))";
$result = $dbh->exec($sql);

var_dump($result);
```

 `$result` returns 0 if execution succeeds, -1 if fails.  



After execution, log in to the `TDengine` database client to view the data table structure. 

```sql
taos> USE DEMO;
Database changed.

taos> SHOW CREATE TABLE device_log_1000;
             Table              |          Create Table          |
==================================================================
 device_log_1000                | create table device_log_100... |
Query OK, 1 row(s) in set (0.002146s)

taos> SHOW CREATE TABLE device_log_1000\G;
*************************** 1.row ***************************
       Table: device_log_1000
Create Table: create table device_log_1000 (created_timestamp TIMESTAMP,v_bool BOOL,v_tinyint TINYINT,v_smallint SMALLINT,v_int INT,v_bigint BIGINT,v_float FLOAT,v_double DOUBLE,v_binary BINARY(60),v_nchar NCHAR(40))
Query OK, 1 row(s) in set (0.000267s)

taos> 
```



## 3. INSERT insert data 

### Two ways to insert data:  

1. Call the `PDO::exec` method to directly execute the INSERT SQL statement.
2. Call the `PDO::prepare` method to perform preprocessing operations. 



### 3.1 PDO::exec  

```php
$dbh = new PDO("taos:host=127.0.0.1;dbname=demo", "root", "taosdata");

$sql = "INSERT INTO device_log_100 (
created_timestamp,
v_bool,
v_tinyint,
v_smallint,
v_int,
v_bigint,
v_float,
v_double,
v_binary,
v_nchar
) VALUES (
NOW,
1,
120,
1200,
12000,
120000,
9.09,
9.000009,
'hello TDengine',
'hello PHP'
)";

$result = $dbh->exec($sql);
var_dump($result);
```

 `$result` returns 0 if execution succeeds, -1 if fails.  

There is no way to get the number of rows affected. 



Notice: 

Be sure to call the `exec` method, not the `query` method.  



### 3.2 PDO::prepare

```php
<?php
try {
    $dbh = new PDO("taos:host=127.0.0.1;dbname=demo", "root", "taosdata");

    $sql = "INSERT INTO device_log_100 (created_timestamp, v_bool, v_tinyint, v_smallint, v_int, v_bigint, v_float, v_double, v_binary, v_nchar ) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $sth = $dbh->prepare($sql);

    $v1 = intval(microtime(true) * 1000);
    $v2 = 1;
    $v3 = 100;
    $v4 = 2000;
    $v5 = 20000;
    $v6 = 200000;
    $v7 = 8.08;
    $v8 = 8.000008;
    $v9 = "TDengine test";
    $v10 = "Taos test";

    $sth->bindParam(1, $v1, PDO::PARAM_TAOS_TIMESTAMP);
    $sth->bindParam(2, $v2, PDO::PARAM_TAOS_BOOL);
    $sth->bindParam(3, $v3, PDO::PARAM_TAOS_TINYINT);
    $sth->bindParam(4, $v4, PDO::PARAM_TAOS_SMALLINT);
    $sth->bindParam(5, $v5, PDO::PARAM_TAOS_INT);
    $sth->bindParam(6, $v6, PDO::PARAM_TAOS_BIGINT);
    $sth->bindParam(7, $v7, PDO::PARAM_TAOS_FLOAT);
    $sth->bindParam(8, $v8, PDO::PARAM_TAOS_DOUBLE);
    $sth->bindParam(9, $v9, PDO::PARAM_TAOS_BINARY);
    $sth->bindParam(10, $v10, PDO::PARAM_TAOS_NCHAR);

    $result = $sth->execute();
    var_dump($result);

    $rowCount = $sth->rowCount()
    var_dump($rowCount);
} catch (Exception $e) {
    printf("%d, %s\n", $e->getCode(), $e->getMessage());
}
?>
```
 `$result` returns true if execution succeeds, false if fails.  

 `$rowCount` represents the number of rows affected.  



#### Placeholder

Placeholder supports parameter names in the form of `:name`, and also in the form of `?`. 



#### Type of data 

Please forget PDO predefined types such as `PDO::PARAM_BOOL`, `PDO::PARAM_INT`, `PDO::PARAM_STR`, etc. 



If the field type of the data table can be obtained in advance when performing the insert operation, and then according to the type of the bound data, it can be perfectly compatible, such as:

MySQL's field types are INT, SMALLINT, TINYINT, BIGINT, and the binding parameter is PDO::PARAM_INT, which can also be inserted.

However, when the TDengine API executes the INSERT operation, it cannot obtain the field type of the data table in advance, and can only bind parameters if it is perfectly consistent with the fields of the data table.



The data types starting with PDO::PARAM_TAOS_ are all custom types, corresponding to the TSDB_DATA_TYPE_ data types of TDengine respectively.



For example:

**PARAM_TAOS_INT => TSDB_DATA_TYPE_INT**

**PARAM_TAOS_FLOAT => TSDB_DATA_TYPE_FLOAT**

**PARAM_TAOS_TIMESTAMP => TSDB_DATA_TYPE_TIMESTAMP**



But the two sides are not equal.

Constants such as `TSDB_DATA_TYPE_INT` are partially equal the PDO predefined constants value, resulting in conflict when judging the data type, so the difference of `6000` is set artificially.

Just for illustration, no impact on development. 



### 3.3 Do not mix

`PDO::exec` writes the complete SQL statement without placeholders.

`PDO::prepare` must use `bindParam` or `bindValue` to avoid preparing SQL without placeholders, otherwise an error will be reported. 



## 4. SELECT query

Two ways to query data: 

1. Call the `PDO::query` method to directly execute the SELECT SQL statement. 
2. Call the `PDO::prepare` method to perform preprocessing operations  



### 4.1 PDO::query

```php
<?php
$dbh = new PDO("taos:host=127.0.0.1;dbname=demo", "root", "taosdata");

$t1 = strtotime("2022-01-27 16:36:12");
$t2 = strtotime("2022-01-27 16:48:13");

$start_time = intval($t1 * 1000);
$end_time = intval($t2 * 1000);

$sql = "SELECT * FROM device_log_100 WHERE created_timestamp >= $start_time AND created_timestamp <= $end_time LIMIT 3 OFFSET 0";
$sth = $dbh->query($sql);

$result = $sth->fetchAll(PDO::FETCH_ASSOC);
var_dump($result);
?>
```

The execution result is as follows:  

```php
array(3) {
  [0]=>
  array(10) {
    ["created_timestamp"]=>
    string(23) "2022-01-27 16:36:12.038"
    ["v_bool"]=>
    string(1) "1"
    ["v_tinyint"]=>
    string(3) "100"
    ["v_smallint"]=>
    string(4) "2000"
    ["v_int"]=>
    string(5) "20000"
    ["v_bigint"]=>
    string(6) "200000"
    ["v_float"]=>
    string(9) "-71.68000"
    ["v_double"]=>
    string(11) "8.000008000"
    ["v_binary"]=>
    string(13) "TDengine test"
    ["v_nchar"]=>
    string(9) "Taos test"
  }
  [1]=>
  array(10) {
    ["created_timestamp"]=>
    string(23) "2022-01-27 16:36:49.146"
    ["v_bool"]=>
    string(1) "1"
    ["v_tinyint"]=>
    string(3) "100"
    ["v_smallint"]=>
    string(4) "2000"
    ["v_int"]=>
    string(5) "20000"
    ["v_bigint"]=>
    string(6) "200000"
    ["v_float"]=>
    string(9) "-71.68000"
    ["v_double"]=>
    string(11) "8.000008000"
    ["v_binary"]=>
    string(13) "TDengine test"
    ["v_nchar"]=>
    string(9) "Taos test"
  }
  [2]=>
  array(10) {
    ["created_timestamp"]=>
    string(23) "2022-01-27 16:36:55.365"
    ["v_bool"]=>
    string(1) "1"
    ["v_tinyint"]=>
    string(3) "100"
    ["v_smallint"]=>
    string(4) "2000"
    ["v_int"]=>
    string(5) "20000"
    ["v_bigint"]=>
    string(6) "200000"
    ["v_float"]=>
    string(9) "-71.68000"
    ["v_double"]=>
    string(11) "8.000008000"
    ["v_binary"]=>
    string(13) "TDengine test"
    ["v_nchar"]=>
    string(9) "Taos test"
  }
}
```

Note: `created_timestamp` uses the `timestamp` type when querying, but the query result formats the `time type`, which is consistent with the `TDengine client` query results.  



### 4.2 PDO::prepare

```php
<?php
$dbh = new PDO("taos:host=127.0.0.1;dbname=demo", "root", "taosdata");

$t1 = strtotime("2022-01-27 16:36:12");
$t2 = strtotime("2022-01-27 16:48:13");

$start_time = intval($t1 * 1000);
$end_time = intval($t2 * 1000);


$sql = "SELECT * FROM device_log_100 WHERE created_timestamp >= :start_time AND created_timestamp <= :end_time LIMIT 3 OFFSET 0";

$sth = $dbh->prepare($sql);

$sth->bindParam("start_time", $start_time, PDO::PARAM_TAOS_TIMESTAMP);
$sth->bindParam("end_time", $end_time, PDO::PARAM_TAOS_TIMESTAMP);


$sth->execute();
$result = $sth->fetchAll(PDO::FETCH_ASSOC);
var_dump($result);
?>
```

Note: `created_timestamp` uses the `timestamp` type when querying, but the query result formats the `time type`, which is consistent with the `TDengine client` query results.  



For other bound parameters, the data type should be the same as the field type of the data table. If using `PDO::PARAM_INT` or `PDO::PARAM_STR` happens to work fine, it's just luck, not recommended.  



No one would have thought that weakly typed PHP requires strong consistency between the **data type** and the **data table field type** when operating the `TDengine` database.  



## 5. UPDATE update data

`TDengine` does not support modification operations by default. Data with duplicate timestamps is written to a table that does not support data updates, and **the later written data** will be **discarded**. 

If the user needs the update function of the data, when building the database, he only needs to specify the **update** option of the database as **1**.  

Example：

```sql
CREATE DATABASE demo UPDATE 1
```

When writing data with duplicate timestamps to a table that supports the data update function, the **old data** will be **overwritten**.   



## 6. DELETE delete data

Up to now, the `TDengine` version is` V2.4`, and the data table does not support the DELETE operation.  

However, **the database** supports automatic deletion. The deletion time is related to the `keep` parameter specified when the database is created. The default is 3650 days, or 10 years. After 10 years, the database will be automatically logically deleted, and the database files will be automatically backed up to the appropriate location.  



# Others

PDO could connect to TDengine without specifying a database. 

For example:  

```php
$dbh = new PDO("taos:host=127.0.0.1", "root", "taosdata");
```



The database of TDengine has a storage time, which is 10 years by default. 

If the device logs are saved as a single database, the device log three years ago has no reference value, but the data cannot be deleted, which will occupy the hard disk space and affect the running speed of the database. 

If device logs are saved as multiple databases separated by `year`. It will not cause the single database and data set to be too large, and the efficiency of inserting and querying data is maintained. Meaningless device logs can be deleted or archived.  



Example：

```php
<?php
try {
    $dbh = new PDO("taos:host=127.0.0.1", "root", "taosdata");
    
    $year = date("Y");
    $database = sprintf("demo_%s", $year);

    $sql = "INSERT INTO {$database}.device_log_100 (created_timestamp, v_bool, v_tinyint, v_smallint, v_int, v_bigint, v_float, v_double, v_binary, v_nchar ) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $sth = $dbh->prepare($sql);

    $v1 = intval(microtime(true) * 1000);
    $v2 = 1;
    $v3 = 100;
    $v4 = 2000;
    $v5 = 20000;
    $v6 = 200000;
    $v7 = 8.08;
    $v8 = 8.000008;
    $v9 = "TDengine test";
    $v10 = "Taos test";

    $sth->bindParam(1, $v1, PDO::PARAM_TAOS_TIMESTAMP);
    $sth->bindParam(2, $v2, PDO::PARAM_TAOS_BOOL);
    $sth->bindParam(3, $v3, PDO::PARAM_TAOS_TINYINT);
    $sth->bindParam(4, $v4, PDO::PARAM_TAOS_SMALLINT);
    $sth->bindParam(5, $v5, PDO::PARAM_TAOS_INT);
    $sth->bindParam(6, $v6, PDO::PARAM_TAOS_BIGINT);
    $sth->bindParam(7, $v7, PDO::PARAM_TAOS_FLOAT);
    $sth->bindParam(8, $v8, PDO::PARAM_TAOS_DOUBLE);
    $sth->bindParam(9, $v9, PDO::PARAM_TAOS_BINARY);
    $sth->bindParam(10, $v10, PDO::PARAM_TAOS_NCHAR);

    $result = $sth->execute();
    var_dump($result);

    $rowCount = $sth->rowCount()
    var_dump($rowCount);
} catch (Exception $e) {
    printf("%d, %s\n", $e->getCode(), $e->getMessage());
}

?>
```

