<?php
/**
 * Single post template.
 *
 * @package ASCINDER
 */
get_header();
?>
<section class="content-wrap">
	<div class="content-card">
		<?php while (have_posts()) : the_post(); ?>
			<article <?php post_class('entry'); ?>>
				<h1 class="entry-title"><?php the_title(); ?></h1>
				<p class="entry-meta"><?php echo esc_html(get_the_date()); ?></p>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</section>
<?php
get_footer();
