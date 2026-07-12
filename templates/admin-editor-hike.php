<?php
/**
 * Custom full-screen admin editor for hike post type.
 * Replaces the standard WordPress post editor.
 */
namespace EscursionismoMappe;

if (!defined('ABSPATH')) exit;

if (!isset($post) || !$post || $post->post_type !== 'hike') {
    $post = get_post((int)($_GET['post'] ?? 0));
    if (!$post || $post->post_type !== 'hike') {
        wp_die(__('Post non valido.', 'escursionismo-mappe'));
    }
}

$hike_id = $post->ID;
$title = get_the_title($post);
$content = $post->post_content;
$status = $post->post_status;
$gpx_file_id = (int) get_post_meta($hike_id, '_gpx_file_id', true);
$gpx_url = '';
if ($gpx_file_id) {
    $url = wp_get_attachment_url($gpx_file_id);
    if ($url) $gpx_url = $url;
}
if (empty($gpx_url)) {
    $gpx_url = get_post_meta($hike_id, '_gpx_url', true);
}
$distance = get_post_meta($hike_id, '_distance_km', true);
$elevation = get_post_meta($hike_id, '_elevation_gain', true);
$elevation_max = get_post_meta($hike_id, '_elevation_max', true);
$basemap = get_post_meta($hike_id, '_basemap', true);
$basemaps = Basemaps::get_all();
if (!isset($basemaps[$basemap])) $basemap = 'OpenStreetMap';

$status_labels = [
    'draft'   => __('Bozza', 'escursionismo-mappe'),
    'publish' => __('Pubblicato', 'escursionismo-mappe'),
    'pending' => __('In attesa', 'escursionismo-mappe'),
    'private' => __('Privato', 'escursionismo-mappe'),
];

$pois = get_posts([
    'post_type'      => 'poi',
    'posts_per_page' => -1,
    'meta_query'     => [
        ['key' => '_hike_ids', 'value' => $hike_id, 'compare' => 'LIKE'],
    ],
]);

$poi_data = [];
foreach ($pois as $poi) {
    $icon_type = get_post_meta($poi->ID, '_icon_type', true);
    $poi_data[] = [
        'id'        => $poi->ID,
        'title'     => get_the_title($poi),
        'content'   => get_post_field('post_content', $poi->ID),
        'lat'       => (float) get_post_meta($poi->ID, '_lat', true),
        'lon'       => (float) get_post_meta($poi->ID, '_lon', true),
        'icon_type' => $icon_type ?: 'marker',
    ];
}

