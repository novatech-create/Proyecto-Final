<?php
declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

abstract class ConexionDAO
{
    private ?PDO $pdo = null;

    protected function conexion(): PDO
    {
        return $this->pdo ??= obtenerConexion();
    }
}
