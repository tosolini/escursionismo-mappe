<?php
/**
 * Template per archivio escursioni (archive-hike)
 */
get_header();
?>
<div class="em-archive-hike" style="max-width:1200px;margin:0 auto;padding:20px;">
    <header class="page-header">
        <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
        <?php
        $total = wp_count_posts('hike')->publish ?? 0;
        echo '<p>' . sprintf(__('%d escursioni totali', 'escursionismo-mappe'), $total) . '</p>';
        ?>
    </header>

    <?php echo do_shortcode('[hike_master_map]'); ?>

    <div class="em-hike-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-top:24px;">
        <?php while (have_posts()): the_post();
            $dist = get_post_meta(get_the_ID(), '_distance_km', true);
            $ele = get_post_meta(get_the_ID(), '_elevation_gain', true);
            $poi_count = count(get_posts([
                'post_type' => 'poi', 'fields' => 'ids', 'posts_per_page' => -1,
                'meta_query' => [['key' => '_hike_ids', 'value' => get_the_ID(), 'compare' => 'LIKE']],
            ]));
        ?>
        <div class="em-hike-card" style="border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;background:#fff;">
            <?php if (has_post_thumbnail()): ?>
                <div style="height:180px;overflow:hidden;">
                    <?php the_post_thumbnail('medium', ['style' => 'width:100%;height:100%;object-fit:cover;']); ?>
                </div>
            <?php endif; ?>
            <div style="padding:16px;">
                <h3><a href="<?php the_permalink(); ?>" style="text-decoration:none;"><?php the_title(); ?></a></h3>
                <?php if (has_excerpt()): ?>
                    <p style="color:#555;font-size:0.9em;margin:8px 0;"><?php echo get_the_excerpt(); ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:16px;font-size:0.85em;color:#666;margin-top:8px;">
                    <?php if ($dist): ?><span><strong><?php _e('Distanza:', 'escursionismo-mappe'); ?></strong> <?php printf('%.1f km', $dist); ?></span><?php endif; ?>
                    <?php if ($ele): ?><span><strong><?php _e('Dislivello+:', 'escursionismo-mappe'); ?></strong> <?php printf('%d m', $ele); ?></span><?php endif; ?>
                    <?php if ($poi_count): ?><span><strong><?php _e('POI:', 'escursionismo-mappe'); ?></strong> <?php echo $poi_count; ?></span><?php endif; ?>
                </div>
                <a href="<?php the_permalink(); ?>" class="button" style="margin-top:12px;display:inline-block;">
                    <?php _e('Vedi escursione', 'escursionismo-mappe'); ?> &rarr;
                </a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <div class="em-pagination" style="margin-top:24px;">
        <?php the_posts_pagination(); ?>
    </div>
</div>
<?php
get_footer();
