<?php
declare(strict_types=1);

require_once __DIR__ . '/ConexionDAO.php';

final class DocumentoDAO extends ConexionDAO
{
    public function listar(bool $soloAprobados): array
    {
        $filtro = $soloAprobados ? " AND d.estado_revision = 'aprobado'" : '';
        $consulta = $this->conexion()->query(
            
            "SELECT d.*, c.id AS categoria_id, c.nombre AS categoria_nombre
             FROM categorias_documentos c
             LEFT JOIN documentos d ON d.categoria_id = c.id AND d.activo = 1 ${filtro}
             ORDER BY c.nombre, d.fecha_actualizacion DESC"
        );

        return $consulta->fetchAll();
    }

    public function buscar(int $id, bool $soloAprobados): array|false
    {
        $filtro = $soloAprobados ? " AND d.estado_revision = 'aprobado'" : '';
        $consulta = $this->conexion()->prepare(
            "SELECT d.id, d.titulo, d.descripcion, d.especialista, d.archivo_url, d.peso_kb,
                    d.fecha_actualizacion, d.estado_revision, c.nombre AS categoria_nombre
             FROM documentos d JOIN categorias_documentos c ON c.id = d.categoria_id
             WHERE d.id = :id AND d.activo = 1 ${filtro}"
        );
        $consulta->execute(['id' => $id]);
        return $consulta->fetch();
    }

    public function existeCategoria(int $categoriaId): bool
    {
        $categoriaExiste = $this->conexion()->prepare(
            'SELECT 1 FROM categorias_documentos WHERE id = :categoria_id'
        );
        $categoriaExiste->execute(['categoria_id' => $categoriaId]);
        return (bool)$categoriaExiste->fetchColumn();
    }

    public function buscarRechazado(int $id, int $usuarioId): array|false
    {
        $buscar = $this->conexion()->prepare(
            "SELECT archivo_url, peso_kb
             FROM documentos
             WHERE id = :id AND activo = 1 AND estado_revision = 'rechazado'
               AND creado_por = :autor"
        );
        $buscar->execute(['id' => $id, 'autor' => $usuarioId]);
        return $buscar->fetch();
    }

    public function reenviar(array $parametros): bool
    {
        $consulta = $this->conexion()->prepare(
            "UPDATE documentos
             SET categoria_id = :categoria, titulo = :titulo, descripcion = :descripcion,
                 especialista = :especialista, archivo_url = :archivo, peso_kb = :peso,
                 estado_revision = 'pendiente', revisado_por = NULL,
                 revisado_en = NULL, motivo_rechazo = NULL
             WHERE id = :id AND activo = 1 AND estado_revision = 'rechazado'
               AND creado_por = :autor"
        );
        $consulta->execute($parametros);
        return $consulta->rowCount() === 1;
    }

    public function crear(array $parametros): int
    {
        $consulta = $this->conexion()->prepare(
            'INSERT INTO documentos (categoria_id, titulo, descripcion, especialista, archivo_url, peso_kb, creado_por)
             VALUES (:categoria_id, :titulo, :descripcion, :especialista, :archivo_url, :peso_kb, :creado_por)'
        );
        $consulta->execute($parametros);
        return (int)$this->conexion()->lastInsertId();
    }

    public function desactivar(int $id): bool
    {
        $consulta = $this->conexion()->prepare(

            // Esto hace la baja lógica del documento, cambiando el campo "activo" a 0
            'UPDATE documentos SET activo = 0 WHERE id = :id AND activo = 1'
        );
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() > 0;
    }

    public function buscarArchivo(int $id): array|false
    {
        $consulta = $this->conexion()->prepare(
            'SELECT archivo_url, estado_revision FROM documentos WHERE id = :id AND activo = 1'
        );
        $consulta->execute(['id' => $id]);
        return $consulta->fetch();
    }

    public function existeAprobado(int $id): bool
    {
        $consulta = $this->conexion()->prepare(
            "SELECT 1 FROM documentos WHERE id = :id AND activo = 1 AND estado_revision = 'aprobado'"
        );
        $consulta->execute(['id' => $id]);
        return (bool)$consulta->fetchColumn();
    }

    public function revisar(int $id, string $estado, string $motivo, int $usuarioId): bool
    {
        $consulta = $this->conexion()->prepare(
            "UPDATE documentos
             SET estado_revision = :estado, revisado_por = :revisor, revisado_en = NOW(), motivo_rechazo = :motivo
             WHERE id = :id AND activo = 1 AND estado_revision = 'pendiente' AND (archivo_url <> '' OR :permite_sin_archivo = 1)"
        );
        $consulta->execute([
            'id' => $id,
            'estado' => $estado,
            'permite_sin_archivo' => $estado === 'rechazado' ? 1 : 0,
            'revisor' => $usuarioId,
            'motivo' => $estado === 'rechazado' ? $motivo : null,
        ]);
        return $consulta->rowCount() === 1;
    }

    public function listarPendientes(): array
    {
        return $this->conexion()->query(
            "SELECT id, titulo, descripcion, especialista FROM documentos
             WHERE activo = 1 AND estado_revision = 'pendiente' ORDER BY id"
        )->fetchAll();
    }
}
