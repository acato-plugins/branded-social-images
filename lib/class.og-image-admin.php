<?php

namespace Clearsite\Plugins\OGImage;

use Clearsite\Tools\HTML_Inputs;
use RankMath;

// supported but not required. question is, do we need it? RankMath uses the Featured Image... todo: investigate

class Admin
{
	const OPTION_PREFIX = '_bsi_';
	const DEFAULTS_PREFIX = '_bsi_default_';
	const SCRIPT_STYLE_HANDLE = 'bsi';
	const BSI_IMAGE_NAME = 'social-image.png';
	const ICON = 'clearsite-logo.svg';
	const ADMIN_SLUG = 'branded-social-images';
	const DO_NOT_RENDER = 'do_not_render';

	public $storage = '';

	public function __construct()
	{
		add_filter('wp_check_filetype_and_ext', function ($result, $file, $filename, $mimes, $realmime) {
			if (substr(strtolower($filename), -4, 4) == '.ttf') {
				$result['ext'] = 'ttf';
				$result['type'] = 'font/ttf';
				$result['proper_filename'] = $filename;
			}
			return $result;
		}, 11, 5);

		add_filter('upload_mimes', function ($existing_mimes) {
			$existing_mimes['ttf'] = 'font/ttf';
			return $existing_mimes;
		});

		add_action('admin_head', [static::class, 'maybe_move_font']);
		add_action('admin_head', [static::class, 'add_fontface_definitions']);
		add_action('admin_init', [static::class, 'process_post'], 11);
		add_action('admin_enqueue_scripts', function () {
			wp_register_script('vanilla-picker', plugins_url('admin/vanilla-picker.js', __DIR__), [], filemtime(dirname(__DIR__) . '/admin/vanilla-picker.js'), true);
			wp_enqueue_script(self::SCRIPT_STYLE_HANDLE, plugins_url('admin/admin.js', __DIR__), ['jquery', 'jquery-ui-slider', 'vanilla-picker'], filemtime(dirname(__DIR__) . '/admin/admin.js'), true);
			wp_localize_script(self::SCRIPT_STYLE_HANDLE, 'bsi_settings', ['preview_url' => get_permalink() . self::BSI_IMAGE_NAME]);

			wp_enqueue_style(self::SCRIPT_STYLE_HANDLE, plugins_url('css/admin.css', __DIR__), '', filemtime(dirname(__DIR__) . '/css/admin.css'), 'all');
		});

		add_action('admin_menu', function () {
			add_menu_page('Branded Social Images', 'Branded Social Images', 'edit_posts', self::ADMIN_SLUG, [self::class, 'admin_panel'], self::admin_icon());
		});

		add_action('admin_init', [static::class, 'sanitize_fonts']);

		add_filter('image_size_names_choose', function ($default_sizes) {
			return array_merge($default_sizes, array(
				Plugin::IMAGE_SIZE_NAME => __('The OG-Image recommended size'),
			));
		});

		add_action('save_post', [static::class, 'save_meta_data']);
		add_action('add_meta_boxes', [static::class, 'add_meta_boxes']);
		add_action('admin_notices', [static::class, 'admin_notices']);
	}

	public static function admin_icon(): string
	{
		static $once;
		if (!$once) {
			$once = true;
			add_action('admin_footer', function () {
				?>
				<style>
					.toplevel_page_branded-social-images .wp-menu-image img {
						display: none;
					}

					.toplevel_page_branded-social-images .wp-menu-image svg {
						width: 80%;
						height: 110%;
					}

					.wp-not-current-submenu:not(:hover) .wp-menu-image svg path {
						fill: #eee;
					}
				</style><?php
			});
		}
		if (preg_match('/\.svg$/', static::ICON) && is_file(dirname(__DIR__) . '/img/' . basename('/' . static::ICON))) {
			return '" alt="" />' . file_get_contents(dirname(__DIR__) . '/img/' . basename('/' . static::ICON)) . '<link href="';
		}
		if (preg_match('/\.svg$/', static::ICON) && is_file(dirname(__DIR__) . '/img/' . basename('/' . static::ICON))) {
			return plugins_url('/img/' . basename('/' . static::ICON), __DIR__);
		}
		return static::ICON;
	}

