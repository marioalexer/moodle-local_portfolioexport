<?php
/**
 * Plugin: local_portfolioexport
 * File: index.php
 * Description: Dashboard principal para la gestión, generación y descarga de evidencias.
 */

require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/adminlib.php');

// =================================================================================
// 1. CONFIGURACIÓN Y SEGURIDAD
// =================================================================================

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Parámetros de entrada
$action   = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$file     = optional_param('file', '', PARAM_FILE);
$view     = optional_param('view', '', PARAM_ALPHA);

// Rutas base
$base_dump_path = $CFG->dataroot . '/portfolio_dump';

// Configuración de la página Moodle
$PAGE->set_url(new moodle_url('/local/portfolioexport/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('apptitle', 'local_portfolioexport'));
$PAGE->set_heading(get_string('heading', 'local_portfolioexport'));
$PAGE->set_pagelayout('admin');

// =================================================================================
// 2. LÓGICA DE NEGOCIO (Procesamiento antes de cualquier HTML)
// =================================================================================

/**
 * ACCIÓN A: DESCARGAR ARCHIVO
 * Usamos headers nativos de PHP para evitar corrupción de ZIPs por buffers de Moodle.
 */
if ($action == 'download' && $courseid && $file) {
    $filepath = $base_dump_path . '/curso_' . $courseid . '/' . $file;
    
    if (file_exists($filepath)) {
        // Limpiar buffers de salida previos (Vital)
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers para forzar descarga binaria limpia
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        
        readfile($filepath);
        exit; // Detener ejecución para no enviar HTML extra
    } else {
        $errormsg = get_string('filenotfound', 'error'); // O mensaje personalizado
    }
}

/**
 * ACCIÓN B: ELIMINAR REPORTES
 */
if ($action == 'delete' && $courseid && confirm_sesskey()) {
    $target_dir = $base_dump_path . '/curso_' . $courseid;
    if (is_dir($target_dir)) {
        // Borrado recursivo en Linux
        $cmd = "rm -rf " . escapeshellarg($target_dir);
        exec($cmd);
        \core\notification::info("Archivos del curso $courseid eliminados correctamente.");
        redirect(new moodle_url('/local/portfolioexport/index.php'));
    }
}

/**
 * ACCIÓN C: PROCESAR (TRIGGER CLI)
 */
if ($action == 'process' && $courseid && confirm_sesskey()) {
    if (!$DB->record_exists('course', ['id' => $courseid])) {
        \core\notification::error("El curso ID $courseid no existe en la base de datos.");
    } else {
        // 1. Definir rutas del script
        $script_dir = $CFG->dirroot . '/local/portfolioexport/cli';
        $script_file = 'export.php'; // Asegúrate que este sea el nombre real en tu carpeta /cli/
        $full_script_path = $script_dir . '/' . $script_file;

        // 2. Obtener binario PHP desde la configuración del plugin (o usar default)
        $php_binary = get_config('local_portfolioexport', 'phppath');
        if (empty($php_binary)) {
            $php_binary = '/usr/bin/php'; // Fallback común
        }

        // 3. Reparar permisos de ejecución si es necesario
        if (file_exists($full_script_path) && !is_executable($full_script_path)) {
            @chmod($full_script_path, 0755);
        }

        // 4. Construir comando asíncrono robusto
        $log_file = $base_dump_path . '/debug_log_curso_' . $courseid . '.txt';
        
        // Estructura: cd [DIR] && [PHP] -f [FILE] -- [ARGS] > [LOG] 2>&1 &
        $cmd = "cd " . escapeshellarg($script_dir) . " && " . 
               escapeshellarg($php_binary) . " -f " . escapeshellarg($script_file) . 
               " -- --courseid=" . escapeshellarg($courseid) . 
               " > " . escapeshellarg($log_file) . " 2>&1 &";
        
        // 5. Ejecutar
        exec($cmd);
        
        \core\notification::success("✅ Proceso iniciado para el Curso $courseid. <br><small>Se está ejecutando en segundo plano. 
Actualiza esta página en unos instantes.</small>");
        redirect(new moodle_url('/local/portfolioexport/index.php'));
    }
}

// =================================================================================
// 3. INTERFAZ GRÁFICA (HTML)
// =================================================================================

echo $OUTPUT->header();

// Estilos CSS incrustados para las funciones visuales
echo "
<style>
    .downloaded-link {
        background-color: #d4edda !important;
        border-color: #c3e6cb !important;
        color: #155724 !important;
        text-decoration: line-through;
        opacity: 0.7;
    }
    .status-area {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #343a40;
        color: #fff;
        padding: 15px 20px;
        border-radius: 8px;
        display: none;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-family: monospace;
        font-size: 14px;
    }
    .box-generator {
        background-color: #f8f9fa;
        padding: 20px;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>
";

// Mostrar error de descarga si ocurrió
if (isset($errormsg)) {
    echo $OUTPUT->notification($errormsg, 'error');
}

// --- SECCIÓN 1: GENERADOR ---
echo $OUTPUT->box_start('generalbox box-generator');
echo "<h3 style='margin-top:0;'>🚀 Generar Nuevo Reporte</h3>";
echo "<form method='post' action='index.php' class='form-inline'>";
echo "<input type='hidden' name='action' value='process'>";
echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
echo "<div class='form-group mb-2'>";
echo "<label for='courseid' class='sr-only'>ID Curso</label>";
echo "<input type='number' name='courseid' id='courseid' class='form-control' placeholder='ID del Curso (ej. 187)' required 
style='margin-right:10px; width:200px;'>";
echo "</div>";
echo "<button type='submit' class='btn btn-primary mb-2'>Iniciar Extracción</button>";
echo "</form>";
echo "<small class='text-muted'>El proceso corre en el servidor. Puedes cerrar esta ventana sin interrumpirlo.</small>";
echo $OUTPUT->box_end();

// --- SECCIÓN 2: HISTORIAL DE CURSOS ---
if ($view != 'detail') {
    echo "<h3>📂 Historial de Portafolios Generados</h3>";
    
    if (is_dir($base_dump_path)) {
        $carpetas = scandir($base_dump_path);
        $found_folders = [];
        
        foreach ($carpetas as $f) {
            if (strpos($f, 'curso_') === 0) $found_folders[] = $f;
        }

        if (empty($found_folders)) {
            echo "<div class='alert alert-info'>No se han encontrado reportes generados. Usa el formulario de arriba para 
comenzar.</div>";
        } else {
            echo "<table class='table table-bordered table-striped table-hover'>";
            echo "<thead class='thead-dark'><tr><th>ID Curso</th><th>Nombre del Curso</th><th>Estado / 
Archivos</th><th>Acciones</th></tr></thead>";
            echo "<tbody>";

            foreach ($found_folders as $folder) {
                // Parsear ID
                $parts = explode('_', $folder);
                $cid = end($parts);
                $full_path = $base_dump_path . '/' . $folder;
                
                // Contar archivos
                $zips = glob($full_path . "/*.zip");
                $zip_count = count($zips);
                
                // Obtener nombre real
                $course_rec = $DB->get_record('course', ['id' => $cid], 'fullname');
                $course_name = $course_rec ? $course_rec->fullname : "<em class='text-muted'>Curso Eliminado ($cid)</em>";
                
                // Fecha modificación
                $date_mod = date("d/m/Y H:i", filemtime($full_path));

                echo "<tr>";
                echo "<td><strong>$cid</strong></td>";
                echo "<td>$course_name <br><small class='text-muted'>Generado: $date_mod</small></td>";
                
                echo "<td>";
                if ($zip_count > 0) {
                    echo "<span class='badge badge-success' style='font-size:1em'>$zip_count Evidencias</span>";
                } else {
                    echo "<span class='badge badge-warning'>Procesando o Vacío</span>";
                }
                echo "</td>";

                echo "<td>";
                // Botón VER
                if ($zip_count > 0) {
                    $view_url = new moodle_url('/local/portfolioexport/index.php', ['view' => 'detail', 'courseid' => $cid]);
                    echo "<a href='$view_url' class='btn btn-sm btn-info'>📂 Ver Archivos</a> ";
                }
                // Botón BORRAR
                $del_url = new moodle_url('/local/portfolioexport/index.php', ['action' => 'delete', 'courseid' => $cid, 'sesskey' => 
sesskey()]);
                echo "<a href='$del_url' onclick='return confirm(\"¿Estás seguro de eliminar los archivos de este curso?\")' class='btn 
btn-sm btn-danger' style='margin-left:5px;'>🗑 Eliminar</a>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
    } else {
        echo "<div class='alert alert-secondary'>El directorio de almacenamiento aún no existe. Se creará con el primer proceso.</div>";
    }
}

// --- SECCIÓN 3: VISTA DE DETALLE (ARCHIVOS) ---
if ($view == 'detail' && $courseid) {
    echo "<div id='detail-view' class='mt-4'>";
    echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
    echo "<h4>Resultados del Curso $courseid</h4>";
    echo "<a href='index.php' class='btn btn-secondary'>&laquo; Volver al Listado</a>";
    echo "</div>";
    
    $course_path = $base_dump_path . '/curso_' . $courseid;
    $archivos = glob($course_path . "/*.zip");
    
    // Panel de herramientas masivas
    echo "<div class='alert alert-secondary'>";
    echo "<strong>⚡ Herramientas de Descarga Masiva:</strong><br>";
    echo "<button id='btn-download-all' class='btn btn-success mt-2' onclick='startBatchDownload()'>Iniciar Descarga en 
Cascada</button>";
    echo "<span class='ml-2 text-muted'> &nbsp; Descarga automática uno por uno para evitar bloqueos.</span>";
    echo "</div>";

    echo "<div class='row'>";
    
    $count = 0;
    foreach($archivos as $arch) {
        $filename = basename($arch);
        
        // Destacar ZIP Maestro si existe (el que contiene todo)
        if (strpos($filename, 'TODO_EL_CURSO') !== false) {
             $url_master = new moodle_url('/local/portfolioexport/index.php', ['action' => 'download', 'courseid' => $courseid, 'file' 
=> $filename]);
             echo "<div class='col-12 mb-3'><div class='alert alert-primary shadow-sm'><strong>📦 ZIP MAESTRO:</strong> <a 
href='$url_master' class='alert-link stretched-link'>Descargar $filename</a></div></div>";
             continue;
        }

        $count++;
        $url = new moodle_url('/local/portfolioexport/index.php', [
            'action' => 'download', 
            'courseid' => $courseid, 
            'file' => $filename
        ]);
        
        echo "<div class='col-md-6 col-lg-4 mb-2'>";
        // Clase 'download-item' es vital para el JS
        echo "<a href='$url' class='btn btn-outline-secondary btn-block text-left download-item' onclick='markAsDownloaded(this)' 
download title='$filename' style='overflow:hidden; text-overflow:ellipsis; white-space:nowrap;'>";
        echo $OUTPUT->pix_icon('f/archive', 'zip') . " $filename";
        echo "</a>";
        echo "</div>";
    }
    echo "</div>"; // End row
    echo "</div>"; // End detail-view
}

// =================================================================================
// 4. JAVASCRIPT (Lógica de Cliente)
// =================================================================================
?>
<div id="status-msg" class="status-area">Iniciando descarga...</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // CONFIGURACIÓN
    const DELAY_MS = 2000;

    // Marcar visualmente como descargado
    function markAsDownloaded(element) {
        element.classList.remove('btn-outline-secondary');
        element.classList.add('downloaded-link');
        if (!element.innerText.includes('✅')) {
            element.innerHTML += ' ✅';
        }
    }

    // Exponer para enlaces individuales
    window.markAsDownloaded = markAsDownloaded;

    let currentIndex = 0;
    let links = [];
    let isDownloading = false;

    // Iniciar descarga en cascada
    function startBatchDownload() {
        links = document.querySelectorAll('.download-item');

        if (links.length === 0) {
            alert('No hay archivos individuales para descargar.');
            return;
        }
        if (isDownloading) return;

        const seconds = DELAY_MS / 1000;
        if (!confirm(
            'Se descargarán ' + links.length +
            ' archivos automáticamente.\n' +
            'Pausa entre archivos: ' + seconds + ' segundos.\n\n' +
            '¿Deseas continuar?'
        )) return;

        isDownloading = true;
        currentIndex = 0;

        const status = document.getElementById('status-msg');
        status.style.display = 'block';
        status.innerText = 'Iniciando descargas...';

        processNext();
    }

    function processNext() {
        if (currentIndex >= links.length) {
            alert('Proceso de descarga finalizado.');
            isDownloading = false;
            document.getElementById('status-msg').style.display = 'none';
            return;
        }

        const link = links[currentIndex];

        if (link.classList.contains('downloaded-link')) {
            currentIndex++;
            processNext();
            return;
        }

        link.click();
        document.getElementById('status-msg').innerText =
            'Descargando ' + (currentIndex + 1) + ' de ' + links.length + '...';

        currentIndex++;
        setTimeout(processNext, DELAY_MS);
    }

    // Registrar botón (corrección clave)
    const btn = document.getElementById('btn-download-all');
    if (btn) {
        btn.addEventListener('click', startBatchDownload);
    }

});
</script>

<?php
echo $OUTPUT->footer();
