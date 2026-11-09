<?php
declare(strict_types=1);

require_once __DIR__ . '/ConexionDAO.php';

final class AmbulanciaDAO extends ConexionDAO
{
    public function catalogos(): array
    {
        return [
            'Choferes' => $this->conexion()->query(
                'SELECT c.IdFuncionarioFK AS IdFuncionario, f.Nombre, c.TipoLicencia
                 FROM CHOFER c JOIN FUNCIONARIO f ON f.IdFuncionario = c.IdFuncionarioFK
                 ORDER BY f.Nombre'
            )->fetchAll(),
            'Enfermeros' => $this->conexion()->query(
                'SELECT e.IdFuncionarioFK AS IdFuncionario, f.Nombre, e.Especialidad
                 FROM ENFERMERO e JOIN FUNCIONARIO f ON f.IdFuncionario = e.IdFuncionarioFK
                 ORDER BY f.Nombre'
            )->fetchAll(),
            'Medicos' => $this->conexion()->query(
                'SELECT m.IdFuncionarioFK AS IdFuncionario, f.Nombre
                 FROM MEDICO m JOIN FUNCIONARIO f ON f.IdFuncionario = m.IdFuncionarioFK
                 ORDER BY f.Nombre'
            )->fetchAll(),
            'Vehiculos' => $this->conexion()->query(
                'SELECT Matricula, TipoVehiculo, RequiereEspecializado FROM VEHICULO ORDER BY Matricula'
            )->fetchAll(),
            'Rutas' => $this->conexion()->query(
                'SELECT IdRuta, PuntoOrigen, PuntoDestino, RutinaBase FROM RUTA ORDER BY RutinaBase DESC, PuntoOrigen, PuntoDestino'
            )->fetchAll(),
        ];
    }

    public function listarTraslados(): array
    {
        $consulta = $this->conexion()->query(
            "SELECT t.IdTraslado, t.Motivo, t.ViaSolicitud, t.Estado, t.HoraSalida,
                    t.HoraLlegadaEst, t.HoraLlegadaDestino, t.HoraRetorno, t.HoraLlegadaBase, t.IdChoferFK, t.IdEnfermeroFK,
                    t.IdAdministrativoFK, t.IdMedicoFK, t.MatriculaFK, t.IdRutaFK, t.IdElementoFK,
                    v.TipoVehiculo, v.RequiereEspecializado,
                    r.PuntoOrigen, r.PuntoDestino, r.RutinaBase,
                    elemento.TipoElemento, elemento.NroReferencia, elemento.DatosPaciente,
                    chofer.Nombre AS NombreChofer, enfermero.Nombre AS NombreEnfermero,
                    administrativo.Nombre AS NombreResponsable, medico.Nombre AS NombreMedico
             FROM TRASLADO t
             JOIN VEHICULO v ON v.Matricula = t.MatriculaFK
             JOIN RUTA r ON r.IdRuta = t.IdRutaFK
             JOIN ELEMENTOTRASLADADO elemento ON elemento.IdElemento = t.IdElementoFK
             JOIN FUNCIONARIO chofer ON chofer.IdFuncionario = t.IdChoferFK
             JOIN FUNCIONARIO administrativo ON administrativo.IdFuncionario = t.IdAdministrativoFK
             LEFT JOIN FUNCIONARIO enfermero ON enfermero.IdFuncionario = t.IdEnfermeroFK
             LEFT JOIN FUNCIONARIO medico ON medico.IdFuncionario = t.IdMedicoFK
             ORDER BY FIELD(t.Estado, 'solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno') ASC,
                      COALESCE(t.HoraSalida, t.HoraLlegadaEst) DESC,
                      t.IdTraslado DESC"
        );
        return $consulta->fetchAll();
    }

    public function listarVehiculos(): array
    {
        return $this->conexion()->query('SELECT Matricula, TipoVehiculo, RequiereEspecializado FROM VEHICULO ORDER BY Matricula')->fetchAll();
    }

