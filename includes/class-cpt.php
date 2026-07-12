<?php
namespace EscursionismoMappe;

class CPT {
    public static function register() {
        self::register_hike_cpt();
        self::register_poi_cpt();
        self::register_meta();
    }

    private static function register_hike_cpt() {
        $labels = [
            'name'                  => _x('Escursioni', 'Post Type General Name', 'escursionismo-mappe'),
            'singular_name'         => _x('Escursione', 'Post Type Singular Name', 'escursionismo-mappe'),
            'menu_name'             => __('Escursionismo', 'escursionismo-mappe'),
            'all_items'             => __('Tutte le escursioni', 'escursionismo-mappe'),
            'add_new'               => __('Aggiungi nuova', 'escursionismo-mappe'),
            'add_new_item'          => __('Aggiungi nuova escursione', 'escursionismo-mappe'),
            'edit_item'             => __('Modifica escursione', 'escursionismo-mappe'),
            'view_item'             => __('Vedi escursione', 'escursionismo-mappe'),
            'search_items'          => __('Cerca escursioni', 'escursionismo-mappe'),
            'not_found'             => __('Nessuna escursione trovata', 'escursionismo-mappe'),
            'not_found_in_trash'    => __('Nessuna escursione nel cestino', 'escursionismo-mappe'),
        ];
        $args = [
            'label'                 => __('Escursione', 'escursionismo-mappe'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'public'                => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-location-alt',
            'show_in_rest'          => true,
            'rest_base'             => 'hikes',
            'has_archive'           => true,
            'rewrite'               => ['slug' => 'escursione'],
            'show_in_graphql'       => true,
        ];
        register_post_type('hike', $args);
    }

    private static function register_poi_cpt() {
        $labels = [
            'name'                  => _x('Punti di Interesse', 'Post Type General Name', 'escursionismo-mappe'),
            'singular_name'         => _x('POI', 'Post Type Singular Name', 'escursionismo-mappe'),
            'menu_name'             => __('POI', 'escursionismo-mappe'),
            'all_items'             => __('Tutti i POI', 'escursionismo-mappe'),
            'add_new'               => __('Aggiungi nuovo POI', 'escursionismo-mappe'),
            'add_new_item'          => __('Aggiungi nuovo POI', 'escursionismo-mappe'),
            'edit_item'             => __('Modifica POI', 'escursionismo-mappe'),
            'view_item'             => __('Vedi POI', 'escursionismo-mappe'),
            'search_items'          => __('Cerca POI', 'escursionismo-mappe'),
            'not_found'             => __('Nessun POI trovato', 'escursionismo-mappe'),
            'not_found_in_trash'    => __('Nessun POI nel cestino', 'escursionismo-mappe'),
        ];
        $args = [
            'label'                 => __('POI', 'escursionismo-mappe'),
            'labels'                => $labels,
            'supports'              => ['title', 'editor', 'custom-fields'],
            'public'                => true,
            'show_in_menu'          => 'edit.php?post_type=hike',
            'menu_icon'             => 'dashicons-location',
            'show_in_rest'          => true,
            'rest_base'             => 'pois',
            'has_archive'           => false,
            'rewrite'               => ['slug' => 'poi'],
            'show_in_graphql'       => true,
        ];
        register_post_type('poi', $args);
    }

    private static function register_meta() {
        $meta_fields = [
            ['key' => '_gpx_file_id', 'type' => 'integer', 'default' => 0, 'object_subtype' => 'hike'],
            ['key' => '_gpx_url', 'type' => 'string', 'default' => '', 'object_subtype' => 'hike'],
            ['key' => '_layer_zoom', 'type' => 'integer', 'default' => 13, 'object_subtype' => 'hike'],
            ['key' => '_basemap', 'type' => 'string', 'default' => 'OpenStreetMap', 'object_subtype' => 'hike'],
            ['key' => '_distance_km', 'type' => 'float', 'default' => 0, 'object_subtype' => 'hike'],
            ['key' => '_elevation_gain', 'type' => 'integer', 'default' => 0, 'object_subtype' => 'hike'],
            ['key' => '_elevation_max', 'type' => 'integer', 'default' => 0, 'object_subtype' => 'hike'],
            ['key' => '_elevation_profile', 'type' => 'string', 'default' => '', 'object_subtype' => 'hike'],
            ['key' => '_lat', 'type' => 'float', 'default' => 0, 'object_subtype' => 'poi'],
            ['key' => '_lon', 'type' => 'float', 'default' => 0, 'object_subtype' => 'poi'],
            ['key' => '_icon_type', 'type' => 'string', 'default' => 'marker', 'object_subtype' => 'poi'],
            ['key' => '_hike_ids', 'type' => 'string', 'default' => '', 'object_subtype' => 'poi'],
        ];

        foreach ($meta_fields as $field) {
            register_post_meta('', $field['key'], [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => $field['type'],
                'default'       => $field['default'],
                'object_subtype' => $field['object_subtype'],
            ]);
        }
    }
}
