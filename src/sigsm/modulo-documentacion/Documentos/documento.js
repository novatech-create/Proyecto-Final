(function () {
  // Base de la API del sistema
  const API_BASE = '/sigsm/api';

  // Elementos del HTML donde se renderiza la lista de documentos
  const gridDocumentos = document.getElementById('gridDocumentos');
  const estadoDocumentos = document.getElementById('estadoDocumentos');

  // Trae todos los documentos desde la API
  async function cargarDocumentos() {
    try {
      const respuesta = await fetch(`${API_BASE}/documentos.php?action=getall`);
      const resultado = await respuesta.json();

      // Si la API respondió con error, tira una excepción para mostrar el mensaje
      if (!respuesta.ok || !resultado.success || !Array.isArray(resultado.data)) {
        throw new Error(resultado.message || 'No se pudieron cargar los documentos.');
      }

      // Aplana la estructura de categorías para tener una lista simple de documentos
      const documentos = resultado.data.flatMap((categoria) => {
        const items = Array.isArray(categoria.documentos) ? categoria.documentos : [];
        return items.map((documento) => ({
          ...documento,
          categoria_nombre: documento.categoria_nombre || categoria.nombre,
        }));
      });

      // Si no hay documentos dice que no hay documentos
      if (!documentos.length) {
        gridDocumentos.innerHTML = '<p class="estado">No hay documentos disponibles.</p>';
        estadoDocumentos.textContent = '';
        return;
      }

      // Genera el HTML de cada tarjeta de documento.
      gridDocumentos.innerHTML = documentos.map((documento) => `
        <button class="tarjeta-documento" data-id="${documento.id}" type="button">
          <span class="tarjeta-documento-titulo">${documento.titulo}</span>
          <span class="tarjeta-documento-subtitulo">${documento.categoria_nombre}</span>
        </button>
      `).join('');

      estadoDocumentos.textContent = '';
    } catch (error) {
      estadoDocumentos.textContent = error.message;
    }
  }

  // Se ejecuta al cargar la página.
  cargarDocumentos();
})();
