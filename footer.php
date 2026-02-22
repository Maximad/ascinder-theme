<?php
/**
 * Theme footer.
 *
 * @package ASCINDER
 */
?>
</main>
<footer class="site-footer" role="contentinfo">
	<div class="site-footer__inner">
		<p>&copy; <?php echo esc_html(wp_date('Y')); ?> ASCINDER</p>
		<nav aria-label="<?php esc_attr_e('Footer navigation', 'ascinder'); ?>">
			<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'ascinder'); ?></a>
			<?php if (get_privacy_policy_url()) : ?>
				<a href="<?php echo esc_url(get_privacy_policy_url()); ?>"><?php esc_html_e('Privacy', 'ascinder'); ?></a>
			<?php endif; ?>
		</nav>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
