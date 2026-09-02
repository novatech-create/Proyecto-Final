<?php
declare(strict_types=1);

// Sesión compartida por todos (login, rol activo, etc.)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORS: permite que el frontend pueda llamar a la API y mantener la sesión
$origen = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origen}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Envía una respuesta JSON y termina la ejecución.
function responder(array $payload, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Lee el cuerpo de la petición como JSON, si no es JSON devuelve $_POST
function cuerpoPeticion(): array
{
    $crudo = file_get_contents('php://input');
    $datos = json_decode($crudo ?: '', true);
    if (is_array($datos)) {
        return $datos;
    }
    return $_POST;
}

//Corta la ejecución si no hay sesión iniciada o el rol no está permitido.
function exigirRol(array $rolesPermitidos): void
{
    if (!isset($_SESSION['usuario_id'])) {
        responder(['success' => false, 'message' => 'Debe iniciar sesión.'], 401);
    }
    if (!in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        responder(['success' => false, 'message' => 'No tiene permisos para esta acción.'], 403);
    }
}
