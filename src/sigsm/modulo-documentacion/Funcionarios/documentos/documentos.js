(function () {
  // Ruta base de la API del sistema
  const API_BASE = '/sigsm/api';

  const estadoDocumentos = document.querySelector('#estadoDocumentos');
  const contenedorCategorias = document.querySelector('#contenedorCategorias');
  const botonNuevoDocumento = document.querySelector('#botonNuevoDocumento');
  const modalNuevoDocumento = document.querySelector('#modalNuevoDocumento');
  const formularioNuevoDocumento = document.querySelector('#formularioNuevoDocumento');
  const campoCategoria = document.querySelector('#campoCategoria');
  const botonCancelarDocumento = document.querySelector('#botonCancelarDocumento');
  const mensajeFormularioDocumento = document.querySelector('#mensajeFormularioDocumento');

  // Carga la lista de documentos desde la API
  async function cargarDocumentos() {
    // Muestra el estado de carga mientras consulta la API
    estadoDocumentos.textContent = 'cargando documentos';
    contenedorCategorias.innerHTML = '';

    try {
      // Llamada a la API para obtener todos los documentos activos
      const respuesta = await fetch(`${API_BASE}/documentos.php?action=getall`, {
        credentials: 'include',
      });
      const resultado = await respuesta.json();

      // Si la respuesta no salió bine, entonces lanza un error para mostrarlo
      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudieron cargar los documentos.');
      }

      // Renderiza la lista y llena el selector de categorías
      renderizarCategorias(resultado.data);
      llenarSelectCategorias(resultado.data);
      estadoDocumentos.textContent = '';
    } catch (error) {
      estadoDocumentos.textContent = error.message;
    }
  }

  // Genera la vista HTML con los documentos agrupados por categoría
  function renderizarCategorias(categorias) {
    if (categorias.length === 0) {
      contenedorCategorias.innerHTML = '<p>No hay documentos cargados todavía</p>';
      return;
    }

    // Por cada categoría crea un bloque y dentro mete sus documentos
    contenedorCategorias.innerHTML = categorias.map((categoria) => `
      <div class="categoria">
        <h3>${escapeHtml(categoria.nombre)} <span class="badge">${categoria.documentos.length}</span></h3>
        <ul>
          ${categoria.documentos.map((documento) => `
            <li>
              <span>${escapeHtml(documento.titulo)}</span>
              <span class="fecha">${formatearFecha(documento.fecha_actualizacion)}</span>
              <a href="PrevistaDocumentos/AdmDocumento.html?id=${documento.id}" class="btn-ver">Ver</a>
              <button type="button" class="btn-eliminar" data-documento-id="${documento.id}">Eliminar</button>
            </li>
          `).join('')}
        </ul>
      </div>
    `).join('');
  }

  // Llena el selector del modal con las categorías disponibles
  function llenarSelectCategorias(categorias) {
    campoCategoria.innerHTML = categorias
      .map((categoria) => `<option value="${categoria.id}">${escapeHtml(categoria.nombre)}</option>`)
      .join('');
  }

  // Formatea la fecha para mostrarla en formato legible para el usuario
  function formatearFecha(fechaIso) {
    const fecha = new Date(fechaIso.replace(' ', 'T'));
    if (Number.isNaN(fecha.getTime())) return fechaIso;
    return fecha.toLocaleDateString('es-UY');
  }

  // Evita que textos con HTML aparezcan en la página y los muestra como texto plano
  function escapeHtml(valor) {
    return String(valor)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  // Elimina un documento mediante la API de documentos
  async function eliminarDocumento(id, boton) {
    if (!window.confirm('Está seguro que desea eliminar este documento?')) return;

    boton.disabled = true;

    try {
      const respuesta = await fetch(`${API_BASE}/documentos.php?action=delete`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      });
      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudo eliminar el documento');
      }

      // Si se elimina, vuelve a cargar la lista actualizada
      await cargarDocumentos();
    } catch (error) {
      window.alert(error.message);
      boton.disabled = false;
    }
  }

  // Abre el modal para crear un documento nuevo
  botonNuevoDocumento.addEventListener('click', () => {
    mensajeFormularioDocumento.classList.remove('visible');
    mensajeFormularioDocumento.textContent = '';
    formularioNuevoDocumento.reset();
    modalNuevoDocumento.classList.remove('oculto');
  });

  // Cierra el modal de creación
  botonCancelarDocumento.addEventListener('click', () => {
    modalNuevoDocumento.classList.add('oculto');
  });

  // Escucha clicks dentro de la lista para detectar si se presionó "Eliminar"
  contenedorCategorias.addEventListener('click', (evento) => {
    const boton = evento.target.closest('.btn-eliminar');
    if (!boton) return;

    eliminarDocumento(Number(boton.dataset.documentoId), boton);
  });

  // Envía el formulario para crear un documento nuevo
  formularioNuevoDocumento.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const datos = new FormData(formularioNuevoDocumento);

    try {
      const respuesta = await fetch(`${API_BASE}/documentos.php?action=create`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          categoria_id: datos.get('categoria_id'),
          titulo: datos.get('titulo'),
          especialista: datos.get('especialista'),
          descripcion: datos.get('descripcion'),
          archivo_url: datos.get('archivo_url'),
        }),
      });
      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudo guardar el documento.');
      }

      // Si se guarda correctamente, cierra el modal y recarga la lista
      modalNuevoDocumento.classList.add('oculto');
      await cargarDocumentos();
    } catch (error) {
      mensajeFormularioDocumento.textContent = error.message;
      mensajeFormularioDocumento.classList.add('visible');
    }
  });

  // Ejecuta la carga inicial al abrir la página
  cargarDocumentos();
})();
