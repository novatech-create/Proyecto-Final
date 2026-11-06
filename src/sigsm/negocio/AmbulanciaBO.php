<?php
declare(strict_types=1);

require_once __DIR__ . '/ResultadoOperacion.php';
require_once __DIR__ . '/../datos/AmbulanciaDAO.php';

final class AmbulanciaBO
{
    private AmbulanciaDAO $dao;

    public function __construct()
    {
        $this->dao = new AmbulanciaDAO();
    }

    public function catalogos(): array
    {
        return resultadoOperacion(['success' => true] + $this->dao->catalogos());
    }

    public function consultarTraslados(string $accion): array
    {
        $traslados = $this->dao->listarTraslados();
        $estadosActivos = ['solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno'];
        $activos = array_values(array_filter($traslados, static fn(array $item): bool => in_array($item['Estado'], $estadosActivos, true)));

        if ($accion === 'activos') {
            return resultadoOperacion([
                'success' => true,
                'Data' => $activos,
            ]);
        }
        if ($accion === 'historial') {
            $historial = array_values(array_filter(
                $traslados,
                static fn(array $item): bool => in_array($item['Estado'], ['finalizado', 'cancelado'], true),
            ));
            return resultadoOperacion(['success' => true, 'Data' => $historial]);
        }

        $vehiculos = $this->dao->listarVehiculos();
        $ocupados = [];
        foreach ($activos as $traslado) {
            $ocupados[$traslado['MatriculaFK']] = $traslado;
        }
        $flota = [];
        foreach ($vehiculos as $vehiculo) {
            $flota[] = [
                'Matricula' => $vehiculo['Matricula'],
                'TipoVehiculo' => $vehiculo['TipoVehiculo'],
                'RequiereEspecializado' => (bool)$vehiculo['RequiereEspecializado'],
                'Traslado' => $ocupados[$vehiculo['Matricula']] ?? null,
            ];
        }
        return resultadoOperacion([
            'success' => true,
            'Estadisticas' => [
                'VehiculosDisponibles' => count($vehiculos) - count($ocupados),
                'TrasladosActivos' => count($activos),
                'PrioridadNoDisponible' => 0,
            ],
            'Flota' => $flota,
            'Activos' => $activos,
        ]);
    }

    public function administracion(): array
    {
        $personal = $this->dao->listarPersonal();
        return resultadoOperacion(['success' => true, 'Personal' => $personal] + $this->dao->catalogos());
    }

    public function crearPersonal(array $datos): array
    {
        $nombre = $this->textoEntrada($datos, 'Nombre');
        $perfiles = $datos['Perfiles'] ?? null;
        $telefonos = $datos['Telefonos'] ?? [];
        if ($nombre === null || $nombre === '' || mb_strlen($nombre, 'UTF-8') > 150
            || !is_array($perfiles) || !$perfiles
            || !is_array($telefonos)
        ) {
            return resultadoOperacion(['success' => false, 'message' => 'Revise el nombre, los perfiles y los teléfonos.'], 400);
        }
        $perfilesPermitidos = ['chofer', 'enfermero', 'medico'];
        foreach ($perfiles as $perfil) {
            if (!in_array($perfil, $perfilesPermitidos, true)) {
                return resultadoOperacion(['success' => false, 'message' => 'Se recibió un perfil no permitido.'], 400);
            }
        }
        $perfiles = array_unique($perfiles);
        foreach ($telefonos as $telefono) {
            if (!is_string($telefono) || strlen(trim($telefono)) < 6 || strlen(trim($telefono)) > 25) {
                return resultadoOperacion(['success' => false, 'message' => 'Cada teléfono debe tener de 6 a 25 caracteres.'], 400);
            }
        }

        try {
            $this->dao->beginTransaction();
            $idFuncionario = $this->dao->crearFuncionario($nombre);
            $detallesPerfil = [
                'chofer' => $this->textoEntrada($datos, 'TipoLicencia'),
                'enfermero' => $this->textoEntrada($datos, 'Especialidad'),
                'medico' => null,
            ];
            foreach ($perfiles as $perfil) {
                $valor = $detallesPerfil[$perfil];
                if ($perfil !== 'medico' && ($valor === null || $valor === ''
                    || mb_strlen($valor, 'UTF-8') > ($perfil === 'chofer' ? 30 : 120))) {
                    throw new DomainException('Complete los datos requeridos del perfil seleccionado.');
                }
                $this->dao->guardarPerfil($idFuncionario, $perfil, $valor);
            }
            $this->dao->guardarTelefonos($idFuncionario, $telefonos);
            $this->dao->commit();
            return resultadoOperacion(['success' => true, 'IdFuncionario' => $idFuncionario], 201);
        } catch (DomainException $error) {
            if ($this->dao->inTransaction()) {
                $this->dao->rollBack();
            }
            return resultadoOperacion(['success' => false, 'message' => $error->getMessage()], 400);
        } catch (PDOException $error) {
            if ($this->dao->inTransaction()) {
                $this->dao->rollBack();
            }
            return resultadoOperacion(['success' => false, 'message' => $error->getCode() === '23000'
                ? 'Hay un dato relacionado duplicado.'
                : 'No se pudo registrar el personal.'], $error->getCode() === '23000' ? 409 : 500);
        }
    }

