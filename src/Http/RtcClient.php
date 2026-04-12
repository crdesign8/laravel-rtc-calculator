<?php

namespace Crdesign8\LaravelRtcCalculator\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

class RtcClient implements RtcClientContract
{
    public function __construct(
        private string $baseUrl,
        private int $timeout,
        private int $retryTimes,
        private int $retrySleepMs,
        private array $logging,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function calcularRegimeGeral(CalculoRequestDTO $dto): CalculoResult
    {
        $endpoint = '/api/calculadora/regime-geral';
        $payload  = $dto->toArray();

        $this->logRequest('POST', $endpoint, $payload);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleepMs, throw: false)
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new RtcConnectionException(
                "Não foi possível conectar à calculadora RTC em {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $this->logResponse('POST', $endpoint, $response->status(), $response->body());

        if ($response->clientError()) {
            throw new RtcValidationException(
                "Erro de validação na calculadora RTC: {$response->body()}",
                errors: (array) ($response->json() ?? []),
            );
        }

        if ($response->serverError() || ! $response->successful()) {
            throw new RtcCalculationException(
                "Erro no cálculo RTC (HTTP {$response->status()}): {$response->body()}",
            );
        }

        return CalculoResult::fromArray((array) $response->json());
    }

    /**
     * {@inheritDoc}
     */
    public function gerarXml(CalculoResult $result, TipoDocumento $tipo): string
    {
        $endpoint = '/api/calculadora/xml/generate';
        $url      = "{$endpoint}?tipo={$tipo->value}";
        $payload  = $result->toArray();

        $this->logRequest('POST', $url, $payload);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleepMs, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RtcConnectionException(
                "Não foi possível conectar à calculadora RTC em {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $this->logResponse('POST', $url, $response->status(), $response->body());

        if ($response->clientError()) {
            throw new RtcValidationException(
                "Erro de validação ao gerar XML RTC: {$response->body()}",
                errors: (array) ($response->json() ?? []),
            );
        }

        if ($response->serverError() || ! $response->successful()) {
            throw new RtcCalculationException(
                "Erro ao gerar XML RTC (HTTP {$response->status()}): {$response->body()}",
            );
        }

        return $response->body();
    }

    /**
     * {@inheritDoc}
     */
    public function validarXml(string $xml): bool
    {
        $endpoint = '/api/calculadora/xml/validate';

        $this->logRequest('POST', $endpoint, ['xml_preview' => substr($xml, 0, 200)]);

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleepMs, throw: false)
                ->withBody($xml, 'application/xml')
                ->post($endpoint);
        } catch (ConnectionException $e) {
            throw new RtcConnectionException(
                "Não foi possível conectar à calculadora RTC em {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $this->logResponse('POST', $endpoint, $response->status(), $response->body());

        if ($response->clientError()) {
            throw new RtcValidationException(
                "XML inválido segundo a calculadora RTC: {$response->body()}",
                errors: (array) ($response->json() ?? ['message' => $response->body()]),
            );
        }

        if ($response->serverError()) {
            throw new RtcConnectionException(
                "Erro no servidor ao validar XML RTC (HTTP {$response->status()}): {$response->body()}",
            );
        }

        return $response->successful();
    }

    private function logRequest(string $method, string $endpoint, array $payload): void
    {
        if (! ($this->logging['enabled'] ?? false)) {
            return;
        }

        Log::channel($this->logging['channel'] ?? 'stack')->debug(
            "RTC Request: {$method} {$this->baseUrl}{$endpoint}",
            ['payload' => $payload],
        );
    }

    private function logResponse(string $method, string $endpoint, int $status, string $body): void
    {
        if (! ($this->logging['enabled'] ?? false)) {
            return;
        }

        Log::channel($this->logging['channel'] ?? 'stack')->debug(
            "RTC Response: {$method} {$this->baseUrl}{$endpoint} [{$status}]",
            ['body' => $body],
        );
    }
}
