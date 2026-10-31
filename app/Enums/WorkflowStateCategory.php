<?php

namespace App\Enums;

enum WorkflowStateCategory: string
{
    case Initial = 'initial';
    case Intermediate = 'intermediate';
    case Terminal = 'terminal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
