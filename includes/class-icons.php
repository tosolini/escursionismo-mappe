<?php
namespace EscursionismoMappe;

class Icons {
    private static $mapping = [];

    public static function init() {
        self::$mapping = self::default_mapping();
    }

    public static function get_all() {
        return self::$mapping;
    }

    public static function get($icon_name) {
        $clean = basename($icon_name, '.png');
        return self::$mapping[$clean] ?? self::$mapping['marker'];
    }

    private static function default_mapping() {
        return [
            'marker'                  => ['icon' => 'fa-location-dot', 'color' => '#e74c3c', 'label' => 'Generico'],
            'geo-vetta'               => ['icon' => 'fa-mountain', 'color' => '#27ae60', 'label' => 'Vetta'],
            'geo-sella-forca'         => ['icon' => 'fa-flag', 'color' => '#1abc9c', 'label' => 'Sella/Forcella'],
            'geo-panoramica-vista'    => ['icon' => 'fa-camera', 'color' => '#2ecc71', 'label' => 'Panorama'],
            'geo-collina'             => ['icon' => 'fa-mound', 'color' => '#27ae60', 'label' => 'Collina'],
            'strutture-casra-malga'   => ['icon' => 'fa-house-chimney', 'color' => '#e67e22', 'label' => 'Casera/Malga'],
            'strutture-casera-malga-ristoro' => ['icon' => 'fa-mug-saucer', 'color' => '#e67e22', 'label' => 'Malga con ristoro'],
            'strutture-casera-malga-bar' => ['icon' => 'fa-martini-glass', 'color' => '#e67e22', 'label' => 'Malga/Bar'],
            'strutture-casera'        => ['icon' => 'fa-house', 'color' => '#e67e22', 'label' => 'Casera'],
            'strutture-casera-disuso' => ['icon' => 'fa-house-crack', 'color' => '#95a5a6', 'label' => 'Casera in disuso'],
            'strutture-rovine'        => ['icon' => 'fa-building-columns', 'color' => '#95a5a6', 'label' => 'Rovine'],
            'strutture-chiesetta-cappella' => ['icon' => 'fa-church', 'color' => '#9b59b6', 'label' => 'Chiesetta/Cappella'],
            'strutture-bivacco'       => ['icon' => 'fa-tent', 'color' => '#e67e22', 'label' => 'Bivacco'],
            'strutture-picnic'        => ['icon' => 'fa-basket-shopping', 'color' => '#f39c12', 'label' => 'Area picnic'],
            'strutture-croce'         => ['icon' => 'fa-cross', 'color' => '#8e44ad', 'label' => 'Croce'],
            'strutture-icona-capitello' => ['icon' => 'fa-monument', 'color' => '#8e44ad', 'label' => 'Capitello'],
            'strutture-guerra'        => ['icon' => 'fa-helmet-safety', 'color' => '#7f8c8d', 'label' => 'Struttura guerra'],
            'strutture-guerra-bunker' => ['icon' => 'fa-shield-halved', 'color' => '#7f8c8d', 'label' => 'Bunker'],
            'strutture-guerra-bunker-cava' => ['icon' => 'fa-kaaba', 'color' => '#7f8c8d', 'label' => 'Bunker in cava'],
            'strutture-guerra-tomba'  => ['icon' => 'fa-skull', 'color' => '#7f8c8d', 'label' => 'Tomba di guerra'],
            'strutture-ponte'         => ['icon' => 'fa-bridge', 'color' => '#3498db', 'label' => 'Ponte'],
            'strutture-ponteradio'    => ['icon' => 'fa-tower-cell', 'color' => '#3498db', 'label' => 'Ponte radio'],
            'strutture-grotta'        => ['icon' => 'fa-mountain-cave', 'color' => '#34495e', 'label' => 'Grotta'],
            'strutture-scalinata'     => ['icon' => 'fa-stairs', 'color' => '#f39c12', 'label' => 'Scalinata'],
            'strutture-edificio-grosso' => ['icon' => 'fa-city', 'color' => '#f39c12', 'label' => 'Edificio'],
            'strutture-rovine'        => ['icon' => 'fa-building-columns', 'color' => '#95a5a6', 'label' => 'Rovine'],
            'acqua-sorgente'          => ['icon' => 'fa-faucet', 'color' => '#2980b9', 'label' => 'Sorgente'],
            'acqua-fontana'           => ['icon' => 'fa-faucet-drip', 'color' => '#2980b9', 'label' => 'Fontana'],
            'acqua-potabile'          => ['icon' => 'fa-glass-water', 'color' => '#2980b9', 'label' => 'Acqua potabile'],
            'acqua-cascata'           => ['icon' => 'fa-waterfall', 'color' => '#3498db', 'label' => 'Cascata'],
            'acqua-torrente'          => ['icon' => 'fa-water', 'color' => '#2980b9', 'label' => 'Torrente'],
            'acqua-lago'              => ['icon' => 'fa-water-ladder', 'color' => '#2980b9', 'label' => 'Lago'],
            'acqua-stagno'            => ['icon' => 'fa-fish', 'color' => '#2980b9', 'label' => 'Stagno'],
            'direction-segnavia'      => ['icon' => 'fa-signs-post', 'color' => '#f1c40f', 'label' => 'Segnavia'],
            'direction_split'         => ['icon' => 'fa-code-branch', 'color' => '#f1c40f', 'label' => 'Bivio'],
            'direction_up'            => ['icon' => 'fa-arrow-up', 'color' => '#f1c40f', 'label' => 'Salita'],
            'direction_upright'       => ['icon' => 'fa-arrow-up-right-from-square', 'color' => '#f1c40f', 'label' => 'Salita destra'],
            'direction_upleft'        => ['icon' => 'fa-arrow-up-left-from-square', 'color' => '#f1c40f', 'label' => 'Salita sinistra'],
            'direction_upthenright'   => ['icon' => 'fa-turn-up', 'color' => '#f1c40f', 'label' => 'Poi destra'],
            'direction_upthenleft'    => ['icon' => 'fa-turn-up', 'color' => '#f1c40f', 'label' => 'Poi sinistra'],
            'direction_rightup'       => ['icon' => 'fa-arrow-trend-up', 'color' => '#f1c40f', 'label' => 'Destra in salita'],
            'direction_rightdown'     => ['icon' => 'fa-arrow-trend-down', 'color' => '#f1c40f', 'label' => 'Destra in discesa'],
            'direction_leftup'        => ['icon' => 'fa-arrow-trend-up', 'color' => '#f1c40f', 'label' => 'Sinistra salita'],
            'direction_leftdown'      => ['icon' => 'fa-arrow-trend-down', 'color' => '#f1c40f', 'label' => 'Sinistra discesa'],
            'direction_right'         => ['icon' => 'fa-arrow-right', 'color' => '#f1c40f', 'label' => 'Destra'],
            'direction_downright'     => ['icon' => 'fa-arrow-down-right', 'color' => '#f1c40f', 'label' => 'Discesa destra'],
            'direction_downthenleft'  => ['icon' => 'fa-turn-down', 'color' => '#f1c40f', 'label' => 'Scendi poi sinistra'],
            'direction_downthenright' => ['icon' => 'fa-turn-down', 'color' => '#f1c40f', 'label' => 'Scendi poi destra'],
            'sport-hiking'            => ['icon' => 'fa-person-hiking', 'color' => '#e74c3c', 'label' => 'Sentiero'],
            'sport-arrampicata'       => ['icon' => 'fa-person-walking-arrow-up', 'color' => '#c0392b', 'label' => 'Arrampicata/Ferrata'],
            'sport-ciaspole'          => ['icon' => 'fa-shoe-prints', 'color' => '#2c3e50', 'label' => 'Ciaspole'],
            'pericolo'                => ['icon' => 'fa-triangle-exclamation', 'color' => '#e74c3c', 'label' => 'Pericolo'],
            'pericolo-valanga'        => ['icon' => 'fa-triangle-exclamation', 'color' => '#c0392b', 'label' => 'Pericolo valanghe'],
            'trasporto-parcheggio'    => ['icon' => 'fa-square-parking', 'color' => '#7f8c8d', 'label' => 'Parcheggio'],
            'trasporto-cabinovia'     => ['icon' => 'fa-cable-car', 'color' => '#7f8c8d', 'label' => 'Funivia/Cabinovia'],
            'trasporto-tunnel'        => ['icon' => 'fa-train', 'color' => '#7f8c8d', 'label' => 'Galleria/Tunnel'],
            'trasporto-segnavia'      => ['icon' => 'fa-signs-post', 'color' => '#7f8c8d', 'label' => 'Segnavia'],
            'service-photo'           => ['icon' => 'fa-camera-retro', 'color' => '#9b59b6', 'label' => 'Webcam'],
            'animale-mucca'           => ['icon' => 'fa-cow', 'color' => '#8B4513', 'label' => 'Mucca'],
            'animale-orso'            => ['icon' => 'fa-paw', 'color' => '#8B4513', 'label' => 'Fauna'],
            'marker-information'      => ['icon' => 'fa-circle-info', 'color' => '#3498db', 'label' => 'Informazioni'],
            'marker-pinother'         => ['icon' => 'fa-map-pin', 'color' => '#e74c3c', 'label' => 'Punto generico'],
            'tempo-ghiaccio-neve'     => ['icon' => 'fa-snowflake', 'color' => '#bdc3c7', 'label' => 'Ghiaccio/Neve'],
            'number_02'               => ['icon' => 'fa-2', 'color' => '#f39c12', 'label' => 'Tornante 2'],
        ];
    }
}
