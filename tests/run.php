<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/IoTdbException.php';
require_once __DIR__ . '/../src/IoTdbPdo.php';
require_once __DIR__ . '/../src/IoTdbStatement.php';

use Bearlord\PdoIotdb\IoTdbException;
use Bearlord\PdoIotdb\IoTdbPdo;

$assertions = 0;

function assertSameValue($expected, $actual, string $message): void
{
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function assertThrows(callable $callback, string $sqlState, string $message): IoTdbException
{
    global $assertions;
    $assertions++;
    try {
        $callback();
    } catch (IoTdbException $exception) {
        assertSameValue($sqlState, $exception->getSqlState(), $message . ' SQLSTATE');
        return $exception;
    }
    throw new RuntimeException($message . ' did not throw IoTdbException');
}

$captured = [];
$transport = static function (string $url, array $headers, string $body, float $timeout) use (&$captured): array {
    $captured[] = [
        'url' => $url,
        'headers' => $headers,
        'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
        'timeout' => $timeout,
    ];
    return [200, '{"code":200,"column_names":["time","temperature"],"values":[[123,26.5]]}'];
};

$table = IoTdbPdo::fromConfig([
    'url' => 'http://iotdb.example/proxy/rest/table/v1/query',
    'model' => 'table',
    'database' => 'weather',
    'user' => 'test-user',
    'password' => 'p@ss:word',
    'timeout' => 4,
    'row_limit' => 25,
    'http_transport' => $transport,
]);
$tableResult = $table->query('SELECT time, temperature FROM sensor LIMIT 1');
assertSameValue('http://iotdb.example/proxy/rest/table/v1/query', $captured[0]['url'], 'Table endpoint is normalized');
assertSameValue('weather', $captured[0]['body']['database'], 'Table requests include database');
assertSameValue(25, $captured[0]['body']['row_limit'], 'Table requests use row_limit');
assertSameValue(4.0, $captured[0]['timeout'], 'Configured timeout reaches transport');
assertSameValue('Authorization: Basic ' . base64_encode('test-user:p@ss:word'), $captured[0]['headers'][2], 'Basic auth header is sent');
assertSameValue(['time' => 123, 'temperature' => 26.5], $tableResult->fetch(), 'Table result maps column names to values');
assertSameValue(1, $tableResult->rowCount(), 'Query row count is the returned row count');
assertSameValue(2, $tableResult->columnCount(), 'Column count follows response metadata');

$treeTransport = static function (string $url, array $headers, string $body, float $timeout) use (&$captured): array {
    $captured[] = [
        'url' => $url,
        'headers' => $headers,
        'body' => json_decode($body, true, 512, JSON_THROW_ON_ERROR),
        'timeout' => $timeout,
    ];
    return [200, '{"code":200,"columnNames":[],"expressions":["root.sg.d1.s1"],"timestamps":[1000,2000],"values":[[10,20]]}'];
};
$tree = new IoTdbPdo('iotdb:host=127.0.0.1;port=18080;user=root;password=root', null, null, ['http_transport' => $treeTransport]);
$treeResult = $tree->query('SELECT s1 FROM root.sg.d1');
assertSameValue('http://127.0.0.1:18080/rest/v1/query', $captured[1]['url'], 'Tree endpoint is selected');
assertSameValue(10000, $captured[1]['body']['rowLimit'], 'Tree requests use rowLimit');
assertSameValue(['Time' => 1000, 'root.sg.d1.s1' => 10], $treeResult->fetch(), 'Tree timestamps are attached to rows');
assertSameValue(['Time' => 2000, 'root.sg.d1.s1' => 20], $treeResult->fetch(), 'Tree result cursor advances');
assertSameValue(null, $treeResult->fetch(), 'Fetch returns null after the last row');

$namedSql = "SELECT '?' AS label, :name AS first_value, :name AS second_value /* :ignored */ -- ?\n";
$named = $table->prepare($namedSql);
$named->bindValue(':name', "O'Reilly; DROP DATABASE weather");
assertSameValue(true, $named->execute(), 'bindValue execution succeeds');
assertSameValue("SELECT '?' AS label, 'O''Reilly; DROP DATABASE weather' AS first_value, 'O''Reilly; DROP DATABASE weather' AS second_value /* :ignored */ -- ?\n", $captured[2]['body']['sql'], 'Repeated named values are escaped; strings and comments are ignored');

$positional = $table->prepare("SELECT '?' AS label, ? /* ? */ -- ?\n");
assertSameValue(true, $positional->execute(['safe value']), 'execute accepts positional values');
assertSameValue("SELECT '?' AS label, 'safe value' /* ? */ -- ?\n", $captured[3]['body']['sql'], 'Only executable positional placeholders are expanded');

$insert = $table->prepare('INSERT INTO sensor(time, temperature) VALUES (?, ?)');
$insert->bindValue(1, 123);
$insert->bindValue(2, 22.5);
assertSameValue(true, $insert->execute(), 'One-based bindValue positional indexes work');
assertSameValue('http://iotdb.example/proxy/rest/table/v1/nonQuery', $captured[4]['url'], 'Writes use nonQuery endpoint');
assertSameValue('INSERT INTO sensor(time, temperature) VALUES (123, 22.5)', $captured[4]['body']['sql'], 'Bound numeric values are emitted as literals');

$table->exec('CREATE DATABASE weather_archive');
assertSameValue('', $captured[5]['body']['database'], 'Creating a Table-model database sends an empty database field');

assertThrows(static function (): void {
    IoTdbPdo::bindParameters('SELECT ?, :name', [0 => 1, 'name' => 'x']);
}, 'HY093', 'Mixed placeholder styles are rejected');
assertThrows(static function (): void {
    IoTdbPdo::bindParameters('SELECT ?', []);
}, 'HY093', 'Missing parameters are rejected');
assertThrows(static function (): void {
    IoTdbPdo::bindParameters('SELECT :name', ['name' => 'x', 'extra' => 'y']);
}, 'HY093', 'Extra named parameters are rejected');
assertThrows(static function (): void {
    IoTdbPdo::bindParameters('SELECT 1', ['unused' => 1]);
}, 'HY093', 'Parameters without placeholders are rejected');
assertThrows(static function (): void {
    IoTdbPdo::bindParameters('SELECT ?', [1 => 'hole']);
}, 'HY093', 'Non-sequential positional keys are rejected');

$ipv6Url = null;
$ipv6 = new IoTdbPdo('iotdb:host=%3A%3A1;port=18080', null, null, [
    'http_transport' => static function (string $url) use (&$ipv6Url): array {
        $ipv6Url = $url;
        return [200, '{"code":200,"column_names":["value"],"values":[[1]]}'];
    },
]);
$ipv6->query('SELECT 1');
assertSameValue('http://[::1]:18080/rest/v1/query', $ipv6Url, 'Unbracketed IPv6 DSN hosts are normalized');

$unauthorized = new IoTdbPdo('iotdb:url=http%3A%2F%2Flocalhost%2F;user=u;password=p', null, null, [
    'http_transport' => static function (): array {
        return [401, '{"message":"invalid credentials"}'];
    },
]);
$authError = assertThrows(static function () use ($unauthorized): void {
    $unauthorized->query('SELECT 1');
}, '28000', 'HTTP authentication errors map to authorization SQLSTATE');
assertSameValue(401, $authError->errorInfo()[1], 'HTTP status is exposed in errorInfo');

$serverError = new IoTdbPdo('iotdb:url=http%3A%2F%2Flocalhost%2F', null, null, [
    'http_transport' => static function (): array {
        return [200, '{"code":507,"message":"SQL execution failed"}'];
    },
]);
assertThrows(static function () use ($serverError): void {
    $serverError->query('SELECT 1');
}, '42000', 'IoTDB JSON execution errors map to SQLSTATE');

$largeResponse = new IoTdbPdo('iotdb:url=http%3A%2F%2Flocalhost%2F', null, null, [
    'max_response_bytes' => 1024,
    'http_transport' => static function (): array {
        return [200, str_repeat('x', 1025)];
    },
]);
assertThrows(static function () use ($largeResponse): void {
    $largeResponse->query('SELECT 1');
}, 'HY001', 'Oversized responses are rejected');

fwrite(STDOUT, 'OK (' . $assertions . " assertions)\n");
