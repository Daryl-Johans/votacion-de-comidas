# 🍽️ Sabores de Vallegrande — Sistema de Votación de Comidas Típicas

Un sistema web interactivo y moderno desarrollado en **PHP 8 (nativo) y JavaScript (ES6)**, diseñado para llevar a cabo votaciones dinámicas de platos tradicionales de Vallegrande, Bolivia. El sistema incluye un panel de administración, auditoría en tiempo real y generación dinámica de códigos QR para participación móvil en red local.

---

## ✨ Características Principales

* **Votación Interactiva:** Los usuarios registrados pueden elegir su plato típico favorito entre 4 opciones emblemáticas del catálogo.
* **Control de Votos Estricto:** Cada usuario tiene permitido emitir un máximo de **1 voto** por sesión.
* **Panel de Auditoría Administrativa:** Acceso para usuarios con privilegios (`admin` y `superadmin`) para supervisar el estado de los votantes, realizar búsquedas de logs en tiempo real y eliminar usuarios o votos de manera granular.
* **Estadísticas Gráficas en Tiempo Real:** Visualización animada de resultados y porcentajes usando barras dinámicas y hojas de estilo modernas.
* **Códigos QR de Invitación:** Generación automática de códigos QR basados en la **IP física local del servidor** o dominio web, permitiendo a los votantes registrarse de forma rápida usando sus dispositivos móviles conectados a la misma red.
* **Optimistic UI:** La interfaz responde instantáneamente al voto del usuario y, en caso de fallar la comunicación con el servidor, revierte el estado visual garantizando una experiencia de usuario sumamente fluida.
* **Diseño Premium:** Interfaz oscura, estilizada e interactiva con micro-animaciones, barras de carga personalizadas, efectos hover y estructura responsive (adaptada a computadoras, tablets y móviles).

---

## 🛠️ Requisitos Técnicos

* **Servidor Web:** Apache 2.4 o superior (con soporte para `.htaccess` y `mod_rewrite`).
* **PHP:** Versión 8.0 o superior.
* **Base de Datos:** MySQL / MariaDB (con soporte para InnoDB).
* **PowerShell 5.1+** (Solo necesario para el despliegue automático mediante script a producción).

---

## 💻 1. Configuración y Ejecución en Entorno Local (XAMPP / Laragon)

Sigue estos pasos para levantar el sistema en tu servidor de desarrollo local:

### Paso 1: Clonar y Ubicar el Proyecto
1. Clona este repositorio o descarga los archivos.
2. Copia la carpeta del proyecto en el directorio público de tu servidor web:
   * **XAMPP:** `C:\xampp\htdocs\votacion-de-comidas\`
   * **Laragon:** `C:\laragon\www\votacion-de-comidas\`

### Paso 2: Encender los Servicios
Abre el panel de control de XAMPP/Laragon e inicia los servicios de **Apache** y **MySQL**.

### Paso 3: Crear la Base de Datos
1. Ingresa a PHPMyAdmin (`http://localhost/phpmyadmin`).
2. Crea una nueva base de datos en blanco llamada exactamente: **`votacion_comidas`**. No es necesario crear tablas, el script de setup se encargará.

### Paso 4: Ejecutar el Setup de Inicialización
Abre tu navegador y accede a la siguiente URL para crear las tablas e insertar los datos por defecto:
```
http://localhost/votacion-de-comidas/setup.php
```
Deberías ver un mensaje de éxito confirmando que las tablas `foods` y `users` han sido creadas e inicializadas.

### Paso 5: Probar el Sistema
1. Entra a la página de inicio del sistema:
   ```
   http://localhost/votacion-de-comidas/index.php
   ```
2. Puedes registrar nuevos usuarios desde el formulario o iniciar sesión con las credenciales de administración por defecto:
   * **Administrador Principal:**
     * **Usuario:** `admin`
     * **Contraseña:** `admin123`
   * **Superadministrador:**
     * **Usuario:** `superadmin`
     * **Contraseña:** `superadmin123`

---

## 🚀 2. Configuración y Despliegue en Producción (InfinityFree)

El sistema está optimizado para funcionar en servidores de hosting compartido gratuitos como **InfinityFree** y cuenta con un script de despliegue incremental.

### Paso 1: Configurar Base de Datos en la Nube
1. Registra tu cuenta en InfinityFree y crea un nuevo hosting.
2. Crea una base de datos MySQL desde el Panel de Control de InfinityFree. El nombre de base de datos resultante suele tener la estructura `if0_xxxxxx_votacion`.
3. Toma nota del servidor de base de datos asignado (ej: `sql210.infinityfree.com`).

