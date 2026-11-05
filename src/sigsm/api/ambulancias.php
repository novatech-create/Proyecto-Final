<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

$accion = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
exigirRol(['funcionario']);
$conexion = obtenerConexion();

// Se llama dentro de la transacción que registra el traslado.
function responsableTraslado(PDO $conexion): int
{
    $usuario = $conexion->prepare(
        "SELECT nombre FROM usuarios WHERE id = :id AND activo = 1 AND rol = 'funcionario' FOR UPDATE"
    );
    $usuario->execute(['id' => $_SESSION['usuario_id']]);
    $nombre = $usuario->fetchColumn();
    if ($nombre === false) {
        $conexion->rollBack();
        responder(['success' => false, 'message' => 'La cuenta ya no tiene permiso para registrar traslados.'], 403);
    }

    $vinculo = $conexion->prepare(
        'SELECT IdFuncionarioFK FROM FUNCIONARIO_CUENTA WHERE IdUsuarioFK = :usuario FOR UPDATE'
    );
    $vinculo->execute(['usuario' => $_SESSION['usuario_id']]);
    $idFuncionario = $vinculo->fetchColumn();
    if ($idFuncionario !== false) {
        return (int)$idFuncionario;
    }

    $crear = $conexion->prepare('INSERT INTO FUNCIONARIO (Nombre) VALUES (:nombre)');
    $crear->execute(['nombre' => $nombre]);
    $idFuncionario = (int)$conexion->lastInsertId();
    $vincular = $conexion->prepare(
        'INSERT INTO FUNCIONARIO_CUENTA (IdFuncionarioFK, IdUsuarioFK) VALUES (:funcionario, :usuario)'
    );
    $vincular->execute(['funcionario' => $idFuncionario, 'usuario' => $_SESSION['usuario_id']]);
    return $idFuncionario;
}

function textoEntrada(array $datos, string $campo): ?string
{
    $valor = $datos[$campo] ?? null;
    return is_string($valor) ? trim($valor) : null;
}

function enteroEntrada(array $datos, string $campo, bool $opcional = false): ?int
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

function booleanoEntrada(array $datos, string $campo): ?int
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

function fechaEntrada(?string $valor): ?string
{
    if ($valor === null || $valor === '') {
        return null;
    }
    $fecha = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $valor);
    return $fecha ? $fecha->format('Y-m-d H:i:s') : null;
}

function obtenerCatalogos(PDO $conexion): array
{
    return [
        'Choferes' => $conexion->query(
            'SELECT c.IdFuncionarioFK AS IdFuncionario, f.Nombre, c.TipoLicencia
             FROM CHOFER c JOIN FUNCIONARIO f ON f.IdFuncionario = c.IdFuncionarioFK
             ORDER BY f.Nombre'
        )->fetchAll(),
        'Enfermeros' => $conexion->query(
            'SELECT e.IdFuncionarioFK AS IdFuncionario, f.Nombre, e.Especialidad
             FROM ENFERMERO e JOIN FUNCIONARIO f ON f.IdFuncionario = e.IdFuncionarioFK
             ORDER BY f.Nombre'
        )->fetchAll(),
        'Medicos' => $conexion->query(
            'SELECT m.IdFuncionarioFK AS IdFuncionario, f.Nombre
             FROM MEDICO m JOIN FUNCIONARIO f ON f.IdFuncionario = m.IdFuncionarioFK
             ORDER BY f.Nombre'
        )->fetchAll(),
        'Vehiculos' => $conexion->query(
            'SELECT Matricula, TipoVehiculo, RequiereEspecializado FROM VEHICULO ORDER BY Matricula'
        )->fetchAll(),
        'Rutas' => $conexion->query(
            'SELECT IdRuta, PuntoOrigen, PuntoDestino, RutinaBase FROM RUTA ORDER BY RutinaBase DESC, PuntoOrigen, PuntoDestino'
        )->fetchAll(),
    ];
}

if ($accion === 'catalogos') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(['success' => false, 'message' => 'Usa GET.'], 405);
    }
    responder(['success' => true] + obtenerCatalogos($conexion));
}