    public function guardarVehiculo(array $datos): array
    {
        $matricula = $this->textoEntrada($datos, 'Matricula');
        $tipo = $this->textoEntrada($datos, 'TipoVehiculo');
        $especializado = $this->booleanoEntrada($datos, 'RequiereEspecializado');
        if ($matricula === null || !preg_match('/^[A-Za-z0-9-]{5,15}$/', $matricula)
            || $tipo === null || $tipo === '' || mb_strlen($tipo, 'UTF-8') > 50
            || $especializado === null
        ) {
            return resultadoOperacion(['success' => false, 'message' => 'Revise matrícula, tipo de vehículo y equipamiento especializado.'], 400);
        }
        try {
            $this->dao->guardarVehiculo(strtoupper($matricula), $tipo, $especializado);
            return resultadoOperacion(['success' => true]);
        } catch (Throwable $error) {
            return resultadoOperacion(['success' => false, 'message' => 'No se pudo guardar el vehículo.'], 500);
        }
    }

    public function crearRuta(array $datos): array
    {
        $origen = $this->textoEntrada($datos, 'PuntoOrigen');
        $destino = $this->textoEntrada($datos, 'PuntoDestino');
        $rutina = $this->booleanoEntrada($datos, 'RutinaBase');
        if ($origen === null || $origen === '' || mb_strlen($origen, 'UTF-8') > 200
            || $destino === null || $destino === '' || mb_strlen($destino, 'UTF-8') > 200
            || $rutina === null
        ) {
            return resultadoOperacion(['success' => false, 'message' => 'Complete origen, destino y tipo de ruta.'], 400);
        }
        $idRuta = $this->dao->crearRuta($origen, $destino, $rutina);
        return resultadoOperacion(['success' => true, 'IdRuta' => $idRuta], 201);
    }

    public function crearTraslado(array $datos, int $usuarioId): array
    {
        $motivo = $this->textoEntrada($datos, 'Motivo');
        $via = $this->textoEntrada($datos, 'ViaSolicitud');
        $tipoElemento = $this->textoEntrada($datos, 'TipoElemento');
        $matricula = $this->textoEntrada($datos, 'MatriculaFK');
        $referencia = $this->textoEntrada($datos, 'NroReferencia');
        $datosPaciente = $this->textoEntrada($datos, 'DatosPaciente');
        $idChofer = $this->enteroEntrada($datos, 'IdChoferFK');
        $idEnfermero = $this->enteroEntrada($datos, 'IdEnfermeroFK', true);
        $idMedico = $this->enteroEntrada($datos, 'IdMedicoFK', true);
        $idRuta = $this->enteroEntrada($datos, 'IdRutaFK');
        $llegadaEstimada = $this->fechaEntrada($this->textoEntrada($datos, 'HoraLlegadaEst'));

        if ($motivo === null || $motivo === '' || mb_strlen($motivo, 'UTF-8') > 500
            || !in_array($via, ['telefono', 'presencial', 'interno', 'sistema'], true)
            || !in_array($tipoElemento, ['paciente', 'equipamiento', 'insumo'], true)
            || $matricula === null || $idChofer === null || $idRuta === null
            || (($datos['HoraLlegadaEst'] ?? '') !== '' && $llegadaEstimada === null)
            || ($tipoElemento === 'paciente' && ($datosPaciente === null || $datosPaciente === '' || mb_strlen($datosPaciente, 'UTF-8') > 2000))
            || ($tipoElemento !== 'paciente' && $datosPaciente !== null && mb_strlen($datosPaciente, 'UTF-8') > 2000)
            || ($referencia !== null && mb_strlen($referencia, 'UTF-8') > 50)
        ) {
            return resultadoOperacion(['success' => false, 'message' => 'Revise motivo, solicitud, vehículo, personal, ruta y datos del elemento.'], 400);
        }

        try {
            $this->dao->beginTransaction();
            $idResponsable = $this->dao->vincularResponsable($usuarioId);
            if ($idResponsable === null) {
                $this->dao->rollBack();
                return resultadoOperacion(['success' => false, 'message' => 'La cuenta ya no tiene permiso para registrar traslados.'], 403);
            }
            $vehiculo = $this->dao->bloquearVehiculo($matricula);
            if (!$vehiculo) {
                $this->dao->rollBack();
                return resultadoOperacion(['success' => false, 'message' => 'El vehículo seleccionado no existe.'], 400);
            }
            if ((int)$vehiculo['RequiereEspecializado'] === 1 && $idEnfermero === null && $idMedico === null) {
                $this->dao->rollBack();
                return resultadoOperacion(['success' => false, 'message' => 'Este vehículo requiere asignar un enfermero o un médico.'], 400);
            }
            if ($this->dao->vehiculoOcupado($matricula)) {
                $this->dao->rollBack();
                return resultadoOperacion(['success' => false, 'message' => 'El vehículo ya está asignado a un traslado activo.'], 409);
            }

            $idElemento = $this->dao->crearElemento([
                'tipo' => $tipoElemento,
                'referencia' => $referencia !== '' ? $referencia : null,
                'datos' => $tipoElemento === 'paciente' ? $datosPaciente : null,
            ]);
            $idTraslado = $this->dao->crearTraslado([
                'motivo' => $motivo,
                'via' => $via,
                'llegada' => $llegadaEstimada,
                'chofer' => $idChofer,
                'enfermero' => $idEnfermero,
                'administrativo' => $idResponsable,
                'medico' => $idMedico,
                'matricula' => $matricula,
                'ruta' => $idRuta,
                'elemento' => $idElemento,
            ]);
            $this->dao->commit();
            return resultadoOperacion(['success' => true, 'IdTraslado' => $idTraslado], 201);
        } catch (PDOException $error) {
            if ($this->dao->inTransaction()) {
                $this->dao->rollBack();
            }
            return resultadoOperacion(['success' => false, 'message' => $error->getCode() === '23000'
                ? 'Una de las relaciones seleccionadas ya no está disponible.'
                : 'No se pudo registrar el traslado.'], $error->getCode() === '23000' ? 409 : 500);
        }
    }