### Paso 2: Crear Archivos de Credenciales Locales (Ignorados por Git)
Para evitar subir contraseñas a repositorios públicos, las credenciales se manejan localmente y están registradas en el `.gitignore`.

1. **Configuración de Conexión a Base de Datos de Producción (`config.php`):**
   Crea un archivo llamado `config.php` en la raíz del proyecto y escribe el siguiente contenido reemplazando por tu contraseña de base de datos real obtenida de InfinityFree:
   ```php
   <?php
   // Contraseña MySQL de InfinityFree
   define('DB_PASSWORD', 'TU_CONTRASEÑA_DE_MYSQL_EN_INFINITYFREE');
   ```

2. **Configuración de Credenciales FTP (`.env.local`):**
   Copia el archivo `.env.local.example` con el nombre `.env.local` en la raíz y rellénalo con tus datos FTP de InfinityFree:
   ```ini
   # Servidor FTP de InfinityFree (generalmente ftpupload.net)
   FTP_HOST=ftpupload.net
   # Tu usuario de FTP (ej: if0_32837262)
   FTP_USER=if0_42133638
   # Contraseña FTP
   FTP_PASS=TU_CONTRASEÑA_FTP_AQUI
   # Directorio raíz del sitio
   FTP_REMOTEDIR=/htdocs
   ```

### Paso 3: Desplegar Archivos mediante PowerShell
El proyecto incluye el script `upload_patch.ps1` que sube únicamente los archivos necesarios al servidor FTP (evitando subir dependencias, configuraciones locales o carpetas de desarrollo).

1. Abre una consola de PowerShell en la carpeta raíz del proyecto.
2. Ejecuta el siguiente comando para subir todos los archivos:
   ```powershell
   .\upload_patch.ps1 -Todo
   ```
3. El script se conectará al FTP y transferirá los archivos del sistema y las imágenes necesarias.

### Paso 4: Ejecutar Inicialización en Producción
1. Accede al archivo `setup.php` desde tu dominio público:
   ```
   https://tu-sitio.infinityfreeapp.com/setup.php
   ```
   *(Reemplaza por la URL de tu subdominio de InfinityFree).*
2. Esto creará las tablas y sembrará la información de los platos e imágenes en tu base de datos remota.

### Paso 5: Medida de Seguridad Crítica
> [!WARNING]
> Una vez que veas el mensaje de éxito de la instalación en producción, **elimina el archivo `setup.php` del servidor FTP** (puedes hacerlo usando un cliente FTP como FileZilla o desde el administrador de archivos web de InfinityFree). Esto previene que usuarios malintencionados reinicien la base de datos o expongan información.

---

## 📂 Estructura del Proyecto

A continuación se describen los archivos principales que componen el sistema:

* **`index.php`:** Vista principal de la aplicación. Realiza la carga de datos iniciales en el servidor y maneja el formulario integrado de inicio de sesión y registro de votantes.
* **`resultados.php`:** Pantalla de estadísticas. Muestra en tiempo real el progreso de los votos con una barra de porcentaje visual animada por plato.
* **`api.php`:** Endpoint de la API REST que atiende peticiones JSON del cliente para acciones como login, registro de usuarios, registro de votos, eliminación granular de usuarios y sondeos de auditoría.
* **`db_helper.php`:** Helper central de datos. Detecta de forma autónoma si el cliente se ejecuta localmente o en producción y establece la conexión PDO correcta, encapsulando las consultas SQL a la base de datos.
* **`config.php`:** Contiene de manera aislada la clave de base de datos de producción (no se incluye en el repositorio de Git por seguridad).
* **`setup.php`:** Archivo instalador que genera la base de datos, crea tablas e inserta los 4 platos típicos de Vallegrande junto con los usuarios administradores.
* **`upload_patch.ps1`:** Script interactivo de PowerShell para despliegues incrementales y totales mediante FTP a InfinityFree.
* **`css/style.css`:** Hoja de estilos principal del proyecto. Implementa la paleta de colores oscuros, componentes visuales e interactivos, y animaciones.
* **`js/app.js`:** Lógica de interactividad del lado del cliente. Controla la UI optimista, peticiones asíncronas fetch, renderizado dinámico de la tabla de auditoría en vivo y generación de códigos QR offline.
* **`images/`:** Carpeta que almacena las imágenes representativas optimizadas de los platos típicos (Asadito Colorado, Bistec Vallegrandino, Huminta en Olla y Asado de Chancho).

---

## 🛡️ Licencia y Uso
Este proyecto fue diseñado con fines educativos y de difusión cultural de la gastronomía de Vallegrande, Santa Cruz - Bolivia. Siente la libertad de usarlo y adaptarlo.
