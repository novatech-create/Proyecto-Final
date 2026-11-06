<?php
declare(strict_types=1);

require_once __DIR__ . '/ResultadoOperacion.php';
require_once __DIR__ . '/../datos/EncuestaDAO.php';
require_once __DIR__ . '/../datos/DocumentoDAO.php';

final class EncuestaBO
{
    private const OPCIONES_CALIFICACION = ['Excelente', 'Buena', 'Regular', 'Mala'];
    private const OPCIONES_COMPRENSION = ['Si', 'Parcialmente', 'No'];
    private const OPCIONES_UTILIDAD = ['Muy util', 'Util', 'Poco util', 'Nada util'];
    private const OPCIONES_SERVICIO = ['Cardiología', 'Nefrología', 'Trasplante', 'Imagenología', 'Neurología', 'Urología'];

    public function ejecutar(string $accion, array $datos): array
    {
        $dao = new EncuestaDAO();

        switch ($accion) {

            // Envío anónimo desde Encuesta.html
            case 'create':
                // Solo se permite recibir datos por POST

                // Lee el JSON enviado desde el frontend y lo transforma en un array asociativo
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
                if (!in_array($servicio, self::OPCIONES_SERVICIO, true)
                    || !in_array($calificacion, self::OPCIONES_CALIFICACION, true)
                    || !in_array($datos['datosSatisfaccion'] ?? null, ['1', '2', '3', '4', '5', 1, 2, 3, 4, 5], true)
                    || $datosSatisfaccion < 1 || $datosSatisfaccion > 5
                    || !in_array($comprension, self::OPCIONES_COMPRENSION, true)
                    || !in_array($utilidad, self::OPCIONES_UTILIDAD, true)
                    || ($documentoId !== null && $documentoId <= 0)
                    || mb_strlen($comentario, 'UTF-8') > 2000
                ) {
                    return resultadoOperacion(['success' => false, 'message' => 'Complete todas las preguntas obligatorias.'], 400);
                }

                // Intenta guardar la encuesta en la base de datos
                try {
                    if ($documentoId !== null && !(new DocumentoDAO())->existeAprobado($documentoId)) {
                        return resultadoOperacion(['success' => false, 'message' => 'El documento indicado no existe o no está activo.'], 400);
                    }
                    $dao->guardar([
                        'documento_id' => $documentoId,
                        'servicio' => $servicio,
                        'calificacion_info' => $calificacion,
                        'datosSatisfaccion' => $datosSatisfaccion,
                        'comprension' => $comprension,
                        'utilidad' => $utilidad,
                        'comentario' => $comentario !== '' ? $comentario : null,
                    ]);

                    // Si todo salió bien, responde éxito con código 201 (Created)
                    return resultadoOperacion(['success' => true], 201);
                } catch (Throwable $error) {
                    // Si falla, responde un error del servidor
                    return resultadoOperacion(['success' => false, 'message' => 'No fue posible enviar la encuesta.'], 500);
                }

            // Listado y estadísticas para "Respuestas de Encuestas"
            case 'getall':
                // Solo los funcionarios pueden consultar las respuestas.
                try {

                    // Obtiene el detalle y las estadísticas desde vistas de reporte.
                    $listado = $dao->listar();
                    $resumenServicios = $dao->resumen();

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
                    return resultadoOperacion([
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
                    return resultadoOperacion(['success' => false, 'message' => 'No fue posible consultar las encuestas.'], 500);
                }

            // Si la acción no existe, devuelve error.
            default:
                return resultadoOperacion(['success' => false, 'message' => 'Acción no válida.'], 400);
        }

    }
}
