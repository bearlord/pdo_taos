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

# Examples
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