<?php
declare(strict_types=1);

require_once __DIR__ . '/ResultadoOperacion.php';
require_once __DIR__ . '/../datos/DocumentoDAO.php';
require_once __DIR__ . '/pdf_documento.php';

final class DocumentoBO
{
    public function ejecutar(string $accion, array $datos, mixed $idConsulta, array $archivo, int $usuarioId): array
    {
        $dao = new DocumentoDAO();

        switch ($accion) {

            // Esto es para obtener todos los documentos activos, agrupados por categoría
            case 'gestion':
            case 'getall':
                try {
                    $filas = $dao->listar($accion === 'getall');

                    // Esto agrupa los documentos por categoría en un array de categorías con sus documentos
                    $categorias = [];
                    foreach ($filas as $fila) {
                        $idCategoria = $fila['categoria_id'];
                        if (!isset($categorias[$idCategoria])) {
                            $categorias[$idCategoria] = [
                                'id' => $idCategoria,
                                'nombre' => $fila['categoria_nombre'],
                                'documentos' => [],
                            ];
                        }
                        if ($fila['id'] === null) {
                            continue;
                        }
                        $categorias[$idCategoria]['documentos'][] = [
                            'id' => $fila['id'],
                            'titulo' => $fila['titulo'],
                            'estado_revision' => $fila['estado_revision'],
                            'motivo_rechazo' => $fila['motivo_rechazo'],
                            'puede_reenviar' => $accion === 'gestion' && $fila['estado_revision'] === 'rechazado'
                                && (int)$fila['creado_por'] === (int)$usuarioId,
                            'descripcion' => $fila['descripcion'],
                            'especialista' => $fila['especialista'],
                            'archivo_url' => $fila['archivo_url'] === '' ? '' : '/sigsm/api/archivo_documento.php?id=' . $fila['id'],
                            'archivo_origen' => $accion === 'gestion' ? $fila['archivo_url'] : null,
                            'peso_kb' => $fila['peso_kb'],
                            'fecha_actualizacion' => $fila['fecha_actualizacion'],
                            'categoria_nombre' => $fila['categoria_nombre'],
                        ];
                    }

                    // Devuelve el array de categorías con sus documentos como respuesta JSON
                    return resultadoOperacion(['success' => true, 'data' => array_values($categorias)]);
                } catch (Throwable $error) {
                    return resultadoOperacion(['success' => false, 'message' => 'No fue posible consultar los documentos.'], 500);
                }

            // Es para devolver un único documento según su id
            case 'vista_interna':
            case 'get':

                // Toma el id del navegador y la pasa a int
                $idRecibido = $idConsulta;
                $id = is_string($idRecibido) ? filter_var($idRecibido, FILTER_VALIDATE_INT) : false;
                $id = $id !== false && $id > 0 ? $id : 0;
                
                // Si el id es menor o igual a 0, devuelve un error 400 (Bad Request)
                if ($id <= 0) {
                    return resultadoOperacion(['success' => false, 'message' => 'Falta el id del documento.'], 400);
                }
                try {

                // Busca un documento activo por su id, junto con su categoría
                    $documento = $dao->buscar($id, $accion === 'get');

                    // Si no existe entonces devuelve un error 404 (Not found), si existe devuelve el documento como respuesta JSON
                    if (!$documento) {
                        return resultadoOperacion(['success' => false, 'message' => 'Documento no encontrado.'], 404);
                    }
                    if ($documento['archivo_url'] !== '') {
                        $documento['archivo_url'] = '/sigsm/api/archivo_documento.php?id=' . $id;
                    }
                    return resultadoOperacion(['success' => true, 'data' => $documento]);
                } catch (Throwable $error) {
                    return resultadoOperacion(['success' => false, 'message' => 'No fue posible consultar el documento.'], 500);
                }

            // Solo permite crear documentos si el usuario tiene el rol "Funcionario" 
            case 'reenviar':
            case 'create':

                // Exige que la petición sea POST, sinó tira error 405 (Method not allowed)

                // Lee los datos enviados por el navegador y los valida, si falta alguno da error 400 (Bad Request)
                $categoriaRecibida = $datos['categoria_id'] ?? null;
                $categoriaId = is_string($categoriaRecibida) || is_int($categoriaRecibida)
                    ? filter_var($categoriaRecibida, FILTER_VALIDATE_INT) : false;
                $categoriaId = $categoriaId !== false && $categoriaId > 0 ? $categoriaId : 0;
                $titulo = is_string($datos['titulo'] ?? null) ? trim($datos['titulo']) : '';
                $descripcion = is_string($datos['descripcion'] ?? null) ? trim($datos['descripcion']) : '';
                $especialista = is_string($datos['especialista'] ?? null) ? trim($datos['especialista']) : '';
                if ($categoriaId <= 0 || $titulo === '' || mb_strlen($titulo, 'UTF-8') > 200
                    || $descripcion === '' || mb_strlen($descripcion, 'UTF-8') > 10000
                    || $especialista === '' || mb_strlen($especialista, 'UTF-8') > 160
                ) {
                    return resultadoOperacion(['success' => false, 'message' => 'Revise la categoría y los campos obligatorios.'], 400);
                }

                if ($accion === 'reenviar') {
                    $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($id === false) {
                        return resultadoOperacion(['success' => false, 'message' => 'Identificador inválido.'], 400);
                    }
                }

                $pdfNuevo = null;
                try {
                    if (!$dao->existeCategoria($categoriaId)) {
                        return resultadoOperacion(['success' => false, 'message' => 'Seleccione una categoría válida.'], 400);
                    }

                    $archivoUrl = '';
                    $pesoKb = 0;
                    if ($accion === 'reenviar') {
                        $anterior = $dao->buscarRechazado($id, $usuarioId);
                        if (!$anterior) {
                            return resultadoOperacion(['success' => false, 'message' => 'Solo el autor puede reenviar un documento rechazado.'], 409);
                        }

                        $archivoUrl = $anterior['archivo_url'];
                        $pesoKb = (int)$anterior['peso_kb'];
                    }

                    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $pdfNuevo = guardarPdf($archivo);
                        $archivoUrl = $pdfNuevo['nombre'];
                        $pesoKb = $pdfNuevo['peso_kb'];
                    }
                    if ($archivoUrl === '') {
                        return resultadoOperacion(['success' => false, 'message' => 'Adjunte un archivo PDF.'], 400);
                    }

                    if ($accion === 'reenviar') {
                        $reenviado = $dao->reenviar([
                            'categoria' => $categoriaId, 'titulo' => $titulo, 'descripcion' => $descripcion,
                            'especialista' => $especialista, 'archivo' => $archivoUrl, 'peso' => $pesoKb,
                            'id' => $id, 'autor' => $usuarioId,
                        ]);
                        if (!$reenviado) {
                            if ($pdfNuevo !== null) {
                                unlink($pdfNuevo['ruta']);
                            }
                            return resultadoOperacion(['success' => false, 'message' => 'Solo el autor puede reenviar un documento rechazado.'], 409);
                        }
                        return resultadoOperacion(['success' => true]);
                    }

                    // Esto crea un nuevo registro en la tabla
                    $idNuevo = $dao->crear([
                        'categoria_id' => $categoriaId,
                        'titulo' => $titulo,
                        'descripcion' => $descripcion,
                        'especialista' => $especialista,
                        'archivo_url' => $archivoUrl,
                        'peso_kb' => $pesoKb,
                        'creado_por' => $usuarioId,
                    ]);

                } catch (ErrorPdf $error) {
                    return resultadoOperacion(['success' => false, 'message' => $error->getMessage()], $error->getCode());
                } catch (Throwable $error) {
                    if ($pdfNuevo !== null) {
                        unlink($pdfNuevo['ruta']);
                    }
                    return resultadoOperacion(['success' => false, 'message' => 'No fue posible guardar el documento.'], 500);
                }
                return resultadoOperacion(['success' => true, 'id' => $idNuevo], 201);

            // Es para eliminar un documento de forma lógica
            case 'delete':

                $idRecibido = $datos['id'] ?? null;
                $id = is_string($idRecibido) || is_int($idRecibido)
                    ? filter_var($idRecibido, FILTER_VALIDATE_INT) : false;
                $id = $id !== false && $id > 0 ? $id : 0;
                if ($id <= 0) {
                    return resultadoOperacion(['success' => false, 'message' => 'Falta el id del documento.'], 400);
                }

                try {
                    $desactivado = $dao->desactivar($id);

                    // Si no encontró nada para desactivar, entonces devuelve error 404 (Not found), si lo desactivó devuelve éxito
                    if (!$desactivado) {
                        return resultadoOperacion(['success' => false, 'message' => 'Documento no encontrado.'], 404);
                    }

                    return resultadoOperacion(['success' => true]);
                } catch (Throwable $error) {
                    return resultadoOperacion(['success' => false, 'message' => 'No fue posible eliminar el documento.'], 500);
                }

            // Si no se eligió nada de lo anterior, entonces tira error 400 (Bad request)
            default:
                return resultadoOperacion(['success' => false, 'message' => 'Acción no válida.'], 400);
        }

    }
}
