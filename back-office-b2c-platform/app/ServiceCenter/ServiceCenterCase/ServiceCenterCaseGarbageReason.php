<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

use Carbon\CarbonInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $type
 * @property string $label
 * @property string $name
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @method ServiceCenterCaseGarbageReason|Builder|\Illuminate\Database\Query\Builder belongsToLabel(string $label)
 * @see ServiceCenterCaseGarbageReason::scopeBelongsToLabel()
 *
 * @method ServiceCenterCaseGarbageReason|Builder|\Illuminate\Database\Query\Builder isForUser()
 * @see ServiceCenterCaseGarbageReason::scopeIsForUser()
 *
 * @method ServiceCenterCaseGarbageReason|Builder|\Illuminate\Database\Query\Builder otherNamelyLast()
 * @see ServiceCenterCaseGarbageReason::scopeOtherNamelyLast()
 *
 * @method static ServiceCenterCaseGarbageReason|Builder|\Illuminate\Database\Query\Builder query()
 *
 * @mixin Eloquent
 */
class ServiceCenterCaseGarbageReason extends Model
{
    use SoftDeletes;

    public const LABEL_CLOSED_BY_SYSTEM_USER = 'case-automatisch-gesloten';
    public const LABEL_CLOSED_BY_FORCE_CLOSE_AGENT = 'case-agent-geforceerd-afgesloten';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_VALID_CALLER = 'valid_caller';
    public const TYPE_INVALID_CALLER = 'invalid_caller';
    private const LABEL_VALID_CALLER_OTHER_NAMELY = 'valid-caller-anders-namelijk';
    private const LABEL_INVALID_CALLER_OTHER_NAMELY = 'invalid-caller-anders-namelijk';

    protected $table = 'sc_case_garbage_reasons';

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): CarbonInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updated_at;
    }

    /**
     * Filters on label
     *
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeBelongsToLabel(Builder $queryBuilder, string $label): Builder
    {
        return $queryBuilder->where('label', $label);
    }

    /**
     * Filters on label
     *
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeIsForUser(Builder $queryBuilder): Builder
    {
        return $queryBuilder->where('type', '<>', self::TYPE_SYSTEM);
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeOtherNamelyLast(Builder $queryBuilder): Builder
    {
        return $queryBuilder->orderByRaw(
            'label IN (?, ?) ASC',
            [
                self::LABEL_VALID_CALLER_OTHER_NAMELY,
                self::LABEL_INVALID_CALLER_OTHER_NAMELY,
            ]
        );
    }
}
