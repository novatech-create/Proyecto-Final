const API_BASE = '../api';

const infoUsuario = document.querySelector('#infoUsuario');

async function cargarSesionPaciente() {
  if (!infoUsuario) return;
  try {
    const respuesta = await fetch(`${API_BASE}/auth.php?action=me`, {
      credentials: 'include',
    });
    const resultado = await respuesta.json();

    if (resultado.autenticado) {
      infoUsuario.innerHTML = `
        <span id="nombreUsuario">Usuario: ${resultado.nombre}</span>
        <a href="index.html" id="botonLogout" class="btn-login">Cerrar Sesión</a>
      `;
      document.querySelector('#botonLogout').addEventListener('click', async (evento) => {
        evento.preventDefault();
        try {
          await fetch(`${API_BASE}/auth.php?action=logout`, { method: 'POST', credentials: 'include' });
        } finally {
          window.location.href = 'index.html';
        }
      });
    }
    // Si no está autenticado, se deja el enlace "Iniciar Sesión" que ya trae el HTML.
  } catch (error) {
    // Sin conexión con la API: se mantiene el enlace de login por defecto.
  }
}

cargarSesionPaciente();
