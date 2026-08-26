<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($accion) {

    // GET ?action=getall  -> documentos activos agrupados por categoría
    // Usado por el panel "Gestión de Documentación" (funcionario/administrador)
    case 'getall':
        exigirRol(['administrador', 'funcionario_documentacion']);
        try {
            $conexion = obtenerConexion();
            $consulta = $conexion->query(
                'SELECT d.id, d.titulo, d.descripcion, d.especialista, d.archivo_url, d.peso_kb,
                        d.fecha_actualizacion, c.id AS categoria_id, c.nombre AS categoria_nombre
                 FROM documentos d
                 JOIN categorias_documentos c ON c.id = d.categoria_id
                 WHERE d.activo = 1
                 ORDER BY c.nombre ASC, d.fecha_actualizacion DESC'
            );
            $filas = $consulta->fetchAll();

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
                    'especialista' => $fila['especialista'],
                    'fecha_actualizacion' => $fila['fecha_actualizacion'],
                ];
            }

            responder(['success' => true, 'data' => array_values($categorias)]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible consultar los documentos.'], 500);
        }
        break;

    // GET ?action=get&id=123 -> detalle de un documento (vista pública desde QR)
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            responder(['success' => false, 'message' => 'Falta el id del documento.'], 400);
        }
        try {
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

            if (!$documento) {
                responder(['success' => false, 'message' => 'Documento no encontrado.'], 404);
            }
            responder(['success' => true, 'data' => $documento]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible consultar el documento.'], 500);
        }
        break;

    // POST ?action=create  { categoria_id, titulo, descripcion, especialista, archivo_url, peso_kb }
    // Usado por el botón "+ Nuevo Documento" (funcionario/administrador)
    case 'create':
        exigirRol(['administrador', 'funcionario_documentacion']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        $datos = cuerpoPeticion();
        $categoriaId = (int)($datos['categoria_id'] ?? 0);
        $titulo = trim((string)($datos['titulo'] ?? ''));
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $especialista = trim((string)($datos['especialista'] ?? ''));
        $archivoUrl = trim((string)($datos['archivo_url'] ?? ''));
        $pesoKb = (int)($datos['peso_kb'] ?? 0);

        if ($categoriaId <= 0 || $titulo === '' || $descripcion === '' || $especialista === '') {
            responder(['success' => false, 'message' => 'Complete categoría, título, descripción y especialista.'], 400);
        }

        try {
            $conexion = obtenerConexion();
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

            responder(['success' => true, 'id' => (int)$conexion->lastInsertId()], 201);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible guardar el documento.'], 500);
        }
        break;

    // POST ?action=delete  { id: 123 } -> desactiva un documento
    // Usado por el botón "Eliminar" (funcionario/administrador)
    case 'delete':
        exigirRol(['administrador', 'funcionario_documentacion']);
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
                'UPDATE documentos SET activo = 0 WHERE id = :id AND activo = 1'
            );
            $consulta->execute(['id' => $id]);

            if ($consulta->rowCount() === 0) {
                responder(['success' => false, 'message' => 'Documento no encontrado.'], 404);
            }

            responder(['success' => true]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible eliminar el documento.'], 500);
        }
        break;

    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
