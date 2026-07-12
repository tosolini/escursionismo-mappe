<?php
namespace EscursionismoMappe;

class Block {
    public static function can_edit() {
        return current_user_can('edit_posts');
    }

    public static function register_block() {
        wp_register_script(
            'em-block',
            EM_PLUGIN_URL . 'assets/js/block.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-api-fetch', 'wp-plugins', 'wp-edit-post'],
            EM_VERSION,
            true
        );

        wp_localize_script('em-block', 'emBlockData', [
            'rest_url' => rest_url('escursionismo-mappe/v1/'),
            'nonce'    => wp_create_nonce('wp_rest'),
        ]);

        register_block_type('escursionismo-mappe/hike-map', [
            'api_version'     => 3,
            'editor_script'   => 'em-block',
            'render_callback' => [__CLASS__, 'render_block'],
            'attributes'      => [
                'hikeId' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
                'height' => [
                    'type'    => 'string',
                    'default' => '500px',
                ],
            ],
        ]);
    }

    public static function render_block($atts) {
        $hike_id = !empty($atts['hikeId']) ? $atts['hikeId'] : 0;
        $height = !empty($atts['height']) ? $atts['height'] : '500px';
        return do_shortcode('[hike_map id="' . $hike_id . '" height="' . $height . '"]');
    }

    public static function register_rest_routes() {
        $can_edit = [__CLASS__, 'can_edit'];

        register_rest_route('escursionismo-mappe/v1', '/hikes', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_hikes_list'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/map-data/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_map_data'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/pois/nearby', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_nearby_pois'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/pois', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'create_poi'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/pois/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_poi'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/pois/(?P<id>\d+)/link', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'link_poi'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/pois/(?P<id>\d+)/link', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'unlink_poi'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/pois/search', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'search_pois'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/hikes/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'update_hike'],
            'permission_callback' => $can_edit,
        ]);

        register_rest_route('escursionismo-mappe/v1', '/gpx/upload', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'upload_gpx'],
            'permission_callback' => $can_edit,
        ]);
    }

    public static function get_nearby_pois($request) {
        $lat = (float)$request->get_param('lat');
        $lon = (float)$request->get_param('lon');
        $radius = (float)$request->get_param('radius') ?: 50;
        $exclude_hike = (int)$request->get_param('exclude_hike');

        if (!$lat || !$lon) {
            return new \WP_Error('missing_params', 'lat e lon richiesti', ['status' => 400]);
        }

        $all_pois = get_posts([
            'post_type'      => 'poi',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ]);

        $nearby = [];
        foreach ($all_pois as $poi) {
            $poi_lat = (float)get_post_meta($poi->ID, '_lat', true);
            $poi_lon = (float)get_post_meta($poi->ID, '_lon', true);
            if (!$poi_lat || !$poi_lon) continue;

            $dist = self::haversine($lat, $lon, $poi_lat, $poi_lon);
            if ($dist <= $radius) {
                $hike_ids = get_post_meta($poi->ID, '_hike_ids', true);
                $linked_hikes = !empty($hike_ids) ? explode(',', $hike_ids) : [];

                if ($exclude_hike && in_array($exclude_hike, $linked_hikes)) continue;

                $icon_type = get_post_meta($poi->ID, '_icon_type', true);
                $icon_info = Icons::get($icon_type);
                $nearby[] = [
                    'id'        => $poi->ID,
                    'title'     => get_the_title($poi),
                    'lat'       => $poi_lat,
                    'lon'       => $poi_lon,
                    'dist'      => round($dist, 1),
                    'icon'      => $icon_info['icon'],
                    'color'     => $icon_info['color'],
                    'label'     => $icon_info['label'],
                    'icon_type' => $icon_type,
                ];
            }
        }

        usort($nearby, function ($a, $b) { return $a['dist'] <=> $b['dist']; });

        return rest_ensure_response($nearby);
    }

    public static function create_poi($request) {
        $title = sanitize_text_field($request->get_param('title'));
        $lat = (float)$request->get_param('lat');
        $lon = (float)$request->get_param('lon');
        $icon_type = sanitize_text_field($request->get_param('icon_type')) ?: 'marker';
        $content = wp_kses_post($request->get_param('content') ?: '');
        $hike_id = (int)$request->get_param('hike_id');

        if (!$title || !$lat || !$lon) {
            return new \WP_Error('missing_params', 'title, lat e lon richiesti', ['status' => 400]);
        }

        $poi_id = wp_insert_post([
            'post_type'    => 'poi',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ]);

        if (is_wp_error($poi_id)) {
            return new \WP_Error('insert_error', 'Errore creazione POI', ['status' => 500]);
        }

        update_post_meta($poi_id, '_lat', $lat);
        update_post_meta($poi_id, '_lon', $lon);
        update_post_meta($poi_id, '_icon_type', $icon_type);

        if ($hike_id) {
            update_post_meta($poi_id, '_hike_ids', (string)$hike_id);
        }

        $icon_info = Icons::get($icon_type);

        return rest_ensure_response([
            'id'      => $poi_id,
            'title'   => $title,
            'lat'     => $lat,
            'lon'     => $lon,
            'icon'    => $icon_info['icon'],
            'color'   => $icon_info['color'],
            'label'   => $icon_info['label'],
            'content' => $content,
        ]);
    }

    public static function update_poi($request) {
        $poi_id = (int)$request['id'];
        $poi = get_post($poi_id);
        if (!$poi || $poi->post_type !== 'poi') {
            return new \WP_Error('not_found', 'POI non trovato', ['status' => 404]);
        }

        $title = sanitize_text_field($request->get_param('title'));
        $lat = (float)$request->get_param('lat');
        $lon = (float)$request->get_param('lon');
        $icon_type = sanitize_text_field($request->get_param('icon_type'));
        $content = wp_kses_post($request->get_param('content') ?: '');

        $update = ['ID' => $poi_id];
        if ($title) $update['post_title'] = $title;
        if ($request->get_param('content') !== null) $update['post_content'] = $content;

        wp_update_post($update);

        if ($lat && $lon) {
            update_post_meta($poi_id, '_lat', $lat);
            update_post_meta($poi_id, '_lon', $lon);
        }
        if ($icon_type) update_post_meta($poi_id, '_icon_type', $icon_type);

        $icon_info = Icons::get($icon_type ?: get_post_meta($poi_id, '_icon_type', true));

        return rest_ensure_response([
            'id'      => $poi_id,
            'title'   => $title ?: get_the_title($poi_id),
            'lat'     => $lat ?: (float)get_post_meta($poi_id, '_lat', true),
            'lon'     => $lon ?: (float)get_post_meta($poi_id, '_lon', true),
            'icon'    => $icon_info['icon'],
            'color'   => $icon_info['color'],
            'label'   => $icon_info['label'],
            'content' => $content,
        ]);
    }

    public static function link_poi($request) {
        $poi_id = (int)$request['id'];
        $hike_id = (int)$request->get_param('hike_id');

        $poi = get_post($poi_id);
        if (!$poi || $poi->post_type !== 'poi') {
            return new \WP_Error('not_found', 'POI non trovato', ['status' => 404]);
        }
        if (!$hike_id) {
            return new \WP_Error('missing_params', 'hike_id richiesto', ['status' => 400]);
        }

        $hike_ids = get_post_meta($poi_id, '_hike_ids', true);
        $ids = !empty($hike_ids) ? explode(',', $hike_ids) : [];
        if (!in_array($hike_id, $ids)) {
            $ids[] = $hike_id;
            update_post_meta($poi_id, '_hike_ids', implode(',', $ids));
        }

        return rest_ensure_response(['success' => true, 'hike_ids' => $ids]);
    }

    public static function unlink_poi($request) {
        $poi_id = (int)$request['id'];
        $hike_id = (int)$request->get_param('hike_id');

        $poi = get_post($poi_id);
        if (!$poi || $poi->post_type !== 'poi') {
            return new \WP_Error('not_found', 'POI non trovato', ['status' => 404]);
        }
        if (!$hike_id) {
            return new \WP_Error('missing_params', 'hike_id richiesto', ['status' => 400]);
        }

        $hike_ids = get_post_meta($poi_id, '_hike_ids', true);
        $ids = !empty($hike_ids) ? explode(',', $hike_ids) : [];
        $ids = array_values(array_filter($ids, function ($id) use ($hike_id) {
            return (int)$id !== $hike_id;
        }));

        if (empty($ids)) {
            delete_post_meta($poi_id, '_hike_ids');
        } else {
            update_post_meta($poi_id, '_hike_ids', implode(',', $ids));
        }

        return rest_ensure_response(['success' => true, 'hike_ids' => $ids]);
    }

    public static function search_pois($request) {
        $s = sanitize_text_field($request->get_param('s'));
        $exclude_hike = (int)$request->get_param('exclude_hike');

        if (empty($s) || strlen($s) < 2) {
            return rest_ensure_response([]);
        }

        $pois = get_posts([
            'post_type'      => 'poi',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            's'              => $s,
        ]);

        $results = [];
        foreach ($pois as $poi) {
            $hike_ids = get_post_meta($poi->ID, '_hike_ids', true);
            $linked = !empty($hike_ids) ? explode(',', $hike_ids) : [];

            if ($exclude_hike && in_array($exclude_hike, $linked)) continue;

            $lat = (float)get_post_meta($poi->ID, '_lat', true);
            $lon = (float)get_post_meta($poi->ID, '_lon', true);
            $icon_type = get_post_meta($poi->ID, '_icon_type', true);
            $icon_info = Icons::get($icon_type);

            $results[] = [
                'id'        => $poi->ID,
                'title'     => get_the_title($poi),
                'lat'       => $lat,
                'lon'       => $lon,
                'icon_type' => $icon_type ?: 'marker',
                'icon'      => $icon_info['icon'] ?? 'fa-location-dot',
                'color'     => $icon_info['color'] ?? '#e74c3c',
                'label'     => $icon_info['label'] ?? 'POI',
                'dist'      => 0,
            ];
        }

        return rest_ensure_response($results);
    }

    public static function update_hike($request) {
        $hike_id = (int)$request['id'];
        $hike = get_post($hike_id);

        if (!$hike || $hike->post_type !== 'hike') {
            return new \WP_Error('not_found', 'Escursione non trovata', ['status' => 404]);
        }

        $update = ['ID' => $hike_id];

        $title = sanitize_text_field($request->get_param('title'));
        if ($request->get_param('title') !== null) {
            $update['post_title'] = $title;
        }

        $content = $request->get_param('content');
        if ($content !== null) {
            $update['post_content'] = wp_kses_post($content);
        }

        $status = $request->get_param('status');
        if ($status && in_array($status, ['draft', 'publish', 'pending', 'private'])) {
            $update['post_status'] = $status;
        }

        if (count($update) > 1) {
            wp_update_post($update);
        }

        $basemap = sanitize_text_field($request->get_param('basemap'));
        if ($basemap && isset(Basemaps::get_all()[$basemap])) {
            update_post_meta($hike_id, '_basemap', $basemap);
        }

        $gpx_url = esc_url_raw($request->get_param('gpx_url'));
        if ($request->get_param('gpx_url') !== null) {
            update_post_meta($hike_id, '_gpx_url', $gpx_url);
            $attachment_id = attachment_url_to_postid($gpx_url);
            update_post_meta($hike_id, '_gpx_file_id', $attachment_id ?: 0);
        }

        $icon_type = sanitize_text_field($request->get_param('icon_type'));
        if ($request->get_param('icon_type') !== null) {
            update_post_meta($hike_id, '_icon_type', $icon_type);
        }

        return rest_ensure_response([
            'id'     => $hike_id,
            'title'  => get_the_title($hike_id),
            'status' => get_post_status($hike_id),
        ]);
    }

    public static function upload_gpx($request) {
        $files = $request->get_file_params();

        if (empty($files) || !isset($files['file'])) {
            return new \WP_Error('no_file', 'Nessun file ricevuto', ['status' => 400]);
        }

        $file = $files['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', 'Errore upload: ' . $file['error'], ['status' => 500]);
        }

        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'gpx') {
            return new \WP_Error('invalid_type', 'Solo file GPX supportati', ['status' => 400]);
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new \WP_Error('upload_error', $upload['error'], ['status' => 500]);
        }

        $file_path = $upload['file'];
        $file_url = $upload['url'];

        $attachment = [
            'post_title'     => basename($file['name'], '.gpx'),
            'post_content'   => '',
            'post_mime_type' => 'application/gpx+xml',
            'guid'           => $file_url,
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attachment_id)) {
            return new \WP_Error('insert_error', 'Errore salvataggio allegato', ['status' => 500]);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        $parser = new GPX_Parser($file_path);
        $stats = [];

        if ($parser->trkpts_count > 0) {
            $stats['distance_km'] = $parser->distance_km;
            $stats['elevation_gain'] = $parser->elevation_gain;
            $stats['elevation_max'] = $parser->elevation_max;
            $stats['trkpts_count'] = $parser->trkpts_count;
        }

        return rest_ensure_response([
            'id'            => $attachment_id,
            'url'           => $file_url,
            'filename'      => basename($file_url),
            'stats'         => $stats,
        ]);
    }

    private static function haversine($lat1, $lon1, $lat2, $lon2) {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }

    public static function get_hikes_list() {
        $hikes = get_posts([
            'post_type'      => 'hike',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $list = array_map(function ($h) {
            return [
                'id'    => $h->ID,
                'title' => get_the_title($h),
            ];
        }, $hikes);

        return rest_ensure_response($list);
    }

    public static function get_map_data($request) {
        $hike_id = (int)$request['id'];
        $hike = get_post($hike_id);

        if (!$hike || $hike->post_type !== 'hike') {
            return new \WP_Error('not_found', 'Escursione non trovata', ['status' => 404]);
        }

        $gpx_file_id = (int)get_post_meta($hike->ID, '_gpx_file_id', true);
        $gpx_url = $gpx_file_id ? wp_get_attachment_url($gpx_file_id) : '';

        $pois = get_posts([
            'post_type'      => 'poi',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => '_hike_ids', 'value' => $hike->ID, 'compare' => 'LIKE'],
            ],
        ]);

        $poi_data = [];
        foreach ($pois as $poi) {
            $icon_type = get_post_meta($poi->ID, '_icon_type', true);
            $icon_info = Icons::get($icon_type);
            $poi_data[] = [
                'id'      => $poi->ID,
                'title'   => get_the_title($poi),
                'content' => wp_trim_words(get_post_field('post_content', $poi->ID), 30),
                'lat'     => (float)get_post_meta($poi->ID, '_lat', true),
                'lon'     => (float)get_post_meta($poi->ID, '_lon', true),
                'icon'    => $icon_info['icon'],
                'color'   => $icon_info['color'],
                'label'   => $icon_info['label'],
            ];
        }

        $data = [
            'id'        => $hike->ID,
            'title'     => get_the_title($hike),
            'gpxUrl'    => $gpx_url,
            'zoom'      => (int)get_post_meta($hike->ID, '_layer_zoom', true) ?: 13,
            'pois'      => $poi_data,
            'distance'  => get_post_meta($hike->ID, '_distance_km', true) ?: 0,
            'elevation' => get_post_meta($hike->ID, '_elevation_gain', true) ?: 0,
            'maxEle'    => get_post_meta($hike->ID, '_elevation_max', true) ?: 0,
            'icons'     => Icons::get_all(),
        ];

        return rest_ensure_response($data);
    }
}
