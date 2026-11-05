<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

exigirRol(['administrador', 'medico']);
$administrador = $_SESSION['rol'] === 'administrador';

try {
    $conexion = obtenerConexion();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = $administrador
            ? "SELECT id, documento, nombre FROM usuarios WHERE rol = 'funcionario' AND activo = 1 ORDER BY nombre"
            : "SELECT id, titulo, descripcion, especialista FROM documentos
               WHERE activo = 1 AND estado_revision = 'pendiente' ORDER BY id";
        responder(['success' => true, 'data' => $conexion->query($sql)->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['success' => false, 'message' => 'Método no permitido.'], 405);
    }

    $datos = cuerpoPeticion();
    $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        responder(['success' => false, 'message' => 'Identificador inválido.'], 400);
    }

    if ($administrador) {
        $consulta = $conexion->prepare(
            "UPDATE usuarios SET activo = 0 WHERE id = :id AND rol = 'funcionario' AND activo = 1"
        );
        $consulta->execute(['id' => $id]);
    } else {
        $estado = $datos['estado'] ?? '';
        $motivo = is_string($datos['motivo'] ?? null) ? trim($datos['motivo']) : '';
        if (!in_array($estado, ['aprobado', 'rechazado'], true)
            || ($estado === 'rechazado' && ($motivo === '' || mb_strlen($motivo) > 500))) {
            responder(['success' => false, 'message' => 'Indique una decisión y un motivo válido si rechaza.'], 400);
        }

        $consulta = $conexion->prepare(
            "UPDATE documentos
             SET estado_revision = :estado, revisado_por = :revisor, revisado_en = NOW(), motivo_rechazo = :motivo
             WHERE id = :id AND activo = 1 AND estado_revision = 'pendiente' AND (archivo_url <> '' OR :permite_sin_archivo = 1)"
        );
        $consulta->execute([
            'id' => $id,
            'estado' => $estado,
            'permite_sin_archivo' => $estado === 'rechazado' ? 1 : 0,
            'revisor' => $_SESSION['usuario_id'],
            'motivo' => $estado === 'rechazado' ? $motivo : null,
        ]);
    }

    if ($consulta->rowCount() !== 1) {
        responder(['success' => false, 'message' => 'El registro no está disponible para esta operación.'], 409);
    }
    responder(['success' => true]);
} catch (Throwable $error) {
    responder(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
}
