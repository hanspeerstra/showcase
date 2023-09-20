<?php

declare(strict_types=1);

namespace App\Clients\Resources\Matchmaker;

use JsonSerializable;

class MatchmakerCompanyFinderContactOption implements JsonSerializable
{
    /** @var ContactMethod */
    private $contactMethod;

    public function __construct(ContactMethod $contactMethod)
    {
        $this->contactMethod = $contactMethod;
    }

    public function getContactMethod(): ContactMethod
    {
        return $this->contactMethod;
    }

    public function jsonSerialize(): array
    {
        return [
            'contactMethod' => $this->contactMethod,
        ];
    }
}
