<?php

namespace r3pt1s\httpclient;

use Closure;
use InvalidArgumentException;
use r3pt1s\httpclient\io\ClientRequestContext;
use r3pt1s\httpclient\io\ClientResponse;
use r3pt1s\httpserver\util\Address;
use r3pt1s\httpserver\util\RequestMethod;
use r3pt1s\httpserver\util\StatusCode;
use RuntimeException;
use Throwable;

final class HttpClient {

    private array $beforeActions = [];
    private array $afterActions = [];

    private ?Throwable $lastError = null;

    public function __construct(
        private readonly Address $address,
        private readonly ?string $basePath,
        private readonly array $defaultHeaders,
        private readonly float $timeout,
        private readonly int $retries
    ) {}

    /**
     * These closures are called before the execution of a request happens.
     * @param Closure(ClientRequestContext $context): void $closure
     * @return $this
     */
    public function before(Closure $closure): self {
        $this->beforeActions[] = $closure;
        return $this;
    }

    /**
     * These closures are called after the execution of a request happened.
     * @param Closure(ClientRequestContext $context, ClientResponse $response): void $closure
     * @return $this
     */
    public function after(Closure $closure): self {
        $this->afterActions[] = $closure;
        return $this;
    }

    /**
     * Executes multiple contexts at the same time.
     * This method does not check for beforeActions, afterActions and retries.
     * @param ClientRequestContext ...$contexts
     * @return array
     */
    public function multi(ClientRequestContext ...$contexts): array {
        $multiHandle = curl_multi_init();
        $handles = [];
        $responses = [];

        /**
         * @var mixed $key
         * @var ClientRequestContext $ctx
         */
        foreach ($contexts as $key => $ctx) {
            curl_multi_add_handle($multiHandle, $curl = curl_copy_handle($ctx->prepareCurlHandle()));
            $handles[$key] = $curl;
        }

        do {
            $status = curl_multi_exec($multiHandle, $active);
            curl_multi_select($multiHandle);
        } while ($active && $status == CURLM_OK);

        foreach ($handles as $key => $ch) {
            $result = curl_multi_getcontent($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);

            if ($errno) {
                $responses[$key] = new RuntimeException("cURL Error ($errno): $error");
            } else {
                $len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $headerLines = explode("\r\n", substr($result, 0, $len));
                $body = substr($result, $len);
                $decodedBody = null;

                foreach ($headerLines as $line) {
                    if (str_contains($line, ":")) {
                        [$name, $val] = explode(":", $line, 2);
                        if (strtolower(trim($name)) === "content-type" && str_contains($val, "application/json")) {
                            $decodedBody = json_decode($body, true);
                        }
                    }
                }

                $responses[$key] = new ClientResponse(
                    $contexts[$key]->url(),
                    $contexts[$key]->method(),
                    StatusCode::tryFrom(curl_getinfo($ch, CURLINFO_HTTP_CODE)) ?? StatusCode::UNKNOWN,
                    $headerLines,
                    $body,
                    $decodedBody,
                    0
                );
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        return $responses;
    }

    public function prepareContext(
        string $path,
        RequestMethod $method,
        array $queries,
        mixed $body,
        array $headers
    ): ClientRequestContext {
        if ($method === RequestMethod::GET && $body !== null) {
            throw new InvalidArgumentException("GET request cannot have a body");
        }

        $basePath = $this->basePath !== null ? trim($this->basePath, "/") . "/" : "";
        $path = trim($path, "/");
        $finalUrl = "http://" . $this->address . "/" . $basePath . $path;

        $finalHeaders = array_merge($this->defaultHeaders, $headers);
        return new ClientRequestContext($finalUrl, $method, $finalHeaders, $queries, $body, $this->timeout, $this->retries);
    }

    public function send(
        string $path,
        RequestMethod $method,
        array $queries = [],
        mixed $body = null,
        array $headers = []
    ): ClientResponse|false {
        $clientContext = $this->prepareContext($path, $method, $queries, $body, $headers);
        foreach ($this->beforeActions as $beforeAction) {
            try {
                ($beforeAction)($clientContext);
            } catch (Throwable $e) {
                $this->lastError = $e;
                return false;
            }
        }

        $res = $clientContext->execute();
        if ($res instanceof Throwable) {
            $this->lastError = $res;
            return false;
        }

        foreach ($this->afterActions as $afterAction) {
            try {
                ($afterAction)($clientContext, $res);
            } catch (Throwable $e) {
                $this->lastError = $e;
                return false;
            }
        }

        return $res;
    }

    public function get(string $path, array $queries = [], array $headers = []): ClientResponse {
        return $this->send($path, RequestMethod::GET, $queries, null, $headers);
    }

    public function post(string $path, mixed $body = null, array $queries = [], array $headers = []): ClientResponse {
        return $this->send($path, RequestMethod::POST, $queries, $body, $headers);
    }

    public function patch(string $path, mixed $body = null, array $queries = [], array $headers = []): ClientResponse {
        return $this->send($path, RequestMethod::PATCH, $queries, $body, $headers);
    }

    public function put(string $path, mixed $body = null, array $queries = [], array $headers = []): ClientResponse {
        return $this->send($path, RequestMethod::PUT, $queries, $body, $headers);
    }

    public function delete(string $path, array $queries = [], mixed $body = null, array $headers = []): ClientResponse {
        return $this->send($path, RequestMethod::DELETE, $queries, $body, $headers);
    }

    public function contextGet(string $path, array $queries = [], array $headers = []): ClientRequestContext {
        return $this->prepareContext($path, RequestMethod::GET, $queries, null, $headers);
    }

    public function contextPost(string $path, mixed $body = null, array $queries = [], array $headers = []): ClientRequestContext {
        return $this->prepareContext($path, RequestMethod::POST, $queries, $body, $headers);
    }

    public function contextPatch(string $path, mixed $body = null, array $queries = [], array $headers = []): ClientRequestContext {
        return $this->prepareContext($path, RequestMethod::PATCH, $queries, $body, $headers);
    }

    public function contextPut(string $path, mixed $body = null, array $queries = [], array $headers = []): ClientRequestContext {
        return $this->prepareContext($path, RequestMethod::PUT, $queries, $body, $headers);
    }

    public function contextDelete(string $path, array $queries = [], mixed $body = null, array $headers = []): ClientRequestContext {
        return $this->prepareContext($path, RequestMethod::DELETE, $queries, $body, $headers);
    }

    public function lastError(): ?Throwable {
        return $this->lastError;
    }

    public function address(): Address {
        return $this->address;
    }

    public function basePath(): ?string {
        return $this->basePath;
    }

    public function defaultHeaders(): array {
        return $this->defaultHeaders;
    }

    public function timeout(): float {
        return $this->timeout;
    }

    public function retries(): int {
        return $this->retries;
    }
}