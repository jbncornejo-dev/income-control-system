# Proyecto TIS

Este proyecto está construido sobre la arquitectura moderna de Laravel 11 y utiliza PostgreSQL como motor de base de datos principal.

## Stack Tecnológico y Versiones

Para garantizar el correcto funcionamiento y evitar problemas de compatibilidad, el entorno de desarrollo utiliza estrictamente las siguientes versiones:

* **Framework:** Laravel v11.56.1
* **Lenguaje:** PHP 8.2.12
* **Base de Datos:** PostgreSQL 15.10
* **Entorno de Node:** Node.js v20.19.0
* **Gestor de Paquetes JS:** NPM v10.8.2
* **Gestor de Dependencias PHP:** Composer

## ⚙️ Configuración del Entorno Local

Sigue estos pasos para levantar el proyecto en tu máquina local tras clonar el repositorio:

1. **Instalar dependencias de Node:**
    npm install


2. **Instalar dependencias de PHP:**
    ```bash
    composer install
    ```

3. **Configurar variables de entorno:**
    ```bash
    cp .env.example .env
    ```
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=proyecto_tis
    DB_USERNAME=postgres
    DB_PASSWORD=tu_contraseña_local

4. **Generar clave de aplicación:**
    ```bash
    php artisan key:generate
    ```

5. **Ejecutar migraciones:**
    ```bash
    php artisan migrate
    ```

6. **Ejecutar seeders:**
    ```bash
    php artisan db:seed
    ```

7. **Iniciar servidor:**
    ```bash
    php artisan serve
    ```
