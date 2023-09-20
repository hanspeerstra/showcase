<?php

namespace App\Providers;

use App\BackOfficeApi\Endpoints\CachingCityContractEndpoint;
use App\BackOfficeApi\Endpoints\CachingCityEndpoint;
use App\BackOfficeApi\Endpoints\CachingCityInfoEndpoint;
use App\BackOfficeApi\Endpoints\CachingLogoEndpoint;
use App\BackOfficeApi\Endpoints\CachingMetricsEndpoint;
use App\BackOfficeApi\Endpoints\CachingProfessionEndpoint;
use App\BackOfficeApi\Endpoints\CachingServiceNumberEndpoint;
use App\BackOfficeApi\Endpoints\CachingServiceTypeEndpoint;
use App\BackOfficeApi\Endpoints\CityContractEndpoint;
use App\BackOfficeApi\Endpoints\CityContractEndpointInterface;
use App\BackOfficeApi\Endpoints\CityEndpoint;
use App\BackOfficeApi\Endpoints\CityEndpointInterface;
use App\BackOfficeApi\Endpoints\CityInfoEndpoint;
use App\BackOfficeApi\Endpoints\CityInfoEndpointInterface;
use App\BackOfficeApi\Endpoints\CompanyEndpoint;
use App\BackOfficeApi\Endpoints\CompanyEndpointInterface;
use App\BackOfficeApi\Endpoints\LogoEndpoint;
use App\BackOfficeApi\Endpoints\LogoEndpointInterface;
use App\BackOfficeApi\Endpoints\MetricsEndpoint;
use App\BackOfficeApi\Endpoints\MetricsEndpointInterface;
use App\BackOfficeApi\Endpoints\ProfessionEndpoint;
use App\BackOfficeApi\Endpoints\ProfessionEndpointInterface;
use App\BackOfficeApi\Endpoints\QuestionnaireFormEndpoint;
use App\BackOfficeApi\Endpoints\QuestionnaireFormEndpointInterface;
use App\BackOfficeApi\Endpoints\RegionEndpoint;
use App\BackOfficeApi\Endpoints\RegionEndpointInterface;
use App\BackOfficeApi\Endpoints\SearchAssistantEndpoint;
use App\BackOfficeApi\Endpoints\SearchAssistantEndpointInterface;
use App\BackOfficeApi\Endpoints\ServiceNumberEndpoint;
use App\BackOfficeApi\Endpoints\ServiceNumberEndpointInterface;
use App\BackOfficeApi\Endpoints\ServiceTypeEndpoint;
use App\BackOfficeApi\Endpoints\ServiceTypeEndpointInterface;
use App\Clients\Factories\QuestionnaireFormEndpointRequestFactory;
use App\Clients\OfficeApiClient;
use GuzzleHttp\Client;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class OfficeApiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            OfficeApiClient::class,
            function () {
                return new OfficeApiClient(
                    new Client([
                        'base_uri' => config('office.api_host'),
                    ])
                );
            }
        );

        $this->app->singleton(
            CityEndpointInterface::class,
            CityEndpoint::class
        );

        $this->app->extend(
            CityEndpointInterface::class,
            function (CityEndpointInterface $delegate, Application $application): CityEndpointInterface {
                return $application->make(
                    CachingCityEndpoint::class,
                    [
                        'delegate' => $delegate,
                    ]
                );
            }
        );
    }

    public function provides(): array
    {
        return [
            CityEndpointInterface::class,
        ];
    }
}
