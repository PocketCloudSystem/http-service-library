<?php

namespace r3pt1s\httpserver\event\def;

use r3pt1s\httpserver\event\CancelableEvent;
use r3pt1s\httpserver\event\Event;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\socket\SocketClient;

final class RequestHandleEvent extends Event implements CancelableEvent {

    public function __construct(
        private readonly SocketClient $client,
        private readonly RequestContext $requestContext,
        private bool $useCachedResponse
    ) {}

    public function setUseCachedResponse(bool $useCachedResponse): void {
        $this->useCachedResponse = $useCachedResponse;
    }

    public function getClient(): SocketClient {
        return $this->client;
    }

    public function getRequestContext(): RequestContext {
        return $this->requestContext;
    }

    public function isUseCachedResponse(): bool {
        return $this->useCachedResponse;
    }
}