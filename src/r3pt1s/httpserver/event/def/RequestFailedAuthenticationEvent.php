<?php

namespace r3pt1s\httpserver\event\def;

use r3pt1s\httpserver\event\Event;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\socket\SocketClient;

final class RequestFailedAuthenticationEvent extends Event {

    public function __construct(
        private readonly SocketClient $client,
        private readonly RequestContext $requestContext,
        private readonly bool $viaApiVersion
    ) {}

    public function getClient(): SocketClient {
        return $this->client;
    }

    public function getRequestContext(): RequestContext {
        return $this->requestContext;
    }

    public function isViaApiVersion(): bool {
        return $this->viaApiVersion;
    }
}