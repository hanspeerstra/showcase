<?php

declare(strict_types=1);

namespace App\Synonyms\Overview\Service;

use App\Models\Office\Profession;
use App\Models\Office\ProfessionSynonym;
use App\Models\Office\Servicetype;
use App\Models\Office\ServicetypeSynonym;
use App\Synonyms\Overview\Model\SynonymsOverviewItem;
use App\Utils\Pagination\PaginatedResult;
use Assert\Assert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SynonymsOverviewService
{
    public static $sortableFieldMap = [
        'updatedAt' => SynonymsOverviewItem::COL_UPDATED_AT,
        'id' => 'id',
        'name' => 'name',
    ];

    /**
     * @return PaginatedResult<SynonymsOverviewItem>
     */
    public function getSynonymsOverview(
        int $page,
        int $perPage,
        array $types,
        ?string $search = null,
        ?bool $hasAnySynonymFilter = null,
        bool $matchmakerFilterEnabled = false,
        array $sorting = []
    ): PaginatedResult {
        Assert::that($page)->greaterOrEqualThan(1);
        Assert::that($perPage)->greaterOrEqualThan(1);
        Assert::thatAll(array_keys($sorting))->inArray(
            array_keys(self::$sortableFieldMap)
        );

        $serviceTypeQuery = Servicetype::query();

        $professionQuery = Profession::query();

        if ($matchmakerFilterEnabled) {
            $serviceTypeQuery->allowedOnMatchmaker();
            $professionQuery->matchmaker();
        }

        if ($hasAnySynonymFilter === true) {
            $serviceTypeQuery->hasSynonyms();
            $professionQuery->hasSynonyms();
        } elseif ($hasAnySynonymFilter === false) {
            $serviceTypeQuery->doesntHaveSynonyms();
            $professionQuery->doesntHaveSynonyms();
        }

        if (null !== $search) {
            $keywords = [];
            $ids = [];
            foreach (explode(' ', $search) as $value) {
                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                if (is_numeric($value)) {
                    $ids[] = (int) $value;
                } else {
                    $keywords[] = $value;
                }
            }

            if (count($keywords) > 0) {
                $serviceTypeQuery->where(function (Builder $queryBuilder) use ($keywords) {
                    $queryBuilder->where(
                    /**
                     * @param Servicetype|Builder $queryBuilder
                     */
                        function (Builder $queryBuilder) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $queryBuilder->whereNameLike($keyword);
                            }
                        }
                    );
                    $queryBuilder->orWhereHas(
                        'servicetypeSynonyms',
                        /**
                         * @param ServicetypeSynonym|Builder $queryBuilder
                         */
                        function (Builder $queryBuilder) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $queryBuilder->whereSynonymLike($keyword);
                            }
                        }
                    );
                });
            }

            if (count($ids) > 0) {
                $serviceTypeQuery->whereIn('id', $ids);
            }

            if (count($keywords) > 0) {
                $professionQuery->where(function (Builder $queryBuilder) use ($keywords) {
                    $queryBuilder->where(
                    /**
                     * @param Profession|Builder $queryBuilder
                     */
                        function (Builder $queryBuilder) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $queryBuilder->whereNameLike($keyword);
                            }
                        }
                    );
                    $queryBuilder->orWhereHas(
                        'professionSynonyms',
                        /**
                         * @param ProfessionSynonym|Builder $queryBuilder
                         */
                        function (Builder $queryBuilder) use ($keywords) {
                            foreach ($keywords as $keyword) {
                                $queryBuilder->whereSynonymLike($keyword);
                            }
                        }
                    );
                });
            }

            if (count($ids) > 0) {
                $professionQuery->whereIn('id', $ids);
            }
        }

        $joinProfessionSynonymsAggregateQuery = ProfessionSynonym::query()
            ->withTrashed()
            ->selectRaw(
                sprintf(
                    '%s, GREATEST(MAX(%s), MAX(%s), MAX(%s)) as last_updated_at',
                    ProfessionSynonym::COL_PROFESSION_ID,
                    ProfessionSynonym::COL_CREATED_AT,
                    ProfessionSynonym::COL_UPDATED_AT,
                    ProfessionSynonym::COL_DELETED_AT
                )
            )
            ->groupBy(ProfessionSynonym::COL_PROFESSION_ID);

        $joinServiceTypeSynonymsAggregateQuery = ServicetypeSynonym::query()
            ->withTrashed()
            ->selectRaw(
                sprintf(
                    '%s, GREATEST(MAX(%s), MAX(%s), MAX(%s)) as last_updated_at',
                    ServicetypeSynonym::COL_SERVICE_TYPE_ID,
                    ServicetypeSynonym::COL_CREATED_AT,
                    ServicetypeSynonym::COL_UPDATED_AT,
                    ServicetypeSynonym::COL_DELETED_AT
                )
            )
            ->groupBy(ServicetypeSynonym::COL_SERVICE_TYPE_ID);

        $query = SynonymsOverviewItem::query()
            ->fromSub(
                $serviceTypeQuery
                    ->leftJoinSub(
                        $joinServiceTypeSynonymsAggregateQuery,
                        'service_type_synonyms_aggregate',
                        'service_type_synonyms_aggregate.' . ServicetypeSynonym::COL_SERVICE_TYPE_ID,
                        '=',
                        'servicetypes.' . Servicetype::COL_ID
                    )
                    ->select(
                        [
                            Servicetype::COL_ID . ' AS id',
                            Servicetype::COL_ID . ' AS ' . SynonymsOverviewItem::COL_SERVICE_TYPE_ID,
                            DB::raw('NULL AS ' . SynonymsOverviewItem::COL_PROFESSION_ID),
                            Servicetype::COL_NAME . ' AS name',
                            'service_type_synonyms_aggregate.last_updated_at AS ' . SynonymsOverviewItem::COL_UPDATED_AT,
                        ]
                    )
                    ->union(
                        $professionQuery
                            ->leftJoinSub(
                                $joinProfessionSynonymsAggregateQuery,
                                'profession_synonyms_aggregate',
                                'profession_synonyms_aggregate.' . ProfessionSynonym::COL_PROFESSION_ID,
                                '=',
                                'professions.' . Profession::COL_ID
                            )
                            ->select(
                                [
                                    Profession::COL_ID . ' AS id',
                                    DB::raw('NULL AS ' . SynonymsOverviewItem::COL_SERVICE_TYPE_ID),
                                    Profession::COL_ID . ' AS ' . SynonymsOverviewItem::COL_PROFESSION_ID,
                                    Profession::COL_NAME . ' AS name',
                                    'profession_synonyms_aggregate.last_updated_at AS ' . SynonymsOverviewItem::COL_UPDATED_AT,
                                ]
                            )
                    ),
                'synonyms_overview_items'
            );

        if (count($types) > 0) {
            $query->whereInType($types);
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
