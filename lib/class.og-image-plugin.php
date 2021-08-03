<?php

namespace Clearsite\Plugins\OGImage;

class Plugin
{
	const FEATURE_STROKE = 'off';
	const FEATURE_SHADOW = 'simple';
	const PADDING = 40;

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
		add_action('wp', function () {
			// thi sis currently THE size for OG images.
			$this->width = 1200;
			$this->height = 630;

			$defaults = $this->default_options();
			$this->logo_options = $defaults['logo_options'];

			// text options
			$this->text_options = $defaults['text_options'];
//			var_dump( get_attached_file(get_option(Admin::DEFAULTS_PREFIX . 'text__ttf_upload')));exit;
			$font_file = get_option(Admin::DEFAULTS_PREFIX . 'text__font');

			$this->text_options['font-file'] = $font_file;

			$this->text_options['color'] = get_option(Admin::DEFAULTS_PREFIX . 'color');
			$this->text_options['background-color'] = get_option(Admin::DEFAULTS_PREFIX . 'background_color');
			$this->text_options['text-stroke'] = get_option(Admin::DEFAULTS_PREFIX . 'text_stroke');
			$this->text_options['text-stroke-color'] = get_option(Admin::DEFAULTS_PREFIX . 'text_stroke_color');
			if ('on' === Plugin::FEATURE_SHADOW) {
				$this->text_options['text-shadow-color'] = get_option(Admin::DEFAULTS_PREFIX . 'text_shadow_color');
				$this->text_options['text-shadow-left'] = get_option(Admin::DEFAULTS_PREFIX . 'text_shadow_left');
				$this->text_options['text-shadow-top'] = get_option(Admin::DEFAULTS_PREFIX . 'text_shadow_top');
			}
			if ('simple' === Plugin::FEATURE_SHADOW) {
				$enabled = get_option(Admin::DEFAULTS_PREFIX . 'text_shadow_enabled', 'off');
				$enabled = 'off' === $enabled ? false : $enabled;
				$this->text_options['text-shadow-color'] = $enabled?'#55555588':'#00000000';
				$this->text_options['text-shadow-left'] = -2;
				$this->text_options['text-shadow-top'] = 2;
			}
			$this->validate_text_options();
			$this->validate_logo_options();

			$this->text_options['position'] = get_option(Admin::DEFAULTS_PREFIX . 'text_position', 'top-left');
			$this->logo_options['position'] = get_option(Admin::DEFAULTS_PREFIX . 'logo_position', 'bottom-right');
			$id = get_the_ID();
			if ($id) {
				$overrule_text_position = get_post_meta($id, Admin::OPTION_PREFIX . 'text_position', true);
				if ($overrule_text_position) {
					$this->text_options['position'] = $overrule_text_position;
				}

				$overrule_logo_enabled = get_post_meta($id, Admin::OPTION_PREFIX . 'logo_enabled', true);
				if (!$overrule_logo_enabled || 'yes' === $overrule_logo_enabled) {
					$this->logo_options['enabled'] = $overrule_logo_enabled;
				}

				$overrule_logo_position = get_post_meta($id, Admin::OPTION_PREFIX . 'logo_position', true);
				if ($overrule_logo_position) {
					$this->logo_options['position'] = $overrule_logo_position;
				}

				$overrule_color = get_post_meta($id, Admin::OPTION_PREFIX . 'color', true);
				if ($overrule_color) {
					$this->text_options['color'] = $overrule_color;
				}

				$overrule_color = get_post_meta($id, Admin::OPTION_PREFIX . 'background_color', true);
				if ($overrule_color) {
					$this->text_options['background-color'] = $overrule_color;
				}

				$overrule_color = get_post_meta($id, Admin::OPTION_PREFIX . 'text_stroke_color', true);
				if ($overrule_color) {
					$this->text_options['text-stroke-color'] = $overrule_color;
				}

				$overrule = get_post_meta($id, Admin::OPTION_PREFIX . 'text_stroke', true);
				if ($overrule !== '') {
					$this->text_options['text-stroke'] = intval($overrule);
				}

				$overrule_color = get_post_meta($id, Admin::OPTION_PREFIX . 'text_shadow_color', true);
				if ($overrule_color) {
					$this->text_options['text-shadow-color'] = $overrule_color;
				}

				$overrule_left = get_post_meta($id, Admin::OPTION_PREFIX . 'text_shadow_left', true);
				if ($overrule_left !== '') {
					$this->text_options['text-shadow-left'] = $overrule_left;
				}

				$overrule_top = get_post_meta($id, Admin::OPTION_PREFIX . 'text_shadow_top', true);
				if ($overrule_top !== '') {
					$this->text_options['text-shadow-top'] = $overrule_top;
				}

				$overrule_tsenabled = get_post_meta($id, Admin::OPTION_PREFIX . 'text_shadow_enabled', true);
				if ($overrule_tsenabled === 'on') {
					$this->text_options['text-shadow-color'] = '#55555588';
					$this->text_options['text-shadow-top'] = -2;
					$this->text_options['text-shadow-left'] = 2;
				}
			}

			$this->expand_text_options();
			$this->expand_logo_options();
		});

