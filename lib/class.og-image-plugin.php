<?php

namespace Clearsite\Plugins\OGImage;

use RankMath;

class Plugin
{
	const BSI_IMAGE_NAME = 'social-image.png';
	const FEATURE_STROKE = 'off';
	const FEATURE_SHADOW = 'simple';
	const FEATURE_META_TEXT_OPTIONS = 'off';
	const FEATURE_META_LOGO_OPTIONS = 'off';
	const TEXT_AREA_WIDTH = .95;
	const PADDING = 40;
	const AA = 2;
	const STORAGE = 'bsi-uploads';
	const IMAGE_SIZE_NAME = 'og-image';
	const DEFAULTS_PREFIX = '_bsi_default_';
	const OPTION_PREFIX = '_bsi_';
	const SCRIPT_STYLE_HANDLE = 'bsi';
	const MIN_LOGO_SCALE = 10;
	const MAX_LOGO_SCALE = 200;

	public $width = 1200;
	public $height = 630;
	public $logo_options;
	public $text_options;
	public $preview;
	public $page_already_has_og_image = false;
	public $og_image_available;

	public function __construct()
	{
		// todo: actions that add a meta box to a post for post-specific settings, like text
		// todo: actions that add a settings panel for configuring the plugin
		add_filter('query_vars', function ($vars) {
			$vars[] = 'clsogimg';
			$vars[] = '_preview';
			return $vars;
		});

		// phase 1; add the endpoints
		add_action('wp', function () {
			$this->setup_defaults();
			$image_layers = self::image_fallback_chain(true);
			$this->og_image_available = !!array_filter($image_layers);

			$this->text_options['position'] = get_option(self::DEFAULTS_PREFIX . 'text_position', 'top-left');
			$this->logo_options['position'] = get_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right');
			$id = get_the_ID();
			if ($id) {
				$overrule_text_position = get_post_meta($id, self::OPTION_PREFIX . 'text_position', true);
				if ($overrule_text_position) {
					$this->text_options['position'] = $overrule_text_position;
				}

				$overrule_logo_enabled = get_post_meta($id, self::OPTION_PREFIX . 'logo_enabled', true);
				if (!$overrule_logo_enabled || 'yes' === $overrule_logo_enabled) {
					$this->logo_options['enabled'] = $overrule_logo_enabled;
				}

				$overrule_logo_position = get_post_meta($id, self::OPTION_PREFIX . 'logo_position', true);
				if ($overrule_logo_position) {
					$this->logo_options['position'] = $overrule_logo_position;
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'color', true);
				if ($overrule_color) {
					$this->text_options['color'] = $overrule_color;
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'background_color', true);
				if ($overrule_color) {
					$this->text_options['background-color'] = $overrule_color;
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'text_stroke_color', true);
				if ($overrule_color) {
					$this->text_options['text-stroke-color'] = $overrule_color;
				}

				$overrule = get_post_meta($id, self::OPTION_PREFIX . 'text_stroke', true);
				if ($overrule !== '') {
					$this->text_options['text-stroke'] = intval($overrule);
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_color', true);
				if ($overrule_color) {
					$this->text_options['text-shadow-color'] = $overrule_color;
				}

				$overrule_left = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_left', true);
				if ($overrule_left !== '') {
					$this->text_options['text-shadow-left'] = $overrule_left;
				}

				$overrule_top = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_top', true);
				if ($overrule_top !== '') {
					$this->text_options['text-shadow-top'] = $overrule_top;
				}

				$overrule_tsenabled = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_enabled', true);
				if ($overrule_tsenabled === 'on') {
					$this->text_options['text-shadow-color'] = '#555555DD';
					$this->text_options['text-shadow-top'] = 2;
					$this->text_options['text-shadow-left'] = -2;
				}
			}

			$this->expand_text_options();
			$this->expand_logo_options();
		});

		add_action('init', function () {
			add_rewrite_endpoint(self::BSI_IMAGE_NAME, EP_PERMALINK | EP_ROOT | EP_PAGES, 'clsogimg');
			add_image_size(Plugin::IMAGE_SIZE_NAME, $this->width, $this->height, true);
			add_image_size(Plugin::IMAGE_SIZE_NAME . '@' . Plugin::AA . 'x', $this->width * Plugin::AA, $this->height * Plugin::AA, true);
		});

		add_action('admin_init', function () {
			$font_file = get_option(self::DEFAULTS_PREFIX . 'text__font');
			if (preg_match('/google:(.+)/', $font_file, $m)) {
				$defaults = $this->default_options();
				$this->text_options = $defaults['text_options'];
				$this->text_options['font-file'] = $font_file;
				$this->text_options['font-family'] = $font_file;
				$this->expand_text_options();
				if ($this->text_options['font-file'] && is_file($this->text_options['font-file']) && $this->text_options['font-file'] !== $font_file) { // PROCESSED!
					update_option(self::DEFAULTS_PREFIX . 'text__font', basename($this->text_options['font-file']));
					wp_redirect(remove_query_arg('asdadasd'));
					exit;
				}
			}
		});

		// phase 2; alter the endpoints to be data-less (like /feed)
		add_filter('rewrite_rules_array', function ($rules) {
			$new_rules = [];
			foreach ($rules as $source => $target) {
				if (preg_match('/' . strtr(self::BSI_IMAGE_NAME, ['.' => '\\.', '-' => '\\-']) . '/', $source)) {
					$source = explode(self::BSI_IMAGE_NAME, $source);
					$source = $source[0] . self::BSI_IMAGE_NAME . '/?$';

					$target = explode('clsogimg=', $target);
					$target = $target[0] . 'clsogimg=1';
				}
				$new_rules[$source] = $target;
			}

			return $new_rules;
		});

		add_action('template_redirect', function () {
			if (get_query_var('clsogimg')) {
				require_once __DIR__ . '/class.og-image.php';
				$og_image = new Image($this);
				if (current_user_can('edit_posts') && isset($_GET['_preview'])) {
					$this->preview = true;

					$this->text_options = shortcode_atts($this->text_options, $_GET); // picks appropriate items from _GET and uses the current settings as default
					$this->logo_options = shortcode_atts($this->logo_options, $_GET); // picks appropriate items from _GET and uses the current settings as default

					$this->validate_text_options();
					$this->validate_logo_options();

					if (isset($_GET['logo_position'])) {
						$this->logo_options['position'] = $_GET['logo_position'];
						unset($this->logo_options['top'], $this->logo_options['left'], $this->logo_options['bottom'], $this->logo_options['right']);
					}
					if (!empty($_GET['logo_enabled'])) {
						$this->logo_options['enabled'] = $_GET['logo_enabled'] === 'yes';
					}
					if (isset($_GET['text_position'])) {
						$this->text_options['position'] = $_GET['text_position'];
						unset($this->text_options['top'], $this->text_options['left'], $this->text_options['bottom'], $this->text_options['right']);
					}
					if (!empty($_GET['text_enabled'])) {
						$this->text_options['enabled'] = $_GET['text_enabled'] === 'yes';
					}
					if (!empty($_GET['color'])) {
						$this->text_options['color'] = '#' . ltrim(urldecode($_GET['color']), '#');
					}
					if (!empty($_GET['background_color'])) {
						$this->text_options['background-color'] = '#' . ltrim(urldecode($_GET['background_color']), '#');
					}
					if (isset($_GET['text_stroke']) && '' !== $_GET['text_stroke']) {
						$this->text_options['text-stroke'] = intval($_GET['text_stroke']);
					}
					if (!empty($_GET['text_stroke_color'])) {
						$this->text_options['text-stroke-color'] = '#' . ltrim(urldecode($_GET['text_stroke_color']), '#');
					}
					if (!empty($_GET['text_shadow_color'])) {
						$this->text_options['text-shadow-color'] = '#' . ltrim(urldecode($_GET['text_shadow_color']), '#');
					}
					if (isset($_GET['text_shadow_left']) && '' !== $_GET['text_shadow_left']) {
						$this->text_options['text-shadow-left'] = $_GET['text_shadow_left'];
					}
					if (isset($_GET['text_shadow_top']) && '' !== $_GET['text_shadow_top']) {
						$this->text_options['text-shadow-top'] = $_GET['text_shadow_top'];
					}

					if (isset($_GET['text_shadow_enabled']) && 'on' === $_GET['text_shadow_enabled']) {
						$this->text_options['text-shadow-color'] = '#555555DD';
						$this->text_options['text-shadow-top'] = -2;
						$this->text_options['text-shadow-left'] = 2;
					}
					if (!empty($_GET['text'])) {
						add_filter('bsi_text', function ($text) {
							return stripslashes_deep(urldecode($_GET['text']));
						}, PHP_INT_MAX);
					}
					if (!empty($_GET['image'])) {
						$id = intval($_GET['image']);
						if ($id && 'attachment' === get_post_type($id) && wp_get_attachment_image($id)) {
							$og_image->image_id = $id;
						}
					}
					$this->expand_text_options($fast = true);
					$this->expand_logo_options();
				}

				$og_image->serve();

				exit;
			}
		});

		add_action('wp', function () {
			$id = get_the_ID();
			if (!is_admin() && $id) {
				$killswitch = get_post_meta($id, self::OPTION_PREFIX . 'disabled', true);
				$go = true;
				if ('on' === $killswitch) { $go = false; }
				if (!Plugin::getInstance()->og_image_available) { $go = false; }
				if ($go) {
					// overrule RankMath
					add_filter('rank_math/opengraph/facebook/image', [static::class, 'overrule_og_image'], PHP_INT_MAX);
					add_filter('rank_math/opengraph/facebook/image_secure_url', [static::class, 'overrule_og_image'], PHP_INT_MAX);
					add_filter('rank_math/opengraph/twitter/twitter_image', [static::class, 'overrule_og_image'], PHP_INT_MAX);
					add_filter('rank_math/opengraph/facebook/og_image_type', function () {
						return 'image/png';
					}, PHP_INT_MAX);

					// overrule Yoast SEO
					add_filter('wpseo_opengraph_image', [static::class, 'overrule_og_image'], PHP_INT_MAX);
					add_filter('wpseo_twitter_image', [static::class, 'overrule_og_image'], PHP_INT_MAX);

					// if an overrule did not take, we need to define our own.
					add_action('wp_head', [static::class, 'late_head'], PHP_INT_MAX);
				}
			}
		}, PHP_INT_MAX);

		add_action('admin_bar_menu', [static::class, 'admin_bar'], 100);

		add_action('admin_init', function () {
			$this->setup_defaults();
		});
	}

	public function setup_defaults()
	{
		// thi sis currently THE size for OG images.
		$defaults = $this->default_options();
		$this->logo_options = $defaults['logo_options'];

		// text options
		$this->text_options = $defaults['text_options'];
//			var_dump( get_attached_file(get_option(Admin::DEFAULTS_PREFIX . 'text__ttf_upload')));exit;
		$font_file = get_option(self::DEFAULTS_PREFIX . 'text__font');

		$this->text_options['font-file'] = $font_file;

		$this->text_options['position'] = get_option(self::DEFAULTS_PREFIX . 'text_position');
		$this->text_options['color'] = get_option(self::DEFAULTS_PREFIX . 'color');
		$this->text_options['background-color'] = get_option(self::DEFAULTS_PREFIX . 'background_color');
		$this->text_options['text-stroke'] = get_option(self::DEFAULTS_PREFIX . 'text_stroke');
		$this->text_options['text-stroke-color'] = get_option(self::DEFAULTS_PREFIX . 'text_stroke_color');
		if ('on' === Plugin::FEATURE_SHADOW) {
			$this->text_options['text-shadow-color'] = get_option(self::DEFAULTS_PREFIX . 'text_shadow_color');
			$this->text_options['text-shadow-left'] = get_option(self::DEFAULTS_PREFIX . 'text_shadow_left');
			$this->text_options['text-shadow-top'] = get_option(self::DEFAULTS_PREFIX . 'text_shadow_top');
		}
		if ('simple' === Plugin::FEATURE_SHADOW) {
			$enabled = get_option(self::DEFAULTS_PREFIX . 'text_shadow_enabled', 'off');
			$enabled = 'off' === $enabled ? false : $enabled;
			$this->text_options['text-shadow-color'] = $enabled ? '#555555DD' : '#00000000';
			$this->text_options['text-shadow-left'] = -2;
			$this->text_options['text-shadow-top'] = 2;
		}
		$this->validate_text_options();
		$this->validate_logo_options();
	}

	public function default_options(): array
	{
		return Admin::base_settings();
	}

	public function validate_text_options()
	{
		$all_possible_options = $this->default_options();
		$all_possible_options = $all_possible_options['text_options'];
		$all_possible_options['position'] = 'left';

		$this->text_options = shortcode_atts($all_possible_options, $this->text_options);

		// colors
		$colors = ['background-color', 'color', 'text-shadow-color', 'text-stroke-color'];
		foreach ($colors as $_color) {
			$color = strtolower($this->text_options[$_color]);

			// single "digit" colors
			if (preg_match('/#[0-9a-f]{3,4}$/', trim($color), $m)) {
				$color .= 'f'; // make sure an alpha value is present
				$color = '#' . substr($color, 1, 1) . substr($color, 1, 1) . substr($color, 2, 1) . substr($color, 2, 1)
					. substr($color, 3, 1) . substr($color, 3, 1) . substr($color, 4, 1) . substr($color, 4, 1);
			}

			// not a valid hex code
			if (!preg_match('/#[0-9a-f]{6,8}$/', trim($color), $m) || preg_match('/#[0-9a-f]{7}$/', trim($color), $m)) {
				$color = '';
			}
			$this->text_options[$_color] = $color;
		}
		$this->text_options['text'] = get_option(self::DEFAULTS_PREFIX . 'text');
	}

	public function validate_logo_options()
	{
		$all_possible_options = $this->default_options();
		$all_possible_options = $all_possible_options['logo_options'];
		$this->logo_options = shortcode_atts($all_possible_options, $this->logo_options);
	}

	public function expand_text_options($fast = false)
	{
		if (empty($this->text_options['position'])) {
			$this->text_options['position'] = 'left';
		}
		switch ($this->text_options['position']) {
			case 'top-left':
			case 'top':
			case 'top-right':
				$this->text_options['top'] = self::PADDING;
				break;
			case 'bottom-left':
			case 'bottom':
			case 'bottom-right':
				$this->text_options['bottom'] = self::PADDING;
				break;
			case 'left':
			case 'center':
			case 'right':
				$this->text_options['top'] = self::PADDING;
				$this->text_options['bottom'] = self::PADDING;
				break;
		}
		switch ($this->text_options['position']) {
			case 'top-left':
			case 'bottom-left':
			case 'left':
				$this->text_options['left'] = self::PADDING;
				break;
			case 'top-right':
			case 'bottom-right':
			case 'right':
				$this->text_options['right'] = self::PADDING;
				break;
			case 'top':
			case 'center':
			case 'bottom':
				$this->text_options['left'] = self::PADDING;
				$this->text_options['right'] = self::PADDING;
				break;
		}

		if (!$fast) {
			$this->text_options['font-weight'] = $this->evaluate_font_weight($this->text_options['font-weight'], 400);
			$this->text_options['font-style'] = $this->evaluate_font_style($this->text_options['font-style'], 'normal');

			if (!$this->text_options['font-file']) {
				$this->text_options['font-file'] = $this->font_filename($this->text_options['font-family'], $this->text_options['font-weight'], $this->text_options['font-style']);
			}
			if ('.' === dirname($this->text_options['font-file'])) { // just a name
				$this->text_options['font-file'] = self::storage() . '/' . $this->text_options['font-file'];
				if (!is_file($this->text_options['font-file']) && is_file($this->text_options['font-file'] . '.ttf')) {
					$this->text_options['font-file'] .= '.ttf';
				}
				// revert back to just a filename for backward compatibility
				$this->text_options['font-file'] = basename($this->text_options['font-file']);
			}

			// we need a TTF
			if (!is_file($this->storage() . '/' . $this->text_options['font-file']) || substr($this->text_options['font-file'], -4) !== '.ttf') {
				$this->text_options['font-file'] = $this->download_font($this->text_options['font-family'], $this->text_options['font-weight'], $this->text_options['font-style']);
			}
			if (is_file($this->storage() . '/' . $this->text_options['font-file'])) {
				$this->text_options['font-file'] = $this->storage() . '/' . $this->text_options['font-file'];
			}
		}

		// text positioning
		$top = &$this->text_options['top'];
		$right = &$this->text_options['right'];
		$bottom = &$this->text_options['bottom'];
		$left = &$this->text_options['left'];

		$top = empty($top) || 'null' === $top ? null : $top;
		$right = empty($right) || 'null' === $right ? null : $right;
		$bottom = empty($bottom) || 'null' === $bottom ? null : $bottom;
		$left = empty($left) || 'null' === $left ? null : $left;

		$this->evaluate_vertical($top, $bottom);
		$this->evaluate_horizontal($left, $right);

		if (null !== $top && null !== $bottom) {
			$valign = 'center';
		}
		elseif (null !== $top) {
			$valign = 'top';
		}
		else {
			$valign = 'bottom';
		}
		if (null !== $left && null !== $right) {
			$halign = 'center';
		}
		elseif (null !== $left) {
			$halign = 'left';
		}
		else {
			$halign = 'right';
		}
		$this->text_options['valign'] = $valign;
		$this->text_options['halign'] = $halign;

		$shadow_type = 'open';
		foreach (['left', 'top'] as $dir) {
			if (preg_match('/[0-9]+S/', $this->text_options['text-shadow-' . $dir])) {
				$shadow_type = 'solid';
			}
			if (preg_match('/[0-9]+G/', $this->text_options['text-shadow-' . $dir])) {
				$shadow_type = 'gradient';
			}
		}
		$this->text_options['text-shadow-type'] = $shadow_type;
	}

	public function evaluate_font_weight($weight, $default = 400)
	{
		$translate = Admin::font_weights();

		if (!intval($weight)) {
			if (isset($translate[strtolower($weight)])) {
				$weight = $translate[strtolower($weight)];
			}
			else {
				$weight = $default;
			}
		}
		$weight = floor($weight / 100) * 100;
		if (!$weight) {
			$weight = $default;
		}
		if ($weight > 800) {
			$weight = 800;
		}

		return $weight;
	}

	public function evaluate_font_style($style, $default = 'normal')
	{
		$allowed = ['normal', 'italic'];
		if (!in_array($style, $allowed)) {
			return $default;
		}
		return $style;
	}

	public function font_filename($font_family, $font_weight, $font_style)
	{
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';
			$font_filename = $m[1] /* fontname */ . '-w' . $font_weight . ($italic ? '-' . $italic : '') . '.ttf';
			return $font_filename;
		}

		// don't know what to do with any other
		return false;
	}

