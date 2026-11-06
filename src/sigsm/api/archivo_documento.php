<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../datos/DocumentoDAO.php';
require_once __DIR__ . '/../negocio/pdf_documento.php';

header('Cache-Control: private, no-store');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    $documento = (new DocumentoDAO())->buscarArchivo($id ?: 0);
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
    $directorio = realpath(esPdfSubido($nombre) ? carpetaPdf() : __DIR__ . '/../Archivos');
    $ruta = $directorio === false ? false : realpath($directorio . DIRECTORY_SEPARATOR . $nombre);
    if ($ruta === false || dirname($ruta) !== $directorio || !is_file($ruta)
        || strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) !== 'pdf') {
        responder(['success' => false, 'message' => 'Archivo no encontrado.'], 404);
    }
    header('Content-Type: application/pdf');
    header('X-Content-Type-Options: nosniff');
    $descargar = ($_GET['descargar'] ?? '') === '1';
    $disposicion = $descargar ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposicion . '; filename="documento-' . $id . '.pdf"');
    session_write_close();
    readfile($ruta);
} catch (Throwable $error) {
    responder(['success' => false, 'message' => 'No se pudo abrir el archivo.'], 500);
}
