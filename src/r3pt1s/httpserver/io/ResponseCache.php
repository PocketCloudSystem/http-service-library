<?php

namespace r3pt1s\httpserver\io;

use r3pt1s\httpserver\HttpServer;
use r3pt1s\httpserver\util\RequestMethod;

final class ResponseCache {

    private array $cache = [];

    public function tick(HttpServer $server): void {
        $cachingTime = $server->cachingTimeInSeconds();
        $now = time();
        $keysToRemove = [];

        foreach ($this->cache as $pathString => $data) {
            [, $time] = $data;
            if ($now >= ($time + $cachingTime)) {
                $keysToRemove[] = $pathString;
            }
        }

        foreach ($keysToRemove as $key) {
            unset($this->cache[$key]);
        }
    }

    public function cache(HttpServer $server, RequestContext $request, ResponseBuilder $response): void {
        if (!$server->enabledResponseCaching()) return;
        $this->cache[$this->buildCacheKey($request)] = [$response, time()];
    }

    public function check(HttpServer $server, RequestContext $request): ?ResponseBuilder {
        if (!$server->enabledResponseCaching()) return null;
        if ($request->method() !== RequestMethod::GET) return null;
        $cacheKey = $this->buildCacheKey($request);
        if (!isset($this->cache[$cacheKey])) return null;
        [$response, $time] = $this->cache[$cacheKey];

        if (time() >= ($time + $server->cachingTimeInSeconds())) {
            unset($this->cache[$cacheKey]);
            return null;
        }

        return $response;
    }

    private function buildCacheKey(RequestContext $request): string {
        $path = $request->path();
        $queries = $request->queries(true);
        $apiVersion = $path->apiVersion() ?? "no-version";
        $fullPath = $path->fullPath() . (count($queries) == 0 ? "" : "?" . http_build_query($queries));
        return $apiVersion . ":" . $path->method()->name . ":" . $fullPath;
    }
}