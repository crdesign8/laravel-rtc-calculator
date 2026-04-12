<?php

declare(strict_types=1);

/**
 * Bootstrap mínimo para rodar os exemplos standalone.
 * Não requer uma aplicação Laravel completa instalada.
 *
 * Uso em cada exemplo:
 *   $client = require __DIR__ . '/bootstrap.php';
 *
 * Variáveis de ambiente aceitas:
 *   RTC_BASE_URL  — URL da calculadora (padrão: http://localhost:8080)
 */

require __DIR__ . '/../vendor/autoload.php';

use Crdesign8\LaravelRtcCalculator\Http\RtcClient;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;

$baseUrl = (string) (getenv('RTC_BASE_URL') ?: 'http://localhost:8080');

// Verifica conectividade antes de qualquer chamada
$sock = @stream_socket_client(str_replace(['http://', 'https://'], 'tcp://', $baseUrl), $errno, $errstr, timeout: 3);

if ($sock === false) {
    fwrite(STDERR, "\n❌  Calculadora RTC não está acessível em {$baseUrl}\n\n");
    fwrite(STDERR, "    Inicie o container Docker:\n\n");
    fwrite(STDERR, "    docker run -d --name calculadora-api -p 8080:8080 -w /calculadora \\\n");
    fwrite(STDERR, "      calculadora-rtc /bin/sh -c \"\\\n");
    fwrite(STDERR, "      JAVA_HOME=/opt/java/openjdk; export PATH=\$JAVA_HOME/bin:\$PATH; \\\n");
    fwrite(STDERR, "      java -jar /calculadora/api-regime-geral.jar --spring.profiles.active=offline\"\n\n");
    exit(1);
}

fclose($sock);

// Bootstrap mínimo do container Laravel (apenas o necessário para a facade Http::)
$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);
$app->instance(HttpFactory::class, new HttpFactory());

return new RtcClient(baseUrl: $baseUrl, timeout: 10, retryTimes: 1, retrySleepMs: 0, logging: [
    'enabled' => false,
    'channel' => 'stack',
]);
