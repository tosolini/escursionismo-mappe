<?php
/**
 * Template per singola escursione (single-hike)
 */
get_header();

while (have_posts()):
    the_post();
    $hike_id = get_the_ID();
?>
<div class="em-single-hike" style="max-width:1200px;margin:0 auto;padding:20px;">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>
            <?php if (has_excerpt()): ?>
                <div class="entry-excerpt"><?php the_excerpt(); ?></div>
            <?php endif; ?>
        </header>

        <div class="entry-content">
            <div class="em-hike-map">
                <?php echo do_shortcode('[hike_map id="' . $hike_id . '"]'); ?>
            </div>

            <?php
            $content = get_the_content();
            if (!empty(trim($content))):
            ?>
            <div class="em-hike-description" style="margin-top:24px;">
                <h2><?php _e('Descrizione', 'escursionismo-mappe'); ?></h2>
                <?php the_content(); ?>
            </div>
            <?php endif; ?>

            <?php
            $pois = get_posts([
                'post_type' => 'poi',
                'posts_per_page' => -1,
                'meta_query' => [['key' => '_hike_ids', 'value' => $hike_id, 'compare' => 'LIKE']],
            ]);
            if (!empty($pois)):
            ?>
            <div class="em-hike-pois" style="margin-top:24px;">
                <h2><?php printf(__('Punti di Interesse (%d)', 'escursionismo-mappe'), count($pois)); ?></h2>
                <div class="em-poi-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <?php foreach ($pois as $poi):
                        $icon = get_post_meta($poi->ID, '_icon_type', true);
                        $icon_info = EscursionismoMappe\Icons::get($icon);
                        $lat = get_post_meta($poi->ID, '_lat', true);
                        $lon = get_post_meta($poi->ID, '_lon', true);
                    ?>
                    <div class="em-poi-card" style="border:1px solid #e0e0e0;border-radius:8px;padding:12px;background:#fff;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="background:<?php echo $icon_info['color']; ?>;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;">
                                <i class="fa-solid <?php echo $icon_info['icon']; ?>"></i>
                            </span>
                            <strong><?php echo get_the_title($poi); ?></strong>
                        </div>
                        <?php if ($lat && $lon): ?>
                            <div style="font-size:0.85em;color:#666;">
                                <?php printf('%.5f, %.5f', $lat, $lon); ?>
                            </div>
                        <?php endif; ?>
                        <?php
                        $content = get_post_field('post_content', $poi->ID);
                        if (!empty(trim($content))):
                        ?>
                        <div style="margin-top:8px;font-size:0.9em;line-height:1.5;">
                            <?php echo apply_filters('the_content', $content); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </article>
</div>
<?php
endwhile;
get_footer();
