<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($accion) {

    // POST ?action=login  { usuario, clave }
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        $datos = cuerpoPeticion();
        $documento = trim((string)($datos['usuario'] ?? ''));
        $clave = (string)($datos['clave'] ?? '');

        if ($documento === '' || $clave === '') {
            responder(['success' => false, 'message' => 'Complete usuario y contraseña.'], 400);
        }

        try {
            $conexion = obtenerConexion();
            $consulta = $conexion->prepare(
                'SELECT id, nombre, clave_hash, rol
                 FROM usuarios
                 WHERE documento = :documento AND activo = 1'
            );
            $consulta->execute(['documento' => $documento]);
            $usuario = $consulta->fetch();

            if (!$usuario || !password_verify($clave, $usuario['clave_hash'])) {
                responder(['success' => false, 'message' => 'Usuario o contraseña incorrectos.'], 401);
            }

            // Regenerar el id de sesión al loguear
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];

            responder([
                'success' => true,
                'nombre' => $usuario['nombre'],
                'rol' => $usuario['rol'],
            ]);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible validar el usuario.'], 500);
        }
        break;

    // GET ?action=me  -> quién es el usuario logueado
    case 'me':
        if (!isset($_SESSION['usuario_id'])) {
            responder(['success' => true, 'autenticado' => false]);
        }
        responder([
            'success' => true,
            'autenticado' => true,
            'nombre' => $_SESSION['nombre'],
            'rol' => $_SESSION['rol'],
        ]);
        break;

    // POST ?action=logout
    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
        }
        session_destroy();
        responder(['success' => true]);
        break;

    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