$preview_url = get_permalink($hike_id);
?>
<div class="em-admin-editor" id="em-admin-editor" data-hike-id="<?php echo esc_attr($hike_id); ?>">
    <div class="em-editor-header">
        <div class="em-editor-header-left">
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=hike')); ?>" class="em-back-btn">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('Torna all\'elenco', 'escursionismo-mappe'); ?>
            </a>
            <span class="em-status-badge em-status-<?php echo esc_attr($status); ?>">
                <?php echo esc_html($status_labels[$status] ?? $status); ?>
            </span>
            <select id="em-post-status" class="em-status-select" aria-label="<?php _e('Stato', 'escursionismo-mappe'); ?>">
                <?php foreach ($status_labels as $val => $label): ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($status, $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="em-editor-header-right">
            <?php if ($preview_url): ?>
            <a href="<?php echo esc_url($preview_url); ?>" target="_blank" class="button" id="em-preview-btn">
                <span class="dashicons dashicons-external"></span>
                <?php _e('Anteprima', 'escursionismo-mappe'); ?>
            </a>
            <?php endif; ?>
            <div class="em-save-status" id="em-save-status"></div>
            <button type="button" class="button button-primary em-save-btn" id="em-save-btn">
                <span class="dashicons dashicons-yes-alt"></span>
                <span class="em-save-label"><?php _e('Salva', 'escursionismo-mappe'); ?></span>
                <span class="spinner" style="display:none"></span>
            </button>
        </div>
    </div>

    <div class="em-editor-title-wrap">
        <input type="text" id="em-post-title" class="em-post-title" value="<?php echo esc_attr($title); ?>"
               placeholder="<?php esc_attr_e('Titolo escursione...', 'escursionismo-mappe'); ?>" autofocus />
    </div>

    <div class="em-editor-body">
        <div class="em-editor-map-area">
            <div id="em-editor-map" class="em-editor-map-container"></div>
            <div class="em-map-zoom-display" id="em-map-zoom-display">Zoom: <span id="em-zoom-level">--</span></div>
        </div>

        <div class="em-editor-sidebar">
            <!-- POI Section -->
            <div class="em-side-section em-section-pois">
                <div class="em-side-section-header">
                    <h3><?php _e('Punti di Interesse', 'escursionismo-mappe'); ?></h3>
                    <span class="em-poi-count-badge" id="em-poi-count-badge">0</span>
                </div>
                <div id="em-poi-list" class="em-poi-list-container">
                    <p class="em-poi-empty"><?php _e('Nessun POI. Clicca sulla mappa per aggiungerne uno.', 'escursionismo-mappe'); ?></p>
                </div>
                <div id="em-poi-form-area" class="em-poi-form-area" style="display:none">
                    <div class="em-poi-form-header">
                        <span id="em-poi-form-title"><?php _e('Nuovo POI', 'escursionismo-mappe'); ?></span>
                        <button type="button" class="button button-small em-poi-form-close" id="em-poi-form-close">&times;</button>
                    </div>
                    <div class="em-poi-form-body">
                        <div class="em-poi-field">
                            <label for="em-poi-title-input"><?php _e('Nome', 'escursionismo-mappe'); ?></label>
                            <div class="em-autocomplete-wrap">
                                <input type="text" id="em-poi-title-input" class="em-poi-title-input" placeholder="<?php esc_attr_e('Nome POI...', 'escursionismo-mappe'); ?>" autocomplete="off" />
                                <div id="em-poi-suggestions" class="em-autocomplete-dropdown" style="display:none"></div>
                            </div>
                        </div>
                        <div class="em-poi-field">
                            <label><?php _e('Icona', 'escursionismo-mappe'); ?></label>
                            <div id="em-poi-icon-grid" class="em-icon-grid"></div>
                        </div>
                        <div class="em-poi-field">
                            <label for="em-poi-desc-input"><?php _e('Descrizione', 'escursionismo-mappe'); ?></label>
                            <textarea id="em-poi-desc-input" class="em-poi-desc-input" rows="2" placeholder="<?php esc_attr_e('Opzionale', 'escursionismo-mappe'); ?>"></textarea>
                        </div>
                        <div class="em-poi-field em-poi-coords-display" id="em-poi-coords"></div>
                        <input type="hidden" id="em-poi-edit-id" value="" />
                        <input type="hidden" id="em-poi-lat" value="" />
                        <input type="hidden" id="em-poi-lon" value="" />
                    </div>
                    <div class="em-poi-form-footer">
                        <button type="button" class="button button-primary em-poi-save-btn" id="em-poi-save-btn">
                            <?php _e('Aggiungi POI', 'escursionismo-mappe'); ?>
                        </button>
                        <button type="button" class="button em-poi-delete-btn" id="em-poi-delete-btn" style="display:none;color:#d63638">
                            <?php _e('Rimuovi', 'escursionismo-mappe'); ?>
                        </button>
                        <button type="button" class="button em-poi-cancel-btn" id="em-poi-cancel-btn">
                            <?php _e('Annulla', 'escursionismo-mappe'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- GPX Section -->
            <div class="em-side-section em-section-gpx">
                <h3><?php _e('Tracciato GPX', 'escursionismo-mappe'); ?></h3>
                <div id="em-gpx-dropzone" class="em-gpx-dropzone<?php echo $gpx_url ? ' has-gpx' : ''; ?>">
                    <div class="em-gpx-dropzone-content">
                        <span class="dashicons dashicons-upload"></span>
                        <p class="em-gpx-drop-text"><?php _e('Trascina un file GPX qui', 'escursionismo-mappe'); ?></p>
                        <p class="em-gpx-or-text"><?php _e('oppure', 'escursionismo-mappe'); ?></p>
                        <button type="button" class="button button-small" id="em-gpx-select-btn"><?php _e('Seleziona file', 'escursionismo-mappe'); ?></button>
                        <br/>
                        <button type="button" class="button button-small" id="em-gpx-media-btn" style="margin-top:6px">
                            <?php _e('Libreria media', 'escursionismo-mappe'); ?>
                        </button>
                    </div>
                    <div class="em-gpx-progress" style="display:none">
                        <div class="em-gpx-progress-bar"></div>
                    </div>
                    <input type="file" id="em-gpx-file-input" accept=".gpx,application/gpx+xml" style="display:none" />
                </div>
                <div id="em-gpx-info" class="em-gpx-info" style="<?php echo $gpx_url ? '' : 'display:none'; ?>">
                    <div class="em-gpx-filename">
                        <span class="dashicons dashicons-media-document"></span>
                        <span id="em-gpx-filename-text"><?php echo $gpx_url ? esc_html(basename($gpx_url)) : ''; ?></span>
                    </div>
                    <div id="em-gpx-stats" class="em-gpx-stats" style="<?php echo ($distance || $elevation) ? '' : 'display:none'; ?>">
                        <?php if ($distance): ?>
                            <span class="em-stat"><span class="dashicons dashicons-admin-site"></span> <span id="em-stat-dist"><?php printf('%.1f km', $distance); ?></span></span>
                        <?php endif; ?>
                        <?php if ($elevation): ?>
                            <span class="em-stat"><span class="dashicons dashicons-arrow-up-alt"></span> <span id="em-stat-ele"><?php printf('%d m', $elevation); ?></span></span>
                        <?php endif; ?>
                        <?php if ($elevation_max): ?>
                            <span class="em-stat"><span class="dashicons dashicons-location"></span> <span id="em-stat-maxele"><?php printf('%d m', $elevation_max); ?></span></span>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button button-small em-gpx-remove-btn" id="em-gpx-remove-btn">
                        <?php _e('Rimuovi GPX', 'escursionismo-mappe'); ?>
                    </button>
                </div>
                <input type="hidden" id="em-gpx-url" value="<?php echo esc_url($gpx_url); ?>" />
                <input type="hidden" id="em-gpx-file-id" value="<?php echo esc_attr($gpx_file_id); ?>" />
            </div>

            <!-- Details Section -->
            <div class="em-side-section em-section-details">
                <h3><?php _e('Dettagli', 'escursionismo-mappe'); ?></h3>
                <div class="em-detail-row">
                    <label for="em-basemap"><?php _e('Base map', 'escursionismo-mappe'); ?></label>
                    <select id="em-basemap" class="em-basemap-select">
                        <?php foreach ($basemaps as $key => $bm): ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($basemap, $key); ?>><?php echo esc_html($bm['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="em-detail-row">
                    <label><?php _e('ID', 'escursionismo-mappe'); ?></label>
                    <span class="em-detail-value">#<?php echo $hike_id; ?></span>
                </div>
                <div class="em-detail-row">
                    <label><?php _e('Slug', 'escursionismo-mappe'); ?></label>
                    <span class="em-detail-value"><?php echo esc_html($post->post_name ?: '—'); ?></span>
                </div>
                <div class="em-detail-row">
                    <label><?php _e('Aggiornato', 'escursionismo-mappe'); ?></label>
                    <span class="em-detail-value"><?php echo get_the_modified_time('d/m/Y H:i', $post); ?></span>
                </div>
            </div>

            <!-- Description Section (collapsible) -->
            <div class="em-side-section em-section-description">
                <div class="em-side-section-header em-collapse-toggle" data-target="em-desc-collapse">
                    <h3><?php _e('Descrizione', 'escursionismo-mappe'); ?></h3>
                    <span class="dashicons dashicons-arrow-up-alt2"></span>
                </div>
                <div id="em-desc-collapse" class="em-collapse-content">
                    <textarea id="em-post-content" class="em-post-content" placeholder="<?php esc_attr_e('Descrizione dell\'escursione...', 'escursionismo-mappe'); ?>" rows="6"><?php echo esc_textarea($content); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <form id="em-save-form" method="post" action="<?php echo esc_url(admin_url('post.php')); ?>">
        <?php wp_nonce_field('update-post_' . $hike_id); ?>
        <input type="hidden" name="action" value="editpost" />
        <input type="hidden" name="post_ID" value="<?php echo esc_attr($hike_id); ?>" />
        <input type="hidden" name="post_type" value="hike" />
        <input type="hidden" name="original_post_status" value="<?php echo esc_attr($status); ?>" />
        <input type="hidden" name="em_gpx_nonce" value="<?php echo wp_create_nonce('em_save_gpx'); ?>" />
        <input type="hidden" name="em_gpx_url" id="em-form-gpx-url" value="<?php echo esc_url($gpx_url); ?>" />
        <input type="hidden" name="post_title" id="em-form-title" value="<?php echo esc_attr($title); ?>" />
        <input type="hidden" name="content" id="em-form-content" value="<?php echo esc_textarea($content); ?>" />
        <input type="hidden" name="post_status" id="em-form-status" value="<?php echo esc_attr($status); ?>" />
        <input type="hidden" name="em_basemap" id="em-form-basemap" value="<?php echo esc_attr($basemap); ?>" />
        <input type="hidden" name="save" value="1" />
    </form>
</div>
<?php
wp_localize_script('em-admin-hike', 'emAdminHikeData', [
    'restUrl'      => rest_url('escursionismo-mappe/v1'),
    'wpRestUrl'    => rest_url('wp/v2'),
    'nonce'        => wp_create_nonce('wp_rest'),
    'hikeId'       => $hike_id,
    'postStatus'   => $status,
    'gpxUrl'       => $gpx_url ?: '',
    'gpxFileId'    => $gpx_file_id,
    'pois'         => $poi_data,
    'icons'        => Icons::get_all(),
    'basemap'      => $basemap,
    'basemaps'     => Basemaps::get_all(),
    'containerId'  => 'em-editor-map',
    'poiListId'    => 'em-poi-list',
]);
