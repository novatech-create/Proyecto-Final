<?php
declare(strict_types=1);

final class ErrorPdf extends DomainException {}

function carpetaPdf(): string
{
    return getenv('SIGSM_PDF_DIR') ?: __DIR__ . '/../Archivos';
}

function esPdfSubido(string $nombre): bool
{
    return preg_match('/\Asubido-[a-f0-9]{32}\.pdf\z/', $nombre) === 1;
}

function guardarPdf(array $archivo): array
{
    $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new ErrorPdf('El PDF supera los 10 MB.', 413);
    }

    $temporal = $archivo['tmp_name'] ?? null;
    if ($error !== UPLOAD_ERR_OK || !is_string($temporal) || !is_uploaded_file($temporal)) {
        throw new ErrorPdf('Selecciona un PDF válido.', 400);
    }

    $tamano = filesize($temporal);
    if ($tamano === false || $tamano === 0) {
        throw new ErrorPdf('El archivo está vacío o no se pudo leer.', 400);
    }
    if ($tamano > 10 * 1024 * 1024) {
        throw new ErrorPdf('El PDF supera los 10 MB.', 413);
    }

    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);
    if ($tipo !== 'application/pdf') {
        throw new ErrorPdf('El archivo debe ser un PDF.', 415);
    }

    $carpeta = carpetaPdf();
    if (!is_dir($carpeta) || !is_writable($carpeta)) {
        throw new RuntimeException('La carpeta privada de PDF no está disponible.');
    }

    $nombre = 'subido-' . bin2hex(random_bytes(16)) . '.pdf';
    $ruta = $carpeta . DIRECTORY_SEPARATOR . $nombre;
    if (!move_uploaded_file($temporal, $ruta)) {
        throw new RuntimeException('No se pudo guardar el PDF.');
    }

    return [
        'nombre' => $nombre,
        'ruta' => $ruta,
        'peso_kb' => (int) ceil($tamano / 1024),
    ];
}
