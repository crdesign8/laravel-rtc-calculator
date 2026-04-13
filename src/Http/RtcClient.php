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
        $response = $this->performRequest('/api/calculadora/regime-geral', $dto->toArray());
        $response = $this->assertResponseSuccessful(
            $response,
            validationErrorMessage: 'Erro de validação na calculadora RTC',
            serverErrorMessage: 'Erro no cálculo RTC',
        );

        return CalculoResult::fromArray($this->asAssociativeArrayOrDefault($response->json()));
    }

    /**
     * {@inheritDoc}
     */
    public function gerarXml(CalculoResult $result, TipoDocumento $tipo): string
    {
        $response = $this->performRequest(
            "/api/calculadora/xml/generate?tipo={$tipo->value}",
            $result->toArray(),
        );
        $response = $this->assertResponseSuccessful(
            $response,
            validationErrorMessage: 'Erro de validação ao gerar XML RTC',
            serverErrorMessage: 'Erro ao gerar XML RTC',
        );

        return $response->body();
    }

    /**
     * {@inheritDoc}
     */
    public function validarXml(string $xml): bool
    {
        $response = $this->performRequest('/api/calculadora/xml/validate', $xml, 'application/xml');
        $response = $this->assertResponseSuccessful(
            $response,
            validationErrorMessage: 'XML inválido segundo a calculadora RTC',
            serverErrorMessage: 'Erro no servidor ao validar XML RTC',
            serverErrorType: 'connection',
        );

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

    /** @param array<string, mixed>|string $payload */
    private function performRequest(
        string $endpoint,
        array|string $payload,
        string $contentType = 'application/json',
    ): Response {
        $logPayload = is_array($payload)
            ? $payload
            : ['xml_preview' => substr($payload, offset: 0, length: 200)];

        $this->logRequest('POST', $endpoint, $logPayload);

        try {
            $request = $this->httpClient();

            $response = is_array($payload)
                ? $request->post($endpoint, $payload)
                : $request->withBody($payload, $contentType)->post($endpoint);
        } catch (ConnectionException $e) {
            throw new RtcConnectionException(
                "Não foi possível conectar à calculadora RTC em {$this->baseUrl}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $this->logResponse('POST', $endpoint, $response->status(), $response->body());

        return $response;
    }

    private function assertResponseSuccessful(
        Response $response,
        string $validationErrorMessage,
        string $serverErrorMessage,
        string $serverErrorType = 'calculation',
    ): Response {
        if ($response->clientError()) {
            throw new RtcValidationException(
                "{$validationErrorMessage}: {$response->body()}",
                errors: $this->asAssociativeArrayOrDefault($response->json(), fallback: ['message' =>
                    $response->body()]),
            );
        }

        if ($response->serverError()) {
            if ($serverErrorType === 'connection') {
                throw new RtcConnectionException(
                    "{$serverErrorMessage} (HTTP {$response->status()}): {$response->body()}",
                );
            }

            throw new RtcCalculationException(
                "{$serverErrorMessage} (HTTP {$response->status()}): {$response->body()}",
            );
        }

        if ($serverErrorType === 'calculation' && ! $response->successful()) {
            throw new RtcCalculationException(
                "{$serverErrorMessage} (HTTP {$response->status()}): {$response->body()}",
            );
        }

        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function logRequest(string $method, string $endpoint, array $payload): void
    {
        if (($this->logging['enabled'] ?? false) !== true) {
            return;
        }

        $channel = 'stack';

        if (
            array_key_exists('channel', $this->logging)
            && is_string($this->logging['channel'])
            && $this->logging['channel'] !== ''
        ) {
            $channel = $this->logging['channel'];
        }

        Log::channel($channel)->debug(
            "RTC Request: {$method} {$this->baseUrl}{$endpoint}",
            ['payload' => $payload],
        );
    }

    private function logResponse(string $method, string $endpoint, int $status, string $body): void
    {
        if (($this->logging['enabled'] ?? false) !== true) {
            return;
        }

        $channel = 'stack';

        if (
            array_key_exists('channel', $this->logging)
            && is_string($this->logging['channel'])
            && $this->logging['channel'] !== ''
        ) {
            $channel = $this->logging['channel'];
        }

        Log::channel($channel)->debug(
            "RTC Response: {$method} {$this->baseUrl}{$endpoint} [{$status}]",
            ['body' => $body],
        );
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
