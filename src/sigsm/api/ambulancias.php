<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

exigirRol(['funcionario']);
$conexion = obtenerConexion();
$accion = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';

function funcionarioActual(PDO $conexion): ?array
{
    $consulta = $conexion->prepare(
        'SELECT f.IdFuncionario, f.Nombre, fc.IdUsuarioFK,
            EXISTS(SELECT 1 FROM ADMINISTRATIVO a WHERE a.IdFuncionarioFK = f.IdFuncionario) AS EsAdministrativo,
            EXISTS(SELECT 1 FROM CHOFER c WHERE c.IdFuncionarioFK = f.IdFuncionario) AS EsChofer,
            EXISTS(SELECT 1 FROM ENFERMERO e WHERE e.IdFuncionarioFK = f.IdFuncionario) AS EsEnfermero,
            EXISTS(SELECT 1 FROM MEDICO m WHERE m.IdFuncionarioFK = f.IdFuncionario) AS EsMedico
         FROM FUNCIONARIO_CUENTA fc
         JOIN FUNCIONARIO f ON f.IdFuncionario = fc.IdFuncionarioFK
         JOIN usuarios u ON u.id = fc.IdUsuarioFK
         WHERE fc.IdUsuarioFK = :usuario AND u.activo = 1
         LIMIT 1'
    );
    $consulta->execute(['usuario' => $_SESSION['usuario_id']]);
    $funcionario = $consulta->fetch();
    return $funcionario ?: null;
}

function exigirFuncionario(PDO $conexion): array
{
    $funcionario = funcionarioActual($conexion);
    if (!$funcionario) {
        responder(['success' => false, 'message' => 'La cuenta todavía no está vinculada a un perfil de funcionario.'], 403);
    }
    return $funcionario;
}

function exigirAdministrativo(PDO $conexion): array
{
    $funcionario = exigirFuncionario($conexion);
    if ((int)$funcionario['EsAdministrativo'] !== 1) {
        responder(['success' => false, 'message' => 'Esta acción requiere perfil administrativo.'], 403);
    }
    return $funcionario;
}

function esPersonalAsignado(array $traslado, array $funcionario): bool
{
    $id = (int)$funcionario['IdFuncionario'];
    return ((int)$funcionario['EsChofer'] === 1 && (int)$traslado['IdChoferFK'] === $id)
        || ((int)$funcionario['EsEnfermero'] === 1 && (int)($traslado['IdEnfermeroFK'] ?? 0) === $id)
        || ((int)$funcionario['EsMedico'] === 1 && (int)($traslado['IdMedicoFK'] ?? 0) === $id);
}

function filtrarTrasladosPorPerfil(array $traslados, array $funcionario): array
{
    if ((int)$funcionario['EsAdministrativo'] === 1) {
        return $traslados;
    }
    return array_values(array_filter(
        $traslados,
        static fn(array $traslado): bool => esPersonalAsignado($traslado, $funcionario),
    ));
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

if ($accion === 'estado-inicial') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(['success' => false, 'message' => 'Usa GET.'], 405);
    }
    $cuentaAdministrativa = (int)$conexion->query(
        'SELECT COUNT(*) FROM ADMINISTRATIVO'
    )->fetchColumn();
    $funcionario = funcionarioActual($conexion);
    responder([
        'success' => true,
        'hayAdministrativo' => $cuentaAdministrativa > 0,
        'vinculado' => $funcionario !== null,
        'puedeInicializar' => $cuentaAdministrativa === 0,
        'nombreUsuario' => $_SESSION['nombre'] ?? '',
    ]);
}

