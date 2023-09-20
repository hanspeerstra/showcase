<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;

interface PDOKLocatieServerClientInterface
{
    public function sendAsyncHttpRequest(Request $request): PromiseInterface;
}
