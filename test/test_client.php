<?php

use r3pt1s\httpclient\HttpClientBuilder;
use r3pt1s\httpclient\util\MultipartBody;
use r3pt1s\httpserver\util\Address;

spl_autoload_register(function (string $class): void {
    $path = __DIR__ . "/../src/$class.php";
    if (file_exists($path)) require_once $path;
});

$httpClient = HttpClientBuilder::create(new Address("localhost", "8081"))
    ->build();

$contexts = [
    $httpClient->contextGet("v1/me"),
    $httpClient->contextPost("v1/post", MultipartBody::create()
        ->field("server", "Lobby-1")
        ->file("server.log", rtrim(__DIR__, "/") . "/server.log")
    )
];

$res = $httpClient->multi(...$contexts);
var_dump($res);