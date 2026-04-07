<?php

namespace r3pt1s\httpserver\util\trait;

use Closure;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\route\ClosureApiPath;
use r3pt1s\httpserver\route\ClosureRegularPath;
use r3pt1s\httpserver\socket\auth\Authentication;
use r3pt1s\httpserver\socket\auth\NoAuthAuthentication;
use r3pt1s\httpserver\util\ActionFailureReason;
use r3pt1s\httpserver\util\RequestMethod;

trait QuickRouteRegistrarsTrait {

    /**
     * @param string $path
     * @param RequestMethod $requestMethod
     * @param Closure(RequestContext $request): Response $handleClosure
     * @param Authentication|null $authentication
     * @param string|null $version
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $isBadRequestClosure
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $willCauseErrorClosure
     * @param Closure(RequestContext $request): Response|null $handleFailedAuthClosure
     * @return ActionFailureReason|true
     */
    public function register(
        string $path,
        RequestMethod $requestMethod,
        Closure $handleClosure,
        ?Authentication $authentication = null,
        ?string $version = null,
        ?Closure $isBadRequestClosure = null,
        ?Closure $willCauseErrorClosure = null,
        ?Closure $handleFailedAuthClosure = null
    ): ActionFailureReason|true {
        $authentication = $authentication ?? new NoAuthAuthentication();
        return $this->registerPath($version !== null ? new ClosureApiPath(
            $path, $version, $requestMethod, $authentication, $handleClosure, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure
        ) : new ClosureRegularPath(
            $path, $requestMethod, $authentication, $handleClosure, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure
        ));
    }

    /**
     * @param string $path
     * @param Closure(RequestContext $request): Response $handleClosure
     * @param Authentication|null $authentication
     * @param string|null $version
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $isBadRequestClosure
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $willCauseErrorClosure
     * @param Closure(RequestContext $request): Response|null $handleFailedAuthClosure
     * @return ActionFailureReason|true
     */
    public function get(
        string $path,
        Closure $handleClosure,
        ?Authentication $authentication = null,
        ?string $version = null,
        ?Closure $isBadRequestClosure = null,
        ?Closure $willCauseErrorClosure = null,
        ?Closure $handleFailedAuthClosure = null
    ): ActionFailureReason|true {
        return $this->register($path, RequestMethod::GET, $handleClosure, $authentication, $version, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure);
    }

    /**
     * @param string $path
     * @param Closure(RequestContext $request): Response $handleClosure
     * @param Authentication|null $authentication
     * @param string|null $version
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $isBadRequestClosure
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $willCauseErrorClosure
     * @param Closure(RequestContext $request): Response|null $handleFailedAuthClosure
     * @return ActionFailureReason|true
     */
    public function post(
        string $path,
        Closure $handleClosure,
        ?Authentication $authentication = null,
        ?string $version = null,
        ?Closure $isBadRequestClosure = null,
        ?Closure $willCauseErrorClosure = null,
        ?Closure $handleFailedAuthClosure = null
    ): ActionFailureReason|true {
        return $this->register($path, RequestMethod::POST, $handleClosure, $authentication, $version, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure);
    }

    /**
     * @param string $path
     * @param Closure(RequestContext $request): Response $handleClosure
     * @param Authentication|null $authentication
     * @param string|null $version
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $isBadRequestClosure
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $willCauseErrorClosure
     * @param Closure(RequestContext $request): Response|null $handleFailedAuthClosure
     * @return ActionFailureReason|true
     */
    public function patch(
        string $path,
        Closure $handleClosure,
        ?Authentication $authentication = null,
        ?string $version = null,
        ?Closure $isBadRequestClosure = null,
        ?Closure $willCauseErrorClosure = null,
        ?Closure $handleFailedAuthClosure = null
    ): ActionFailureReason|true {
        return $this->register($path, RequestMethod::PATCH, $handleClosure, $authentication, $version, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure);
    }

    /**
     * @param string $path
     * @param Closure(RequestContext $request): Response $handleClosure
     * @param Authentication|null $authentication
     * @param string|null $version
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $isBadRequestClosure
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $willCauseErrorClosure
     * @param Closure(RequestContext $request): Response|null $handleFailedAuthClosure
     * @return ActionFailureReason|true
     */
    public function put(
        string $path,
        Closure $handleClosure,
        ?Authentication $authentication = null,
        ?string $version = null,
        ?Closure $isBadRequestClosure = null,
        ?Closure $willCauseErrorClosure = null,
        ?Closure $handleFailedAuthClosure = null
    ): ActionFailureReason|true {
        return $this->register($path, RequestMethod::PUT, $handleClosure, $authentication, $version, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure);
    }

    /**
     * @param string $path
     * @param Closure(RequestContext $request): Response $handleClosure
     * @param Authentication|null $authentication
     * @param string|null $version
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $isBadRequestClosure
     * @param Closure(RequestContext $request, ResponseBuilder $builder): bool|null $willCauseErrorClosure
     * @param Closure(RequestContext $request): Response|null $handleFailedAuthClosure
     * @return ActionFailureReason|true
     */
    public function delete(
        string $path,
        Closure $handleClosure,
        ?Authentication $authentication = null,
        ?string $version = null,
        ?Closure $isBadRequestClosure = null,
        ?Closure $willCauseErrorClosure = null,
        ?Closure $handleFailedAuthClosure = null
    ): ActionFailureReason|true {
        return $this->register($path, RequestMethod::DELETE, $handleClosure, $authentication, $version, $isBadRequestClosure, $willCauseErrorClosure, $handleFailedAuthClosure);
    }
}