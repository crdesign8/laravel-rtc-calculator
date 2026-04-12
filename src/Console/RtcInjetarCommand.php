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
        $caminhoNfe = $this->argument('nfe');
        $caminhoJson = $this->argument('rtc_json');
        $caminhoSaida = $this->argument('saida');

        // Valida arquivos de entrada
        if (!file_exists($caminhoNfe)) {
            $this->error("Arquivo NFe não encontrado: {$caminhoNfe}");

            return self::FAILURE;
        }

        if (!file_exists($caminhoJson)) {
            $this->error("Arquivo JSON do cálculo RTC não encontrado: {$caminhoJson}");

            return self::FAILURE;
        }

        // Lê e valida o JSON do resultado
        $json = file_get_contents($caminhoJson);
        $data = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Arquivo JSON inválido: ' . json_last_error_msg());

            return self::FAILURE;
        }

        // Resolve o tipo de documento
        $tipoStr = strtoupper($this->option('tipo'));
        $tipo = TipoDocumento::tryFrom($tipoStr);

        if ($tipo === null) {
            $this->error("Tipo de documento inválido: '{$tipoStr}'. Use: NFe, NFCe ou CTe");

            return self::FAILURE;
        }

        $xmlNfe = file_get_contents($caminhoNfe);

        $this->info('Gerando XML RTC...');

        try {
            $result = CalculoResult::fromArray($data);
            $xmlRtc = new GerarXmlRtcAction($client)->handle($result, $tipo);
        } catch (RtcConnectionException $e) {
            $this->error('Falha de conexão: ' . $e->getMessage());

            return self::FAILURE;
        } catch (RtcValidationException $e) {
            $this->error('Erro de validação ao gerar XML: ' . $e->getMessage());

            return self::FAILURE;
        } catch (RtcCalculationException $e) {
            $this->error('Erro ao gerar XML: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Injetando grupos RTC na NFe...');

        try {
            $nfeComRtc = new InjetarXmlNfeAction()->handle($xmlRtc, $xmlNfe);
        } catch (RtcValidationException $e) {
            $this->error('Erro na injeção XML: ' . $e->getMessage());

            return self::FAILURE;
        }

        file_put_contents($caminhoSaida, $nfeComRtc);

        $this->newLine();
        $this->info("NFe com RTC injetado salva em: {$caminhoSaida}");

        return self::SUCCESS;
    }
}
