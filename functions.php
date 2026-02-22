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
 * Resolve front-end source mode for panel data.
 *
 * @return string
 */
function ascinder_get_panels_source_mode()
{
	$mode = defined('ASCINDER_PANELS_SOURCE_MODE') ? (string) ASCINDER_PANELS_SOURCE_MODE : 'cpt';
	$allowed = array('cpt', 'cpt-with-json-fallback', 'json-only');
	return in_array($mode, $allowed, true) ? $mode : 'cpt';
}

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
	$cache_mode = (defined('WP_DEBUG') && WP_DEBUG) ? 'no-store' : 'default';

	wp_localize_script(
		'ascinder-app',
		'ascinderData',
		array(
			'lang'              => $current_lang,
			'isRTL'             => is_rtl(),
			'sourceMode'        => ascinder_get_panels_source_mode(),
			'cacheMode'         => $cache_mode,
			'panelsEndpointUrl' => esc_url_raw(rest_url('ascinder/v1/panels')),
			'defaultLang'       => function_exists('pll_default_language') ? (string) pll_default_language('slug') : 'en',
			'allowLangFallback' => true,
			'panelsJsonUrl'     => esc_url_raw(get_template_directory_uri() . '/assets/' . $data_file),
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
 * Resolve panel JSON asset path for current language.
 *
 * @param string $lang Language slug.
 * @return string
 */
function ascinder_get_panels_asset_path($lang)
{
	$file = ('ar' === $lang) ? 'panels.ar.json' : 'panels.en.json';
	return trailingslashit(get_template_directory()) . 'assets/' . $file;
}

/**
 * Get cache version for panel payload transients.
 *
 * @return int
 */
function ascinder_get_panels_cache_version()
{
	$version = (int) get_option('ascinder_panels_payload_version', 1);
	return $version > 0 ? $version : 1;
}

/**
 * Bump cache version for panel payload transients.
 */
function ascinder_bump_panels_cache_version()
{
	update_option('ascinder_panels_payload_version', ascinder_get_panels_cache_version() + 1, false);
}

/**
 * Build media payload.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $media_type Media type.
 * @return array<string,mixed>
 */
function ascinder_build_panel_media_payload($attachment_id, $media_type)
{
	$empty = array('type' => $media_type, 'url' => '', 'width' => 0, 'height' => 0);
	if (! $attachment_id) {
		return $empty;
	}

	if ('video' === $media_type) {
		$url = wp_get_attachment_url($attachment_id);
		$meta = wp_get_attachment_metadata($attachment_id);
		$mime = get_post_mime_type($attachment_id);

		return array(
			'type'   => 'video',
			'url'    => is_string($url) ? $url : '',
			'width'  => isset($meta['width']) ? (int) $meta['width'] : 0,
			'height' => isset($meta['height']) ? (int) $meta['height'] : 0,
			'mime'   => is_string($mime) ? $mime : '',
		);
	}

	$image = wp_get_attachment_image_src($attachment_id, '2048x2048');
	if (! is_array($image)) {
		$image = wp_get_attachment_image_src($attachment_id, 'large');
	}

	return array(
		'type'   => 'image',
		'url'    => is_array($image) && isset($image[0]) ? (string) $image[0] : '',
		'width'  => is_array($image) && isset($image[1]) ? (int) $image[1] : 0,
		'height' => is_array($image) && isset($image[2]) ? (int) $image[2] : 0,
	);
}

/**
 * Determine if requested lang should fall back to current/default when Polylang is active.
 *
 * @return bool
 */
function ascinder_panels_allow_polylang_lang_fallback()
{
	return (bool) apply_filters('ascinder_panels_allow_polylang_lang_fallback', true);
}

/**
 * Build endpoint payload shape for front-end panels from CPT.
 *
 * @param string $lang Requested language slug.
 * @param string $context Request context.
 * @return array{lang:string,rtl:bool,generatedAt:string,panels:array<int,array<string,mixed>>}
 */
function ascinder_get_panels_payload($lang = '', $context = 'front')
{
	$requested_lang = is_string($lang) && '' !== $lang ? sanitize_key($lang) : ascinder_get_current_lang();
	$cache_key = 'ascinder_panels_' . md5($requested_lang . '|' . $context . '|' . (string) ascinder_get_panels_cache_version());
	$cached = get_transient($cache_key);
	if (is_array($cached)) {
		return $cached;
	}

	$query_args = array(
		'post_type'      => 'asc_panel',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array('menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'),
		'meta_key'       => '_asc_enabled',
		'meta_value'     => '1',
	);

	if (function_exists('pll_current_language')) {
		$query_args['lang'] = $requested_lang;
	}

	$posts = get_posts($query_args);

	if (function_exists('pll_current_language') && empty($posts) && ascinder_panels_allow_polylang_lang_fallback()) {
		$fallback_lang = pll_default_language('slug');
		if (! is_string($fallback_lang) || '' === $fallback_lang) {
			$fallback_lang = pll_current_language('slug');
		}
		if (is_string($fallback_lang) && '' !== $fallback_lang) {
			$query_args['lang'] = sanitize_key($fallback_lang);
			$posts = get_posts($query_args);
		}
	}

	$panels = array();
	foreach ($posts as $panel_post) {
		$panel_id = (int) $panel_post->ID;
		$media_type = (string) get_post_meta($panel_id, '_asc_media_type', true);
		if (! in_array($media_type, array('image', 'video'), true)) {
			$media_type = 'image';
		}

		$media_attachment_id = absint(get_post_meta($panel_id, '_asc_media_attachment_id', true));
		$cta_label = trim((string) get_post_meta($panel_id, '_asc_cta_label', true));
		$cta_href = trim((string) get_post_meta($panel_id, '_asc_cta_url', true));
		$overlay = (string) get_post_meta($panel_id, '_asc_overlay_preset', true);
		if (! in_array($overlay, array('default', 'dark', 'left-focus', 'right-focus'), true)) {
			$overlay = 'default';
		}

		$panels[] = array(
			'id'            => (string) $panel_post->post_name,
			'kicker'        => (string) get_post_meta($panel_id, '_asc_kicker', true),
			'title'         => get_the_title($panel_id),
			'text'          => wp_strip_all_tags((string) $panel_post->post_content),
			'media'         => ascinder_build_panel_media_payload($media_attachment_id, $media_type),
			'cta'           => ('' !== $cta_label && '' !== $cta_href) ? array('label' => $cta_label, 'href' => esc_url_raw($cta_href)) : null,
			'overlayPreset' => $overlay,
		);
	}

	$response_lang = $requested_lang;
	if (function_exists('pll_current_language') && ! empty($query_args['lang']) && is_string($query_args['lang'])) {
		$response_lang = $query_args['lang'];
	}

	$payload = array(
		'lang'        => $response_lang,
		'rtl'         => ('ar' === strtolower($response_lang)),
		'generatedAt' => gmdate('c'),
		'panels'      => $panels,
	);

	set_transient($cache_key, $payload, HOUR_IN_SECONDS);

	return $payload;
}

/**
 * Register panels endpoint.
 */
function ascinder_register_panels_endpoint()
{
	register_rest_route(
		'ascinder/v1',
		'/panels',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => static function (WP_REST_Request $request) {
				$lang = (string) $request->get_param('lang');
				$context = (string) $request->get_param('context');
				if ('' === $context) {
					$context = 'front';
				}
				return rest_ensure_response(ascinder_get_panels_payload($lang, $context));
			},
			'permission_callback' => '__return_true',
			'args'                => array(
				'lang'    => array('required' => false, 'sanitize_callback' => 'sanitize_key'),
				'context' => array('required' => false, 'sanitize_callback' => 'sanitize_key'),
			),
		)
	);
}
add_action('rest_api_init', 'ascinder_register_panels_endpoint');

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

/**
 * Register Homepage Panel CPT.
 */
function ascinder_register_panel_cpt()
{
	$labels = array(
		'name'               => esc_html__('Homepage Panels', 'ascinder'),
		'singular_name'      => esc_html__('Homepage Panel', 'ascinder'),
		'menu_name'          => esc_html__('Homepage Panels', 'ascinder'),
		'add_new'            => esc_html__('Add Panel', 'ascinder'),
		'add_new_item'       => esc_html__('Add New Homepage Panel', 'ascinder'),
		'edit_item'          => esc_html__('Edit Homepage Panel', 'ascinder'),
		'new_item'           => esc_html__('New Homepage Panel', 'ascinder'),
		'view_item'          => esc_html__('View Homepage Panel', 'ascinder'),
		'search_items'       => esc_html__('Search Homepage Panels', 'ascinder'),
		'not_found'          => esc_html__('No panels found.', 'ascinder'),
		'not_found_in_trash' => esc_html__('No panels found in Trash.', 'ascinder'),
	);

	register_post_type(
		'asc_panel',
		array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-slides',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'has_archive'        => false,
			'rewrite'            => false,
			'exclude_from_search'=> true,
			'supports'           => array('title', 'editor', 'page-attributes'),
		)
	);
}
add_action('init', 'ascinder_register_panel_cpt');

