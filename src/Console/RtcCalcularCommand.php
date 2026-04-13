<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Console;

use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;
use Illuminate\Console\Command;
use JsonException;

use function count;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_string;
use function json_decode;
use function str_repeat;

class RtcCalcularCommand extends Command
{
    protected $signature = 'rtc:calcular
                            {arquivo : Caminho para o arquivo JSON com os dados de entrada}
                            {--saida= : Salva o resultado em um arquivo JSON (opcional)}';

    protected $description = 'Calcula os tributos RTC (IS, IBS, CBS) a partir de um arquivo JSON de entrada';

    public function handle(RtcClientContract $client): int
    {
        /** @var string $caminho */
        $caminho = $this->argument('arquivo');

        if (! file_exists($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $json = file_get_contents($caminho);

        if (! is_string($json)) {
            $this->error("Não foi possível ler o arquivo: {$caminho}");

            return self::FAILURE;
        }

        try {
            /** @var array<array-key, mixed>|bool|float|int|string|null $decoded */
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Arquivo JSON inválido: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_array($decoded)) {
            $this->error('Estrutura JSON inválida para cálculo RTC. Verifique os campos obrigatórios.');

            return self::FAILURE;
        }

        /** @var array{id: string, versao: string, dataHoraEmissao: string, municipio: int|string, uf: string, itens?: list<array{numero: int|string, ncm: string, quantidade: float|int|string, unidade: string, cst: string, baseCalculo: float|int|string, cClassTrib: string, tributacaoRegular?: array{cst: string, cClassTrib: string}, impostoSeletivo?: array{cst: string, baseCalculo: float|int|string, cClassTrib: string, unidade: string, quantidade: float|int|string, impostoInformado?: float|int|string}}>} $data */
        $data = $decoded;

        $this->info('Calculando tributos RTC...');

        try {
            $dto = CalculoRequestDTO::fromArray($data);
            $result = (new CalcularTributosAction($client))->handle($dto);
        } catch (RtcConnectionException|RtcValidationException|RtcCalculationException $e) {
            $this->error('Erro no cálculo RTC: '.$e->getMessage());

            return self::FAILURE;
        }

        // Exibe resumo no terminal
        $this->newLine();
        $this->line('<fg=green;options=bold>Resultado do Cálculo RTC</>');
        $this->line(str_repeat('─', times: 50));

        $total = $result->getTotal();
        $this->table(['Tributo', 'Valor'], [
            ['ISTot (Imposto Seletivo)', $total->getVIsTot()],
            ['IBSCBSTot — Base de cálculo', $total->getVBcIbsCbs()],
            ['IBSCBSTot — IBS Total', $total->getVIbsTot()],
            ['IBSCBSTot — IBS UF', $total->getVIbsUfTot()],
            ['IBSCBSTot — IBS Mun', $total->getVIbsMunTot()],
            ['IBSCBSTot — CBS Total', $total->getVCbsTot()],
        ]);

        $this->line('Itens calculados: '.count($result->getObjetos()));

        // Salva em arquivo se solicitado
        $arquivoSaida = $this->option('saida');

        if (is_string($arquivoSaida) && $arquivoSaida !== '') {
            $bytes = file_put_contents($arquivoSaida, $result->toJson());

            if ($bytes === false) {
                $this->error("Falha ao salvar resultado em: {$arquivoSaida}");

                return self::FAILURE;
            }

            $this->info("Resultado salvo em: {$arquivoSaida}");
        }

        return self::SUCCESS;
    }
}
