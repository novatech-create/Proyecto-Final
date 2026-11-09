(function () {
  // Ruta base de la API del proyecto
  const API_BASE = '/sigsm/api';

  const estadoDocumento = document.querySelector('#estadoDocumento');
  const tarjetaDocumento = document.querySelector('#tarjetaDocumento');
  const contenedorQr = document.querySelector('#contenedorQr');
  const qrDocumento = document.querySelector('#qrDocumento');
  const docCategoria = document.querySelector('#docCategoria');
  const docTitulo = document.querySelector('#docTitulo');
  const docDescripcion = document.querySelector('#docDescripcion');
  const docFecha = document.querySelector('#docFecha');
  const docPeso = document.querySelector('#docPeso');
  const enlaceDescarga = document.querySelector('#descargarDocumento');
  const enlaceVista = document.querySelector('#verDocumento');
  const accionesDocumento = document.querySelector('.acciones-documento');

  // Carga un documento específico según el id que llega por la URL
  async function cargarDocumento() {
    const parametros = new URLSearchParams(window.location.search);
    const id = parametros.get('id');

    if (!id) {
      estadoDocumento.textContent = 'Falta el identificador del documento en el enlace del QR.';
      return;
    }

    try {
      // Peide el documento por id a la API
      const respuesta = await fetch(`${API_BASE}/documentos.php?action=vista_interna&id=${encodeURIComponent(id)}`);
      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se encontró el documento.');
      }

      const documento = resultado.data;

      // Completa la tarjeta con los datos del documento
      docCategoria.textContent = documento.categoria_nombre;
      docTitulo.textContent = documento.titulo;
      docDescripcion.textContent = documento.descripcion;
      docFecha.textContent = `Actualizado: ${formatearFecha(documento.fecha_actualizacion)}`;
      docPeso.textContent = `PDF ${documento.peso_kb} KB`;

      // Genera la URL para que el QR lleve a la página de documentos 
      const urlDocumento = `${window.location.origin}/sigsm/presentacion/modulo-documentacion/Documentos/documento.html?id=${documento.id}`;
      const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(urlDocumento)}`;
      if (qrDocumento && documento.estado_revision === 'aprobado') {
        qrDocumento.src = qrUrl;
        qrDocumento.alt = `QR para ${documento.titulo}`;
      }
      if (contenedorQr && documento.estado_revision === 'aprobado') {
        contenedorQr.classList.remove('oculto');
      }

      if (documento.archivo_url) {
        const urlPdf = new URL(documento.archivo_url, window.location.href);
        enlaceVista.href = urlPdf.href;

        urlPdf.searchParams.set('descargar', '1');
        enlaceDescarga.href = urlPdf.href;
        accionesDocumento.classList.remove('oculto');
      }

      estadoDocumento.textContent = '';
      tarjetaDocumento.classList.remove('oculto');
    } catch (error) {
      estadoDocumento.textContent = error.message;
    }
  }

  // Formatea la fecha para mostrarla al usuario
  function formatearFecha(fechaIso) {
    const fecha = new Date(fechaIso.replace(' ', 'T'));
    if (Number.isNaN(fecha.getTime())) return fechaIso;
    return fecha.toLocaleDateString('es-UY');
  }

  // Se ejecuta cuando carga la página
  cargarDocumento();
})();