if ($accion === 'resumen' || $accion === 'activos' || $accion === 'historial') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(['success' => false, 'message' => 'Usa GET.'], 405);
    }
    $consulta = $conexion->query(
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
    $traslados = $consulta->fetchAll();
    $estadosActivos = ['solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno'];
    $activos = array_values(array_filter($traslados, static fn(array $item): bool => in_array($item['Estado'], $estadosActivos, true)));

    if ($accion === 'activos') {
        responder([
            'success' => true,
            'Data' => $activos,
        ]);
    }
    if ($accion === 'historial') {
        $historial = array_values(array_filter(
            $traslados,
            static fn(array $item): bool => in_array($item['Estado'], ['finalizado', 'cancelado'], true),
        ));
        responder(['success' => true, 'Data' => $historial]);
    }

    $vehiculos = $conexion->query('SELECT Matricula, TipoVehiculo, RequiereEspecializado FROM VEHICULO ORDER BY Matricula')->fetchAll();
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
    responder([
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

if ($accion === 'administracion') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(['success' => false, 'message' => 'Usa GET.'], 405);
    }
    $personal = $conexion->query(
        'SELECT f.IdFuncionario, f.Nombre,
                EXISTS(SELECT 1 FROM CHOFER c WHERE c.IdFuncionarioFK = f.IdFuncionario) AS EsChofer,
                EXISTS(SELECT 1 FROM ENFERMERO e WHERE e.IdFuncionarioFK = f.IdFuncionario) AS EsEnfermero,
                EXISTS(SELECT 1 FROM MEDICO m WHERE m.IdFuncionarioFK = f.IdFuncionario) AS EsMedico
         FROM FUNCIONARIO f
         ORDER BY f.Nombre'
    )->fetchAll();
    responder(['success' => true, 'Personal' => $personal] + obtenerCatalogos($conexion));
}

if ($accion === 'crear-personal') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $nombre = textoEntrada($datos, 'Nombre');
    $perfiles = $datos['Perfiles'] ?? null;
    $telefonos = $datos['Telefonos'] ?? [];
    if ($nombre === null || $nombre === '' || mb_strlen($nombre, 'UTF-8') > 150
        || !is_array($perfiles) || !$perfiles
        || !is_array($telefonos)
    ) {
        responder(['success' => false, 'message' => 'Revise el nombre, los perfiles y los teléfonos.'], 400);
    }
    $perfilesPermitidos = ['chofer', 'enfermero', 'medico'];
    foreach ($perfiles as $perfil) {
        if (!in_array($perfil, $perfilesPermitidos, true)) {
            responder(['success' => false, 'message' => 'Se recibió un perfil no permitido.'], 400);
        }
    }
    $perfiles = array_unique($perfiles);
    foreach ($telefonos as $telefono) {
        if (!is_string($telefono) || strlen(trim($telefono)) < 6 || strlen(trim($telefono)) > 25) {
            responder(['success' => false, 'message' => 'Cada teléfono debe tener de 6 a 25 caracteres.'], 400);
        }
    }

    try {
        $conexion->beginTransaction();
        $crearFuncionario = $conexion->prepare('INSERT INTO FUNCIONARIO (Nombre) VALUES (:nombre)');
        $crearFuncionario->execute(['nombre' => $nombre]);
        $idFuncionario = (int)$conexion->lastInsertId();
        $perfilSql = [
            'chofer' => ['INSERT INTO CHOFER (IdFuncionarioFK, TipoLicencia) VALUES (:id, :valor)', textoEntrada($datos, 'TipoLicencia')],
            'enfermero' => ['INSERT INTO ENFERMERO (IdFuncionarioFK, Especialidad) VALUES (:id, :valor)', textoEntrada($datos, 'Especialidad')],
            'medico' => ['INSERT INTO MEDICO (IdFuncionarioFK) VALUES (:id)', null],
        ];
        foreach ($perfiles as $perfil) {
            [$sql, $valor] = $perfilSql[$perfil];
            if (in_array($perfil, ['chofer', 'enfermero'], true) && ($valor === null || $valor === '' || mb_strlen($valor, 'UTF-8') > ($perfil === 'chofer' ? 30 : 120))) {
                throw new DomainException('Complete los datos requeridos del perfil seleccionado.');
            }
            $insertarPerfil = $conexion->prepare($sql);
            $parametros = ['id' => $idFuncionario];
            if ($valor !== null) {
                $parametros['valor'] = $valor;
            }
            $insertarPerfil->execute($parametros);
        }
        $insertarTelefono = $conexion->prepare(
            'INSERT INTO TELEFONOS (IdFuncionarioFK, Telefono) VALUES (:id, :telefono)'
        );
        foreach ($telefonos as $telefono) {
            $telefono = trim($telefono);
            if ($telefono !== '') {
                $insertarTelefono->execute(['id' => $idFuncionario, 'telefono' => $telefono]);
            }
        }
        $conexion->commit();
        responder(['success' => true, 'IdFuncionario' => $idFuncionario], 201);
    } catch (DomainException $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responder(['success' => false, 'message' => $error->getMessage()], 400);
    } catch (PDOException $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responder(['success' => false, 'message' => $error->getCode() === '23000'
            ? 'Hay un dato relacionado duplicado.'
            : 'No se pudo registrar el personal.'], $error->getCode() === '23000' ? 409 : 500);
    }
}

