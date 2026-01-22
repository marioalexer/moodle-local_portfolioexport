<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// CONFIGURACIÓN: ¿Quieres borrar la carpeta original después de crear el ZIP?
$borrar_temporales = true;

// 1. OBTENER ARGUMENTOS
list($options, $unrecognized) = cli_get_params(['courseid' => false], ['c' => 'courseid']);

if (!$options['courseid']) {
    cli_error("Error: Debes especificar un ID de curso. \nUso: php export.php --courseid=187");
}

$courseid = $options['courseid'];
$dest_base = $CFG->dataroot . '/portfolio_dump/curso_' . $courseid;

mtrace("=== INICIANDO EXPORTACIÓN + ZIP (MODO FAST) ===");
mtrace("Curso ID: " . $courseid);
mtrace("Destino: " . $dest_base);

// Limpiar ejecución previa si existe
if (file_exists($dest_base)) {
    mtrace("Nota: El directorio destino ya existía.");
} else {
    mkdir($dest_base, 0777, true);
}

// 2. QUERY DE DATOS
$sql = "SELECT 
            u.username,
            a.name AS assignment_name,
            f.filename,
            f.contenthash,
            COALESCE(CAST(ROUND(ag.grade, 0) AS UNSIGNED), 'Sin_calificar') AS calificacion,
            COALESCE(FROM_UNIXTIME(ag.timecreated, '%Y-%m-%d'), 'Sin_fecha') AS fecha
        FROM {course} c
        JOIN {assign} a ON a.course = c.id
        JOIN {assign_submission} s ON s.assignment = a.id
        JOIN {user} u ON u.id = s.userid
        JOIN {files} f ON f.itemid = s.id 
        LEFT JOIN {assign_grades} ag ON ag.assignment = a.id AND ag.userid = u.id
        WHERE c.id = :courseid
        AND f.component = 'assignsubmission_file'
        AND f.filearea = 'submission_files'
        AND f.filename != '.'";

$rs = $DB->get_recordset_sql($sql, ['courseid' => $courseid]);

$count = 0;
$users_processed = [];

mtrace(">>> FASE 1: Extracción de archivos...");

foreach ($rs as $rec) {
    // Limpieza estricta de nombres
    $clean_user = clean_param($rec->username, PARAM_FILE);
    $clean_assign = clean_param($rec->assignment_name, PARAM_FILE);
    
    // Registrar usuario para la fase de zipeado
    $users_processed[$clean_user] = true;

    $user_dir = $dest_base . '/' . $clean_user . '/' . $clean_assign;
    
    if (!file_exists($user_dir)) {
        mkdir($user_dir, 0777, true);
    }

    // Copiar archivo físico
    $source_file = $CFG->dataroot . '/filedir/' . substr($rec->contenthash, 0, 2) . '/' . substr($rec->contenthash, 2, 2) . '/' . 
$rec->contenthash;
    $dest_file = $user_dir . '/' . $rec->filename;

    if (file_exists($source_file)) {
        copy($source_file, $dest_file);
    }

    // Generar TXT de calificación
    $report_content = "Calificación: " . $rec->calificacion . "\n" .
                      "Fecha: " . $rec->fecha . "\n" .
                      "Archivo: " . $rec->filename;
    file_put_contents($user_dir . '/INFO_CALIFICACION.txt', $report_content);

    $count++;
    if ($count % 100 == 0) mtrace("   Extract: $count archivos...");
}
$rs->close();

mtrace(">>> FASE 2: Generación de ZIPs por usuario (Sin Compresión)...");

// Cambiamos al directorio base para que los ZIPs no tengan rutas absolutas largas
chdir($dest_base);

foreach (array_keys($users_processed) as $user_folder) {
    // Comando ZIP del sistema
    // -r: recursivo
    // -0: SIN COMPRESIÓN (Velocidad pura, bajo CPU)
    // -q: quiet (silencioso)
    // -m: move (borra los archivos originales después de zipear - Ahorra espacio automáticamente)
    
    $zip_name = escapeshellarg($user_folder . '.zip');
    $folder_target = escapeshellarg($user_folder);
    
    // Construimos el comando
    if ($borrar_temporales) {
        // -m mueve los archivos al zip (los borra del disco)
        $cmd = "zip -r -0 -q -m $zip_name $folder_target";
    } else {
        $cmd = "zip -r -0 -q $zip_name $folder_target";
    }

    // Ejecutar comando del sistema
    exec($cmd, $output, $return_var);

    if ($return_var === 0) {
        mtrace(" [OK] ZIP generado: " . $user_folder . ".zip");
    } else {
        mtrace(" [ERROR] Falló zip para: " . $user_folder);
    }
}

mtrace("------------------------------------------------");
mtrace("PROCESO TERMINADO.");
mtrace("Tus ZIPs están en: " . $dest_base);
