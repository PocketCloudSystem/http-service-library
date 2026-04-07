<?php

namespace r3pt1s\httpserver\route;

use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\socket\auth\Authentication;
use r3pt1s\httpserver\util\RequestMethod;

interface Path {

    public function handle(RequestContext $request): Response;

    public function handleFailedAuth(RequestContext $request): Response;

    public function isBadRequest(RequestContext $request, ResponseBuilder $response): bool;

    public function willCauseError(RequestContext $request, ResponseBuilder $response): bool;

    public function getApiVersion(): ?string;

    public function getPath(): string;

    public function getFullPath(): string;

    public function getMethod(): RequestMethod;

    public function getAuthentication(): Authentication;
}