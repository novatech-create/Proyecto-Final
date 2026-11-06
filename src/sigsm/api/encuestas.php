<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../negocio/EncuestaBO.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';
$accion = is_string($accion) ? $accion : '';

if ($accion === 'getall') {
    exigirRol(['funcionario']);
}
if ($accion === 'create' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['success' => false, 'message' => 'Usa POST.'], 405);
}

responder(...(new EncuestaBO())->ejecutar($accion, $accion === 'create' ? cuerpoPeticion() : []));
