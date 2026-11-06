<?php
declare(strict_types=1);

require_once __DIR__ . '/ConexionDAO.php';

final class UsuarioDAO extends ConexionDAO
{
    public function buscarParaLogin(string $documento): array|false
    {
        $consulta = $this->conexion()->prepare(
            'SELECT id, nombre, clave, rol
             FROM usuarios
             WHERE documento = :documento AND activo = 1'
        );
        $consulta->execute(['documento' => $documento]);
        return $consulta->fetch();
    }

    public function buscarPerfil(int $usuarioId): array|false
    {
        $consulta = $this->conexion()->prepare(
            'SELECT nombre, rol, activo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $usuarioId]);
        return $consulta->fetch();
    }

    public function actualizarClave(int $id, string $hash): void
    {
        $actualizarClave = $this->conexion()->prepare(
            'UPDATE usuarios SET clave = :clave WHERE id = :id'
        );
        $actualizarClave->execute([
            'clave' => $hash,
            'id' => $id,
        ]);
    }

    public function crearFuncionario(string $documento, string $nombre, string $hash): int
    {
        $crearUsuario = $this->conexion()->prepare(
            'INSERT INTO usuarios (documento, nombre, clave, rol, activo)
             VALUES (:documento, :nombre, :clave, :rol, 1)'
        );
        $crearUsuario->execute([
            'documento' => $documento,
            'nombre' => $nombre,
            'clave' => $hash,
            'rol' => 'funcionario',
        ]);
        return (int)$this->conexion()->lastInsertId();
    }

    public function listarFuncionarios(): array
    {
        return $this->conexion()->query(
            "SELECT id, documento, nombre FROM usuarios WHERE rol = 'funcionario' AND activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function desactivarFuncionario(int $id): bool
    {
        $consulta = $this->conexion()->prepare(
            "UPDATE usuarios SET activo = 0 WHERE id = :id AND rol = 'funcionario' AND activo = 1"
        );
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() === 1;
    }
}
