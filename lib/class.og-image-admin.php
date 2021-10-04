<?php

namespace Clearsite\Plugins\OGImage;

use Clearsite\Tools\HTML_Inputs;
use RankMath;

// supported but not required. question is, do we need it? RankMath uses the Featured Image... todo: investigate

class Admin
{
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
			wp_enqueue_script(Plugin::SCRIPT_STYLE_HANDLE, plugins_url('admin/admin.js', __DIR__), ['jquery', 'jquery-ui-slider', 'vanilla-picker'], filemtime(dirname(__DIR__) . '/admin/admin.js'), true);
			wp_localize_script(Plugin::SCRIPT_STYLE_HANDLE, 'bsi_settings', ['preview_url' => get_permalink() . Plugin::BSI_IMAGE_NAME]);

			wp_enqueue_style(Plugin::SCRIPT_STYLE_HANDLE, plugins_url('css/admin.css', __DIR__), '', filemtime(dirname(__DIR__) . '/css/admin.css'), 'all');
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

		add_action('bsi_footer', function() {
			?><p><?php print sprintf('<a href="%s" target="_blank">Branded Social Images</a> is a free plugin by <a href="%s" target="_blank">Clearsite</a>.
				Please let us know what you think of this plugin and what you wish to see in the Pro version. <a
					href="%s">Contact us here</a>.',
				Plugin::PLUGIN_URL_WPORG, Plugin::CLEARSITE_URL_INFO, Plugin::CLEARSITE_URL_CONTACT); ?></p><?php
		});
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
			'font-size' => Plugin::DEF_FONT_SIZE,
			'color' => '#ffffffff', 'line-height' => Plugin::DEF_FONT_SIZE * 1.25,
			'font-file' => '',
			'font-family' => '',
			'font-weight' => 400,
			'font-style' => 'normal',
			'display' => 'inline', // determines background-dimensions block: 100% width??? inline-block: rectangle around all text, inline: behind text only
			'padding' => '10', // background padding
			'background-color' => '#66666666',
			'background-enabled' => 'on',
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
			'size' => get_option(Plugin::OPTION_PREFIX . 'image_logo_size', '100'),
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
		$fields = Plugin::field_list()['admin'];
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