	public function storage()
	{
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/' . Plugin::STORAGE;
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		self::setError('storage', null);
		if (!is_dir($dir)) {
			self::setError('storage', __('Could not create the storage directory in the uploads folder. In a WordPress site the uploads folder should always be writable. Please fix this. This error will disappear once the problem has been corrected.', 'clsogimg'));
		}
		return $dir;
	}

	public static function setError($tag, $text)
	{
		$errors = get_option(self::OPTION_PREFIX . 'image__errors', []);
		$errors[$tag] = $text;
		$errors = array_filter($errors);
		update_option(self::OPTION_PREFIX . 'image__og_logo_errors', $errors);
	}

	public function download_font($font_family, $font_weight, $font_style)
	{
		self::setError('font-family', null);
		$font_filename = $this->font_filename($font_family, $font_weight, $font_style);
		if (!$font_filename) {
			self::setError('font-family', __('Don\'t know where to get this font. Sorry.', 'clsogimg'));
			return false;
		}
		if (is_file($this->storage() . '/' . $font_filename)) {
			return $font_filename;
		}
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';
			$font_css = wp_remote_retrieve_body(wp_remote_get('http://fonts.googleapis.com/css?family=' . urlencode($m[1]) . ':' . $font_weight . $italic, ['useragent' => ' ']));

			if (!$font_css) {
				self::setError('font-family', __('Could not download font from Google Fonts. Please download yourself and upload here.', 'clsogimg'));
				return false;
			}
			// grab any url
			self::setError('font-family', null);
			if (preg_match('@https?://[^)]+ttf@', $font_css, $n)) {
				$font_ttf = wp_remote_retrieve_body(wp_remote_get($n[0]));
				$this->file_put_contents($this->storage() . '/' . $font_filename, $font_ttf);
				return $font_filename;
			}
			else {
				self::setError('font-family', __('This Google Fonts does not offer a TTF file. Sorry, cannot continue at this time.', 'clsogimg'));
				return false;
			}
		}

