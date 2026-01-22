# Moodle Portfolio & Evidence Exporter (local_portfolioexport)

Este plugin para Moodle permite exportar de forma masiva los entregables (tareas) de los alumnos de un curso específico, organizándolos en una estructura de carpetas por usuario y generando un reporte individual de calificación.

Ideal para procesos de **auditoría gubernamental** o portafolios de evidencias institucionales.

## ✨ Características
- **Exportación masiva vía CLI:** Evita *timeouts* de PHP procesando los archivos en segundo plano.
- **Estructura jerárquica:** Crea carpetas `Usuario > Actividad > Archivo`.
- **Reportes integrados:** Genera un archivo `.txt` por cada entrega con la calificación y fecha.
- **Descarga en Cascada:** Interfaz web con JavaScript para descargar cientos de ZIPs de forma secuencial sin bloquear el navegador.
- **Configurable:** Permite definir la ruta del binario PHP (CLI) desde la administración de Moodle.

## 🚀 Requisitos
- Moodle 3.9 o superior.
- Acceso a la ejecución de funciones PHP `exec()` en el servidor.
- Servidor Linux con el comando `zip` instalado.

## 🛠 Instalación
1. Descarga el repositorio y renombra la carpeta a `portfolioexport`.
2. Súbela al directorio `/local/` de tu instalación de Moodle.
3. Ve a **Administración del sitio > Notificaciones** para instalar.
4. Configura la ruta de tu PHP CLI en **Administración del sitio > Extensiones > Extensiones locales > Gestor de Portafolios**.

## 📖 Uso
1. Ve a la herramienta desde el menú de administración o accede a `yourmoodle.com/local/portfolioexport/`.
2. Ingresa el **ID del curso** que deseas exportar.
3. Haz clic en **Iniciar Extracción**. El proceso correrá en segundo plano.
4. Una vez finalizado (puedes refrescar la página), entra a **Ver Archivos**.
5. Usa el botón **Iniciar Descarga en Cascada** para bajar todos los portafolios a tu ordenador.

## ⚖️ Licencia
Distribuido bajo la licencia GPL v3. Consulta el archivo `LICENSE` para más detalles.

---
Desarrollado para resolver necesidades urgentes de portafolios de evidencias. ¡Las contribuciones y forks son bienvenidos!

## 🛠 Solución de Problemas (Troubleshooting)

### 1. Las descargas son de 0 bytes o fallan
**Causa:** El usuario web no tiene permisos sobre los archivos generados por la CLI.
**Solución:** Asegúrate de que la carpeta de destino en `moodledata` tenga permisos correctos:
`chmod -R 775 /ruta/a/moodledata/portfolio_dump`

### 2. El proceso no inicia (No se crean carpetas)
**Causa:** La ruta del ejecutable PHP es incorrecta o `exec()` está deshabilitado.
**Solución:** - Verifica la ruta en la configuración del plugin (ej. `/usr/bin/php`).
- Asegúrate de que `exec` no esté en la lista `disable_functions` de tu `php.ini`.

### 3. El navegador bloquea la descarga en cascada
**Causa:** Protección de seguridad contra descargas múltiples.
**Solución:** Al iniciar la descarga, haz clic en el icono de bloqueo en la barra de direcciones del navegador y selecciona "Permitir siempre descargas múltiples de este sitio".
