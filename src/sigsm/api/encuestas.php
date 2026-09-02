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

switch ($accion) {

    // Envío anónimo desde Encuesta.html
    case 'create':
        // Solo se permite recibir datos por POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        // Lee el JSON enviado desde el frontend y lo transforma en un array asociativo
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

        // Valida todos los campos obligatorios antes de guardar la encuesta
        // También se comprueba que las opciones elegidas estén dentro de los valores permitidos
        if ($servicio === '' || !in_array($calificacion, OPCIONES_CALIFICACION, true) || $datosSatisfaccion < 1 || $datosSatisfaccion > 5
            || !in_array($comprension, OPCIONES_COMPRENSION, true) || !in_array($utilidad, OPCIONES_UTILIDAD, true)
        ) {
            responder(['success' => false, 'message' => 'Complete todas las preguntas obligatorias.'], 400);
        }

        // Intenta guardar la encuesta en la base de datos
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

            // Trae todas las encuestas ordenadas por fecha más reciente.
            $listado = $conexion->query(
                'SELECT servicio, calificacion_info, datosSatisfaccion, comentario, creado_en
                 FROM encuestas
                 ORDER BY creado_en DESC'
            )->fetchAll();

            $total = count($listado);

            // Calcula el promedio general usando la nota numérica guardada en la BD.
            $suma = 0;
            $conteoServicios = [];
            foreach ($listado as $fila) {
                $suma += (int)$fila['datosSatisfaccion'];
                $servicio = $fila['servicio'];
                $conteoServicios[$servicio] = ($conteoServicios[$servicio] ?? 0) + 1;
            }
            $promedio = $total > 0 ? round($suma / $total, 1) : 0;

            // Busca cuál servicio fue el más consultado.
            $servicioTop = null;
            $maxConteo = 0;
            foreach ($conteoServicios as $servicio => $conteo) {
                if ($conteo > $maxConteo) {
                    $maxConteo = $conteo;
                    $servicioTop = $servicio;
                }
            }

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
