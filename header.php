<?php
/**
 * Theme header.
 *
 * @package ASCINDER
 */
$switcher = ascinder_get_language_switcher();
$contact_url = ascinder_get_contact_url();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header" role="banner">
	<div class="site-header__inner">
		<a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('ASCINDER Home', 'ascinder'); ?>">ASCINDER</a>
		<nav class="site-nav" aria-label="<?php esc_attr_e('Primary navigation', 'ascinder'); ?>">
			<?php if (is_front_page()) : ?>
				<a href="#panels" class="site-nav__link"><?php esc_html_e('Explore', 'ascinder'); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url(home_url('/#panels')); ?>" class="site-nav__link"><?php esc_html_e('Explore', 'ascinder'); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url($contact_url); ?>" class="site-nav__link"><?php esc_html_e('Contact', 'ascinder'); ?></a>
		</nav>
		<a class="lang-toggle" href="<?php echo esc_url($switcher['url']); ?>" rel="alternate"><?php echo esc_html($switcher['label']); ?></a>
	</div>
</header>
<main id="site-content" class="site-main" role="main">
