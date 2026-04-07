<?php

namespace r3pt1s\httpserver;

use r3pt1s\httpserver\event\def\HttpServerStartedEvent;
use r3pt1s\httpserver\event\EventDispatcher;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseCache;
use r3pt1s\httpserver\route\Path;
use r3pt1s\httpserver\route\RegularPath;
use r3pt1s\httpserver\socket\SocketClient;
use r3pt1s\httpserver\socket\SocketServer;
use r3pt1s\httpserver\util\ActionFailureReason;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\HttpConstants;
use r3pt1s\httpserver\util\LoggerInterface;
use r3pt1s\httpserver\util\RateLimiter;
use r3pt1s\httpserver\util\RequestMethod;
use r3pt1s\httpserver\util\trait\QuickEventListenersTrait;
use r3pt1s\httpserver\util\trait\QuickRouteRegistrarsTrait;
use r3pt1s\httpserver\version\ApiVersion;
use RuntimeException;

final class HttpServer {
    use QuickRouteRegistrarsTrait, QuickEventListenersTrait;

    private EventDispatcher $eventDispatcher;
    private SocketServer $server;
    private ResponseCache $responseCache;

    /** @var array<ApiVersion> */
    private array $versions = [];
    /** @var array<array<Path>> */
    private array $paths = [];

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
        $pathRoute = "/" . trim($path->getPath(), "/");
        if ($path->getApiVersion() !== null && !$this->enableVersioning) {
            return ActionFailureReason::PATH_REGISTER_FAILED_VERSIONING_DISABLED;
        }

        if ($path instanceof RegularPath) {
            $this->paths[$path->getMethod()->name][$path->getFullPath()] = $path;
        } else {
            if (($version = $this->getVersion($path->getApiVersion())) !== null) {
                if (!$version->isValidPath($path->getMethod()->name, $pathRoute)) {
                    $version->addPath($path->getMethod()->name, $pathRoute);
                }

                $this->paths[$path->getMethod()->name][$path->getFullPath()] = $path;
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

        if (isset($this->versions[$version->getVersion()])) {
            return ActionFailureReason::VERSION_REGISTER_FAILED_VERSION_EXISTS;
        }

        $this->versions[$version->getVersion()] = $version;
        return true;
    }

    public function getVersion(string $versionOrPath, RequestMethod|string $method = "GET"): ?ApiVersion {
        $method = $method instanceof RequestMethod ? $method->name : $method;
        if (isset($this->versions[$versionOrPath])) return $this->versions[$versionOrPath];
        if (count($a = array_filter($this->versions, fn(ApiVersion $version) => $version->isValidPath($method, $versionOrPath))) > 0) return current($a);
        return null;
    }

    public function getVersions(): array {
        return $this->versions;
    }

    public function getPath(RequestMethod|string $method, string $path): ?Path {
        $method = $method instanceof RequestMethod ? $method->name : $method;
        return $this->paths[$method][$path] ?? null;
    }

    public function getPaths(): array {
        return $this->paths;
    }

    public function getEventDispatcher(): EventDispatcher {
        return $this->eventDispatcher;
    }

    public function getServer(): SocketServer {
        return $this->server;
    }

    public function getResponseCache(): ResponseCache {
        return $this->responseCache;
    }

    public function getRateLimitResponse(SocketClient $client, RequestContext $request, int $endTimestamp): Response {
        return $this->rateLimiter->prepareResponse($client, $request, $endTimestamp);
    }

    public function getAddress(): Address {
        return $this->address;
    }

    public function getRateLimiter(): RateLimiter {
        return $this->rateLimiter;
    }

    public function getLogger(): LoggerInterface {
        return $this->logger;
    }

    public function isEnableVersioning(): bool {
        return $this->enableVersioning;
    }

    public function isEnableResponseCaching(): bool {
        return $this->enableResponseCaching;
    }

    public function getCachingTimeInSeconds(): int {
        return $this->cachingTimeInSeconds;
    }
}