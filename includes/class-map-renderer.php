<?php
namespace EscursionismoMappe;

class Map_Renderer {
    public function render($atts) {
        $atts = shortcode_atts([
            'id'      => 0,
            'height'  => '580px',
            'width'   => '100%',
            'cluster' => 'true',
        ], $atts);

        $hike_id = $this->resolve_hike_id($atts['id']);
        if (!$hike_id) {
            $hikes = get_posts([
                'post_type'      => 'hike',
                'posts_per_page' => 1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            if (empty($hikes)) {
                return '<p>' . __('Nessuna escursione trovata.', 'escursionismo-mappe') . '</p>';
            }
            $hike_id = $hikes[0]->ID;
        }

        $hike = get_post($hike_id);
        if (!$hike || $hike->post_type !== 'hike') {
            return '<p>' . __('Escursione non trovata.', 'escursionismo-mappe') . '</p>';
        }

        $gpx_file_id = (int)get_post_meta($hike->ID, '_gpx_file_id', true);
        $gpx_url = $gpx_file_id ? wp_get_attachment_url($gpx_file_id) : '';
        $zoom = (int)get_post_meta($hike->ID, '_layer_zoom', true) ?: 13;

        $pois = get_posts([
            'post_type'      => 'poi',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => '_hike_ids', 'value' => $hike->ID, 'compare' => 'LIKE'],
            ],
        ]);

        $this->enqueue_assets();

        $poi_data = [];
        foreach ($pois as $poi) {
            $icon_type = get_post_meta($poi->ID, '_icon_type', true);
            $icon_info = Icons::get($icon_type);
            $poi_data[] = [
                'id'        => $poi->ID,
                'title'     => get_the_title($poi),
                'content'   => apply_filters('the_content', get_post_field('post_content', $poi->ID)),
                'lat'       => (float)get_post_meta($poi->ID, '_lat', true),
                'lon'       => (float)get_post_meta($poi->ID, '_lon', true),
                'icon_type' => $icon_type,
                'icon_fa'   => $icon_info['icon'],
                'icon_color'=> $icon_info['color'],
                'icon_label'=> $icon_info['label'],
                'link'      => get_permalink($poi),
            ];
        }

        $elevation_profile = get_post_meta($hike->ID, '_elevation_profile', true);
        if (!empty($elevation_profile)) {
            $elevation_profile = json_decode($elevation_profile, true);
        } else {
            $elevation_profile = null;
        }

        $basemap_key = get_post_meta($hike->ID, '_basemap', true);
        if (!isset(Basemaps::get_all()[$basemap_key])) $basemap_key = 'OpenStreetMap';

        $map_data = [
            'hikeId'            => $hike->ID,
            'hikeTitle'         => get_the_title($hike),
            'gpxUrl'            => $gpx_url,
            'zoom'              => $zoom,
            'cluster'           => $atts['cluster'] === 'true',
            'pois'              => $poi_data,
            'icons'             => Icons::get_all(),
            'basemap'           => $basemap_key,
            'basemaps'          => Basemaps::get_all(),
            'elevationProfile'  => $elevation_profile,
            'elevationGain'     => get_post_meta($hike->ID, '_elevation_gain', true) ?: 0,
            'elevationMax'      => get_post_meta($hike->ID, '_elevation_max', true) ?: 0,
        ];

        $map_id = 'em-map-' . $hike->ID;
        wp_localize_script('em-map', 'emMapData_' . $hike->ID, $map_data);

        $chart_id = $map_id . '-chart';

        $output = '<div class="em-map-wrapper" style="height:' . esc_attr($atts['height']) . ';width:' . esc_attr($atts['width']) . '" data-has-chart="' . ($elevation_profile ? '1' : '0') . '">';
        $output .= '<div id="' . $map_id . '" class="em-map-container"></div>';
        $output .= '<div class="em-map-loading">' . __('Caricamento mappa...', 'escursionismo-mappe') . '</div>';
        $output .= '</div>';

        $stats = [];
        $distance = get_post_meta($hike->ID, '_distance_km', true);
        $elevation = get_post_meta($hike->ID, '_elevation_gain', true);
        $elevation_max = get_post_meta($hike->ID, '_elevation_max', true);

        if ($distance) $stats[] = '<span class="em-stat"><i class="fa-solid fa-route"></i> ' . sprintf('%.1f km', $distance) . '</span>';
        if ($elevation) $stats[] = '<span class="em-stat"><i class="fa-solid fa-arrow-trend-up"></i> ' . sprintf('%d m+', $elevation) . '</span>';
        if ($elevation_max) $stats[] = '<span class="em-stat"><i class="fa-solid fa-mountain"></i> ' . sprintf('max %d m', $elevation_max) . '</span>';
        if ($gpx_url) $stats[] = '<a href="' . esc_url($gpx_url) . '" class="em-gpx-download em-stat" download><i class="fa-solid fa-download"></i> GPX</a>';
        if (!empty($stats)) {
            $output .= '<div class="em-map-stats">' . implode(' &middot; ', $stats) . '</div>';
        }

        if ($elevation_profile) {
            $output .= '<div class="em-elevation-chart" id="' . $chart_id . '-wrap"><h4 class="em-chart-title">' . __('Profilo altimetrico', 'escursionismo-mappe') . '</h4><canvas id="' . $chart_id . '" class="em-chart-canvas" width="600" height="180"></canvas></div>';
        }

        return $output;
    }

