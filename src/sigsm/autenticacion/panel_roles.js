(() => {
  var api = '/sigsm/api';
  var estado = document.querySelector('#estado');
  var registros = document.querySelector('#registros');
  var alta = document.querySelector('#alta');

  function solicitar(ruta, datos) {
    var opciones;

    if (datos) {
      opciones = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
      };
    }

    return fetch(api + ruta, opciones).then(function (respuesta) {
      return respuesta.json().then(function (resultado) {
        if (!respuesta.ok || !resultado.success) {
          throw new Error(resultado.message || 'Error en la operación.');
        }
        return resultado.data;
      });
    });
  }

  function boton(texto, accion) {
    var elemento = document.createElement('button');
    elemento.type = 'button';
    elemento.textContent = texto;

    if (texto === 'Rechazar' || texto === 'Dar de baja') {
      elemento.classList.add('accion-rechazar');
    }

    elemento.addEventListener('click', accion);
    return elemento;
  }

  function guardar(ruta, datos, mensaje) {
    return solicitar(ruta, datos)
      .then(function () {
        return cargar();
      })
      .then(function () {
        estado.textContent = mensaje;
        return true;
      })
      .catch(function (error) {
        estado.textContent = error.message;
        return false;
      });
  }

  function renderizar(registro) {
    var fila = document.createElement('li');
    var titulo = document.createElement('p');
    fila.appendChild(titulo);

    if (alta) {
      titulo.textContent = registro.nombre + ' · ' + registro.documento;

      fila.appendChild(boton('Dar de baja', function () {
        if (confirm('¿Dar de baja a ' + registro.nombre + '?')) {
          guardar('/panel_roles.php', { id: registro.id }, 'Funcionario dado de baja.');
        }
      }));

      return fila;
    }

    titulo.textContent = registro.titulo + ' · ' + registro.especialista;

    var descripcion = document.createElement('p');
    descripcion.textContent = registro.descripcion;
    fila.appendChild(descripcion);

    var pdf = document.createElement('a');
    pdf.href = api + '/archivo_documento.php?id=' + encodeURIComponent(registro.id);
    pdf.target = '_blank';
    pdf.rel = 'noopener';
    pdf.textContent = 'Abrir PDF (nueva pestaña)';
    pdf.className = 'panel-accion';
    fila.appendChild(pdf);

    fila.appendChild(boton('Aprobar', function () {
      guardar('/panel_roles.php', { id: registro.id, estado: 'aprobado' }, 'Documento aprobado.');
    }));

    fila.appendChild(boton('Rechazar', function () {
      var motivo = prompt('Motivo del rechazo');

      if (motivo !== null) {
        guardar('/panel_roles.php', { id: registro.id, estado: 'rechazado', motivo: motivo }, 'Documento rechazado.');
      }
    }));

    return fila;
  }

  function cargar() {
    return solicitar('/panel_roles.php').then(function (datos) {
      var fragmento = document.createDocumentFragment();

      while (registros.firstChild) {
        registros.removeChild(registros.firstChild);
      }

      for (var i = 0; i < datos.length; i++) {
        fragmento.appendChild(renderizar(datos[i]));
      }

      registros.appendChild(fragmento);

      if (datos.length) {
        estado.textContent = '';
      } else {
        estado.textContent = 'No hay registros para mostrar.';
      }
    });
  }

  if (alta) {
    alta.addEventListener('submit', function (evento) {
      evento.preventDefault();

      var datos = {};
      var formulario = new FormData(alta);

      formulario.forEach(function (valor, clave) {
        datos[clave] = valor;
      });

      guardar('/auth.php?action=register', datos, 'Funcionario creado.').then(function (ok) {
        if (ok) {
          alta.reset();
        }
      });
    });
  }

  cargar().catch(function (error) {
    estado.textContent = error.message;
  });
})();
