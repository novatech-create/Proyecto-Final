const API_BASE = '/sigsm/api';

const estadoEncuestas = document.querySelector('#estadoEncuestas');
const statTotal = document.querySelector('#statTotal');
const statPromedio = document.querySelector('#statPromedio');
const statServicioTop = document.querySelector('#statServicioTop');
const cuerpoTabla = document.querySelector('#cuerpoTablaRespuestas');

async function cargarRespuestas() {
  estadoEncuestas.textContent = 'Cargando respuestas...';

  try {
    const respuesta = await fetch(`${API_BASE}/encuestas.php?action=getall`, {
      credentials: 'include',
    });
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
      throw new Error(resultado.message || 'No se pudieron cargar las encuestas.');
    }

    statTotal.textContent = resultado.estadisticas.total;
    statPromedio.textContent = `${resultado.estadisticas.promedio} / 5`;
    statServicioTop.textContent = resultado.estadisticas.servicio_mas_consultado || '-';

    cuerpoTabla.innerHTML = resultado.data.map((fila) => `
      <tr>
        <td>${formatearFecha(fila.creado_en)}</td>
        <td>${escapeHtml(fila.servicio)}</td>
        <td>${escapeHtml(fila.calificacion_info)}</td>
        <td>${escapeHtml(fila.comentario || '-')}</td>
      </tr>
    `).join('');

    estadoEncuestas.textContent = '';
  } catch (error) {
    estadoEncuestas.textContent = error.message;
  }
}

function formatearFecha(fechaIso) {
  const fecha = new Date(fechaIso.replace(' ', 'T'));
  if (Number.isNaN(fecha.getTime())) return fechaIso;
  return fecha.toLocaleDateString('es-UY');
}

function escapeHtml(valor) {
  return String(valor)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

cargarRespuestas();
