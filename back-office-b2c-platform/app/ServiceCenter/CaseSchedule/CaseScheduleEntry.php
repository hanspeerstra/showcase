<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use Carbon\CarbonInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $id
 * @property ServiceCenterCase $case
 * @property CarbonInterface $due_at
 * @property CarbonInterface $created_at
 *
 * @method CaseScheduleEntry|Builder|\Illuminate\Database\Query\Builder whereCase(ServiceCenterCase $case)
 * @see CaseScheduleEntry::scopeWhereCase()
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 *
 * @mixin Eloquent
 */
class CaseScheduleEntry extends Model
{
    use SoftDeletes;

    protected $table = 'sc_case_schedule';

    protected $dates = [
        'due_at',
    ];

    public static function makeInstance(
        ServiceCenterCase $case,
        CarbonInterface $dueAt
    ): self {
        return (new static())
            ->setCase($case)
            ->setDueAt($dueAt);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): CarbonInterface
    {
        return $this->created_at;
    }

    public function getCase(): ServiceCenterCase
    {
        return $this->case;
    }

    private function setCase(ServiceCenterCase $case): self
    {
        $this->case()->associate($case);

        return $this;
    }

    public function getDueAt(): CarbonInterface
    {
        return $this->due_at;
    }

    private function setDueAt(CarbonInterface $dueAt): self
    {
        $this->due_at = $dueAt;

        return $this;
    }

    /**
     * @internal
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCase::class);
    }

    public function scopeWhereCase(Builder $builder, ServiceCenterCase $case): void
    {
        $builder->where('case_id', $case->getId());
    }
}
