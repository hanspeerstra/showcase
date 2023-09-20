<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase\Http\Resource;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCaseNote;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCenterCaseNote
 */
class ServiceCenterCaseNoteResource extends JsonResource
{
    public function __construct(ServiceCenterCaseNote $resource)
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'createdAt' => self::formatDateTime($this->getCreatedAt()),
            'updatedAt' => self::formatDateTime($this->getUpdatedAt()),
            'agent' => [
                'id' => $this->getAgent()->getId(),
                'firstName' => $this->getAgent()->getFirstName(),
                'lastName' => $this->getAgent()->getLastName(),
                // TODO more fields
            ],
            'note' => $this->getNote(),
        ];
    }

    private static function formatDateTime(?DateTimeInterface $dateTime): ?string
    {
        if (null === $dateTime) {
            return null;
        }

        return $dateTime->format(DateTimeInterface::ATOM);
    }
}