/**
 * Add panel settings metabox.
 */
function ascinder_add_panel_metaboxes()
{
	add_meta_box(
		'ascinder-panel-settings',
		esc_html__('Panel Settings', 'ascinder'),
		'ascinder_render_panel_metabox',
		'asc_panel',
		'normal',
		'default'
	);
}
add_action('add_meta_boxes', 'ascinder_add_panel_metaboxes');

/**
 * Render panel settings metabox.
 *
 * @param WP_Post $post Post.
 */
function ascinder_render_panel_metabox($post)
{
	wp_nonce_field('ascinder_save_panel_meta', 'ascinder_panel_meta_nonce');

	$kicker = get_post_meta($post->ID, '_asc_kicker', true);
	$cta_label = get_post_meta($post->ID, '_asc_cta_label', true);
	$cta_url = get_post_meta($post->ID, '_asc_cta_url', true);
	$media_type = get_post_meta($post->ID, '_asc_media_type', true);
	$media_id = absint(get_post_meta($post->ID, '_asc_media_attachment_id', true));
	$overlay = get_post_meta($post->ID, '_asc_overlay_preset', true);
	$enabled = get_post_meta($post->ID, '_asc_enabled', true);

	$allowed_media_types = array('image', 'video');
	if (! in_array($media_type, $allowed_media_types, true)) {
		$media_type = 'image';
	}

	$allowed_overlays = array('default', 'dark', 'left-focus', 'right-focus');
	if (! in_array($overlay, $allowed_overlays, true)) {
		$overlay = 'default';
	}

	$attachment_file = $media_id ? wp_get_attachment_url($media_id) : '';
	?>
	<p>
		<label for="asc_kicker"><strong><?php echo esc_html__('Kicker', 'ascinder'); ?></strong></label><br />
		<input type="text" class="widefat" id="asc_kicker" name="asc_kicker" value="<?php echo esc_attr((string) $kicker); ?>" />
	</p>
	<p>
		<label for="asc_cta_label"><strong><?php echo esc_html__('CTA Label', 'ascinder'); ?></strong></label><br />
		<input type="text" class="widefat" id="asc_cta_label" name="asc_cta_label" value="<?php echo esc_attr((string) $cta_label); ?>" />
	</p>
	<p>
		<label for="asc_cta_url"><strong><?php echo esc_html__('CTA URL', 'ascinder'); ?></strong></label><br />
		<input type="url" class="widefat" id="asc_cta_url" name="asc_cta_url" value="<?php echo esc_attr((string) $cta_url); ?>" />
	</p>
	<p>
		<label for="asc_media_type"><strong><?php echo esc_html__('Media Type', 'ascinder'); ?></strong></label><br />
		<select id="asc_media_type" name="asc_media_type">
			<option value="image" <?php selected($media_type, 'image'); ?>><?php echo esc_html__('Image', 'ascinder'); ?></option>
			<option value="video" <?php selected($media_type, 'video'); ?>><?php echo esc_html__('Video', 'ascinder'); ?></option>
		</select>
	</p>
	<div data-asc-panel-media-root>
		<p>
			<label><strong><?php echo esc_html__('Media Attachment', 'ascinder'); ?></strong></label><br />
			<input type="hidden" id="asc_media_attachment_id" name="asc_media_attachment_id" value="<?php echo esc_attr((string) $media_id); ?>" data-asc-panel-media-input />
			<button class="button" data-asc-panel-media-select><?php echo esc_html__('Select / Replace Media', 'ascinder'); ?></button>
			<button class="button" data-asc-panel-media-clear><?php echo esc_html__('Clear Media', 'ascinder'); ?></button>
			<input type="hidden" value="<?php echo esc_attr($media_type); ?>" data-asc-panel-media-type />
		</p>
		<div data-asc-panel-media-preview>
			<?php
			if ('image' === $media_type && $media_id) {
				echo wp_kses_post(wp_get_attachment_image($media_id, 'thumbnail', false, array('style' => 'max-width: 180px; height: auto;')));
			} elseif ('video' === $media_type && $media_id && is_string($attachment_file)) {
				echo esc_html(basename($attachment_file));
			} else {
				echo '<em>' . esc_html__('No media selected.', 'ascinder') . '</em>';
			}
			?>
		</div>
	</div>
	<p>
		<label for="asc_overlay_preset"><strong><?php echo esc_html__('Overlay Preset', 'ascinder'); ?></strong></label><br />
		<select id="asc_overlay_preset" name="asc_overlay_preset">
			<option value="default" <?php selected($overlay, 'default'); ?>><?php echo esc_html__('Default', 'ascinder'); ?></option>
			<option value="dark" <?php selected($overlay, 'dark'); ?>><?php echo esc_html__('Dark', 'ascinder'); ?></option>
			<option value="left-focus" <?php selected($overlay, 'left-focus'); ?>><?php echo esc_html__('Left Focus', 'ascinder'); ?></option>
			<option value="right-focus" <?php selected($overlay, 'right-focus'); ?>><?php echo esc_html__('Right Focus', 'ascinder'); ?></option>
		</select>
	</p>
	<p>
		<label>
			<input type="checkbox" name="asc_enabled" value="1" <?php checked((string) $enabled, '1'); ?> />
			<?php echo esc_html__('Enabled', 'ascinder'); ?>
		</label>
	</p>
	<?php
}

