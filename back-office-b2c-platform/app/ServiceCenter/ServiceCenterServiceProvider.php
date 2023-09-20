<?php

declare(strict_types=1);

namespace App\ServiceCenter;

use App\ServiceCenter\AgentSession\AgentSessionLifetimeConfig;
use App\ServiceCenter\Telephony\Service\TelephonyConfig;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ServiceCenterServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(
            AgentSessionLifetimeConfig::class,
            static function (Application $application): AgentSessionLifetimeConfig {
                return new AgentSessionLifetimeConfig(
                    (int) config('session.lifetime')
                );
            }
        );

        $this->app->bind(TelephonyConfig::class, static function (Application $app) {
            return new TelephonyConfig(config('service-center.telephony.outbound_session_provider'));
        });
    }

    public function provides(): array
    {
        return [
            AgentSessionLifetimeConfig::class,
            TelephonyConfig::class,
        ];
    }
}
