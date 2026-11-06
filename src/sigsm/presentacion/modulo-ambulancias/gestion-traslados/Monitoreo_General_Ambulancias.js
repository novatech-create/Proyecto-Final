(function () {
  const API_URL = '/sigsm/api/ambulancias.php';
  const filasFlota = document.querySelector('#tablaFlota');
  const disponiblesEl = document.querySelector('#movilesDisponibles');
  const activosEl = document.querySelector('#trasladosActivos');

  function escapeHtml(valor) {
    return String(valor).replace(/[&<>"']/g, (caracter) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[caracter]);
  }

  async function cargarFlota() {
    try {
      const respuesta = await fetch(`${API_URL}?action=resumen`, { credentials: 'include' });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudo consultar la flota.');
      }

      disponiblesEl.textContent = resultado.Estadisticas.VehiculosDisponibles;
      activosEl.textContent = resultado.Estadisticas.TrasladosActivos;
      filasFlota.innerHTML = resultado.Flota.map(({ Matricula, TipoVehiculo, Traslado }) => `
        <tr>
          <td>${escapeHtml(Matricula)} · ${escapeHtml(TipoVehiculo)}</td>
          <td>${Traslado ? escapeHtml(Traslado.NombreChofer) : '-'}</td>
          <td>${Traslado ? escapeHtml(`${Traslado.PuntoOrigen} a ${Traslado.PuntoDestino}`) : 'Disponible en base'}</td>
          <td><span class="estado ${Traslado ? 'en-camino' : 'disponible'}">${Traslado ? escapeHtml(Traslado.Estado.replaceAll('_', ' ')) : 'Disponible'}</span></td>
        </tr>
      `).join('');
    } catch (error) {
      filasFlota.innerHTML = `<tr><td colspan="4">${escapeHtml(error.message)}</td></tr>`;
    }
  }

  cargarFlota();
  window.setInterval(cargarFlota, 30000);
})();
