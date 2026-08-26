/**
 * Script compartido por las pantallas "Inicio ..." de cada rol.
 * Consulta la sesión activa (api/auth.php?action=me), muestra el nombre
 * real del usuario y conecta el botón "Cerrar Sesión" al backend.
 *
 * Requiere que la página tenga:
 *   <span id="nombreUsuario">Usuario: "Nombre"</span>
 *   <a id="botonLogout" href="index.html">Cerrar Sesión</a>
 */
const API_BASE = '../api';

const nombreUsuarioEl = document.querySelector('#nombreUsuario');
const botonLogout = document.querySelector('#botonLogout');

async function cargarSesion() {
  if (!nombreUsuarioEl) return;
  try {
    const respuesta = await fetch(`${API_BASE}/auth.php?action=me`, {
      credentials: 'include',
    });
    const resultado = await respuesta.json();

    if (resultado.autenticado) {
      nombreUsuarioEl.textContent = `Usuario: ${resultado.nombre}`;
    } else {
      // No hay sesión activa: volver a la pantalla de login
      // (usa el mismo destino que ya tiene el botón "Cerrar Sesión"/"Iniciar Sesión" en el HTML)
      window.location.href = botonLogout ? botonLogout.getAttribute('href') : 'index.html';
    }
  } catch (error) {
    nombreUsuarioEl.textContent = 'Usuario: (sin conexión)';
  }
}

if (botonLogout) {
  botonLogout.addEventListener('click', async (evento) => {
    evento.preventDefault();
    try {
      await fetch(`${API_BASE}/auth.php?action=logout`, {
        method: 'POST',
        credentials: 'include',
      });
    } finally {
      window.location.href = botonLogout.getAttribute('href') || 'index.html';
    }
  });
}

cargarSesion();
