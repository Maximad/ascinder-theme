<?php
/**
 * 404 template.
 *
 * @package ASCINDER
 */
get_header();
?>
<section class="content-wrap">
	<div class="content-card">
		<h1><?php esc_html_e('Page not found', 'ascinder'); ?></h1>
		<p><?php esc_html_e('The page you are looking for does not exist.', 'ascinder'); ?></p>
		<p><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Return home', 'ascinder'); ?></a></p>
	</div>
</section>
<?php
get_footer();
