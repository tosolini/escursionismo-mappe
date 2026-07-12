<?php
namespace EscursionismoMappe;

class Basemaps {
    private static $mapping = [];

    public static function init() {
        self::$mapping = self::default_mapping();
    }

    public static function get_all() {
        return self::$mapping;
    }

    private static function default_mapping() {
        return [
            'OpenStreetMap' => [
                'url'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                'maxZoom'     => 19,
                'label'       => 'OpenStreetMap',
            ],
            'OpenTopoMap' => [
                'url'         => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'attribution' => '&copy; <a href="https://opentopomap.org">OpenTopoMap</a> contributors',
                'maxZoom'     => 17,
                'label'       => 'OpenTopoMap',
            ],
        ];
    }
}
