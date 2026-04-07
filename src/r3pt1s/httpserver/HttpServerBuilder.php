<?php

namespace r3pt1s\httpserver;

use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\LoggerImpl;
use r3pt1s\httpserver\util\LoggerInterface;
use r3pt1s\httpserver\util\RateLimiter;

final class HttpServerBuilder {

    public static function create(Address $address): self {
        return new self($address);
    }

    public function __construct(
        private Address $address,
        private ?RateLimiter $rateLimiter = null,
        private ?LoggerInterface $logger = null,
        private bool $enableVersioning = false,
        private bool $enableResponseCaching = false,
        private int $cachingTimeInSeconds = 60
    ) {}

    public function address(Address $address): HttpServerBuilder {
        $this->address = $address;
        return $this;
    }

    public function rateLimiter(RateLimiter $rateLimiter): HttpServerBuilder {
        $this->rateLimiter = $rateLimiter;
        return $this;
    }

    public function logger(LoggerInterface $logger): HttpServerBuilder {
        $this->logger = $logger;
        return $this;
    }

    public function enableVersioning(bool $enabled): HttpServerBuilder {
        $this->enableVersioning = $enabled;
        return $this;
    }

    public function configureResponseCaching(bool $enabled, int $cachingTimeInSeconds = 60): HttpServerBuilder {
        $this->enableResponseCaching = $enabled;
        $this->cachingTimeInSeconds = $cachingTimeInSeconds;
        return $this;
    }

    public function build(): HttpServer {
        return new HttpServer(
            $this->address,
            $this->rateLimiter ?? RateLimiter::configure(false, 0, 0, 0),
            $this->logger ?? new LoggerImpl(),
            $this->enableVersioning,
            $this->enableResponseCaching,
            $this->cachingTimeInSeconds
        );
    }
}