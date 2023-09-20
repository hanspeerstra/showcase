<?php

declare(strict_types=1);

namespace App\Clients\Utils;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class ResponseUtil
{
    public static function handleAwaitedResponse(array $response): ResponseInterface
    {
        $state = $response['state'];

        if ($state === PromiseInterface::FULFILLED) {
            return $response['value'];
        }

        if ($state === PromiseInterface::REJECTED) {
            /** @var ClientException $exception */
            $exception = $response['reason'];

            throw $exception;
        }

        throw new UnexpectedValueException(
            sprintf('Unhandled response state "%s".', $state)
        );
    }
}
