<?php

namespace Domains\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property int $capacity
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 */
class Product extends Model
{
    public static function makeInstance(
        string $name,
        int $capacity
    ): self {
        return (new self())
            ->setName($name)
            ->setCapacity($capacity);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }
}
