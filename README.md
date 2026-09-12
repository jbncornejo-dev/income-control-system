# Proyecto TIS

Esta es proyecto es una aplicacion para un Sistema informatico para la gestion, validacion y control del ingreso de estudiantes durante la
realizacion de examenes masivos.

## Tecnológias y versiones utlizidas 

* **Servidor web:** Apache HTTP Server 2.4.62
* **Framework:** Laravel v11.56.1
* **Lenguaje:** PHP 8.2
* **Base de Datos:** PostgreSQL 15.10
* **Entorno de Node:** Node.js v22.0.0
* **Gestor de Paquetes JS:** NPM incluido en la imagen de Node.js
* **Gestor de Dependencias PHP:** Composer incluido en los contenedores

## Guía de uso de contenedores
### Prerrequisitos

Asegúrate de tener Docker y Docker Compose instalados. Verifícalo ejecutando:

```bash
docker --version
docker compose version
```

Si alguno de los comandos no devuelve una versión, instala Docker y de Docker Compose desde el gestor de paquetes de tu distribución Linux o consulta la documentación oficial de Docker: [Docker](https://docs.docker.com/get-docker/) y [Docker Compose](https://docs.docker.com/compose/install/)

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

3. Ingresa al contenedor `workspace`:

   ```bash
   docker compose exec workspace bash
   ```

4. Dentro del contenedor, instala las dependencias de Laravel:

   ```bash
   composer install
   ```

5. Genera la clave de la aplicación:

   ```bash
   php artisan key:generate
   ```

6. Ejecuta las migraciones:

   ```bash
   php artisan migrate
   ```

7. Sal del contenedor `workspace`:

   ```bash
   exit
   ```

8. Recrea los contenedores `php-fpm` y `workspace` para que reconozcan la nueva
   clave generada:

   ```bash
   docker compose up -d --force-recreate php-fpm workspace
   ```

Ahora puedes ver la aplicación en [http://localhost:8080](http://localhost:8080).

### Acceder al contenedor `workspace`

El contenedor `workspace` incluye Composer, npm y las herramientas necesarias para el
desarrollo. Cuando necesites ejecutar comandos de Artisan, Composer o npm,
ingresa al contenedor con:

```bash
docker compose exec workspace bash
```

Dentro del contenedor puedes ejecutar comandos, como por ejemplo:

```bash
php artisan migrate
composer install
npm run build
...
```

Cuando termines ejecuta `exit` para volver a tu máquina host.

### Actualizar frontend (interfaz)

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

### Actualizar la base de datos después de cambios del backend

Cada vez que traigas cambios desde GitHub con `git pull` que incluyan modificaciones
del backend o cuando modifiques archivos del servidor (backend) localmente que involucren
 migraciones, seeders o cualquier estructura relacionada con la base de datos, actualizala base de datos desde el contenedor `workspace`:

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
Apaga los contenedores cuando no estes trabajando en el proyecto, asi los contenedores desapareceran liberando recursos de tu computadora.

```bash
docker compose down
```

### Levantar los contenedores
Enciende los contenedores unicamente cuando trabajes en el proyecto.

```bash
docker compose up -d
```
