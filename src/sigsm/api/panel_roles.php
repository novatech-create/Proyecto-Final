<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../negocio/PanelRolesBO.php';

exigirRol(['administrador', 'medico']);
$metodo = $_SERVER['REQUEST_METHOD'];
responder(...(new PanelRolesBO())->ejecutar(
    $metodo,
    $_SESSION['rol'] === 'administrador',
    $metodo === 'POST' ? cuerpoPeticion() : [],
    (int)$_SESSION['usuario_id'],
));