    private function resolve_hike_id($id) {
        if (empty($id) || $id === '0') return 0;
        $post = get_post((int)$id);
        if ($post && $post->post_type === 'hike') return $post->ID;
        $posts = get_posts([
            'post_type'      => 'hike',
            'meta_key'       => '_legacy_layer_id',
            'meta_value'     => (int)$id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        return !empty($posts) ? $posts[0] : 0;
    }

    public static function enqueue_assets() {
        $ver = '1.9.4';
        $cluster_ver = '1.5.3';
        $gpx_ver = '2.1.2';

        wp_enqueue_style('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/' . $ver . '/leaflet.css', [], $ver);
        wp_enqueue_script('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/' . $ver . '/leaflet.min.js', [], $ver, true);

        wp_enqueue_script('leaflet-cluster', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/' . $cluster_ver . '/leaflet.markercluster.min.js', ['leaflet'], $cluster_ver, true);
        wp_enqueue_style('leaflet-cluster', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/' . $cluster_ver . '/MarkerCluster.min.css', ['leaflet'], $cluster_ver);
        wp_enqueue_style('leaflet-cluster-default', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/' . $cluster_ver . '/MarkerCluster.Default.min.css', ['leaflet'], $cluster_ver);

        wp_enqueue_script('leaflet-gpx', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/' . $gpx_ver . '/gpx.min.js', ['leaflet'], $gpx_ver, true);

        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', [], '4.4.1', true);

        wp_enqueue_style('leaflet-minimap', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-minimap/3.6.1/Control.MiniMap.min.css', ['leaflet'], '3.6.1');
        wp_enqueue_script('leaflet-minimap', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-minimap/3.6.1/Control.MiniMap.min.js', ['leaflet'], '3.6.1', true);

        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css', [], '6.6.0');
        wp_enqueue_style('em-map', EM_PLUGIN_URL . 'assets/css/hike-map.css', ['leaflet'], EM_VERSION);
        wp_enqueue_script('em-map', EM_PLUGIN_URL . 'assets/js/hike-map.js', ['leaflet', 'leaflet-gpx', 'leaflet-cluster', 'chart-js', 'leaflet-minimap'], EM_VERSION, true);

        add_action('wp_head', function () {
            echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com">';
            echo '<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">';
        }, 1);
    }
}
