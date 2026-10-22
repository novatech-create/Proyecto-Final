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
        $documentoRecibido = $datos['usuario'] ?? null;
        $claveRecibida = $datos['clave'] ?? null;
        $documento = is_string($documentoRecibido) ? trim($documentoRecibido) : '';
        $clave = is_string($claveRecibida) ? $claveRecibida : '';

        // Esto valida que ambos campos tengan algo dentro y sinó tira error 400
        if (!preg_match('/^[0-9]{1,20}$/', $documento) || $clave === '' || strlen($clave) > 72) {
            responder(['success' => false, 'message' => 'Ingrese un documento válido y una contraseña de hasta 72 caracteres.'], 400);
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

            $claveAlmacenada = (string)($usuario['clave'] ?? '');
            $infoHash = password_get_info($claveAlmacenada);
            $esHash = $infoHash['algo'] !== null && $infoHash['algo'] !== 0;
            $claveValida = $usuario && (
                password_verify($clave, $claveAlmacenada)
                || (!$esHash && hash_equals($claveAlmacenada, $clave))
            );

            if (!$claveValida) {
                responder(['success' => false, 'message' => 'Usuario o contraseña incorrectos.'], 401);
            }

            if (!$esHash || password_needs_rehash($claveAlmacenada, PASSWORD_DEFAULT)) {
                $actualizarClave = $conexion->prepare(
                    'UPDATE usuarios SET clave = :clave WHERE id = :id'
                );
                $actualizarClave->execute([
                    'clave' => password_hash($clave, PASSWORD_DEFAULT),
                    'id' => $usuario['id'],
                ]);
            }

            // Acá está el token para que el navegador lo guarde y lo envíe en cada petición, 
            //así se puede mantener la sesión aunque se cierre el navegador como pidió el profesor. 
            //El token se guarda en la BD con un hash SHA256 para que no se pueda adivinar y se le pone una fecha de expiración de 30 días. 
            //Luego se devuelve el token al navegador para que lo guarde y lo envíe en cada petición.
            $token = bin2hex(random_bytes(32));
            $expira = time() + (30 * 24 * 60 * 60);
            $guardarToken = $conexion->prepare(
                'INSERT INTO auth_tokens (token_hash, usuario_id, expires_at)
                 VALUES (:token_hash, :usuario_id, :expires_at)'
            );
            $guardarToken->execute([
                'token_hash' => hash('sha256', $token),
                'usuario_id' => $usuario['id'],
                'expires_at' => $expira,
            ]);

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
                'token' => $token,
            ]);

            // Si hay algún error en la conexión a la BD o en la consulta se da un error 500 para luego break terminar el caso del switch
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible validar el usuario.'], 500);
        }
        break;

    case 'register':
        exigirRol(['funcionario']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder(['success' => false, 'message' => 'Usa POST.'], 405);
        }

        $datos = cuerpoPeticion();
        $documentoRecibido = $datos['documento'] ?? null;
        $nombreRecibido = $datos['nombre'] ?? null;
        $claveRecibida = $datos['clave'] ?? null;
        $documento = is_string($documentoRecibido) ? trim($documentoRecibido) : '';
        $nombre = is_string($nombreRecibido) ? trim($nombreRecibido) : '';
        $clave = is_string($claveRecibida) ? $claveRecibida : '';

        if (!preg_match('/^[0-9]{1,20}$/', $documento) || $nombre === ''
            || mb_strlen($nombre, 'UTF-8') > 150 || mb_strlen($clave, 'UTF-8') < 8
            || strlen($clave) > 72
        ) {
            responder([
                'success' => false,
                'message' => 'Ingrese un documento numérico válido, un nombre de hasta 150 caracteres y una clave de 8 a 72 caracteres.',
            ], 400);
        }

        try {
            $conexion = obtenerConexion();
            $crearUsuario = $conexion->prepare(
                'INSERT INTO usuarios (documento, nombre, clave, rol, activo)
                 VALUES (:documento, :nombre, :clave, :rol, 1)'
            );
            $crearUsuario->execute([
                'documento' => $documento,
                'nombre' => $nombre,
                'clave' => password_hash($clave, PASSWORD_DEFAULT),
                'rol' => 'funcionario',
            ]);

            responder(['success' => true, 'id' => (int)$conexion->lastInsertId()], 201);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                responder(['success' => false, 'message' => 'El documento ya está registrado.'], 409);
            }
            responder(['success' => false, 'message' => 'No fue posible registrar el usuario.'], 500);
        } catch (Throwable $error) {
            responder(['success' => false, 'message' => 'No fue posible registrar el usuario.'], 500);
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

    // Cierra la sesión del usuario logueado y elimina el token de la BD para que no se pueda usar más
    case 'logout':
        $token = tokenAutorizacion();
        if ($token !== null) {
            try {
                $conexion = obtenerConexion();
                $eliminarToken = $conexion->prepare(
                    'DELETE FROM auth_tokens WHERE token_hash = :token_hash'
                );
                $eliminarToken->execute(['token_hash' => hash('sha256', $token)]);
            } catch (Throwable $error) {
                responder(['success' => false, 'message' => 'No fue posible cerrar la sesión.'], 500);
            }
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

    // Si la acción es diferente de las anteriores entonces devuelve un error 400 (Bad Request)
    default:
        responder(['success' => false, 'message' => 'Acción no válida.'], 400);
    
}