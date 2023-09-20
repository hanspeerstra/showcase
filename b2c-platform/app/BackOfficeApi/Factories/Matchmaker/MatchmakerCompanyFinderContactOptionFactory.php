<?php

declare(strict_types=1);

namespace App\Clients\Factories\Matchmaker;

use App\Clients\Resources\Matchmaker\MatchmakerCompanyFinderContactOption;

class MatchmakerCompanyFinderContactOptionFactory
{
    /** @var ContactMethodFactory */
    private $contactMethodFactory;

    public function __construct(ContactMethodFactory $contactMethodFactory)
    {
        $this->contactMethodFactory = $contactMethodFactory;
    }

    public function makeFromApiResult(object $data): MatchmakerCompanyFinderContactOption
    {
        return new MatchmakerCompanyFinderContactOption(
            $this->contactMethodFactory->makeFromApiResult($data->contactMethod)
        );
    }
}
