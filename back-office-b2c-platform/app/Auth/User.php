<?php

namespace App\Auth;

use DateTimeInterface;
use Eloquent;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use redsd\AESEncrypt\Database\Eloquent\ModelEncrypt;

/**
 * @property int $id
 *
 * @method User|Builder|\Illuminate\Database\Query\Builder whereReferralToken(string $referralToken)
 * @see User::scopeWhereReferralToken()
 *
 * @method User|Builder|\Illuminate\Database\Query\Builder whereNoActivitySince(DateTimeInterface $since)
 * @see User::scopeWhereNoActivitySince()
 *
 * @method User|Builder|\Illuminate\Database\Query\Builder whereEmail(string $email)
 * @see User::scopeWhereEmail()
 *
 * @method static static|Builder|\Illuminate\Database\Query\Builder query()
 *
 * @mixin Eloquent
 */
class User extends ModelEncrypt implements AuthenticatableContract
{
    use Authenticatable;

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeWhereReferralToken(Builder $queryBuilder, string $referralToken): Builder
    {
        $queryBuilder->where('referral_token', $referralToken);

        return $queryBuilder;
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeWhereNoActivitySince(Builder $queryBuilder, DateTimeInterface $since): Builder
    {
        $queryBuilder->where('last_activity_at', '<=', $since);

        return $queryBuilder;
    }

    /**
     * @param static|Builder $queryBuilder
     *
     * @return static|Builder
     */
    public function scopeWhereEmail(Builder $queryBuilder, string $email): Builder
    {
        $queryBuilder->where('email', $email);

        return $queryBuilder;
    }
}
