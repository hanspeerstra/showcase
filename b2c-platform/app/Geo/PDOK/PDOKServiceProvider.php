<?php

namespace App\Geo\PDOK;

use GuzzleHttp\Client;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class PDOKServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PDOKLocatieServerClientInterface::class,
            static function () {
                return new PDOKLocatieServerClient(
                    new Client([
                        'base_uri' => config('geo.pdok_location_server.api_base_uri'),
                    ])
                );
            }
        );
    }

    public function provides(): array
    {
        return [
            PDOKLocatieServerClientInterface::class,
        ];
    }
}
