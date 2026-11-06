<?php
declare(strict_types=1);

require_once __DIR__ . '/ConexionDAO.php';

final class EncuestaDAO extends ConexionDAO
{
    public function guardar(array $parametros): void
    {
        $consulta = $this->conexion()->prepare(
        'INSERT INTO encuestas (documento_id, servicio, calificacion_info, datosSatisfaccion, comprension, utilidad, comentario)
         VALUES (:documento_id, :servicio, :calificacion_info, :datosSatisfaccion, :comprension, :utilidad, :comentario)'
        );
        $consulta->execute($parametros);
    }

    public function listar(): array
    {
        return $this->conexion()->query(
            'SELECT servicio, calificacion_info, datosSatisfaccion, comentario, creado_en
             FROM vw_encuestas_detalle
             ORDER BY creado_en DESC'
        )->fetchAll();
    }

    public function resumen(): array
    {
        return $this->conexion()->query(
            'SELECT servicio, total_respuestas, suma_satisfaccion
             FROM vw_encuestas_resumen'
        )->fetchAll();
    }
}
