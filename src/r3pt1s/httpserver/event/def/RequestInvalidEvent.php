<?php

namespace r3pt1s\httpserver\event\def;

use r3pt1s\httpserver\event\Event;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\route\Path;
use r3pt1s\httpserver\socket\SocketClient;

final class RequestInvalidEvent extends Event {

    /**
     * @param SocketClient $client
     * @param RequestContext $requestContext
     * @param bool $identifiedBadRequest When true, event got triggered after isBadRequest, if it is false, event got triggered after willCauseError
     * @see Path
     * @param ResponseBuilder $responseBuilder
     */
    public function __construct(
        private readonly SocketClient $client,
        private readonly RequestContext $requestContext,
        private readonly bool $identifiedBadRequest,
        private ResponseBuilder $responseBuilder
    ) {}

    public function setResponseBuilder(ResponseBuilder $responseBuilder): void {
        $this->responseBuilder = $responseBuilder;
    }

    public function getClient(): SocketClient {
        return $this->client;
    }

    public function getRequestContext(): RequestContext {
        return $this->requestContext;
    }

    public function isIdentifiedBadRequest(): bool {
        return $this->identifiedBadRequest;
    }

    public function getResponseBuilder(): ResponseBuilder {
        return $this->responseBuilder;
    }
}