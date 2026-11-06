(function () {
  const API_URL = '/sigsm/api/ambulancias.php';
  const mensaje = document.querySelector('#mensajeAdmin');
  const tablaPersonal = document.querySelector('#tablaPersonal');
  const listaVehiculos = document.querySelector('#listaVehiculos');
  const listaRutas = document.querySelector('#listaRutas');
  const formularioPersonal = document.querySelector('#formularioPersonal');
  const formularioVehiculo = document.querySelector('#formularioVehiculo');
  const formularioRuta = document.querySelector('#formularioRuta');

  function escapar(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[caracter]);
  }

  async function request(action, options = {}) {
    const respuesta = await fetch(`${API_URL}?action=${action}`, {
      credentials: 'include',
      ...options,
    });
    const resultado = await respuesta.json();
    if (!respuesta.ok || !resultado.success) {
      throw new Error(resultado.message || 'La solicitud no pudo completarse.');
    }
    return resultado;
  }

  function renderizarPersonal(personal) {
    if (!personal.length) {
      tablaPersonal.innerHTML = '<tr><td colspan="2">Todavía no hay personal registrado.</td></tr>';
      return;
    }
    tablaPersonal.innerHTML = personal.map((persona) => {
      const perfiles = [
        persona.EsChofer && 'Chofer',
        persona.EsEnfermero && 'Enfermero',
        persona.EsMedico && 'Médico',
      ].filter(Boolean).join(', ');
      return `<tr>
        <td>${escapar(persona.Nombre)}</td>
        <td>${escapar(perfiles)}</td>
      </tr>`;
    }).join('');
  }

  function renderizarCatalogos(vehiculos, rutas) {
    listaVehiculos.innerHTML = vehiculos.length
      ? vehiculos.map((vehiculo) => `<li>${escapar(vehiculo.Matricula)} · ${escapar(vehiculo.TipoVehiculo)}${Number(vehiculo.RequiereEspecializado) ? ' · Requiere especialidad' : ''}</li>`).join('')
      : '<li>No hay vehículos registrados.</li>';
    listaRutas.innerHTML = rutas.length
      ? rutas.map((ruta) => `<li>${escapar(ruta.PuntoOrigen)} a ${escapar(ruta.PuntoDestino)}${Number(ruta.RutinaBase) ? ' · Habitual' : ''}</li>`).join('')
      : '<li>No hay rutas registradas.</li>';
  }

  async function cargarAdministracion() {
    try {
      const datos = await request('administracion');
      renderizarPersonal(datos.Personal);
      renderizarCatalogos(datos.Vehiculos, datos.Rutas);
      mensaje.textContent = '';
    } catch (error) {
      mensaje.textContent = error.message;
    }
  }

  formularioPersonal.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!formularioPersonal.reportValidity()) return;
    const formData = new FormData(formularioPersonal);
    const perfiles = formData.getAll('Perfil');
    if (!perfiles.length) {
      mensaje.textContent = 'Seleccione al menos un perfil.';
      return;
    }

    const boton = formularioPersonal.querySelector('[type="submit"]');
    boton.disabled = true;
    mensaje.textContent = '';
    try {
      await request('crear-personal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          Nombre: formData.get('Nombre').trim(),
          Perfiles: perfiles,
          TipoLicencia: formData.get('TipoLicencia').trim(),
          Especialidad: formData.get('Especialidad').trim(),
          Telefonos: formData.get('Telefonos').split(/\r?\n/).map((telefono) => telefono.trim()).filter(Boolean),
        }),
      });
      formularioPersonal.reset();
      await cargarAdministracion();
      mensaje.textContent = 'Personal registrado. Ya puedes seleccionarlo al crear un traslado.';
    } catch (error) {
      mensaje.textContent = error.message;
    } finally {
      boton.disabled = false;
    }
  });

  formularioVehiculo.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!formularioVehiculo.reportValidity()) return;
    const formData = new FormData(formularioVehiculo);
    const boton = formularioVehiculo.querySelector('[type="submit"]');
    boton.disabled = true;
    try {
      await request('guardar-vehiculo', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          Matricula: formData.get('Matricula').trim().toUpperCase(),
          TipoVehiculo: formData.get('TipoVehiculo').trim(),
          RequiereEspecializado: formData.has('RequiereEspecializado'),
        }),
      });
      formularioVehiculo.reset();
      await cargarAdministracion();
      mensaje.textContent = 'Vehículo guardado.';

    } catch (error) {
      mensaje.textContent = error.message;
    } finally {
      boton.disabled = false;
    }
  });

  formularioRuta.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!formularioRuta.reportValidity()) return;
    const formData = new FormData(formularioRuta);
    const boton = formularioRuta.querySelector('[type="submit"]');
    boton.disabled = true;
    try {
      await request('crear-ruta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          PuntoOrigen: formData.get('PuntoOrigen').trim(),
          PuntoDestino: formData.get('PuntoDestino').trim(),
          RutinaBase: formData.has('RutinaBase'),
        }),
      });
      formularioRuta.reset();
      await cargarAdministracion();
      mensaje.textContent = 'Ruta guardada.';

    } catch (error) {
      mensaje.textContent = error.message;
    } finally {
      boton.disabled = false;
    }
  });


  cargarAdministracion();
})();
