(function () {
const API_BASE = '/sigsm/api';

const formulario = document.querySelector('#formularioEncuesta');
const campoServicio = document.querySelector('#campoServicio');
const campoComentarios = document.querySelector('#campoComentarios');
const botonEnviar = document.querySelector('#botonEnviarEncuesta');
const mensajeError = document.querySelector('#mensajeErrorEncuesta');

// Si se llega desde la vista de un documento (QR), se preselecciona el servicio
// y se guarda el id del documento para asociarlo a la respuesta.
const parametros = new URLSearchParams(window.location.search);
const documentoId = parametros.get('documento_id');
const servicioSugerido = parametros.get('servicio');
if (servicioSugerido) {
  const opciones = Array.from(campoServicio.options).map((opcion) => opcion.value);
  if (opciones.includes(servicioSugerido)) {
    campoServicio.value = servicioSugerido;
  }
}

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  if (!formulario.reportValidity()) return;

  mensajeError.textContent = '';
  mensajeError.classList.remove('visible');

  const datos = new FormData(formulario);
  const calificacionInfo = datos.get('p1');
  const datosSatisfaccion = datos.get('datosSatisfaccion');
  const comprension = datos.get('p2');
  const utilidad = datos.get('p3');
  const comentario = campoComentarios.value.trim();
  const serviciosValidos = ['Cardiología', 'Nefrología', 'Trasplante', 'Imagenología', 'Neurología', 'Urología'];
  const calificacionesValidas = ['Excelente', 'Buena', 'Regular', 'Mala'];
  const comprensionesValidas = ['Si', 'Parcialmente', 'No'];
  const utilidadesValidas = ['Muy util', 'Util', 'Poco util', 'Nada util'];
  const idDocumentoValido = documentoId === null || /^[1-9][0-9]*$/.test(documentoId);

  if (!serviciosValidos.includes(campoServicio.value)
    || !calificacionesValidas.includes(calificacionInfo)
    || !/^[1-5]$/.test(String(datosSatisfaccion || ''))
    || !comprensionesValidas.includes(comprension)
    || !utilidadesValidas.includes(utilidad)
    || comentario.length > 2000 || !idDocumentoValido
  ) {
    mensajeError.textContent = 'Revise las respuestas obligatorias y el límite de 2000 caracteres del comentario.';
    mensajeError.classList.add('visible');
    return;
  }

  botonEnviar.disabled = true;
  botonEnviar.textContent = 'Enviando...';

  try {
    const respuesta = await fetch(`${API_BASE}/encuestas.php?action=create`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        servicio: campoServicio.value,
        calificacion_info: calificacionInfo,
        datosSatisfaccion: Number(datosSatisfaccion),
        comprension,
        utilidad,
        comentario,
        documento_id: documentoId,
      }),
    });
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
      throw new Error(resultado.message || 'No se pudo enviar la encuesta.');
    }

    window.location.href = 'gracias_por_encuesta.html';
  } catch (error) {
    mensajeError.textContent = error.message;
    mensajeError.classList.add('visible');
    botonEnviar.disabled = false;
    botonEnviar.textContent = 'Enviar Encuesta';
  }
});
})();
