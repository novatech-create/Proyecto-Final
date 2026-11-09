<?php
declare(strict_types=1);

require_once __DIR__ . '/ResultadoOperacion.php';
require_once __DIR__ . '/../datos/UsuarioDAO.php';

final class AutenticacionBO
{
    public function autenticar(array $datos): array
    {
        $documentoRecibido = $datos['usuario'] ?? null;
        $claveRecibida = $datos['clave'] ?? null;
        $documento = is_string($documentoRecibido) ? trim($documentoRecibido) : '';
        $clave = is_string($claveRecibida) ? $claveRecibida : '';

        // Esto valida que ambos campos tengan algo dentro y sinó tira error 400
        if (!preg_match('/^[0-9]{1,20}$/', $documento) || $clave === '' || strlen($clave) > 72) {
            return resultadoOperacion(['success' => false, 'message' => 'Ingrese un documento válido y una contraseña de hasta 72 caracteres.'], 400);
        }

        // Abre la conexión a la BD y busca al usuario por el "documento" (que es el nombre de usuario) y que esté activo
        // Si no encuentra el usuario o la contraseña no coincide se tira un error 401
        // Solo busca usuarios activos (activo = 1), osea que no están de baja lógica
        try {
            $dao = new UsuarioDAO();
            $usuario = $dao->buscarParaLogin($documento);

            $claveAlmacenada = (string)($usuario['clave'] ?? '');
            $infoHash = password_get_info($claveAlmacenada);
            $esHash = $infoHash['algo'] !== null && $infoHash['algo'] !== 0;
            $claveValida = $usuario && (
                password_verify($clave, $claveAlmacenada)
                || (!$esHash && hash_equals($claveAlmacenada, $clave))
            );

            if (!$claveValida) {
                return resultadoOperacion(['success' => false, 'message' => 'Usuario o contraseña incorrectos.'], 401);
            }

            if (!$esHash || password_needs_rehash($claveAlmacenada, PASSWORD_DEFAULT)) {
                $dao->actualizarClave((int)$usuario['id'], password_hash($clave, PASSWORD_DEFAULT));
            }

            return resultadoOperacion(['success' => true, 'usuario' => [
                'id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'rol' => $usuario['rol'],
            ]]);
        } catch (Throwable $error) {
            return resultadoOperacion(['success' => false, 'message' => 'No fue posible validar el usuario.'], 500);
        }
    }

    public function registrarFuncionario(array $datos): array
    {
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
            return resultadoOperacion([
                'success' => false,
                'message' => 'Ingrese un documento numérico válido, un nombre de hasta 150 caracteres y una clave de 8 a 72 caracteres.',
            ], 400);
        }

        try {
            $id = (new UsuarioDAO())->crearFuncionario($documento, $nombre, password_hash($clave, PASSWORD_DEFAULT));
            return resultadoOperacion(['success' => true, 'id' => $id], 201);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                return resultadoOperacion(['success' => false, 'message' => 'El documento ya está registrado.'], 409);
            }
            return resultadoOperacion(['success' => false, 'message' => 'No fue posible registrar el usuario.'], 500);
        } catch (Throwable $error) {
            return resultadoOperacion(['success' => false, 'message' => 'No fue posible registrar el usuario.'], 500);
        }
    }
}
