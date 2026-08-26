(function () {
const API_BASE = '/sigsm/api';

const nombreUsuarioEl = document.querySelector('#nombreUsuario');
const botonLogout = document.querySelector('#botonLogout');
const infoUsuario = document.querySelector('#infoUsuario');
const rolesPermitidos = (document.body.dataset.roles || '')
  .split(',')
  .map((rol) => rol.trim())
  .filter(Boolean);
const loginUrl = document.body.dataset.loginUrl
  || botonLogout?.getAttribute('href')
  || 'index.html';

function redirigirAlLogin() {
  window.location.href = loginUrl;
}

async function cerrarSesion(evento) {
  evento.preventDefault();
  try {
    await fetch(`${API_BASE}/auth.php?action=logout`, {
      method: 'POST',
      credentials: 'include',
    });
  } finally {
    redirigirAlLogin();
  }
}

function mostrarUsuario(nombre) {
  if (nombreUsuarioEl) {
    nombreUsuarioEl.textContent = `Usuario: ${nombre}`;
    return;
  }

  if (!infoUsuario) return;
  infoUsuario.innerHTML = '';
  const nombreEl = document.createElement('span');
  nombreEl.textContent = `Usuario: ${nombre}`;
  const boton = document.createElement('a');
  boton.href = loginUrl;
  boton.id = 'botonLogout';
  boton.className = 'btn-login';
  boton.textContent = 'Cerrar Sesión';
  boton.addEventListener('click', cerrarSesion);
  infoUsuario.append(nombreEl, boton);
}

async function cargarSesion() {
  try {
    const respuesta = await fetch(`${API_BASE}/auth.php?action=me`, {
      credentials: 'include',
    });
    const resultado = await respuesta.json();

    if (!resultado.autenticado || (rolesPermitidos.length > 0
      && !rolesPermitidos.includes(resultado.rol))) {
      redirigirAlLogin();
      return;
    }

    mostrarUsuario(resultado.nombre);
  } catch (error) {
    redirigirAlLogin();
  }
}

if (botonLogout) {
  botonLogout.addEventListener('click', cerrarSesion);
}

cargarSesion();
})();
