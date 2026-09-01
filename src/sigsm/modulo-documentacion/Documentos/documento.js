(function () {
const API_BASE = '/sigsm/api';

const estadoDocumento = document.querySelector('#estadoDocumento');
const tarjetaDocumento = document.querySelector('#tarjetaDocumento');
const docCategoria = document.querySelector('#docCategoria');
const docTitulo = document.querySelector('#docTitulo');
const docDescripcion = document.querySelector('#docDescripcion');
const docFecha = document.querySelector('#docFecha');
const docPeso = document.querySelector('#docPeso');
const enlaceEncuesta = document.querySelector('#enlaceEncuesta');
const enlaceDescarga = document.querySelector('#descargarDocumento');
const enlaceVista = document.querySelector('#verDocumento');

async function cargarDocumento() {
  const parametros = new URLSearchParams(window.location.search);
  const id = parametros.get('id');

  if (!id) {
    estadoDocumento.textContent = 'Falta el identificador del documento en el enlace del QR.';
    return;
  }

  try {
    const respuesta = await fetch(`${API_BASE}/documentos.php?action=get&id=${encodeURIComponent(id)}`);
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
      throw new Error(resultado.message || 'No se encontró el documento.');
    }

    const documento = resultado.data;
    docCategoria.textContent = documento.categoria_nombre;
    docTitulo.textContent = documento.titulo;
    docDescripcion.textContent = documento.descripcion;
    docFecha.textContent = `Actualizado: ${formatearFecha(documento.fecha_actualizacion)}`;
    docPeso.textContent = `PDF ${documento.peso_kb} KB`;

    if (enlaceDescarga) {
      enlaceDescarga.href = documento.archivo_url || '#';
      enlaceDescarga.classList.toggle('oculto', !documento.archivo_url);
    }
    if (enlaceVista) {
      enlaceVista.href = documento.archivo_url || '#';
      enlaceVista.classList.toggle('oculto', !documento.archivo_url);
    }

    if (enlaceEncuesta) {
      enlaceEncuesta.href = `../Pacientes/Encuesta.html?documento_id=${documento.id}&servicio=${encodeURIComponent(documento.categoria_nombre)}`;
    }

    estadoDocumento.textContent = '';
    tarjetaDocumento.classList.remove('oculto');
  } catch (error) {
    estadoDocumento.textContent = error.message;
  }
}

function formatearFecha(fechaIso) {
  const fecha = new Date(fechaIso.replace(' ', 'T'));
  if (Number.isNaN(fecha.getTime())) return fechaIso;
  return fecha.toLocaleDateString('es-UY');
}

cargarDocumento();
})();
