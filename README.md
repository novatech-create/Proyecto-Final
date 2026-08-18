# S.I.G.S.M. - Sistema Informático de Gestión de Servicios Médicos

# Descripción

S.I.G.S.M. es un sistema web desarrollado para el **Hospital de Clínicas** que integra dos módulos:

- **Módulo de Gestión Documental** permite a funcionarios administrativos cargar y gestionar documentos para pacientes, accesibles mediante códigos QR desde dispositivos móviles. Incluye un sistema de encuestas de satisfacción anónimas.
- **Módulo de Trazabilidad de Ambulancias** permite registrar y hacer seguimiento de las solicitudes de traslado realizadas mediante ambulancias y otros vehículos del hospital.

# Equipo

| Integrante | Rol | Rama |
|  | Coordinador | `feature/Emily` |
|  | Sub-Coordinador | `feature/Julieta` |
|  | Integrante 1 | `feature/Clemente` |
|  | Integrante 2 | `feature/Alexis` |

# Tecnologías utilizadas

- **Frontend:** HTML5, CSS3 (Flexbox y Grid)
- **Backend:** PHP 8.x
- **Base de datos:** MySQL
- **Servidor:** Rocky Linux 9.7
- **Control de versiones:** Git / GitHub

# Estructura del repositorio

Proyecto-HospitalDeClinicas/
|
|-> docs/    # Documentación por entrega
|   |-> primera-entrega/
|
|-> src/  # Código fuente
|   |-> modulo-documentacion/
|   │   |-> PanelAdmin/     # Panel de administración
|   │   |-> QR/             # Vista accesible por QR
|   │   |-> Encuestas/      # Formulario de encuesta y confirmación
|   |-> modulo-ambulancias/
|       |-> Gestión de Traslados/      # Panel de gestión de traslados
|       |-> Formulario de Traslado/    # Alta de nuevo traslado
|       |-> Seguimiento/               # Seguimiento de estado
|
|-> database/   # Scripts SQL
|
|-> README.md

# Ramas

| Rama | Descripción |
| `main` | Código estable. Solo se actualiza en cada entrega oficial |
| `develop` | Rama de integración. Se fusionan las ramas de cada integrante |
| `feature/Emily` | Rama de trabajo de Emily |
| `feature/Julieta` | Rama de trabajo de Julieta |
| `feature/Clemente` | Rama de trabajo de Clemente |
| `feature/Alexis` | Rama de trabajo de Alexis |

## Convenciones de commits

Todos los commits deben seguir el siguiente formato:
[tipo] descripción breve en minúsculas

# Tipos

| Tipo | Cuándo usarlo |
| `[feat]` | Nueva funcionalidad o vista |
| `[fix]` | Corrección de un error |
| `[docs]` | Cambios en documentación |
| `[style]` | Cambios de estilos CSS sin afectar lógica |
| `[refactor]` | Reorganización de código sin cambiar funcionalidad |

# Ejemplos
[feat] agregar panel de administración módulo documentación

[fix] corregir enlace entre encuesta y confirmación

[docs] actualizar README con estructura de carpetas

[style] ajustar colores del header en vista QR

# Instalación

> La documentación completa de instalación del entorno se encuentra en docs/Configuración del Entorno de Desarrollo/.

# Requisitos
- Rocky Linux 9.7
- Apache 2.x
- PHP 8.x
- MySQL
- Visual Studio Code con extensiones: PHP Intelephense, GitLens, Live Server

# Guía de uso de GitHub Desktop

Nadie hace commits directo en `main` ni en `develop`. El camino siempre es:

```
feature/tu-rama > Pull Request > develop > (en la entrega) > Pull Request > main
```

## 1. Instalación y configuración inicial

- Descargar GitHub Desktop desde desktop.github.com.
- Instalar y abrir la app.
- File > Options > Accounts > Sign in con tu cuenta de GitHub. (Usen la cuenta personal suya)
- File > Clone repository, elegir el repositorio Proyecto-Final, seleccionar una carpeta local, y click en Clone.

## 2. Rutina de trabajo diaria

### Paso 1: Cambiar a tu rama

En la parte superior de la ventana está el selector Current branch. Click ahí y elegí tu rama, por ejemplo feature/Emily.

### Paso 2: Actualizar tu rama antes de trabajar

Para evitar conflictos grandes, antes de empezar a escribir el código:

