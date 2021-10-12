<?php

namespace Clearsite\Plugins\OGImage;

use RankMath;

defined('ABSPATH') or die('You cannot be here.');

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
	const MIN_FONT_SIZE = 16;
	const MAX_FONT_SIZE = 64;
	const DEF_FONT_SIZE = 40;
	const PLUGIN_URL_WPORG = 'https://wordpress.org/plugins/branded-social-images/';
	const CLEARSITE_URL_INFO = 'https://www.clearsite.nl/';
	const BSI_URL_CONTACT = 'https://wordpress.org/support/plugins/branded-social-images/';
	const BSI_URL_CONTRIBUTE = 'https://github.com/clearsite/branded-social-images/';
	const EXTERNAL_INSPECTOR_NAME = 'opengraph.xyz';
	const EXTERNAL_INSPECTOR = 'https://www.opengraph.xyz/url/%s/';
	public const DO_NOT_RENDER = 'do_not_render';
	public const ADMIN_SLUG = 'branded-social-images';
	public const ICON = 'icon.svg';
	public const TEXT_DOMAIN = 'bsi';
	public const QUERY_VAR = 'bsi_img';

	public $width = 1200;
	public $height = 630;
	public $logo_options;
	public $text_options;
	public $page_already_has_og_image = false;
	public $og_image_available;

	public function __construct()
	{
		add_filter('query_vars', function ($vars) {
			$vars[] = Plugin::QUERY_VAR;
			return $vars;
		});

		add_action('wp', function () {
			// oh, my, this is a mess.
			// ...
			// todo: create new settings manager that handles all this.

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
			add_rewrite_endpoint(self::BSI_IMAGE_NAME, EP_PERMALINK | EP_ROOT | EP_PAGES, Plugin::QUERY_VAR);

			if (get_option("bsi_needs_rewrite_rules")) {
				delete_option("bsi_needs_rewrite_rules");
				global $wp_rewrite;
				update_option("rewrite_rules", FALSE);
				$wp_rewrite->flush_rules(true);
			}
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
					wp_redirect(remove_query_arg(''));
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
			if (get_query_var(Plugin::QUERY_VAR)) {
				require_once __DIR__ . '/class.og-image.php';
				$og_image = new Image($this);
				$og_image->serve();
				exit;
			}
		});

		add_action('wp', function () {
			$id = get_the_ID();
			if (!is_admin() && $id) {
				$killswitch = get_post_meta($id, self::OPTION_PREFIX . 'disabled', true);
				$go = true;
				if ('on' === $killswitch) {
					$go = false;
				}
				if (!Plugin::getInstance()->og_image_available) {
					$go = false;
				}
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

					// overrule WordPress JetPack recently acquired SocialImageGenerator, because, hey, we were here first!
					add_filter('sig_image_url', function ($url, $post_id) {
						return static::get_og_image_url($post_id);
					}, PHP_INT_MAX, 2);

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
		$this->logo_options['position'] = get_option(self::DEFAULTS_PREFIX . 'logo_position');

		// text options
		$this->text_options = $defaults['text_options'];
//			var_dump( get_attached_file(get_option(Admin::DEFAULTS_PREFIX . 'text__ttf_upload')));exit;
		$font_file = get_option(self::DEFAULTS_PREFIX . 'text__font');

		$this->text_options['font-file'] = $font_file;

		$this->text_options['position'] = get_option(self::DEFAULTS_PREFIX . 'text_position');
		$this->text_options['color'] = get_option(self::DEFAULTS_PREFIX . 'color');
		$this->text_options['background-color'] = get_option(self::DEFAULTS_PREFIX . 'background_color');
		$this->text_options['background-enabled'] = get_option(self::DEFAULTS_PREFIX . 'background_enabled', 'on');
		$this->text_options['text-stroke'] = get_option(self::DEFAULTS_PREFIX . 'text_stroke');
		$this->text_options['text-stroke-color'] = get_option(self::DEFAULTS_PREFIX . 'text_stroke_color');

		$this->text_options['font-size'] = get_option(self::OPTION_PREFIX . 'text__font_size', Plugin::DEF_FONT_SIZE);
		$this->text_options['line-height'] = get_option(self::OPTION_PREFIX . 'text__font_size', Plugin::DEF_FONT_SIZE) * 1.25;

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
				if (!is_file($this->text_options['font-file']) && is_file($this->text_options['font-file'] . '.otf')) {
					$this->text_options['font-file'] .= '.otf';
				}
				// revert back to just a filename for backward compatibility
				$this->text_options['font-file'] = basename($this->text_options['font-file']);
			}

			// we need a TTF
			if (
				!is_file($this->storage() . '/' . $this->text_options['font-file']) || (
					substr($this->text_options['font-file'], -4) !== '.ttf' && substr($this->text_options['font-file'], -4) !== '.otf')
			) {
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
			self::setError('storage', __('Could not create the storage directory in the uploads folder.', Plugin::TEXT_DOMAIN) .' ' . __('In a WordPress site the uploads folder should always be writable.', Plugin::TEXT_DOMAIN) .' '. __('Please fix this.', Plugin::TEXT_DOMAIN) .' '. __('This error will disappear once the problem has been corrected.', Plugin::TEXT_DOMAIN));
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
			self::setError('font-family', __('Don\'t know where to get this font.', Plugin::TEXT_DOMAIN) .' ' . __('Sorry.', Plugin::TEXT_DOMAIN));
			return false;
		}
		if (is_file($this->storage() . '/' . $font_filename)) {
			return $font_filename;
		}
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';
			$font_css = wp_remote_retrieve_body(wp_remote_get('http://fonts.googleapis.com/css?family=' . urlencode($m[1]) . ':' . $font_weight . $italic, ['useragent' => ' ']));

			if (!$font_css) {
				self::setError('font-family', __('Could not download font from Google Fonts.', Plugin::TEXT_DOMAIN) .' ' . __('Please download yourself and upload here.', Plugin::TEXT_DOMAIN));
				return false;
			}
			// grab any url
			self::setError('font-family', null);
			if (preg_match('@https?://[^)]+[ot]tf@', $font_css, $n)) {
				$font_ttf = wp_remote_retrieve_body(wp_remote_get($n[0]));
				$this->file_put_contents($this->storage() . '/' . $font_filename, $font_ttf);
				return $font_filename;
			}
			else {
				self::setError('font-family', __('This Google Fonts does not offer a TTF or OTF file.', Plugin::TEXT_DOMAIN) .' ' . __('Sorry, cannot continue at this time.', Plugin::TEXT_DOMAIN));
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

	public static function get_og_image_url($post_id)
	{
		return get_permalink($post_id) ? get_permalink($post_id) . self::BSI_IMAGE_NAME . '/' : false;
	}

	public static function init()
	{
		$instance = self::getInstance();
		if (is_admin()) {
			$admin = Admin::getInstance();
			$admin->storage = $instance->storage();
		}

		load_plugin_textdomain(Plugin::TEXT_DOMAIN, FALSE, basename(dirname(__DIR__)) . '/languages');
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
		$b = basename($font);
		$base = basename($font, '.ttf');
		if ($b === $base) {
			$base = basename($font, '.otf');
		}
		$font = $base;
		if (!empty($tweaks[$font]) && !empty($tweaks[$font][$section])) {
			return $tweaks[$font][$section];
		}
		return false;
	}

	public static function default_google_fonts(): array
	{
		return array_map(function ($font) {
			return $font['font_name'];
		}, self::font_rendering_tweaks());
	}

	public static function font_rendering_tweaks($write_json = false): array
	{
		$tweaks = [
			/** letter-spacing: px, line-height: factor */
			'Anton-Regular' => [
				'font_name' => 'Anton', 'admin' => ['letter-spacing' => '-0.32px'], 'gd' => ['line-height' => 1]
			],
			'Courgette-Regular' => [
				'font_name' => 'Courgette', 'admin' => ['letter-spacing' => '-0.32px'], 'gd' => ['line-height' => .86]
			],
			'JosefinSans-Regular' => [
				'font_name' => 'Josefin Sans', 'admin' => ['letter-spacing' => '-0.4px'], 'gd' => ['line-height' => .96]
			],
			'Merriweather-Regular' => [
				'font_name' => 'Merriweather', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .86]
			],
			'OpenSans-Regular' => [
				'font_name' => 'Open Sans', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .95, 'text-area-width' => '.67']
			],
			'Oswald-Regular' => [
				'font_name' => 'Oswald', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .92, 'text-area-width' => '.67']
			],
			'PTSans-Regular' => [
				'font_name' => 'PT Sans', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => 1.03]
			],
			'Roboto-Regular' => [
				'font_name' => 'Roboto', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .97]
			],
			'WorkSans-Regular' => [
				'font_name' => 'Work Sans', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => 1]
			],
			'AkayaKanadaka-Regular' => [
				'font_name' => 'Akaya Kanadaka', 'admin' => ['letter-spacing' => '0px'], 'gd' => ['line-height' => .98]
			],
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
			'top-left' => __('Top Left', Plugin::TEXT_DOMAIN),
			'top' => __('Top Center', Plugin::TEXT_DOMAIN),
			'top-right' => __('Top Right', Plugin::TEXT_DOMAIN),
			'left' => __('Left Middle', Plugin::TEXT_DOMAIN),
			'center' => __('Centered', Plugin::TEXT_DOMAIN),
			'right' => __('Right Middle', Plugin::TEXT_DOMAIN),
			'bottom-left' => __('Bottom Left', Plugin::TEXT_DOMAIN),
			'bottom' => __('Bottom Center', Plugin::TEXT_DOMAIN),
			'bottom-right' => __('Bottom Right', Plugin::TEXT_DOMAIN),
		];
	}

	public static function get_valid_POST_keys($section = false)
	{
		$list = self::field_list();
		$valid = [];
		foreach ($list as $_section => $sublist) {
			if ($section && $_section !== $section) {
				continue;
			}
			foreach ($sublist as $key => $item) {
				if (empty($valid[$item['namespace']])) {
					$valid[$item['namespace']] = [];
				}
				$valid[$item['namespace']][] = $key;
			}
		}
		return $valid;
	}

	public static function field_list()
	{
		static $once, $support_webp;
		if (!$once) {
			// TODO: ImageMagick?! for now, GD only
			$support_webp = function_exists('imagewebp') || Plugin::maybe_fake_support_webp();
			$once = true;
		}

		$image_comment = __('The following process is used to determine the OG:Image (in order of importance)', Plugin::TEXT_DOMAIN) . ':
<ol><li>' . __('Branded Social Image on page/post', Plugin::TEXT_DOMAIN) . '</li>';
		if (defined('WPSEO_VERSION')) {
			$image_comment .= '<li>' . __('Yoast Social image on page/post', Plugin::TEXT_DOMAIN) . '</li>';
		}
		$image_comment .= '<li>' . __('Featured image on page/post (when checked in general settings)', Plugin::TEXT_DOMAIN) . '</li>';
		$image_comment .= '<li>' . __('Fallback Branded Social image in general settings', Plugin::TEXT_DOMAIN) . '</li></ol>';

		$options = [
			'admin' => [
				'image' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'image', 'types' => 'image/png,image/jpeg,image/webp', 'class' => '-no-remove', 'label' => __('Fallback OG:Image.', Plugin::TEXT_DOMAIN), 'comment' => __('Used for any page/post that has no OG image selected.', Plugin::TEXT_DOMAIN) . ' ' . __('You can use JPEG and PNG.', Plugin::TEXT_DOMAIN) . ' ' . __('Recommended size: 1200x630 pixels.', Plugin::TEXT_DOMAIN)],
				'image_use_thumbnail' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => __('Use the WordPress Featured image.', Plugin::TEXT_DOMAIN), 'default' => 'on', 'info-icon' => 'dashicons-info', 'info' => $image_comment],

				'image_logo' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'types' => 'image/gif,image/png', 'label' => __('Your logo', Plugin::TEXT_DOMAIN), 'comment' => __('Image should be approximately 600 pixels wide/high.', Plugin::TEXT_DOMAIN) . ' ' . __('Use a transparent PNG for best results.', Plugin::TEXT_DOMAIN)],
				'logo_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid(), 'label' => __('Default logo position', Plugin::TEXT_DOMAIN), 'default' => 'top-left'],
				'image_logo_size' => ['namespace' => self::OPTION_PREFIX, 'type' => 'slider', 'class' => 'single-slider', 'label' => __('Logo-scale (%)', Plugin::TEXT_DOMAIN), 'comment' => '', 'default' => '100', 'min' => Plugin::MIN_LOGO_SCALE, 'max' => Plugin::MAX_LOGO_SCALE, 'step' => 1],

				'text' => ['namespace' => self::DEFAULTS_PREFIX, 'class' => 'hidden editable-target', 'type' => 'textarea', 'label' => __('The text to overlay if no other text or title can be found.', Plugin::TEXT_DOMAIN), 'comment' => __('This should be a generic text that is applicable to the entire website.', Plugin::TEXT_DOMAIN), 'default' => get_bloginfo('name') . ' - ' . get_bloginfo('description')],
				'text__font' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'select', 'label' => __('Select a font', Plugin::TEXT_DOMAIN), 'options' => self::get_font_list(), 'default' => 'Roboto-Regular'],
				'text__ttf_upload' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'file', 'types' => 'font/ttf,font/otf', 'label' => __('Font upload', Plugin::TEXT_DOMAIN), 'upload' => __('Upload .ttf/.otf file', Plugin::TEXT_DOMAIN), 'info-icon' => 'dashicons-info', 'info' => __('Custom font must be a .ttf or .otf file.', Plugin::TEXT_DOMAIN) . ' ' . __('You\'re responsible for the proper permissions and usage rights of the font.', Plugin::TEXT_DOMAIN)],
