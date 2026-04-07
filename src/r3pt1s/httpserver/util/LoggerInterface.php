<?php

namespace r3pt1s\httpserver\util;

use Throwable;

interface LoggerInterface {

    public function info(string $message, string|int|float|bool ...$params): self;

    public function warn(string $message, string|int|float|bool ...$params): self;

    public function debug(string $message, string|int|float|bool ...$params): self;

    public function error(string $message, string|int|float|bool ...$params): self;

    public function exception(Throwable $exception): self;
}