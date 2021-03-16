<?php

namespace Clearsite\Plugins\OGImage;

class Plugin
{
	public $width;
	public $height;
	public $logo_options;
	public $text_options;
	public $preview;

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
		add_action('init', function () {
			$this->width = get_site_option('cls_og_image__og_width', 1200);
			$this->height = get_site_option('cls_og_image__og_height', 630);

			$defaults = $this->default_options();
			$this->logo_options = get_site_option('cls_og_image__og_logo_options', $defaults['logo_options']);
			$this->logo_options['enabled'] = $this->logo_options['enabled'] == 'on';

			// text options
			$this->text_options = get_site_option('cls_og_image__og_text_options', $defaults['text_options']);
			$this->text_options['enabled'] = $this->text_options['enabled'] == 'on';

			add_rewrite_endpoint('og-image.png', EP_PERMALINK | EP_ROOT | EP_PAGES, 'clsogimg');
			add_image_size('og-image', $this->width, $this->height, true);

			$this->validate_text_options();
			$this->expand_text_options();
		});

		// phase 2; alter the endpoints to be data-less (like /feed)
		add_filter('rewrite_rules_array', function ($rules) {
			$new_rules = [];
			foreach ($rules as $source => $target) {
				if (preg_match('/og-image\.png/', $source)) {
					$source = explode('og-image.png', $source);
					$source = $source[0] . 'og-image.png/?$';

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
					$this->expand_text_options();
				}
				$og_image->serve_type = get_site_option('cls_og_image__serve_type', 'inline'); // inline or redirect
				$og_image->serve();
				exit;
			}
		});

	}

	public function default_options()
	{
		$defaults = [];
		$defaults['text_options'] = [ // colors are RGBA in hex format
			'enabled' => 'on',
			'left' => '20', 'bottom' => '20', 'top' => null, 'right' => null,
			'font-size' => '32', 'color' => '#ffffffff', 'line-height' => '40',
			'font-family' => 'google:Redressed',
			'font-weight' => 400,
			'font-style' => 'normal',
			'display' => 'inline', // determines background-dimensions block: 100% width??? inline-block: rectangle around all text, inline: behind text only
			'padding' => '10',
			'background-color' => '#99999999',
			'text-shadow-color' => '',
			'text-shadow-left' => '2',
			'text-shadow-top' => '-2',
			'text-stroke-color' => '',
			'text-stroke' => '2',
		];
		$defaults['logo_options'] = [
			'enabled' => '',
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

	public function validate_text_options()
	{
		$all_possible_options = $this->default_options();
		$all_possible_options = $all_possible_options['text_options'];

		$this->text_options = shortcode_atts($all_possible_options, $this->text_options);

		// colors
		$colors = [ 'background-color', 'color', 'text-shadow-color', 'text-stroke-color' ];
		foreach ($colors as $_color) {
			$color = strtolower($this->text_options[ $_color ]);

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
			$this->text_options[ $_color ] = $color;
		}
	}

	public function expand_text_options()
	{
		$this->text_options['font-weight'] = $this->evaluate_font_weight($this->text_options['font-weight'], 400);
		$this->text_options['font-style'] = $this->evaluate_font_style($this->text_options['font-style'], 'normal');

		$this->text_options['font-file'] = $this->font_filename($this->text_options['font-family'], $this->text_options['font-weight'], $this->text_options['font-style']);

		// we need a TTF
		if (!is_file($this->storage() .'/'. $this->text_options['font-file']) || substr($this->text_options['font-file'], -4) !== '.ttf') {
			$this->text_options['font-file'] = $this->download_font($this->text_options['font-family'], $this->text_options['font-weight'], $this->text_options['font-style']);
		}
		if (is_file($this->storage() .'/'. $this->text_options['font-file'])) {
			$this->text_options['font-file'] = $this->storage() .'/'. $this->text_options['font-file'];
		}

		// text positioning
		$top = &$this->text_options['top'];
		$right = &$this->text_options['right'];
		$bottom = &$this->text_options['bottom'];
		$left = &$this->text_options['left'];

		$top = 'null' === $top || (empty( $top ) && 0 !== $top && '0' !== $top) ? null : $top;
		$right = 'null' === $right || (empty( $right ) && 0 !== $right && '0' !== $right) ? null : $right;
		$bottom = 'null' === $bottom || (empty( $bottom ) && 0 !== $bottom && '0' !== $bottom) ? null : $bottom;
		$left = 'null' === $left || (empty( $left ) && 0 !== $left && '0' !== $left) ? null : $left;

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
	}

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public static function init()
	{
		self::getInstance();
	}

	public function evaluate_font_style($style, $default = 'normal')
	{
		$allowed = [ 'normal', 'italic' ];
		if (!in_array($style, $allowed)) {
			return $default;
		}
		return $style;
	}

	public function evaluate_font_weight($weight, $default = 400)
	{
		$translate = [
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
		if (!intval($weight)) {
			if (isset($translate[ strtolower($weight) ])) {
				$weight = $translate[ strtolower($weight) ];
			}
			else {
				$weight = $default;
			}
		}
		$weight = floor($weight/100) * 100;
		if (!$weight) {
			$weight = $default;
		}
		if ($weight > 800) {
			$weight = 800;
		}

		return $weight;
	}

	public static function setError($tag, $text)
	{
		$errors = get_option('cls_og_image__errors', []);
		$errors[ $tag ] = $text;
		$errors = array_filter($errors);
		update_option('cls_og_image__og_logo_errors', $errors);
	}

	public function font_filename($font_family, $font_weight, $font_style)
	{
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';
			$font_filename = $m[1] /* fontname */ . '-w'. $font_weight . ( $italic ? '-'. $italic : '' ) .'.ttf';
			return $font_filename;
		}

		// don't know what to do with any other
		return false;
	}


	public function download_font($font_family, $font_weight, $font_style)
	{
		self::setError('font-family', null);
		$font_filename = $this->font_filename($font_family, $font_weight, $font_style);
		if (!$font_filename) {
			self::setError('font-family', __('Don\'t know where to get this font. Sorry.', 'clsogimg'));
			return false;
		}
		if (is_file($this->storage() .'/'. $font_filename)) {
			return $font_filename;
		}
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';
			$font_css = wp_remote_retrieve_body(wp_remote_get('http://fonts.googleapis.com/css?family='. $m[1] .':' . $font_weight . $italic, ['useragent' => ' ']));

			if (!$font_css) {
				self::setError('font-family', __('Could not download font from Google Fonts. Please download yourself and upload here.', 'clsogimg'));
				return false;
			}
			// grab any url
			self::setError('font-family', null);
			if (preg_match('@https?://[^)]+ttf@', $font_css, $n)) {
				$font_ttf = wp_remote_retrieve_body( wp_remote_get($n[0]) );
				$this->file_put_contents( $this->storage() .'/'. $font_filename, $font_ttf );
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

	private function storage()
	{
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/og-images';
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		self::setError('storage', null);
		if (!is_dir($dir)) {
			self::setError('storage', __('Could not create the storage directory in the uploads folder. In a WordPress site the uploads folder should always be writable. Please fix this. This error will disappear once the problem has been corrected.', 'clsogimg'));
		}
		return $dir;
	}

	public function hex_to_rgba($hex_color, $alpha_is_gd = false)
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

}
