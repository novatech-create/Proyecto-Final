(function () {
  const API_URL = '/sigsm/api/ambulancias.php';
  const formulario = document.querySelector('#formularioTraslado');
  const mensajeError = document.querySelector('#mensajeErrorTraslado');
  const botonEnviar = formulario.querySelector('[type="submit"]');
  const campoTipoElemento = formulario.elements.namedItem('TipoElemento');
  const campoDatosElemento = formulario.elements.namedItem('DatosPaciente');

  function agregarOpciones(campo, registros, valor, etiqueta, placeholder) {
    campo.replaceChildren(new Option(placeholder, ''));
    for (const registro of registros) {
      const opcion = new Option(etiqueta(registro), registro[valor]);
      campo.add(opcion);
    }
  }

  async function cargarCatalogos() {
    try {
      const respuesta = await fetch(`${API_URL}?action=catalogos`, { credentials: 'include' });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudieron cargar los datos del traslado.');
      }
      agregarOpciones(
        formulario.elements.namedItem('IdChoferFK'), resultado.Choferes,
        'IdFuncionario', (persona) => `${persona.Nombre} · Licencia ${persona.TipoLicencia}`,
        'Seleccione conductor...',
      );
      agregarOpciones(
        formulario.elements.namedItem('IdEnfermeroFK'), resultado.Enfermeros,
        'IdFuncionario', (persona) => `${persona.Nombre} · ${persona.Especialidad}`,
        'Sin acompañante',
      );
      agregarOpciones(
        formulario.elements.namedItem('IdMedicoFK'), resultado.Medicos,
        'IdFuncionario', (persona) => persona.Nombre, 'Sin médico asignado',
      );
      agregarOpciones(
        formulario.elements.namedItem('MatriculaFK'), resultado.Vehiculos,
        'Matricula', (vehiculo) => `${vehiculo.Matricula} · ${vehiculo.TipoVehiculo}${Number(vehiculo.RequiereEspecializado) ? ' · Especializado' : ''}`,
        'Seleccione vehículo...',
      );
      agregarOpciones(
        formulario.elements.namedItem('IdRutaFK'), resultado.Rutas,
        'IdRuta', (ruta) => `${ruta.PuntoOrigen} a ${ruta.PuntoDestino}`,
        'Seleccione ruta...',
      );
    } catch (error) {
      mensajeError.textContent = error.message;
      botonEnviar.disabled = true;
    }
  }

  campoTipoElemento.addEventListener('change', () => {
    const esPaciente = campoTipoElemento.value === 'paciente';
    campoDatosElemento.required = esPaciente;
    campoDatosElemento.setCustomValidity('');
    campoDatosElemento.labels[0].textContent = esPaciente
      ? 'Datos del paciente (mínimo necesario)'
      : 'Descripción del elemento';
  });
  campoTipoElemento.dispatchEvent(new Event('change'));

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!formulario.reportValidity()) return;

    mensajeError.textContent = '';
    botonEnviar.disabled = true;
    try {
      const campos = new FormData(formulario);
      const datos = Object.fromEntries(campos.entries());
      for (const campo of ['IdEnfermeroFK', 'IdMedicoFK', 'NroReferencia', 'DatosPaciente', 'HoraLlegadaEst']) {
        if (datos[campo] === '') datos[campo] = null;
      }
      const respuesta = await fetch(`${API_URL}?action=crear-traslado`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();
      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'Revise los datos del traslado.');
      }
      window.location.href = 'Panel_traslados_activos.html';
    } catch (error) {
      mensajeError.textContent = error.message;
      botonEnviar.disabled = false;
    }
  });

  cargarCatalogos();
})();
