<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../negocio/DocumentoBO.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';
$accion = is_string($accion) ? $accion : '';

// PHP vacía $_POST y $_FILES cuando el cuerpo supera post_max_size.
if (in_array($accion, ['create', 'reenviar'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $limitePeticion = ini_parse_quantity((string) ini_get('post_max_size'));
    if ($limitePeticion > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limitePeticion) {
        responder(['success' => false, 'message' => 'El PDF supera los 10 MB.'], 413);
    }
}

if (in_array($accion, ['gestion', 'vista_interna', 'create', 'reenviar', 'delete'], true)) {
    exigirRol(['funcionario']);
}
$esEscritura = in_array($accion, ['create', 'reenviar', 'delete'], true);
if ($esEscritura && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['success' => false, 'message' => 'Usa POST.'], 405);
}

responder(...(new DocumentoBO())->ejecutar(
    $accion,
    $esEscritura ? cuerpoPeticion() : [],
    $_GET['id'] ?? null,
    $_FILES['pdf'] ?? [],
    (int)($_SESSION['usuario_id'] ?? 0),
));
