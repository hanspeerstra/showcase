<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;

class PDOKLocatieServerClient implements PDOKLocatieServerClientInterface
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function sendAsyncHttpRequest(Request $request): PromiseInterface
    {
        return $this->client->sendAsync($request);
    }
}
