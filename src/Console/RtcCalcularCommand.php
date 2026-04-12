<?php

namespace Crdesign8\LaravelRtcCalculator\Console;

use Illuminate\Console\Command;
use Crdesign8\LaravelRtcCalculator\Actions\CalcularTributosAction;
use Crdesign8\LaravelRtcCalculator\Contracts\RtcClientContract;
use Crdesign8\LaravelRtcCalculator\DTOs\CalculoRequestDTO;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcCalculationException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcConnectionException;
use Crdesign8\LaravelRtcCalculator\Exceptions\RtcValidationException;

class RtcCalcularCommand extends Command
{
    protected $signature = 'rtc:calcular
                            {arquivo : Caminho para o arquivo JSON com os dados de entrada}
                            {--saida= : Salva o resultado em um arquivo JSON (opcional)}';

    protected $description = 'Calcula os tributos RTC (IS, IBS, CBS) a partir de um arquivo JSON de entrada';

    public function handle(RtcClientContract $client): int
    {
        $caminho = $this->argument('arquivo');

        if (! file_exists($caminho)) {
            $this->error("Arquivo não encontrado: {$caminho}");

            return self::FAILURE;
        }

        $json = file_get_contents($caminho);
        $data = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Arquivo JSON inválido: ' . json_last_error_msg());

            return self::FAILURE;
        }

        $this->info('Calculando tributos RTC...');

        try {
            $dto    = CalculoRequestDTO::fromArray($data);
            $result = (new CalcularTributosAction($client))->handle($dto);
        } catch (RtcConnectionException $e) {
            $this->error('Falha de conexão: ' . $e->getMessage());

            return self::FAILURE;
        } catch (RtcValidationException $e) {
            $this->error('Erro de validação: ' . $e->getMessage());
            foreach ($e->getErrors() as $err) {
                $this->line("  • {$err}");
            }

            return self::FAILURE;
        } catch (RtcCalculationException $e) {
            $this->error('Erro no cálculo: ' . $e->getMessage());

            return self::FAILURE;
        }

        // Exibe resumo no terminal
        $this->newLine();
        $this->line('<fg=green;options=bold>Resultado do Cálculo RTC</>');
        $this->line(str_repeat('─', 50));

        $total = $result->getTotal();
        $this->table(
            ['Tributo', 'Valor'],
            [
                ['ISTot (Imposto Seletivo)', $total->getVIsTot()],
                ['IBSCBSTot — Base de cálculo', $total->getVBcIbsCbs()],
                ['IBSCBSTot — IBS Total', $total->getVIbsTot()],
                ['IBSCBSTot — IBS UF', $total->getVIbsUfTot()],
                ['IBSCBSTot — IBS Mun', $total->getVIbsMunTot()],
                ['IBSCBSTot — CBS Total', $total->getVCbsTot()],
            ]
        );

        $this->line('Itens calculados: ' . count($result->getObjetos()));

        // Salva em arquivo se solicitado
        $arquivoSaida = $this->option('saida');
        if ($arquivoSaida !== null) {
            file_put_contents($arquivoSaida, $result->toJson());
            $this->info("Resultado salvo em: {$arquivoSaida}");
        }

        return self::SUCCESS;
    }
}