		// don't know what to do with any other
		return $font_family;
	}

	public function file_put_contents($filename, $content)
	{
		// for security reasons, $filename must be in $this->storage()
		if (substr(trim($filename), 0, strlen($this->storage())) !== $this->storage()) {
			return false;
		}
		$dirs = [];
		$dir = $filename; // we will be dirname-ing this

		while (($dir = dirname($dir)) && $dir && $dir !== '.' && $dir !== $this->storage() && !is_dir($dir)) {
			array_unshift($dirs, $dir);
		}

		array_map('mkdir', $dirs);

		return file_put_contents($filename, $content);
	}

	private function evaluate_vertical(&$top, &$bottom)
	{
		if (substr($top, -1) == '%') {
			$top = intval(floor(intval($top) / 100 * $this->height));
		}
		if (substr($bottom, -1) == '%') {
			$bottom = intval(ceil(intval($bottom) / 100 * $this->height));
		}
	}

	private function evaluate_horizontal(&$left, &$right)
	{
		if (substr($left, -1) == '%') {
			$left = intval(floor(intval($left) / 100 * $this->width));
		}
		if (substr($right, -1) == '%') {
			$right = intval(ceil(intval($right) / 100 * $this->width));
		}
	}

	public function expand_logo_options()
	{
		if (empty($this->logo_options['enabled']) && false !== $this->logo_options['enabled']) {
			$this->logo_options['enabled'] = true;
		}

		switch ($this->logo_options['position']) {
			case 'top-left':
			case 'top':
			case 'top-right':
				$this->logo_options['top'] = self::PADDING;
				break;
			case 'bottom-left':
			case 'bottom':
			case 'bottom-right':
				$this->logo_options['bottom'] = self::PADDING;
				break;
			case 'left':
			case 'center':
			case 'right':
				$this->logo_options['top'] = self::PADDING;
				$this->logo_options['bottom'] = self::PADDING;
				break;
		}
		switch ($this->logo_options['position']) {
			case 'top-left':
			case 'bottom-left':
			case 'left':
				$this->logo_options['left'] = self::PADDING;
				break;
			case 'top-right':
			case 'bottom-right':
			case 'right':
				$this->logo_options['right'] = self::PADDING;
				break;
			case 'top':
			case 'center':
			case 'bottom':
				$this->logo_options['left'] = self::PADDING;
				$this->logo_options['right'] = self::PADDING;
				break;
		}

		$this->logo_options['file'] = get_option(self::OPTION_PREFIX . 'image_logo');
		if (is_numeric($this->logo_options['file'])) {
			$this->logo_options['file'] = get_attached_file($this->logo_options['file']);
		}
		list($sw, $sh) = is_file($this->logo_options['file']) ? getimagesize($this->logo_options['file']) : [0, 0];
		if ($sw && $sh) {
			$sa = $sw / $sh;
			$this->logo_options['source_width'] = $sw;
			$this->logo_options['source_height'] = $sh;
			$this->logo_options['source_aspectratio'] = $sa;
		}
		else {
			// not an image
			$this->logo_options['file'] = false;
			$this->logo_options['error'] = 'Not an image';
			$this->logo_options['enabled'] = false;
			return;
		}

		// logo positioning
		$top = &$this->logo_options['top'];
		$right = &$this->logo_options['right'];
		$bottom = &$this->logo_options['bottom'];
		$left = &$this->logo_options['left'];

		$top = empty($top) || 'null' === $top ? null : $top;
		$right = empty($right) || 'null' === $right ? null : $right;
		$bottom = empty($bottom) || 'null' === $bottom ? null : $bottom;
		$left = empty($left) || 'null' === $left ? null : $left;

		$this->evaluate_vertical($top, $bottom);
		$this->evaluate_horizontal($left, $right);

		if (null !== $top && null !== $bottom) {
			$valign = 'center';
		}
		elseif (null !== $top) {
			$valign = 'top';
		}
		else {
			$valign = 'bottom';
		}
		if (null !== $left && null !== $right) {
			$halign = 'center';
		}
		elseif (null !== $left) {
			$halign = 'left';
		}
		else {
			$halign = 'right';
		}
		$this->logo_options['valign'] = $valign;
		$this->logo_options['halign'] = $halign;

		// size w and h are bounding box!
		$this->logo_options['size'] = intval($this->logo_options['size']);
		$this->logo_options['size'] = min(Plugin::MAX_LOGO_SCALE, intval($this->logo_options['size']));
		$this->logo_options['size'] = max(Plugin::MIN_LOGO_SCALE, intval($this->logo_options['size']));
		$this->logo_options['w'] = $this->logo_options['size'] / 100 * $sw;
		$this->logo_options['h'] = $this->logo_options['size'] / 100 * $sh;
		$this->logo_options['size'] .= '%';

		// resolve aspect issues
		// -> this makes bounding box actual image size
		$scale = min($this->logo_options['w'] / $sw, $this->logo_options['h'] / $sh);
		$this->logo_options['w'] = $sw * $scale;
		$this->logo_options['h'] = $sh * $scale;
	}

	public static function late_head()
	{
		if (!self::getInstance()->og_image_available) {
			return false;
		}
		if (!self::getInstance()->page_already_has_og_image) {
			?>
			<meta property="og:image" content="<?php print self::overrule_og_image(); ?>"><?php
		}
	}

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public static function overrule_og_image($old = null): string
	{
		self::getInstance()->page_already_has_og_image = true;
		return trailingslashit(remove_query_arg(array_keys(!empty($_GET) ? $_GET : ['asd' => 1]))) . self::BSI_IMAGE_NAME . '/'; // yes, slash, WP will add it with a redirect anyway
	}

	public static function init()
	{
		$instance = self::getInstance();
		if (is_admin()) {
			$admin = Admin::getInstance();
			$admin->storage = $instance->storage();
		}
	}

	/**
	 * @param $source
	 * @return mixed|string
	 * @uses exec to execute system command. this might not be supported.
	 * @see  file php.ini. disable_functions = "show_source,system, shell_exec,exec" <- remove exec
	 */
	public static function convert_webp_to_png($source)
	{
		$support = self::maybe_fake_support_webp(); // just in case
		$target = false;
		if ($support) {
			$bin = dirname(__DIR__) . '/bin';
			$target = "{$source}.temp.png";
			$command = "{$bin}/dwebp \"{$source}\" -o \"{$target}\"";
			ob_start();
			try {
				print $command;
				exec($command);
			} catch (\Exception $e) {

			}
			$log = ob_get_clean();
		}

		if (!$target || !file_exists($target)) {
			$target = $source;
		}

		return $target;
	}

	/**
	 * @return bool
	 * @uses exec to execute system command. this might not be supported.
	 * @see  file php.ini. disable_functions = "show_source,system, shell_exec,exec" <- remove exec
	 */
	public static function maybe_fake_support_webp(): bool
	{
		$support = false;

		$bin = dirname(__DIR__) . '/bin';
		// not downloaded yet
		if (!file_exists("$bin/dwebp")) {
			// can we fake support?
			ob_start();
			try {
				exec($bin . '/download.sh');
			} catch (\Exception $e) {

			}
			ob_end_clean();
		}
		if (!file_exists("$bin/dwebp")) {
			// could not download
		}
		else {
			// downloaded but did not run conversion tool succesfully
			if (file_exists($bin . '/can-execute-binaries-from-php.success')) {
				$support = true;
			}
		}

		return $support;
	}

	public static function font_rendering_tweaks_for($font, $section)
	{
		$tweaks = self::font_rendering_tweaks();
		$font = basename($font, '.ttf');
		if (!empty($tweaks[$font]) && !empty($tweaks[$font][$section])) {
			return $tweaks[$font][$section];
		}
		return false;
	}

	public static function font_rendering_tweaks($write_json = false): array
	{
		$tweaks = [
			/** letter-spacing: px, line-height: factor */
			'Anton-w400' => ['admin' => ['letter-spacing' => '-0.32px'], 'gd' => ['line-height' => 1]],
			'Courgette-w400' => ['admin' => ['letter-spacing' => '-0.32px'], 'gd' => ['line-height' => .86]],
			'Josefin Sans-w400' => ['admin' => ['letter-spacing' => '-0.4px'], 'gd' => ['line-height' => .96]],
			'Merriweather-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .86]],
			'Open Sans-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .95, 'text-area-width' => '.67']],
			'Oswald-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .92, 'text-area-width' => '.67']],
			'PT Sans-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => 1.03]],
			'Roboto-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .97]],
			'Work Sans-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => 1]],
			'Akaya Kanadaka-w400' => ['admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .98]],
		];

		$json_files = glob(self::getInstance()->storage() . '/*.json');
		foreach ($json_files as $file) {
			$font = basename($file, '.json');
			if (empty($tweaks[$font])) {
				$tweaks[$font] = [];
			}
			$tweaks[$font] = array_merge($tweaks[$font], json_decode(file_get_contents($file), true));
		}

		if ($write_json) {
			foreach ($tweaks as $font => $data) {
				self::getInstance()->file_put_contents(self::getInstance()->storage() . '/' . $font . '.json', json_encode($data, JSON_PRETTY_PRINT));
			}
		}

		return $tweaks;
	}

	public static function position_grid(): array
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

	public static function field_list()
	{
		static $once, $support_webp;
		if (!$once) {
			// TODO: ImageMagick?! for now, GD only
			$support_webp = function_exists('imagewebp') || Plugin::maybe_fake_support_webp();
			$once = true;
		}

		$image_comment = '';
		if ($support_webp) {
			$image_comment = '<br />When using WEBP, you MUST upload your image in 1200x630 or 2400x1260 pixels';
		}
		if (defined('WPSEO_VERSION')) {
			$image_comment .= '<br />Yoast SEO has been detected. If you set-up an OG Image with Yoast and not here, the image selected with Yoast SEO will be used.';
		} // maybe RankMath?
		elseif (class_exists(RankMath::class)) {
			$image_comment .= '<br />SEO by Rank Math has been detected. If you set-up an OG Image with Rank Math and not here, the image selected with Rank Math will be used.';
		}
		elseif (!get_option(self::DEFAULTS_PREFIX . 'image')) {
			$image_comment .= '<br />No Fallback images have been detected. If you do not set-up an image here, no OG:Image will be available for this ' . get_post_type();
		}

		$options = [
			'admin' => [
				'image' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'image', 'types' => 'image/png,image/jpeg,image/webp', 'class' => '-no-remove', 'label' => 'The default OG:Image for any page/post/... that has no OG:Image defined.', 'comment-icon' => 'dashicons-info', 'comment' => 'You can use ' . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . ' as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.' . $image_comment],
				'image_use_thumbnail' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use the WordPress Featured image, if selected, before using the default image selected above.', 'default' => 'on'],

				'image_logo' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'types' => 'image/gif,image/png', 'label' => 'Your logo', 'comment' => 'For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.'],
				'logo_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid(), 'label' => 'Default logo position', 'default' => 'bottom-right'],
				'image_logo_size' => ['namespace' => self::OPTION_PREFIX, 'type' => 'slider', 'class' => 'single-slider', 'label' => 'Logo-scale', 'comment' => '', 'default' => '20%', 'min' => Plugin::MIN_LOGO_SCALE, 'max' => Plugin::MAX_LOGO_SCALE, 'step' => 1],

				'text' => ['namespace' => self::DEFAULTS_PREFIX, 'class' => 'hidden editable-target', 'type' => 'textarea', 'label' => 'The default text to overlay if no other text or title can be found.', 'comment' => 'This should be a generic text that is applicable to the entire website.', 'default' => get_bloginfo('name') . ' - ' . get_bloginfo('description')],
				'text__font' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'select', 'label' => 'Font', 'options' => self::get_font_list(), 'comment' => 'Fonts are stored in your uploads folder. You can manage them there.'],
				'text__ttf_upload' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'file', 'types' => 'font/ttf', 'label' => 'Font upload', 'upload' => 'Upload .ttf file'],
