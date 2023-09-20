<?php

declare(strict_types=1);

namespace App\ServiceCenter\CaseSchedule\Service;

use App\Models\Office\Appointment;
use App\Models\Office\CallbackRequest;
use App\Models\Office\Customer;
use App\Models\Office\Lead;
use App\Models\Office\Quote;
use App\ServiceCenter\CaseSchedule\CaseScheduleEntry;
use App\ServiceCenter\ServiceCenterCase\ServiceCenterCase;
use App\Utils\Pagination\PaginatedResult;
use Assert\Assert;
use Illuminate\Database\Eloquent\Builder;

class CaseScheduleOverviewService
{
    public static $sortableFieldMap = [
        'startedAt' => 'sc_cases.started_at',
        'dueAt' => 'sc_case_schedule.due_at',
    ];

    /**
     * @return PaginatedResult<CaseScheduleEntry>
     */
    public function getOverviewItems(
        int $page,
        int $perPage,
        ?string $postalCode = null,
        ?int $caseNumber = null,
        array $sorting = []
    ): PaginatedResult {
        Assert::that($page)->greaterOrEqualThan(1);
        Assert::that($perPage)->greaterOrEqualThan(1);
        Assert::thatAll(array_keys($sorting))->inArray(
            array_keys(self::$sortableFieldMap)
        );

        if (!isset($sorting['dueAt'])) {
            $sorting['dueAt'] = 'ASC';
        }

        $query = CaseScheduleEntry::query()
            ->join('sc_cases', 'sc_cases.id', '=', 'sc_case_schedule.case_id');

        if (null !== $caseNumber) {
            $query->where('case_id', $caseNumber);
        }

        if (null !== $postalCode) {
            $query->whereHas(
                'case',
                /**
                 * @param Builder|ServiceCenterCase $builder
                 */
                function (Builder $builder) use ($postalCode): void {
                    $builder->whereHas(
                        'sourceLead',
                        /**
                         * @param Builder|Lead $builder
                         */
                        function (Builder $builder) use ($postalCode): void {
                            $builder->whereHas(
                                'callbackRequest',
                                /**
                                 * @param Builder|CallbackRequest $builder
                                 */
                                function (Builder $builder) use ($postalCode): void {
                                    $builder->whereHas(
                                        'customer',
                                        /**
                                         * @param Builder|Customer $builder
                                         */
                                        function (Builder $builder) use ($postalCode): void {
                                            $builder->wherePostalCodeLike($postalCode);
                                        }
                                    );
                                }
                            );
                            $builder->orWhereHas(
                                'quote',
                                /**
                                 * @param Builder|Quote $builder
                                 */
                                function (Builder $builder) use ($postalCode): void {
                                    $builder->whereHas(
                                        'customer',
                                        /**
                                         * @param Builder|Customer $builder
                                         */
                                        function (Builder $builder) use ($postalCode): void {
                                            $builder->wherePostalCodeLike($postalCode);
                                        }
                                    );
                                }
                            );
                            $builder->orWhere('postcode', 'LIKE', '%' . $postalCode . '%');
                        }
                    );
                    $builder->orWhereHas(
                        'sourceAppointment',
                        /**
                         * @param Builder|Appointment $builder
                         */
                        function (Builder $builder) use ($postalCode): void {
                            $builder->whereHas(
                                'customer',
                                /**
                                 * @param Builder|Customer $builder
                                 */
                                function (Builder $builder) use ($postalCode): void {
                                    $builder->wherePostalCodeLike($postalCode);
                                }
                            );
                        }
                    );
                }
            );
        }

        foreach ($sorting as $field => $direction) {
            $query->orderBy(
                self::$sortableFieldMap[$field],
                $direction === 'ASC' ? 'asc' : 'desc'
            );
        }

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        return new PaginatedResult(
            $results->items(),
            $page,
            $perPage,
            $results->total()
        );
    }
}