    public function listarPersonal(): array
    {
        return $this->conexion()->query(
            'SELECT f.IdFuncionario, f.Nombre,
                    EXISTS(SELECT 1 FROM CHOFER c WHERE c.IdFuncionarioFK = f.IdFuncionario) AS EsChofer,
                    EXISTS(SELECT 1 FROM ENFERMERO e WHERE e.IdFuncionarioFK = f.IdFuncionario) AS EsEnfermero,
                    EXISTS(SELECT 1 FROM MEDICO m WHERE m.IdFuncionarioFK = f.IdFuncionario) AS EsMedico
             FROM FUNCIONARIO f
             ORDER BY f.Nombre'
        )->fetchAll();
    }

    public function vincularResponsable(int $usuarioId): ?int
    {
        $usuario = $this->conexion()->prepare(
            "SELECT nombre FROM usuarios WHERE id = :id AND activo = 1 AND rol = 'funcionario' FOR UPDATE"
        );
        $usuario->execute(['id' => $usuarioId]);
        $nombre = $usuario->fetchColumn();
        if ($nombre === false) {
            return null;
        }

        $vinculo = $this->conexion()->prepare(
            'SELECT IdFuncionarioFK FROM FUNCIONARIO_CUENTA WHERE IdUsuarioFK = :usuario FOR UPDATE'
        );
        $vinculo->execute(['usuario' => $usuarioId]);
        $idFuncionario = $vinculo->fetchColumn();
        if ($idFuncionario !== false) {
            return (int)$idFuncionario;
        }

        $idFuncionario = $this->crearFuncionario($nombre);
        $vincular = $this->conexion()->prepare(
            'INSERT INTO FUNCIONARIO_CUENTA (IdFuncionarioFK, IdUsuarioFK) VALUES (:funcionario, :usuario)'
        );
        $vincular->execute(['funcionario' => $idFuncionario, 'usuario' => $usuarioId]);
        return $idFuncionario;
    }

    public function crearFuncionario(string $nombre): int
    {
        $crearFuncionario = $this->conexion()->prepare('INSERT INTO FUNCIONARIO (Nombre) VALUES (:nombre)');
        $crearFuncionario->execute(['nombre' => $nombre]);
        return (int)$this->conexion()->lastInsertId();
    }

    public function guardarPerfil(int $idFuncionario, string $perfil, ?string $valor): void
    {
        $sql = match ($perfil) {
            'chofer' => 'INSERT INTO CHOFER (IdFuncionarioFK, TipoLicencia) VALUES (:id, :valor)',
            'enfermero' => 'INSERT INTO ENFERMERO (IdFuncionarioFK, Especialidad) VALUES (:id, :valor)',
            'medico' => 'INSERT INTO MEDICO (IdFuncionarioFK) VALUES (:id)',
        };
        $parametros = ['id' => $idFuncionario];
        if ($valor !== null) {
            $parametros['valor'] = $valor;
        }
        $this->conexion()->prepare($sql)->execute($parametros);
    }

    public function guardarTelefonos(int $idFuncionario, array $telefonos): void
    {
        $insertarTelefono = $this->conexion()->prepare(
            'INSERT INTO TELEFONOS (IdFuncionarioFK, Telefono) VALUES (:id, :telefono)'
        );
        foreach ($telefonos as $telefono) {
            $telefono = trim($telefono);
            if ($telefono !== '') {
                $insertarTelefono->execute(['id' => $idFuncionario, 'telefono' => $telefono]);
            }
        }
    }

    public function guardarVehiculo(string $matricula, string $tipo, int $especializado): void
    {
        $guardar = $this->conexion()->prepare(
            'INSERT INTO VEHICULO (Matricula, TipoVehiculo, RequiereEspecializado)
             VALUES (:matricula, :tipo, :especializado)
             ON DUPLICATE KEY UPDATE TipoVehiculo = VALUES(TipoVehiculo), RequiereEspecializado = VALUES(RequiereEspecializado)'
        );
        $guardar->execute(['matricula' => $matricula, 'tipo' => $tipo, 'especializado' => $especializado]);
    }

