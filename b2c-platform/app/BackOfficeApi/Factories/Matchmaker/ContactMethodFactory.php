<?php

declare(strict_types=1);

namespace App\Clients\Factories\Matchmaker;

use App\Clients\Resources\Matchmaker\ContactMethod;

class ContactMethodFactory
{
    public function makeFromApiResult(object $data): ContactMethod
    {
        return new ContactMethod(
            $data->id,
            $data->name
        );
    }
}
