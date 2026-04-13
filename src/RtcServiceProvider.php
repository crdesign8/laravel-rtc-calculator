<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator;

use Crdesign8\LaravelRtcCalculator\Console\RtcCalcularCommand;
use Crdesign8\LaravelRtcCalculator\Console\RtcHealthcheckCommand;
use Crdesign8\LaravelRtcCalculator\Console\RtcInjetarCommand;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Http\RtcClient;
use Illuminate\Support\ServiceProvider;

use function app;
use function config;
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
        $this->app->singleton(RtcClientContract::class, static function (): RtcClient {
            /** @var array<string, mixed> $logging */
            $logging = config('rtc.logging', []);

            return new RtcClient(
                baseUrl: (string) config('rtc.base_url', default: ''),
                timeout: (int) config('rtc.timeout', default: 30),
                retryTimes: (int) config('rtc.retry_times', default: 0),
                retrySleepMs: (int) config('rtc.retry_sleep_ms', default: 0),
                logging: $logging,
            );
        });

        // Registra a classe principal vinculada ao contrato
        $this->app->singleton(Rtc::class, static fn (): Rtc => new Rtc(app(RtcClientContract::class)));

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
     *
     * @return array<int, string>
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
