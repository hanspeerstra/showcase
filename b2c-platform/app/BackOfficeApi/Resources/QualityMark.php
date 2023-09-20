<?php

declare(strict_types=1);

namespace App\Clients\Resources;

class QualityMark
{
    /** @var int */
    private $id;
    /** @var string */
    private $name;
    /** @var string */
    private $slug;
    /** @var string */
    private $logo;
    /** @var string|null */
    private $type;

    public function __construct(
        int $id,
        string $name,
        string $slug,
        string $logo,
        ?string $type
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->logo = $logo;
        $this->type = $type;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getLogo(): string
    {
        return $this->logo;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
