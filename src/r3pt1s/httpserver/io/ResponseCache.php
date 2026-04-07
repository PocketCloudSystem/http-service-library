<?php

namespace r3pt1s\httpserver\io;

use r3pt1s\httpserver\HttpServer;
use r3pt1s\httpserver\util\RequestMethod;

final class ResponseCache {

    private array $cache = [];

    public function tick(HttpServer $server): void {
        $cachingTime = $server->getCachingTimeInSeconds();
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

    public function cache(HttpServer $server, RequestContext $request, Response $response): void {
        if (!$server->isEnableResponseCaching()) return;
        $this->cache[$this->buildCacheKey($request)] = [$response, time()];
    }

    public function check(HttpServer $server, RequestContext $request): ?Response {
        if (!$server->isEnableResponseCaching()) return null;
        if ($request->getMethod() !== RequestMethod::GET) return null;
        $cacheKey = $this->buildCacheKey($request);
        if (!isset($this->cache[$cacheKey])) return null;
        [$response, $time] = $this->cache[$cacheKey];

        if (time() >= ($time + $server->getCachingTimeInSeconds())) {
            unset($this->cache[$cacheKey]);
            return null;
        }

        return $response;
    }

    private function buildCacheKey(RequestContext $request): string {
        $path = $request->getPath();
        $queries = $request->getQueries(true);
        $apiVersion = $path->getApiVersion() ?? "no-version";
        $fullPath = $path->getFullPath() . (count($queries) == 0 ? "" : "?" . http_build_query($queries));
        return $apiVersion . ":" . $path->getMethod()->name . ":" . $fullPath;
    }
}