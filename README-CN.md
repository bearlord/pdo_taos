简体中文 | [English](./README.md)

PDO_TAOS是 [涛思数据](https://github.com/taosdata/TDengine) 的PDO驱动。

[涛思数据](https://github.com/taosdata/TDengine) 已同意使用库文件开发PHP扩展。



# TDengine 简介

TDengine是涛思数据专为物联网、车联网、工业互联网、IT运维等设计和优化的大数据平台。除核心的快10倍以上的时序数据库功能外，还提供缓存、数据订阅、流式计算等功能，最大程度减少研发和运维的复杂度，且核心代码，包括集群功能全部开源（开源协议，AGPL v3.0）。

- 10 倍以上性能提升。定义了创新的数据存储结构，单核每秒就能处理至少2万次请求，插入数百万个数据点，读出一千万以上数据点，比现有通用数据库快了十倍以上。
- 硬件或云服务成本降至1/5。由于超强性能，计算资源不到通用大数据方案的1/5；通过列式存储和先进的压缩算法，存储空间不到通用数据库的1/10。
- 全栈时序数据处理引擎。将数据库、消息队列、缓存、流式计算等功能融合一起，应用无需再集成Kafka/Redis/HBase/Spark等软件，大幅降低应用开发和维护成本。
- 强大的分析功能。无论是十年前还是一秒钟前的数据，指定时间范围即可查询。数据可在时间轴上或多个设备上进行聚合。即席查询可通过Shell/Python/R/Matlab随时进行。
- 与第三方工具无缝连接。不用一行代码，即可与Telegraf, Grafana, EMQ X, Prometheus, Matlab, R集成。后续还将支持MQTT, OPC, Hadoop，Spark等, BI工具也将无缝连接。
- 零运维成本、零学习成本。安装、集群一秒搞定，无需分库分表，实时备份。标准SQL，支持JDBC,RESTful，支持Python/Java/C/C++/Go/Node.JS, 与MySQL相似，零学习成本。



# 安装 PDO_TAOS

## 源码编译安装

```bash
phpize
./configure
make && make install
```



## 开启 pdo_taos 扩展

编辑 `php.ini` 文件 或者在 `php.d` 目录下新增配置文件。

```ini
extension=pdo_taos.so
```



## 安装TDengine

PDO_TAOS编译时，需要libtaos.so，默认路径为：/usr/lib/libtaos.so，指向路径为：/usr/local/taos/driver/libtaos.so.2.x.x.x。所以需要先安装TDengine数据库服务器端或者C客户端。





# 用法

## 0. 创建数据库

客户端完成创建数据库

```sql
CREATE DATABASE demo
```



## 1. 连接TDengine数据库

```php
$dbh = new PDO("taos:host=127.0.0.1;dbname=test", "root", "taosdata");
```



## 2. CREATE 创建数据表

创建数据表与 `MySQL` 语法类似，数据类型也有类似，但是又有区别。

`TDengine` 有自己的数据库类型，`NCHAR` 类似 MySQL 的 `VARCHAR`，`BINARY` 类似 MySQL 的 `TEXT`。

`TDengine` 没有 `PRIMARY KEY`、`AUTO INCREMENT`, `DEFAULT NULL` 等关键字。

`TDengine`的第一列必须是 `TIMESTAMP` 类型。



例如：

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

如果执行成功，`$result` 返回 **0** ；  如果失败了，返回 **-1**。



执行结束后，登录 `TDengine` 数据库客户端查看数据表结构。

```shell
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

`SHOW CREATE TABLE` 可以查看表结构，显示不全，末尾请加`\G`。



## 3. INSERT 插入数据

### 插入数据分两种方式:

1. 调用 `PDO::exec` 方法直接执行 `INSERT` SQL语句。
2. 调用`PDO::prepare`方法执行预处理操作。



### 3.1 PDO::exec方式

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

执行如果成功，$result 返回 `0` ；  如果失败了，返回 `-1`。

没有方法能获取受影响的行数。



**注意：**

**一定要调用 `exec` 方法，而不是 `query` 方法。**



### 3.2 PDO::prepare预处理方式

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

执行成功，`$result` 返回 true，执行失败，返回false。

 `$rowCount`  表示受影响的行数。



#### 占位符

支持应 `:name` 形式的参数名，也支持 `?` 形式 。



#### 数据类型

请忘记：`PDO::PARAM_BOOL `、`PDO::PARAM_INT `, `PDO::PARAM_STR` 等PDO预定义类型。

如果在执行插入操作时，能**预先**获取数据表的**字段类型**，再根据绑定的**数据的类型**，则可以完美兼容，如：

MySQL的字段类型是 `INT`, `SMALLINT`, `TINYINT`, `BIGINT`，绑定参数为`PDO::PARAM_INT` 也可以插入 。

但是`TDengine` 的API在执行 `INSERT`操作时，不能预先获取数据表的字段类型，只能与数据表的字段完全一致，才可以绑定参数。



`PDO::PARAM_TAOS_`开头的数据类型，均是自定义的类型，分别对应TDengine的 `TSDB_DATA_TYPE_` 数据类型。

例如：

**PARAM_TAOS_INT => TSDB_DATA_TYPE_INT**

**PARAM_TAOS_FLOAT => TSDB_DATA_TYPE_FLOAT**

**PARAM_TAOS_TIMESTAMP => TSDB_DATA_TYPE_TIMESTAMP**



但是前后两者并不相等。

`TSDB_DATA_TYPE_INT` 等常量，与 PDO 预定义常量的值 部分相等，导致判断数据类型时 发生冲突，故人为设定 `6000` 的差值。

仅做说明，对开发没有影响。



### **3.3 不要混写**

`PDO::exec` 写完整的SQL语句，不要占位符。

`PDO::prepare` 一定要 `bindParam` 或者 `bindValue`，避免 `prepare` 没有占位符的 SQL，否则会报错。



## 4. SELECT查询

查询数据分两种方法:

1. 调用 `PDO::query` 方法直接执行 `SELECT` SQL语句。
2. 调用 `PDO::prepare `方法执行预处理操作。



### 4.1 PDO::query方法

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

执行结果如下：

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



注意：`created_timestamp` 在查询时候，用的 `时间戳` 类型 , 但查询结果`格式化时间` 类型，这个跟`TDengine`的客户端查询结果保持一致。



### 4.2 PDO::prepare方法

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



注意：`created_timestamp` 在绑定参数时候，用的`时间戳`类型 , 但查询结果`格式化时间` 类型，这个跟`TDengine`的客户端查询结果保持一致。

其他绑定的参数，数据类型也要和数据表的字段类型一致。如果用 `PDO::PARAM_INT` 或者 `PDO::PARAM_STR`碰巧运行正常，仅仅是因为运气好而已，不推荐。



谁也想不到，弱类型的PHP，在操作 `TDengine` 数据库的时候，竟要求 **数据类型** 和 **数据表字段类型** **强一致性**。



## 5. UPDATE 修改数据

`TDengine` 默认不支持修改操作。向不支持数据更新的表中写入重复时间戳的数据，**后写入**的数据会被**丢弃**。

若用户需要数据的更新功能，则在建库的时候，只需要指定数据库的  **update** 选项为**1**即可。

例如：

```sql
CREATE DATABASE demo UPDATE 1
```

在向支持数据更新功能的表中，写入重复时间戳的数据时，**老数据**会被**覆盖** ， 



## 6. DELETE 删除数据

截止到 `TDengine V2.4`, **数据表**暂不支持DELETE操作。

但是**数据库**支持自动删除，删除时间与 创建数据库时候指定的`keep`参数有关系，默认3650天，即10年。10年后这个数据库会自动逻辑删除，数据库文件自动备份到适当位置。



# 其他

`PDO` 连接 `TDengine`, 可以不指定数据库。例如：

```php
$dbh = new PDO("taos:host=127.0.0.1", "root", "taosdata");
```



`TDengine`的数据库有保存时间，默认10年。

如果设备日志保存为单数据库，3年前的设备日志已没有参考意义，但又不能删除数据，导致占用硬盘空间，同时影响数据库的运行速度。

如果设备日志保存为按 **年** 区分的多个数据库。不会导致单数据库和数据集过大，保持了插入和查询数据的高效性。没有意义的设备日志，可以删除、归档。



例如：

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

