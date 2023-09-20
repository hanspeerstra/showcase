<?php

namespace Domains\Booking\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use Domains\Booking\ChangeoverDay;
use Domains\Planning\Repositories\CheckInDayExceptionRepository;
use Domains\Setting\Services\SeasonService;

readonly class ChangeoverDayService
{
    public function __construct(
        private SeasonService $seasonService
    ) {}

    public function getCheckInDaysWithinInterval(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): array {
        $year = (int) $startDate->format('Y');

        $season = $this->seasonService->getByYear($year);

        $period = new DatePeriod(
            max($startDate, $season->startDate),
            DateInterval::createFromDateString('1 day'),
            min($endDate, $season->endDate)
        );

        return $this->getChangeoverDays($period);
    }

    public function getCheckOutDaysWithinInterval(
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): array {
        $year = (int) $startDate->format('Y');

        $season = $this->seasonService->getByYear($year);

        $period = new DatePeriod(
            max($startDate, $season->startDate),
            DateInterval::createFromDateString('1 day'),
            min($endDate, $season->endDate),
            DatePeriod::EXCLUDE_START_DATE | DatePeriod::INCLUDE_END_DATE
        );

        return $this->getChangeoverDays($period);
    }

    /**
     * @return ChangeoverDay[]
     */
    private function getChangeoverDays(DatePeriod $period): array
    {
        $checkInDayExceptions = [];

        $changeoverDays = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('w');

            if ($dayOfWeek === 1 || $dayOfWeek === 5) {
                if (array_key_exists($key, $checkInDayExceptions)) {
                    $changeoverDays[$key] = new ChangeoverDay($checkInDayExceptions[$key]);
                } else {
                    $changeoverDays[$key] = new ChangeoverDay($date);
                }
            }
        }

        return array_values($changeoverDays);
    }
}
