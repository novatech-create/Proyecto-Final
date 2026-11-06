<?php
declare(strict_types=1);

require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/../negocio/AutenticacionBO.php';

$accion = $_GET['action'] ?? $_POST['action'] ?? '';
if ($accion === 'register') {
    exigirRol(['administrador']);
}
if (in_array($accion, ['login', 'register'], true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['success' => false, 'message' => 'Usa POST.'], 405);
}

switch ($accion) {
    case 'login':
        [$resultado, $codigo] = (new AutenticacionBO())->autenticar(cuerpoPeticion());
        if (!$resultado['success']) {
            responder($resultado, $codigo);
        }
        if (!session_regenerate_id(true)) {
            responder(['success' => false, 'message' => 'No fue posible iniciar la sesión.'], 500);
        }
        $usuario = $resultado['usuario'];
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['rol'] = $usuario['rol'];
        responder(['success' => true, 'nombre' => $usuario['nombre'], 'rol' => $usuario['rol']]);

    case 'register':
        responder(...(new AutenticacionBO())->registrarFuncionario(cuerpoPeticion()));

    // Consulta si hay un usuario logueado y devuelve sus datos. Si no hay usuario entonces autenticado = false
    // Esto se usa para saber si hay un usuario logueado y poder mostrar el menú de navegación correspondiente
    case 'me':
        if (!isset($_SESSION['usuario_id'])) {
            responder(['success' => true, 'autenticado' => false]);
        }
        exigirRol(['funcionario', 'administrador', 'medico']);
        responder([
            'success' => true,
            'autenticado' => true,
            'nombre' => $_SESSION['nombre'],
            'rol' => $_SESSION['rol'],
        ]);
        break;

    // Cierra la sesión del usuario.
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly']
            );
        }
        session_destroy();
        responder(['success' => true]);
        break;

    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
}
