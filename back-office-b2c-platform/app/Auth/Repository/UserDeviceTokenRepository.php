<?php

declare(strict_types=1);

namespace App\Auth\Repository;

use App\Auth\UserDeviceToken;
use App\Utils\Database\Contract\TransactionHandler;
use App\Utils\Storage\EloquentRepositoryHelperTrait;

class UserDeviceTokenRepository
{
    use EloquentRepositoryHelperTrait;

    /** @var TransactionHandler */
    private $transactionHandler;

    public function __construct(TransactionHandler $transactionHandler)
    {
        $this->transactionHandler = $transactionHandler;
    }

    public function store(UserDeviceToken $userDeviceToken): UserDeviceToken
    {
        $currentUserDeviceToken = UserDeviceToken::query()
            ->belongsToDeviceToken($userDeviceToken->getDeviceToken())
            ->first();

        if ($currentUserDeviceToken !== null && $currentUserDeviceToken->getUser()->is($userDeviceToken->getUser())) {
            return $currentUserDeviceToken;
        }

        if ($currentUserDeviceToken !== null) {
            $this->delete($currentUserDeviceToken);
        }

        self::doInsert($userDeviceToken);

        return $userDeviceToken;
    }

    private function delete(UserDeviceToken $userDeviceToken): void
    {
        self::doDelete($userDeviceToken);
    }
}
