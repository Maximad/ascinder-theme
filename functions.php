<?php
/**
 * ASCINDER Theme functions.
 *
 * @package ASCINDER
 */

if (! defined('ASCINDER_VERSION')) {
	define('ASCINDER_VERSION', '0.1.0');
}

/**
 * Theme setup.
 */
function ascinder_theme_setup()
{
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
	register_nav_menus(
		array(
			'primary' => esc_html__('Primary Menu', 'ascinder'),
		)
	);
}
add_action('after_setup_theme', 'ascinder_theme_setup');

/**
 * Enqueue theme assets.
 */
function ascinder_enqueue_assets()
{
	$theme = wp_get_theme();
	$version = $theme->get('Version') ? $theme->get('Version') : ASCINDER_VERSION;

	wp_enqueue_style('ascinder-style', get_stylesheet_uri(), array(), $version);
	wp_enqueue_style('ascinder-app', get_template_directory_uri() . '/assets/app.css', array('ascinder-style'), $version);

	wp_enqueue_script('ascinder-app', get_template_directory_uri() . '/assets/app.js', array(), $version, true);

	$current_lang = ascinder_get_current_lang();
	$data_file = ('ar' === $current_lang) ? 'panels.ar.json' : 'panels.en.json';

	wp_localize_script(
		'ascinder-app',
		'ascinderData',
		array(
			'lang'              => $current_lang,
			'isRTL'             => is_rtl(),
			'panelsUrl'         => esc_url_raw(get_template_directory_uri() . '/assets/' . $data_file),
			'homeUrl'           => esc_url_raw(home_url('/')),
			'contactUrl'        => esc_url_raw(ascinder_get_contact_url()),
			'i18n'              => array(
				'prev'   => esc_html__('Previous panel', 'ascinder'),
				'next'   => esc_html__('Next panel', 'ascinder'),
				'toggle' => esc_html__('Toggle orientation', 'ascinder'),
			),
		)
	);
}
add_action('wp_enqueue_scripts', 'ascinder_enqueue_assets');

/**
 * Add body classes.
 *
 * @param array<int, string> $classes Existing classes.
 *
 * @return array<int, string>
 */
function ascinder_body_classes($classes)
{
	$classes[] = 'ascinder-theme';
	$classes[] = 'lang-' . sanitize_html_class(ascinder_get_current_lang());

	if (is_front_page()) {
		$classes[] = 'is-interactive-home';
	}

	return $classes;
}
add_filter('body_class', 'ascinder_body_classes');

/**
 * Get current language with Polylang fallback.
 */
function ascinder_get_current_lang()
{
	if (function_exists('pll_current_language')) {
		$lang = pll_current_language('slug');
		if (is_string($lang) && '' !== $lang) {
			return $lang;
		}
	}

	$locale = get_locale();
	if (is_string($locale) && 0 === strpos(strtolower($locale), 'ar')) {
		return 'ar';
	}

	return is_rtl() ? 'ar' : 'en';
}

/**
 * Get the opposite language slug for EN/AR experience.
 */
function ascinder_get_other_lang()
{
	return ('ar' === ascinder_get_current_lang()) ? 'en' : 'ar';
}

/**
 * Build language switch URL + label.
 *
 * @return array{url: string, label: string}
 */
function ascinder_get_language_switcher()
{
	$other_lang = ascinder_get_other_lang();
	$label = ('ar' === $other_lang) ? 'العربية' : 'English';
	$url = home_url('/');

	if (function_exists('pll_home_url')) {
		$url = pll_home_url($other_lang);

		if (is_front_page()) {
			$url = pll_home_url($other_lang);
		} elseif (is_singular()) {
			$translated_id = pll_get_post(get_queried_object_id(), $other_lang);
			if (! empty($translated_id)) {
				$url = get_permalink($translated_id);
			}
		} elseif (is_tax() || is_category() || is_tag()) {
			$term = get_queried_object();
			if ($term instanceof WP_Term) {
				$translated_term_id = pll_get_term($term->term_id, $other_lang);
				if (! empty($translated_term_id)) {
					$term_link = get_term_link((int) $translated_term_id, $term->taxonomy);
					if (! is_wp_error($term_link)) {
						$url = $term_link;
					}
				}
			}
		}
	}

	return array(
		'url'   => is_string($url) ? $url : home_url('/'),
		'label' => $label,
	);
}

/**
 * Attempt to get contact page url in current language.
 */
function ascinder_get_contact_url()
{
	$fallback = home_url('/contact/');

	if (function_exists('pll_get_post') && function_exists('pll_current_language')) {
		$current_lang = ascinder_get_current_lang();
		$contact = get_page_by_path('contact');
		if ($contact instanceof WP_Post) {
			$translated = pll_get_post($contact->ID, $current_lang);
			if (! empty($translated)) {
				$link = get_permalink($translated);
				if (is_string($link)) {
					return $link;
				}
			}
		}
	}

	return $fallback;
}

/**
 * Arabic-Indic digits map (normalizes Persian digits too).
 */
function ascinder_to_arabic_indic_digits($value)
{
	if (! is_string($value) || '' === $value) {
		return $value;
	}

	$map = array(
		'0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
		'5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
		'۰' => '٠', '۱' => '١', '۲' => '٢', '۳' => '٣', '۴' => '٤',
		'۵' => '٥', '۶' => '٦', '۷' => '٧', '۸' => '٨', '۹' => '٩',
	);

	return strtr($value, $map);
}

/**
 * Convert Gregorian month names to Levant Arabic month names.
 */
function ascinder_levant_months($value)
{
	if (! is_string($value) || '' === $value) {
		return $value;
	}

	$months = array(
		'January' => 'كانون الثاني',
		'February' => 'شباط',
		'March' => 'آذار',
		'April' => 'نيسان',
		'May' => 'أيار',
		'June' => 'حزيران',
		'July' => 'تموز',
		'August' => 'آب',
		'September' => 'أيلول',
		'October' => 'تشرين الأول',
		'November' => 'تشرين الثاني',
		'December' => 'كانون الأول',
	);

	return strtr($value, $months);
}

/**
 * Apply Arabic front-end formatting for dates and numbers.
 */
function ascinder_format_arabic_frontend($value)
{
	if (is_admin() || 'ar' !== ascinder_get_current_lang()) {
		return $value;
	}

	if (! is_string($value)) {
		return $value;
	}

	$value = ascinder_levant_months($value);
	return ascinder_to_arabic_indic_digits($value);
}
add_filter('date_i18n', 'ascinder_format_arabic_frontend', 20);
add_filter('wp_date', 'ascinder_format_arabic_frontend', 20);
add_filter('number_format_i18n', 'ascinder_format_arabic_frontend', 20);

/**
 * Add preconnect hints for local assets.
 *
 * @param array<int|string, mixed> $urls URLs.
 * @param string                   $relation_type Relation type.
 * @return array<int|string, mixed>
 */
function ascinder_resource_hints($urls, $relation_type)
{
	if ('preconnect' === $relation_type) {
		$urls[] = array(
			'href' => home_url('/'),
		);
	}
	return $urls;
}
add_filter('wp_resource_hints', 'ascinder_resource_hints', 10, 2);