if ($accion === 'inicializar-administrativo') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $bloqueoInicial = (int)$conexion->query("SELECT GET_LOCK('sigsm_ambulancias_admin_inicial', 10)")->fetchColumn();
    if ($bloqueoInicial !== 1) {
        responder(['success' => false, 'message' => 'No se pudo reservar la configuración inicial; inténtelo nuevamente.'], 503);
    }
    try {
        $cantidadAdmin = (int)$conexion->query('SELECT COUNT(*) FROM ADMINISTRATIVO')->fetchColumn();
        if ($cantidadAdmin > 0) {
            responder(['success' => false, 'message' => 'La administración inicial ya fue configurada.'], 409);
        }
        $yaVinculado = funcionarioActual($conexion);
        if ($yaVinculado) {
            responder(['success' => false, 'message' => 'La cuenta ya tiene un perfil asignado.'], 409);
        }

        $conexion->beginTransaction();
        $nombre = trim((string)($_SESSION['nombre'] ?? 'Administrador inicial'));
        $crearFuncionario = $conexion->prepare('INSERT INTO FUNCIONARIO (Nombre) VALUES (:nombre)');
        $crearFuncionario->execute(['nombre' => $nombre]);
        $idFuncionario = (int)$conexion->lastInsertId();
        $vincular = $conexion->prepare(
            'INSERT INTO FUNCIONARIO_CUENTA (IdFuncionarioFK, IdUsuarioFK) VALUES (:funcionario, :usuario)'
        );
        $vincular->execute(['funcionario' => $idFuncionario, 'usuario' => $_SESSION['usuario_id']]);
        $crearAdmin = $conexion->prepare('INSERT INTO ADMINISTRATIVO (IdFuncionarioFK) VALUES (:funcionario)');
        $crearAdmin->execute(['funcionario' => $idFuncionario]);
        $conexion->commit();
        responder(['success' => true, 'mensaje' => 'Cuenta configurada como administrador del módulo.']);
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responder(['success' => false, 'message' => 'No se pudo configurar la administración inicial.'], 500);
    } finally {
        $conexion->query("SELECT RELEASE_LOCK('sigsm_ambulancias_admin_inicial')");
    }
}

$funcionario = exigirFuncionario($conexion);

if ($accion === 'catalogos') {
    exigirAdministrativo($conexion);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(['success' => false, 'message' => 'Usa GET.'], 405);
    }
    responder(['success' => true, 'EsAdministrativo' => (bool)$funcionario['EsAdministrativo']] + obtenerCatalogos($conexion));
}