- Cambiá a la rama develop desde el selector Current branch.
- Click en Fetch origin (arriba a la derecha) y luego en Pull origin si aparece.
- Volvé a tu rama feature/TuNombre desde el selector.
- Andá al menú Branch > Merge into current branch...
- Seleccioná develop y click en Merge develop into feature/TuNombre.

Esto trae los cambios nuevos de develop a tu rama (por eso en tu tablero de GitHub por ejemplo ves "2 Behind" hay 2 commits en develop que aún no tenés).

### Paso 3: Trabajar y guardar cambios (commit)

Editá tus archivos normalmente en VS Code.

Volvé a GitHub Desktop: en el panel izquierdo vas a ver la lista de Changes (archivos modificados), con un diff a la derecha mostrando qué cambió.

Revisá los cambios y tildá los archivos que querés incluir en el commit.

Abajo a la izquierda, completá con todos los datos del commit. (Si tenés dudas de qué poner fijate en lo que puse el profesor de fullstack en Canva)

Ejemplo de Summary:

```
[feat] panel de administración de documentos
```

Recordá siempre usar la convención del README:

| Tipo | Cuándo usarlo |
|---|---|
| [feat] | Nueva funcionalidad o vista |
| [fix] | Corrección de un error |
| [docs] | Cambios en documentación |
| [style] | Cambios de estilos CSS sin afectar lógica |
| [refactor] | Reorganización de código sin cambiar funcionalidad |

### Paso 4: Subir tus cambios (push)

Después de commitear, arriba a la derecha aparece el botón Push origin. Click ahí para subir tus cambios a GitHub.

## 3. Cómo crear un Pull Request (PR) desde GitHub Desktop

Con tu rama feature/TuNombre ya pusheada, andá al menú Branch > Create Pull Request. Esto abre GitHub en el navegador con el PR precargado. Verificá que diga:

- base: develop
- compare: feature/TuNombre

Escribí un título claro, por ejemplo: [feat] panel de administración de documentos.

En la descripción, contá brevemente qué hiciste y si hay algo que el revisor deba probar.

Click en Create pull request.

Pedile a un compañero (idealmente Coordinador o Sub-Coordinador) que revise y apruebe desde GitHub (web).

Una vez aprobado, hacé Merge (recomendado: Squash and merge) desde la web de GitHub.

## 4. De develop a main (entregas oficiales)

Solo se hace en los momentos de entrega:

- Verificar que develop esté estable (todo funcionando, sin bugs conocidos).
- Cambiar a la rama develop en GitHub Desktop.
- Menú Branch > Create Pull Request, esto abre GitHub en el navegador.
- Configurar base: main, compare: develop.
- El equipo revisa el PR (idealmente el Coordinador).
- Hacer Merge desde la web de GitHub.
- Opcional: crear un tag de la entrega. Esto se hace mejor desde la web de GitHub (Releases > Draft a new release)

## 5. Manejo de conflictos en GitHub Desktop

Si al hacer Branch > Merge into current branch... aparece un conflicto:

- GitHub Desktop te va a avisar con un mensaje tipo "There are conflicts in X files".
- Click en View conflicts o abrí directamente los archivos marcados en VS Code.
- Vas a ver las marcas `<<<<<<<`, `=======`, `>>>>>>>` en el código. Hablalo con el compañero correspondiente y decidan qué versión dejar (o combinar ambas).
- Borrá las marcas de conflicto y dejá el código final guardado.
- Volvé a GitHub Desktop: los archivos van a pasar de "conflicted" a listos para commitear.
- Completá el Summary, por ejemplo:

```
[fix] resolver conflicto entre feature/X y develop
```

- Click en Commit merge y después Push origin.

Tip: los conflictos son mucho menos frecuentes si actualizás tu rama seguido (Paso 2 de la rutina diaria) en vez de dejar pasar muchos días.

## 6. Guía visual rápida de la interfaz

| Botón / Menú | Para qué sirve |
|---|---|
| Current branch (selector superior) | Cambiar entre ramas |
| Fetch origin / Pull origin | Traer novedades del repositorio remoto |
| Push origin | Subir tus commits al repositorio remoto |
| Changes (panel izquierdo) | Ver y seleccionar archivos modificados |
| History (panel izquierdo, al lado de Changes) | Ver el historial de commits de la rama actual |
| Branch → Merge into current branch... | Traer cambios de otra rama (ej. develop) a la tuya |
| Branch → Create Pull Request | Abrir GitHub en el navegador para crear un PR |
| Repository → Show in Explorer/Finder | Abrir la carpeta local del proyecto |

## 7. Buenas prácticas para el equipo

