<?php

declare(strict_types=1);

namespace App\Clients\Resources;

use App\Clients\Types\Company\OpeningHour;
use App\Clients\Types\Company\Presentation;
use JsonSerializable;

class Company extends AbstractCompany implements JsonSerializable
{
    /** @var string */
    public $slug;

    public function __construct(
        string $name,
        string $slug,
        ?string $logo
    ) {
        parent::__construct($name, $logo);

        $this->slug = $slug;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
