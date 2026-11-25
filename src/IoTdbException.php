<?php

declare(strict_types=1);

namespace Bearlord\PdoIotdb;

final class IoTdbException extends \RuntimeException
{
    private string $sqlState;
    private array $errorInfo;

    public function __construct(string $message, string $sqlState = 'HY000', int $driverCode = 0, ?\Throwable $previous = null)
    {
        $this->sqlState = $sqlState;
        $this->errorInfo = [$sqlState, $driverCode, $message];
        parent::__construct($message, $driverCode, $previous);
    }

    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    public function errorInfo(): array
    {
        return $this->errorInfo;
    }
}