/**
 * Persist panel metabox fields.
 *
 * @param int $post_id Post ID.
 */
function ascinder_save_panel_meta($post_id)
{
	if (! isset($_POST['ascinder_panel_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ascinder_panel_meta_nonce'])), 'ascinder_save_panel_meta')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if ('asc_panel' !== get_post_type($post_id)) {
		return;
	}

	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$kicker = isset($_POST['asc_kicker']) ? sanitize_text_field(wp_unslash($_POST['asc_kicker'])) : '';
	$cta_label = isset($_POST['asc_cta_label']) ? sanitize_text_field(wp_unslash($_POST['asc_cta_label'])) : '';
	$cta_url = isset($_POST['asc_cta_url']) ? esc_url_raw(wp_unslash($_POST['asc_cta_url'])) : '';
	$media_type = isset($_POST['asc_media_type']) ? sanitize_key(wp_unslash($_POST['asc_media_type'])) : 'image';
	$media_id = isset($_POST['asc_media_attachment_id']) ? absint(wp_unslash($_POST['asc_media_attachment_id'])) : 0;
	if ($media_id && 'attachment' !== get_post_type($media_id)) {
		$media_id = 0;
	}
	$overlay = isset($_POST['asc_overlay_preset']) ? sanitize_key(wp_unslash($_POST['asc_overlay_preset'])) : 'default';
	$enabled = isset($_POST['asc_enabled']) ? '1' : '0';

	if (! in_array($media_type, array('image', 'video'), true)) {
		$media_type = 'image';
	}

	if (! in_array($overlay, array('default', 'dark', 'left-focus', 'right-focus'), true)) {
		$overlay = 'default';
	}

	update_post_meta($post_id, '_asc_kicker', $kicker);
	update_post_meta($post_id, '_asc_cta_label', $cta_label);
	update_post_meta($post_id, '_asc_cta_url', $cta_url);
	update_post_meta($post_id, '_asc_media_type', $media_type);
	update_post_meta($post_id, '_asc_media_attachment_id', $media_id);
	update_post_meta($post_id, '_asc_overlay_preset', $overlay);
	update_post_meta($post_id, '_asc_enabled', $enabled);
}
add_action('save_post_asc_panel', 'ascinder_save_panel_meta');

