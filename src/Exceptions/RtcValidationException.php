<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Exceptions;

use RuntimeException;

class RtcValidationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors  Erros retornados pela API da calculadora
     */
    public function __construct(
        string $message = '',
        private array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
