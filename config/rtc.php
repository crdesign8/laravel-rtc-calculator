<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL Base da Calculadora RTC
    |--------------------------------------------------------------------------
    |
    | Endereço onde a calculadora Java está rodando. Por padrão roda em
    | localhost:8080, mas pode ser alterado para ambientes de homologação
    | ou configurações de container Docker.
    |
    */
    'base_url' => env('RTC_BASE_URL', 'http://localhost:8080'),

    /*
    |--------------------------------------------------------------------------
    | Timeout (segundos)
    |--------------------------------------------------------------------------
    |
    | Tempo máximo de espera por resposta da API da calculadora.
    |
    */
    'timeout' => env('RTC_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    |
    | Número de tentativas em caso de falha de conexão e o intervalo
    | em milissegundos entre as tentativas.
    |
    */
    'retry_times' => env('RTC_RETRY_TIMES', 2),
    'retry_sleep_ms' => env('RTC_RETRY_SLEEP_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | Tipo de Documento Padrão
    |--------------------------------------------------------------------------
    |
    | Tipo de documento usado por padrão ao gerar XML.
    | Valores aceitos: 'NFe', 'NFCe', 'CTe'
    |
    */
    'default_tipo_documento' => env('RTC_DEFAULT_TIPO_DOCUMENTO', 'NFe'),

    /*
    |--------------------------------------------------------------------------
    | Versão do Schema
    |--------------------------------------------------------------------------
    |
    | Versão da API da calculadora. Verificar na documentação oficial.
    |
    */
    'versao' => env('RTC_VERSAO', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Quando habilitado, as requisições e respostas da calculadora serão
    | registradas no canal de log configurado.
    |
    */
    'logging' => [
        'enabled' => env('RTC_LOGGING_ENABLED', false),
        'channel' => env('RTC_LOGGING_CHANNEL', env('LOG_CHANNEL', 'stack')),
    ],

];
