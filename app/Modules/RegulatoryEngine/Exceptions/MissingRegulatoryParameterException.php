<?php

namespace App\Modules\RegulatoryEngine\Exceptions;

use RuntimeException;

final class MissingRegulatoryParameterException extends RuntimeException
{
    public static function for(string $category, string $key, string $date): self
    {
        return new self("Falta parámetro regulatorio vigente: {$category}.{$key} para fecha {$date}.");
    }
}
