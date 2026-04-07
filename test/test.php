<?php

use r3pt1s\httpserver\event\def\HttpServerStartedEvent;
use r3pt1s\httpserver\HttpServerBuilder;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\Response;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\socket\auth\NoAuthAuthentication;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\RateLimiter;
use r3pt1s\httpserver\version\ApiVersion;

spl_autoload_register(function (string $class): void {
    $path = __DIR__ . "/../src/$class.php";
    if (file_exists($path)) require_once $path;
});

$httpServer = HttpServerBuilder::create(Address::create("127.0.0.1", 8081))
    ->enableVersioning(true)
    ->rateLimiter(RateLimiter::configure(false, 10, 1, 30))
    ->build();

$httpServer->registerVersion(new ApiVersion("v1", new NoAuthAuthentication()));
$httpServer->get("/me", function (RequestContext $request): Response {
    return ResponseBuilder::create()
        ->code(200)
        ->body(["message" => "hi"])
        ->build();
}, version: "v1");

$httpServer->post("/post", function (RequestContext $request): Response {
    var_dump($request->getBody());
    return ResponseBuilder::create()
        ->code(200)
        ->body(["message" => "posted"])
        ->build();
}, version: "v1");

$httpServer->listen(HttpServerStartedEvent::class, function (HttpServerStartedEvent $event): void {
    echo "Started." . PHP_EOL;
});
$httpServer->start();