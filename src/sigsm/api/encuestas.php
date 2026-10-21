<?php
declare(strict_types=1);

// Este archivo maneja la creación y consulta de encuestas
require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

// Lee la acción que viene desde el navegador.
// Por ejemplo: ?action=create o ?action=getall
$accion = $_GET['action'] ?? $_POST['action'] ?? '';

// Opciones válidas para cada pregunta de la encuesta
// Sirven para validar que los datos enviados por el frontend estén dentro de lo permitido (Lo cuál deberían ya que son li)
const OPCIONES_CALIFICACION = ['Excelente', 'Buena', 'Regular', 'Mala'];
const OPCIONES_COMPRENSION = ['Si', 'Parcialmente', 'No'];
const OPCIONES_UTILIDAD = ['Muy util', 'Util', 'Poco util', 'Nada util'];
const OPCIONES_SERVICIO = ['Cardiología', 'Nefrología', 'Trasplante', 'Imagenología', 'Neurología', 'Urología'];

switch ($accion) {

    // Envío anónimo desde Encuesta.html
    case 'create':
        // Solo se permite recibir datos por POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        // Lee el JSON enviado desde el frontend y lo transforma en un array asociativo
        $datos = cuerpoPeticion();
        $servicio = is_string($datos['servicio'] ?? null) ? trim($datos['servicio']) : '';
        $calificacion = is_string($datos['calificacion_info'] ?? null) ? $datos['calificacion_info'] : '';
        $satisfaccionRecibida = $datos['datosSatisfaccion'] ?? null;
        $datosSatisfaccion = is_string($satisfaccionRecibida) || is_int($satisfaccionRecibida)
            ? (int)$satisfaccionRecibida : 0;
        $comprension = is_string($datos['comprension'] ?? null) ? $datos['comprension'] : '';
        $utilidad = is_string($datos['utilidad'] ?? null) ? $datos['utilidad'] : '';
        $comentario = is_string($datos['comentario'] ?? null) ? trim($datos['comentario']) : '';
        $documentoRecibido = $datos['documento_id'] ?? '';
        $documentoId = $documentoRecibido === '' ? null
            : ((is_string($documentoRecibido) || is_int($documentoRecibido))
                ? filter_var($documentoRecibido, FILTER_VALIDATE_INT) : false);
        $documentoId = $documentoId === false || ($documentoId !== null && $documentoId <= 0)
            ? 0 : $documentoId;

        // Valida todos los campos obligatorios antes de guardar la encuesta
        // También se comprueba que las opciones elegidas estén dentro de los valores permitidos
        if (!in_array($servicio, OPCIONES_SERVICIO, true)
            || !in_array($calificacion, OPCIONES_CALIFICACION, true)
            || !in_array($datos['datosSatisfaccion'] ?? null, ['1', '2', '3', '4', '5', 1, 2, 3, 4, 5], true)
            || $datosSatisfaccion < 1 || $datosSatisfaccion > 5
            || !in_array($comprension, OPCIONES_COMPRENSION, true)
            || !in_array($utilidad, OPCIONES_UTILIDAD, true)
            || ($documentoId !== null && $documentoId <= 0)
            || mb_strlen($comentario, 'UTF-8') > 2000
        ) {
            responder(['success' => false, 'message' => 'Complete todas las preguntas obligatorias.'], 400);
        }

        // Intenta guardar la encuesta en la base de datos
        try {
            $conexion = obtenerConexion();
            if ($documentoId !== null) {
                $documentoExiste = $conexion->prepare(
                    'SELECT 1 FROM vw_documentos_activos WHERE id = :id LIMIT 1'
                );
                $documentoExiste->execute(['id' => $documentoId]);
                if (!$documentoExiste->fetchColumn()) {
                    responder(['success' => false, 'message' => 'El documento indicado no existe o no está activo.'], 400);
                }
            }
            $consulta = $conexion->prepare(
            'INSERT INTO encuestas (documento_id, servicio, calificacion_info, datosSatisfaccion, comprension, utilidad, comentario)
             VALUES (:documento_id, :servicio, :calificacion_info, :datosSatisfaccion, :comprension, :utilidad, :comentario)'
            );
            $consulta->execute([
                'documento_id' => $documentoId,
                'servicio' => $servicio,
                'calificacion_info' => $calificacion,
                'datosSatisfaccion' => $datosSatisfaccion,
                'comprension' => $comprension,
                'utilidad' => $utilidad,
                'comentario' => $comentario !== '' ? $comentario : null,
            ]);

            // Si todo salió bien, responde éxito con código 201 (Created)
            responder(['success' => true], 201);
        } catch (Throwable $error) {
            // Si falla, responde un error del servidor
            responder(['success' => false, 'message' => 'No fue posible enviar la encuesta.'], 500);
        }
        break;

    // Listado y estadísticas para "Respuestas de Encuestas"
    case 'getall':
        // Solo los funcionarios pueden consultar las respuestas.
        exigirRol(['funcionario']);
        try {
            $conexion = obtenerConexion();

            // Obtiene el detalle y las estadísticas desde vistas de reporte.
            $listado = $conexion->query(
                'SELECT servicio, calificacion_info, datosSatisfaccion, comentario, creado_en
                 FROM vw_encuestas_detalle
                 ORDER BY creado_en DESC'
            )->fetchAll();

            $resumenServicios = $conexion->query(
                'SELECT servicio, total_respuestas, suma_satisfaccion
                 FROM vw_encuestas_resumen'
            )->fetchAll();

            $total = 0;
            $suma = 0;
            $servicioTop = null;
            $maxConteo = 0;
            foreach ($resumenServicios as $fila) {
                $conteo = (int)$fila['total_respuestas'];
                $total += $conteo;
                $suma += (int)$fila['suma_satisfaccion'];
                if ($conteo > $maxConteo) {
                    $maxConteo = $conteo;
                    $servicioTop = $fila['servicio'];
                }
            }
            $promedio = $total > 0 ? round($suma / $total, 1) : 0;

            // Devuelve tanto el listado como las estadísticas.
            responder([
                'success' => true,
                'estadisticas' => [
                    'total' => $total,
                    'promedio' => $promedio,
                    'servicio_mas_consultado' => $servicioTop,
                ],
                'data' => $listado,
            ]);
        } catch (Throwable $error) {
            // Error al consultar las encuestas.
            responder(['success' => false, 'message' => 'No fue posible consultar las encuestas.'], 500);
        }
        break;

    // Si la acción no existe, devuelve error.
    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
