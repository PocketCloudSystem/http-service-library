<?php

namespace r3pt1s\httpserver\util;

use ReflectionClass;
use ReflectionException;
use Throwable;

final class LoggerImpl implements LoggerInterface {

    public const string FORMAT = "[%s] %s: %s";

    private function send(string $level, string $message, string|int|float|bool ...$params): void {
        $params = array_map(fn(mixed $p) => is_bool($p) ? ($p ? "true" : "false") : $p, $params);
        $finalMessage = sprintf($message, ...$params);
        echo sprintf(self::FORMAT, date("H:i:s T"), $level, $finalMessage) . PHP_EOL;
    }

    public function info(string $message, string|int|float|bool ...$params): self {
        $this->send("INFO", $message, ...$params);
        return $this;
    }

    public function warn(string $message, string|int|float|bool ...$params): self {
        $this->send("WARN", $message, ...$params);
        return $this;
    }

    public function debug(string $message, string|bool|int|float ...$params): self {
        $this->send("DEBUG", $message, ...$params);
        return $this;
    }

    public function error(string $message, string|int|float|bool ...$params): self {
        $this->send("ERROR", $message, ...$params);
        return $this;
    }

    public function exception(Throwable $exception): self {
        $this->error("Unhandled %s: %s was thrown in %s at line %s", $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine());
        $i = 1;
        foreach ($exception->getTrace() as $trace) {
            $args = implode(", ", array_map(function(mixed $argument): string {
                if (is_object($argument)) {
                    try {
                        return new ReflectionClass($argument)->getShortName();
                    } catch (ReflectionException) {
                        return get_class($argument);
                    }
                } else if (is_array($argument)) {
                    return "array(" . count($argument) . ")";
                }
                return gettype($argument);
            }, ($trace["args"] ?? [])));

            if (isset($trace["line"])) {
                $this->error("Trace #%s called at '%s(%s)' in %s at line %s", $i, $trace["function"], $args, $trace["file"] ?? $trace["class"], $trace["line"]);
            } else {
                $this->error("Trace #%s called at '%s(%s)' in %s", $i, $trace["function"], $args, $trace["file"] ?? $trace["class"]);
            }
            $i++;
        }

        return $this;
    }
}