(function () {
  // Ruta base de la API del sistema
  const API_BASE = '/sigsm/api';

  // Elementos de la interfaz que muestran el nombre del usuario y el botón de logout
  const nombreUsuarioEl = document.querySelector('#nombreUsuario');
  const botonLogout = document.querySelector('#botonLogout');
  const infoUsuario = document.querySelector('#infoUsuario');

  // Roles permitidos para esta pantalla, leídos desde el body del HTML
  const rolesPermitidos = (document.body.dataset.roles || '')
    .split(',')
    .map((rol) => rol.trim())
    .filter(Boolean);

  // URL de login de respaldo si no se define en la página
  const loginUrl = document.body.dataset.loginUrl
    || botonLogout?.getAttribute('href')
    || 'index.html';

  // Redirige al usuario al login si no está autenticado
  function redirigirAlLogin() {
    window.location.href = loginUrl;
  }

  // Cierra la sesión en el backend y luego redirige al login
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

  // Devuelve el nombre del usuario guardado
  function obtenerNombreDisponible(nombre) {
    const nombreBase = (nombre && String(nombre).trim()) ? String(nombre).trim() : '';
    if (nombreBase) return nombreBase;

    const persistido = sessionStorage.getItem('usuario_nombre')
      || localStorage.getItem('usuario_nombre')
      || '';
    return persistido.trim() || 'Usuario';
  }

  // Muestra el nombre del usuario en la cabecera y crea el botón de logout
  function mostrarUsuario(nombre) {
    const nombreReal = obtenerNombreDisponible(nombre);
    sessionStorage.setItem('usuario_nombre', nombreReal);
    localStorage.setItem('usuario_nombre', nombreReal);

    if (nombreUsuarioEl) {
      nombreUsuarioEl.textContent = `Usuario: ${nombreReal}`;
    }

    const saludoFuncionario = document.getElementById('bienvenidaFuncionario');
    if (saludoFuncionario) {
      saludoFuncionario.textContent = `Bienvenido Funcionario/a ${nombreReal}`;
    }

    if (!infoUsuario) return;
    infoUsuario.innerHTML = '';
    const nombreEl = document.createElement('span');
    nombreEl.textContent = `Usuario: ${nombreReal}`;

    const boton = document.createElement('a');
    boton.href = loginUrl;
    boton.id = 'botonLogout';
    boton.className = 'btn-login';
    boton.textContent = 'Cerrar Sesión';
    boton.addEventListener('click', cerrarSesion);

    infoUsuario.append(nombreEl, boton);
  }

  // Consulta la sesión actual para verificar si el usuario está autenticado
  async function cargarSesion() {
    try {
      const respuesta = await fetch(`${API_BASE}/auth.php?action=me`, {
        credentials: 'include',
      });
      const resultado = await respuesta.json();

      // Si no está autenticado o no tiene el rol permitido, lo manda al login
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

  // Si la página ya trae un botón de logout, también lo conecta.
  if (botonLogout) {
    botonLogout.addEventListener('click', cerrarSesion);
  }

  cargarSesion();
})();
