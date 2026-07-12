# Escursionismo Mappe

A WordPress plugin for managing hikes and Points of Interest (POIs) with interactive maps. Modern, full-screen editor with GPX support, elevation profiles, and a Gutenberg block.

## Features

- **Custom Post Types** — Hikes (`hike`) and POIs (`poi`) with full REST API support.
- **Full-Screen Admin Editor** — Replace the default WordPress editor with a bespoke layout: interactive Leaflet map, GPX drag-and-drop upload, inline POI CRUD, icon picker, auto-save.
- **Interactive Maps** — Leaflet.js frontend with multiple basemaps (OpenStreetMap, OpenTopoMap), marker clustering, GPX track overlay, colored elevation tracks, elevation profile chart (Chart.js), minimap overview, and a reset-view button.
- **Gutenberg Block** — Insert `escursionismo-mappe/hike-map` anywhere. Pick a hike via search + select in the block inspector; a card preview shows distance, elevation, and POI count.
- **POI Management** — 60+ predefined icon categories with custom colors and labels. Create, edit, delete, and link POIs directly from the hike editor or via REST.
- **GPX Parsing** — Upload GPX files; the parser extracts distance, elevation gain, elevation max, and a sampled elevation profile for Chart.js rendering.
- **Master Map** — Shortcode `[hike_master_map]` displays all hikes on a single overview map.
- **Migration Tool** — Import data from the legacy Leaflet Maps Marker plugin (layers → hikes, markers → POIs).
- **Export Tool** — Download all hikes and POIs (including GPX files) as a portable JSON file.
- **Templates** — Custom single-hike and archive-hike templates included.

## Requirements

- WordPress 7.0 or later
- PHP 8.0 or later

## Installation

1. Upload the `escursionismo-mappe` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress admin **Plugins** screen.
3. A new **Escursionismo** menu appears in the admin sidebar.

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[hike_map id="123"]` | Display a single hike map. Attributes: `id`, `height` (default `580px`), `width` (default `100%`), `cluster` (`true`/`false`). |
| `[hike_master_map]` | Display all hikes on one map. Attributes: `height` (default `600px`), `width` (default `100%`). |

## Gutenberg Block

Search for "Mappa Escursione" in the block inserter. Use the inspector sidebar to find and select a hike.

## REST API Endpoints

All endpoints are under `wp-json/escursionismo-mappe/v1/`. Authentication via `wp_rest` nonce.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/hikes` | List all hikes |
| GET | `/map-data/{id}` | Get full map data (POIs, GPX, elevation) for a hike |
| GET | `/pois/nearby?lat=&lng=&radius=` | Find POIs near coordinates |
| GET | `/pois/search?s=` | Search POIs by title |
| POST | `/pois` | Create a POI |
| PUT | `/pois/{id}` | Update a POI |
| DELETE | `/pois/{id}` | Delete a POI |
| PUT | `/pois/{id}/link` | Link a POI to a hike |
| PUT | `/hikes/{id}` | Update a hike (title, content, status) |
| POST | `/gpx/upload` | Upload a GPX file |

## WP-CLI Commands

```bash
wp em migrate          # Import escursioni and POI from Leaflet Maps Marker
wp em retry-gpx        # Retry downloading missing GPX files
```

## Developer Notes

- The plugin uses a PSR-4-like autoloader under the `EscursionismoMappe\` namespace.
- Frontend assets are enqueued via `Map_Renderer::enqueue_assets()`.
- All map data is localized into `window.emMapData_{id}` for the single map and `window.emMasterMapData` for the master map.

## License

GPL-2.0-or-later

## Author

**Walter Tosolini** — [tosolini.info](https://www.tosolini.info)