/**
 * Invalidate cached panels payload when panel content changes.
 *
 * @param int $post_id Post ID.
 */
function ascinder_invalidate_panels_cache_on_save($post_id)
{
	if ('asc_panel' !== get_post_type($post_id)) {
		return;
	}
	ascinder_bump_panels_cache_version();
}
add_action('save_post_asc_panel', 'ascinder_invalidate_panels_cache_on_save', 30);

/**
 * Invalidate cached panels payload when posts are deleted.
 *
 * @param int $post_id Deleted post ID.
 */
function ascinder_invalidate_panels_cache_on_delete($post_id)
{
	if ('asc_panel' !== get_post_type($post_id)) {
		return;
	}
	ascinder_bump_panels_cache_version();
}
add_action('deleted_post', 'ascinder_invalidate_panels_cache_on_delete');

/**
 * Best-effort cache invalidation for Polylang translation updates.
 *
 * @param int $post_id Post ID.
 */
function ascinder_invalidate_panels_cache_on_translation_change($post_id)
{
	if ('asc_panel' !== get_post_type($post_id)) {
		return;
	}
	ascinder_bump_panels_cache_version();
}
if (function_exists('pll_current_language')) {
	add_action('pll_save_post', 'ascinder_invalidate_panels_cache_on_translation_change');
}

