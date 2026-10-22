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
      tablaPersonal.innerHTML = '<tr><td colspan="5">Todavía no hay cuentas vinculadas al módulo.</td></tr>';
      return;
    }
    tablaPersonal.innerHTML = personal.map((persona) => {
      const perfiles = [
        persona.EsChofer && 'Chofer',
        persona.EsEnfermero && 'Enfermero',
        persona.EsMedico && 'Médico',
        persona.EsAdministrativo && 'Administrativo',
      ].filter(Boolean).join(', ');
      const estado = Number(persona.Activo) === 1 ? 'Activo' : 'Desactivado';
      return `<tr>
        <td>${escapar(persona.Nombre)}</td>
        <td>${escapar(persona.Documento)}</td>
        <td>${escapar(perfiles)}</td>
        <td>${estado}</td>
        <td>${Number(persona.Activo) === 1 ? `<button type="button" data-desactivar="${Number(persona.IdUsuario)}">Desactivar</button>` : ''}</td>
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
          Documento: formData.get('Documento').trim(),
          Nombre: formData.get('Nombre').trim(),
          Clave: formData.get('Clave'),
          Perfiles: perfiles,
          TipoLicencia: formData.get('TipoLicencia').trim(),
          Especialidad: formData.get('Especialidad').trim(),
          Telefonos: formData.get('Telefonos').split(/\r?\n/).map((telefono) => telefono.trim()).filter(Boolean),
        }),
      });
      formularioPersonal.reset();
      mensaje.textContent = 'Cuenta y perfiles creados.';
      await cargarAdministracion();
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
      mensaje.textContent = 'Vehículo guardado.';
      await cargarAdministracion();
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
      mensaje.textContent = 'Ruta guardada.';
      await cargarAdministracion();
    } catch (error) {
      mensaje.textContent = error.message;
    } finally {
      boton.disabled = false;
    }
  });

  tablaPersonal.addEventListener('click', async (evento) => {
    const boton = evento.target.closest('button[data-desactivar]');
    if (!boton || !window.confirm('¿Desactivar el acceso de esta cuenta?')) return;
    boton.disabled = true;
    try {
      await request('desactivar-personal', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ IdUsuario: Number(boton.dataset.desactivar) }),
      });
      mensaje.textContent = 'Cuenta desactivada.';
      await cargarAdministracion();
    } catch (error) {
      mensaje.textContent = error.message;
      boton.disabled = false;
    }
  });

  cargarAdministracion();
})();
