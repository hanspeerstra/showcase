<?php

namespace Domains\Booking\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property DateTimeInterface|null $date_of_birth
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 */
class Guest extends Model
{
    public static function makeInstance(
        string $name,
        DateTimeInterface $dateOfBirth
    ): self {
        return (new self())
            ->setName($name)
            ->setDateOfBirth($dateOfBirth);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDateOfBirth(): ?DateTimeInterface
    {
        return $this->date_of_birth;
    }

    public function setDateOfBirth(?DateTimeInterface $dateOfBirth): self
    {
        $this->date_of_birth = $dateOfBirth;

        return $this;
    }
}
