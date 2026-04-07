<?php

namespace r3pt1s\httpserver\event\def;

use r3pt1s\httpserver\event\Event;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\socket\SocketClient;
use Throwable;

final class RequestErrorEvent extends Event {

    public function __construct(
        private readonly SocketClient $client,
        private readonly ?RequestContext $requestContext,
        private readonly Throwable $exception,
        private ResponseBuilder $responseBuilder
    ) {}

    public function setResponseBuilder(ResponseBuilder $responseBuilder): void {
        $this->responseBuilder = $responseBuilder;
    }

    public function getClient(): SocketClient {
        return $this->client;
    }

    public function getRequest(): ?RequestContext {
        return $this->requestContext;
    }

    public function getException(): Throwable {
        return $this->exception;
    }

    public function getResponseBuilder(): ResponseBuilder {
        return $this->responseBuilder;
    }
}