    public function crearRuta(string $origen, string $destino, int $rutina): int
    {
        $guardar = $this->conexion()->prepare(
            'INSERT INTO RUTA (PuntoOrigen, PuntoDestino, RutinaBase) VALUES (:origen, :destino, :rutina)'
        );
        $guardar->execute(['origen' => $origen, 'destino' => $destino, 'rutina' => $rutina]);
        return (int)$this->conexion()->lastInsertId();
    }

    public function bloquearVehiculo(string $matricula): array|false
    {
        $bloquearVehiculo = $this->conexion()->prepare('SELECT Matricula, RequiereEspecializado FROM VEHICULO WHERE Matricula = :matricula FOR UPDATE');
        $bloquearVehiculo->execute(['matricula' => $matricula]);
        return $bloquearVehiculo->fetch();
    }

    public function vehiculoOcupado(string $matricula): bool
    {
        $ocupado = $this->conexion()->prepare(
            "SELECT IdTraslado FROM TRASLADO WHERE MatriculaFK = :matricula
             AND Estado IN ('solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno') LIMIT 1"
        );
        $ocupado->execute(['matricula' => $matricula]);
        return (bool)$ocupado->fetchColumn();
    }

    public function crearElemento(array $parametros): int
    {
        $crearElemento = $this->conexion()->prepare(
            'INSERT INTO ELEMENTOTRASLADADO (TipoElemento, NroReferencia, DatosPaciente)
             VALUES (:tipo, :referencia, :datos)'
        );
        $crearElemento->execute($parametros);
        return (int)$this->conexion()->lastInsertId();
    }

    public function crearTraslado(array $parametros): int
    {
        $crearTraslado = $this->conexion()->prepare(
            "INSERT INTO TRASLADO
             (Motivo, ViaSolicitud, Estado, HoraLlegadaEst, IdChoferFK, IdEnfermeroFK,
              IdAdministrativoFK, IdMedicoFK, MatriculaFK, IdRutaFK, IdElementoFK)
             VALUES (:motivo, :via, 'solicitado', :llegada, :chofer, :enfermero,
                     :administrativo, :medico, :matricula, :ruta, :elemento)"
        );
        $crearTraslado->execute($parametros);
        return (int)$this->conexion()->lastInsertId();
    }

    public function bloquearTraslado(int $id): array|false
    {
        $buscar = $this->conexion()->prepare(
            'SELECT Estado
             FROM TRASLADO WHERE IdTraslado = :id FOR UPDATE'
        );
        $buscar->execute(['id' => $id]);
        return $buscar->fetch();
    }

    public function actualizarEstado(int $id, string $nuevoEstado): void
    {
        $actualizar = $this->conexion()->prepare(
            "UPDATE TRASLADO SET Estado = :estado,
            HoraSalida = CASE
             WHEN :estado_salida = 'en_camino'
             THEN COALESCE(HoraSalida, NOW())
             ELSE HoraSalida END,
            HoraLlegadaDestino = CASE
             WHEN :estado_llegada = 'en_destino'
             THEN COALESCE(HoraLlegadaDestino, NOW())
             ELSE HoraLlegadaDestino END,
            HoraRetorno = CASE
             WHEN :estado_retorno = 'en_retorno'
             THEN COALESCE(HoraRetorno, NOW())
             ELSE HoraRetorno END,
            HoraLlegadaBase = CASE
             WHEN :estado_base = 'finalizado'
             THEN COALESCE(HoraLlegadaBase, NOW())
             ELSE HoraLlegadaBase END
             WHERE IdTraslado = :id"
        );

        $actualizar->execute([
            'estado' => $nuevoEstado,
            'estado_salida' => $nuevoEstado,
            'estado_llegada' => $nuevoEstado,
            'estado_retorno' => $nuevoEstado,
            'estado_base' => $nuevoEstado,
            'id' => $id,
        ]);
    }

    public function beginTransaction(): void
    {
        $this->conexion()->beginTransaction();
    }

    public function inTransaction(): bool
    {
        return $this->conexion()->inTransaction();
    }

    public function commit(): void
    {
        $this->conexion()->commit();
    }

    public function rollBack(): void
    {
        $this->conexion()->rollBack();
    }
}
