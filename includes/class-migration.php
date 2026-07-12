<?php
namespace EscursionismoMappe;

class Migration {
    private $layer_imported = 0;
    private $poi_imported = 0;
    private $errors = [];

    public function run() {
        $this->import_layers();
        $this->import_markers();
        return [
            'layers_imported' => $this->layer_imported,
            'pois_imported'   => $this->poi_imported,
            'errors'          => $this->errors,
        ];
    }

    private function import_layers() {
        global $wpdb;
        $table = $wpdb->base_prefix . '2_leafletmapsmarker_layers';

        $layers = $wpdb->get_results("SELECT * FROM {$table} WHERE id > 0 ORDER BY id ASC");

        foreach ($layers as $layer) {
            $existing = get_posts([
                'post_type'      => 'hike',
                'meta_key'       => '_legacy_layer_id',
                'meta_value'     => $layer->id,
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ]);

            if (!empty($existing)) {
                continue;
            }

            $gpx_file_id = 0;
            if (!empty($layer->gpx_url)) {
                $gpx_file_id = $this->import_gpx_file($layer->gpx_url, $layer->name);
            }

            $post_id = wp_insert_post([
                'post_title'   => $layer->name,
                'post_type'    => 'hike',
                'post_status'  => 'publish',
                'post_content' => '',
            ]);

            if (is_wp_error($post_id)) {
                $this->errors[] = "Errore creazione escursione '{$layer->name}': " . $post_id->get_error_message();
                continue;
            }

            update_post_meta($post_id, '_legacy_layer_id', $layer->id);
            update_post_meta($post_id, '_gpx_file_id', $gpx_file_id);
            update_post_meta($post_id, '_gpx_url', $layer->gpx_url);
            update_post_meta($post_id, '_layer_zoom', $layer->layerzoom);
            update_post_meta($post_id, '_basemap', $layer->basemap);

            $this->import_markers_for_layer($layer->id, $post_id);

            if ($gpx_file_id) {
                $this->parse_gpx_stats($gpx_file_id, $post_id);
            }

            $this->layer_imported++;
        }
    }

    private function import_gpx_file($url, $name) {
        if (empty($url)) {
            return 0;
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        $filename = basename($url);

        global $wpdb;
        $attachment_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE %s AND post_type = 'attachment' AND post_mime_type = %s LIMIT 1",
            '%' . $wpdb->esc_like($filename),
            'application/gpx+xml'
        ));

        if ($attachment_id) {
            return $attachment_id;
        }