	public static function base_settings(): array
	{
		$defaults = [];
		$defaults['text_options'] = [ // colors are RGBA in hex format
			'enabled' => 'on',
			'left' => null, 'bottom' => null, 'top' => null, 'right' => null,
			'font-size' => '32', 'color' => '#ffffffff', 'line-height' => '40',
			'font-file' => '',
			'font-family' => '',
			'font-weight' => 400,
			'font-style' => 'normal',
			'display' => 'inline', // determines background-dimensions block: 100% width??? inline-block: rectangle around all text, inline: behind text only
			'padding' => '10', // background padding
			'background-color' => '#66666666',
			'text-shadow-color' => '',
			'text-shadow-left' => '2',
			'text-shadow-top' => '-2',
			'text-shadow-enabled' => 'off',
			'text-stroke-color' => '',
			'text-stroke' => '2',
		];
		$defaults['logo_options'] = [
			'enabled' => 'on',
			'position' => 'bottom-right',
			'left' => null, 'bottom' => null, 'top' => null, 'right' => null,
			'size' => get_option(self::OPTION_PREFIX . 'image_logo_size', '20%'),
		];

		// more freemium options to consider;
		/**
		 * stroke on text-shadow
		 * rotation on text
		 * independant rotation of shadow
		 * skew?
		 *
		 */

		return $defaults;
	}

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public static function admin_panel()
	{
		$fields = self::field_list()['admin'];
		?>
		<div class="wrap">
			<h2>Branded Social Images</h2>
			<?php
			$errors = self::getErrors();
			foreach ($errors as $error) {
				?>
				<div class="updated error"><p><?php print $error; ?></p></div><?php
			}
			?>
			<div>
				<form method="POST" action="<?php print esc_attr(add_query_arg('bsi-defaults', '1')); ?>">
					<?php self::show_editor($fields); ?>
					<br/>
					<br/>
					<button class="action button-primary"><?php _e('Save settings'); ?></button>
				</form>
			</div>

			<p>Branded Social Images is a free plugin by Clearsite. We are working on a full-featured Pro version,
				please let us know what you think of this plugin and what you wish to see in the Pro version. <a
					href="mailto:branded-social-images@clearsite.nl">Contact us here</a>.</p>
		</div>
		<?php
	}

	private static function field_list()
	{
		static $once, $support_webp;
		if (!$once) {
			// TODO: ImageMagick?! for now, GD only
			$support_webp = function_exists('imagewebp') || Plugin::maybe_fake_support_webp();
			$once = true;
		}

		$image_comment = '';
		if ($support_webp) {
			$image_comment = 'When using WEBP, you MUST upload your image in 1200x630 pixels';
		}
		if (defined('WPSEO_VERSION')) {
			$image_comment = '<br />Yoast SEO has been detected. If you set-up an OG Image with Yoast and not here, the image selected with Yoast SEO will be used.';
		} // maybe RankMath?
		elseif (class_exists(RankMath::class)) {
			$image_comment = '<br />SEO by Rank Math has been detected. If you set-up an OG Image with Rank Math and not here, the image selected with Rank Math will be used.';
		}
		elseif (!get_option(self::DEFAULTS_PREFIX . 'image')) {
			$image_comment = '<br />No Fallback images have been detected. If you do not set-up an image here, no OG:Image will be available for this ' . get_post_type();
		}

		$options = [
			'admin' => [
				'image' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'image', 'class' => 'no-remove', 'label' => 'The default OG:Image for any page/post/... that has no OG:Image defined.', 'comment' => 'todo: info'],
				'image_use_thumbnail' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use the WordPress Featured image, if selected, before using the default image selected above.', 'default' => 'on'],

				'image_logo' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'label' => 'Your logo', 'comment' => 'For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.'],
				'logo_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid(), 'label' => 'Default logo position', 'default' => 'bottom-right'],
				'image_logo_size' => ['namespace' => self::OPTION_PREFIX, 'type' => 'slider', 'class' => 'single-slider', 'label' => 'Logo-scale', 'comment' => '', 'default' => '20%', 'min' => 5, 'max' => 95, 'step' => 1],

				'text' => ['namespace' => self::DEFAULTS_PREFIX, 'class' => 'hidden editable-target', 'type' => 'textarea', 'label' => 'The default text to overlay if no other text or title can be found.', 'comment' => 'This should be a generic text that is applicable to the entire website.'],
				'text__font' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'select', 'label' => 'Font', 'options' => self::get_font_list(), 'comment' => 'Fonts are stored in your uploads folder. You can manage them there.'],
				'text__ttf_upload' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'file', 'types' => 'font/ttf', 'label' => 'Font upload', 'upload' => 'Upload .ttf file'],
