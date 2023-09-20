<?php

declare(strict_types=1);

namespace App\Clients\Resources;

abstract class AbstractCompany
{
    /** @var string */
    protected $name;
    /** @var string|null */
    protected $logo;

    public function __construct(
        string $name,
        ?string $logo
    ) {
        $this->name = $name;
        $this->logo = $logo;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }
}