        $upload_dir = wp_upload_dir();
        $local_relative = preg_replace('#^.*?/uploads/sites/\d+/#', '', $path);
        if ($local_relative === $path) {
            $local_relative = preg_replace('#^.*?/uploads/#', '', $path);
        }
        $local_path = $upload_dir['basedir'] . '/' . ltrim($local_relative, '/');
        if (file_exists($local_path)) {
            $wp_filetype = wp_check_filetype($filename);
            $attachment = [
                'post_mime_type' => 'application/gpx+xml',
                'post_title'     => $name,
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            $attachment_id = wp_insert_attachment($attachment, $local_path);
            if (!is_wp_error($attachment_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_generate_attachment_metadata($attachment_id, $local_path);
                return $attachment_id;
            }
        }

        $tmp_file = download_url($url);
        if (is_wp_error($tmp_file)) {
            $this->errors[] = "Download GPX fallito per '{$name}': " . $tmp_file->get_error_message();
            return 0;
        }

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        ];

        $attachment_id = media_handle_sideload($file_array, 0, $name);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp_file);
            $this->errors[] = "Allegato GPX fallito per '{$name}': " . $attachment_id->get_error_message();
            return 0;
        }

        return $attachment_id;
    }

    private function import_markers_for_layer($layer_id, $hike_post_id) {
        global $wpdb;
        $markers_table = $wpdb->base_prefix . '2_leafletmapsmarker_markers';

        $markers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$markers_table} WHERE layer LIKE %s",
            '%"' . $layer_id . '"%'
        ));

        foreach ($markers as $marker) {
            $this->import_single_marker($marker, $hike_post_id);
        }
    }

    private function import_single_marker($marker, $hike_post_id) {
        $existing = get_posts([
            'post_type'      => 'poi',
            'meta_key'       => '_legacy_marker_id',
            'meta_value'     => $marker->id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        $icon_clean = basename($marker->icon, '.png');
        $content = !empty($marker->popuptext) ? $marker->popuptext : '';

        if (!empty($existing)) {
            $poi_id = $existing[0];
            if ($hike_post_id > 0) {
                $existing_hike_ids = get_post_meta($poi_id, '_hike_ids', true);
                $hike_ids = !empty($existing_hike_ids) ? explode(',', $existing_hike_ids) : [];
                if (!in_array((string)$hike_post_id, $hike_ids)) {
                    $hike_ids[] = $hike_post_id;
                    update_post_meta($poi_id, '_hike_ids', implode(',', $hike_ids));
                }
            }
            if (empty(get_post_meta($poi_id, '_lat', true))) {
                update_post_meta($poi_id, '_lat', (float)$marker->lat);
                update_post_meta($poi_id, '_lon', (float)$marker->lon);
                update_post_meta($poi_id, '_icon_type', $icon_clean);
            }
            return;
        }

        $poi_id = wp_insert_post([
            'post_title'   => $marker->markername,
            'post_type'    => 'poi',
            'post_status'  => 'publish',
            'post_content' => wp_kses_post($content),
        ]);

        if (is_wp_error($poi_id)) {
            $this->errors[] = "Errore creazione POI '{$marker->markername}': " . $poi_id->get_error_message();
            return;
        }

        update_post_meta($poi_id, '_legacy_marker_id', $marker->id);
        update_post_meta($poi_id, '_lat', (float)$marker->lat);
        update_post_meta($poi_id, '_lon', (float)$marker->lon);
        update_post_meta($poi_id, '_icon_type', $icon_clean);

        if ($hike_post_id > 0) {
            update_post_meta($poi_id, '_hike_ids', (string)$hike_post_id);
        } else {
            update_post_meta($poi_id, '_hike_ids', '');
        }

        $this->poi_imported++;
    }

    private function import_markers() {
        global $wpdb;
        $markers_table = $wpdb->base_prefix . '2_leafletmapsmarker_markers';

        $markers = $wpdb->get_results("SELECT * FROM {$markers_table} ORDER BY id ASC");

        foreach ($markers as $marker) {
            $this->import_single_marker($marker, 0);
        }
    }

    private function parse_gpx_stats($attachment_id, $post_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return;
        }

        $parser = new GPX_Parser($file_path);
        if ($parser->trkpts_count > 0) {
            update_post_meta($post_id, '_distance_km', $parser->distance_km);
            update_post_meta($post_id, '_elevation_gain', $parser->elevation_gain);
            update_post_meta($post_id, '_elevation_max', $parser->elevation_max);
            update_post_meta($post_id, '_elevation_profile', wp_json_encode($parser->get_elevation_profile()));
        }
    }

    public function retry_missing_gpx() {
        global $wpdb;
        $table = $wpdb->base_prefix . '2_leafletmapsmarker_layers';

        $hikes = get_posts([
            'post_type' => 'hike',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_gpx_file_id', 'value' => '0'],
                ['key' => '_gpx_url', 'value' => '', 'compare' => '!='],
            ],
        ]);

        $imported = 0;
        foreach ($hikes as $hike) {
            $gpx_url = get_post_meta($hike->ID, '_gpx_url', true);
            if (empty($gpx_url)) continue;

            $gpx_file_id = $this->import_gpx_file($gpx_url, $hike->post_title);
            if ($gpx_file_id) {
                update_post_meta($hike->ID, '_gpx_file_id', $gpx_file_id);
                $this->parse_gpx_stats($gpx_file_id, $hike->ID);
                $imported++;
            }
        }

        return $imported;
    }
}
