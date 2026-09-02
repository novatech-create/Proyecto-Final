<?php
declare(strict_types=1);
// Este es el archivo que se encarga de la autenticación
// Recibe usuario y contraseña
// Busca usuario en la BD y compara la contraseña
// Si es correcto la sesión se guarda en local (Por eso se pierde al cerrar el navegador)
// Luego con eso se puede saber quién está logueado y cerrar sesión


// Se requiere el archivo de cabeceras para permitir CORS y establecer el tipo de contenido como JSON
// Aparte también se requiere la conexión a la base de datos para poder consultar los usuarios
// Y con require_once aseguramos que cargue una sola vez
require_once __DIR__ . '/cabeceras.php';
require_once __DIR__ . '/conexion.php';

// Lee la acción que viene del navegador, si no llega ninguna esta queda vacía
$accion = $_GET['action'] ?? $_POST['action'] ?? '';

// Esto mira que acción se quiere hacer (Login, me o logout) y después toma una decisión
switch ($accion) {

    // Esto solo permite el método POST, si alguién intentara usar acceder por GET le saltaría el error 405 (método no permitido)
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }
        
        // Lee el JSON enviado por el navegador y lo transfroma en un array asociativo (Se extra Usuario y Clave) aparte se usa trim() para quitar espacios al inicio y final de la cadena y se asegura que sea string
        // Para quitar los espacios del principio y final del string para evitar errores tontos
        $datos = cuerpoPeticion();
        $documento = trim((string)($datos['usuario'] ?? ''));
        $clave = (string)($datos['clave'] ?? '');

        // Esto valida que ambos campos tengan algo dentro y sinó tira error 400
        if ($documento === '' || $clave === '') {
            responder(['success' => false, 'message' => 'Complete usuario y contraseña.'], 400);
        }

        // Abre la conexión a la BD y busca al usuario por el "documento" (que es el nombre de usuario) y que esté activo
        // Si no encuentra el usuario o la contraseña no coincide se tira un error 401
        // Solo busca usuarios activos (activo = 1), osea que no están de baja lógica
        try {
            $conexion = obtenerConexion();
            $consulta = $conexion->prepare(
                'SELECT id, nombre, clave, rol
                 FROM usuarios
                 WHERE documento = :documento AND activo = 1'
            );
            $consulta->execute(['documento' => $documento]);
            $usuario = $consulta->fetch();

            // Esto compara la contraseña que viene del navegador con la que está en la BD, si no coincide tira error 401
            if (!$usuario || (string)$usuario['clave'] !== $clave) {
                responder(['success' => false, 'message' => 'Usuario o contraseña incorrectos.'], 401);
            }

            // Regenerar el id de sesión al loguear
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['rol'] = $usuario['rol'];

            // Devuelve el nombre y rol del usuario logueado
            responder([
                'success' => true,
                'nombre' => $usuario['nombre'],
                'rol' => $usuario['rol'],
            ]);

            // Si hay algún error en la conexión a la BD o en la consulta se da un error 500 para luego break terminar el caso del switch
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible validar el usuario.'], 500);
        }
        break;


    // Consulta si hay un usuario logueado y devuelve sus datos. Si no hay usuario entonces autenticado = false
    // Esto se usa para saber si hay un usuario logueado y poder mostrar el menú de navegación correspondiente
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

    // Consulta si hay un usuario logueado y cierrra la sesión, destruyendola
    case 'logout':
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

    // Si la acción es diferente de las anteriores entonces devuelve un error 400 (Bad Request)
    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
    
}