//				'text__google_download' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Google Font Download', 'comment' => 'Enter a Google font name as it is listed on fonts.google.com'],

				'text_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'label' => 'The default text position', 'options' => self::position_grid(), 'default' => 'center'],
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
				'disabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Check if you don\'t want a Social Image with this ' . get_post_type()],
				'text_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use text on this image?', 'default' => 'yes', 'value' => 'yes', 'comment' => 'Uncheck if you do not wish text on this image.'],

				'image' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'types' => 'image/png,image/jpeg,image/webp', 'label' => 'You can upload/select a specific OG Image here', 'comment' => 'You can use ' . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . ' as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.' . $image_comment],

				'text' => ['namespace' => self::OPTION_PREFIX, 'type' => 'textarea', 'class' => 'hidden editable-target', 'label' => 'Text on image', 'If you leave this blank, the current page title is used as it appears in the webpage HTML. If you have Yoast SEO or RankMath installed, the title is taken from that.'],
				'color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Text color', 'default' => get_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF')],
				'text_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'label' => 'Text position', 'options' => self::position_grid(), 'default' => get_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-right')],

				'background_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Text background color', 'default' => get_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666')],

				'text_stroke_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => 'Stroke color', 'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000')],
				'text_stroke' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Default stroke width', 'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke', '0')],

				'text_shadow_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'label' => 'Text shadow color', get_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000')],
				'text_shadow_top' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2')],
				'text_shadow_left' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_left', '2')],
				'text_shadow_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use a text shadow', 'comment' => 'Will improve readability of light text on light background.', 'value' => 'on', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off')],

				'logo_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use a logo on this image?', 'default' => 'yes', 'comment' => 'Uncheck if you do not wish a logo on this image, or choose a position below'],
				'logo_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'label' => 'Logo position', 'class' => 'position-grid', 'options' => self::position_grid(), 'default' => get_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right')],

				'image_logo' => ['namespace' => Admin::DO_NOT_RENDER, 'type' => 'image', 'types' => 'image/gif,image/png', 'label' => 'Your logo', 'comment' => 'For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.', 'default' => get_option(self::OPTION_PREFIX . 'image_logo')],
			]
		];

		if ('on' !== Plugin::FEATURE_STROKE) {
			unset($options['admin']['text_stroke_color']);
			unset($options['admin']['text_stroke']);
			unset($options['meta']['text_stroke_color']);
			unset($options['meta']['text_stroke']);
		}

		if ('on' !== Plugin::FEATURE_META_LOGO_OPTIONS) {
			unset($options['meta']['logo_position']);
		}

		if ('on' !== Plugin::FEATURE_META_TEXT_OPTIONS) {
			unset($options['meta']['color']);
			unset($options['meta']['text_position']);
			unset($options['meta']['background_color']);
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

	public static function text_fallback_chain(): array
	{
		$post_id = get_the_ID();
		$layers = [];
		if ($post_id) {
			try {
				$page = wp_remote_retrieve_body(wp_remote_get(get_permalink($post_id), ['httpversion' => '1.1', 'user-agent' => $_SERVER["HTTP_USER_AGENT"], 'referer' => remove_query_arg('asd')]));
			} catch (\Exception $e) {
				$page = '';
			}
			// this is a lousy way of getting a processed og:title, but unfortunately, no easy options exist.
			// also; poor excuse for tag parsing. sorry.
			if ($page && false !== strpos($page, 'og:title')) {
				preg_match('/og:title.+content=(.)([^\n]+)/', $page, $m);
				$title = $m[2];
				$quote = $m[1];

				$layers['scraped'] = trim($title, ' />' . $quote);
			}

			$layers['default'] = get_option(self::DEFAULTS_PREFIX . 'text');
		}

		return $layers;
	}

	public static function image_fallback_chain($with_post = false): array
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

		if ($with_post) {
			$layers['meta'] = get_post_meta(get_the_ID(), Plugin::OPTION_PREFIX . 'image', true);
		}

		foreach ($layers as &$layer) {
			if ($layer) {
				$image = wp_get_attachment_image_src($layer, Plugin::IMAGE_SIZE_NAME);
				$layer = $image[0];
			}
		}

		return $layers;
	}

	public static function get_font_list(): array
	{
		$fonts = Admin::valid_fonts();
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

	public static function admin_bar($admin_bar)
	{
		if (!is_admin() && array_filter(Plugin::image_fallback_chain(true))) {
			$args = array(
				'id' => Admin::ADMIN_SLUG . '-view',
				'title' => 'View Social Image',
				'href' => '#',
				'meta' => [
					'class' => Admin::ADMIN_SLUG . '-view'
				]
			);
			$admin_bar->add_node($args);

			add_action('wp_footer', [static::class, 'admin_bar_script'], PHP_INT_MAX);
		}
	}

	public static function admin_bar_script()
	{
		?>
<style>
	.<?php print Admin::ADMIN_SLUG; ?>-view-preview {
		display: none;
		z-index: 0;
	}
	body.<?php print Admin::ADMIN_SLUG; ?>-view-active .<?php print Admin::ADMIN_SLUG; ?>-view-preview {
		display: block;
		position: fixed;
		z-index: <?php print PHP_INT_MAX; ?>;
		top: 40px;
		left: 50%;
		transform: translateX(-50%);
	}
</style>
<script>
	;(function($,a){
		$('.'+a).on('click touchend', function(e){ e.preventDefault(); $('body').toggleClass(a+'-active'); })
		var preview = $('.'+a+'-preview');
		if (preview.length < 1) {
			var div = $('<div/>');
			div.addClass(a+'-preview');
			$('body').append(div);
			div.append('<img src="./<?php print Plugin::BSI_IMAGE_NAME; ?>/" />');
			preview = $('.'+a+'-preview');
		}
	})(jQuery, <?php print json_encode(Admin::ADMIN_SLUG . '-view'); ?>);
</script>
		<?php
	}

	public function hex_to_rgba($hex_color, $alpha_is_gd = false): array
	{
		if (substr($hex_color, 0, 1) !== '#') {
			$hex_color = '#ffffffff';
		}
		$hex_values = str_split(substr($hex_color, 1), 2);
		$int_values = array_map('hexdec', $hex_values);
		// the last value is 255 for opaque and 0 for transparent, but GD uses 0 - 127 for the same
		if ($alpha_is_gd) {
			$int_values[3] = 255 - $int_values[3];
			$int_values[3] = $int_values[3] / 255 * 127;
			$int_values[3] = intval(floor($int_values[3]));
		}

		return $int_values;
	}

	public function rgba_to_hex($rgba_color, $alpha_is_gd = false): string
	{
		if ($alpha_is_gd) {
			$rgba_color[3] = intval($rgba_color[3]);
			$rgba_color[3] = $rgba_color[3] / 127 * 255;
			$rgba_color[3] = 255 - floor($rgba_color[3]);
			$rgba_color[3] = max(0, $rgba_color[3]); // minimum value = 0
			$rgba_color[3] = min(255, $rgba_color[3]); // maximum value = 255
		}
		$hex_values = array_map(function ($in) {
			return sprintf("%02s", dechex($in));
		}, $rgba_color);

		return '#' . strtoupper(substr(implode('', $hex_values), 0, 8));
	}

}