if ($accion === 'resumen' || $accion === 'activos' || $accion === 'historial') {
    $funcionario = exigirFuncionario($conexion);
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
                administrativo.Nombre AS NombreAdministrativo, medico.Nombre AS NombreMedico
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
    $traslados = filtrarTrasladosPorPerfil($consulta->fetchAll(), $funcionario);
    $estadosActivos = ['solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno'];
    $activos = array_values(array_filter($traslados, static fn(array $item): bool => in_array($item['Estado'], $estadosActivos, true)));

    if ($accion === 'activos') {
        responder([
            'success' => true,
            'EsAdministrativo' => (bool)$funcionario['EsAdministrativo'],
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
    if ((int)$funcionario['EsAdministrativo'] !== 1) {
        $matriculasAsignadas = array_unique(array_column($activos, 'MatriculaFK'));
        $vehiculos = array_values(array_filter(
            $vehiculos,
            static fn(array $vehiculo): bool => in_array($vehiculo['Matricula'], $matriculasAsignadas, true),
        ));
    }
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
        'EsAdministrativo' => (bool)$funcionario['EsAdministrativo'],
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
    exigirAdministrativo($conexion);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        responder(['success' => false, 'message' => 'Usa GET.'], 405);
    }
    $personal = $conexion->query(
        'SELECT f.IdFuncionario, f.Nombre, u.id AS IdUsuario, u.documento AS Documento,
                u.activo AS Activo,
                EXISTS(SELECT 1 FROM CHOFER c WHERE c.IdFuncionarioFK = f.IdFuncionario) AS EsChofer,
                EXISTS(SELECT 1 FROM ENFERMERO e WHERE e.IdFuncionarioFK = f.IdFuncionario) AS EsEnfermero,
                EXISTS(SELECT 1 FROM ADMINISTRATIVO a WHERE a.IdFuncionarioFK = f.IdFuncionario) AS EsAdministrativo,
                EXISTS(SELECT 1 FROM MEDICO m WHERE m.IdFuncionarioFK = f.IdFuncionario) AS EsMedico
         FROM FUNCIONARIO f
         JOIN FUNCIONARIO_CUENTA fc ON fc.IdFuncionarioFK = f.IdFuncionario
         JOIN usuarios u ON u.id = fc.IdUsuarioFK
         ORDER BY f.Nombre'
    )->fetchAll();
    responder(['success' => true, 'Personal' => $personal] + obtenerCatalogos($conexion));
}

if ($accion === 'crear-personal') {
    exigirAdministrativo($conexion);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $documento = textoEntrada($datos, 'Documento');
    $nombre = textoEntrada($datos, 'Nombre');
    $clave = $datos['Clave'] ?? null;
    $perfiles = $datos['Perfiles'] ?? null;
    $telefonos = $datos['Telefonos'] ?? [];
    if ($documento === null || !preg_match('/^[0-9]{1,20}$/', $documento)
        || $nombre === null || $nombre === '' || mb_strlen($nombre, 'UTF-8') > 150
        || !is_string($clave) || mb_strlen($clave, 'UTF-8') < 10 || strlen($clave) > 72
        || !is_array($perfiles) || !$perfiles
        || !is_array($telefonos)
    ) {
        responder(['success' => false, 'message' => 'Revise documento numérico, nombre (máximo 150), clave (10 a 72 caracteres), perfiles y lista de teléfonos.'], 400);
    }
    $perfilesPermitidos = ['chofer', 'enfermero', 'administrativo', 'medico'];
    $perfiles = array_values(array_unique($perfiles));
    if (array_diff($perfiles, $perfilesPermitidos)) {
        responder(['success' => false, 'message' => 'Se recibió un perfil no permitido.'], 400);
    }
    foreach ($telefonos as $telefono) {
        if (!is_string($telefono) || strlen(trim($telefono)) < 6 || strlen(trim($telefono)) > 25) {
            responder(['success' => false, 'message' => 'Cada teléfono debe tener de 6 a 25 caracteres.'], 400);
        }
    }

    try {
        $conexion->beginTransaction();
        $crearUsuario = $conexion->prepare(
            'INSERT INTO usuarios (documento, nombre, clave, rol, activo)
             VALUES (:documento, :nombre, :clave, :rol, 1)'
        );
        $crearUsuario->execute([
            'documento' => $documento,
            'nombre' => $nombre,
            'clave' => password_hash($clave, PASSWORD_DEFAULT),
            'rol' => 'funcionario',
        ]);
        $idUsuario = (int)$conexion->lastInsertId();
        $crearFuncionario = $conexion->prepare('INSERT INTO FUNCIONARIO (Nombre) VALUES (:nombre)');
        $crearFuncionario->execute(['nombre' => $nombre]);
        $idFuncionario = (int)$conexion->lastInsertId();
        $vincular = $conexion->prepare(
            'INSERT INTO FUNCIONARIO_CUENTA (IdFuncionarioFK, IdUsuarioFK) VALUES (:funcionario, :usuario)'
        );
        $vincular->execute(['funcionario' => $idFuncionario, 'usuario' => $idUsuario]);

        $perfilSql = [
            'chofer' => ['INSERT INTO CHOFER (IdFuncionarioFK, TipoLicencia) VALUES (:id, :valor)', textoEntrada($datos, 'TipoLicencia')],
            'enfermero' => ['INSERT INTO ENFERMERO (IdFuncionarioFK, Especialidad) VALUES (:id, :valor)', textoEntrada($datos, 'Especialidad')],
            'administrativo' => ['INSERT INTO ADMINISTRATIVO (IdFuncionarioFK) VALUES (:id)', null],
            'medico' => ['INSERT INTO MEDICO (IdFuncionarioFK) VALUES (:id)', null],
        ];
        foreach ($perfiles as $perfil) {
            [$sql, $valor] = $perfilSql[$perfil];
            if (in_array($perfil, ['chofer', 'enfermero'], true) && ($valor === null || $valor === '' || mb_strlen($valor, 'UTF-8') > 120)) {
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
        responder(['success' => true, 'IdFuncionario' => $idFuncionario, 'IdUsuario' => $idUsuario], 201);
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
            ? 'El documento ya existe o hay un dato relacionado duplicado.'
            : 'No se pudo crear el usuario y sus perfiles.'], $error->getCode() === '23000' ? 409 : 500);
    }
}

if ($accion === 'desactivar-personal') {
    exigirAdministrativo($conexion);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Usa POST.'], 405);
    }
    $datos = cuerpoPeticion();
    $idUsuario = enteroEntrada($datos, 'IdUsuario');
    if ($idUsuario === null || $idUsuario === (int)$_SESSION['usuario_id']) {
        responder(['success' => false, 'message' => 'No puede desactivar esta cuenta.'], 400);
    }
    $esAdmin = $conexion->prepare(
        'SELECT EXISTS(
            SELECT 1 FROM FUNCIONARIO_CUENTA fc
            JOIN ADMINISTRATIVO a ON a.IdFuncionarioFK = fc.IdFuncionarioFK
            WHERE fc.IdUsuarioFK = :usuario
        )'
    );
    $esAdmin->execute(['usuario' => $idUsuario]);
    if ((int)$esAdmin->fetchColumn() === 1) {
        $adminsActivos = (int)$conexion->query(
            'SELECT COUNT(*) FROM ADMINISTRATIVO a
             JOIN FUNCIONARIO_CUENTA fc ON fc.IdFuncionarioFK = a.IdFuncionarioFK
             JOIN usuarios u ON u.id = fc.IdUsuarioFK
             WHERE u.activo = 1'
        )->fetchColumn();
        if ($adminsActivos <= 1) {
            responder(['success' => false, 'message' => 'Debe quedar al menos una cuenta administrativa activa.'], 409);
        }
    }
    $consulta = $conexion->prepare(
        'UPDATE usuarios SET activo = 0 WHERE id = :id AND EXISTS (
            SELECT 1 FROM FUNCIONARIO_CUENTA fc
            JOIN ADMINISTRATIVO a ON a.IdFuncionarioFK = fc.IdFuncionarioFK
            WHERE fc.IdUsuarioFK = :id_admin
        ) AND EXISTS (
            SELECT 1 FROM FUNCIONARIO_CUENTA objetivo
            WHERE objetivo.IdUsuarioFK = :id_vinculado
        )'
    );
    $consulta->execute([
        'id' => $idUsuario,
        'id_admin' => $_SESSION['usuario_id'],
        'id_vinculado' => $idUsuario,
    ]);
    responder(['success' => $consulta->rowCount() > 0]);
}

