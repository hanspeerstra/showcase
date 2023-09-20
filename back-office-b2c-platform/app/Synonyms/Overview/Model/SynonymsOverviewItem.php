<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Model;

use App\Models\Office\Profession;
use App\Models\Office\Servicetype;
use Carbon\CarbonInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UnexpectedValueException;

/**
 * This is a read-only "virtual" model.
 *
 * @property CarbonInterface|null $updated_at
 * @property Servicetype|null $serviceType
 * @property Profession|null $profession
 *
 * @method Profession|Builder|\Illuminate\Database\Query\Builder whereInType(array $types)
 * @see SynonymsOverviewItem::scopeWhereInType()
 *
 * @method static SynonymsOverviewItem|Builder|\Illuminate\Database\Query\Builder query()
 *
 * @mixin Eloquent
 */
class SynonymsOverviewItem extends Model
{
    public const COL_SERVICE_TYPE_ID = 'service_type_id';
    public const COL_PROFESSION_ID = 'profession_id';
    public const COL_UPDATED_AT = 'updated_at';

    public const TYPE_SERVICE_TYPE = 'service_type';
    public const TYPE_PROFESSION = 'profession';

    public const TYPES = [
        self::TYPE_SERVICE_TYPE,
        self::TYPE_PROFESSION,
    ];

    public $timestamps = false;

    protected $dates = [
        self::COL_UPDATED_AT,
    ];

    public function getId(): int
    {
        if ($this->serviceType !== null) {
            return $this->serviceType->getId();
        }

        if ($this->profession !== null) {
            return $this->profession->getId();
        }

        throw new UnexpectedValueException('Could not determine id.');
    }

    public function getType(): string
    {
        if ($this->serviceType !== null) {
            return self::TYPE_SERVICE_TYPE;
        }

        if ($this->profession !== null) {
            return self::TYPE_PROFESSION;
        }

        throw new UnexpectedValueException('Could not determine type.');
    }

    public function getUpdatedAt(): ?CarbonInterface
    {
        return $this->updated_at;
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(Servicetype::class);
    }

    public function getServiceType(): ?Servicetype
    {
        return $this->serviceType;
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function getProfession(): ?Profession
    {
        return $this->profession;
    }

    /**
     * @param static|Builder $queryBuilder
     * @param string[] $types
     *
     * @return static|Builder
     */
    public function scopeWhereInType(Builder $queryBuilder, array $types): Builder
    {
        $queryBuilder->where(static function (Builder $queryBuilder) use ($types) {
            foreach ($types as $type) {
                if ($type === self::TYPE_SERVICE_TYPE) {
                    $queryBuilder->orWhereNotNull(self::COL_SERVICE_TYPE_ID);
                } elseif ($type === self::TYPE_PROFESSION) {
                    $queryBuilder->orWhereNotNull(self::COL_PROFESSION_ID);
                }
            }
        });

        return $queryBuilder;
    }
}