			<?php do_action('bsi_footer'); ?>
		</div>
		<?php
	}

	public static function valid_fonts(): array
	{
		$fonts = glob(self::storage() . '/*.ttf');
		$list = [];
		foreach ($fonts as $font) {
			$base = basename($font, '.ttf');
			$json = preg_replace('/\.ttf$/', '.json', $font);
			$meta = [];
			if (is_file($json)) {
				$meta = json_decode(file_get_contents($json));
			}
			preg_match('/-w([1-9]00)(-italic)?\./', $font, $m);
			$entry = [
				'weight' => !empty($m[1]) ? $m[1] : 400,
				'style' => !empty($m[2]) ? trim($m[2], '-') : 'normal',
				'name' => $meta && !empty($meta->font_name) ? $meta->font_name : self::nice_font_name($base),
				'valid' => true,
				'ttf' => self::storage() . '/' . $base . '.ttf',
			];
			$weights = implode('|', self::font_name_weights());
			if (preg_match("/-({$weights})?(Italic)?$/", $base, $m) && !empty($m[1])) {
				$weight = array_search($m[1], self::font_name_weights());
				if ($weight) {
					$entry['weight'] = $weight;
				}
			}
			if (!empty($m[2]) && $m[2] === 'Italic') {
				$entry['style'] = 'italic';
			}
//			$entry[]

			// display name
			$entry['display_name'] = $entry['name'] . ' - ' . self::weight_to_suffix($entry['weight'], $entry['style'] == 'italic');
			$entry['display_name'] = str_replace('Italic', ' Italic', $entry['display_name']);
			$entry['display_name'] = str_replace('  Italic', ' Italic', $entry['display_name']);
			$list[$base] = $entry;
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
		$weights = implode('|', self::font_name_weights());
		$name = preg_replace("/-({$weights})?(Italic)?$/", '', $name);
		return $name;
	}

	public static function getErrors()
	{
		$errors = get_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', []);
		update_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', []);
		return $errors;
	}

	public static function show_editor($fields)
	{

		$fields['text']['current_value'] = trim($fields['text']['current_value']) ? $fields['text']['current_value'] : self::array_first(Plugin::text_fallback_chain());

		$text_settings = Plugin::getInstance()->text_options;
		$logo_settings = Plugin::getInstance()->logo_options;

		$image = $fields['image']['current_value'];
		if ($image && is_numeric($image)) {
			$image = wp_get_attachment_image($image, Plugin::IMAGE_SIZE_NAME);
			preg_match('/src="(.+)"/U', $image, $m);
			$image = $m[1];
		}

		$logo = $fields['image_logo']['current_value'];
		if ($logo && is_numeric($logo)) {
			$logo = wp_get_attachment_image($logo, 'full');
			preg_match('/width="(.+)"/U', $logo, $width); $width = $width[1];
			preg_match('/height="(.+)"/U', $logo, $height); $height = $height[1];
			preg_match('/src="(.+)"/U', $logo, $m);
			$logo = $m[1];
		}

		?>
		<?php self::render_options($fields, ['disabled']); ?>
		<style>
		#branded-social-images-editor {
			--padding: <?php print Plugin::PADDING; ?>px;
			--text-width: <?php print ceil(Plugin::getInstance()->width * Plugin::TEXT_AREA_WIDTH - 2 * $text_settings['padding']); ?>px;
			--text-height: <?php print ceil(Plugin::getInstance()->height * Plugin::TEXT_AREA_WIDTH - 2 * $text_settings['padding']); ?>px;

			--text-background: <?php print Admin::hex_to_rgba($text_settings['background-color'], true); ?>;
			--text-color: <?php print Admin::hex_to_rgba($text_settings['color'], true); ?>;
			--text-font: <?php print $text_settings['font-file']; ?>;
			--text-shadow-color: <?php print Admin::hex_to_rgba($text_settings['text-shadow-color'], true); ?>;
			--text-shadow-top: <?php print intval($text_settings['text-shadow-top']); ?>px;
			--text-shadow-left: <?php print intval($text_settings['text-shadow-left']); ?>px;
			--font-size: <?php print $text_settings['font-size']; ?>px;
			--text-padding: <?php print $text_settings['padding']; ?>px;
			--line-height: <?php print $text_settings['line-height']; ?>px;

			--logo-scale: <?php print $logo_settings['size']; ?>;
			--logo-width: <?php print $width; ?>;
			--logo-height: <?php print $height; ?>;
		}

		</style><?php

			$editor_class = [];
			$editor_class[] = 'logo_position-' . (!empty($fields['logo_position']) ? $fields['logo_position']['current_value'] : $logo_settings['position']);
			$editor_class[] = 'text_position-' . (!empty($fields['text_position']) ? $fields['text_position']['current_value'] : $text_settings['position']);

			$editor_class = implode(' ', $editor_class);
		?>
		<div id="branded-social-images-editor"
			 class="<?php print $editor_class; ?>"
			 data-font="<?php print $text_settings['font-file']; ?>"
			 data-use-thumbnail="<?php print Plugin::field_list()['admin']['image_use_thumbnail']['current_value']; ?>">
			<div class="grid">
				<div class="area--background-canvas"></div>
				<?php foreach (Plugin::image_fallback_chain() as $kind => $fallback_image) { ?>
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
						<pre contenteditable="true"
							 class="editable"><?php print $fields['text']['current_value']; ?></pre>
						<?php foreach (Plugin::text_fallback_chain() as $type => $text) {
							?>
							<div class="text-alternate type-<?php print $type; ?>"><?php print $text; ?></div><?php
						} ?>
					</div>
				</div>
			</div>
			<div class="settings">
				<div class="area--options">
					<h2>Image/Logo options</h2>
					<div class="inner">
						<?php self::render_options($fields, [
							'image', 'image_use_thumbnail',
							'image_logo', 'logo_position', 'image_logo_size',
						]); ?>
					</div>
				</div>
				<div class="area--settings">
					<h2>Text settings</h2>
					<div class="inner">
						<?php self::render_options($fields, [
							'text', 'text_enabled',
							'color',
							'text_shadow_enabled',
							'text__font', 'text__ttf_upload', 'text_position',
							'text__font_size',
							'background_enabled', 'background_color',
							'text_shadow_color', 'text_shadow_top', 'text_shadow_left',
							'text_stroke_color', 'text_stroke',
							'text__google_download',
						]); ?>
					</div>
				</div>
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
						if ($key === 'text' && !empty($value)) {
							$value = strip_tags($value);
						}
						if ($key === 'text' && self::text_is_identical($value, self::array_first(Plugin::text_fallback_chain()))) {
							$value = '';
						}
						update_post_meta($post_id, "$namespace$key", $value);
					}
				}
			}
		}
	}

	public static function meta_panel()
	{
		$fields = Plugin::field_list()['meta'];
		self::show_editor($fields);
		do_action('bsi_footer');
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

		$tweaks = Plugin::font_rendering_tweaks();
		foreach ($tweaks as $font => &$tweak) {
			$tweak = $tweak['admin'];
			foreach ($tweak as $prop => &$val) {
				$val = "$prop: $val;";
			}
			$tweak = implode("\n", $tweak);
			$tweak = "#branded-social-images-editor[data-font='$font'] .editable-container {\n$tweak}\n";
		}

		print '<style id="branded-social-images-css">' . implode("\n\n", $faces) . "\n\n" . implode("\n\n", $tweaks) .'</style>';
	}

	public static function sanitize_fonts()
	{
		$storage = trailingslashit(self::storage());
		$missed_one = false;
		foreach (Plugin::default_google_fonts() as $font_family) {
			foreach (['400'] as $font_weight) {
				foreach (['normal'/*, 'italic'*/] as $font_style) {
					foreach ([/*'woff', */ 'ttf'] as $extention) {
						$local_filename = self::google_font_filename($font_family, $font_weight, $font_style, $extention);
						if (!is_file($storage . $local_filename)/* && !is_file($storage . $local_filename . '2' / * facking hack * /)*/) {
							self::download_google_font($font_family, $font_weight, $font_style);
							$missed_one = true;
						}
					}
				}
			}
		}
		if ($missed_one) {
			Plugin::font_rendering_tweaks( true );
		}
	}

	public static function google_font_filename($font_family, $font_weight, $font_style, $extention = ''): string
	{
		$italic = $font_style == 'italic' ? 'italic' : '';
		$suffix = self::weight_to_suffix($font_weight, $italic);
		$font_filename = str_replace(' ', '', $font_family) . '-' . $suffix;
		if ($extention) {
			$font_filename .= '.' . $extention;
		}
		return $font_filename;
	}

	private static function weight_to_suffix($weight, $is_italic)
	{
		$weight = intval(round($weight/100)*100);
		$weights = self::font_name_weights();

		if (!array_key_exists($weight, $weights) || (/* Special case; RegularItalic is just called Italic */ 400 == $weight && $is_italic)) {
			$suffix = '';
		}
		else {
			$suffix = $weights[ $weight ];
		}
		if ($is_italic) {
			$suffix .= 'Italic';
		}

		return $suffix;
	}

	private static function font_name_weights()
	{
		return [
			100 => 'Thin',
			200 => 'ExtraLight',
			300 => 'Light',
			400 => 'Regular',
			500 => 'Medium',
			600 => 'SemiBold',
			700 => 'Bold',
			800 => 'ExtraBold',
			900 => 'Black',
		];
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
			$errors = get_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', []);
			$errors[] = $text;
			$errors = array_filter($errors);
			$errors = array_unique($errors);
			update_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', $errors);
		}
		else {
			$errors = get_option(Plugin::DEFAULTS_PREFIX . '_errors', []);
			$errors[$tag] = $text;
			$errors = array_filter($errors);
			update_option(Plugin::DEFAULTS_PREFIX . '_errors', $errors);
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
		if (is_admin() && ($font_id = get_option(Plugin::DEFAULTS_PREFIX . 'text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = Plugin::getInstance();
				update_option(Plugin::DEFAULTS_PREFIX . 'text__ttf_upload', false);
				update_option(Plugin::DEFAULTS_PREFIX . 'text__font', basename($font, '.ttf'));
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
						if ($key === 'text' && !empty($value)) {
							$value = strip_tags($value, '<br>');
						}
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
			'ultra bold' => 900,
		];
	}

	public static function getError($tag = null)
	{
		$errors = get_option(Plugin::DEFAULTS_PREFIX . '_errors', []);

		if ($tag) {
			$return = $errors[$tag];
			unset($errors[$tag]);
			$errors = array_filter($errors);
		}
		else {
			$return = $errors;
			$errors = [];
		}

		update_option(Plugin::DEFAULTS_PREFIX . '_errors', $errors);

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

	private static function array_first(array $array)
	{
		return reset($array);
	}

	private static function text_is_identical($value1, $value2)
	{
		$value1 = trim(str_replace(["\n", "\r"], '', $value1));
		$value2 = trim(str_replace(["\n", "\r"], '', $value2));

		return strip_tags($value1) == strip_tags($value2);
	}
}
