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
git clone git@github.com:jbncornejo-dev/income-control-system.git app
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
   php artisan key:generate --seed

   # Ejecuta las migraciones
   php artisan migrate
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