if ($accion === 'guardar-vehiculo') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $matricula = textoEntrada($datos, 'Matricula');
    $tipo = textoEntrada($datos, 'TipoVehiculo');
    $especializado = booleanoEntrada($datos, 'RequiereEspecializado');
    if ($matricula === null || !preg_match('/^[A-Za-z0-9-]{5,15}$/', $matricula)
        || $tipo === null || $tipo === '' || mb_strlen($tipo, 'UTF-8') > 50
        || $especializado === null
    ) {
        responder(['success' => false, 'message' => 'Revise matrícula, tipo de vehículo y equipamiento especializado.'], 400);
    }
    try {
        $guardar = $conexion->prepare(
            'INSERT INTO VEHICULO (Matricula, TipoVehiculo, RequiereEspecializado)
             VALUES (:matricula, :tipo, :especializado)
             ON DUPLICATE KEY UPDATE TipoVehiculo = VALUES(TipoVehiculo), RequiereEspecializado = VALUES(RequiereEspecializado)'
        );
        $guardar->execute(['matricula' => strtoupper($matricula), 'tipo' => $tipo, 'especializado' => $especializado]);
        responder(['success' => true]);
    } catch (Throwable $error) {
        responder(['success' => false, 'message' => 'No se pudo guardar el vehículo.'], 500);
    }
}

if ($accion === 'crear-ruta') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $origen = textoEntrada($datos, 'PuntoOrigen');
    $destino = textoEntrada($datos, 'PuntoDestino');
    $rutina = booleanoEntrada($datos, 'RutinaBase');
    if ($origen === null || $origen === '' || mb_strlen($origen, 'UTF-8') > 200
        || $destino === null || $destino === '' || mb_strlen($destino, 'UTF-8') > 200
        || $rutina === null
    ) {
        responder(['success' => false, 'message' => 'Complete origen, destino y tipo de ruta.'], 400);
    }
    $guardar = $conexion->prepare(
        'INSERT INTO RUTA (PuntoOrigen, PuntoDestino, RutinaBase) VALUES (:origen, :destino, :rutina)'
    );
    $guardar->execute(['origen' => $origen, 'destino' => $destino, 'rutina' => $rutina]);
    responder(['success' => true, 'IdRuta' => (int)$conexion->lastInsertId()], 201);
}