/**
 * Enqueue admin assets for panel CPT.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function ascinder_enqueue_panel_admin_assets($hook_suffix)
{
	if (! in_array($hook_suffix, array('post.php', 'post-new.php'), true)) {
		return;
	}

	$screen = get_current_screen();
	if (! $screen || 'asc_panel' !== $screen->post_type) {
		return;
	}

	wp_enqueue_media();

	wp_enqueue_script(
		'ascinder-panel-admin',
		get_template_directory_uri() . '/assets/admin-panels.js',
		array('jquery'),
		ASCINDER_VERSION,
		true
	);

	wp_localize_script(
		'ascinder-panel-admin',
		'ascPanelAdmin',
		array(
			'selectMedia' => esc_html__('Select panel media', 'ascinder'),
			'useMedia'    => esc_html__('Use this media', 'ascinder'),
		)
	);
}
add_action('admin_enqueue_scripts', 'ascinder_enqueue_panel_admin_assets');

/**
 * Register custom columns for panel CPT list table.
 *
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function ascinder_panel_columns($columns)
{
	$updated = array();

	foreach ($columns as $key => $label) {
		$updated[$key] = $label;
		if ('title' === $key) {
			$updated['asc_enabled'] = esc_html__('Enabled', 'ascinder');
			$updated['menu_order'] = esc_html__('Order', 'ascinder');
			$updated['asc_media'] = esc_html__('Media', 'ascinder');
			if (function_exists('pll_get_post_language')) {
				$updated['asc_language'] = esc_html__('Language', 'ascinder');
			}
		}
	}

	return $updated;
}
add_filter('manage_asc_panel_posts_columns', 'ascinder_panel_columns');

/**
 * Render panel custom column values.
 *
 * @param string $column Column name.
 * @param int    $post_id Post ID.
 */
function ascinder_panel_custom_column($column, $post_id)
{
	if ('asc_enabled' === $column) {
		echo ('1' === get_post_meta($post_id, '_asc_enabled', true)) ? esc_html__('Yes', 'ascinder') : esc_html__('No', 'ascinder');
	}

	if ('menu_order' === $column) {
		echo esc_html((string) get_post_field('menu_order', $post_id));
	}

	if ('asc_media' === $column) {
		$media_id = absint(get_post_meta($post_id, '_asc_media_attachment_id', true));
		$media_type = get_post_meta($post_id, '_asc_media_type', true);
		if (! $media_id) {
			echo '—';
			return;
		}

		if ('image' === $media_type) {
			echo wp_kses_post(wp_get_attachment_image($media_id, array(60, 60)));
			return;
		}

		$url = wp_get_attachment_url($media_id);
		echo esc_html(is_string($url) ? basename($url) : __('Video', 'ascinder'));
	}

	if ('asc_language' === $column && function_exists('pll_get_post_language')) {
		$lang = pll_get_post_language($post_id, 'slug');
		echo esc_html(is_string($lang) && '' !== $lang ? strtoupper($lang) : '—');
	}
}
add_action('manage_asc_panel_posts_custom_column', 'ascinder_panel_custom_column', 10, 2);

