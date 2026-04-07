<?php

namespace r3pt1s\httpserver\socket;

use r3pt1s\httpserver\event\def\ClientRateLimitedEvent;
use r3pt1s\httpserver\event\def\RequestErrorEvent;
use r3pt1s\httpserver\event\def\RequestFailedAuthenticationEvent;
use r3pt1s\httpserver\event\def\RequestHandleEvent;
use r3pt1s\httpserver\event\def\RequestInvalidEvent;
use r3pt1s\httpserver\HttpServer;
use r3pt1s\httpserver\io\RequestContext;
use r3pt1s\httpserver\io\ResponseBuilder;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\HttpConstants;
use r3pt1s\httpserver\util\StatusCode;
use r3pt1s\httpserver\util\Utils;
use Socket;
use Throwable;

final class SocketServer {

    private ?Socket $socket = null;

    /** @var array<string, Socket> */
    private array $clients = [];

    /** @var array<string, array{buffer: string, contentLength: int, headersComplete: bool, bodyStartPos: int, address: Address}> */
    private array $clientBuffers = [];

    private int $totalConnections = 0;
    private int $totalRequests = 0;

    public function __construct(private readonly HttpServer $server) {}

    public function create(): bool {
        if ($this->socket !== null) return false;
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) return false;

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_nonblock($this->socket);