if ($accion === 'guardar-vehiculo') {
    exigirAdministrativo($conexion);
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
    exigirAdministrativo($conexion);
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
    exigirAdministrativo($conexion);
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
            'administrativo' => $funcionario['IdFuncionario'],
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
    $funcionario = exigirFuncionario($conexion);
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
            'SELECT Estado, IdChoferFK, IdEnfermeroFK, IdMedicoFK
             FROM TRASLADO WHERE IdTraslado = :id FOR UPDATE'
        );
        $buscar->execute(['id' => $id]);
        $trasladoBloqueado = $buscar->fetch();
        if (!$trasladoBloqueado) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'Traslado no encontrado.'], 404);
        }
        $esChoferAsignado = (int)$funcionario['EsChofer'] === 1
            && (int)$trasladoBloqueado['IdChoferFK'] === (int)$funcionario['IdFuncionario'];
        if ((int)$funcionario['EsAdministrativo'] !== 1 && !$esChoferAsignado) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'Solo el administrativo o el conductor asignado puede actualizar el estado.'], 403);
        }
        $estadoActual = $trasladoBloqueado['Estado'];
        if (!in_array($nuevoEstado, $transiciones[$estadoActual] ?? [], true)) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'La transición de estado no está permitida.'], 409);
        }
        if ((int)$funcionario['EsAdministrativo'] !== 1
            && (in_array($nuevoEstado, ['cancelado', 'pendiente'], true) || $estadoActual === 'solicitado')
        ) {
            $conexion->rollBack();
            responder(['success' => false, 'message' => 'Aceptar o cancelar solicitudes requiere perfil administrativo.'], 403);
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
