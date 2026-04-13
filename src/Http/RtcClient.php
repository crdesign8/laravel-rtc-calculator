<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Http;

use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function array_key_exists;
use function array_keys;
use function is_array;
use function is_string;
use function substr;

class RtcClient implements RtcClientContract
{
    /**
     * @param  array{enabled?: bool, channel?: string|null}|array<string, mixed>  $logging
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $retryTimes,
        private readonly int $retrySleepMs,
        private readonly array $logging,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function calcularRegimeGeral(CalculoRequestDTO $dto): CalculoResult
    {
        $endpoint = '/api/calculadora/regime-geral';
        $payload = $dto->toArray();

        $this->logRequest('POST', $endpoint, $payload);

        try {
            $response = $this->httpClient()->post($endpoint, $payload);
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
                errors: $this->responseJsonObject($response, fallback: ['message' => $response->body()]),
            );
        }

        if ($response->serverError() || ! $response->successful()) {
            throw new RtcCalculationException("Erro no cálculo RTC (HTTP {$response->status()}): {$response->body()}");
        }

        return CalculoResult::fromArray($this->responseJsonObject($response));
    }

    /**
     * {@inheritDoc}
     */
    public function gerarXml(CalculoResult $result, TipoDocumento $tipo): string
    {
        $endpoint = '/api/calculadora/xml/generate';
        $url = "{$endpoint}?tipo={$tipo->value}";
        $payload = $result->toArray();

        $this->logRequest('POST', $url, $payload);

        try {
            $response = $this->httpClient()->post($url, $payload);
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
                errors: $this->responseJsonObject($response, fallback: ['message' => $response->body()]),
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

        $this->logRequest('POST', $endpoint, ['xml_preview' => substr($xml, offset: 0, length: 200)]);

        try {
            $response = $this->httpClient()->withBody($xml, 'application/xml')->post($endpoint);
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
                errors: $this->responseJsonObject($response, fallback: ['message' => $response->body()]),
            );
        }

        if ($response->serverError()) {
            throw new RtcConnectionException(
                "Erro no servidor ao validar XML RTC (HTTP {$response->status()}): {$response->body()}",
            );
        }

        return $response->successful();
    }

    // -----------------------------------------------------------------------
    // Helpers internos
    // -----------------------------------------------------------------------

    /**
     * Retorna um PendingRequest pré-configurado com baseUrl, timeout e retry.
     * Centraliza a configuração HTTP evitando repetição nos métodos públicos (DRY).
     */
    private function httpClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleepMs, throw: false);
    }

    /** @param array<string, mixed> $payload */
    private function logRequest(string $method, string $endpoint, array $payload): void
    {
        if (($this->logging['enabled'] ?? false) !== true) {
            return;
        }

        Log::channel($this->resolveLogChannel())->debug(
            "RTC Request: {$method} {$this->baseUrl}{$endpoint}",
            ['payload' => $payload],
        );
    }

    private function logResponse(string $method, string $endpoint, int $status, string $body): void
    {
        if (($this->logging['enabled'] ?? false) !== true) {
            return;
        }

        Log::channel($this->resolveLogChannel())->debug(
            "RTC Response: {$method} {$this->baseUrl}{$endpoint} [{$status}]",
            ['body' => $body],
        );
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function responseJsonObject(Response $response, array $fallback = []): array
    {
        return $this->asAssociativeArrayOrDefault($response->json(), $fallback);
    }

    private function resolveLogChannel(): ?string
    {
        if (
            ! array_key_exists('channel', $this->logging)
            || ! is_string($this->logging['channel'])
            || $this->logging['channel'] === ''
        ) {
            return 'stack';
        }

        return $this->logging['channel'];
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function asAssociativeArrayOrDefault(mixed $value, array $fallback = []): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        $normalized = [];

        foreach (array_keys($value) as $key) {
            $normalized[(string) $key] = $value[$key];
        }

        return $normalized;
    }
}
