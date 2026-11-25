<?php

declare(strict_types=1);

namespace Bearlord\PdoIotdb;

final class IoTdbStatement
{
    private IoTdbPdo $connection;
    private string $sql;
    private array $rows = [];
    private array $columnNames = [];
    private int $cursor = 0;
    private int $affectedRows = 0;
    private bool $query = false;
    /** @var array<int|string, mixed> */
    private array $boundValues = [];

    public function __construct(IoTdbPdo $connection, string $sql, ?array $response = null, bool $query = false)
    {
        $this->connection = $connection;
        $this->sql = $sql;
        $this->query = $query;
        if ($response !== null) $this->loadResponse($response);
    }

    public function execute(array $parameters = []): bool
    {
        $boundValues = $this->boundValues;
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $key = ltrim($key, ':');
            }
            $boundValues[$key] = $value;
        }

        $sql = IoTdbPdo::bindParameters($this->sql, $boundValues);
        $executed = $this->connection->executeSql($sql);
        $this->rows = $executed->rows;
        $this->columnNames = $executed->columnNames;
        $this->affectedRows = $executed->affectedRows;
        $this->query = $executed->query;
        $this->cursor = 0;
        return true;
    }

    public function bindValue($parameter, $value): bool
    {
        if (is_int($parameter) || (is_string($parameter) && ctype_digit($parameter))) {
            $position = (int) $parameter;
            if ($position < 1) {
                throw new IoTdbException('Positional parameter indexes start at 1.', 'HY093');
            }
            $key = $position - 1;
        } else {
            $key = ltrim((string) $parameter, ':');
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                throw new IoTdbException('Invalid named parameter: ' . (string) $parameter, 'HY093');
            }
        }
        $this->boundValues[$key] = $value;
        return true;
    }

    public function fetch(): ?array
    {
        if (!isset($this->rows[$this->cursor])) return null;
        return $this->rows[$this->cursor++];
    }

    public function fetchAll(): array
    {
        $rows = array_slice($this->rows, $this->cursor);
        $this->cursor = count($this->rows);
        return $rows;
    }

    public function fetchColumn(int $column = 0)
    {
        $row = $this->fetch();
        if ($row === null) return false;
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function columnCount(): int
    {
        return count($this->columnNames);
    }

    public function rowCount(): int
    {
        return $this->query ? count($this->rows) : $this->affectedRows;
    }

    public function getColumnNames(): array
    {
        return $this->columnNames;
    }

    private function loadResponse(array $response): void
    {
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
        $this->columnNames = self::firstArray($data, ['columnNames', 'column_names', 'columns', 'expressions']);
        $values = self::firstArray($data, ['values', 'rows']);
        $timestamps = self::firstArray($data, ['timestamps']);

        if ($this->connection->getAttribute('model') === 'tree' && $this->query) {
            // Tree v1 returns columns as vectors. SELECT labels live in
            // expressions; SHOW labels live in columnNames and have no time.
            $names = $this->columnNames;
            $this->columnNames = $timestamps === [] ? $names : array_merge(['Time'], $names);
            $count = $timestamps === [] ? count($values[0] ?? []) : count($timestamps);
            for ($i = 0; $i < $count; $i++) {
                $row = $timestamps === [] ? [] : ['Time' => $timestamps[$i]];
                foreach ($names as $column => $name) { $row[$name] = $values[$column][$i] ?? null; }
                $this->rows[] = $row;
            }
            return;
        }

        if ($this->columnNames === [] && isset($values[0]) && is_array($values[0]) && !self::isList($values[0])) {
            $this->columnNames = array_keys($values[0]);
        }

        $prependTime = $timestamps !== [] && isset($values[0]) && is_array($values[0]) && self::isList($values[0]) &&
            !in_array('Time', $this->columnNames, true) && count($this->columnNames) === count($values[0]);
        if ($prependTime) array_unshift($this->columnNames, 'Time');
        if ($this->columnNames === [] && isset($values[0]) && is_array($values[0])) {
            for ($index = 0; $index < count($values[0]); $index++) $this->columnNames[] = 'column' . ($index + 1);
        }

        foreach ($values as $index => $valueRow) {
            if (!is_array($valueRow)) continue;
            if (!self::isList($valueRow)) {
                $row = $valueRow;
            } else {
                if ($prependTime && array_key_exists($index, $timestamps)) array_unshift($valueRow, $timestamps[$index]);
                $row = [];
                foreach ($this->columnNames as $columnIndex => $name) {
                    $row[$name] = $valueRow[$columnIndex] ?? null;
                }
            }
            $this->rows[] = $row;
        }

        $this->affectedRows = (int) ($response['rows'] ?? $response['affectedRows'] ?? 0);
    }

    private static function firstArray(array $source, array $keys): array
    {
        foreach ($keys as $key) {
            if (!empty($source[$key]) && is_array($source[$key])) return $source[$key];
        }
        return [];
    }

    private static function isList(array $values): bool
    {
        $index = 0;
        foreach ($values as $key => $_) {
            if ($key !== $index++) return false;
        }
        return true;
    }
}
