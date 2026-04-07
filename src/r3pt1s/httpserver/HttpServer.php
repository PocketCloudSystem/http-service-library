<?php

namespace r3pt1s\httpserver;

use Closure;
use r3pt1s\httpserver\event\def\HttpServerStartedEvent;
use r3pt1s\httpserver\event\EventDispatcher;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\io\ResponseCache;
use r3pt1s\httpserver\route\Path;
use r3pt1s\httpserver\route\RegularPath;
use r3pt1s\httpserver\socket\SocketClient;
use r3pt1s\httpserver\socket\SocketServer;
use r3pt1s\httpserver\util\ActionFailureReason;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\LoggerInterface;
use r3pt1s\httpserver\util\RateLimiter;
use r3pt1s\httpserver\util\RequestMethod;
use r3pt1s\httpserver\util\trait\QuickEventListenersTrait;
use r3pt1s\httpserver\util\trait\QuickRouteRegistrarsTrait;
use r3pt1s\httpserver\version\ApiVersion;
use RuntimeException;
use Throwable;

final class HttpServer {
    use QuickRouteRegistrarsTrait, QuickEventListenersTrait;

    private EventDispatcher $eventDispatcher;
    private SocketServer $server;
    private ResponseCache $responseCache;

    /** @var array<ApiVersion> */
    private array $versions = [];
    /** @var array<array<Path>> */
    private array $paths = [];

    private array $beforeActions = [];
    private array $afterActions = [];
    private array $mappedExceptions = [];

    public function __construct(
        private readonly Address $address,
        private readonly RateLimiter $rateLimiter,
        private readonly LoggerInterface $logger,
        private readonly bool $enableVersioning,
        private readonly bool $enableResponseCaching,
        private readonly int $cachingTimeInSeconds = 60
    ) {
        $this->eventDispatcher = new EventDispatcher();
        $this->responseCache = new ResponseCache();
        $this->server = new SocketServer($this);
    }

    /**
     * These closures are called before the handling of a request happens.
     * @param Closure(RequestContext $context): ?ResponseBuilder $closure
     * @return $this
     */
    public function before(Closure $closure): self {
        $this->beforeActions[] = $closure;
        return $this;
    }

    /**
     * These closures are called after the handling of a request happened.
     * @param Closure(RequestContext $context, ResponseBuilder $builder): void $closure
     * @return $this
     */
    public function after(Closure $closure): self {
        $this->afterActions[] = $closure;
        return $this;
    }

    /**
     * These closures are called when an exception occurs during the request handling.
     * @param string $exceptionClass
     * @param Closure(RequestContext|null $request, SocketClient $client, Throwable $exception, ResponseBuilder $builder): void $closure
     * @return $this
     */
    public function exception(string $exceptionClass, Closure $closure): self {
        $this->mappedExceptions[$exceptionClass] = $closure;
        return $this;
    }

    /**
     * @return void
     * @throws RuntimeException
     */
    public function start(): void {
        if (!$this->server->create()) {
            throw new RuntimeException("Failed to establish http server on address: " . $this->address);
        } else {
            $this->logger->info("Successfully established http server on address: %s", $this->address)
                ->info("Waiting for incoming requests...");
            $this->eventDispatcher->dispatch(new HttpServerStartedEvent());
            $this->server->listen();
        }
    }

    public function stop(): void {
        $this->server->close();
    }

    public function registerPath(Path $path): ActionFailureReason|true {
        $pathRoute = "/" . trim($path->path(), "/");
        if ($path->apiVersion() !== null && !$this->enableVersioning) {
            return ActionFailureReason::PATH_REGISTER_FAILED_VERSIONING_DISABLED;
        }

        if ($path instanceof RegularPath) {
            $this->paths[$path->method()->name][$path->fullPath()] = $path;
        } else {
            if (($version = $this->getVersion($path->apiVersion())) !== null) {
                if (!$version->validPath($path->method()->name, $pathRoute)) {
                    $version->add($path->method()->name, $pathRoute);
                }

                $this->paths[$path->method()->name][$path->fullPath()] = $path;
            } else {
                return ActionFailureReason::PATH_REGISTER_FAILED_API_VERSION_NOT_EXISTENT;
            }
        }

        return true;
    }

    public function registerVersion(ApiVersion $version): ActionFailureReason|true {
        if (!$this->enableVersioning) {
            return ActionFailureReason::VERSION_REGISTER_FAILED_VERSIONING_DISABLED;
        }

        if (isset($this->versions[$version->version()])) {
            return ActionFailureReason::VERSION_REGISTER_FAILED_VERSION_EXISTS;
        }

        $this->versions[$version->version()] = $version;
        return true;
    }

    public function beforeActions(): array {
        return $this->beforeActions;
    }

    public function afterActions(): array {
        return $this->afterActions;
    }

    public function mappedExceptions(): array {
        return $this->mappedExceptions;
    }

    public function getVersion(string $versionOrPath, RequestMethod|string $method = "GET"): ?ApiVersion {
        $method = $method instanceof RequestMethod ? $method->name : $method;
        if (isset($this->versions[$versionOrPath])) return $this->versions[$versionOrPath];
        if (count($a = array_filter($this->versions, fn(ApiVersion $version) => $version->validPath($method, $versionOrPath))) > 0) return current($a);
        return null;
    }

    public function versions(): array {
        return $this->versions;
    }

    public function getPath(RequestMethod|string $method, string $path): ?Path {
        $method = $method instanceof RequestMethod ? $method->name : $method;
        return $this->paths[$method][$path] ?? null;
    }

    public function paths(): array {
        return $this->paths;
    }

    public function eventDispatcher(): EventDispatcher {
        return $this->eventDispatcher;
    }

    public function server(): SocketServer {
        return $this->server;
    }

    public function responseCache(): ResponseCache {
        return $this->responseCache;
    }

    public function rateLimitResponse(SocketClient $client, RequestContext $request, int $endTimestamp): Response {
        return $this->rateLimiter->prepareResponse($client, $request, $endTimestamp);
    }

    public function address(): Address {
        return $this->address;
    }

    public function rateLimiter(): RateLimiter {
        return $this->rateLimiter;
    }

    public function logger(): LoggerInterface {
        return $this->logger;
    }

    public function enabledVersioning(): bool {
        return $this->enableVersioning;
    }

    public function enabledResponseCaching(): bool {
        return $this->enableResponseCaching;
    }

    public function cachingTimeInSeconds(): int {
        return $this->cachingTimeInSeconds;
    }
}