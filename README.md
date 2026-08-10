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
- **Servidor:** Apache (Ubuntu Server)
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
- Ubuntu Server 24.04
- Apache 2.x
- PHP 8.x
- MySQL
- Visual Studio Code con extensiones: PHP Intelephense, GitLens, Live Server
