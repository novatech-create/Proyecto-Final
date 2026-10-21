<?php
declare(strict_types=1);

// Sesión compartida por todos (login, rol activo, etc.)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function tokenAutorizacion(): ?string
{
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($cabecera), $coincidencias)) {
        return null;
    }
    return $coincidencias[1];
}

function restaurarSesionDesdeToken(): void
{
    if (isset($_SESSION['usuario_id'])) {
        return;
    }

    $token = tokenAutorizacion();
    if ($token === null) {
        return;
    }

    require_once __DIR__ . '/conexion.php';
    try {
        $conexion = obtenerConexion();
        $consulta = $conexion->prepare(
            'SELECT u.id, u.nombre, u.rol
             FROM auth_tokens t
             JOIN usuarios u ON u.id = t.usuario_id
             WHERE t.token_hash = :token_hash
               AND t.expires_at > UNIX_TIMESTAMP()
               AND u.activo = 1'
        );
        $consulta->execute(['token_hash' => hash('sha256', $token)]);
        $usuario = $consulta->fetch();
        if ($usuario) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];
        }
    } catch (Throwable $error) {
        return;
    }
}

// CORS: permite que el frontend pueda llamar a la API y mantener la sesión
$origen = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origen}");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

restaurarSesionDesdeToken();

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