    public function actualizarEstado(array $datos): array
    {
        $id = $this->enteroEntrada($datos, 'IdTraslado');
        $nuevoEstado = $this->textoEntrada($datos, 'Estado');
        if ($id === null) {
            return resultadoOperacion(['success' => false, 'message' => 'Identificador de traslado inválido.'], 400);
        }
        $transiciones = [
            'solicitado' => ['pendiente', 'cancelado'],
            'pendiente' => ['en_camino', 'cancelado'],
            'en_camino' => ['en_destino', 'cancelado'],
            'en_destino' => ['en_retorno'],
            'en_retorno' => ['finalizado'],
        ];
        try {
            $this->dao->beginTransaction();
            $trasladoBloqueado = $this->dao->bloquearTraslado($id);
            if (!$trasladoBloqueado) {
                $this->dao->rollBack();
                return resultadoOperacion(['success' => false, 'message' => 'Traslado no encontrado.'], 404);
            }
            $estadoActual = $trasladoBloqueado['Estado'];
            if (!in_array($nuevoEstado, $transiciones[$estadoActual] ?? [], true)) {
                $this->dao->rollBack();
                return resultadoOperacion(['success' => false, 'message' => 'La transición de estado no está permitida.'], 409);
            }
            $this->dao->actualizarEstado($id, $nuevoEstado);
            $this->dao->commit();
            return resultadoOperacion(['success' => true, 'Estado' => $nuevoEstado]);
        } catch (Throwable $error) {
            if ($this->dao->inTransaction()) {
                $this->dao->rollBack();
            }
            return resultadoOperacion(['success' => false, 'message' => 'No se pudo actualizar el traslado.'], 500);
        }
    }

    private function textoEntrada(array $datos, string $campo): ?string
    {
        $valor = $datos[$campo] ?? null;
        return is_string($valor) ? trim($valor) : null;
    }

    private function enteroEntrada(array $datos, string $campo, bool $opcional = false): ?int
    {
        $valor = $datos[$campo] ?? null;
        if ($opcional && ($valor === null || $valor === '')) {
            return null;
        }
        if (!is_int($valor) && !is_string($valor)) {
            return null;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT);
        return $entero !== false && $entero > 0 ? $entero : null;
    }

    private function booleanoEntrada(array $datos, string $campo): ?int
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === true || $valor === 1 || $valor === '1') {
            return 1;
        }
        if ($valor === false || $valor === 0 || $valor === '0') {
            return 0;
        }
        if (is_string($valor)) {
            $normalizado = strtolower(trim($valor));
            if ($normalizado === 'true') return 1;
            if ($normalizado === 'false') return 0;
        }
        return null;
    }

    private function fechaEntrada(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $fecha = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $valor);
        return $fecha ? $fecha->format('Y-m-d H:i:s') : null;
    }
}
