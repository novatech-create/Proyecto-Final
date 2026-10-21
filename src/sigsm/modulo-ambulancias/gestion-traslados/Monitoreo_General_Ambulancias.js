(function () {
  const API_URL = '/sigsm/api/ambulancias.php';
  const filasFlota = document.querySelector('#tablaFlota');
  const disponiblesEl = document.querySelector('#movilesDisponibles');
  const activosEl = document.querySelector('#trasladosActivos');
  const panelInicial = document.querySelector('#inicioAdministrativo');
  const botonInicializar = document.querySelector('#botonInicializarAdmin');
  const mensajeInicializacion = document.querySelector('#mensajeInicializacion');
  const enlaceAdministracion = document.querySelector('#enlaceAdministracion');
  const enlaceNuevoTraslado = document.querySelector('#enlaceNuevoTraslado');

  function escapeHtml(valor) {
    return String(valor).replace(/[&<>"']/g, (caracter) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[caracter]);
  }

  async function cargarFlota() {
    try {
      const estadoRespuesta = await fetch(`${API_URL}?action=estado-inicial`, { credentials: 'include' });
      const estadoInicial = await estadoRespuesta.json();
      if (!estadoRespuesta.ok || !estadoInicial.success) {
        throw new Error(estadoInicial.message || 'No se pudo consultar el estado de la cuenta.');
      }

      if (estadoInicial.puedeInicializar && !estadoInicial.vinculado) {
        panelInicial.hidden = false;
      }

      const respuesta = await fetch(`${API_URL}?action=resumen`, { credentials: 'include' });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudo consultar la flota.');
      }
      if (resultado.EsAdministrativo) {
        enlaceAdministracion.hidden = false;
        enlaceNuevoTraslado.hidden = false;
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

  botonInicializar?.addEventListener('click', async () => {
    if (!window.confirm('¿Vincular esta cuenta como primera administrativa del módulo?')) return;
    botonInicializar.disabled = true;
    try {
      const respuesta = await fetch(`${API_URL}?action=inicializar-administrativo`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) throw new Error(resultado.message || 'No se pudo inicializar la administración.');
      window.location.reload();
    } catch (error) {
      mensajeInicializacion.textContent = error.message;
      botonInicializar.disabled = false;
    }
  });

  cargarFlota();
  window.setInterval(cargarFlota, 30000);
})();
