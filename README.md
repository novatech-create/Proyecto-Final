# SIGSM

Sistema Informático de Gestión de Servicios Médicos del equipo NovaTech. Incluye documentación para pacientes, encuestas anónimas y trazabilidad de ambulancias.

## Instalación local

1. Colocar el proyecto en htdocs de XAMPP, la ruta tiene que ser `C:/xampp/htdocs/sigsm`
2. Iniciar Apache y MySQL
3. Para una instalación limpia en Workbench, crear `sigsm1` e importar el esquema y los datos de demostración del mismo proyecto de github database/BDProyecto.sql. Crear la cuenta local `sigsm_app` con permisos sobre `sigsm1` y conservar su contraseña para el siguiente paso
4. Configurar las variables de entorno del proceso PHP. El proyecto usa `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=sigsm1` y `DB_USER=sigsm_app` como valores locales predeterminados. Definir `DB_PASSWORD` con la contraseña de esa cuenta. En XAMPP se puede establecer con `SetEnv DB_PASSWORD "tu-clave-local"` en el .htaccess de /sigsm
5. Dar permiso de escritura al proceso Apache en `Archivos/`, la carpeta protegida de PDF. La variable `SIGSM_PDF_DIR` permite indicar otra ubicación. Configurar `file_uploads=On`, `upload_max_filesize=10M` y `post_max_size=12M` en PHP.
6. Abrir SIGSM en localhost `http://localhost/sigsm/` y usar una cuenta de demostración del SQL para cada rol. Para probar un QR, escanear el QR con Google Lens en el mismo equipo del localhost

## Operación de prueba

- **Funcionario** Subir un PDF en Documentos, consultar las encuestas y operar los traslados
- **Médico** Abrir el PDF pendiente y aprobarlo
- **Administrador** Crear una cuenta de funcionario y desactivarla.
- **Paciente** Consultar el catálogo o un QR sin sesión y responder una encuesta anónima

Las cuentas de demostración se identifican en los comentarios del SQL y en el manual
Para respaldar una instalación, conservar el respaldo de la base y la carpeta privada de PDF

## Organización

| Carpeta | Responsabilidad |
|---------|-----------------|
| `presentacion/` | Páginas, estilos, JavaScript e imágenes |
| `api/` | Entradas HTTP, sesión y respuestas |
| `negocio/` | Validaciones y reglas de los módulos |
| `datos/` | Conexión y consultas PDO |
| `bd/` | Esquema, datos de prueba y permisos |
| `Archivos/` | Compatibilidad con PDF locales antiguos |
| `output/` | Documentación y artefactos de prueba |


## Instalación con Docker en Rocky Linux

### Recetas de los contenedores

| Servicio | Imagen y configuración | Almacenamiento |
|----------|------------------------|----------------|
| `web` | El Dockerfile parte de PHP 8.5 con Apache, agrega PDO para MySQL y habilita las rutas del SIGSM. Apache bloquea las URL directas de `Archivos/` y PHP sirve los PDF mediante la API. | El volumen `pdfs` guarda los archivos en `/var/www/html/sigsm/Archivos` |
| `db` | El docker-compose usa la imagen oficial de MySQL 8.4. Crea `sigsm1` y una cuenta para PHP e importa el SQL al iniciar un volumen nuevo | El volumen `mysql_data` guarda la base en `/var/lib/mysql` |

El Compose se llama `sigsm`. PHP se conecta a `db:3306` por la red interna de Compose y el único puerto publicado es el web. Si el puerto 8000 está ocupado, elegir otro en `WEB_PORT`

### Copiar e instalar

Desde PowerShell, dentro del proyecto en la PC, preparar y enviar los archivos. Sustituir `IPDELAMÁQUINAVIRTUAL` por la dirección de la máquina virtual

tar -czf "$env:TEMP/sigsm-docker.tar.gz" Dockerfile docker-compose.yml .dockerignore .env.example .htaccess README.md api datos negocio presentacion docker Archivos/.htaccess
scp "$env:TEMP/sigsm-docker.tar.gz" novatech@IPDELAMÁQUINAVIRTUAL:~/


En Rocky, comprobar que Docker y Compose están disponibles, extraer el paquete y preparar el archivo de configuración


docker --version
docker compose version

mkdir -p ~/sigsm-docker
tar -xzf ~/sigsm-docker.tar.gz -C ~/sigsm-docker
cd ~/sigsm-docker

if [ ! -f .env ]; then
  cp .env.example .env
fi
chmod 600 .env
nano .env


Completar `DB_PASSWORD` y `MYSQL_ROOT_PASSWORD` con dos claves distintas. Usar `WEB_PORT=8000` si el puerto está libre. Conservar `.env` en la VM

Antes de arrancar, comprobar los archivos:


head -n 1 Dockerfile
ls -ld docker/mysql/BDProyecto.sql
head -n 1 docker/mysql/BDProyecto.sql


El Dockerfile debe empezar por `FROM php:8.5-apache-bookworm`. El SQL debe ser un archivo regular con contenido y empezar por `SET NAMES utf8mb4;`. Si falta el archivo o aparece como directorio, recuperar el SQL correcto antes de continuar


test -f docker/mysql/BDProyecto.sql && test -s docker/mysql/BDProyecto.sql &&
  docker compose config --quiet &&
  docker compose up -d --build --wait
docker compose ps


MySQL debe aparecer como `healthy` y `web` en ejecución. Desde la PC, abrir `http://IPDELAMÁQUINAVIRTUAL:8000/sigsm/`. Si se cambió `WEB_PORT`, usar ese puerto en la URL. Si la conexión falla, probar primero `curl -I http://127.0.0.1:8000/sigsm/` dentro de Rocky. Si responde allí, revisar el firewall o la red de la VM. En modo NAT puede hacer falta una redirección TCP en el hipervisor

### Comprobaciones

1. Entrar como administrador y confirmar que Sujeto1 Documentación se muestra con el acento correcto. Las cuentas de prueba figuran en los comentarios del SQL inicial
2. Entrar como funcionario y subir un PDF de prueba. Aprobarlo como médico y abrirlo desde la ficha pública
3. Reiniciar con `docker compose restart` y comprobar que el documento y el PDF siguen disponibles

### Si falla la instalación

Consultar `docker compose logs --tail=100 db web`

| Problema | Qué revisar |
|----------|-------------|
| `xz: Failed to enable the sandbox` durante la compilación | Verificar que el Dockerfile usa la imagen con etiqueta `bookworm` |
| `Can't initialize batch_readline` o falta la tabla `documentos` | Comprobar que `docker/mysql/BDProyecto.sql` existe como archivo regular. Si MySQL ya creó el volumen, el SQL inicial no se ejecuta de nuevo al reiniciar. Revisar qué tablas quedaron antes de intentar una importación manual |
| Nombres como `DocumentaciÃ³n` | Verificar que el SQL se transfirió en UTF-8 y empieza por `SET NAMES utf8mb4;`. En una importación manual, usar también `--default-character-set=utf8mb4` en el cliente. Los datos guardados con otra codificación requieren una corrección aparte |
| `Unexpected token '<'` al cargar un panel | Hacer una recarga completa. Si se repite, revisar el contenido de la respuesta de la API y los logs de `web` |