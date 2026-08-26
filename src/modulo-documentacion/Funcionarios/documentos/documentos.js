const API_BASE = '/sigsm/api';

const estadoDocumentos = document.querySelector('#estadoDocumentos');
const contenedorCategorias = document.querySelector('#contenedorCategorias');
const botonNuevoDocumento = document.querySelector('#botonNuevoDocumento');
const modalNuevoDocumento = document.querySelector('#modalNuevoDocumento');
const formularioNuevoDocumento = document.querySelector('#formularioNuevoDocumento');
const campoCategoria = document.querySelector('#campoCategoria');
const botonCancelarDocumento = document.querySelector('#botonCancelarDocumento');
const mensajeFormularioDocumento = document.querySelector('#mensajeFormularioDocumento');

async function cargarDocumentos() {
  estadoDocumentos.textContent = 'Cargando documentos...';
  contenedorCategorias.innerHTML = '';

  try {
    const respuesta = await fetch(`${API_BASE}/documentos.php?action=getall`, {
      credentials: 'include',
    });
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
      throw new Error(resultado.message || 'No se pudieron cargar los documentos.');
    }

    renderizarCategorias(resultado.data);
    llenarSelectCategorias(resultado.data);
    estadoDocumentos.textContent = '';
  } catch (error) {
    estadoDocumentos.textContent = error.message;
  }
}

function renderizarCategorias(categorias) {
  if (categorias.length === 0) {
    contenedorCategorias.innerHTML = '<p>No hay documentos cargados todavía.</p>';
    return;
  }

  contenedorCategorias.innerHTML = categorias.map((categoria) => `
    <div class="categoria">
      <h3>${escapeHtml(categoria.nombre)} <span class="badge">${categoria.documentos.length}</span></h3>
      <ul>
        ${categoria.documentos.map((documento) => `
          <li>
            <span>${escapeHtml(documento.titulo)}</span>
            <span class="fecha">${formatearFecha(documento.fecha_actualizacion)}</span>
            <a href="../../Documentos/documento.html?id=${documento.id}" class="btn-ver">Ver</a>
          </li>
        `).join('')}
      </ul>
    </div>
  `).join('');
}

function llenarSelectCategorias(categorias) {
  campoCategoria.innerHTML = categorias
    .map((categoria) => `<option value="${categoria.id}">${escapeHtml(categoria.nombre)}</option>`)
    .join('');
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

botonNuevoDocumento.addEventListener('click', () => {
  mensajeFormularioDocumento.classList.remove('visible');
  mensajeFormularioDocumento.textContent = '';
  formularioNuevoDocumento.reset();
  modalNuevoDocumento.classList.remove('oculto');
});

botonCancelarDocumento.addEventListener('click', () => {
  modalNuevoDocumento.classList.add('oculto');
});

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

    modalNuevoDocumento.classList.add('oculto');
    await cargarDocumentos();
  } catch (error) {
    mensajeFormularioDocumento.textContent = error.message;
    mensajeFormularioDocumento.classList.add('visible');
  }
});

cargarDocumentos();
