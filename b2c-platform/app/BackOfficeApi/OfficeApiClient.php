<?php

namespace App\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Response;
use Psr\Http\Message\ResponseInterface;

class OfficeApiClient
{
    public const JSON_HEADERS = [
        HeaderName::ACCEPT => MimeType::JSON,
        HeaderName::CONTENT_TYPE => MimeType::JSON,
    ];

    public const ACCEPT_JSON_HEADER = [
        HeaderName::ACCEPT => MimeType::JSON,
    ];

    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function sendHttpRequest(Request $request): ResponseInterface
    {
        return $this->client->send($request);
    }

    public function sendAsyncHttpRequest(Request $request): PromiseInterface
    {
        return $this->client->sendAsync($request);
    }

    private function performHttpRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}