//				'text__google_download' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Google Font Download', 'comment' => 'Enter a Google font name as it is listed on fonts.google.com'],

				'text_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'label' => 'The default text position', 'options' => self::position_grid()],
				'color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Default Text color', 'default' => '#FFFFFFFF'],
				'background_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Default Text background color', 'default' => '#66666666'],

				'text_stroke_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'color', 'attributes' => 'rgba', 'label' => 'Stroke color', 'default' => '#00000000'],
				'text_stroke' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Default stroke width', 'default' => 0],

				'text_shadow_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'color', 'label' => 'Default Text shadow color', '#00000000'],
				'text_shadow_top' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.', 'default' => '-2'],
				'text_shadow_left' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.', 'default' => '2'],
				'text_shadow_enabled' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'checkbox', 'label' => 'Use a text shadow', 'value' => 'on', 'default' => 'off'],
			],
			'meta' => [
				'disabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Check this box to disable OG by Clearsite for this post/page/item'],
				'image' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'label' => 'You can upload/select a specific OG Image here', 'comment' => 'You can use ' . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . ' as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.' . $image_comment],

				'text_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use text on this image?', 'default' => 'yes', 'comment' => 'Uncheck if you do not wish text on this image.'],
				'text' => ['namespace' => self::OPTION_PREFIX, 'type' => 'textarea', 'class' => 'hidden editable-target', 'label' => 'Text on image', 'If you leave this blank, the current page title is used as it appears in the webpage HTML. If you have Yoast SEO or RankMath installed, the title is taken from that.'],
				'color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Text color', 'default' => get_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF')],
				'text_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'label' => 'Text position', 'options' => self::position_grid(), 'default' => get_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-right')],

				'background_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Text background color', 'default' => get_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666')],

				'text_stroke_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Stroke color', 'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000')],
				'text_stroke' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Default stroke width', 'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke', '0')],

				'text_shadow_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'label' => 'Text shadow color', get_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000')],
				'text_shadow_top' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2')],
				'text_shadow_left' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_left', '2')],
				'text_shadow_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use a text shadow', 'value' => 'on', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off')],

				'logo_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use a logo on this image?', 'default' => 'yes', 'comment' => 'Uncheck if you do not wish a logo on this image, or choose a position below'],
				'logo_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'label' => 'Logo position', 'class' => 'position-grid', 'options' => self::position_grid(), 'default' => get_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right')],

				'image_logo' => ['namespace' => self::DO_NOT_RENDER, 'type' => 'image', 'label' => 'Your logo', 'comment' => 'For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.', 'default' => get_option(self::OPTION_PREFIX . 'image_logo')],
			]
		];

		if ('on' !== Plugin::FEATURE_STROKE) {
			unset($options['admin']['text_stroke_color']);
			unset($options['admin']['text_stroke']);
			unset($options['meta']['text_stroke_color']);
			unset($options['meta']['text_stroke']);
		}

		if ('on' !== Plugin::FEATURE_SHADOW) {
			unset($options['admin']['text_shadow_color']);
			unset($options['admin']['text_shadow_top']);
			unset($options['admin']['text_shadow_left']);
			unset($options['meta']['text_shadow_color']);
			unset($options['meta']['text_shadow_top']);
			unset($options['meta']['text_shadow_left']);
		}
		if ('simple' !== Plugin::FEATURE_SHADOW) {
			unset($options['admin']['text_shadow_enabled']);
			unset($options['meta']['text_shadow_enabled']);
		}

		foreach ($options['admin'] as $field => $_) {
			$options['admin'][$field]['current_value'] = get_option($_['namespace'] . $field, !empty($_['default']) ? $_['default'] : null);
		}

		if (get_the_ID()) {
			foreach ($options['meta'] as $field => $_) {
				$options['meta'][$field]['current_value'] = get_post_meta(get_the_ID(), $_['namespace'] . $field, true) ?: (!empty($_['default']) ? $_['default'] : null);
			}
		}

		return $options;
	}

	private static function position_grid(): array
	{
		return [
			'top-left' => 'Top Left',
			'top' => 'Top Center',
			'top-right' => 'Top Right',
			'left' => 'Left Middle',
			'center' => 'Centered',
			'right' => 'Right Middle',
			'bottom-left' => 'Bottom Left',
			'bottom' => 'Bottom Center',
			'bottom-right' => 'Bottom Right',
		];
	}

	private static function get_font_list(): array
	{
		$fonts = self::valid_fonts();
		$options = [];

		foreach ($fonts as $font_base => $_) {
			if (!$_['valid']) {
				continue;
			}
			$font_name = $_['name'];
			$options[$font_base] = $font_name;
		}

		return $options;
	}

	public static function valid_fonts(): array
	{
		$fonts = glob(self::storage() . '/*.ttf');
		$list = [];
		foreach ($fonts as $font) {
			preg_match('/-w([1-9]00)(-italic)?\./', $font, $m);
			$base = basename($font, '.ttf');
			$list[$base] = [
				'weight' => !empty($m[1]) ? $m[1] : 400,
				'style' => !empty($m[2]) ? trim($m[2], '-') : 'normal',
				'name' => self::nice_font_name($base),
				'valid' => false, // assume error
				'ttf' => self::storage() . '/' . $base . '.ttf',
			];
//			foreach (['woff2', 'woff'] as $ext) {
//				if (is_file(self::storage() . '/' . $base . '.' . $ext)) {
//					$list[$base][$ext] = self::storage() . '/' . $base . '.' . $ext;
//				}
//			}
		}

		foreach ($list as &$item) {
			if (!empty($item['ttf'])/* && (!empty($item['woff']) || !empty($item['woff2']))*/) {
				$item['valid'] = true;
			}
		}

		return $list;
	}

	private static function storage($as_url = false)
	{
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/' . Plugin::STORAGE;
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		if (!is_dir($dir)) {
			self::setError('storage', __('Could not create the storage directory in the uploads folder. In a WordPress site the uploads folder should always be writable. Please fix this. This error will disappear once the problem has been corrected.', 'clsogimg'));
		}

		if ($as_url) {
			return str_replace(trailingslashit(ABSPATH), '/', $dir);
		}

		return $dir;
	}

	public static function nice_font_name($font)
	{
		// w400 to normal, w700 to bold etc
		list($name, $_) = explode('-w', $font . '-w400', 2);
		return $name;
	}

	public static function getErrors()
	{
		$errors = get_option(Admin::DEFAULTS_PREFIX . '_admin_errors', []);
		update_option(Admin::DEFAULTS_PREFIX . '_admin_errors', []);
		return $errors;
	}

	public static function show_editor($fields)
	{

		$text_settings = Plugin::getInstance()->text_options;
		$logo_settings = Plugin::getInstance()->logo_options;

		$image = $fields['image']['current_value'];
		if (is_numeric($image)) {
			$image = wp_get_attachment_image($image, Plugin::IMAGE_SIZE_NAME);
			preg_match('/src="(.+)"/U', $image, $m);
			$image = $m[1];
		}

		$logo = $fields['image_logo']['current_value'];
		if (is_numeric($logo)) {
			$logo = wp_get_attachment_image($logo, 'full');
			preg_match('/src="(.+)"/U', $logo, $m);
			$logo = $m[1];
		}

		?>
		<?php self::render_options($fields, ['disabled']); ?>
		<style>
			#branded-social-images-editor {
				--padding: <?php print Plugin::PADDING; ?>px;
				--text-width: <?php print ceil(Plugin::getInstance()->width * .7 - 2 * $text_settings['padding']); ?>px;
				--text-height: <?php print ceil(Plugin::getInstance()->height * .7 - 2 * $text_settings['padding']); ?>px;

				--text-background: none;
				--text-color: <?php print Admin::hex_to_rgba($text_settings['color'], true); ?>;
				--text-font: <?php print $text_settings['font-file']; ?>;
				--text-shadow-color: <?php print Admin::hex_to_rgba($text_settings['text-shadow-color'], true); ?>;
				--text-shadow-top: <?php print intval($text_settings['text-shadow-top']); ?>px;
				--text-shadow-left: <?php print intval($text_settings['text-shadow-left']); ?>px;
				--font-size: <?php print $text_settings['font-size']; ?>px;
				--text-padding: <?php print $text_settings['padding']; ?>px;
				--line-height: <?php print $text_settings['line-height']; ?>px;

				--logo-scale: <?php print $logo_settings['size']; ?>;
			}

		</style>
		<div id="branded-social-images-editor"
			 data-use-thumbnail="<?php print self::field_list()['admin']['image_use_thumbnail']['current_value']; ?>">
			<div class="area--background-canvas"></div>
			<?php foreach (self::image_fallback_chain() as $kind => $fallback_image) { ?>
				<div class="area--background-alternate image-source-<?php print $kind; ?>">
					<div class="background"
						 <?php if ($fallback_image) { ?>style="background-image:url('<?php print esc_attr($fallback_image); ?>')"<?php } ?>>
					</div>
				</div>
			<?php } ?>
			<div class="area--background">
				<div class="background" style="background-image:url('<?php print esc_attr($image); ?>')"></div>
			</div>
			<div class="area--logo">
				<div class="logo" style="background-image:url('<?php print esc_attr($logo); ?>')"></div>
			</div>
			<div class="area--text">
				<div class="editable-container">
					<div contenteditable="true" class="editable"><?php print $fields['text']['current_value']; ?></div>
				</div>
			</div>
			<div class="area--options">
				<?php self::render_options($fields, [
					'image', 'image_use_thumbnail',
					'image_logo', 'logo_position', 'image_logo_size',
					'background_color',
				]); ?>
			</div>
			<div class="area--settings">
				<?php self::render_options($fields, [
					'text', 'text__font', 'text__ttf_upload', 'text_position', 'color',
					'text__google_download',
					'text_shadow_color', 'text_shadow_top', 'text_shadow_left', 'text_shadow_enabled',
					'text_stroke_color', 'text_stroke',
				]); ?>
			</div>
		</div>
		<?php
	}

	public static function render_options($options, $filter = [])
	{
		static $seen = [];
		require_once __DIR__ . '/class.html_inputs.php';
		if (!$filter) {
			$filter = array_keys($options);
		}

		$filter = array_diff($filter, $seen);

		foreach ($filter as $option_name) {
			if (!empty ($options[$option_name])) {
				$seen[] = $option_name;
				self::render_option($option_name, $options[$option_name]);
			}
		}
	}

	private static function render_option($option_name, $option_atts)
	{
		if (!empty($option_atts['namespace']) && $option_atts['namespace'] == self::DO_NOT_RENDER) {
			return;
		}
		print '<span data-name="' . esc_attr($option_name) . '" class="input-wrap name-' . esc_attr($option_name) . ' input-' . $option_atts['type'] . (!empty($option_atts['class']) ? str_replace(' ', ' wrap-', ' ' . $option_atts['class']) : '') . '">';
		$label = '';
		if (!empty($option_atts['label'])) {
			$label = $option_atts['label'];
			unset($option_atts['label']);
		}
		HTML_Inputs::render($option_name, $option_atts, $label);
		print '</span>';
	}

	public static function hex_to_rgba($hex, $asRGBA = false)
	{
		$hex = str_replace('#', '', $hex);
		if (!$hex) {
			$hex = '0000';
		}
		if (strlen($hex) <= 4) {
			$hex = str_split($hex . 'F');
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		$hex = substr($hex . 'FF', 0, 8);

		$int = hexdec($hex);
		$red = ($int >> 24) & 255;
		$green = ($int >> 16) & 255;
		$blue = ($int >> 8) & 255;
		$alpha = floatval($int & 255) / 255;

		return $asRGBA ? sprintf('rgba(%d, %d, %d, %0.1f)', $red, $green, $blue, $alpha) : array(
			'red' => $red,
			'green' => $green,
			'blue' => $blue,
			'alpha' => $alpha,
		);
	}

	private static function image_fallback_chain()
	{
		if (!get_the_ID()) {
			return [];
		}

		// layers are stacked in order, bottom first
		$layers = [];
		// layer 1: the configured default
		$settings = self::field_list()['admin'];
		$layers['settings'] = $settings['image']['current_value'];
		// layer 2, if enabled, the thumbnail/featured
		if ('on' === $settings['image_use_thumbnail']['current_value']) {
			$layers['thumbnail'] = get_post_thumbnail_id(get_the_ID());
		}
		// layer 3, if available, social plugins

		// maybe Yoast SEO?
		if (defined('WPSEO_VERSION')) {
			$layers['yoast'] = get_post_meta(get_the_ID(), '_yoast_wpseo_opengraph-image-id', true);
		}

		// maybe RankMath? // latest rank math uses thumbnail ?????
		if (class_exists(RankMath::class)) {
			$layers['rankmath'] = get_post_meta(get_the_ID(), 'rank_math_facebook_image_id', true);
		}

		foreach ($layers as &$layer) {
			if ($layer) {
				$image = wp_get_attachment_image_src($layer, Plugin::IMAGE_SIZE_NAME);
				$layer = $image[0];
			}
		}

		return $layers;
	}

	public static function add_meta_boxes()
	{
		$post_types = apply_filters('bsi_post_types', ['post', 'page']);
		foreach ($post_types as $post_type) {
			add_meta_box(
				self::ADMIN_SLUG,
				'Branded Social Images',
				[static::class, 'meta_panel'],
				$post_type
			);
		}
	}

	public static function save_meta_data($post_id)
	{
		if (array_key_exists('branded_social_images', $_POST)) {
			foreach ($_POST['branded_social_images'] as $namespace => $values) {
				if (is_array($values)) {
					foreach ($values as $key => $value) {
						update_post_meta($post_id, "$namespace$key", $value);
					}
				}
			}
		}
	}

	public static function meta_panel()
	{
		$fields = self::field_list()['meta'];
		self::show_editor($fields); ?>

		<p>Branded Social Images is a free plugin by Clearsite. We are working on a full-featured Pro version,
			please let us know what you think of this plugin and what you wish to see in the Pro version. <a
				href="mailto:branded-social-images@clearsite.nl">Contact us here</a>.</p>
		<?php
	}

	public static function admin_notices()
	{
		$errors = self::getErrors();
		foreach ($errors as $error) {
			?>
			<div class="updated error"><p><?php print $error; ?></p></div><?php
		}

	}

	public static function add_fontface_definitions()
	{
		$fonts = self::valid_fonts();
		$faces = [];
		$storage = self::storage(true);
		foreach ($fonts as $font_base => $font) {
			if (!$font['valid']) {
				continue;
			}
			$style = $font['style'];
			$weight = $font['weight'];
			$sources = [];
			foreach (['ttf' => 'truetype'/*, 'woff2' => 'woff2', 'woff' => 'woff'*/] as $extention => $format) {
				if (empty($font[$extention])) {
					continue;
				}
				$sources[] = "url('$storage/$font_base.$extention') format('$format')";
			}
			$sources = implode(',', $sources);
			$faces[] = <<< EOCSS
@font-face {
	font-family: $font_base;
	font-style: $style;
	font-weight: $weight;
	src: $sources;
}
EOCSS;
		}

		print '<style id="font-face-definitions-for-branded-social-images">' . implode("\n\n", $faces) . '</style>';
	}

	public static function sanitize_fonts()
	{
		$storage = trailingslashit(self::storage());
		foreach (self::default_google_fonts() as $font_family) {
			foreach (['400'] as $font_weight) {
				foreach (['normal'/*, 'italic'*/] as $font_style) {
					foreach ([/*'woff', */ 'ttf'] as $extention) {
						$local_filename = self::google_font_filename($font_family, $font_weight, $font_style, $extention);
						if (!is_file($storage . $local_filename)/* && !is_file($storage . $local_filename . '2' / * facking hack * /)*/) {
							self::download_google_font($font_family, $font_weight, $font_style);
						}
					}
				}
			}
		}
	}

	public static function default_google_fonts(): array
	{
		return ['Open Sans', 'Roboto', 'Montserrat', 'PT Sans', 'Merriweather', 'Oswald', 'Anton', 'Work Sans', 'Courgette', 'Josefin Sans'];
	}

	public static function google_font_filename($font_family, $font_weight, $font_style, $extention = ''): string
	{
		$italic = $font_style == 'italic' ? 'italic' : '';
		$font_filename = $font_family . '-w' . $font_weight . ($italic ? '-' . $italic : '');
		if ($extention) {
			$font_filename .= '.' . $extention;
		}
		return $font_filename;
	}

	public static function download_google_font($font_family, $font_weight, $font_style)
	{
		$font_filename = self::google_font_filename($font_family, $font_weight, $font_style);
		$font_url = self::google_font_url($font_family, $font_weight, $font_style);
		$font_url = str_replace(' ', '%20', $font_url);

		/** @var $formats array User-Agent => file extention */
		$formats = [' ' => '.ttf'];
		// also get woff2? doesn't seem required as all browsers currently support rendering ttf...
//		$formats['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36'] = '.woff';

		foreach ($formats as $user_agent => $extention) {
			$font_css = wp_remote_retrieve_body(wp_remote_get($font_url, [
				'headers' => [
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', // emulate browser
				],
				'user-agent' => $user_agent,  // emulate browser
				'httpversion' => '1.1',  // emulate browser
				'referer' => site_url(),  // emulate browser
			]));

			if (!$font_css) {
				self::setError('font-family', __('Could not download font from Google Fonts. Please download yourself and upload here.', 'clsogimg'));
			}
			else {
				$font_css_parts = explode('@font-face', $font_css);
				$font_css = '@font-face' . end($font_css_parts);
				// use the last one, it should be latin. todo: verify; if not always latin last, build checks to actually GET latin.

				if (preg_match('@https?://[^)]+' . $extention . '@', $font_css, $n)) {
					$font_ttf = wp_remote_retrieve_body(wp_remote_get($n[0]));
					self::file_put_contents(self::storage() . '/' . $font_filename . $extention, $font_ttf);
				}
			}
		}

		return $font_family;
	}

	public static function google_font_url($font_family, $font_weight, $font_style): string
	{
		$italic = $font_style == 'italic' ? 'italic' : '';
		$font_url = 'http://fonts.googleapis.com/css?family=' . $font_family . ':' . $font_weight . $italic;

		return $font_url;
	}

	public static function setError($tag, $text)
	{
		if ('generic' == $tag) {
			$errors = get_option(Admin::DEFAULTS_PREFIX . '_admin_errors', []);
			$errors[] = $text;
			$errors = array_filter($errors);
			$errors = array_unique($errors);
			update_option(Admin::DEFAULTS_PREFIX . '_admin_errors', $errors);
		}
		else {
			$errors = get_option(Admin::DEFAULTS_PREFIX . '_errors', []);
			$errors[$tag] = $text;
			$errors = array_filter($errors);
			update_option(Admin::DEFAULTS_PREFIX . '_errors', $errors);
		}
	}

	public static function file_put_contents($filename, $content)
	{
		// for security reasons, $filename must be in $this->storage()
		if (substr(trim($filename), 0, strlen(self::storage())) !== self::storage()) {
			return false;
		}
		$dirs = [];
		$dir = $filename; // we will be dirname-ing this

		while (($dir = dirname($dir)) && $dir && $dir !== '.' && $dir !== self::storage() && !is_dir($dir)) {
			array_unshift($dirs, $dir);
		}

		array_map('mkdir', $dirs);

		return file_put_contents($filename, $content);
	}

	public static function maybe_move_font()
	{
		if (is_admin() && ($font_id = get_option(self::DEFAULTS_PREFIX . 'text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = Plugin::getInstance();
				update_option(self::DEFAULTS_PREFIX . 'text__ttf_upload', false);
				update_option(self::DEFAULTS_PREFIX . 'text__font', basename($font));
				rename($font, $instance->storage() . '/' . basename($font));
				wp_delete_post($font_id);
			}
		}
	}

	public static function process_post()
	{
		if (is_admin() && !empty($_GET['page']) && $_GET['page'] === self::ADMIN_SLUG && !empty($_POST)) {
			// bsi-defaults
			// handle $_POST
			foreach ($_POST['branded_social_images'] as $namespace => $values) {
				if (is_array($values)) {
					foreach ($values as $key => $value) {
						update_option("$namespace$key", $value);
					}
				}
			}
			wp_redirect(remove_query_arg('bsi-defaults', add_query_arg('updated', 1)));
			exit;
		}
	}

	public static function font_weights(): array
	{
		return [
			'thin' => 100,
			'extra light' => 200,
			'ultra light' => 200,
			'light' => 300,
			'normal' => 400,
			'book' => 400,
			'regular' => 400,
			'medium' => 500,
			'semi bold' => 600,
			'demi bold' => 600,
			'bold' => 700,
			'extra bold' => 800,
			'ultra bold' => 800,
		];
	}

	public static function getError($tag = null)
	{
		$errors = get_option(Admin::DEFAULTS_PREFIX . '_errors', []);

		if ($tag) {
			$return = $errors[$tag];
			unset($errors[$tag]);
			$errors = array_filter($errors);
		}
		else {
			$return = $errors;
			$errors = [];
		}

		update_option(Admin::DEFAULTS_PREFIX . '_errors', $errors);

		return $return;
	}

	private static function hex_to_hex_opacity($hex_color): array
	{
		if (substr($hex_color, 0, 1) !== '#') {
			$hex_color = '#ffffffff';
		}
		$hex_values = str_split(substr($hex_color . 'FF', 1, 8), 6);

		return [$hex_values[0], intval((hexdec($hex_values[1]) + 1) / 256 * 100)];
	}
}
