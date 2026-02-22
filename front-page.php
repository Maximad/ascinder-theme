<?php
/**
 * Front page template.
 *
 * @package ASCINDER
 */
get_header();
?>
<section id="panels" class="interactive-home" aria-label="<?php esc_attr_e('ASCINDER experience', 'ascinder'); ?>">
	<div class="experience-shell">
		<div class="experience-hud" role="group" aria-label="<?php esc_attr_e('Panel controls', 'ascinder'); ?>">
			<button type="button" class="hud-btn" data-action="prev" aria-label="<?php esc_attr_e('Previous panel', 'ascinder'); ?>">←</button>
			<button type="button" class="hud-btn" data-action="toggle" aria-label="<?php esc_attr_e('Toggle orientation', 'ascinder'); ?>">⇄</button>
			<button type="button" class="hud-btn" data-action="next" aria-label="<?php esc_attr_e('Next panel', 'ascinder'); ?>">→</button>
		</div>
		<div id="experience-rail" class="experience-rail" tabindex="0" aria-live="polite"></div>
	</div>
</section>
<?php
get_footer();