- Commits chicos y frecuentes, no un commit gigante al final.
- Un Pull Request = una funcionalidad o corrección concreta, no mezclar varias cosas.
- Revisar siempre el diff en Changes antes de commitear, para no subir archivos de más (ej. .env, carpetas temporales).
- Antes de un PR a develop, probar que tu código funciona localmente.
- Mantener el README actualizado (estructura de carpetas, instrucciones de instalación) a medida que el proyecto crece.

## 8. Q&A

### ¿Qué hace exactamente el merge?

Un merge toma los commits de una rama (por ejemplo develop) y los copia a otra rama (por ejemplo feature/Emily). No mueve nada, no borra nada: simplemente hace que tu rama ahora "contenga" también esos commits.

Git internamente crea un nuevo commit (llamado merge commit) que dice: "a partir de acá, esta rama tiene la historia de las dos". Las ramas siguen existiendo como cosas separadas, cada una con su propio puntero (su propio "dónde estoy parado").

### ¿Es permanente?

Sí, en el sentido de que ese commit de merge queda para siempre en el historial de tu rama (a menos que lo deshagas explícitamente con git reset o similar). Pero no es permanente en el sentido de que "ate" tu rama a develop para siempre.

Después de esto, vos seguís trabajando solo en tu rama. Podés hacer commits nuevos, y esos commits no aparecen en develop hasta que vos hagas un Pull Request y alguien lo mergee ahí. Las ramas van cada una por su lado hasta que decidís volver a juntarlas.

Entonces sí: fusionás una vez, y después podés seguir cambiando tu rama sin que se toque develop. Es exactamente el flujo normal de trabajo.

### ¿Qué pasa si develop ya tiene una base/prototipo?

Si develop ya tiene código (un prototipo, una estructura base, lo que sea), cuando vos creás tu rama feature/X a partir de develop, tu rama arranca con una copia de todo eso. No hace falta que hagas nada especial para "traerlo": ya lo tenés.

Si develop cambia después de que vos ya empezaste a trabajar (por ejemplo, otro integrante subió algo), ahí sí necesitás traer esos cambios nuevos con git merge develop (o el equivalente en GitHub Desktop: Branch → Merge into current branch).

### ¿Puedo subir cosas y que se reemplacen solas?

Acá hay un matiz importante que conviene aclarar: git no reemplaza archivos completos a lo bruto. Funciona a nivel de líneas de código:

- Si vos modificás una parte de un archivo y nadie más tocó esa misma parte > el merge se hace automático, sin que tengas que hacer nada. Git combina los cambios.
- Si vos y otro integrante modificaron las mismas líneas de un mismo archivo > ahí git no puede decidir solo cuál dejar, y te avisa de un conflicto (lo que vimos en la guía anterior). Ahí tenés que decidir vos manualmente qué queda.

Así que la respuesta corta es: sí, podés subir tus cambios y en la mayoría de los casos se combinan solos con lo que hay en develop, siempre que no estén tocando exactamente las mismas líneas al mismo tiempo. Por eso conviene que cada uno trabaje en carpetas/archivos distintos cuando se pueda (por ejemplo: modulo-documentacion/ vs modulo-ambulancias/), así los conflictos son raros.

### ¿Para qué son los distintos Merge a la hora de hacer un Pull Request?

**1. Merge commit (la opción por defecto)**

Trae todos los commits de tu rama tal cual están, uno por uno, y agrega un commit extra al final que marca "acá se unieron las dos ramas".

- Ventaja: conservás el historial completo y exacto de lo que hiciste, con todos los pasos intermedios.
- Desventaja: si hiciste 15 commits chiquitos tipo "arreglo", "prueba", "ahora sí" — todo eso queda en el historial de develop, se ve "sucio".

**2. Squash and merge**

Toma todos tus commits de la rama y los aplasta en uno solo antes de meterlos en develop.

- Ventaja: el historial de develop queda prolijo, un commit = un PR = una funcionalidad. Coincide muy bien con la convención [feat], [fix], etc.
- Desventaja: perdés el detalle de los commits intermedios (pero siguen existiendo en tu rama feature/, no se pierden del todo).

**3. Rebase and merge**

Agarra tus commits y los "reubica" arriba de los últimos commits de develop, sin crear un commit de merge extra. Queda como si hubieras trabajado directamente sobre la última versión de develop desde el principio.

- Ventaja: historial lineal, sin ramificaciones ni commits de merge.
- Desventaja: puede ser confuso para un equipo que recién empieza con git, y si hay conflictos hay que resolverlos commit por commit.
