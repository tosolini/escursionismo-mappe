<?php
/**
 * Plugin Name: Escursionismo Mappe
 * Plugin URI: https://escursionismo.tosolini.info
 * Description: Gestione escursioni e POI con mappe Leaflet. 
 * Version: 1.1.0
 * Author: Walter Tosolini
 * Author URI: https://tosolini.info
 * License: MIT
 * Text Domain: escursionismo-mappe
 * Requires at least: 7.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

define('EM_VERSION', '1.1.0');
define('EM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EM_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(function ($class) {
    $prefix = 'EscursionismoMappe\\';
    $base_dir = EM_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

function em_load_textdomain() {
    load_plugin_textdomain('escursionismo-mappe', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'em_load_textdomain');

function em_init() {
    EscursionismoMappe\CPT::register();
    EscursionismoMappe\Icons::init();
    EscursionismoMappe\Basemaps::init();
}
add_action('init', 'em_init');

function em_admin_init() {
    if (is_admin()) {
        new EscursionismoMappe\Admin();
    }
}
add_action('plugins_loaded', 'em_admin_init');

function em_blocks_init() {
    EscursionismoMappe\Block::register_block();
}
add_action('init', 'em_blocks_init');

function em_rest_init() {
    EscursionismoMappe\Block::register_rest_routes();
}
add_action('rest_api_init', 'em_rest_init');

function em_shortcode_hike_map($atts) {
    $renderer = new EscursionismoMappe\Map_Renderer();
    return $renderer->render($atts);
}
add_shortcode('hike_map', 'em_shortcode_hike_map');

function em_shortcode_master_map($atts) {
    $atts = shortcode_atts(['height' => '600px', 'width' => '100%'], $atts);

    $hikes = get_posts([
        'post_type'      => 'hike',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    if (empty($hikes)) {
        return '<p>' . __('Nessuna escursione trovata.', 'escursionismo-mappe') . '</p>';
    }

    EscursionismoMappe\Map_Renderer::enqueue_assets();

    $hike_data = [];
    foreach ($hikes as $hike) {
        $gpx_file_id = (int)get_post_meta($hike->ID, '_gpx_file_id', true);
        $gpx_url = $gpx_file_id ? wp_get_attachment_url($gpx_file_id) : '';
        $distance = get_post_meta($hike->ID, '_distance_km', true) ?: 0;
        $elevation = get_post_meta($hike->ID, '_elevation_gain', true) ?: 0;

        $hike_data[] = [
            'id'        => $hike->ID,
            'title'     => get_the_title($hike),
            'link'      => get_permalink($hike),
            'gpxUrl'    => $gpx_url,
            'distance'  => $distance,
            'elevation' => $elevation,
            'excerpt'   => get_the_excerpt($hike),
        ];
    }

    $map_id = 'em-master-map';
    $data = [
        'hikes'    => $hike_data,
        'cluster'  => true,
        'basemaps' => EscursionismoMappe\Basemaps::get_all(),
        'basemap'  => 'OpenStreetMap',
    ];
    wp_localize_script('em-map', 'emMasterMapData', $data);

    $output = '<div class="em-map-wrapper" style="height:' . esc_attr($atts['height']) . ';width:' . esc_attr($atts['width']) . '">';
    $output .= '<h3>' . __('Tutte le escursioni', 'escursionismo-mappe') . '</h3>';
    $output .= '<div id="' . $map_id . '" class="em-map-container"></div>';
    $output .= '<div class="em-map-loading">' . __('Caricamento mappa...', 'escursionismo-mappe') . '</div>';
    $output .= '</div>';

    return $output;
}
add_shortcode('hike_master_map', 'em_shortcode_master_map');

function em_template_include($template) {
    if (is_singular('hike')) {
        $plugin_template = EM_PLUGIN_DIR . 'templates/single-hike.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    if (is_post_type_archive('hike')) {
        $plugin_template = EM_PLUGIN_DIR . 'templates/archive-hike.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}
add_filter('template_include', 'em_template_include');

function em_allow_gpx_upload($mimes) {
    $mimes['gpx'] = 'application/gpx+xml';
    return $mimes;
}
add_filter('upload_mimes', 'em_allow_gpx_upload');

function em_wp_check_filetype_and_ext_gpx($checks, $file, $filename, $mimes) {
    if (empty($checks['ext']) && empty($checks['type'])) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (strtolower($ext) === 'gpx') {
            $checks['ext'] = 'gpx';
            $checks['type'] = 'application/gpx+xml';
            $checks['proper_filename'] = $filename;
        }
    }
    return $checks;
}
add_filter('wp_check_filetype_and_ext', 'em_wp_check_filetype_and_ext_gpx', 10, 4);

if (defined('WP_CLI') && WP_CLI) {
    class EM_Migration_Command {
        public function run($args, $assoc_args) {
            WP_CLI::line('Avvio importazione escursioni e POI...');
            $migration = new EscursionismoMappe\Migration();
            $result = $migration->run();
            WP_CLI::success(sprintf('Importate %d escursioni e %d POI.', $result['layers_imported'], $result['pois_imported']));
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    WP_CLI::warning($error);
                }
            }
        }
        public function retry_gpx($args, $assoc_args) {
            WP_CLI::line('Riprovo import GPX mancanti...');
            $migration = new EscursionismoMappe\Migration();
            $count = $migration->retry_missing_gpx();
            WP_CLI::success(sprintf('Importati %d file GPX.', $count));
        }
    }
    WP_CLI::add_command('em migrate', 'EM_Migration_Command');
}
