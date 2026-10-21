# 🌍 EcoTrack — Sistema de Seguimiento Ambiental

[![Despliegue en Railway](https://img.shields.io/badge/Railway-Despliegue%20Activo-00C7B7?style=for-the-badge&logo=railway&logoColor=white)](https://ecotrack-production-28e7.up.railway.app/)
[![PHP](https://img.shields.io/badge/PHP-8.2%20Apache-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com/)

> 🚀 **Aplicación en Producción:** Podés acceder directamente a la plataforma desplegada en la nube haciendo clic aquí:  
> 👉 **[https://ecotrack-production-28e7.up.railway.app/](https://ecotrack-production-28e7.up.railway.app/)**

Aplicación fullstack desplegada en la nube para el registro, consulta y análisis de mediciones ambientales en tiempo real.  
**Trabajo Práctico Integrador N.º 1** · *Aplicaciones Móviles y Cloud Computing* · **Universidad Provincial del Sudoeste (UPSO)**.

---

## 1. Presentación y Equipo de Desarrollo

- **Nombre de la Aplicación:** EcoTrack
- **Tema:** Seguimiento y monitoreo ambiental (temperatura, humedad relativa y geolocalización de estaciones meteorológicas).
- **Equipo de Desarrollo:** **EcoTech Dev Team**
- **Integrantes (Estudiantes UPSO):**
  - **Mateo Lopez Muñoz**
  - **Santiago Saldias**
  - **Matias Puhl**
  - **Walter Solano**
- **Docentes:** Eduardo Mónaco · Heber Alonso
- **Institución:** Universidad Provincial del Sudoeste (UPSO)
- **URL Pública Oficial:** [https://ecotrack-production-28e7.up.railway.app/](https://ecotrack-production-28e7.up.railway.app/)

---

## 2. Cómo correrla en local

### Requisitos previos
- Entorno de desarrollo local con soporte PHP (8.0 o superior con extensión `curl` y `pdo_mysql`) y MySQL / MariaDB (por ejemplo, **XAMPP**, WampServer o Laragon).
- Navegador web moderno (Chrome, Firefox, Edge).

### Paso a paso con XAMPP
1. **Ubicación de los archivos:**
   - Copiar la carpeta completa del proyecto `Ecotech` dentro del directorio `htdocs` de XAMPP:
     ```text
     C:\xampp\htdocs\Ecotech\
     ```
2. **Iniciar servicios:**
   - Abrir el **Panel de Control de XAMPP**.
   - Iniciar los módulos **Apache** y **MySQL** (ambos deben figurar en verde).
3. **Creación de la Base de Datos:**
   - Abrir phpMyAdmin en el navegador: `http://localhost/phpmyadmin`.
   - Ir a la pestaña **Importar** (o pestaña **SQL**).
   - Cargar y ejecutar el archivo [sql/mediciones.sql](sql/mediciones.sql).
   - Esto creará automáticamente la base de datos `ecotrack`, la tabla `mediciones` con sus índices y cinco registros iniciales de prueba.
4. **Configuración de variables de entorno locales:**
   - Copiar el archivo `.env.example` y renombrarlo como `.env` en la raíz del proyecto:
     ```env
     ENTORNO=local
     APP_ENV=development
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=ecotrack
     DB_USER=root
     DB_PASS=
     RESEND_API_KEY=
     EMAIL_AVISOS=
     ```
5. **Ejecutar la aplicación:**
   - Abrir en el navegador web:
     ```text
     http://localhost/Ecotech/
     ```

---

## 3. Endpoints de la API REST

La API responde y recibe datos exclusivamente en formato `JSON` y cumple la semántica estándar de códigos HTTP (R1, R2, R8).

| Método | Ruta | Qué recibe | Qué devuelve | Códigos de estado HTTP |
| :--- | :--- | :--- | :--- | :--- |
| **GET** | `/api/mediciones.php` | Parámetro opcional `?buscar=termino` en query string. | Lista JSON con todas las mediciones ambientales ordenadas por fecha descendente. | `200 OK`, `500 Internal Server Error` |
| **GET** | `/api/mediciones.php?id={id}` | Parámetro `id` numérico en query string. | Objeto JSON con el detalle de la medición solicitada. | `200 OK`, `404 Not Found`, `500 Internal Server Error` |
| **POST** | `/api/mediciones.php` | Body en JSON con: `ubicacion` *(string, obligatorio)*, `temperatura` *(numérico, obligatorio)*, `humedad` *(numérico, obligatorio)*, `fecha` *(opcional)*, `latitud` *(opcional)*, `longitud` *(opcional)*, `observaciones` *(opcional)*. <br>• Si no se envían coordenadas manuales, el backend geocodifica automáticamente la ubicación con Open-Meteo. Si la localidad no existe responde `422`; si el servicio externo falla opera en modo degradado guardando con coordenadas `null` y agregando `'aviso_servicio'`. Despacha o simula aviso por email. | Objeto JSON con la medición creada, su `id` asignado y eventual aviso de servicio degradado. | `201 Created`, `400 Bad Request`, `422 Unprocessable Entity`, `500 Internal Server Error` |
| **PUT** | `/api/mediciones.php?id={id}` | Parámetro `id` en query string (o payload) y cuerpo JSON con los campos actualizados. <br>• Si no se envían coordenadas manuales, el backend geocodifica la localidad con Open-Meteo (`422` si no existe; guardado sin coordenadas en modo degradado si el servicio cae). | Objeto JSON con la medición modificada y mensaje de confirmación. | `200 OK`, `400 Bad Request`, `404 Not Found`, `422 Unprocessable Entity`, `500 Internal Server Error` |
| **DELETE** | `/api/mediciones.php?id={id}` | Parámetro `id` numérico en query string. | Mensaje JSON confirmando la eliminación del registro. | `200 OK`, `400 Bad Request`, `404 Not Found`, `500 Internal Server Error` |
| **GET** | `/api/servicios.php` | `?accion=clima&lat={lat}&lon={lon}` o `?accion=geocodificar&ciudad={nombre}` | Objeto JSON con datos climáticos en tiempo real de Open-Meteo o coordenadas geocodificadas con reporte de degradación elegante. | `200 OK`, `400 Bad Request`, `500 Internal Server Error` |

---

## 4. Variables de Entorno

De acuerdo con los requisitos **R7** y **R8**, ninguna credencial ni clave sensible se almacena dentro del código fuente ni en el historial de commits de Git. Todas las configuraciones se inyectan a través del entorno del servidor y son leídas mediante `getenv()`:

| Variable | Propósito | Ejemplo / Por defecto |
| :--- | :--- | :--- |
| `ENTORNO` | Entorno de ejecución según estándar de la cátedra (`local` o `produccion`). En `produccion` apaga `display_errors` para no exponer rutas del servidor. | `local` |
| `APP_ENV` | Variable complementaria de entorno (`development` o `production`). | `development` |
| `DB_HOST` | Nombre de host o dirección IP del servidor MySQL (`localhost` o host del proveedor cloud en Railway). | `localhost` |
| `DB_PORT` | Puerto de conexión a MySQL. | `3306` |
| `DB_NAME` | Nombre de la base de datos que aloja la tabla `mediciones`. | `ecotrack` |
| `DB_USER` | Usuario con permisos de lectura y escritura en la base de datos. | `root` |
| `DB_PASS` | Contraseña del usuario de la base de datos (vacía en local por defecto). | *(definido en entorno)* |
| `CORS_ORIGIN` | Origen permitido para CORS (R8). Si está vacío no se emite el encabezado `Access-Control-Allow-Origin: *` de forma indiscriminada. | *(opcional)* |
| `RESEND_API_KEY` | Clave de API de Resend para notificaciones por email. Si está vacía, la app opera en **modo simulación** escribiendo en `api/avisos.log`. | `""` *(modo simulación)* |
| `EMAIL_AVISOS` | Correo electrónico de destino para los avisos de nuevas mediciones. | `""` |
| `WEATHER_API_BASE_URL` | URL base del endpoint de pronóstico de Open-Meteo. | `https://api.open-meteo.com/v1/forecast` |
| `GEOCODING_API_BASE_URL` | URL base del endpoint de búsqueda geográfica de Open-Meteo. | `https://geocoding-api.open-meteo.com/v1/search` |

---

## 5. Servicios Externos

El proyecto incorpora tres integraciones externas justificadas según los requisitos **R4** y **R5**:

### 1. Open-Meteo Forecast & Geocoding API (Servicio en Backend)
- **Quién lo llama:** El servidor backend desde [api/servicios.php](api/servicios.php) utilizando `cURL` nativo.
- **Por qué:** 
  1. Geocodifica automáticamente el nombre de la ciudad ingresada en `POST` y `PUT` de [api/mediciones.php](api/mediciones.php) para autocompletar coordenadas de latitud y longitud si el operador no las proveyó manualmente.
  2. Permite contrastar las mediciones ambientales registradas con los valores meteorológicos oficiales en tiempo real de la estación de referencia (temperatura, humedad y velocidad del viento).
- **Datos que salen de la app:** Nombre de la localidad para geocodificar o coordenadas numéricas de latitud y longitud. No se transmiten datos personales, nombres de usuario ni credenciales.
- **Degradación elegante (R5):** Peticiones ejecutadas con `cURL` con timeouts estrictos (4 segundos totales y 3 segundos de conexión) y soporte de certificados nativos para evitar fallos en XAMPP Windows. Si Open-Meteo sufre una caída de servicio o no hay conexión de red:
  - En geocodificación: La medición ambiental se guarda normalmente en MySQL con coordenadas `null`, informando un `'aviso_servicio'` en la respuesta JSON (código `201` o `200`) sin interrumpir la operación del cliente.
  - En consulta climática: Devuelve un objeto con `'degradado' => true`, registrando el fallo en `error_log` y permitiendo al frontend mostrar un banner preventivo sin congelarse ni propagar error `500`.

### 2. Resend Email API / Modo Simulación (Servicio de Notificación en Backend)
- **Quién lo llama:** El servidor backend desde [api/servicios.php](api/servicios.php) (`enviarAvisoMedicion()`) invocado al registrar una nueva medición en `POST /api/mediciones.php`.
- **Por qué:** Envía un aviso automático por correo electrónico ante cada nueva medición ambiental cargada en la plataforma.
- **Datos que salen de la app:** Resumen de la medición registrada (ubicación, temperatura, humedad y fecha/hora) dirigido al email configurado en `EMAIL_AVISOS`.
- **Degradación elegante (R5):** Si `RESEND_API_KEY` o `EMAIL_AVISOS` no están configuradas, el sistema entra en **modo simulación**, registrando el evento en `api/avisos.log` y en `error_log()`. Si las credenciales están presentes pero la conexión con Resend falla o devuelve error HTTP, se captura la excepción y se asienta en el log técnico del servidor sin interrumpir el alta ni alterar el código HTTP `201 Created` devuelto al usuario.

### 3. OpenStreetMap con Leaflet (Servicio en Frontend)
- **Quién lo llama:** El navegador web del cliente mediante JavaScript ([js/app.js](js/app.js)) y las librerías CDN de Leaflet.
- **Por qué:** Proporciona un mapa geográfico interactivo donde el operador puede visualizar la distribución espacial de las estaciones ambientales, observar marcadores de cada medición y seleccionar puntos con un clic para capturar automáticamente sus coordenadas.
- **Datos que salen de la app:** Peticiones HTTP estándar de teselas cartográficas (tiles) a los servidores de OpenStreetMap. Cero datos privados expuestos.
- **Degradación elegante:** Si no hay conexión para descargar las teselas o la librería no responde, la interfaz permanece completamente funcional: los campos de latitud y longitud pueden completarse manualmente o autocompletarse por geocodificación del backend, y la tabla de mediciones sigue disponible sin interrupciones.

---

## 6. Despliegue en la Nube

### Proveedor elegido y modelo Cloud
- **Proveedor:** **Railway** (`https://ecotrack-production-28e7.up.railway.app/`).
- **Modelo de Servicio:** **PaaS** (Platform as a Service).
- **Justificación de la elección:**
  1. **Contenedor PHP + Base de Datos en el mismo proyecto:** Railway permite desplegar la aplicación mediante un `Dockerfile` estándar (`php:8.2-apache` con extensión `pdo_mysql`) junto con una base de datos MySQL gestionada en la misma red privada de baja latencia.
  2. **Gestión nativa de variables de entorno:** Permite configurar `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `ENTORNO=produccion`, `RESEND_API_KEY`, etc., directamente desde el panel web de Railway, cumpliendo estrictamente con **R7**.
  3. **Certificados SSL automáticos (HTTPS):** Genera certificados TLS/HTTPS automáticos de forma transparente, dando cumplimiento a **R6**.
  4. **Compatibilidad indispensable con el TP2 (Android):** A diferencia de servicios de hosting compartido gratuito (como InfinityFree, que inyectan retos de cookies y bloquean clientes que no sean navegadores de escritorio), Railway expone una API REST limpia y directa sin bloqueos, requisito fundamental para que la futura aplicación móvil Android en Java (Semanas 12 a 16) consuma los endpoints HTTP.

### Pasos para desplegar en Railway
1. **Preparación del repositorio:**
   - Asegurarse de que el archivo `.env` y los logs estén ignorados en `.gitignore`.
   - Verificar la presencia de [Dockerfile](Dockerfile) en la raíz del proyecto.
   - Subir el repositorio a GitHub.
2. **Crear la Base de Datos MySQL en Railway:**
   - En el panel de Railway, crear un nuevo proyecto y añadir un servicio **Database → MySQL**.
   - Conectarse a la base creada (mediante la pestaña *Data* de Railway o un cliente como DBeaver / TablePlus) y ejecutar el script [sql/mediciones.sql](sql/mediciones.sql).
3. **Desplegar el servicio Web desde GitHub:**
   - Añadir un nuevo servicio en el proyecto seleccionando **GitHub Repo** y eligiendo el repositorio del proyecto.
   - Railway detectará automáticamente el archivo `Dockerfile`.
   - En la pestaña **Variables**, configurar:
     - `ENTORNO=produccion`
     - `APP_ENV=production`
     - `DB_HOST=${{MySQL.MYSQLHOST}}`
     - `DB_PORT=${{MySQL.MYSQLPORT}}`
     - `DB_NAME=${{MySQL.MYSQLDATABASE}}`
     - `DB_USER=${{MySQL.MYSQLUSER}}`
     - `DB_PASS=${{MySQL.MYSQLPASSWORD}}`
     - `RESEND_API_KEY=` *(opcional)*
     - `EMAIL_AVISOS=` *(opcional)*
4. **Generar dominio público:**
   - En la pestaña **Settings → Networking**, generar un dominio público (`*.up.railway.app`).
   - Validar el funcionamiento del frontend y los endpoints JSON desde el navegador y herramientas como Postman o cURL.

---

## 7. Limitaciones conocidas

- **Alcance acotado al TP1:** No se incluye autenticación de usuarios, roles ni sesiones con login para no exceder la consigna del TP1 y mantener la API pública lista para consumo del TP2.
- **Gráficos en tiempo real:** El gráfico de tendencias ambientales grafica en base a las últimas mediciones cargadas en la tabla actual; no implementa agregaciones estadísticas complejas (promedios móviles semanales), las cuales quedan planificadas para versiones posteriores.
- **Soporte offline local:** Si el cliente pierde la conexión total a internet, el mapa de OpenStreetMap no carga nuevas teselas geográficas (aunque los datos ya descargados de la tabla se mantienen visibles en la pantalla).

---

## 8. Arquitectura y Buenas Prácticas (Cátedra UPSO)

- **Separación de responsabilidades:** Separación clara entre configuración general (`config.php`), conexión a base de datos (`conexion.php`), integraciones externas (`servicios.php`) y controlador RESTful (`mediciones.php`).
- **Seguridad en consultas:** 100% de las consultas SQL parametrizadas a través de `PDO` con marcadores con nombre (`:id`, `:ubicacion`, etc.) y `PDO::ATTR_EMULATE_PREPARES => false`, imposibilitando ataques de Inyección SQL.
- **Validación del lado del servidor:** Toda petición `POST` y `PUT` valida tipos de datos y rangos físicos razonables (temperatura de -60 °C a 70 °C, humedad de 0 % a 100 %) antes de tocar la base de datos, además de validar la existencia geográfica de localidades desconocidas (`422`).
- **Manejo de errores seguro:** `display_errors` apagado en producción con registro en `error_log`, evitando filtración de directorios o credenciales ante anomalías imprevistas.

---

## 9. Aportes y Registro de Trabajo (R10)

Tabla de registro de aportes y responsabilidades individuales del equipo estudiantil según el requisito **R10** de la consigna:

| Integrante | Rol / Responsabilidad | Componentes Desarrollados | Archivos Desarrollados / Modificados | Fechas |
| :--- | :--- | :--- | :--- | :--- |
| **Mateo Lopez Muñoz** | **DevOps & Arquitectura Cloud** | Configuración de entorno, Dockerfile optimizado para Railway (Apache + PHP 8.2), despliegue PaaS y coordinación del proyecto. | `Dockerfile`, `api/config.php`, `.env.example`, `.gitignore`, `README.md` | Octubre 2026 |
| **Santiago Saldias** | **Backend & Base de Datos** | Modelado DDL relacional en MySQL, conexión PDO parametrizada y controlador RESTful (CRUD completo de mediciones con códigos HTTP). | `api/mediciones.php`, `api/conexion.php`, `sql/mediciones.sql`, `sql/railway_setup.sql` | Octubre 2026 |
| **Matias Puhl** | **Servicios Externos & Degradación** | Integración cURL con Open-Meteo (pronóstico climático y geocodificación automática) y manejo de degradación elegante ante caídas de servicio. | `api/servicios.php`, `api/mediciones.php` | Octubre 2026 |
| **Walter Solano** | **Frontend & Visualización de Datos** | Interfaz de usuario responsiva, consumo asíncrono Fetch API, mapa interactivo Leaflet con OpenStreetMap y gráfico Chart.js. | `index.html`, `js/app.js`, `css/styles.css` | Octubre 2026 |
