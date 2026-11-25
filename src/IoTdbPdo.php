<?php

declare(strict_types=1);

namespace Bearlord\PdoIotdb;

use DateTimeInterface;
use JsonException;

/**
 * PDO-style userland adapter for the Apache IoTDB REST v1 APIs.
 * This class deliberately does not extend PDO and does not register a PDO driver.
 */
final class IoTdbPdo
{
    public const MODEL_TREE = 'tree';
    public const MODEL_TABLE = 'table';

    private string $baseUrl;
    private string $username;
    private string $password;
    private string $model;
    private string $database;
    private float $timeout;
    private int $rowLimit;
    private int $maxResponseBytes;
    /** @var callable|null Test or custom transport: fn(string $url, array $headers, string $body, float $timeout): array */
    private $transport;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, array $options = [])
    {
        $config = self::parseDsn($dsn);
        $this->username = $username ?? (string) ($config['user'] ?? 'root');
        $this->password = $password ?? (string) ($config['password'] ?? 'root');
        $this->model = strtolower((string) ($options['model'] ?? $config['model'] ?? self::MODEL_TREE));
        $this->database = (string) ($options['database'] ?? $config['database'] ?? '');
        $this->timeout = (float) ($options['timeout'] ?? $config['timeout'] ?? 30);
        $this->rowLimit = (int) ($options['row_limit'] ?? $config['row_limit'] ?? 10000);
        $this->maxResponseBytes = (int) ($options['max_response_bytes'] ?? 16777216);
        $this->transport = $options['http_transport'] ?? null;

        if (!in_array($this->model, [self::MODEL_TREE, self::MODEL_TABLE], true)) {
            throw new IoTdbException('The IoTDB model must be "tree" or "table".', 'HY024');
        }
        if ($this->timeout <= 0 || $this->timeout > 3600) {
            throw new IoTdbException('The HTTP timeout must be between 0 and 3600 seconds.', 'HY024');
        }
        if ($this->rowLimit < 1 || $this->rowLimit > 1000000) {
            throw new IoTdbException('row_limit must be between 1 and 1000000.', 'HY024');
        }
        if ($this->maxResponseBytes < 1024) {
            throw new IoTdbException('max_response_bytes must be at least 1024.', 'HY024');
        }
        if ($this->transport !== null && !is_callable($this->transport)) {
            throw new IoTdbException('http_transport must be callable.', 'HY024');
        }

        $url = (string) ($options['url'] ?? $config['url'] ?? '');
        if ($url === '') {
            $host = (string) ($config['host'] ?? '127.0.0.1');
            $port = (int) ($config['port'] ?? 18080);
            if ($port < 1 || $port > 65535) {
                throw new IoTdbException('The port must be between 1 and 65535.', 'HY024');
            }
            if (strpos($host, ':') !== false && substr($host, 0, 1) !== '[') {
                $host = '[' . $host . ']';
            }
            $url = 'http://' . $host . ':' . $port . '/';
        }
        $this->baseUrl = self::normalizeBaseUrl($url);
    }

    public static function fromConfig(array $config): self
    {
        $dsn = 'iotdb:';
        foreach (['url', 'host', 'port', 'database', 'model', 'timeout', 'row_limit', 'user', 'password'] as $key) {
            if (array_key_exists($key, $config)) {
                $dsn .= $key . '=' . rawurlencode((string) $config[$key]) . ';';
            }
        }

        $options = $config;
        unset($options['user'], $options['password'], $options['host'], $options['port'], $options['url'], $options['model'], $options['database'], $options['timeout'], $options['row_limit']);
        return new self($dsn, null, null, array_merge($options, array_intersect_key($config, array_flip(['model', 'database', 'timeout', 'row_limit', 'url']))));
    }

    public function getAttribute(string $attribute)
    {
        switch ($attribute) {
            case 'driver_name':
                return 'iotdb-rest-' . $this->model;
            case 'model':
                return $this->model;
            case 'database':
                return $this->database;
            case 'timeout':
                return $this->timeout;
            default:
                throw new IoTdbException('Unsupported connection attribute: ' . $attribute, 'HY092');
        }
    }

    public function setDatabase(string $database): void
    {
        if ($this->model !== self::MODEL_TABLE) {
            throw new IoTdbException('A current database is only used by Table model requests.', 'IM001');
        }
        $this->database = $database;
    }

    public function query(string $sql): IoTdbStatement
    {
        if (!self::isQuery($sql)) {
            throw new IoTdbException('query() accepts read statements. Use exec() for DDL or DML.', 'HY000');
        }
        return $this->executeSql($sql);
    }

    public function exec(string $sql): int
    {
        if (self::isQuery($sql)) {
            throw new IoTdbException('exec() accepts non-query statements. Use query() for read statements.', 'HY000');
        }
        $statement = $this->executeSql($sql);
        return $statement->rowCount();
    }

    public function prepare(string $sql): IoTdbStatement
    {
        return new IoTdbStatement($this, $sql);
    }

    /** @internal Used by IoTdbStatement after client-side placeholder expansion. */
    public function executeSql(string $sql): IoTdbStatement
    {
        if (trim($sql) === '') {
            throw new IoTdbException('SQL must not be empty.', 'HY000');
        }
        $query = self::isQuery($sql);
        $apiRoot = $this->model === self::MODEL_TABLE ? 'rest/table/v1/' : 'rest/v1/';
        $endpoint = $apiRoot . ($query ? 'query' : 'nonQuery');
        $payload = ['sql' => $sql];

        if ($this->model === self::MODEL_TABLE) {
            $payload['database'] = self::isCreateDatabase($sql) ? '' : $this->database;
            $payload['row_limit'] = $this->rowLimit;
        } else {
            $payload['rowLimit'] = $this->rowLimit;
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
        ];

        try {
            [$status, $responseBody] = $this->transport === null
                ? $this->sendHttpRequest($url, $headers, $body)
                : ($this->transport)($url, $headers, $body, $this->timeout);
        } catch (IoTdbException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new IoTdbException('IoTDB REST transport failed: ' . $exception->getMessage(), '08006', 0, $exception);
        }

        if (!is_int($status) || !is_string($responseBody)) {
            throw new IoTdbException('The REST transport returned an invalid response.', 'HY000');
        }
        if (strlen($responseBody) > $this->maxResponseBytes) {
            throw new IoTdbException('IoTDB REST response exceeds the configured size limit.', 'HY001');
        }
        if ($status < 200 || $status >= 300) {
            $decoded = self::decodeJson($responseBody, false);
            $message = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : 'IoTDB REST request failed.';
            throw new IoTdbException('HTTP ' . $status . ': ' . $message, self::sqlStateForHttpStatus($status), $status);
        }

        $decoded = self::decodeJson($responseBody, true);
        self::throwForServerError($decoded);
        return new IoTdbStatement($this, $sql, $decoded, $query);
    }

    /** @internal Used by IoTdbStatement::execute(). */
    public static function bindParameters(string $sql, array $parameters): string
    {
        $placeholderInfo = self::placeholderInfo($sql);
        $positionalCount = $placeholderInfo['positional'];
        $namedPlaceholders = $placeholderInfo['named'];
        if ($positionalCount === 0 && $namedPlaceholders === []) {
            if ($parameters !== []) {
                throw new IoTdbException('Parameters were supplied but the SQL has no placeholders.', 'HY093');
            }
            return $sql;
        }

        if ($positionalCount > 0 && $namedPlaceholders !== []) {
            throw new IoTdbException('Named and positional placeholders cannot be mixed.', 'HY093');
        }

        $expanded = [];
        if ($namedPlaceholders !== []) {
            foreach ($parameters as $key => $value) {
                if (!is_string($key)) {
                    throw new IoTdbException('Named placeholders require named parameter keys.', 'HY093');
                }
                $name = ltrim($key, ':');
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1 || array_key_exists($name, $expanded)) {
                    throw new IoTdbException('Invalid or duplicate named parameter: ' . $key, 'HY093');
                }
                $expanded[$name] = self::quoteValue($value);
            }
            foreach ($namedPlaceholders as $name => $_) {
                if (!array_key_exists($name, $expanded)) {
                    throw new IoTdbException('Named parameter :' . $name . ' is missing.', 'HY093');
                }
            }
            if (count($expanded) !== count($namedPlaceholders)) {
                throw new IoTdbException('The number of bound parameters does not match the SQL placeholders.', 'HY093');
            }
        } else {
            if (count($parameters) !== $positionalCount) {
                throw new IoTdbException('The number of bound parameters does not match the SQL placeholders.', 'HY093');
            }
            foreach ($parameters as $key => $value) {
                if (!is_int($key) || $key < 0 || $key >= $positionalCount) {
                    throw new IoTdbException('Positional parameters must use a zero-based, sequential array.', 'HY093');
                }
                $expanded[(string) ($key + 1)] = self::quoteValue($value);
            }
            for ($index = 0; $index < $positionalCount; $index++) {
                if (!array_key_exists((string) ($index + 1), $expanded)) {
                    throw new IoTdbException('A positional parameter is missing.', 'HY093');
                }
            }
        }

        $output = '';
        $length = strlen($sql);
        $quote = '';
        $lineComment = false;
        $blockComment = false;
        $position = 0;
        $positionalIndex = 0;
        while ($position < $length) {
            $char = $sql[$position];
            $next = $position + 1 < $length ? $sql[$position + 1] : '';

            if ($lineComment) {
                $output .= $char;
                if ($char === "\n") $lineComment = false;
                $position++;
                continue;
            }
            if ($blockComment) {
                $output .= $char;
                if ($char === '*' && $next === '/') {
                    $output .= '/';
                    $position += 2;
                    $blockComment = false;
                } else {
                    $position++;
                }
                continue;
            }
            if ($quote !== '') {
                $output .= $char;
                if ($char === $quote) {
                    if ($next === $quote) {
                        $output .= $next;
                        $position += 2;
                        continue;
                    }
                    $quote = '';
                } elseif ($char === '\\' && $next !== '') {
                    $output .= $next;
                    $position += 2;
                    continue;
                }
                $position++;
                continue;
            }
            if ($char === '-' && $next === '-') {
                $output .= '--';
                $position += 2;
                $lineComment = true;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $output .= '/*';
                $position += 2;
                $blockComment = true;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $output .= $char;
                $position++;
                continue;
            }
            if ($char === '?') {
                $key = (string) (++$positionalIndex);
                if (!array_key_exists($key, $expanded)) {
                    throw new IoTdbException('A positional parameter is missing.', 'HY093');
                }
                $output .= $expanded[$key];
                $position++;
                continue;
            }
            if ($char === ':' && preg_match('/[A-Za-z_]/', $next) === 1) {
                $end = $position + 1;
                while ($end < $length && preg_match('/[A-Za-z0-9_]/', $sql[$end]) === 1) $end++;
                $name = substr($sql, $position + 1, $end - $position - 1);
                if (!array_key_exists($name, $expanded)) {
                    throw new IoTdbException('Named parameter :' . $name . ' is missing.', 'HY093');
                }
                $output .= $expanded[$name];
                $position = $end;
                continue;
            }
            $output .= $char;
            $position++;
        }

        if ($positionalIndex > 0 && $positionalIndex !== $positionalCount) {
            throw new IoTdbException('The SQL placeholder count changed while binding.', 'HY093');
        }
        return $output;
    }

    private function sendHttpRequest(string $url, array $headers, string $body): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            $error = error_get_last();
            $message = isset($error['message']) ? preg_replace('/https?:\/\/[^\s]+/i', '[endpoint]', $error['message']) : 'Unable to open the HTTP connection.';
            throw new IoTdbException('IoTDB REST connection failed: ' . $message, '08006');
        }
        try {
            $meta = stream_get_meta_data($stream);
            $responseBody = stream_get_contents($stream, $this->maxResponseBytes + 1);
            if ($responseBody === false) {
                throw new IoTdbException('Failed to read the IoTDB REST response.', '08006');
            }
        } finally {
            fclose($stream);
        }

        $status = 0;
        foreach ($meta['wrapper_data'] ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }
        return [$status, $responseBody];
    }

    private static function parseDsn(string $dsn): array
    {
        if (stripos($dsn, 'iotdb:') !== 0) {
            throw new IoTdbException('The DSN must start with "iotdb:".', 'HY024');
        }
        $config = [];
        foreach (explode(';', substr($dsn, 6)) as $part) {
            if ($part === '') continue;
            $separator = strpos($part, '=');
            if ($separator === false) {
                throw new IoTdbException('Invalid DSN component: ' . $part, 'HY024');
            }
            $key = strtolower(trim(substr($part, 0, $separator)));
            $value = rawurldecode(substr($part, $separator + 1));
            if (!in_array($key, ['url', 'host', 'port', 'database', 'model', 'timeout', 'row_limit', 'user', 'password'], true)) {
                throw new IoTdbException('Unknown DSN option: ' . $key, 'HY024');
            }
            $config[$key] = $value;
        }
        return $config;
    }

    private static function normalizeBaseUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new IoTdbException('The REST URL must be an absolute HTTP or HTTPS URL.', 'HY024');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new IoTdbException('Put credentials in the user and password fields, not in the REST URL.', 'HY024');
        }
        $path = rtrim($parts['path'] ?? '/', '/');
        $path = preg_replace('~/(?:rest/(?:table/)?v1)(?:/(?:query|nonQuery|insertTablet))?$~i', '', $path) ?? $path;
        $authority = strtolower($parts['scheme']) . '://' . $parts['host'];
        if (isset($parts['port'])) $authority .= ':' . $parts['port'];
        return $authority . ($path === '' ? '/' : $path . '/');
    }

    private static function decodeJson(string $body, bool $required): ?array
    {
        if (trim($body) === '') {
            if ($required) throw new IoTdbException('IoTDB returned an empty response body.', 'HY000');
            return null;
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            if ($required) throw new IoTdbException('IoTDB returned invalid JSON.', 'HY000', 0, $exception);
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private static function throwForServerError(?array $response): void
    {
        if ($response === null || !array_key_exists('code', $response)) return;
        $code = (int) $response['code'];
        if ($code === 200 || $code === 0) return;
        $message = (string) ($response['message'] ?? 'IoTDB reported an execution error.');
        $sqlState = $code === 401 || $code === 403 ? '28000' : '42000';
        throw new IoTdbException($message, $sqlState, $code);
    }

    private static function sqlStateForHttpStatus(int $status): string
    {
        if ($status === 401 || $status === 403) return '28000';
        if ($status === 400 || $status === 422) return '42000';
        if ($status === 404) return '08004';
        if ($status >= 500) return '08006';
        return 'HY000';
    }

    private static function isQuery(string $sql): bool
    {
        $keyword = self::firstKeyword($sql);
        return in_array($keyword, ['SELECT', 'WITH', 'SHOW', 'LIST', 'DESCRIBE', 'DESC', 'EXPLAIN'], true);
    }

    private static function isCreateDatabase(string $sql): bool
    {
        return preg_match('/^\s*(?:--[^\n]*\n\s*|\/\*.*?\*\/\s*)*CREATE\s+DATABASE\b/is', $sql) === 1;
    }

    private static function firstKeyword(string $sql): string
    {
        $sql = preg_replace('/^(?:\s|--[^\n]*(?:\n|$)|\/\*.*?\*\/)+/s', '', $sql) ?? $sql;
        return strtoupper(strtok($sql, " \t\r\n(") ?: '');
    }

    /** @return array{positional:int,named:array<string,bool>} */
    private static function placeholderInfo(string $sql): array
    {
        $positional = 0;
        $named = [];
        $quote = '';
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);
        for ($position = 0; $position < $length; $position++) {
            $char = $sql[$position];
            $next = $position + 1 < $length ? $sql[$position + 1] : '';
            if ($lineComment) {
                if ($char === "\n") $lineComment = false;
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $position++;
                    $blockComment = false;
                }
                continue;
            }
            if ($quote !== '') {
                if ($char === $quote) {
                    if ($next === $quote) {
                        $position++;
                    } else {
                        $quote = '';
                    }
                } elseif ($char === '\\') {
                    $position++;
                }
                continue;
            }
            if ($char === '-' && $next === '-') {
                $position++;
                $lineComment = true;
            } elseif ($char === '/' && $next === '*') {
                $position++;
                $blockComment = true;
            } elseif ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            } elseif ($char === '?') {
                $positional++;
            } elseif ($char === ':' && preg_match('/[A-Za-z_]/', $next) === 1) {
                $end = $position + 1;
                while ($end < $length && preg_match('/[A-Za-z0-9_]/', $sql[$end]) === 1) $end++;
                $name = substr($sql, $position + 1, $end - $position - 1);
                $named[$name] = true;
                $position = $end - 1;
            }
        }
        return ['positional' => $positional, 'named' => $named];
    }

    private static function quoteValue($value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
        if (is_int($value)) return (string) $value;
        if (is_float($value)) {
            if (!is_finite($value)) throw new IoTdbException('Non-finite float parameters are not supported.', 'HY105');
            return sprintf('%.17g', $value);
        }
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d\TH:i:s.uP');
        }
        if (!is_string($value) && !is_numeric($value)) {
            throw new IoTdbException('Only scalar and DateTimeInterface values can be bound.', 'HY105');
        }
        $value = (string) $value;
        if (strpos($value, "\0") !== false) {
            throw new IoTdbException('NUL bytes are not supported in SQL string parameters.', 'HY105');
        }
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