/**
 * Sort panel admin list deterministically.
 *
 * @param WP_Query $query Query.
 */
function ascinder_panel_admin_order($query)
{
	if (! is_admin() || ! $query->is_main_query()) {
		return;
	}

	if ('asc_panel' !== $query->get('post_type')) {
		return;
	}

	if ($query->get('orderby')) {
		return;
	}

	$query->set('orderby', array('menu_order' => 'ASC', 'date' => 'ASC', 'ID' => 'ASC'));
	$query->set('order', 'ASC');
}
add_action('pre_get_posts', 'ascinder_panel_admin_order');

/**
 * Best-effort Polylang integration: mark panel CPT as translatable.
 *
 * @param array<string, string> $post_types Post types.
 * @param bool                  $is_settings Whether in settings context.
 * @return array<string, string>
 */
function ascinder_polylang_panel_post_type($post_types, $is_settings)
{
	if ($is_settings) {
		$post_types['asc_panel'] = 'asc_panel';
	}
	return $post_types;
}
if (function_exists('pll_current_language')) {
	add_filter('pll_get_post_types', 'ascinder_polylang_panel_post_type', 10, 2);
}

/**
 * Import panel JSON files into asc_panel CPT.
 *
 * @param array<string,mixed> $args Import options.
 * @return array<string,mixed>
 */