        if (!socket_bind($this->socket, $this->server->address()->address(), $this->server->address()->port())) return false;
        return socket_listen($this->socket);
    }

    public function listen(): bool {
        if ($this->socket === null) return false;
        $lastCacheCleanup = time();

        while ($this->socket !== null) {
            $read = [$this->socket];

            foreach ($this->clients as $clientSocket) {
                $read[] = $clientSocket;
            }

            $write = null;
            $except = null;

            $changed = @socket_select($read, $write, $except, 0, 50 * 1000);

            if ($changed === false) continue;

            if ($changed === 0) {
                $this->performMaintenance($lastCacheCleanup);
                continue;
            }

            if (in_array($this->socket, $read)) {
                $this->acceptNewConnection();

                $key = array_search($this->socket, $read);
                unset($read[$key]);
            }

            foreach ($read as $clientSocket) {
                $this->handleClientData($clientSocket);
            }

            $this->performMaintenance($lastCacheCleanup);
        }

        return true;
    }
    
    private function acceptNewConnection(): void {
        $clientSocket = @socket_accept($this->socket);

        if ($clientSocket === false) return;
        if (!$clientSocket instanceof Socket) return;

        socket_set_nonblock($clientSocket);

        if (!@socket_getpeername($clientSocket, $address, $port)) {
            @socket_close($clientSocket);
            return;
        }

        $clientId = "$address:$port";
        $this->clients[$clientId] = $clientSocket;
        $this->clientBuffers[$clientId] = [
            "buffer" => "",
            "contentLength" => 0,
            "headersComplete" => false,
            "bodyStartPos" => 0,
            "address" => new Address($address, $port)
        ];

        $this->totalConnections++;
    }
    
    private function handleClientData(Socket $clientSocket): void {
        if (!@socket_getpeername($clientSocket, $address, $port)) {
            @socket_close($clientSocket);
            return;
        }

        $clientId = "$address:$port";

        if (!isset($this->clients[$clientId])) return;
        if (!isset($this->clientBuffers[$clientId])) return;

        $buffer = &$this->clientBuffers[$clientId];

        $chunk = @socket_read($clientSocket, HttpConstants::CHUNK_SIZE);

        if ($chunk === false || $chunk === "") {
            $this->closeClient($clientId);
            return;
        }

        $buffer["buffer"] .= $chunk;

        if (strlen($buffer["buffer"]) > HttpConstants::MAX_REQUEST_SIZE) {
            $this->server->logger()->warn("Request too large from %s, closing...", $buffer["address"]);
            $this->closeClient($clientId);
            return;
        }

        if (!$buffer["headersComplete"]) {
            if (($headerEndPos = strpos($buffer["buffer"], "\r\n\r\n")) !== false) {
                $buffer["headersComplete"] = true;
                $buffer["bodyStartPos"] = $headerEndPos + 4;

                $headerSection = substr($buffer["buffer"], 0, $headerEndPos);

                if (preg_match("/Content-Length:\s*(\d+)/i", $headerSection, $matches)) {
                    $buffer["contentLength"] = (int) $matches[1];

                    if ($buffer["contentLength"] > HttpConstants::MAX_REQUEST_SIZE) {
                        $this->server->logger()->warn("Content-Length too large from %s, closing...", $buffer["address"]);
                        $this->closeClient($clientId);
                        return;
                    }
                }
            }
        }

        if ($buffer["headersComplete"]) {
            $currentBodyLength = strlen($buffer["buffer"]) - $buffer["bodyStartPos"];

            if ($currentBodyLength >= $buffer["contentLength"]) {
                $this->processCompleteRequest($clientId, $buffer["buffer"], $buffer["address"]);
            }
        }
    }
    
    private function processCompleteRequest(string $clientId, string $requestBuffer, Address $address): void {
        if (!isset($this->clients[$clientId])) return;

        $this->totalRequests++;
        
        $client = new SocketClient($address, $this->clients[$clientId]);

        $this->server->responseCache()->tick($this->server);

        $this->handleRequest($client, $requestBuffer);

        unset($this->clients[$clientId]);
        unset($this->clientBuffers[$clientId]);
    }

    private function closeClient(string $clientId): void {
        if (isset($this->clients[$clientId])) {
            @socket_close($this->clients[$clientId]);
            unset($this->clients[$clientId]);
        }

        if (isset($this->clientBuffers[$clientId])) {
            unset($this->clientBuffers[$clientId]);
        }
    }

    private function performMaintenance(int &$lastCacheCleanup): void {
        $now = time();

        if (($now - $lastCacheCleanup) >= 5) {
            $this->server->responseCache()->tick($this->server);
            $lastCacheCleanup = $now;
        }
    }

    public function handleRequest(SocketClient $client, string $buffer): void {
        $request = null;
        try {
            $request = Utils::parseHttpRequest($this->server, $client->getAddress(), $buffer);
            if ($request instanceof StatusCode) {
                $client->respond(ResponseBuilder::create()
                    ->code($request)
                    ->build()
                );
                return;
            }

            $path = $request->path();

            foreach ($this->server->beforeActions() as $actionClosure) {
                $builder = ($actionClosure)($request);
                if ($builder instanceof ResponseBuilder) {
                    $client->respond($builder->build());
                    return;
                }
            }

            if ($path->apiVersion() !== null) {
                $ver = $this->server->getVersion($path->apiVersion());
                if ($ver !== null && !$ver->authentication()->authenticate($client, $request)) {
                    $this->server->eventDispatcher()->dispatch(new RequestFailedAuthenticationEvent($client, $request, true));
                    $client->respond($path->handleFailedAuth($request)->build());
                    return;
                }
            }

            if ($path->authentication()->authenticate($client, $request)) {
                if ($this->server->rateLimiter()->checkRequest($client->getAddress(), $endTimestamp, $justRateLimited)) {
                    if ($path->isBadRequest($request, $badRequestResponse = ResponseBuilder::create()->code(StatusCode::BAD_REQUEST))) {
                        $this->server->eventDispatcher()->dispatch($ev = new RequestInvalidEvent($client, $request, true, $badRequestResponse));
                        $client->respond($ev->getResponseBuilder()->build());
                        return;
                    }

                    if ($path->willCauseError($request, $errorResponse = ResponseBuilder::create()->code(StatusCode::INTERNAL_SERVER_ERROR))) {
                        $this->server->eventDispatcher()->dispatch($ev = new RequestInvalidEvent($client, $request, false, $errorResponse));
                        $client->respond($ev->getResponseBuilder()->build());
                        return;
                    }

                    $response = $this->server->responseCache()->check($this->server, $request);
                    $this->server->eventDispatcher()->dispatch($ev = new RequestHandleEvent($client, $request, $response !== null));
                    if ($ev->isCanceled()) {
                        $client->close();
                        return;
                    }

                    if (!$ev->isUseCachedResponse()) {
                        $response = $path->handle($request);
                        if ($response->getStatusCode() == 200) {
                            $this->server->responseCache()->cache($this->server, $request, $response);
                        }
                    }

                    foreach ($this->server->afterActions() as $actionClosure) {
                        ($actionClosure)($request, $response);
                    }

                    if ($response !== null) $client->respond($response->build());
                    else $client->close();
                } else {
                    if ($justRateLimited) $this->server->eventDispatcher()->dispatch(new ClientRateLimitedEvent($client, $request, $endTimestamp));
                    $client->respond($this->server->rateLimitResponse($client, $request, $endTimestamp));
                }
            } else {
                $this->server->eventDispatcher()->dispatch(new RequestFailedAuthenticationEvent($client, $request, false));
                $client->respond($path->handleFailedAuth($request)->build());
            }
        } catch (Throwable $exception) {
            $this->server->logger()->error("Unexpected error has occurred while processing request from %s (%s)", $client->getAddress(), $request?->path()->fullPath() ?? "Request not parsed yet");
            $this->server->logger()->exception($exception);
            $this->server->eventDispatcher()->dispatch(new RequestErrorEvent(
                $client,
                $request instanceof RequestContext ? $request : null,
                $exception
            ));

            $builder = ResponseBuilder::create()
                ->code(StatusCode::INTERNAL_SERVER_ERROR)
                ->body(["message" => "An unexpected error has occurred while processing your request.", "error" => $exception->getMessage()]);

            if (isset($this->server->mappedExceptions()[$exception::class])) {
                ($this->server->mappedExceptions()[$exception::class])($request instanceof RequestContext ? $request : null, $client, $exception, $builder);
            }

            $client->respond($builder->build());
        }
    }

    public function close(): void {
        if ($this->socket !== null) {
            foreach ($this->clients as $clientId => $clientSocket) $this->closeClient($clientId);
            socket_close($this->socket);
            $this->socket = null;
        }
    }
}