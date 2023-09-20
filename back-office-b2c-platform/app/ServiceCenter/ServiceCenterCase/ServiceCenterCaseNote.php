<?php

declare(strict_types=1);

namespace App\ServiceCenter\ServiceCenterCase;

use App\Auth\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property ServiceCenterCase $case
 * @property User $agent
 * @property string $note
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class ServiceCenterCaseNote extends Model
{
    use SoftDeletes;

    protected $table = 'sc_case_notes';

    public static function makeInstance(
        ServiceCenterCase $case,
        User $agent,
        string $note
    ): self {
        return (new self())
            ->setCase($case)
            ->setAgent($agent)
            ->setNote($note);
    }

    /**
     * @internal
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ServiceCenterCase::class, 'case_id');
    }

    public function getId(): int
    {
        return $this->id;
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

    /**
     * @internal
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function getAgent(): User
    {
        return $this->agent;
    }

    private function setAgent(User $agent): self
    {
        $this->agent()->associate($agent);

        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): CarbonInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): CarbonInterface
    {
        return $this->updated_at;
    }
}
