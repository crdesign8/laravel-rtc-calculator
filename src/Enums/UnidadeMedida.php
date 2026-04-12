<?php

declare(strict_types=1);

namespace Crdesign8\LaravelRtcCalculator\Enums;

enum UnidadeMedida: string
{
    case VN  = 'VN';   // Unidade
    case KG  = 'KG';   // Quilograma
    case G   = 'G';    // Grama
    case L   = 'L';    // Litro
    case ML  = 'ML';   // Mililitro
    case M   = 'M';    // Metro
    case M2  = 'M2';   // Metro Quadrado
    case M3  = 'M3';   // Metro Cúbico
    case T   = 'T';    // Tonelada
    case MWH = 'MWH';  // MegaWatt-hora
    case GJ  = 'GJ';   // GigaJoule
}
