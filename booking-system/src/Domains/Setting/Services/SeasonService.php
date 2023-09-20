<?php

namespace Domains\Setting\Services;

use Domains\Setting\Repositories\SettingRepository;
use Domains\Setting\Season;
use Domains\Setting\SeasonData;
use Domains\Setting\Factories\SeasonFactory;
use Domains\Setting\Setting;
use Domains\Setting\SettingType;

readonly class SeasonService
{
    public function getByYear(int $year): Season
    {
        return new Season(2023, new \DateTime(), new \DateTime());
    }
}
