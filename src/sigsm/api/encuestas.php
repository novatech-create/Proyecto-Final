<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';

const OPCIONES_CALIFICACION = ['Excelente', 'Buena', 'Regular', 'Mala'];
const OPCIONES_COMPRENSION = ['Si', 'Parcialmente', 'No'];
const OPCIONES_UTILIDAD = ['Muy util', 'Util', 'Poco util', 'Nada util'];

switch ($accion) {

    // POST ?action=create  { servicio, calificacion_info, datosSatisfaccion, comprension, utilidad, comentario, documento_id? }
    // Envío anónimo desde Encuesta.html
    case 'create':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        $datos = cuerpoPeticion();
        $servicio = trim((string)($datos['servicio'] ?? ''));
        $calificacion = (string)($datos['calificacion_info'] ?? '');
        $datosSatisfaccion = (int)($datos['datosSatisfaccion'] ?? 0);
        $comprension = (string)($datos['comprension'] ?? '');
        $utilidad = (string)($datos['utilidad'] ?? '');
        $comentario = trim((string)($datos['comentario'] ?? ''));
        $documentoId = isset($datos['documento_id']) && $datos['documento_id'] !== ''
            ? (int)$datos['documento_id']
            : null;

        if ($servicio === ''
            || !in_array($calificacion, OPCIONES_CALIFICACION, true)
            || $datosSatisfaccion < 1
            || $datosSatisfaccion > 5
            || !in_array($comprension, OPCIONES_COMPRENSION, true)
            || !in_array($utilidad, OPCIONES_UTILIDAD, true)
        ) {
            responder(['success' => false, 'message' => 'Complete todas las preguntas obligatorias.'], 400);
        }

        try {
            $conexion = obtenerConexion();
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

            responder(['success' => true], 201);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible enviar la encuesta.'], 500);
        }
        break;

    // GET ?action=getall -> listado y estadísticas para "Respuestas de Encuestas"
    case 'getall':
        exigirRol(['funcionario']);
        try {
            $conexion = obtenerConexion();

            $listado = $conexion->query(
                'SELECT servicio, calificacion_info, datosSatisfaccion, comentario, creado_en
                 FROM encuestas
                 ORDER BY creado_en DESC'
            )->fetchAll();

            $total = count($listado);

            // Promedio usando la escala numérica almacenada en la BD.
            $suma = 0;
            $conteoServicios = [];
            foreach ($listado as $fila) {
                $suma += (int)$fila['datosSatisfaccion'];
                $servicio = $fila['servicio'];
                $conteoServicios[$servicio] = ($conteoServicios[$servicio] ?? 0) + 1;
            }
            $promedio = $total > 0 ? round($suma / $total, 1) : 0;

            $servicioTop = null;
            $maxConteo = 0;
            foreach ($conteoServicios as $servicio => $conteo) {
                if ($conteo > $maxConteo) {
                    $maxConteo = $conteo;
                    $servicioTop = $servicio;
                }
            }

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
            responder(['success' => false, 'message' => 'No fue posible consultar las encuestas.'], 500);
        }
        break;

    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
