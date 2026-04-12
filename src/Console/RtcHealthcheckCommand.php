<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RtcHealthcheckCommand extends Command
{
    protected $signature = 'rtc:healthcheck
                            {--url= : URL base da calculadora (sobrescreve RTC_BASE_URL)}';

    protected $description = 'Verifica se a Calculadora RTC Java está acessível';

    public function handle(): int
    {
        $baseUrl = $this->option('url') ?? config('rtc.base_url', default: 'http://localhost:8080');
        $timeout = (int) config('rtc.timeout', default: 5);

        $this->line('Verificando conexão com a calculadora RTC...');
        $this->line("URL: <fg=cyan>{$baseUrl}</>");
        $this->newLine();

        try {
            $response = Http::baseUrl($baseUrl)->timeout($timeout)->get('/actuator/health');

            if ($response->successful()) {
                $body = $response->json();
                $status = $body['status'] ?? 'UNKNOWN';

                if ($status === 'UP') {
                    $this->line('<fg=green;options=bold>✔ Calculadora RTC está operacional (status: UP)</>');
                } else {
                    $this->warn("⚠  Calculadora respondeu mas com status: {$status}");
                }

                return self::SUCCESS;
            }

            // Tenta o endpoint raiz como fallback (a API pode não ter /actuator)
            $responseRoot = Http::baseUrl($baseUrl)->timeout($timeout)->get('/');

            if ($responseRoot->status() < 500) {
                $this->line(
                    '<fg=green;options=bold>✔ Calculadora RTC está acessível (HTTP '.$responseRoot->status().')</>',
                );

                return self::SUCCESS;
            }

            $this->error('✘ Calculadora retornou erro HTTP '.$response->status());

            return self::FAILURE;
        } catch (ConnectionException $e) {
            $this->newLine();
            $this->error('✘ Não foi possível conectar à calculadora RTC');
            $this->line("  Detalhe: {$e->getMessage()}");
            $this->newLine();
            $this->line('Verifique se a calculadora está rodando:');
            $this->line('  <fg=cyan>java -jar api-regime-geral.jar --spring.profiles.active=offline</>');
            $this->line('  ou');
            $this->line('  <fg=cyan>docker run -p 8080:8080 calculadora-rtc</>');

            return self::FAILURE;
        }
    }
}
