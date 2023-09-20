<?php

declare(strict_types=1);

namespace App\Geo\PDOK;

class PostcodeFactory
{
    public function makeFromApiResult(array $data): Postcode
    {
        return new Postcode($data['postcode']);
    }
}
