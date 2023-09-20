<?php

declare(strict_types=1);

namespace App\Auth;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property User $user
 * @property string $device_token
 *
 * @method UserDeviceToken|Builder|\Illuminate\Database\Query\Builder belongsToDeviceToken(string $deviceToken)
 * @see UserDeviceToken::scopeBelongsToDeviceToken()
 *
 * @method static UserDeviceToken|Builder|\Illuminate\Database\Query\Builder query()
 *
 * @mixin Eloquent
 */
class UserDeviceToken extends Model
{
    use SoftDeletes;

    protected $table = 'user_device_tokens';

    public static function makeInstance(User $user, string $deviceToken): self
    {
        return (new static())
            ->setUser($user)
            ->setDeviceToken($deviceToken);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private function setUser(User $user): self
    {
        $this->user()->associate($user);

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDeviceToken(): string
    {
        return $this->device_token;
    }

    private function setDeviceToken(string $deviceToken): self
    {
        $this->device_token = $deviceToken;

        return $this;
    }

    public function scopeBelongsToDeviceToken(Builder $queryBuilder, string $deviceToken): Builder
    {
        $queryBuilder->where('device_token', '=', $deviceToken);

        return $queryBuilder;
    }
}
