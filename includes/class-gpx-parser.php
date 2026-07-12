<?php
namespace EscursionismoMappe;

class GPX_Parser {
    private $xml;
    public $distance_km = 0;
    public $elevation_gain = 0;
    public $elevation_max = 0;
    public $elevation_min = PHP_FLOAT_MAX;
    public $points = [];
    public $trkpts_count = 0;
    private $distances = [];

    public function __construct($file_path) {
        if (!file_exists($file_path)) {
            return;
        }
        $content = file_get_contents($file_path);
        if (!$content) {
            return;
        }
        $this->xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOWARNING | LIBXML_NOCDATA);
        if (!$this->xml) {
            return;
        }
        $this->parse();
    }

    private function parse() {
        $namespaces = $this->xml->getNamespaces(true);
        $gpx_ns = $this->xml->children($namespaces[''] ?? '');

        foreach ($gpx_ns->trk as $trk) {
            foreach ($trk->trkseg as $seg) {
                foreach ($seg->trkpt as $pt) {
                    $attrs = $pt->attributes();
                    $lat = (float)$attrs['lat'];
                    $lon = (float)$attrs['lon'];
                    $ele = isset($pt->ele) ? (float)$pt->ele : 0;

                    $this->points[] = ['lat' => $lat, 'lon' => $lon, 'ele' => $ele];
                    $this->trkpts_count++;

                    if ($ele > $this->elevation_max) {
                        $this->elevation_max = $ele;
                    }
                    if ($ele > 0 && $ele < $this->elevation_min) {
                        $this->elevation_min = $ele;
                    }
                }
            }
        }

        $this->calculate_distance();
        $this->calculate_elevation_gain();
    }

    private function calculate_distance() {
        $total = 0;
        $this->distances = [0];
        for ($i = 1; $i < count($this->points); $i++) {
            $p1 = $this->points[$i - 1];
            $p2 = $this->points[$i];
            $total += $this->haversine($p1['lat'], $p1['lon'], $p2['lat'], $p2['lon']);
            $this->distances[] = $total;
        }
        $this->distance_km = round($total, 2);
    }

    public function get_elevation_profile($max_points = 200) {
        if (empty($this->points) || empty($this->distances)) {
            return [];
        }

        $count = count($this->points);
        if ($count <= $max_points) {
            return $this->build_profile($this->points, $this->distances);
        }

        $step = $count / $max_points;
        $sampled = [];
        $sampled_dists = [];
        for ($i = 0; $i < $max_points; $i++) {
            $idx = (int)round($i * $step);
            if ($idx >= $count) $idx = $count - 1;
            $sampled[] = $this->points[$idx];
            $sampled_dists[] = $this->distances[$idx];
        }
        if (end($sampled) !== end($this->points)) {
            $sampled[count($sampled) - 1] = end($this->points);
            $sampled_dists[count($sampled_dists) - 1] = end($this->distances);
        }

        return $this->build_profile($sampled, $sampled_dists);
    }

    private function build_profile($pts, $dists) {
        $profile = [];
        foreach ($pts as $i => $pt) {
            $profile[] = [
                'd' => round($dists[$i], 2),
                'e' => round($pt['ele']),
                'lat' => round($pt['lat'], 6),
                'lon' => round($pt['lon'], 6),
            ];
        }
        return $profile;
    }

    private function calculate_elevation_gain() {
        $gain = 0;
        for ($i = 1; $i < count($this->points); $i++) {
            $diff = $this->points[$i]['ele'] - $this->points[$i - 1]['ele'];
            if ($diff > 0) {
                $gain += $diff;
            }
        }
        $this->elevation_gain = round($gain);
    }

    private function haversine($lat1, $lon1, $lat2, $lon2) {
        $R = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}
