(function () {
const API_BASE = '/sigsm/api';

const formulario = document.querySelector('#formularioLogin');
const campoUsuario = document.querySelector('#usuario');
const campoClave = document.querySelector('#clave');
const botonIngresar = document.querySelector('#botonIngresar');
const mensajeError = document.querySelector('#mensajeError');

const RUTAS_POR_ROL = {
  funcionario: 'inicio_funcionario.html',
  paciente: 'inicio_pacientes.html',
};

formulario.addEventListener('submit', async (evento) => {
  evento.preventDefault();

  mensajeError.textContent = '';
  mensajeError.classList.remove('visible');
  botonIngresar.disabled = true;
  botonIngresar.textContent = 'Ingresando...';

  try {
    const respuesta = await fetch(`${API_BASE}/auth.php?action=login`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        usuario: campoUsuario.value.trim(),
        clave: campoClave.value,
      }),
    });
    const resultado = await respuesta.json();

    if (!respuesta.ok || !resultado.success) {
      throw new Error(resultado.message || 'No se pudo iniciar sesión.');
    }

    if (resultado.nombre) {
      const nombreUsuario = String(resultado.nombre).trim();
      sessionStorage.setItem('usuario_nombre', nombreUsuario);
      localStorage.setItem('usuario_nombre', nombreUsuario);
    }

    const destino = RUTAS_POR_ROL[resultado.rol] || 'index.html';
    window.location.href = destino;
  } catch (error) {
    mensajeError.textContent = error.message;
    mensajeError.classList.add('visible');
  } finally {
    botonIngresar.disabled = false;
    botonIngresar.textContent = 'INGRESAR';
  }
});
})();
