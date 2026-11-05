<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

header('Cache-Control: private, no-store');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    $consulta = obtenerConexion()->prepare(
        'SELECT archivo_url, estado_revision FROM documentos WHERE id = :id AND activo = 1'
    );
    $consulta->execute(['id' => $id ?: 0]);
    $documento = $consulta->fetch();
    if (!$documento || $documento['archivo_url'] === '') {
        responder(['success' => false, 'message' => 'Archivo no encontrado.'], 404);
    }
    if ($documento['estado_revision'] !== 'aprobado') {
        exigirRol(['funcionario', 'medico']);
    }

    $url = $documento['archivo_url'];
    if (filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
        header('Location: ' . $url);
        exit;
    }
    $nombre = basename(rawurldecode($url));
    $directorio = realpath(__DIR__ . '/../Archivos');
    $ruta = $directorio === false ? false : realpath($directorio . DIRECTORY_SEPARATOR . $nombre);
    if ($ruta === false || dirname($ruta) !== $directorio || !is_file($ruta)
        || strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== 'pdf') {
        responder(['success' => false, 'message' => 'Archivo no encontrado.'], 404);
    }
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
} catch (Throwable $error) {
    responder(['success' => false, 'message' => 'No se pudo abrir el archivo.'], 500);
}
