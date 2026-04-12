<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator;

use Crdesign8\LaravelRtcCalculator\Console\RtcCalcularCommand;
use Crdesign8\LaravelRtcCalculator\Console\RtcHealthcheckCommand;
use Crdesign8\LaravelRtcCalculator\Console\RtcInjetarCommand;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Http\RtcClient;
use Illuminate\Support\ServiceProvider;
use function config_path;

class RtcServiceProvider extends ServiceProvider
{
    /**
     * Registra os bindings do pacote no container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rtc.php', 'rtc');

        // Registra o cliente HTTP como singleton para reaproveitamento de conexão
        $this->app->singleton(RtcClientContract::class, static fn ($app) => new RtcClient(
            baseUrl: $app['config']->get('rtc.base_url'),
            timeout: $app['config']->get('rtc.timeout'),
            retryTimes: $app['config']->get('rtc.retry_times'),
            retrySleepMs: $app['config']->get('rtc.retry_sleep_ms'),
            logging: $app['config']->get('rtc.logging'),
        ));

        // Registra a classe principal vinculada ao contrato
        $this->app->singleton(Rtc::class, static fn ($app) => new Rtc($app->make(RtcClientContract::class)));

        // Alias para resolução via facade
        $this->app->alias(Rtc::class, 'rtc');
    }

    /**
     * Bootstrap do pacote: publica assets e registra comandos.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publica o arquivo de configuração
            $this->publishes([
                __DIR__.'/../config/rtc.php' => config_path('rtc.php'),
            ], 'rtc-config');

            // Registra comandos Artisan
            $this->commands([
                RtcCalcularCommand::class,
                RtcInjetarCommand::class,
                RtcHealthcheckCommand::class,
            ]);
        }
    }

    /**
     * Retorna os serviços providos por este provider.
     */
    public function provides(): array
    {
        return [
            RtcClientContract::class,
            Rtc::class,
            'rtc',
        ];
    }
}
