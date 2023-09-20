<?php

declare(strict_types=1);

namespace App\Geo;

class PostalCode
{
    /** @var int */
    private $digits;
    /** @var string|null */
    private $letters;

    public function __construct(int $digits, ?string $letters)
    {
        $this->digits = $digits;
        $this->letters = $letters;
    }

    public function getDigits(): int
    {
        return $this->digits;
    }

    public function getLetters(): ?string
    {
        return $this->letters;
    }

    public function __toString(): string
    {
        return sprintf('%s%s', $this->digits, $this->letters);
    }
}
