<?php

declare(strict_types=1);

namespace TP\Core;

enum LogLevel: string
{
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case CRITICAL = 'CRITICAL';
}

interface LoggerInterface
{
    public function log(LogLevel $level, string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
}

final class Logger implements LoggerInterface
{
    private string $logFile;
    private string $dateFormat;
    private LogLevel $minLevel;

    public function __construct(
        string $logFile = 'php://stderr',
        LogLevel $minLevel = LogLevel::INFO,
        string $dateFormat = 'Y-m-d H:i:s'
    ) {
        $this->logFile = $logFile;
        $this->minLevel = $minLevel;
        $this->dateFormat = $dateFormat;
    }

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date($this->dateFormat);
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = sprintf("[%s] %s: %s%s\n", $timestamp, $level->value, $message, $contextStr);

        error_log($logMessage, 3, $this->logFile);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    private function shouldLog(LogLevel $level): bool
    {
        $levels = [
            LogLevel::DEBUG->value => 1,
            LogLevel::INFO->value => 2,
            LogLevel::WARNING->value => 3,
            LogLevel::ERROR->value => 4,
            LogLevel::CRITICAL->value => 5,
        ];

        return $levels[$level->value] >= $levels[$this->minLevel->value];
    }
}