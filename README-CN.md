简体中文 | [English](./README.md)

PDO_TAOS是 [涛思数据](https://github.com/taosdata/TDengine) 的PDO驱动。

# TDengine 简介

TDengine是涛思数据专为物联网、车联网、工业互联网、IT运维等设计和优化的大数据平台。除核心的快10倍以上的时序数据库功能外，还提供缓存、数据订阅、流式计算等功能，最大程度减少研发和运维的复杂度，且核心代码，包括集群功能全部开源（开源协议，AGPL v3.0）。

- 10 倍以上性能提升。定义了创新的数据存储结构，单核每秒就能处理至少2万次请求，插入数百万个数据点，读出一千万以上数据点，比现有通用数据库快了十倍以上。
- 硬件或云服务成本降至1/5。由于超强性能，计算资源不到通用大数据方案的1/5；通过列式存储和先进的压缩算法，存储空间不到通用数据库的1/10。
- 全栈时序数据处理引擎。将数据库、消息队列、缓存、流式计算等功能融合一起，应用无需再集成Kafka/Redis/HBase/Spark等软件，大幅降低应用开发和维护成本。
- 强大的分析功能。无论是十年前还是一秒钟前的数据，指定时间范围即可查询。数据可在时间轴上或多个设备上进行聚合。即席查询可通过Shell/Python/R/Matlab随时进行。
- 与第三方工具无缝连接。不用一行代码，即可与Telegraf, Grafana, EMQ X, Prometheus, Matlab, R集成。后续还将支持MQTT, OPC, Hadoop，Spark等, BI工具也将无缝连接。
- 零运维成本、零学习成本。安装、集群一秒搞定，无需分库分表，实时备份。标准SQL，支持JDBC,RESTful，支持Python/Java/C/C++/Go/Node.JS, 与MySQL相似，零学习成本。


# 安装 PDO_TAOS
```bash
phpize
./configure
make && make install
```

## 安装TDengine
PDO_TAOS编译时，需要libtaos.so，默认路径为：/usr/lib/libtaos.so，指向路径为：/usr/local/taos/driver/libtaos.so.2.x.x.x。所以需要先安装TDengine数据库。

# 用法
```php
$dbh = new PDO("taos:host=127.0.0.1;dbname=test", "root", "taosdata");
$sth = $dbh->query("select avg(current), max(voltage), min(phase) from meters where location='beijing'");

$result = $sth->fetchAll();
print_r($result);
```

```php
$dbh = new PDO("taos:host=127.0.0.1;dbname=test", "root", "taosdata");
$sth = $dbh->prepare("select avg(current), max(voltage), min(phase) from meters where location=:location");

$location = 'beijing';
$sth->bindValue(":location", $location, PDO::PARAM_STR);
$sth->execute();
$result = $sth->fetchAll(PDO::FETCH_ASSOC);
print_r($result);
```

