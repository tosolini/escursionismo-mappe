<?php
namespace EscursionismoMappe;

class Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('manage_hike_posts_columns', [$this, 'hike_columns']);
        add_action('manage_hike_posts_custom_column', [$this, 'hike_column_data'], 10, 2);
        add_filter('manage_poi_posts_columns', [$this, 'poi_columns']);
        add_action('manage_poi_posts_custom_column', [$this, 'poi_column_data'], 10, 2);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_hike', [$this, 'save_hike_meta'], 10, 2);
        add_filter('replace_editor', [$this, 'replace_hike_editor'], 10, 2);
        add_action('admin_body_class', [$this, 'admin_body_class']);
        add_action('admin_enqueue_scripts', [$this, 'dequeue_conflicts'], 999);
        add_action('admin_print_scripts-post.php', [$this, 'dequeue_conflicts'], 9999);
        add_action('admin_print_scripts-post-new.php', [$this, 'dequeue_conflicts'], 9999);
    }

    public function add_admin_pages() {
        add_submenu_page(
            'edit.php?post_type=hike',
            __('Importa escursioni dal vecchio plugin', 'escursionismo-mappe'),
            __('Importa dati', 'escursionismo-mappe'),
            'manage_options',
            'em-migration',
            [$this, 'migration_page']
        );

        add_submenu_page(
            'edit.php?post_type=hike',
            __('Esporta escursioni e POI', 'escursionismo-mappe'),
            __('Esporta dati', 'escursionismo-mappe'),
            'manage_options',
            'em-export',
            [$this, 'export_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook === 'hike_page_em-migration') {
            wp_enqueue_style('em-map', EM_PLUGIN_URL . 'assets/css/hike-map.css', [], EM_VERSION);
        }

        $screen = get_current_screen();
        $is_hike_editor = $screen && $screen->post_type === 'hike' && ($hook === 'post.php' || $hook === 'post-new.php');

        if ($is_hike_editor) {
            self::enqueue_editor_assets();
        }
    }

    public static function enqueue_editor_assets() {
        wp_enqueue_style('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css', [], '1.9.4');
        wp_enqueue_script('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js', [], '1.9.4', true);
        wp_enqueue_script('leaflet-gpx', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js', ['leaflet'], '2.1.2', true);
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css', [], '6.6.0');
        wp_enqueue_style('em-map', EM_PLUGIN_URL . 'assets/css/hike-map.css', ['leaflet'], EM_VERSION);
        wp_enqueue_script('em-admin-hike', EM_PLUGIN_URL . 'assets/js/admin-hike.js', ['leaflet', 'leaflet-gpx', 'wp-api-fetch'], EM_VERSION, true);
    }

    public function dequeue_conflicts() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'hike' || !in_array($screen->base, ['post', 'post-new'], true)) {
            return;
        }

        $handles = [
            'vc-backend-actions-js',
            'vc-backend-min-js',
            'wpb-modules-js',
        ];

        foreach ($handles as $h) {
            wp_dequeue_script($h);
        }
    }

    public function hike_columns($columns) {
        $date = $columns['date'];
        unset($columns['date'], $columns['cb']);
        $cb = '<input type="checkbox" />';
        $new = [
            'cb'       => $cb,
            'title'    => __('Titolo', 'escursionismo-mappe'),
            'distance' => __('Distanza', 'escursionismo-mappe'),
            'elevation'=> __('Dislivello', 'escursionismo-mappe'),
            'gpx'      => __('GPX', 'escursionismo-mappe'),
            'pois'     => __('POI', 'escursionismo-mappe'),
            'date'     => $date,
        ];
        return $new;
    }

    public function hike_column_data($column, $post_id) {
        switch ($column) {
            case 'distance':
                $dist = get_post_meta($post_id, '_distance_km', true);
                echo $dist ? sprintf('%.1f km', $dist) : '—';
                break;
            case 'elevation':
                $ele = get_post_meta($post_id, '_elevation_gain', true);
                echo $ele ? sprintf('%d m', $ele) : '—';
                break;
            case 'gpx':
                $file_id = get_post_meta($post_id, '_gpx_file_id', true);
                if ($file_id) {
                    $url = wp_get_attachment_url($file_id);
                    echo $url ? '<a href="' . esc_url($url) . '" target="_blank" title="' . __('Scarica GPX', 'escursionismo-mappe') . '"><span class="dashicons dashicons-download"></span></a>' : '—';
                } else {
                    echo '—';
                }
                break;
            case 'pois':
                $count = count(get_posts([
                    'post_type' => 'poi', 'posts_per_page' => -1, 'fields' => 'ids',
                    'meta_query' => [['key' => '_hike_ids', 'value' => $post_id, 'compare' => 'LIKE']],
                ]));
                echo $count ?: '—';
                break;
        }
    }

    public function poi_columns($columns) {
        $date = $columns['date'];
        unset($columns['date']);
        $columns['latlon'] = __('Coordinate', 'escursionismo-mappe');
        $columns['icon_type'] = __('Icona', 'escursionismo-mappe');
        $columns['hikes'] = __('Escursioni', 'escursionismo-mappe');
        $columns['date'] = $date;
        return $columns;
    }

    public function poi_column_data($column, $post_id) {
        switch ($column) {
            case 'latlon':
                $lat = get_post_meta($post_id, '_lat', true);
                $lon = get_post_meta($post_id, '_lon', true);
                echo $lat && $lon ? sprintf('%.6f, %.6f', $lat, $lon) : '—';
                break;
            case 'icon_type':
                $icon = get_post_meta($post_id, '_icon_type', true);
                $info = Icons::get($icon);
                echo $info ? sprintf('<span style="color:%s">&#9679;</span> %s', $info['color'], $info['label']) : $icon;
                break;
            case 'hikes':
                $hike_ids = get_post_meta($post_id, '_hike_ids', true);
                if (!empty($hike_ids)) {
                    $ids = explode(',', $hike_ids);
                    $links = [];
                    foreach ($ids as $hid) {
                        $h = get_post((int)$hid);
                        if ($h) {
                            $links[] = '<a href="' . get_edit_post_link($hid) . '">' . esc_html(get_the_title($hid)) . '</a>';
                        }
                    }
                    echo implode(', ', $links);
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'em_gpx_upload',
            __('Tracciato GPX', 'escursionismo-mappe'),
            [$this, 'gpx_meta_box'],
            'hike',
            'side',
            'high'
        );

        add_meta_box(
            'em_map_preview',
            __('Anteprima Mappa', 'escursionismo-mappe'),
            [$this, 'map_preview_meta_box'],
            'hike',
            'normal',
            'high'
        );

        add_meta_box(
            'em_hike_details',
            __('Dettagli Escursione', 'escursionismo-mappe'),
            [$this, 'hike_meta_box'],
            'hike',
            'side',
            'default'
        );

        add_meta_box(
            'em_poi_details',
            __('Dettagli POI', 'escursionismo-mappe'),
            [$this, 'poi_meta_box'],
            'poi',
            'side',
            'default'
        );

        add_meta_box(
            'em_poi_map_preview',
            __('Posizione POI', 'escursionismo-mappe'),
            [$this, 'poi_map_preview_meta_box'],
            'poi',
            'normal',
            'high'
        );
    }

    public function gpx_meta_box($post) {
        $gpx_file_id = (int)get_post_meta($post->ID, '_gpx_file_id', true);
        $gpx_url = get_post_meta($post->ID, '_gpx_url', true);
        $current_url = $gpx_file_id ? wp_get_attachment_url($gpx_file_id) : $gpx_url;
        wp_nonce_field('em_save_gpx', 'em_gpx_nonce');
        ?>
        <div class="em-gpx-field">
            <label for="em_gpx_url"><?php _e('URL file GPX', 'escursionismo-mappe'); ?></label>
            <input type="text" id="em_gpx_url" name="em_gpx_url" value="<?php echo esc_url($current_url); ?>" placeholder="https://...gpx" />
            <p class="description"><?php _e('Inserisci URL del file GPX o cerca dal media library.', 'escursionismo-mappe'); ?></p>
        </div>
        <div class="em-gpx-field">
            <button type="button" class="button" id="em_media_gpx"><?php _e('Scegli dal Media Library', 'escursionismo-mappe'); ?></button>
        </div>
        <?php
        $distance = get_post_meta($post->ID, '_distance_km', true);
        $elevation = get_post_meta($post->ID, '_elevation_gain', true);
        $elevation_max = get_post_meta($post->ID, '_elevation_max', true);
        if ($distance || $elevation) {
            echo '<div class="em-gpx-field" style="background:#f0f0f1;padding:8px 12px;border-radius:4px;">';
            if ($distance) echo '<p><strong>' . __('Distanza:', 'escursionismo-mappe') . '</strong> ' . sprintf('%.1f km', $distance) . '</p>';
            if ($elevation) echo '<p><strong>' . __('Dislivello+:', 'escursionismo-mappe') . '</strong> ' . sprintf('%d m', $elevation) . '</p>';
            if ($elevation_max) echo '<p><strong>' . __('Quota max:', 'escursionismo-mappe') . '</strong> ' . sprintf('%d m', $elevation_max) . '</p>';
            echo '</div>';
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#em_media_gpx').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: '<?php _e('Seleziona file GPX', 'escursionismo-mappe'); ?>',
                    library: { type: 'application/gpx+xml' },
                    multiple: false,
                    button: { text: '<?php _e('Usa questo file', 'escursionismo-mappe'); ?>' }
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#em_gpx_url').val(attachment.url);
                });
                frame.open();
            });
        });
        </script>
        <?php
    }

    public function map_preview_meta_box($post) {
        $gpx_file_id = (int)get_post_meta($post->ID, '_gpx_file_id', true);
        $gpx_url = '';

        if ($gpx_file_id) {
            $url = wp_get_attachment_url($gpx_file_id);
            if ($url) $gpx_url = $url;
        }

        if (empty($gpx_url)) {
            $gpx_url = get_post_meta($post->ID, '_gpx_url', true);
        }

        $map_id = 'em-admin-map-' . $post->ID;
        $list_id = $map_id . '-poi-list';

        $pois = get_posts([
            'post_type'      => 'poi',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => '_hike_ids', 'value' => $post->ID, 'compare' => 'LIKE'],
            ],
        ]);

        $poi_data = [];
        foreach ($pois as $poi) {
            $icon_type = get_post_meta($poi->ID, '_icon_type', true);
            $poi_data[] = [
                'id'        => $poi->ID,
                'title'     => get_the_title($poi),
                'content'   => get_post_field('post_content', $poi->ID),
                'lat'       => (float)get_post_meta($poi->ID, '_lat', true),
                'lon'       => (float)get_post_meta($poi->ID, '_lon', true),
                'icon_type' => $icon_type ?: 'marker',
            ];
        }

        $basemap_key = get_post_meta($post->ID, '_basemap', true);
        if (!isset(Basemaps::get_all()[$basemap_key])) $basemap_key = 'OpenStreetMap';

        wp_localize_script('em-admin-hike', 'emAdminHikeData', [
            'restUrl'       => rest_url('escursionismo-mappe/v1'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'hikeId'        => $post->ID,
            'gpxUrl'        => $gpx_url ?: '',
            'pois'          => $poi_data,
            'icons'         => Icons::get_all(),
            'basemap'       => $basemap_key,
            'basemaps'      => Basemaps::get_all(),
            'containerId'   => $map_id,
            'listContainerId' => $list_id,
        ]);

        if (!$gpx_file_id && empty($gpx_url)) {
            echo '<div style="padding:40px;text-align:center;color:#888;">';
            echo '<span class="dashicons dashicons-location-alt" style="font-size:48px;width:48px;height:48px;display:block;margin:0 auto 12px;"></span>';
            echo '<p>' . __('Aggiungi un file GPX per attivare la mappa interattiva con POI.', 'escursionismo-mappe') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div id="' . esc_attr($map_id) . '" class="em-admin-map" style="height:400px;width:100%;"></div>';
        echo '<div class="em-admin-poi-bar">';
        echo '<span class="em-admin-poi-bar-title">' . __('Punti di Interesse', 'escursionismo-mappe') . '</span>';
        echo '<span class="em-admin-poi-bar-hint">' . __('Clicca sulla mappa per aggiungere un POI', 'escursionismo-mappe') . '</span>';
        echo '</div>';
        echo '<div id="' . esc_attr($list_id) . '" class="em-admin-poi-list"></div>';
    }

    public function hike_meta_box($post) {
        $zoom = get_post_meta($post->ID, '_layer_zoom', true);
        $basemap = get_post_meta($post->ID, '_basemap', true);
        $gpx_url = get_post_meta($post->ID, '_gpx_url', true);
        $basemaps = Basemaps::get_all();
        ?>
        <table class="widefat striped" style="margin-top:8px">
            <tr>
                <td><label for="em_basemap"><?php _e('Base map', 'escursionismo-mappe'); ?></label></td>
                <td>
                    <select id="em_basemap" name="em_basemap" style="width:100%">
                        <?php foreach ($basemaps as $key => $bm): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($basemap, $key); ?>><?php echo esc_html($bm['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php if ($zoom): ?><tr><td><?php _e('Zoom', 'escursionismo-mappe'); ?></td><td><?php echo (int)$zoom; ?></td></tr><?php endif; ?>
            <?php if ($gpx_url): ?><tr><td colspan="2"><a href="<?php echo esc_url($gpx_url); ?>" target="_blank" class="button button-small"><?php _e('GPX originale', 'escursionismo-mappe'); ?></a></td></tr><?php endif; ?>
            <tr><td colspan="2">
                <a href="<?php echo esc_url(home_url('/escursione/' . $post->post_name . '/')); ?>" target="_blank" class="button button-small">
                    <?php _e('Vedi sul sito', 'escursionismo-mappe'); ?>
                </a>
                <button type="button" class="button button-small" onclick="jQuery('#em_map_preview').show();"><?php _e('Mostra mappa', 'escursionismo-mappe'); ?></button>
            </td></tr>
        </table>
        <?php
    }

    public function poi_meta_box($post) {
        $lat = get_post_meta($post->ID, '_lat', true);
        $lon = get_post_meta($post->ID, '_lon', true);
        $icon = get_post_meta($post->ID, '_icon_type', true);
        $icon_info = Icons::get($icon);
        $hike_ids = get_post_meta($post->ID, '_hike_ids', true);
        ?>
        <table class="widefat striped" style="margin-top:8px">
            <?php if ($lat && $lon): ?>
                <tr><td><?php _e('Latitudine', 'escursionismo-mappe'); ?></td><td><?php echo $lat; ?></td></tr>
                <tr><td><?php _e('Longitudine', 'escursionismo-mappe'); ?></td><td><?php echo $lon; ?></td></tr>
            <?php endif; ?>
            <?php if ($icon_info): ?>
                <tr><td><?php _e('Icona', 'escursionismo-mappe'); ?></td><td><span style="color:<?php echo $icon_info['color']; ?>">&#9679;</span> <?php echo $icon_info['label']; ?></td></tr>
                <tr><td><?php _e('Font Awesome', 'escursionismo-mappe'); ?></td><td><i class="fa-solid <?php echo $icon_info['icon']; ?>"></i> <?php echo $icon_info['icon']; ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($hike_ids)): ?>
                <tr><td><?php _e('Escursioni', 'escursionismo-mappe'); ?></td><td>
                    <?php
                    $ids = explode(',', $hike_ids);
                    $links = [];
                    foreach ($ids as $hid) {
                        $h = get_post((int)$hid);
                        if ($h) $links[] = '<a href="' . get_edit_post_link($hid) . '">' . esc_html(get_the_title($hid)) . '</a>';
                    }
                    echo implode(', ', $links);
                    ?>
                </td></tr>
            <?php endif; ?>
            <?php if ($lat && $lon): ?>
                <tr><td colspan="2"><a href="https://www.openstreetmap.org/?mlat=<?php echo $lat; ?>&mlon=<?php echo $lon; ?>&zoom=15" target="_blank" class="button button-small"><?php _e('Vedi su OSM', 'escursionismo-mappe'); ?></a></td></tr>
            <?php endif; ?>
        </table>
        <?php
    }

    public function poi_map_preview_meta_box($post) {
        $lat = get_post_meta($post->ID, '_lat', true);
        $lon = get_post_meta($post->ID, '_lon', true);
        if (!$lat || !$lon) {
            echo '<p>' . __('Nessuna coordinata impostata per questo POI.', 'escursionismo-mappe') . '</p>';
            return;
        }
        Map_Renderer::enqueue_assets();
        $map_id = 'em-poi-map-' . $post->ID;
        $data = [
            'zoom' => 15,
            'pois' => [[
                'id' => $post->ID,
                'title' => get_the_title($post),
                'content' => '',
                'lat' => (float)$lat,
                'lon' => (float)$lon,
                'icon_fa' => 'fa-map-pin',
                'icon_color' => '#e74c3c',
                'icon_label' => 'POI',
            ]],
            'cluster'  => false,
            'gpxUrl'   => '',
            'basemaps' => Basemaps::get_all(),
            'basemap'  => 'OpenStreetMap',
        ];
        wp_localize_script('em-map', 'emMapData_' . $post->ID, $data);
        echo '<div id="' . $map_id . '" class="em-map-container" style="height:300px;width:100%;"></div>';
    }

    public function replace_hike_editor($replace, $post) {
        if ($post->post_type !== 'hike') {
            return $replace;
        }

        $action = $_GET['action'] ?? '';
        if ($action === 'em-ajax-save') {
            return $replace;
        }

        // The replace_editor filter is called early from WP_Screen::get()
        // during set_current_screen() in admin.php (when $_GET['post'] is set
        // for existing posts). Skip that early call — our editor only runs
        // once the current_screen action has fired.
        if (!did_action('current_screen')) {
            return $replace;
        }

        static $called = false;
        if ($called) {
            return $replace;
        }
        $called = true;

        if (!did_action('admin_header')) {
            require_once ABSPATH . 'wp-admin/admin-header.php';
        }

        include EM_PLUGIN_DIR . 'templates/admin-editor-hike.php';
        return true;
    }

    public function admin_body_class($classes) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'hike' && ($screen->base === 'post' || $screen->base === 'post-new')) {
            $classes .= ' em-hike-editor-active ';
        }
        return $classes;
    }

    public function save_hike_meta($post_id, $post) {
        if (!isset($_POST['em_gpx_nonce']) || !wp_verify_nonce($_POST['em_gpx_nonce'], 'em_save_gpx')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['em_basemap'])) {
            $basemap = sanitize_text_field($_POST['em_basemap']);
            $valid = Basemaps::get_all();
            if (isset($valid[$basemap])) {
                update_post_meta($post_id, '_basemap', $basemap);
            }
        }

        if (isset($_POST['em_gpx_url'])) {
            $url = esc_url_raw($_POST['em_gpx_url']);
            update_post_meta($post_id, '_gpx_url', $url);

            $attachment_id = attachment_url_to_postid($url);
            if (!$attachment_id && !empty($url)) {
                global $wpdb;
                $filename = basename($url);
                $attachment_id = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE %s AND post_type = 'attachment' LIMIT 1",
                    '%' . $wpdb->esc_like($filename)
                ));
            }
            update_post_meta($post_id, '_gpx_file_id', $attachment_id ?: 0);

            if ($attachment_id) {
                $path = get_attached_file($attachment_id);
                if ($path && file_exists($path)) {
                    $parser = new GPX_Parser($path);
                    if ($parser->trkpts_count > 0) {
                        update_post_meta($post_id, '_distance_km', $parser->distance_km);
                        update_post_meta($post_id, '_elevation_gain', $parser->elevation_gain);
                        update_post_meta($post_id, '_elevation_max', $parser->elevation_max);
                    }
                }
            }
        }
    }

    public function export_page() {
        if (!current_user_can('manage_options')) wp_die(__('Permesso negato.', 'escursionismo-mappe'));

        if (isset($_POST['em_run_export']) && check_admin_referer('em_export')) {
            $this->do_export();
            return;
        }

        $hike_total = wp_count_posts('hike');
        $hike_count = ($hike_total->publish ?? 0) + ($hike_total->draft ?? 0) + ($hike_total->private ?? 0);
        $poi_total = wp_count_posts('poi');
        $poi_count = ($poi_total->publish ?? 0) + ($poi_total->draft ?? 0) + ($poi_total->private ?? 0);
        ?>
        <div class="wrap">
            <h1><?php _e('Esporta Escursioni e POI', 'escursionismo-mappe'); ?></h1>
            <p><?php _e('Esporta tutti i dati delle escursioni e dei punti di interesse in formato JSON.', 'escursionismo-mappe'); ?></p>

            <div class="em-status-box" style="background:#f0f0f1;padding:15px;border-radius:4px;margin:15px 0;">
                <h3><?php _e('Dati da esportare', 'escursionismo-mappe'); ?></h3>
                <p><?php printf(__('Escursioni: %d', 'escursionismo-mappe'), $hike_count); ?> &middot; <?php printf(__('POI: %d', 'escursionismo-mappe'), $poi_count); ?></p>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('em_export'); ?>
                <p class="submit">
                    <button type="submit" name="em_run_export" class="button button-primary">
                        <?php _e('Scarica JSON', 'escursionismo-mappe'); ?>
                    </button>
                </p>
            </form>
            <p><em><?php _e('Il file JSON contiene titoli, descrizioni, metadati e file GPX (codificati in base64). Puoi importarlo in un\'altra installazione di questo plugin.', 'escursionismo-mappe'); ?></em></p>
        </div>
        <?php
    }

    private function do_export() {
        $hikes = get_posts([
            'post_type'      => 'hike',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $pois = get_posts([
            'post_type'      => 'poi',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $export = [
            'plugin'   => 'escursionismo-mappe',
            'version'  => EM_VERSION,
            'exported' => current_time('mysql'),
            'total'    => [
                'hikes' => count($hikes),
                'pois'  => count($pois),
            ],
            'hikes'    => [],
            'pois'     => [],
        ];

        foreach ($hikes as $hike) {
            $gpx_file_id = (int)get_post_meta($hike->ID, '_gpx_file_id', true);
            $gpx_data = '';

            if ($gpx_file_id) {
                $file_path = get_attached_file($gpx_file_id);
                if ($file_path && file_exists($file_path)) {
                    $gpx_data = base64_encode(file_get_contents($file_path));
                }
            }

            $export['hikes'][] = [
                'title'     => get_the_title($hike),
                'slug'      => $hike->post_name,
                'content'   => $hike->post_content,
                'excerpt'   => $hike->post_excerpt,
                'status'    => $hike->post_status,
                'thumbnail' => get_the_post_thumbnail_url($hike->ID, 'full') ?: '',
                'meta'      => [
                    'gpx_file'        => $gpx_data ? [
                        'name' => get_the_title($gpx_file_id) ?: basename($file_path),
                        'data' => $gpx_data,
                    ] : null,
                    'gpx_url'         => get_post_meta($hike->ID, '_gpx_url', true) ?: '',
                    'layer_zoom'      => (int)get_post_meta($hike->ID, '_layer_zoom', true) ?: 13,
                    'basemap'         => get_post_meta($hike->ID, '_basemap', true) ?: 'OpenStreetMap',
                    'distance_km'     => (float)get_post_meta($hike->ID, '_distance_km', true) ?: 0,
                    'elevation_gain'  => (int)get_post_meta($hike->ID, '_elevation_gain', true) ?: 0,
                    'elevation_max'   => (int)get_post_meta($hike->ID, '_elevation_max', true) ?: 0,
                    'elevation_profile' => get_post_meta($hike->ID, '_elevation_profile', true) ?: '',
                ],
            ];
        }

        foreach ($pois as $poi) {
            $hike_ids = get_post_meta($poi->ID, '_hike_ids', true);
            $hike_ids_arr = $hike_ids ? array_map('intval', explode(',', $hike_ids)) : [];

            $export['pois'][] = [
                'title'   => get_the_title($poi),
                'slug'    => $poi->post_name,
                'content' => $poi->post_content,
                'status'  => $poi->post_status,
                'meta'    => [
                    'lat'       => (float)get_post_meta($poi->ID, '_lat', true) ?: 0,
                    'lon'       => (float)get_post_meta($poi->ID, '_lon', true) ?: 0,
                    'icon_type' => get_post_meta($poi->ID, '_icon_type', true) ?: 'marker',
                    'hike_ids'  => $hike_ids_arr,
                ],
            ];
        }

        $json = wp_json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'escursionismo-mappe-export-' . date('Y-m-d') . '.json';

        nocache_headers();
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    public function migration_page() {
        if (!current_user_can('manage_options')) wp_die(__('Permesso negato.', 'escursionismo-mappe'));

        $result = false;
        if (isset($_POST['em_run_migration']) && check_admin_referer('em_migration')) {
            $migration = new Migration();
            $result = $migration->run();
        }

        $hike_published = wp_count_posts('hike')->publish ?? 0;
        $poi_published = wp_count_posts('poi')->publish ?? 0;
        ?>
        <div class="wrap">
            <h1><?php _e('Importa Escursioni e POI', 'escursionismo-mappe'); ?></h1>
            <p><?php _e('Importa i dati dal vecchio plugin Leaflet Maps Marker nelle tabelle personalizzate.', 'escursionismo-mappe'); ?></p>

            <div class="em-status-box" style="background:#f0f0f1;padding:15px;border-radius:4px;margin:15px 0;">
                <h3><?php _e('Stato attuale', 'escursionismo-mappe'); ?></h3>
                <p><?php printf(__('Escursioni: %d', 'escursionismo-mappe'), $hike_published); ?> &middot; <?php printf(__('POI: %d', 'escursionismo-mappe'), $poi_published); ?></p>
            </div>

            <?php if ($result): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php printf(__('Importazione completata! %d escursioni e %d POI.', 'escursionismo-mappe'), $result['layers_imported'], $result['pois_imported']); ?></p>
                </div>
                <?php if (!empty($result['errors'])): ?>
                    <div class="notice notice-warning is-dismissible">
                        <p><?php _e('Errori:', 'escursionismo-mappe'); ?></p>
                        <ul><?php foreach ($result['errors'] as $e): ?><li><?php echo esc_html($e); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('em_migration'); ?>
                <p class="submit">
                    <button type="submit" name="em_run_migration" class="button button-primary"><?php _e('Avvia importazione', 'escursionismo-mappe'); ?></button>
                </p>
            </form>
            <p><em><?php _e('Le escursioni e POI già importati verranno saltati. Puoi eseguire l\'operazione più volte.', 'escursionismo-mappe'); ?></em></p>
        </div>
        <?php
    }
}
