<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Console;

use Crdesign8\LaravelRtcCalculator\Actions\GerarXmlRtcAction;
use Crdesign8\LaravelRtcCalculator\Actions\InjetarXmlNfeAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\Data\CalculoResult;
use Crdesign8\LaravelRtcCalculator\Enums\TipoDocumento;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function strtoupper;

class RtcInjetarCommand extends Command
{
    protected $signature = 'rtc:injetar
                            {nfe : Caminho para o arquivo XML da NFe sem os grupos RTC}
                            {rtc_json : Caminho para o arquivo JSON com o resultado do cálculo RTC}
                            {saida : Caminho para salvar o XML da NFe com os grupos RTC injetados}
                            {--tipo=NFe : Tipo de documento para geração do XML RTC (NFe, NFCe, CTe)}';

    protected $description = 'Injeta os grupos RTC (IS, IBSCBS, ISTot, IBSCBSTot) em uma NFe existente';

    public function handle(RtcClientContract $client): int
    {
        /** @var string $caminhoNfe */
        $caminhoNfe = $this->argument('nfe');

        /** @var string $caminhoJson */
        $caminhoJson = $this->argument('rtc_json');

        /** @var string $caminhoSaida */
        $caminhoSaida = $this->argument('saida');

        try {
            $jsonRtc = (string) file_get_contents($caminhoJson);
            $xmlNfe = (string) file_get_contents($caminhoNfe);

            /** @var array<array-key, mixed>|bool|float|int|string|null $decoded */
            $decoded = json_decode($jsonRtc, associative: true, flags: JSON_THROW_ON_ERROR);

            if (! \is_array($decoded)) {
                throw new InvalidArgumentException('JSON RTC inválido: o conteúdo deve ser um objeto JSON.');
            }

            /** @var array<string, mixed> $data */
            $data = $decoded;

            $tipoOption = $this->option('tipo');
            $tipoInput = \is_string($tipoOption) && $tipoOption !== '' ? $tipoOption : 'NFe';
            $tipo = [
                'NFE' => TipoDocumento::NFe,
                'NFCE' => TipoDocumento::NFCe,
                'CTE' => TipoDocumento::CTe,
            ][strtoupper($tipoInput)] ?? throw new InvalidArgumentException(
                "Tipo de documento inválido: '{$tipoInput}'. Use: NFe, NFCe ou CTe",
            );

            $this->info('Gerando XML RTC...');
            $result = CalculoResult::fromArray($data);
            $xmlRtc = (new GerarXmlRtcAction($client))->handle($result, $tipo);

            $this->info('Injetando grupos RTC na NFe...');
            $nfeComRtc = (new InjetarXmlNfeAction)->handle($xmlRtc, $xmlNfe);
        } catch (
            JsonException|InvalidArgumentException|RtcConnectionException|RtcValidationException|RtcCalculationException $e
        ) {
            $this->error('Erro ao gerar/injetar XML RTC: '.$e->getMessage());

            return self::FAILURE;
        }

        if (file_put_contents($caminhoSaida, $nfeComRtc) === false) {
            $this->error("Falha ao salvar o XML de saída em: {$caminhoSaida}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("NFe com RTC injetado salva em: {$caminhoSaida}");

        return self::SUCCESS;
    }
}
