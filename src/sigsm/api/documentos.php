<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../negocio/DocumentoBO.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';
$accion = is_string($accion) ? $accion : '';

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
