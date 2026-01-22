<?php
require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo $OUTPUT->header();
echo "<h3>Diagnóstico de Servidor</h3>";

// 1. Chequeo de Funciones Prohibidas
$disabled = explode(',', ini_get('disable_functions'));
$exec_enabled = !in_array('exec', $disabled);
echo "<p><strong>Estado de exec():</strong> " . ($exec_enabled ? "<span style='color:green'>HABILITADO</span>" : "<span 
style='color:red'>BLOQUEADO (Revisa php.ini)</span>") . "</p>";

// 2. Chequeo de Ruta PHP
$phppath = get_config('local_portfolioexport', 'phppath');
echo "<p><strong>Ruta PHP Configurada:</strong> $phppath</p>";

if (!file_exists($phppath)) {
    echo "<p style='color:red'>ERROR: El archivo ejecutable no existe en esa ruta.</p>";
} else {
    // Intentar ejecutar version
    $version = shell_exec("$phppath -v");
    echo "<pre>Versión PHP CLI detectada:\n$version</pre>";
}

// 3. Chequeo de Permisos de Escritura
$dump_dir = $CFG->dataroot . '/portfolio_dump';
echo "<p><strong>Directorio destino:</strong> $dump_dir</p>";

if (!file_exists($dump_dir)) {
    // Intentar crear
    if (mkdir($dump_dir, 0777, true)) {
        echo "<span style='color:green'>OK: Se pudo crear el directorio automáticamente.</span>";
    } else {
        echo "<span style='color:red'>FATAL: No se puede crear el directorio. Permisos de 'moodledata' insuficientes para el usuario 
web.</span>";
    }
} else {
    if (is_writable($dump_dir)) {
        echo "<span style='color:green'>OK: El directorio existe y es escribible.</span>";
    } else {
        echo "<span style='color:red'>FATAL: El directorio existe pero NO tengo permiso de escritura (quizás pertenece a 
root?).</span>";
    }
}

echo $OUTPUT->footer();
