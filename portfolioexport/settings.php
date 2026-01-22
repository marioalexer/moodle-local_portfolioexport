<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // 1. Creamos una página de configuración administrativa
    $settings = new admin_settingpage('local_portfolioexport', get_string('pluginname', 'local_portfolioexport'));

    // 2. Agregamos el enlace a la herramienta (HTML puro dentro de la config)
    $url = new moodle_url('/local/portfolioexport/index.php');
    $link = html_writer::link($url, get_string('menulink', 'local_portfolioexport'), ['class' => 'btn btn-primary']);
    $settings->add(new admin_setting_heading('local_portfolioexport_link', '', $link));

    // 3. CAMPO VITAL: Ruta de PHP configurable
    // Busca si Moodle ya tiene una ruta configurada globalmente, si no, sugiere /usr/bin/php
    $default_php = !empty($CFG->pathtophp) ? $CFG->pathtophp : '/usr/bin/php';
    
    $settings->add(new admin_setting_configtext(
        'local_portfolioexport/phppath', // Nombre interno de la variable
        get_string('phppath_label', 'local_portfolioexport'), // Título visible
        get_string('phppath_desc', 'local_portfolioexport'), // Descripción
        $default_php, // Valor por defecto
        PARAM_TEXT
    ));

    // Guardamos la configuración en el árbol de administración
    $ADMIN->add('localplugins', $settings);
}
