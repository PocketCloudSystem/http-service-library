<?php

namespace r3pt1s\httpserver\route;

use Closure;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\socket\auth\Authentication;
use r3pt1s\httpserver\util\RequestMethod;

final class ClosureApiPath extends ApiPath {

    public function __construct(
        string $path,
        string $version,
        RequestMethod $requestMethod,
        Authentication $authentication,
        private readonly Closure $handleClosure,
        private readonly ?Closure $isBadRequestClosure = null,
        private readonly ?Closure $willCauseErrorClosure = null,
        private readonly ?Closure $handleFailedAuthClosure = null
    ) {
        parent::__construct($path, $version, $requestMethod, $authentication);
    }

    public function handle(RequestContext $request): Response {
        return ($this->handleClosure)($request);
    }

    public function isBadRequest(RequestContext $request, ResponseBuilder $response): bool {
        if ($this->isBadRequestClosure === null) return false;
        return ($this->isBadRequestClosure)($request, $response);
    }

    public function willCauseError(RequestContext $request, ResponseBuilder $response): bool {
        if ($this->willCauseErrorClosure === null) return false;
        return ($this->willCauseErrorClosure)($request, $response);
    }

    public function handleFailedAuth(RequestContext $request): Response {
        if ($this->handleFailedAuthClosure === null) return parent::handleFailedAuth($request);
        return ($this->handleFailedAuthClosure)($request);
    }
}