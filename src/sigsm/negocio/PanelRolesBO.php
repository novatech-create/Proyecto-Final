<?php
declare(strict_types=1);

require_once __DIR__ . '/ResultadoOperacion.php';
require_once __DIR__ . '/../datos/UsuarioDAO.php';
require_once __DIR__ . '/../datos/DocumentoDAO.php';

final class PanelRolesBO
{
    public function ejecutar(string $metodo, bool $administrador, array $datos, int $usuarioId): array
    {
        try {
            $usuarios = new UsuarioDAO();
            $documentos = new DocumentoDAO();
            if ($metodo === 'GET') {
                $filas = $administrador ? $usuarios->listarFuncionarios() : $documentos->listarPendientes();
                return resultadoOperacion(['success' => true, 'data' => $filas]);
            }

            if ($metodo !== 'POST') {
                return resultadoOperacion(['success' => false, 'message' => 'Método no permitido.'], 405);
            }

            $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                return resultadoOperacion(['success' => false, 'message' => 'Identificador inválido.'], 400);
            }

            if ($administrador) {
                $modificado = $usuarios->desactivarFuncionario($id);
            } else {
                $estado = $datos['estado'] ?? '';
                $motivo = is_string($datos['motivo'] ?? null) ? trim($datos['motivo']) : '';
                if (!in_array($estado, ['aprobado', 'rechazado'], true)
                    || ($estado === 'rechazado' && ($motivo === '' || mb_strlen($motivo) > 500))) {
                    return resultadoOperacion(['success' => false, 'message' => 'Indique una decisión y un motivo válido si rechaza.'], 400);
                }

                $modificado = $documentos->revisar($id, $estado, $motivo, $usuarioId);
            }

            if (!$modificado) {
                return resultadoOperacion(['success' => false, 'message' => 'El registro no está disponible para esta operación.'], 409);
            }
            return resultadoOperacion(['success' => true]);
        } catch (Throwable $error) {
            return resultadoOperacion(['success' => false, 'message' => 'No se pudo completar la operación.'], 500);
        }

    }
}
