<?php

declare(strict_types=1);

namespace App\Tracking;

class TrackingContext implements \JsonSerializable
{
    /** @var string|null */
    private $page;
    /** @var string|null */
    private $pageElement;

    public function __construct(?string $page, ?string $pageElement)
    {
        $this->page = $page;
        $this->pageElement = $pageElement;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function getPageElement(): ?string
    {
        return $this->pageElement;
    }

    public function jsonSerialize(): array
    {
        return [
            'page' => $this->page,
            'pageElement' => $this->pageElement,
        ];
    }
}