//				'text__google_download' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Google Font Download', 'comment' => 'Enter a Google font name as it is listed on fonts.google.com'],

				'text_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'label' => __('Text position', Plugin::TEXT_DOMAIN), 'options' => self::position_grid(), 'default' => 'bottom-left'],
				'color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => __('Default Text color', Plugin::TEXT_DOMAIN), 'default' => '#FFFFFFFF'],
				'text__font_size' => ['namespace' => self::OPTION_PREFIX, 'type' => 'slider', 'class' => 'single-slider', 'label' => __('Font-size (px)', Plugin::TEXT_DOMAIN), 'comment' => '', 'default' => Plugin::DEF_FONT_SIZE, 'min' => Plugin::MIN_FONT_SIZE, 'max' => Plugin::MAX_FONT_SIZE, 'step' => 1],
				'background_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => __('Text background color', Plugin::TEXT_DOMAIN), 'default' => '#66666666'],
				'background_enabled' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'checkbox', 'label' => __('Use a background', Plugin::TEXT_DOMAIN), 'value' => 'on', 'default' => 'on'],

				'text_stroke_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'color', 'attributes' => 'rgba', 'label' => __('Stroke color', Plugin::TEXT_DOMAIN), 'default' => '#00000000'],
				'text_stroke' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => __('Default stroke width', Plugin::TEXT_DOMAIN), 'default' => 0],

				'text_shadow_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'color', 'label' => __('Default Text shadow color', Plugin::TEXT_DOMAIN), '#00000000'],
				'text_shadow_top' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => __('Shadow offset - vertical.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to top, Positive numbers to bottom.', Plugin::TEXT_DOMAIN), 'default' => '-2'],
				'text_shadow_left' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => __('Shadow offset - horizontal.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to left, Positive numbers to right.', Plugin::TEXT_DOMAIN), 'default' => '2'],
				'text_shadow_enabled' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'checkbox', 'label' => __('Use a text shadow', Plugin::TEXT_DOMAIN), 'value' => 'on', 'default' => 'off'],
			],
			'meta' => [
				'disabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => sprintf(_x('Select if you don\'t want a Social Image with this %s', 'post-type', Plugin::TEXT_DOMAIN), get_post_type()) ],
				'text_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => __('Use text on this image?', Plugin::TEXT_DOMAIN), 'default' => 'yes', 'value' => 'yes', 'comment' => __('Uncheck if you do not wish text on this image.', Plugin::TEXT_DOMAIN)],

				'image' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'types' => 'image/png,image/jpeg,image/webp', 'label' => __('You can upload/select a specific Social Image here', Plugin::TEXT_DOMAIN), 'comment' => __('You can use JPEG and PNG.', Plugin::TEXT_DOMAIN) . ' ' . __('Recommended size: 1200x630 pixels.', Plugin::TEXT_DOMAIN), 'info-icon' => 'dashicons-info', 'info' => $image_comment],

				'text' => ['namespace' => self::OPTION_PREFIX, 'type' => 'textarea', 'class' => 'hidden editable-target', 'label' => __('Text on image', Plugin::TEXT_DOMAIN)],
				'color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => __('Text color', Plugin::TEXT_DOMAIN), 'default' => get_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF')],
				'text_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'label' => __('Text position', Plugin::TEXT_DOMAIN), 'options' => self::position_grid(), 'default' => get_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-left')],

				'background_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => __('Text background color', Plugin::TEXT_DOMAIN), 'default' => get_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666')],

				'text_stroke_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'attributes' => 'rgba', 'label' => __('Stroke color', Plugin::TEXT_DOMAIN), 'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000')],
				'text_stroke' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => __('Default stroke width', Plugin::TEXT_DOMAIN), 'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke', '0')],

				'text_shadow_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'color', 'label' => __('Text shadow color', Plugin::TEXT_DOMAIN), get_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000')],
				'text_shadow_top' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => __('Shadow offset - vertical.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to top, Positive numbers to bottom.', Plugin::TEXT_DOMAIN), 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2')],
				'text_shadow_left' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => __('Shadow offset - horizontal.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to left, Positive numbers to right.', Plugin::TEXT_DOMAIN), 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_left', '2')],
				'text_shadow_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => __('Use a text shadow', Plugin::TEXT_DOMAIN), 'comment' => __('Will improve readability of light text on light background.', Plugin::TEXT_DOMAIN), 'value' => 'on', 'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off')],

				'logo_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => __('Use a logo on this image?', Plugin::TEXT_DOMAIN), 'default' => 'yes', 'comment' => __('Uncheck if you do not wish a logo on this image, or choose a position below.', Plugin::TEXT_DOMAIN)],
				'logo_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'label' => __('Logo position', Plugin::TEXT_DOMAIN), 'class' => 'position-grid', 'options' => self::position_grid(), 'default' => get_option(self::DEFAULTS_PREFIX . 'logo_position', 'top-left')],

				'image_logo' => ['namespace' => self::DO_NOT_RENDER, 'type' => 'image', 'types' => 'image/gif,image/png', 'label' => __('Your logo', Plugin::TEXT_DOMAIN), 'comment' => __('Image should be approximately 600 pixels wide/high.', Plugin::TEXT_DOMAIN) . ' ' . __('Use a transparent PNG for best results.', Plugin::TEXT_DOMAIN), 'default' => get_option(self::OPTION_PREFIX . 'image_logo')],
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
			unset($options['meta']['text_shadow_enabled']);
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

		$title = '';
		if (Plugin::setting('use_bare_post_title')) {
			$layers['wordpress'] = apply_filters('the_title', get_the_title($post_id), $post_id);
		}

		if ($post_id) {
			if (!$title) {
				$title = '';
				try {
					$page = wp_remote_retrieve_body(wp_remote_get(get_permalink($post_id), ['httpversion' => '1.1', 'user-agent' => $_SERVER["HTTP_USER_AGENT"], 'referer' => remove_query_arg('asd')]));
				} catch (\Exception $e) {
					$page = '';
				}
				$page = str_replace(["\n", "\r"], '', $page);
				// this is a lousy way of getting a processed og:title, but unfortunately, no easy options exist.
				// also; poor excuse for tag parsing. sorry.
				if ($page && (false !== strpos($page, 'og:title'))) {
					if (preg_match('/og:title.+content=([\'"])(.+)([\'"])([ \/>])/mU', $page, $m)) {
						$title = $m[2];
						$quote = $m[1];
						$layers['scraped'] = trim($title, ' />' . $quote);
					}

				}
				if ($page && !$title && (false !== strpos($page, '<title'))) {
					if (preg_match('/<title>(.+)<\/title>/mU', $page, $m)) {
						$title = $m[1];
						$layers['scraped'] = trim($title);
					}

				}
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
			$font_name = $_['display_name'];
			$options[$font_base] = $font_name;
		}

		return $options;
	}

	public static function admin_bar($admin_bar)
	{
		if (
			defined('BSI_SHOW_ADMIN_BAR_IMAGE_LINK') &&
			true === BSI_SHOW_ADMIN_BAR_IMAGE_LINK && array_filter(Plugin::image_fallback_chain(true))
		) {
			$args = array(
				'id' => self::ADMIN_SLUG . '-view',
				'title' => __('View Social Image', Plugin::TEXT_DOMAIN),
				'href' => get_permalink(get_the_ID()) . Plugin::BSI_IMAGE_NAME . '/',
				'meta' => [
					'target' => '_blank',
					'class' => self::ADMIN_SLUG . '-view'
				]
			);
			$admin_bar->add_node($args);
		}

		$args = array(
			'id' => self::ADMIN_SLUG . '-inspector',
			'title' => self::icon() . __('Inspect Social Image', Plugin::TEXT_DOMAIN),
			'href' => Plugin::EXTERNAL_INSPECTOR,
			'meta' => [
				'target' => '_blank',
				'title' => __('Shows how this post is shared using an external, unaffiliated service.', Plugin::TEXT_DOMAIN),
			]
		);

		$args['href'] = sprintf($args['href'], urlencode(get_permalink(get_the_ID())));
		$admin_bar->add_node($args);

		add_action('wp_footer', [static::class, 'admin_bar_icon_style'], PHP_INT_MAX);
		add_action('admin_footer', [static::class, 'admin_bar_icon_style'], PHP_INT_MAX);
	}

	public static function admin_bar_icon_style()
	{
		?>
		<style>#wp-admin-bar-<?php print self::ADMIN_SLUG; ?>-inspector svg {
			position: relative;
			top: 3px;
		}</style><?php
	}

	private static function icon()
	{
		if (preg_match('/\.svg$/', Plugin::ICON)) {
			$icon = file_get_contents(dirname(__DIR__) . '/img/' . basename('/' . Plugin::ICON));
			$icon = str_replace('<path', '<path fill="currentColor"', $icon);

			return $icon;
		}
		else {
			return '<img src="' . esc_attr(plugins_url('/img/' . basename('/' . Plugin::ICON), __DIR__)) . '" />';
		}
	}

	public static function get_plugin_file()
	{
		return str_replace(trailingslashit(WP_PLUGIN_DIR), '', BSI_PLUGIN_FILE);
	}

	public static function get_management_permission()
	{
		// @todo: improve this. This is crap.
		$permission = apply_filters('bsi_management_permission', 'edit_posts');
		if (!$permission || trim($permission) == 'read') {
			$permission = 'edit_posts';
		}

		return $permission;
	}

	/**
	 * @function Will eventually handle all settings, but for now, this allows you to overrule...
	 * @param $setting string can be one of ...
	 * $setting = use_bare_post_title, filter = bsi_settings_use_bare_post_title, expects true or false
	 *                                 with true, the WordPress title is used as default
	 *                                 with false, the default title is scraped from the HTML and will therefore be
	 *                                             influenced by plugins like Yoast SEO. This is standard behavior.
	 * @return mixed|void
	 */
	public static function setting($setting)
	{
		return apply_filters('bsi_settings_' . $setting, false);
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
