(() => {
  const api = '/sigsm/api/documentos.php';
  const grid = document.querySelector('#gridDocumentos');
  const estado = document.querySelector('#estadoDocumentos');
  const id = new URLSearchParams(location.search).get('id');

  const ficha = document.querySelector('#fichaDocumento');
  const encuesta = document.querySelector('#enlaceEncuesta');

  if (id !== null) {
    document.querySelector('#tituloPagina').textContent = 'Detalle del documento';
    document.querySelector('#subtituloCatalogo').hidden = true;
    document.querySelector('#volverCatalogo').hidden = false;
    grid.hidden = true;
    encuesta.hidden = true;
  }

  async function cargar() {
    const consulta = id === null ? 'action=getall' : 'action=get&id=' + encodeURIComponent(id);
    const respuesta = await fetch(api + '?' + consulta);
    const resultado = await respuesta.json();
    if (!respuesta.ok || !resultado.success) throw new Error(resultado.message);
    if (id === null) {
      const documentos = resultado.data.flatMap((categoria) => categoria.documentos);
      grid.replaceChildren(...documentos.map((documento) => {
        const enlace = document.createElement('a');
        enlace.className = 'tarjeta-documento';
        enlace.href = '?id=' + encodeURIComponent(documento.id);
        const titulo = document.createElement('span');
        titulo.className = 'tarjeta-documento-titulo';
        titulo.textContent = documento.titulo;
        const categoria = document.createElement('span');
        categoria.className = 'tarjeta-documento-subtitulo';
        categoria.textContent = documento.categoria_nombre;
        enlace.append(titulo, categoria);
        return enlace;
      }));
      estado.textContent = documentos.length ? '' : 'Todavía no hay documentos disponibles.';
      return;
    }

    const documento = resultado.data;
    const titulo = document.createElement('h3');
    titulo.textContent = documento.titulo;
    const descripcion = document.createElement('p');
    descripcion.textContent = documento.descripcion;
    ficha.replaceChildren(titulo, descripcion);
    ficha.hidden = false;
    if (documento.archivo_url) {
      const leer = document.createElement('a');
      leer.href = documento.archivo_url;
      leer.textContent = 'Leer PDF';
      leer.className = 'btn-enviar';

      const urlDescarga = new URL(leer.href);
      urlDescarga.searchParams.set('descargar', '1');

      const descargar = document.createElement('a');
      descargar.href = urlDescarga.href;
      descargar.textContent = 'Descargar PDF';
      descargar.className = 'btn-secundario';

      const acciones = document.createElement('div');
      acciones.className = 'acciones-pdf';
      acciones.append(leer, descargar);
      ficha.append(acciones);
    }
    encuesta.href = '../Pacientes/Encuesta.html?servicio=' + encodeURIComponent(documento.categoria_nombre)
      + '&documento_id=' + encodeURIComponent(documento.id);
    encuesta.hidden = false;
    estado.textContent = '';
  }

  cargar().catch((error) => { estado.textContent = error.message; });
})();
