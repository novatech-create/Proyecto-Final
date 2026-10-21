(function () {
  const API_URL = '/sigsm/api/ambulancias.php';
  const lista = document.querySelector('#listaTraslados');
  const historial = document.querySelector('#historialTraslados');
  const estadoEl = document.querySelector('#estadoTraslados');
  const totalEl = document.querySelector('#totalActivos');
  const prioridadAltaEl = document.querySelector('#totalAlta');
  const actualizacionEl = document.querySelector('#ultimaActualizacion');
  const enlaceNuevoTraslado = document.querySelector('#enlaceNuevoTraslado');

  let esAdministrativo = false;

  const acciones = {
    solicitado: [
      { Estado: 'pendiente', Texto: 'Aceptar solicitud' },
      { Estado: 'cancelado', Texto: 'Cancelar solicitud' },
    ],
    pendiente: [
      { Estado: 'en_camino', Texto: 'Registrar salida' },
      { Estado: 'cancelado', Texto: 'Cancelar traslado' },
    ],
    en_camino: [
      { Estado: 'en_destino', Texto: 'Registrar llegada al destino' },
      { Estado: 'cancelado', Texto: 'Cancelar traslado' },
    ],
    en_destino: [
      { Estado: 'en_retorno', Texto: 'Iniciar retorno' },
    ],
    en_retorno: [
      {
        Estado: 'finalizado',
        Texto: 'Registrar llegada a base y finalizar',
      },
    ],
  };

  const etiquetasEstado = {
    solicitado: 'Solicitado',
    pendiente: 'Pendiente',
    en_camino: 'En camino',
    en_destino: 'En destino',
    en_retorno: 'En retorno',
    finalizado: 'Finalizado',
    cancelado: 'Cancelado',
  };

  function escapar(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    })[caracter]);
  }

  function fechaLegible(valor) {
    if (!valor) return 'Sin registrar';

    const fecha = new Date(String(valor).replace(' ', 'T'));

    return Number.isNaN(fecha.getTime())
      ? String(valor)
      : fecha.toLocaleString('es-UY');
  }

  async function leerRespuesta(respuesta, mensajeError) {
    let resultado;

    try {
      resultado = await respuesta.json();
    } catch {
      throw new Error(
        `El servidor no devolvió JSON válido (HTTP ${respuesta.status}). ` +
        'Revisá los nombres de las columnas en ambulancias.php y el registro de errores de PHP.'
      );
    }

    if (!respuesta.ok || !resultado || !resultado.success) {
      throw new Error(resultado?.message || mensajeError);
    }

    return resultado;
  }

  function renderizar(traslados) {
    if (!traslados.length) {
      lista.innerHTML = '<p>No hay traslados activos.</p>';
      return;
    }

    lista.innerHTML = traslados.map((traslado) => {
      const accionesEstado = acciones[traslado.Estado] || [];

      const accionesPermitidas = esAdministrativo
        ? accionesEstado
        : accionesEstado.filter(
            (accion) =>
              accion.Estado !== 'pendiente' &&
              accion.Estado !== 'cancelado'
          );

      const botones = accionesPermitidas.map((accion) => `
        <button
          type="button"
          class="btn-accion"
          data-id="${Number(traslado.IdTraslado)}"
          data-estado="${escapar(accion.Estado)}"
        >${escapar(accion.Texto)}</button>
      `).join(' ');

      return `
        <article class="tarjeta-traslado estado-${escapar(traslado.Estado)}">
          <h3>
            ${escapar(traslado.MatriculaFK)} ·
            ${escapar(etiquetasEstado[traslado.Estado] || traslado.Estado)}
          </h3>

          <p><strong>Motivo:</strong> ${escapar(traslado.Motivo)}</p>

          <p>
            <strong>Elemento:</strong>
            ${escapar(traslado.TipoElemento)} ·
            ${escapar(traslado.NroReferencia || 'Sin referencia')}
          </p>

          ${traslado.DatosPaciente ? `
            <p>
              <strong>Datos del paciente:</strong>
              ${escapar(traslado.DatosPaciente)}
            </p>
          ` : ''}

          <p>
            <strong>Recorrido:</strong>
            ${escapar(traslado.PuntoOrigen)} a
            ${escapar(traslado.PuntoDestino)}
          </p>

          <p>
            <strong>Conductor:</strong>
            ${escapar(traslado.NombreChofer)} ·
            <strong>Enfermero:</strong>
            ${escapar(traslado.NombreEnfermero || 'No asignado')}
          </p>

          <p>
            <strong>Médico:</strong>
            ${escapar(traslado.NombreMedico || 'No asignado')} ·
            <strong>Administrativo:</strong>
            ${escapar(traslado.NombreAdministrativo)}
          </p>

          <p>
            <strong>Salida:</strong>
            ${escapar(fechaLegible(traslado.HoraSalida))} ·
            <strong>Llegada estimada:</strong>
            ${escapar(fechaLegible(traslado.HoraLlegadaEst))}
          </p>

          <p>
            <strong>Llegada al punto:</strong>
            ${escapar(fechaLegible(traslado.HoraLlegadaDestino))}
          </p>

          <p>
            <strong>Inicio del regreso:</strong>
            ${escapar(fechaLegible(traslado.HoraRetorno))}
          </p>

          <p>
            <strong>Llegada a base / fin:</strong>
            ${escapar(fechaLegible(traslado.HoraLlegadaBase))}
          </p>

          <p>
            <strong>Solicitud:</strong>
            ${escapar(traslado.ViaSolicitud)}
          </p>

          <div class="acciones-traslado">${botones}</div>
        </article>
      `;
    }).join('');
  }

  function renderizarHistorial(traslados) {
    historial.innerHTML = `
      <div class="tabla-desborde">
        <table>
          <thead>
            <tr>
              <th>N.º</th>
              <th>Vehículo</th>
              <th>Elemento / motivo</th>
              <th>Origen</th>
              <th>Destino</th>
              <th>Conductor</th>
              <th>Salida</th>
              <th>Llegada al punto</th>
              <th>Inicio del regreso</th>
              <th>Llegada a base / fin</th>
              <th>Estado</th>
            </tr>
          </thead>

          <tbody>
            ${traslados.length ? traslados.map((traslado) => `
              <tr>
                <td>${Number(traslado.IdTraslado)}</td>
                <td>${escapar(traslado.MatriculaFK)}</td>
                <td>
                  ${escapar(traslado.TipoElemento)} ·
                  ${escapar(traslado.NroReferencia || traslado.Motivo)}
                </td>
                <td>${escapar(traslado.PuntoOrigen)}</td>
                <td>${escapar(traslado.PuntoDestino)}</td>
                <td>${escapar(traslado.NombreChofer)}</td>
                <td>${escapar(fechaLegible(traslado.HoraSalida))}</td>
                <td>${escapar(fechaLegible(traslado.HoraLlegadaDestino))}</td>
                <td>${escapar(fechaLegible(traslado.HoraRetorno))}</td>
                <td>${escapar(fechaLegible(traslado.HoraLlegadaBase))}</td>
                <td>
                  ${escapar(etiquetasEstado[traslado.Estado] || traslado.Estado)}
                </td>
              </tr>
            `).join('') : `
              <tr>
                <td class="historial-vacio" colspan="11">
                  Aún no hay traslados finalizados o cancelados.
                </td>
              </tr>
            `}
          </tbody>
        </table>
      </div>
    `;
  }

  async function cargarTraslados() {
    estadoEl.textContent = 'Cargando traslados...';

    try {
      const respuesta = await fetch(`${API_URL}?action=activos`, {
        credentials: 'include',
      });

      const resultado = await leerRespuesta(
        respuesta,
        'No se pudieron cargar los traslados.'
      );

      const respuestaHistorial = await fetch(
        `${API_URL}?action=historial`,
        { credentials: 'include' }
      );

      const resultadoHistorial = await leerRespuesta(
        respuestaHistorial,
        'No se pudo cargar el historial.'
      );

      if (
        !Array.isArray(resultado.Data) ||
        !Array.isArray(resultadoHistorial.Data)
      ) {
        throw new Error(
          'El servidor devolvió una lista de traslados con formato incorrecto.'
        );
      }

      const traslados = resultado.Data;

      esAdministrativo =
        resultado.EsAdministrativo === true ||
        Number(resultado.EsAdministrativo) === 1;

      enlaceNuevoTraslado.hidden = !esAdministrativo;

      totalEl.textContent = traslados.length;

      prioridadAltaEl.textContent = traslados.filter(
        (item) => ['solicitado', 'pendiente'].includes(item.Estado)
      ).length;

      const llegadasEstimadas = traslados
        .map((item) => item.HoraLlegadaEst)
        .filter(Boolean)
        .sort();

      actualizacionEl.textContent = llegadasEstimadas.length
        ? fechaLegible(llegadasEstimadas[0])
        : '-';

      renderizar(traslados);
      renderizarHistorial(resultadoHistorial.Data);

      estadoEl.textContent = '';
    } catch (error) {
      estadoEl.textContent = error.message;
    }
  }

  lista.addEventListener('click', async (evento) => {
    const boton = evento.target.closest(
      'button[data-id][data-estado]'
    );

    if (!boton || boton.disabled) return;

    if (!window.confirm(
      `¿Confirmar: ${boton.textContent.trim()}?`
    )) {
      return;
    }

    boton.disabled = true;

    try {
      const respuesta = await fetch(
        `${API_URL}?action=actualizar-estado`,
        {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            IdTraslado: Number(boton.dataset.id),
            Estado: boton.dataset.estado,
          }),
        }
      );

      await leerRespuesta(
        respuesta,
        'No se pudo actualizar el traslado.'
      );

      await cargarTraslados();
    } catch (error) {
      estadoEl.textContent = error.message;
    } finally {
      boton.disabled = false;
    }
  });

  cargarTraslados();
  window.setInterval(cargarTraslados, 30000);
})();