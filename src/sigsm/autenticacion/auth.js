(function () {
  // Ruta base de la API del sistema
  const API_BASE = '/sigsm/api';


  const formulario = document.querySelector('#formularioLogin');
  const campoUsuario = document.querySelector('#usuario');
  const campoClave = document.querySelector('#clave');
  const botonIngresar = document.querySelector('#botonIngresar');
  const mensajeError = document.querySelector('#mensajeError');

  // Mapa de rol a pantalla de destino.
  // Si el usuario es funcionario, va al inicio del funcionario; si es paciente, va a su inicio
  const RUTAS_POR_ROL = {
    funcionario: 'inicio_funcionario.html',
    paciente: 'inicio_pacientes.html',
  };

  // Cuando el usuario envía el formulario, se ejecuta esta lógica
  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    // Limpia mensajes y deshabilita el botón mientras se procesa el login
    mensajeError.textContent = '';
    mensajeError.classList.remove('visible');
    botonIngresar.disabled = true;
    botonIngresar.textContent = 'Ingresando...';

    try {
      // Envia los datos al backend para validar usuario y contraseña
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

      // Si la API responde con error, lanza una excepción para mostrar el mensaje
      if (!respuesta.ok || !resultado.success) {
        throw new Error(resultado.message || 'No se pudo iniciar sesión.');
      }

      // Guarda el nombre para mostrarlo en otras pantallas
      if (resultado.nombre) {
        const nombreUsuario = String(resultado.nombre).trim();
        sessionStorage.setItem('usuario_nombre', nombreUsuario);
        localStorage.setItem('usuario_nombre', nombreUsuario);
      }

      // Redirige según el rol que devuelve la API
      const destino = RUTAS_POR_ROL[resultado.rol] || 'index.html';
      window.location.href = destino;
    } catch (error) {
      // Muestra el mensaje de error en la pantalla
      mensajeError.textContent = error.message;
      mensajeError.classList.add('visible');
    } finally {
      // Restaura el botón al terminar el intento de login
      botonIngresar.disabled = false;
      botonIngresar.textContent = 'INGRESAR';
    }
  });
})();
