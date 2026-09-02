<?php
declare(strict_types=1);
// Este archivo es la API que maneja todo lo que tiene que ver con documentos

// Se requiere el archivo de cabeceras para permitir CORS y establecer el tipo de contenido como JSON
// Aparte también se requiere la conexión a la base de datos para poder consultar los usuarios
// Y con require_once aseguramos que cargue una sola vez
require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

// Lee la acción que viene del navegador, si no llega ninguna queda vacio
$accion = $_GET['action'] ?? $_POST['action'] ?? '';


switch ($accion) {

    // Esto es para obtener todos los documentos activos, agrupados por categoría
    case 'getall':
        try {
            $conexion = obtenerConexion();
            $consulta = $conexion->query(
                
                // Trae todos los documentos activos, junto con su categoría, ordenados y con fecha de actualización
                'SELECT d.id, d.titulo, d.descripcion, d.especialista, d.archivo_url, d.peso_kb,
                        d.fecha_actualizacion, c.id AS categoria_id, c.nombre AS categoria_nombre
                 FROM documentos d
                 JOIN categorias_documentos c ON c.id = d.categoria_id
                 WHERE d.activo = 1
                 ORDER BY c.nombre ASC, d.fecha_actualizacion DESC'
            );
            
            // Guarda todas las filas resultantes en un array asociativo
            $filas = $consulta->fetchAll();

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
                $categorias[$idCategoria]['documentos'][] = [
                    'id' => $fila['id'],
                    'titulo' => $fila['titulo'],
                    'descripcion' => $fila['descripcion'],
                    'especialista' => $fila['especialista'],
                    'archivo_url' => $fila['archivo_url'],
                    'peso_kb' => $fila['peso_kb'],
                    'fecha_actualizacion' => $fila['fecha_actualizacion'],
                    'categoria_nombre' => $fila['categoria_nombre'],
                ];
            }

            // Devuelve el array de categorías con sus documentos como respuesta JSON
            responder(['success' => true, 'data' => array_values($categorias)]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible consultar los documentos.'], 500);
        }
        break;

    // Es para devolver un único documento según su id
    case 'get':

        // Toma el id del navegador y la pasa a int
        $id = (int)($_GET['id'] ?? 0);
        
        // Si el id es menor o igual a 0, devuelve un error 400 (Bad Request)
        if ($id <= 0) {
            responder(['success' => false, 'message' => 'Falta el id del documento.'], 400);
        }
        try {

        // Busca un documento activo por su id, junto con su categoría
            $conexion = obtenerConexion();
            $consulta = $conexion->prepare(
                'SELECT d.id, d.titulo, d.descripcion, d.especialista, d.archivo_url, d.peso_kb,
                        d.fecha_actualizacion, c.nombre AS categoria_nombre
                 FROM documentos d
                 JOIN categorias_documentos c ON c.id = d.categoria_id
                 WHERE d.id = :id AND d.activo = 1'
            );
            $consulta->execute(['id' => $id]);
            $documento = $consulta->fetch();

            // Si no existe entonces devuelve un error 404 (Not found), si existe devuelve el documento como respuesta JSON
            if (!$documento) {
                responder(['success' => false, 'message' => 'Documento no encontrado.'], 404);
            }
            responder(['success' => true, 'data' => $documento]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible consultar el documento.'], 500);
        }
        break;

    // Solo permite crear documentos si el usuario tiene el rol "Funcionario" 
    case 'create':
        exigirRol(['funcionario']);

        // Exige que la petición sea POST, sinó tira error 405 (Method not allowed)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        // Lee los datos enviados por el navegador y los valida, si falta alguno da error 400 (Bad Request)
        $datos = cuerpoPeticion();
        $categoriaId = (int)($datos['categoria_id'] ?? 0);
        $titulo = trim((string)($datos['titulo'] ?? ''));
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $especialista = trim((string)($datos['especialista'] ?? ''));
        $archivoUrl = trim((string)($datos['archivo_url'] ?? ''));
        $pesoKb = (int)($datos['peso_kb'] ?? 0);

        // Si faltan datos tira otro error 400
        if ($categoriaId <= 0 || $titulo === '' || $descripcion === '' || $especialista === '') {
            responder(['success' => false, 'message' => 'Complete categoría, título, descripción y especialista.'], 400);
        }

        try {
            $conexion = obtenerConexion();

            // Esto crea un nuevo registro en la tabla
            $consulta = $conexion->prepare(
                'INSERT INTO documentos (categoria_id, titulo, descripcion, especialista, archivo_url, peso_kb, creado_por)
                 VALUES (:categoria_id, :titulo, :descripcion, :especialista, :archivo_url, :peso_kb, :creado_por)'
            );
            $consulta->execute([
                'categoria_id' => $categoriaId,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'especialista' => $especialista,
                'archivo_url' => $archivoUrl,
                'peso_kb' => $pesoKb,
                'creado_por' => $_SESSION['usuario_id'],
            ]);

            // Esto guarda quién lo creó y devuelve el id del documento recién creado como JSON con código 201 (Created)
            responder(['success' => true, 'id' => (int)$conexion->lastInsertId()], 201);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible guardar el documento.'], 500);
        }
        break;

    // Es para eliminar un documento de forma lógica
    case 'delete':
        exigirRol(['funcionario']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        $datos = cuerpoPeticion();
        $id = (int)($datos['id'] ?? 0);
        if ($id <= 0) {
            responder(['success' => false, 'message' => 'Falta el id del documento.'], 400);
        }

        try {
            $conexion = obtenerConexion();
            $consulta = $conexion->prepare(

                // Esto hace la baja lógica del documento, cambiando el campo "activo" a 0
                'UPDATE documentos SET activo = 0 WHERE id = :id AND activo = 1'
            );
            $consulta->execute(['id' => $id]);

            // Si no encontró nada para desactivar, entonces devuelve error 404 (Not found), si lo desactivó devuelve éxito
            if ($consulta->rowCount() === 0) {
                responder(['success' => false, 'message' => 'Documento no encontrado.'], 404);
            }

            responder(['success' => true]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible eliminar el documento.'], 500);
        }
        break;

    // Si no se eligió nada de lo anterior, entonces tira error 400 (Bad request)
    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
