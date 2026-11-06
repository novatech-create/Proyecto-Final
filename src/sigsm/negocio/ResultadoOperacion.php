<?php
declare(strict_types=1);

// Resultado de una operación para el adaptador HTTP.
function resultadoOperacion(array $datos, int $codigo = 200): array
{
    return [$datos, $codigo];
}
