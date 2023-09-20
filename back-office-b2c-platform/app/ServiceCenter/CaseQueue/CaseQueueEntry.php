<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseQueue;

use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\ServiceCenter\WorkGroup\WorkGroup;
use Carbon\CarbonInterface;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int|null $id
 * @property ServiceCenterCase $case
 * @property WorkGroup $workGroup
 * @property bool $automatically_assign
 * @property CarbonInterface $created_at
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 * @method static static|Builder|\Illuminate\Database\Query\Builder byPriority()
 * @see CaseQueueEntry::scopeByPriority()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasAutomaticallyAssign()
 * @see CaseQueueEntry::scopeHasAutomaticallyAssign()
 * @method static static|Builder|\Illuminate\Database\Query\Builder caseIsInteractive()
 * @see CaseQueueEntry::scopeCaseIsInteractive()
 * @method static static|Builder|\Illuminate\Database\Query\Builder caseIsPassive()
 * @see CaseQueueEntry::scopeCaseIsPassive()
 * @method static static|Builder|\Illuminate\Database\Query\Builder hasWorkGroups(WorkGroup ...$workGroups)
 * @see CaseQueueEntry::scopeHasWorkGroups()
 *
 * @mixin Eloquent
 */
class CaseQueueEntry extends Model
{
    use SoftDeletes;

    protected $table = 'sc_case_queue';

    protected $casts = [
        'automatically_assign' => 'boolean',
    ];

    public static function makeInstance(
        ServiceCenterCase $case,
        WorkGroup $workGroup,
        bool $automaticallyAssign = true
    ): self {
        return (new static())
            ->setCase($case)
            ->setWorkGroup($workGroup)
            ->setAutomaticallyAssign($automaticallyAssign);
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

    public function getWorkGroup(): WorkGroup
    {
        return $this->workGroup;
    }

    private function setWorkGroup(WorkGroup $workGroup): self
    {
        $this->workGroup()->associate($workGroup);

        return $this;
    }

    /**
     * @internal
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCase::class);
    }

    /**
     * @internal
     */
    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }

    public function isAutomaticallyAssign(): bool
    {
        return $this->automatically_assign;
    }

    private function setAutomaticallyAssign(bool $automaticallyAssign): self
    {
        $this->automatically_assign = $automaticallyAssign;

        return $this;
    }

    public function scopeByPriority(Builder $queryBuilder): Builder
    {
        $queryBuilder->orderBy('created_at'); // FIFO order (oldest cases first)

        return $queryBuilder;
    }

    public function scopeHasAutomaticallyAssign(Builder $queryBuilder): Builder
    {
        $queryBuilder->where('automatically_assign', '=', true);

        return $queryBuilder;
    }

    public function scopeCaseIsInteractive(Builder $queryBuilder): Builder
    {
        $queryBuilder->whereHas(
            'case',
            /** @param ServiceCenterCase|Builder $queryBuilder */
            static fn (Builder $queryBuilder) => $queryBuilder->isContactMethodTelephony()->hasAnyTelephonySession()
        );

        return $queryBuilder;
    }

    public function scopeCaseIsPassive(Builder $queryBuilder): Builder
    {
        $queryBuilder->where(static function (Builder $queryBuilder) {
            /** @param CaseQueueEntry|Builder $queryBuilder */
            $queryBuilder
                ->whereHas(
                    'case',
                    /** @param ServiceCenterCase|Builder $queryBuilder */
                    static fn (Builder $queryBuilder) => $queryBuilder->isContactMethodElectronic()
                )
                ->orWhereHas(
                    'case',
                    /** @param ServiceCenterCase|Builder $queryBuilder */
                    static fn (Builder $queryBuilder) => $queryBuilder
                        ->isContactMethodTelephony()
                        ->hasNoTelephonySession()
                );
        });

        return $queryBuilder;
    }

    public function scopeHasWorkGroups(Builder $queryBuilder, WorkGroup ...$workGroups): Builder
    {
        $workGroupIdList = [];
        foreach ($workGroups as $workGroup) {
            $workGroupIdList[] = $workGroup->getId();
        }

        $queryBuilder->whereIn(
            'work_group_id',
            $workGroupIdList
        );

        return $queryBuilder;
    }
}