if ($accion === 'crear-traslado') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $motivo = textoEntrada($datos, 'Motivo');
    $via = textoEntrada($datos, 'ViaSolicitud');
    $tipoElemento = textoEntrada($datos, 'TipoElemento');
    $matricula = textoEntrada($datos, 'MatriculaFK');
    $referencia = textoEntrada($datos, 'NroReferencia');
    $datosPaciente = textoEntrada($datos, 'DatosPaciente');
    $idChofer = enteroEntrada($datos, 'IdChoferFK');
    $idEnfermero = enteroEntrada($datos, 'IdEnfermeroFK', true);
    $idMedico = enteroEntrada($datos, 'IdMedicoFK', true);
    $idRuta = enteroEntrada($datos, 'IdRutaFK');
    $llegadaEstimada = fechaEntrada(textoEntrada($datos, 'HoraLlegadaEst'));

    if ($motivo === null || $motivo === '' || mb_strlen($motivo, 'UTF-8') > 500
        || !in_array($via, ['telefono', 'presencial', 'interno', 'sistema'], true)
        || !in_array($tipoElemento, ['paciente', 'equipamiento', 'insumo'], true)
        || $matricula === null || $idChofer === null || $idRuta === null
        || (($datos['HoraLlegadaEst'] ?? '') !== '' && $llegadaEstimada === null)
        || ($tipoElemento === 'paciente' && ($datosPaciente === null || $datosPaciente === '' || mb_strlen($datosPaciente, 'UTF-8') > 2000))
        || ($tipoElemento !== 'paciente' && $datosPaciente !== null && mb_strlen($datosPaciente, 'UTF-8') > 2000)
        || ($referencia !== null && mb_strlen($referencia, 'UTF-8') > 50)
    ) {
        responder(['success' => false, 'message' => 'Revise motivo, solicitud, vehículo, personal, ruta y datos del elemento.'], 400);
    }

    try {
        $conexion->beginTransaction();
        $idResponsable = responsableTraslado($conexion);
        $bloquearVehiculo = $conexion->prepare('SELECT Matricula, RequiereEspecializado FROM VEHICULO WHERE Matricula = :matricula FOR UPDATE');
        $bloquearVehiculo->execute(['matricula' => $matricula]);
        $vehiculo = $bloquearVehiculo->fetch();
        if (!$vehiculo) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'El vehículo seleccionado no existe.'], 400);
        }
        if ((int)$vehiculo['RequiereEspecializado'] === 1 && $idEnfermero === null && $idMedico === null) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'Este vehículo requiere asignar un enfermero o un médico.'], 400);
        }
        $ocupado = $conexion->prepare(
            "SELECT IdTraslado FROM TRASLADO WHERE MatriculaFK = :matricula
             AND Estado IN ('solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno') LIMIT 1"
        );
        $ocupado->execute(['matricula' => $matricula]);
        if ($ocupado->fetchColumn()) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'El vehículo ya está asignado a un traslado activo.'], 409);
        }

        $crearElemento = $conexion->prepare(
            'INSERT INTO ELEMENTOTRASLADADO (TipoElemento, NroReferencia, DatosPaciente)
             VALUES (:tipo, :referencia, :datos)'
        );
        $crearElemento->execute([
            'tipo' => $tipoElemento,
            'referencia' => $referencia !== '' ? $referencia : null,
            'datos' => $tipoElemento === 'paciente' ? $datosPaciente : null,
        ]);
        $idElemento = (int)$conexion->lastInsertId();
        $crearTraslado = $conexion->prepare(
            "INSERT INTO TRASLADO
             (Motivo, ViaSolicitud, Estado, HoraLlegadaEst, IdChoferFK, IdEnfermeroFK,
              IdAdministrativoFK, IdMedicoFK, MatriculaFK, IdRutaFK, IdElementoFK)
             VALUES (:motivo, :via, 'solicitado', :llegada, :chofer, :enfermero,
                     :administrativo, :medico, :matricula, :ruta, :elemento)"
        );
        $crearTraslado->execute([
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
        $idTraslado = (int)$conexion->lastInsertId();
        $conexion->commit();
        responder(['success' => true, 'IdTraslado' => $idTraslado], 201);
    } catch (PDOException $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responder(['success' => false, 'message' => $error->getCode() === '23000'
            ? 'Una de las relaciones seleccionadas ya no está disponible.'
            : 'No se pudo registrar el traslado.'], $error->getCode() === '23000' ? 409 : 500);
    }
}

if ($accion === 'actualizar-estado') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $id = enteroEntrada($datos, 'IdTraslado');
    $nuevoEstado = textoEntrada($datos, 'Estado');
    if ($id === null) {
        responder(['success' => false, 'message' => 'Identificador de traslado inválido.'], 400);
    }
    $transiciones = [
        'solicitado' => ['pendiente', 'cancelado'],
        'pendiente' => ['en_camino', 'cancelado'],
        'en_camino' => ['en_destino', 'cancelado'],
        'en_destino' => ['en_retorno'],
        'en_retorno' => ['finalizado'],
    ];
    try {
        $conexion->beginTransaction();
        $buscar = $conexion->prepare(
            'SELECT Estado
             FROM TRASLADO WHERE IdTraslado = :id FOR UPDATE'
        );
        $buscar->execute(['id' => $id]);
        $trasladoBloqueado = $buscar->fetch();
        if (!$trasladoBloqueado) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'Traslado no encontrado.'], 404);
        }
        $estadoActual = $trasladoBloqueado['Estado'];
        if (!in_array($nuevoEstado, $transiciones[$estadoActual] ?? [], true)) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'La transición de estado no está permitida.'], 409);
        }
        $actualizar = $conexion->prepare(
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
        $conexion->commit();
        responder(['success' => true, 'Estado' => $nuevoEstado]);
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responder(['success' => false, 'message' => 'No se pudo actualizar el traslado.'], 500);
    }
}

    responder(['success' => false, 'message' => 'Acción no válida.'], 400);
