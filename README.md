# Proyecto TIS

Este es un proyecto para un sistema informático de gestión, validación y control
del ingreso de estudiantes durante la realización de exámenes masivos.

## Índice de contenidos

- [Guía de uso mediante contenedores](#guía-de-uso-mediante-contenedores)
  - [Prerrequisitos](#prerrequisitos)
  - [Clonar el repositorio](#clonar-el-repositorio)
  - [Configuración del entorno de desarrollo](#configuración-del-entorno-de-desarrollo)
  - [Actualizar el frontend (interfaz)](#actualizar-el-frontend-interfaz)
  - [Actualizar la base de datos después de cambios en el backend](#actualizar-la-base-de-datos-después-de-cambios-en-el-backend)
  - [Detener los contenedores](#detener-los-contenedores)
  - [Levantar los contenedores](#levantar-los-contenedores)
  - [Acceder al contenedor `workspace`](#acceder-al-contenedor-workspace)

## Guía de uso mediante contenedores

### Prerrequisitos

Asegúrate de tener Docker y Docker Compose instalados. Verifícalo ejecutando:

```bash
docker --version
docker compose version
```

Si alguno de los comandos no devuelve una versión, instala Docker y Docker Compose
desde el gestor de paquetes de tu distribución Linux o consulta la documentación
oficial de [Docker](https://docs.docker.com/get-docker/) y
[Docker Compose](https://docs.docker.com/compose/install/).

### Clonar el repositorio

```bash
# Via HTTPS
git clone https://github.com/jbncornejo-dev/income-control-system.git app
# Via SSH
git clone git@github.com:jbncornejo-dev/income-control-system.git app
# Entrar al proyecto
cd app
```

### Configuración del entorno de desarrollo

1. Copia el archivo `.env.example` a `.env`:

   ```bash
   cp .env.example .env
   ```

2. Inicia los contenedores con Docker Compose:

   ```bash
   docker compose up -d
   ```
   > Este proceso demorará la primera vez porque construirá todos los contenedores
   > necesarios para el entorno de desarrollo de la aplicación. Sé paciente y
   > espera a que termine el proceso de construcción.
3. Ingresa al contenedor `workspace`:

   ```bash
   docker compose exec workspace bash
   ```

   > Dentro del contenedor, configura el servidor (backend) y la interfaz
   > (frontend) de la aplicación:
   
   > Configura el servidor:
   ```bash
   # Instala las dependencias del servidor
   composer install

   # Genera la clave de la aplicación
   php artisan key:generate

   # Ejecuta las migraciones y carga los datos demo
   php artisan migrate --seed
   ```
   > Compila la interfaz:

   ```bash
   # Instala las dependencias de la interfaz
   npm install
   
   # Compila los recursos del frontend para el servidor Apache
   npm run build
   ```
5. Ejecuta `exit` para salir del contenedor `workspace` y volver a tu máquina host.

   ```bash
   exit
   ```

6. Recrea los contenedores `php-fpm` y `workspace` para que reconozcan la nueva
   clave generada junto con los nuevos valores para las variables de entorno:

   ```bash
   docker compose up -d --force-recreate php-fpm workspace
   ```
   > Todos los comandos anteriores se ejecutan únicamente la primera vez que
   > clonas el proyecto, para construir y dejar listo el entorno de desarrollo.

Ahora puedes ver la aplicación en [http://localhost:8080](http://localhost:8080).

Puedes ver la base de datos en [http://localhost:8081](http://localhost:8081).

Las credenciales para acceder a la base de datos son:

- **System:** PostgreSQL
- **Server:** postgres
- **Username:** nath
- **Password:** secret
- **Database:** app

> ¡Listo! Ya tienes todo el entorno de desarrollo de la aplicación.

> Toda la explicación que sigue a continuación es exclusivamente para desarrollo,
> así que presta atención.

### Actualizar el frontend (interfaz)

Cada vez que traigas cambios desde GitHub con `git pull` que incluyan modificaciones
del frontend, o cuando modifiques archivos de la interfaz localmente, debes volver a
instalar las dependencias y compilar el frontend para que Apache sirva la versión
actualizada:

```bash
docker compose exec workspace bash
npm install
npm run build
exit
```

Si no ejecutas `npm run build`, Apache puede continuar mostrando una versión
desactualizada del frontend.

### Actualizar la base de datos después de cambios en el backend

Cada vez que traigas cambios desde GitHub con `git pull` que incluyan modificaciones
del backend o cuando modifiques archivos del servidor (backend) localmente que involucren
migraciones, seeders o cualquier estructura relacionada con la base de datos,
actualiza la base de datos desde el contenedor `workspace`:

```bash
docker compose exec workspace php artisan migrate --seed
```

Este comando ejecuta las migraciones pendientes, conserva los datos existentes y
ejecuta los seeders.

Si necesitas reiniciar completamente la base de datos durante el desarrollo, puedes
utilizar:

```bash
docker compose exec workspace php artisan migrate:fresh --seed
```

> **Advertencia:** `migrate:fresh --seed` elimina todas las tablas y los datos de la
> base de datos antes de recrearlos. Utiliza este comando únicamente cuando quieras
> reiniciar la base de datos de desarrollo.

### Detener los contenedores

Apaga los contenedores cuando no estés trabajando en el proyecto. Así, los
contenedores desaparecerán y liberarán recursos de tu computadora.

```bash
docker compose down
```

### Levantar los contenedores

Enciende los contenedores únicamente cuando trabajes en el proyecto.

```bash
docker compose up -d
```

### Acceder al contenedor `workspace`

El contenedor `workspace` incluye Composer, npm, Artisan y las herramientas
necesarias para el desarrollo. Cuando necesites ejecutar comandos de Artisan,
Composer o npm, ingresa al contenedor con:

```bash
docker compose exec workspace bash
```

Dentro del contenedor puedes ejecutar comandos, como por ejemplo:

```bash
php artisan migrate --seed
composer install
npm run build
...
```
> Estos comandos son solo algunos ejemplos de las tareas que puedes realizar en el
> contenedor `workspace`.

Cuando termines, ejecuta `exit` para salir del contenedor `workspace` y volver a tu máquina host.


## Accesos de demostración

Con `APP_ENV=local`, ejecuta `php artisan migrate --seed` y usa uno de estos
accesos.

| Rol | Correo | Contraseña |
| --- | --- | --- |
| Administrador | administrador@gmail.com | `SalchiPapa123*` |
| Docente | docente@example.com | `pass` |
| Personal de control | control@example.com | `pass` |
| Estudiante | estudiante@example.com | `pass` |

El administrador demo también puede ingresar con el usuario `administrador.local`.

Para actualizar solo las cuentas demo de una base local ya inicializada,
ejecuta `php artisan db:seed --class=DemoUserSeeder`.

## Puesta en marcha en el servidor de la universidad

Configurar Laravel con `APP_ENV=production`, `APP_DEBUG=false`, HTTPS,
almacenamiento persistente y las credenciales PostgreSQL de la universidad.
Con `SESSION_DRIVER=database`, la desactivación de una cuenta individual
invalida también sus sesiones almacenadas.

Después de compilar los recursos y configurar el entorno, ejecutar:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan admin:provisionar
```

En producción, el seeder general registra los roles base. El último comando
solicita un correo y un nombre de usuario individual, y lee la contraseña sin
mostrarla. Ejecutarlo con `--rotar=ID` para reemplazar las
credenciales de un administrador existente conservando su historial. Desde
**Usuarios** se pueden crear más cuentas administrativas o asignar ese rol a
una cuenta existente con credenciales nuevas. En **Roles** se pueden crear
perfiles personalizados de exportación, limitar sus asignaturas y asignarlos
desde **Usuarios**.

### Backups en el servidor

Ejecutar `scripts/backup-servidor.sh` con Bash en el host Linux. Se necesitan
`pg_dump`, `pg_restore`, `createdb`, `tar`, `gzip`, `sha256sum` y `flock`. Usar
un cliente PostgreSQL de la misma versión mayor que el servidor. En un
despliegue con contenedores, dar al proceso acceso de lectura al volumen de
`storage/app/public` y conectividad a PostgreSQL. El directorio de copias
debe estar fuera del sitio web y en un volumen persistente con permisos `0700`.

Preparar un archivo de entorno privado para la cuenta del sistema que realizará
los backups. El archivo indicado en `PGPASSFILE` debe tener permisos `0600`.

```bash
export PGHOST=127.0.0.1 PGPORT=5432 PGDATABASE=control_ingreso
export PGUSER=cuenta_backup PGPASSFILE=/ruta/privada/pgpass
export PUBLIC_STORAGE_DIR=/ruta/volumen/storage/app/public
export BACKUP_DIR=/ruta/privada/backups/cie
export BACKUP_RETENTION_DAYS=14
```

La cuenta de backup requiere conexión y lectura de tablas y secuencias de la
base. Ejecutar manualmente una primera vez:

```bash
. /ruta/privada/backup-cie.env
bash /ruta/app/scripts/backup-servidor.sh crear
```

Para automatizarlo, instalar esta entrada en el `crontab` de esa cuenta,
ajustando las rutas y creando previamente el directorio de logs con permisos
privados:

```cron
0 2 * * * /bin/bash -c 'source /ruta/privada/backup-cie.env && exec /bin/bash /ruta/app/scripts/backup-servidor.sh crear' >> /ruta/privada/logs/backup-cie.log 2>&1
```

Cada copia contiene PostgreSQL, los archivos públicos persistentes (como las
fotos de estudiantes) y un manifiesto SHA-256. La copia aparece una vez
completada. La retención usa los días indicados. El valor `0` conserva todas
las copias. Guardar también una copia protegida de la configuración, `APP_KEY`
y los permisos de conexión, necesarios para levantar la aplicación si se
pierde el host.

### Restauración

1. Elegir una copia y preparar el acceso PostgreSQL de un operador con
   permisos para crear una base, instalar `unaccent` y restaurar el esquema.
   Conservar el nombre de la base activa en `PGDATABASE` y usar un nombre nuevo
   para la restauración. Si el operador y la cuenta web son distintos, conceder
   a la cuenta web los permisos de la nueva base antes de conectarla.
2. Ejecutar el siguiente comando. Verifica las sumas de ambos archivos,
   restaura PostgreSQL en una transacción y extrae los archivos a una ruta nueva.

```bash
bash /ruta/app/scripts/backup-servidor.sh restaurar \
  /ruta/privada/backups/cie/backup-AAAAMMDDTHHMMSSZ-sufijo \
  control_ingreso_restaurado /ruta/volumen/public-restaurado
```

3. Probar la copia con una instancia aislada usando la versión de la aplicación
   compatible con la copia. Verificar el acceso, los registros y la apertura de
   fotografías. Registrar la fecha y el resultado del simulacro.
4. Para reemplazar la base activa, poner la aplicación en mantenimiento y
   pausar los procesos que escriben en la base. Conservar la base y el volumen
   previos, configurar la aplicación para utilizar la nueva base y montar el
   directorio restaurado en `storage/app/public` con permisos para la cuenta
   web. Regenerar la configuración en caché, probar y reabrir la aplicación.
   Si hay un problema, volver a la configuración y al volumen anteriores.
