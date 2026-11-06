<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../negocio/AmbulanciaBO.php';

$accion = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
exigirRol(['funcionario']);
$metodos = [
    'catalogos' => 'GET', 'resumen' => 'GET', 'activos' => 'GET', 'historial' => 'GET',
    'administracion' => 'GET', 'crear-personal' => 'POST', 'guardar-vehiculo' => 'POST',
    'crear-ruta' => 'POST', 'crear-traslado' => 'POST', 'actualizar-estado' => 'POST',
];
$metodo = $metodos[$accion] ?? null;
if ($metodo === null) {
    responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
    responder(['success' => false, 'message' => 'Usa ' . $metodo . '.'], 405);
}

$negocio = new AmbulanciaBO();
$datos = $metodo === 'POST' ? cuerpoPeticion() : [];
try {
    $resultado = match ($accion) {
        'catalogos' => $negocio->catalogos(),
        'resumen', 'activos', 'historial' => $negocio->consultarTraslados($accion),
        'administracion' => $negocio->administracion(),
        'crear-personal' => $negocio->crearPersonal($datos),
        'guardar-vehiculo' => $negocio->guardarVehiculo($datos),
        'crear-ruta' => $negocio->crearRuta($datos),
        'crear-traslado' => $negocio->crearTraslado($datos, (int)$_SESSION['usuario_id']),
        'actualizar-estado' => $negocio->actualizarEstado($datos),
    };
    responder(...$resultado);
} catch (Throwable $error) {
    responder(['success' => false, 'message' => 'No fue posible completar la operación.'], 500);
}
