<?php

declare(strict_types=1);

namespace App\BackOfficeApi\Endpoints;

use App\Clients\Resources\City;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Cache\Repository;

class CachingCityEndpoint implements CityEndpointInterface
{
    /** @var CityEndpointInterface */
    private $delegate;
    /** @var Repository */
    private $cache;
    /** @var CarbonInterval */
    private $ttl;

    public function __construct(CityEndpointInterface $delegate, Repository $cache, int $ttlInSeconds = 86400)
    {
        $this->delegate = $delegate;
        $this->cache = $cache;
        $this->ttl = CarbonInterval::seconds($ttlInSeconds);
    }

    public function tryGetBySlug(string $slug): ?City
    {
        $cacheKey = 'city_' . $slug;

        return $this->cache->remember(
            $cacheKey,
            $this->ttl,
            function () use ($slug) {
                return $this->delegate->tryGetBySlug($slug);
            }
        );
    }
}