		add_action('init', function(){
			add_rewrite_endpoint(Admin::BSI_IMAGE_NAME, EP_PERMALINK | EP_ROOT | EP_PAGES, 'clsogimg');
			add_image_size('og-image', $this->width, $this->height, true);
		});

		add_action('admin_init', function() {
			$font_file = get_option(Admin::DEFAULTS_PREFIX . 'text__font');
			if (preg_match('/google:(.+)/', $font_file, $m)) {
				$defaults = $this->default_options();
				$this->text_options = $defaults['text_options'];
				$this->text_options['font-file'] = $font_file;
				$this->text_options['font-family'] = $font_file;
				$this->expand_text_options();
				if ($this->text_options['font-file'] && is_file($this->text_options['font-file']) && $this->text_options['font-file'] !== $font_file) { // PROCESSED!
					update_option(Admin::DEFAULTS_PREFIX . 'text__font', basename($this->text_options['font-file']));
					wp_redirect(remove_query_arg('asdadasd'));
					exit;
				}
			}
		});

		// phase 2; alter the endpoints to be data-less (like /feed)
		add_filter('rewrite_rules_array', function ($rules) {
			$new_rules = [];
			foreach ($rules as $source => $target) {
				if (preg_match('/'. strtr(Admin::BSI_IMAGE_NAME, [ '.' => '\\.', '-' => '\\-' ]) .'/', $source)) {
					$source = explode(Admin::BSI_IMAGE_NAME, $source);
					$source = $source[0] . Admin::BSI_IMAGE_NAME . '/?$';

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
						$this->text_options['text-shadow-color'] = '#55555588';
						$this->text_options['text-shadow-top'] = -2;
						$this->text_options['text-shadow-left'] = 2;
					}
					if (!empty($_GET['text'])) {
						add_filter('bsi_text', function($text) {
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
				$killswitch = get_post_meta($id, Admin::OPTION_PREFIX . 'disabled', true);
				if (!$killswitch && 'on' !== $killswitch) {
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

	}

	public $page_has_og_image = false;

	public static function overrule_og_image($old = null)
	{
//		var_dump($old);exit;
		self::getInstance()->page_has_og_image = true;
		return trailingslashit(remove_query_arg(array_keys(!empty($_GET) ? $_GET : ['asd' => 1]))) . Admin::BSI_IMAGE_NAME;
	}

	public static function late_head()
	{
		if (!self::getInstance()->page_has_og_image) {
			?>
			<meta property="og:image" content="<?php print self::overrule_og_image(); ?>"><?php
		}
	}

	public function default_options()
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
			'size' => get_option(Admin::OPTION_PREFIX . 'image_logo_size', '20%'),
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
		$this->text_options['text'] = get_option(Admin::DEFAULTS_PREFIX . 'text');
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

		$top = 'null' === $top || (empty($top) && 0 !== $top && '0' !== $top) ? null : $top;
		$right = 'null' === $right || (empty($right) && 0 !== $right && '0' !== $right) ? null : $right;
		$bottom = 'null' === $bottom || (empty($bottom) && 0 !== $bottom && '0' !== $bottom) ? null : $bottom;
		$left = 'null' === $left || (empty($left) && 0 !== $left && '0' !== $left) ? null : $left;

		$this->evaluate_vertical($top, $bottom);
		$this->evaluate_horizontal($left, $right);

		if (null !== $top && null !== $bottom) {
			$valign = 'center';
		} elseif (null !== $top) {
			$valign = 'top';
		} else {
			$valign = 'bottom';
		}
		if (null !== $left && null !== $right) {
			$halign = 'center';
		} elseif (null !== $left) {
			$halign = 'left';
		} else {
			$halign = 'right';
		}
		$this->text_options['valign'] = $valign;
		$this->text_options['halign'] = $halign;

		$shadow_type = 'open';
		foreach (['left','top'] as $dir) {
			if (preg_match('/[0-9]+S/', $this->text_options['text-shadow-' . $dir])) {
				$shadow_type = 'solid';
			}
			if (preg_match('/[0-9]+G/', $this->text_options['text-shadow-' . $dir])) {
				$shadow_type = 'gradient';
			}
		}
		$this->text_options['text-shadow-type'] = $shadow_type;
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

		$this->logo_options['file'] = get_option(Admin::OPTION_PREFIX . 'image_logo');
		if (is_numeric($this->logo_options['file'])) {
			$this->logo_options['file'] = get_attached_file($this->logo_options['file']);
		}
		list($sw, $sh) = is_file($this->logo_options['file']) ? getimagesize($this->logo_options['file']) : [0,0];
		if ($sw && $sh) {
			$sa = $sw / $sh;
			$this->logo_options['source_width'] = $sw;
			$this->logo_options['source_height'] = $sh;
			$this->logo_options['source_aspectratio'] = $sa;
		} else {
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

		$top = 'null' === $top || (empty($top) && 0 !== $top && '0' !== $top) ? null : $top;
		$right = 'null' === $right || (empty($right) && 0 !== $right && '0' !== $right) ? null : $right;
		$bottom = 'null' === $bottom || (empty($bottom) && 0 !== $bottom && '0' !== $bottom) ? null : $bottom;
		$left = 'null' === $left || (empty($left) && 0 !== $left && '0' !== $left) ? null : $left;

		$this->evaluate_vertical($top, $bottom);
		$this->evaluate_horizontal($left, $right);

		if (null !== $top && null !== $bottom) {
			$valign = 'center';
		} elseif (null !== $top) {
			$valign = 'top';
		} else {
			$valign = 'bottom';
		}
		if (null !== $left && null !== $right) {
			$halign = 'center';
		} elseif (null !== $left) {
			$halign = 'left';
		} else {
			$halign = 'right';
		}
		$this->logo_options['valign'] = $valign;
		$this->logo_options['halign'] = $halign;

		// update july 2021: logo size is ALWAYS a percentage
		if (intval($this->logo_options['size']) < 100) {
			$this->logo_options['size'] = trim($this->logo_options['size'], '%') . '%';
		}

		// size w and h are bounding box!
		if (preg_match('/[0-9]+%/', $this->logo_options['size'])) {
			$this->logo_options['size'] = min(100, intval($this->logo_options['size']));
			$this->logo_options['size'] = max(0, intval($this->logo_options['size']));
			$this->logo_options['w'] = $this->logo_options['size'] / 100 * $this->width;
			$this->logo_options['h'] = $this->logo_options['size'] / 100 * $this->height;
			$this->logo_options['size'] .= '%';
		} elseif (preg_match('/([0-9]+)x([0-9]+)/', $this->logo_options['size'], $m)) {
			$w = $m[1];
			$w = min($this->width, $w);
			$this->logo_options['w'] = max(0, $w); // stupid to set 0, but could mean "auto"
			$h = $m[2];
			$h = min($this->height, $h);
			$this->logo_options['h'] = max(0, $h); // stupid to set 0, but could mean "auto"
		} else { // assume a pixel width
			$w = intval($this->logo_options['size']);
			$w = min($this->width, $w);
			$this->logo_options['w'] = max(0, $w); // stupid to set 0, but could mean "auto"
			$this->logo_options['h'] = 0; // auto
		}
		// resolve "unset"
		if (!$this->logo_options['w'] && !$this->logo_options['h']) {
			$this->logo_options['w'] = $sw;
			$this->logo_options['h'] = $sh;
		}
		// resolve "auto"
		if ($this->logo_options['w'] && !$this->logo_options['h']) { // auto height
			$this->logo_options['h'] = $this->logo_options['w'] / $sa;
		}
		if (!$this->logo_options['w'] && $this->logo_options['h']) { // auto height
			$this->logo_options['w'] = $this->logo_options['h'] * $sa;
		}
		// resolve aspect issues
		// -> this makes bounding box actual image size
		$scale = min($this->logo_options['w'] / $sw, $this->logo_options['h'] / $sh);
		$this->logo_options['w'] = $sw * $scale;
		$this->logo_options['h'] = $sh * $scale;
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
		$instance = self::getInstance();
		if (is_admin()) {
			$admin = Admin::getInstance();
			$admin->storage = $instance->storage();
		}
	}

	public function evaluate_font_style($style, $default = 'normal')
	{
		$allowed = ['normal', 'italic'];
		if (!in_array($style, $allowed)) {
			return $default;
		}
		return $style;
	}

	public function evaluate_font_weight($weight, $default = 400)
	{
		$translate = Admin::font_weights();

		if (!intval($weight)) {
			if (isset($translate[strtolower($weight)])) {
				$weight = $translate[strtolower($weight)];
			} else {
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

	public static function setError($tag, $text)
	{
		$errors = get_option(Admin::OPTION_PREFIX . 'image__errors', []);
		$errors[$tag] = $text;
		$errors = array_filter($errors);
		update_option(Admin::OPTION_PREFIX . 'image__og_logo_errors', $errors);
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
			} else {
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
		$hex_values = array_map(function($in) { return sprintf("%02s", dechex($in)); }, $rgba_color);

		return '#' . strtoupper( substr(implode('', $hex_values), 0, 8) );
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


	public static function maybe_fake_support_webp(): bool
	{
		$support = false;

		$bin = dirname(__DIR__) .'/bin';
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
			}
			catch(\Exception $e) {

			}
			$log = ob_get_clean();
		}

		if (!$target || !file_exists($target)) {
			$target = $source;
		}

		return $target;
	}
}
