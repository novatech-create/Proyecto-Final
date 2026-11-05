<?php
declare(strict_types=1);

const DURACION_SESION = 30 * 24 * 60 * 60;

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
        'gc_maxlifetime' => DURACION_SESION,
        'cookie_lifetime' => DURACION_SESION,
        'cookie_path' => '/sigsm/',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

header('Content-Type: application/json; charset=utf-8');
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

    try {
        require_once __DIR__ . '/conexion.php';
        $conexion = obtenerConexion();
        $consulta = $conexion->prepare(
            'SELECT nombre, rol, activo FROM usuarios WHERE id = :id LIMIT 1'
        );
        $consulta->execute(['id' => $_SESSION['usuario_id']]);
        $usuarioActual = $consulta->fetch();
    } catch (Throwable $error) {
        responder(['success' => false, 'message' => 'No se pudo verificar el perfil.'], 503);
    }

    if (!$usuarioActual || (int)$usuarioActual['activo'] !== 1) {
        $_SESSION = [];
        responder(['success' => false, 'message' => 'La cuenta no está activa.'], 401);
    }

    $_SESSION['nombre'] = $usuarioActual['nombre'];
    $_SESSION['rol'] = $usuarioActual['rol'];
    if (!in_array($usuarioActual['rol'], $rolesPermitidos, true)) {
        responder(['success' => false, 'message' => 'No tiene permisos para esta acción.'], 403);
    }
}