function ascinder_import_panels_from_json($args = array())
{
	$defaults = array(
		'dry_run' => false,
		'log'     => null,
	);
	$options = wp_parse_args($args, $defaults);
	$dry_run = (bool) $options['dry_run'];
	$logger = is_callable($options['log']) ? $options['log'] : null;

	$files = array(
		'en' => ascinder_get_panels_asset_path('en'),
		'ar' => ascinder_get_panels_asset_path('ar'),
	);

	$report = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors'  => 0,
		'items'   => array(),
	);

	$translation_groups = array();

	foreach ($files as $lang => $file_path) {
		if (! file_exists($file_path) || ! is_readable($file_path)) {
			$report['errors']++;
			if ($logger) {
				$logger(sprintf('[%s] Missing/unreadable file: %s', $lang, $file_path));
			}
			continue;
		}

		$raw = file_get_contents($file_path);
		$data = json_decode(is_string($raw) ? $raw : '', true);
		if (! is_array($data)) {
			$report['errors']++;
			if ($logger) {
				$logger(sprintf('[%s] Invalid JSON in %s', $lang, $file_path));
			}
			continue;
		}

		foreach ($data as $index => $panel) {
			if (! is_array($panel)) {
				$report['skipped']++;
				continue;
			}

			$external_id = isset($panel['id']) ? sanitize_key((string) $panel['id']) : '';
			if ('' === $external_id) {
				$external_id = 'panel-' . (string) ($index + 1);
			}

			$title = isset($panel['title']) ? sanitize_text_field((string) $panel['title']) : '';
			$text = isset($panel['text']) ? wp_kses_post((string) $panel['text']) : '';
			$kicker = isset($panel['kicker']) ? sanitize_text_field((string) $panel['kicker']) : '';
			$cta_label = isset($panel['cta']['label']) ? sanitize_text_field((string) $panel['cta']['label']) : '';
			$cta_href = isset($panel['cta']['href']) ? esc_url_raw((string) $panel['cta']['href']) : '';
			$media_type = isset($panel['media']['type']) ? sanitize_key((string) $panel['media']['type']) : 'image';
			$media_url = isset($panel['media']['url']) ? esc_url_raw((string) $panel['media']['url']) : '';

			if (! in_array($media_type, array('image', 'video'), true)) {
				$media_type = 'image';
			}

			$existing = get_posts(
				array(
					'post_type'      => 'asc_panel',
					'post_status'    => array('publish', 'draft', 'pending', 'private'),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'   => '_asc_external_id',
							'value' => $external_id,
						),
						array(
							'key'   => '_asc_external_lang',
							'value' => $lang,
						),
					),
				)
			);

			$post_data = array(
				'post_type'    => 'asc_panel',
				'post_status'  => 'publish',
				'post_title'   => '' !== $title ? $title : strtoupper($lang) . ' ' . $external_id,
				'post_content' => $text,
				'menu_order'   => (int) $index,
				'post_name'    => sanitize_title($external_id . '-' . $lang),
			);

			$is_update = ! empty($existing);
			$post_id = 0;
			if ($is_update) {
				$post_data['ID'] = (int) $existing[0];
				$post_id = (int) $existing[0];
			}

			if ($dry_run) {
				if ($is_update) {
					$report['updated']++;
				} else {
					$report['created']++;
				}
				$report['items'][] = array('lang' => $lang, 'external_id' => $external_id, 'action' => $is_update ? 'update' : 'create', 'post_id' => $post_id);
				if ($logger) {
					$logger(sprintf('[dry-run] %s %s (%s)', $is_update ? 'update' : 'create', $external_id, $lang));
				}
				continue;
			}

			$result = $is_update ? wp_update_post($post_data, true) : wp_insert_post($post_data, true);
			if (is_wp_error($result) || ! is_numeric($result)) {
				$report['errors']++;
				if ($logger) {
					$logger(sprintf('[%s] Failed to save %s: %s', $lang, $external_id, is_wp_error($result) ? $result->get_error_message() : 'Unknown error'));
				}
				continue;
			}

			$post_id = (int) $result;
			update_post_meta($post_id, '_asc_external_id', $external_id);
			update_post_meta($post_id, '_asc_external_lang', $lang);
			update_post_meta($post_id, '_asc_kicker', $kicker);
			update_post_meta($post_id, '_asc_cta_label', $cta_label);
			update_post_meta($post_id, '_asc_cta_url', $cta_href);
			update_post_meta($post_id, '_asc_media_type', $media_type);
			update_post_meta($post_id, '_asc_enabled', '1');

			if ('' !== $media_url) {
				update_post_meta($post_id, '_asc_media_external_url', $media_url);
				$matched_attachment_id = absint(url_to_postid($media_url));
				if ($matched_attachment_id > 0 && 'attachment' === get_post_type($matched_attachment_id)) {
					update_post_meta($post_id, '_asc_media_attachment_id', $matched_attachment_id);
				}
			}

			if ($is_update) {
				$report['updated']++;
			} else {
				$report['created']++;
			}

			$report['items'][] = array('lang' => $lang, 'external_id' => $external_id, 'action' => $is_update ? 'update' : 'create', 'post_id' => $post_id);
			if ($logger) {
				$logger(sprintf('%s %s (%s) -> #%d', $is_update ? 'Updated' : 'Created', $external_id, $lang, $post_id));
			}

			if (! isset($translation_groups[$external_id])) {
				$translation_groups[$external_id] = array();
			}
			$translation_groups[$external_id][$lang] = $post_id;
		}
	}

	if (! $dry_run && function_exists('pll_save_post_translations')) {
		foreach ($translation_groups as $external_id => $group) {
			if (count($group) < 2) {
				continue;
			}
			pll_save_post_translations($group);
			if ($logger) {
				$logger(sprintf('Linked translations for %s', $external_id));
			}
		}
	}

	return $report;
}

if (defined('WP_CLI') && WP_CLI) {
	/**
	 * Import legacy panel JSON into asc_panel posts.
	 */
	class Ascinder_Panels_Import_Command
	{
		/**
		 * Run panel import.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without writing posts.
		 */
		public function run($args, $assoc_args)
		{
			$dry_run = isset($assoc_args['dry-run']);
			$report = ascinder_import_panels_from_json(
				array(
					'dry_run' => $dry_run,
					'log'     => static function ($message) {
						WP_CLI::log($message);
					},
				)
			);

			WP_CLI::log(sprintf('Created: %d | Updated: %d | Skipped: %d | Errors: %d', (int) $report['created'], (int) $report['updated'], (int) $report['skipped'], (int) $report['errors']));
			if ((int) $report['errors'] > 0) {
				WP_CLI::warning('Import completed with errors.');
			}
			WP_CLI::success($dry_run ? 'Dry-run complete.' : 'Import complete.');
		}
	}

	WP_CLI::add_command('ascinder panels import-json', array('Ascinder_Panels_Import_Command', 'run'));
}
