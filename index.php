<?php
/**
 * Index template.
 *
 * @package ASCINDER
 */
get_header();
?>
<section class="content-wrap">
	<div class="content-card">
		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : the_post(); ?>
				<article <?php post_class('entry'); ?>>
					<h1 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
					<div class="entry-meta"><?php echo esc_html(get_the_date()); ?></div>
					<div class="entry-content"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e('No content found.', 'ascinder'); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
