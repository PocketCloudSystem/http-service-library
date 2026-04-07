<?php

namespace r3pt1s\httpserver\event\def;

use r3pt1s\httpserver\event\Event;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\socket\SocketClient;

final class ClientRateLimitedEvent extends Event {

    public function __construct(
        private readonly SocketClient $client,
        private readonly RequestContext $requestContext,
        private readonly int $endTimestamp
    ) {}

    public function getClient(): SocketClient {
        return $this->client;
    }

    public function getRequestContext(): RequestContext {
        return $this->requestContext;
    }

    public function getEndTimestamp(): int {
        return $this->endTimestamp;
    }
}