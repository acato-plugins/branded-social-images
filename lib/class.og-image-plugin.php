<?php

namespace Clearsite\Plugins\OGImage;

use Exception;
use RankMath;

defined('ABSPATH') or die('You cannot be here.');

class Plugin
{
	/** @var string Defines the URL-endpoint. After changing, re-save permalinks to take effect */
	const BSI_IMAGE_NAME = 'social-image';
	/** @var string Experimental feature text-stroke. (off|on) */
	const FEATURE_STROKE = 'off';
	/** @var string Reduced functionality for clarity. (off|simple|on) */
	const FEATURE_SHADOW = 'simple';
	/** @var string Enable text-options in post-meta. Turned off to reduce confusion. (off|on) */
	const FEATURE_META_TEXT_OPTIONS = 'off';
	/** @var string Enable logo-options in post-meta. Turned off to reduce confusion. (off|on) */
	const FEATURE_META_LOGO_OPTIONS = 'off';
	/** @var float fraction of 1, maximum width of the text-field on the image. values from .7 to .95 work fine. Future feature: setting in interface. */
	const TEXT_AREA_WIDTH = .95;
	/** @var int logo and text offset from image edge. This is a value in pixels */
	const PADDING = 40;
	/** @var int Experimental feature: attempt a smoother end result by using higher scale materials. Image and logo are pixel based and therefore need
	 * to be of higher than average resolution for this to work;
	 * For example, if set to 2; 2400x1260 for the image and min 1200 w/h for the logo. You can even use 3 or 4 ;)
	 * After changing, you will need to use a 3rd party plugin to "rebuild thumbnails" to re-generate the proper formats.
	 */
	const AA = 1;
	/** @var string The name of the folder in /wp-uploads (/wp-content/uploads) */
	const STORAGE = 'bsi-uploads';
	/** @var string The name of the WordPress "image-size", visible in the interface with plugins like "ajax thumbnail rebuild" */
	const IMAGE_SIZE_NAME = 'og-image';
	/** @var string The script and style names */
	const SCRIPT_STYLE_HANDLE = 'bsi';
	/** @var int lower boundary for logo scaling, a percentage value (positive number, 100 = 1:1 scale) */
	const MIN_LOGO_SCALE = 10;
	/** @var int upper boundary for logo scaling, a percentage value (positive number, 100 = 1:1 scale) */
	const MAX_LOGO_SCALE = 200;
	/** @var int lower boundary for font-size, a points value. (Yes, points. GD2 works in points, 100 pixels = 75 points) */
	const MIN_FONT_SIZE = 16;
	/** @var int upper boundary for font-size, a points value. */
	const MAX_FONT_SIZE = 64;
	/** @var int default value for font-size, a points value. */
	const DEF_FONT_SIZE = 40;
	/** @var string External URL: the WP Plugin repository URL */
	const PLUGIN_URL_WPORG = 'https://wordpress.org/plugins/branded-social-images/';
	/** @var string External URL: Our website */
	const AUTHOR_URL_INFO = 'https://acato.nl/';
	/** @var string External URL: the WP Plugin support URL */
	const BSI_URL_CONTACT = 'https://wordpress.org/support/plugin/branded-social-images/';
	/** @var string External URL: the GitHub  repository URL */
	const BSI_URL_CONTRIBUTE = 'https://github.com/clearsite/branded-social-images/';
	/** @var string External tool for post-inspection, the name */
	const EXTERNAL_INSPECTOR_NAME = 'opengraph.xyz';
	/** @var string External tool for post-inspection, the url-pattern */
	const EXTERNAL_INSPECTOR = 'https://www.opengraph.xyz/url/%s/';
	/** @var string Admin Slug */
	const ADMIN_SLUG = 'branded-social-images';
	/** @var string Which image to use in admin */
	const ICON = 'icon.svg';
	/** @var string Which image to use in admin menu */
	const ADMIN_ICON = 'admin-icon.svg';
	/** @var string The WordPress text-domain. If you change this, you also must change the filenames of the po and mo files in the 'languages' folder. */
	const TEXT_DOMAIN = 'bsi';
	/** @var string The WordPress query-var variable name. In the rare case there is a conflict, this can be changed, but re-save permalinks after. */
	const QUERY_VAR = 'bsi_img';
	/** @var string Internal value for a special options rendering case. Do not change. */
	const DO_NOT_RENDER = 'do_not_render';
	/** @var string options prefix */
	const DEFAULTS_PREFIX = '_bsi_default_';
	/** @var string meta prefix */
	const OPTION_PREFIX = '_bsi_';

	/** @var int Output width. Cannot remember why this is not a constant... */
	public $width = 1200;
	/** @var int Output height. same deal... */
	public $height = 630;

	/** @var array holds the logo_options */
	public $logo_options;
	/** @var array holds the text_options */
	public $text_options;
	/** @var bool keeps track of existence of an og:image */
	public $page_already_has_og_image = false;
	/** @var bool keeps track of availability of an og:image */
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
			// todo: this code runs too often, should only run on either admin, or when the image endpoint is accessed.

			$this->setup_defaults();
			$image_layers = self::image_fallback_chain(true);
			$this->og_image_available = !!array_filter($image_layers);

			$this->text_options['position'] = get_option(self::DEFAULTS_PREFIX . 'text_position', 'top-left');
			$this->logo_options['position'] = get_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right');

			$id = get_the_ID();
			if ($id) {
				$allowed_meta = array_keys(self::field_list()['meta']);
				$overrule_text_position = get_post_meta($id, self::OPTION_PREFIX . 'text_position', true);
				if (in_array('text_position', $allowed_meta) && $overrule_text_position) {
					$this->text_options['position'] = $overrule_text_position;
				}

				$overrule_logo_enabled = get_post_meta($id, self::OPTION_PREFIX . 'logo_enabled', true);
				if (in_array('logo_enabled', $allowed_meta) && !$overrule_logo_enabled || 'yes' === $overrule_logo_enabled) {
					$this->logo_options['enabled'] = $overrule_logo_enabled;
				}

				$overrule_logo_position = get_post_meta($id, self::OPTION_PREFIX . 'logo_position', true);
				if (in_array('logo_position', $allowed_meta) && $overrule_logo_position) {
					$this->logo_options['position'] = $overrule_logo_position;
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'color', true);
				if (in_array('color', $allowed_meta) && $overrule_color) {
					$this->text_options['color'] = $overrule_color;
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'background_color', true);
				if (in_array('background_color', $allowed_meta) && $overrule_color) {
					$this->text_options['background-color'] = $overrule_color;
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'text_stroke_color', true);
				if (in_array('text_stroke_color', $allowed_meta) && $overrule_color) {
					$this->text_options['text-stroke-color'] = $overrule_color;
				}

				$overrule = get_post_meta($id, self::OPTION_PREFIX . 'text_stroke', true);
				if (in_array('text_stroke', $allowed_meta) && $overrule !== '') {
					$this->text_options['text-stroke'] = intval($overrule);
				}

				$overrule_color = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_color', true);
				if (in_array('text_shadow_color', $allowed_meta) && $overrule_color) {
					$this->text_options['text-shadow-color'] = $overrule_color;
				}

				$overrule_left = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_left', true);
				if (in_array('text_shadow_left', $allowed_meta) && $overrule_left !== '') {
					$this->text_options['text-shadow-left'] = $overrule_left;
				}

				$overrule_top = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_top', true);
				if (in_array('text_shadow_top', $allowed_meta) && $overrule_top !== '') {
					$this->text_options['text-shadow-top'] = $overrule_top;
				}

				$overrule_tsenabled = get_post_meta($id, self::OPTION_PREFIX . 'text_shadow_enabled', true);
				if (in_array('text_shadow_enabled', $allowed_meta) && $overrule_tsenabled === 'on') {
					$this->text_options['text-shadow-color'] = '#555555DD';
					$this->text_options['text-shadow-top'] = 2;
					$this->text_options['text-shadow-left'] = -2;
				}
			}

			$this->expand_text_options();
			$this->expand_logo_options();
		});

		/**
		 * url endpoint, the WordPress way
		 *
		 * pros:
		 * 1. it works on non-standard hosts,
		 * 2. works on nginx hosts
		 * cons:
		 * 1. does not work for custom post-type archives
		 * 2. because it is in essence a page-modifier, WP considers this a page, therefore
		 * - adds a trailing slash
		 * - confusing caching plugins into thinking the content-type should be text/html
		 * 3. the WP construction assumes an /endpoint/value/ set-up, requiring cleanup, see fileter rewrite_rules_array implementation below
		 *
		 * Why this way?
		 *
		 * Because an .htaccess RewriteRule, although improving performance 20-fold, would be web-server-software specific, blog-set-up specific and multi-site aware
		 * which makes it quite impossible to do universally. Unfortunately.
		 *
		 * If you feel adventurous, you can always add it yourself! It should look something like this:
		 *
		 * RewriteRule (.+)/social-image.(jpg|png)/?$ $1/?bsi_img=1 [QSA,L,NC]
		 *
		 * If only for a certain domain, you can add a condition;
		 *
		 * RewriteCond %{HTTP_HOST} yourdomain.com
		 * RewriteRule (.+)/social-image.(jpg|png)/?$ $1/?bsi_img=1 [QSA,L,NC]
		 *
		 * For more information on apache rewrite rules, see
		 * @see https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html
		 */

		add_action('init', function () {
			// does not work in any possible way for Post-Type Archives
			add_rewrite_endpoint(self::output_filename(), EP_PERMALINK | EP_ROOT | EP_PAGES, Plugin::QUERY_VAR);

			if (get_option("bsi_needs_rewrite_rules") || Plugin::output_filename() !== get_option('_bsi_rewrite_rules_based_on')) {
				delete_option("bsi_needs_rewrite_rules");
				global $wp_rewrite;
				update_option("rewrite_rules", false);
				$wp_rewrite->flush_rules(true);
				update_option('_bsi_rewrite_rules_based_on', Plugin::output_filename());
				Plugin::purge_cache();
			}
			add_image_size(Plugin::IMAGE_SIZE_NAME, $this->width, $this->height, true);
			if (Plugin::AA > 1) {
				for ($i = Plugin::AA; $i > 1; $i--) {
					add_image_size(Plugin::IMAGE_SIZE_NAME . "@{$i}x", $this->width * $i, $this->height * $i, true);
				}
			}
		});

		// todo: remove in 1.0.20 release
//		add_action('admin_init', function () {
//			$font_file = get_option(self::DEFAULTS_PREFIX . 'text__font');
//
//			// legacy code follows, todo: investigate removal.
//			if (preg_match('/google:(.+)/', $font_file, $m)) {
//				$defaults = Admin::base_settings();
//				$this->text_options = $defaults['text_options'];
//				$this->text_options['font-file'] = $font_file;
//				$this->text_options['font-family'] = $font_file;
//				$this->expand_text_options();
//				if ($this->text_options['font-file'] && is_file($this->text_options['font-file']) && $this->text_options['font-file'] !== $font_file) { // PROCESSED!
//					update_option(self::DEFAULTS_PREFIX . 'text__font', basename($this->text_options['font-file']));
//					wp_redirect(remove_query_arg(''));
//					exit;
//				}
//			}
//		});

		// this filter is used when a re-save permalink occurs
		add_filter('rewrite_rules_array', function ($rules) {
			$new_rules = [];
			/**
			 * make post-type archives work
			 */
			$pt_archives = [];
			foreach ($rules as $target) {
				if (preg_match('/^index.php\?post_type=([^&%]+)$/', $target, $m)) {
					$pt_archives[$m[1] . '/' . Plugin::output_filename() . '/?$'] = $target . '&' . Plugin::QUERY_VAR . '=1';
				}
			}
			$rules = array_merge($pt_archives, $rules);

			/**
			 * changes the rewrite rules so the endpoint is value-less and more a tag, like 'feed' is for WordPress.
			 */
			foreach ($rules as $source => $target) {
				if (
					preg_match('/' . strtr(self::output_filename(), [
							'.' => '\\.',
							'-' => '\\-'
						]) . '/', $source)
				) {
					$source = explode(self::output_filename(), $source);
					$source = $source[0] . self::output_filename() . '/?$';

					$target = explode(Plugin::QUERY_VAR . '=', $target);
					$target = $target[0] . Plugin::QUERY_VAR . '=1';
				}
				$new_rules[$source] = $target;
			}

			return $new_rules;
		});

		// WordPress will not know what to do with the endpoint urls, and look for a template
		// at this time, we detect the endpoint and push an image to the browser.
		// todo: what to do when we don't have an image??
		add_action('template_redirect', function () {
			if (get_query_var(Plugin::QUERY_VAR)) {
				require_once __DIR__ . '/class.og-image.php';
				$og_image = new Image($this);
				$og_image->serve();
				exit;
			}
		});

		// yes, a second hook on 'wp', but note; this runs absolute last using priority PHP_INT_MAX.
		add_action('wp', [$this, '_init'], PHP_INT_MAX);

		add_action('admin_bar_menu', [static::class, 'admin_bar'], 100);

		add_action('admin_init', function () {
			$this->setup_defaults();
		});

		add_filter('bsi_post_types', [static::class, 'post_types'], ~PHP_INT_MAX, 0);

		/**
		 * Patch the response to WordPress oembed request
		 */
		add_filter('oembed_response_data', function ($data, $post) {
			$id = $post->ID;

			if (self::go_for_id($id)) {
				$url = static::get_og_image_url($id);

				$data['thumbnail_url'] = $url;
				$data['thumbnail_width'] = static::getInstance()->width;
				$data['thumbnail_height'] = static::getInstance()->height;
			}

			return $data;
		}, PHP_INT_MAX, 2);

		/**
		 * Patch SEO by RankMath JD+JSON data
		 */
		add_filter('rank_math/json_ld', function ($data, $RankMath_schema_jsonld) {
			$id = $RankMath_schema_jsonld->post_id;

			if ($id && self::go_for_id($id)) {
				$url = static::get_og_image_url($id);

				$data['primaryImage']['url'] = $url;
				$data['primaryImage']['width'] = static::getInstance()->width;
				$data['primaryImage']['height'] = static::getInstance()->height;
			}

			return $data;

		}, PHP_INT_MAX, 2);
	}

	/**
	 * Test if we want a BSI for this post.
	 * @todo: future version will have to detect archives and categories as well
	 * @param $post_id
	 * @return bool
	 */
	public static function go_for_id($post_id): bool
	{
		$killswitch = get_post_meta($post_id, self::OPTION_PREFIX . 'disabled', true) ?: get_option(self::DEFAULTS_PREFIX . 'disabled', 'off');
		$go = !!self::image_fallback_chain() || get_post_meta($post_id, self::OPTION_PREFIX . 'image', true);
		if ('on' === $killswitch) {
			$go = false;
		}

		return $go;
	}

	/**
	 * Logging function used for debugging.
	 * When called with ?debug=BSI, an image url produces debug output
	 * Because some hosts mess with output buffers and/or reverse proxies, this data is not always visible
	 * so it is stored for viewing in the config panel as well.
	 * @return array
	 */
	public static function log(): array
	{
		static $log, $static;

		if (!$static) {
			$static = [];
			$static[] = '-- active plugins --';
			$plugins = get_option('active_plugins', []);
			foreach ($plugins as $key => $value) {
				$static[] = ($key + 1) . ": $value";
			}
			$static[] = '-- php/wp functions --';
			foreach (['mime_content_type', 'finfo_open', 'wp_check_filetype', 'exec', 'shell_exec', 'passthru', 'system'] as $function) {
				$static[] = "$function: " . (constant(strtoupper($function) .'_EXISTED_BEFORE_PATCH') ? 'exists' : 'does not exist');
			}
			$static[] = '-- php settings --';
			foreach (['memory_limit', 'max_execution_time'] as $setting) {
				$static[] = "$setting: " . ini_get($setting);
			}
			$static[] = '-- end of log --';
		}
		if (!$log) {
			$log = [];
			$log[] = 'Log start: ' . date('r');
			$log[] = 'BSI version: ' . Plugin::get_version();
			$log[] = 'BSI revision date: ' . date('r', filemtime(BSI_PLUGIN_FILE));
			$log[] = "Start memory usage: " . ceil(memory_get_peak_usage() / (1024 * 1024)) . "M";
			$log[] = '-- image generation --';
			$log[] = "BSI Debug log for " . 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . remove_query_arg('debug');
		}
		if (count(func_get_args()) > 0) {
			$item = func_get_arg(0);
			// sanitize
			$item = str_replace(trailingslashit(ABSPATH), 'ABSPATH/', $item);
			$log[] = $item;
		}

		return array_merge($log, ['Peak memory usage: ' . ceil(memory_get_peak_usage() / (1024 * 1024)) . 'M'], $static);
	}

	public static function display_log()
	{
		if (current_user_can(Plugin::get_management_permission())) {
			header("Content-Type: text/plain");
			$log = implode("\n", self::log());
			set_transient(Plugin::OPTION_PREFIX . '_debug_log', $log, 7 * 86400); // keep log for 1 week.
			print $log;
			exit;
		}
	}

	/**
	 * yes, we are mimicking get_plugin_data here because get_plugin_data is not always available while get_file_data is.
	 * we could have statically set the version number, but we could forget to update it.
	 * @return string the version number of the plugin.
	 */
	public static function get_version()
	{
		static $version;
		if (!$version) {
			$data = get_file_data(BSI_PLUGIN_FILE, ['Version' => 'Version'], 'plugin');
			$version = $data['Version'];
		}
		return $version;
	}

	/**
	 * Retrieves Media-library-item data with a certain size
	 * A mashup of wp_get_attachment_image_src and wp_get_attachment_metadata
	 * @param $image_id
	 * @param string $size
	 * @return array|false
	 */
	public static function wp_get_attachment_image_data($image_id, string $size)
	{
		$data = wp_get_attachment_image_src($image_id, $size);
		$meta = wp_get_attachment_metadata($image_id);
		if (is_array($data) && is_array($meta)) {
			$upl = wp_upload_dir();
			$upl = $upl['basedir'];
			// path to the base image
			$file = $base = trailingslashit($upl) . $meta['file'];
			Plugin::log(sprintf(' Base image for #%d: %s', $image_id, $base));
			if (!empty($meta['sizes'][$size]['path'])) {
				// path to the resized image, if it exists
				$file = trailingslashit($upl) . $meta['sizes'][$size]['path'];
				Plugin::log(sprintf(' Using \'path\' entry: %s with size-name %s', $file, $size));
			}
			elseif (!empty($meta['sizes'][$size]['file'])) {
				// only a new filename is listed, use directory of base.
				$file = dirname($base) . '/' . $meta['sizes'][$size]['file'];
				Plugin::log(sprintf(' Using \'file\' entry: %s with size-name %s', $file, $size));
			}
			// check existence of file.
			if (is_file($file)) {
				$data[4] = $file;
			}
			else {
				Plugin::log(' Selected size-specific file does not exist. Please run a thumbnail rebuild.');
				if (is_file($base)) {
					$data[4] = $base;
				}
				else {
					Plugin::log(' Base image also does not exist. THIS IS A PROBLEM!');
				}
			}
		}

		return $data;
	}

	public static function get_purgable_cache($type = 'all')
	{
		$filter = 'is_file';
		switch ($type) {
			case 'directories':
				$ext = '';
				$filter = 'is_dir';
				break;
			case 'files':
				$ext = '/*';
				break;
			case 'images':
				$ext = '/*.{png,jpg,webp}';
				break;
			case 'locks':
				$ext = '/*.lock';
				break;
			case 'tmp':
				$ext = '/*.tmp';
				break;
			case 'all':
			default:
				$list = array_merge(self::get_purgable_cache('files'), self::get_purgable_cache('directories'), array_map('dirname', self::get_purgable_cache('directories')));
				// sort files first, then directory depth
				uasort($list, function ($item1, $item2) {
					if (is_file($item1)) {
						return -1;
					}
					if (is_dir($item1)) {
						return strnatcmp(count(explode('/', $item1)), count(explode('/', $item2)));
					}
					return 0;
				});
				// now the items are sorted, but the order in the array is wrong ?!?!
				$sorted = [];
				for ($i = 0; $i < count($list); $i++) {
					$sorted[] = $list[$i];
				}
				return array_values(array_unique($sorted));
		}
		$cache = glob(self::getInstance()->storage() . '/*/*' . $ext, GLOB_BRACE);

		return array_filter($cache, $filter);
	}

	public static function scrape_title($post_id)
	{
		return self::scrape_title_data($post_id)[1];
	}

	public static function scrape_code($post_id)
	{
		return self::scrape_title_data($post_id)[0];
	}

	/**
	 * Scrape the title from a rendered page.
	 * This really is an eye-sore and we will replace it with a title-builder in the future.
	 * @param $post_id
	 * @return array|mixed
	 */
	public static function scrape_title_data($post_id)
	{
		static $previous = [];
		if (!empty($previous[$post_id])) {
			return $previous[$post_id];
		}

		$title = $page = '';
		$code = 0;
		try {
			$result = wp_remote_get(get_permalink($post_id), [
				'httpversion' => '1.1',
				'user-agent' => $_SERVER["HTTP_USER_AGENT"],
				'referer' => remove_query_arg('asd')
			]);
			$code = wp_remote_retrieve_response_code($result);
			if (intval($code) === 200) {
				$page = wp_remote_retrieve_body($result);
				// limit size of string to work with
				list($page) = explode('</head>', $page);
				// remove line endings for better scraping
				$page = str_replace(["\n", "\r"], '', $page);
			}
		} catch (Exception $e) {
			$page = '';
		}

		if ($page && (false !== strpos($page, 'og:title'))) {
			if (preg_match('/og:title.+content=([\'"])(.+)\1([ \/>])/mU', $page, $m)) {
				$title = trim($m[2], ' />' . $m[1]);
			}

		}
		if ($page && !$title && (false !== strpos($page, '<title'))) {
			if (preg_match('/<title[^>]*>(.+)<\/title>/mU', $page, $m)) {
				$title = trim($m[1]);
			}
		}

		return $previous[$post_id] = [$code, html_entity_decode($title)];
	}

	public static function title_format($post_id = null, $no_title = false)
	{
		// to return, in case of no post
		$format = get_option(Plugin::OPTION_PREFIX . 'title_format', '{title} - {blogname}');
		if ($post_id) { // post asked, build the full title
			$tokens = apply_filters('bsi_title_tokens', [
				'{title}' => $no_title ? '{title}' : get_the_title($post_id),
				'{blogname}' => get_bloginfo('name')
			]);

			return strtr($format, $tokens);
		}

		return $format;
	}

	public static function output_filename()
	{
		$fallback_format = 'jpg';
		$output_format = Plugin::setting('output_format', $fallback_format);
		if (is_array($output_format)) {
			$fallback_format = $output_format[1];
			$output_format = $output_format[0];
		}
		if (!in_array($fallback_format, ['png', 'jpg', /* 'webp' */])) {
			$fallback_format = 'jpg';
		}
		if ('webp' === $output_format && !function_exists('imagewebp')) {
			$output_format = $fallback_format;
		}
		if (!in_array($output_format, ['png', 'jpg', 'webp'])) {
			$output_format = $fallback_format;
		}

		return self::BSI_IMAGE_NAME . '.' . $output_format;
	}

	/**
	 * wrapper for unlink function for proper error handling or at least error-prevention
	 * @see unlink
	 *
	 * @param $path
	 * @return bool
	 */
	private static function unlink($path): bool
	{
		try {
			$result = unlink($path);
		} catch (Exception $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * wrapper for rmdir function for proper error handling or at least error-prevention
	 * also clears .DS_Store items (macOS sucks sometimes) before attempting rmdir.
	 * @see rmdir
	 *
	 * @param $path
	 * @return bool
	 */
	private static function rmdir($path): bool
	{
		if (is_file("$path/.DS_Store")) {
			self::unlink("$path/.DS_Store");
		}
		try {
			$result = rmdir($path);
		} catch (Exception $e) {
			$result = false;
		}

		return $result;
	}

	/**
	 * Clear the BSI cache.
	 * @return bool
	 */
	public static function purge_cache(): bool
	{
		$purgable = Plugin::get_purgable_cache();
		// protection!
		$base = trailingslashit(Plugin::getInstance()->storage());
		$result = true;
		foreach ($purgable as $item) {
			if (false === strpos($item, $base)) {
				continue;
			}

			try {
				if (is_file($item)) {
					if (!self::unlink($item)) {
						$result = false;
					}
				}
				if (is_dir($item)) {
					if (!self::rmdir($item)) {
						$result = false;
					}
				}
			} catch (\Exception $e) {

			}
		}

		return $result;
	}

	/**
	 * @param false $destroy_buffer if false; do a nice flush. if true; previous output is destroyed.
	 */
	public static function no_output_buffers($destroy_buffer = false)
	{
		if (ob_get_level()) {
			$list = ob_list_handlers();
			foreach ($list as $item) {
				Plugin::log('Output buffer detected: ' . (is_string($item) ? $item : serialize($item)));
			}
			while (ob_get_level()) {
				$destroy_buffer ? ob_end_clean() : ob_end_flush();
			}
			Plugin::log('All buffers are ' . ($destroy_buffer ? 'cleaned' : 'flushed'));
		}
	}

	public function _init()
	{
		$id = get_the_ID();
		if (!is_admin() && $id || is_home() || is_archive()) {
			$killswitch = get_post_meta($id, self::OPTION_PREFIX . 'disabled', true) ?: get_option(self::DEFAULTS_PREFIX . 'disabled', 'off');
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
				add_filter('rank_math/opengraph/facebook/image_secure_url', [
					static::class,
					'overrule_og_image'
				], PHP_INT_MAX);
				add_filter('rank_math/opengraph/facebook/og_image', [
					static::class,
					'overrule_og_image'
				], PHP_INT_MAX);
				add_filter('rank_math/opengraph/facebook/og_image_secure_url', [
					static::class,
					'overrule_og_image'
				], PHP_INT_MAX);
				add_filter('rank_math/opengraph/twitter/twitter_image', [
					static::class,
					'overrule_og_image'
				], PHP_INT_MAX);
				add_filter('rank_math/opengraph/facebook/og_image_type', [
					static::class,
					'overrule_og_type'
				], PHP_INT_MAX);
				add_filter('rank_math/opengraph/facebook/og_image_width', function () {
					return static::getInstance()->width;
				}, PHP_INT_MAX);
				add_filter('rank_math/opengraph/facebook/og_image_height', function () {
					return static::getInstance()->height;
				}, PHP_INT_MAX);

				// overrule Yoast SEO
				add_filter('wpseo_opengraph_image', [static::class, 'overrule_og_image'], PHP_INT_MAX);
				add_filter('wpseo_twitter_image', [static::class, 'overrule_og_image'], PHP_INT_MAX);

				// this is a very intrusive way, but Yoast does not allow overruling the
				// image dimensions.
				// @todo: investigate the option of always doing it this way. I wanted to prevent it, bus since we cannot
				// @todo: perhaps we should always just kill all og:image data from the head and add it ourselves.
				add_action('wpseo_head', [static::class, 'patch_wpseo_head'], ~PHP_INT_MAX);
				add_action('wpseo_head', [static::class, 'patch_wpseo_head'], PHP_INT_MAX);

				add_filter('wpseo_schema_main_image', function ($graph_piece) use ($id) {
					$url = static::get_og_image_url($id);
					$graph_piece['url'] = $graph_piece['contentUrl'] = $url;
					$graph_piece['width'] = $this->width;
					$graph_piece['height'] = $this->height;

					return $graph_piece;
				}, 11);

				// overrule WordPress JetPack recently acquired SocialImageGenerator, because, hey, we were here first!
				add_filter('sig_image_url', function ($url, $post_id) {
					return static::get_og_image_url($post_id);
				}, PHP_INT_MAX, 2);

				// if an overrule did not take, we need to define our own.
				add_action('wp_head', [static::class, 'late_head'], PHP_INT_MAX);
			}
		}
	}


	public function setup_defaults()
	{
		$defaults = Admin::base_settings();
		$this->logo_options = $defaults['logo_options'];
		$this->logo_options['position'] = get_option(self::DEFAULTS_PREFIX . 'logo_position', 'top-left');

		// text options
		$this->text_options = $defaults['text_options'];
		$font_file = get_option(self::DEFAULTS_PREFIX . 'text__font', 'Roboto-Bold');

		$this->text_options['font-file'] = $font_file;

		$this->text_options['position'] = get_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-left');
		$this->text_options['color'] = get_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF');
		$this->text_options['background-color'] = get_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666');
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

	public static function post_types(): array
	{
		$list = get_post_types(['public' => true]);

		return array_values($list);
	}

	public function validate_text_options()
	{
		$all_possible_options = Admin::base_settings();
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
		$all_possible_options = Admin::base_settings();
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
			$this->text_options['font-weight'] = $this->evaluate_font_weight($this->text_options['font-weight']);
			$this->text_options['font-style'] = $this->evaluate_font_style($this->text_options['font-style']);

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
		list($valign, $halign) = $this->evaluate_positions($this->text_options['top'], $this->text_options['right'], $this->text_options['bottom'], $this->text_options['left']);
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
			$weight = $translate[strtolower($weight)] ?? $default;
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

	public function font_filename($font_family, $font_weight, $font_style): string
	{
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';

			return $m[1] /* fontname */ . '-w' . $font_weight . ($italic ? '-' . $italic : '') . '.ttf';
		}

		// don't know what to do with any other
		return '';
	}

	public function storage(): string
	{
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/' . Plugin::STORAGE;
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		self::setError('storage', null);
		if (!is_dir($dir)) {
			self::setError('storage', __('Could not create the storage directory in the uploads folder.', Plugin::TEXT_DOMAIN) . ' ' . __('In a WordPress site the uploads folder should always be writable.', Plugin::TEXT_DOMAIN) . ' ' . __('Please fix this.', Plugin::TEXT_DOMAIN) . ' ' . __('This error will disappear once the problem has been corrected.', Plugin::TEXT_DOMAIN));
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
			self::setError('font-family', __('Don\'t know where to get this font.', Plugin::TEXT_DOMAIN) . ' ' . __('Sorry.', Plugin::TEXT_DOMAIN));

			return false;
		}
		if (is_file($this->storage() . '/' . $font_filename)) {
			return $font_filename;
		}
		if (preg_match('/google:(.+)/', $font_family, $m)) {
			$italic = $font_style == 'italic' ? 'italic' : '';
			$font_css = wp_remote_retrieve_body(wp_remote_get('https://fonts.googleapis.com/css?family=' . urlencode($m[1]) . ':' . $font_weight . $italic, ['useragent' => ' ']));

			if (!$font_css) {
				self::setError('font-family', __('Could not download font from Google Fonts.', Plugin::TEXT_DOMAIN) . ' ' . __('Please download yourself and upload here.', Plugin::TEXT_DOMAIN));

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
				self::setError('font-family', __('This Google Fonts does not offer a TTF or OTF file.', Plugin::TEXT_DOMAIN) . ' ' . __('Sorry, cannot continue at this time.', Plugin::TEXT_DOMAIN));

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
		if ( ! empty( $top ) && '%' === substr( $top, - 1 ) ) {
			$top = intval( floor( intval( $top ) / 100 * $this->height ) );
		}
		if ( ! empty( $bottom ) && '%' === substr( $bottom, - 1 ) ) {
			$bottom = intval( ceil( intval( $bottom ) / 100 * $this->height ) );
		}
	}

	private function evaluate_horizontal(&$left, &$right)
	{
		if ( ! empty( $left ) && '%' === substr( $left, - 1 ) ) {
			$left = intval( floor( intval( $left ) / 100 * $this->width ) );
		}
		if ( ! empty( $right ) && '%' === substr( $right, - 1 ) ) {
			$right = intval( ceil( intval( $right ) / 100 * $this->width ) );
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
		list($valign, $halign) = $this->evaluate_positions($this->logo_options['top'], $this->logo_options['right'], $this->logo_options['bottom'], $this->logo_options['left']);

		$this->logo_options['valign'] = $valign;
		$this->logo_options['halign'] = $halign;

		// size w and h are bounding box!
		$this->logo_options['size'] = intval($this->logo_options['size']);
		$this->logo_options['size'] = min(Plugin::MAX_LOGO_SCALE, $this->logo_options['size']);
		$this->logo_options['size'] = max(Plugin::MIN_LOGO_SCALE, $this->logo_options['size']);
		$this->logo_options['w'] = $this->logo_options['size'] / 100 * $sw;
		$this->logo_options['h'] = $this->logo_options['size'] / 100 * $sh;
		$this->logo_options['size'] .= '%';

		// resolve aspect issues
		// -> this makes bounding box actual image size
		$scale = min($this->logo_options['w'] / $sw, $this->logo_options['h'] / $sh);
		$this->logo_options['w'] = $sw * $scale;
		$this->logo_options['h'] = $sh * $scale;
	}

	public function evaluate_positions(&$top, &$right, &$bottom, &$left)
	{
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

		return [$valign, $halign];
	}

	public static function late_head()
	{
		if (!self::getInstance()->og_image_available) {
			return;
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

	public static function overrule_og_image(): string
	{
		// ignore twitter image; we want to overrule, but only skip our late head when the opengraph image is set
		if (current_action() !== 'wpseo_twitter_image' && current_action() !== 'rank_math/opengraph/twitter/twitter_image') {
			self::getInstance()->page_already_has_og_image = true;
		}

		// this construction is to please WPML;
		// *_query_arg return domain-less root-based urls (/language/page/path/)
		// get_bloginfo('url') returns https://somesite.com/language/
		// we could use site_url, but this will be problematic with multi-site installations...
		$base_url = get_bloginfo('url');
		// from https://somesite.com/language/, keep only the base url, as the rest is included in the result from 'remove_query_arg'
		$base_url = parse_url($base_url, PHP_URL_SCHEME) . '://' . parse_url($base_url, PHP_URL_HOST); // no trailing slash

		$og_url = trailingslashit($base_url . remove_query_arg(array_keys(!empty($_GET) ? $_GET : ['asd' => 1]))) . self::output_filename() . '/'; // yes, slash, WP will add it with a redirect anyway

		/**
		 * @since 1.0.20 allows for final filtering of the output OG:Image url.
		 */
		return apply_filters('bsi_image_url', $og_url);
	}

	public static function overrule_og_type(): string
	{
		$ext = explode('.', self::output_filename());
		$ext = end($ext);

		return 'image/' . $ext;
	}

	public static function get_og_image_url($post_id)
	{
		return get_permalink($post_id) ? get_permalink($post_id) . self::output_filename() . '/' : false;
	}

	public static function patch_wpseo_head()
	{
		static $step;
		if (!$step) {
			$step = 0;
		}
		$step++;

		switch ($step) {
			case 1:
				ob_start();
				break;
			case 2:
				$wpseo_head = ob_get_clean();
				if (preg_match('@/' . Plugin::output_filename() . '/@', $wpseo_head)) {
					$wpseo_head = preg_replace('/og:image:width" content="([0-9]+)"/', 'og:image:width" content="' . Plugin::getInstance()->width . '"', $wpseo_head);
					$wpseo_head = preg_replace('/og:image:height" content="([0-9]+)"/', 'og:image:height" content="' . Plugin::getInstance()->height . '"', $wpseo_head);
				}
				print $wpseo_head;
		}
	}

	public static function init()
	{
		$instance = self::getInstance();
		if (is_admin()) {
			$admin = Admin::getInstance();
			$admin->storage = $instance->storage();
		}

		load_plugin_textdomain(Plugin::TEXT_DOMAIN, false, basename(dirname(__DIR__)) . '/languages');
	}

	/**
	 * EXPERIMENTAL
	 *
	 * @param $source
	 *
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
			$target = "$source.temp.png";
			$command = "$bin/dwebp \"$source\" -o \"$target\"";
			ob_start();
			try {
				print $command;
				exec($command);
			} catch (Exception $e) {

			}
			ob_get_clean();
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
		if (function_exists('exec') && !file_exists("$bin/dwebp")) {
			// can we fake support?
			ob_start();
			try {
				exec($bin . '/download.sh');
			} catch (Exception $e) {

			}
			ob_end_clean();
		}
		if (file_exists("$bin/dwebp")) {
			// downloaded and ran conversion tool successfully
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
			// PATCH THE DATA
			$font['font_family'] = $font['font_name'];
			unset($font['admin'], $font['gd']);

			return $font;
		}, self::font_rendering_tweaks(false, false));
	}

	public static function font_rendering_tweaks($write_json = false, $read_disk = true): array
	{
		$tweaks = [
			/** letter-spacing: px, line-height: factor */
			'Anton' => [
				'font_name' => 'Anton',
				'font_weight' => 400,
				'admin' => ['letter-spacing' => '-0.32px'],
				'gd' => ['line-height' => 1]
			],
			'Courgette' => [
				'font_name' => 'Courgette',
				'font_weight' => 400,
				'admin' => ['letter-spacing' => '-0.32px'],
				'gd' => ['line-height' => .86]
			],
			'JosefinSans-Bold' => [
				'font_name' => 'Josefin Sans',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '-0.4px'],
				'gd' => ['line-height' => .96]
			],
			'Merriweather-Bold' => [
				'font_name' => 'Merriweather',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => .86]
			],
			'OpenSans-Bold' => [
				'font_name' => 'Open Sans',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => .95, 'text-area-width' => .96]
			],
			'Oswald-Bold' => [
				'font_name' => 'Oswald',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => .92, 'text-area-width' => .96]
			],
			'PTSans-Bold' => [
				'font_name' => 'PT Sans',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => 1.03]
			],
			'Roboto-Bold' => [
				'font_name' => 'Roboto',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => .97]
			],
			'WorkSans-Bold' => [
				'font_name' => 'Work Sans',
				'font_weight' => 700,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => 1]
			],
			'AkayaKanadaka' => [
				'font_name' => 'Akaya Kanadaka',
				'font_weight' => 400,
				'admin' => ['letter-spacing' => '0px'],
				'gd' => ['line-height' => .98]
			],
		];

		if ($read_disk) {

			$json_files = glob(self::getInstance()->storage() . '/*.json');
			foreach ($json_files as $file) {
				$font = basename($file, '.json');
				if (empty($tweaks[$font])) {
					$tweaks[$font] = [];
				}
				$tweaks[$font] = array_merge($tweaks[$font], json_decode(file_get_contents($file), true));
			}

		}

		if ($write_json) {
			foreach ($tweaks as $font => $data) {
				self::getInstance()->file_put_contents(self::getInstance()->storage() . '/' . $font . '.json', json_encode($data, JSON_PRETTY_PRINT));
			}
			$DOCUMENTATION = <<< EODOC
Files in here:
- *.otf, *.ttf:             These are the fonts :) You can place any TTF or OTF font here.
                            Just make sure you have the proper license for the font(s).
                            We only put Google Fonts here for you, which are licensed for web.
- *.json:                   for tweaking the rendering of the font in CSS and in image generation, see
                            below for more details.
- what-are-these-files.txt: well, that's this file

Info on the .json files;
Some fonts render different (some even VERY different) in GD2 than they do in HTML/CSS.
Sample content:

{
    "font_name": "Open Sans",
    "admin": {
        "letter-spacing": "0px"
    },
    "gd": {
        "line-height": 0.95,
        "text-area-width": .96
    }
}

Here are defined; the readable font-name. Useful when the font-name has a space.

The admin sub-tree can contain:
- letter-spacing:  a CSS compatible value to tweak the rendering of the font in the
                   admin interface.
Need more? Let us know. See the WordPress BSI Settings panel for details on contacting us.

The gd sub-tree can contain:
- line-height:     a FACTOR (absent or  a value of 1 means "no change") to tweak the
                   line-height as calculated by GD.
- text-area-width: a factor (again) to tweak the width of the text-area.
                   This is useful when fonts render slightly to narrow or to wide.
Need more? Let us know. See the WordPress BSI Settings panel for details on contacting us.
EODOC;
			// force Windows line endings, because I KNOW that most users that need this documentation are not Linux, Unix or macOS users.
			$DOCUMENTATION = str_replace("\n", "\r\n", str_replace("\r", "", $DOCUMENTATION));
			self::getInstance()->file_put_contents(self::getInstance()->storage() . '/what-are-these-files.txt', $DOCUMENTATION);
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

	/**
	 * @function used to combat injection/request forgery
	 */
	public static function get_valid_POST_keys($section = false): array
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

	public static function field_list(): array
	{
		$image_comment = __('The following process is used to determine the OG:Image (in order of importance)', Plugin::TEXT_DOMAIN) . ':
<ol><li>' . __('Branded Social Image on page/post', Plugin::TEXT_DOMAIN) . '</li>';
		if (defined('WPSEO_VERSION')) {
			$image_comment .= '<li>' . __('Yoast Social image on page/post', Plugin::TEXT_DOMAIN) . '</li>';
		}
		$image_comment .= '<li>' . __('Featured image on page/post (when checked in general settings)', Plugin::TEXT_DOMAIN) . '</li>';
		$image_comment .= '<li>' . __('Fallback Branded Social image in general settings', Plugin::TEXT_DOMAIN) . '</li></ol>';

		$options = [
			'admin' => [
				'disabled' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'checkbox',
					'label' => __('Select to disable the plugin Branded Social Images by default.', Plugin::TEXT_DOMAIN),
					'default' => 'off'
				],
				'menu_location' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'select',
					'label' => __('Where does Branded Social Images live in the menu?', Plugin::TEXT_DOMAIN),
					'default' => 'main',
					'options' => [
						'main' => __('At the main level', Plugin::TEXT_DOMAIN),
						'options' => __('In the Settings sub-menu', Plugin::TEXT_DOMAIN),
						'media' => __('In the Media sub-menu', Plugin::TEXT_DOMAIN)
					]
				],
				'meta_location' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'select',
					'label' => __('Branded Social Images meta-box location', Plugin::TEXT_DOMAIN),
					'default' => 'advanced',
					'options' => [
						'advanced' => __('Below the content editor', Plugin::TEXT_DOMAIN),
						'side' => __('In the sidebar', Plugin::TEXT_DOMAIN)
					]
				],

				'image' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'image',
					'types' => 'image/png,image/jpeg,image/webp',
					'class' => '-no-remove',
					'label' => __('Fallback OG:Image.', Plugin::TEXT_DOMAIN),
					'comment' => __('Used for any page/post that has no OG image selected.', Plugin::TEXT_DOMAIN) . ' ' . __('You can use JPEG and PNG.', Plugin::TEXT_DOMAIN) . ' ' . __('Recommended size: 1200x630 pixels.', Plugin::TEXT_DOMAIN)
				],
				'image_use_thumbnail' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'checkbox',
					'label' => __('Use the WordPress Featured image.', Plugin::TEXT_DOMAIN),
					'default' => 'on',
					'info-icon' => 'dashicons-info',
					'info' => $image_comment
				],

				'image_logo' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'image',
					'types' => 'image/gif,image/png',
					'label' => __('Your logo', Plugin::TEXT_DOMAIN),
					'comment' => __('Image should be approximately 600 pixels wide/high.', Plugin::TEXT_DOMAIN) . ' ' . __('Use a transparent PNG for best results.', Plugin::TEXT_DOMAIN)
				],
				'logo_position' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'radios',
					'class' => 'position-grid',
					'options' => self::position_grid(),
					'label' => __('Default logo position', Plugin::TEXT_DOMAIN),
					'default' => 'top-left'
				],
				'image_logo_size' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'slider',
					'class' => 'single-slider',
					'label' => __('Logo-scale (%)', Plugin::TEXT_DOMAIN),
					'comment' => '',
					'default' => '100',
					'min' => Plugin::MIN_LOGO_SCALE,
					'max' => Plugin::MAX_LOGO_SCALE,
					'step' => 1
				],

				'text' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'class' => 'hidden editable-target',
					'type' => 'textarea',
					'label' => __('The text to overlay if no other text or title can be found.', Plugin::TEXT_DOMAIN),
					'comment' => __('This should be a generic text that is applicable to the entire website.', Plugin::TEXT_DOMAIN),
					'default' => Plugin::getInstance()->dummy_data('text')
				],
				'text__font' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'select',
					'label' => __('Select a font', Plugin::TEXT_DOMAIN),
					'options' => self::get_font_list(),
					'default' => 'Roboto-Bold'
				],
				'text__ttf_upload' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'file',
					'types' => 'font/ttf,font/otf',
					'label' => __('Font upload', Plugin::TEXT_DOMAIN),
					'upload' => __('Upload .ttf/.otf file', Plugin::TEXT_DOMAIN),
					'info-icon' => 'dashicons-info',
					'info' => __('Custom font must be a .ttf or .otf file.', Plugin::TEXT_DOMAIN) . ' ' . __('You\'re responsible for the proper permissions and usage rights of the font.', Plugin::TEXT_DOMAIN)
				],
//				'text__google_download' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Google Font Download', 'comment' => 'Enter a Google font name as it is listed on fonts.google.com'],

				'text_position' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'radios',
					'class' => 'position-grid',
					'label' => __('Text position', Plugin::TEXT_DOMAIN),
					'options' => self::position_grid(),
					'default' => 'bottom-left'
				],
				'color' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'color',
					'attributes' => 'rgba',
					'label' => __('Default Text color', Plugin::TEXT_DOMAIN),
					'default' => '#FFFFFFFF'
				],
				'text__font_size' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'slider',
					'class' => 'single-slider',
					'label' => __('Font-size (px)', Plugin::TEXT_DOMAIN),
					'comment' => '',
					'default' => Plugin::DEF_FONT_SIZE,
					'min' => Plugin::MIN_FONT_SIZE,
					'max' => Plugin::MAX_FONT_SIZE,
					'step' => 1
				],
				'background_color' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'color',
					'attributes' => 'rgba',
					'label' => __('Text background color', Plugin::TEXT_DOMAIN),
					'default' => '#66666666'
				],
				'background_enabled' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'checkbox',
					'label' => __('Use text background', Plugin::TEXT_DOMAIN),
					'value' => 'on',
					'default' => 'on'
				],

				'text_stroke_color' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'text',
					'color',
					'attributes' => 'rgba',
					'label' => __('Stroke color', Plugin::TEXT_DOMAIN),
					'default' => '#00000000'
				],
				'text_stroke' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'text',
					'label' => __('Default stroke width', Plugin::TEXT_DOMAIN),
					'default' => 0
				],

				'text_shadow_color' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'color',
					'label' => __('Default Text shadow color', Plugin::TEXT_DOMAIN),
					'#00000000'
				],
				'text_shadow_top' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'text',
					'label' => __('Shadow offset - vertical.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to top, Positive numbers to bottom.', Plugin::TEXT_DOMAIN),
					'default' => '-2'
				],
				'text_shadow_left' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'text',
					'label' => __('Shadow offset - horizontal.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to left, Positive numbers to right.', Plugin::TEXT_DOMAIN),
					'default' => '2'
				],
				'text_shadow_enabled' => [
					'namespace' => self::DEFAULTS_PREFIX,
					'type' => 'checkbox',
					'label' => __('Use a text shadow', Plugin::TEXT_DOMAIN),
					'value' => 'on',
					'default' => 'off'
				],
			],
			'meta' => [
				'disabled' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'checkbox',
					'label' => __('Select to disable the plugin Branded Social Images for this post.', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'disabled', 'off'),
					'comment' => '<div class="disabled-notice">' . __('The plugin Branded Social Images is disabled for this post.', Plugin::TEXT_DOMAIN) . '</div>'
				],
				'text_enabled' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'checkbox',
					'label' => __('Deselect if you do not wish text on this image.', Plugin::TEXT_DOMAIN),
					'default' => 'yes',
					'value' => 'yes'
				],

				'image' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'image',
					'types' => 'image/png,image/jpeg,image/webp',
					'label' => __('You can upload/select a specific Social Image here', Plugin::TEXT_DOMAIN),
					'comment' => __('You can use JPEG and PNG.', Plugin::TEXT_DOMAIN) . ' ' . __('Recommended size: 1200x630 pixels.', Plugin::TEXT_DOMAIN),
					'info-icon' => 'dashicons-info',
					'info' => $image_comment
				],

				'text' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'textarea',
					'class' => 'hidden editable-target',
					'label' => __('Text on image', Plugin::TEXT_DOMAIN)
				],
				'color' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'color',
					'attributes' => 'rgba',
					'label' => __('Text color', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF')
				],
				'text_position' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'radios',
					'class' => 'position-grid',
					'label' => __('Text position', Plugin::TEXT_DOMAIN),
					'options' => self::position_grid(),
					'default' => get_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-left')
				],

				'background_color' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'color',
					'attributes' => 'rgba',
					'label' => __('Text background color', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666')
				],

				'text_stroke_color' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'color',
					'attributes' => 'rgba',
					'label' => __('Stroke color', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000')
				],
				'text_stroke' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'text',
					'label' => __('Default stroke width', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'text_stroke', '0')
				],

				'text_shadow_color' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'color',
					'label' => __('Text shadow color', Plugin::TEXT_DOMAIN),
					get_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000')
				],
				'text_shadow_top' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'text',
					'label' => __('Shadow offset - vertical.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to top, Positive numbers to bottom.', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2')
				],
				'text_shadow_left' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'text',
					'label' => __('Shadow offset - horizontal.', Plugin::TEXT_DOMAIN) . ' ' . __('Negative numbers to left, Positive numbers to right.', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_left', '2')
				],
				'text_shadow_enabled' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'checkbox',
					'label' => __('Use a text shadow', Plugin::TEXT_DOMAIN),
					'comment' => __('Will improve readability of light text on light background.', Plugin::TEXT_DOMAIN),
					'value' => 'on',
					'default' => get_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off')
				],

				'logo_enabled' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'checkbox',
					'label' => __('Use a logo on this image?', Plugin::TEXT_DOMAIN),
					'default' => 'yes',
					'comment' => __('Uncheck if you do not wish a logo on this image, or choose a position below.', Plugin::TEXT_DOMAIN)
				],
				'logo_position' => [
					'namespace' => self::OPTION_PREFIX,
					'type' => 'radios',
					'label' => __('Logo position', Plugin::TEXT_DOMAIN),
					'class' => 'position-grid',
					'options' => self::position_grid(),
					'default' => get_option(self::DEFAULTS_PREFIX . 'logo_position', 'top-left')
				],

				'image_logo' => [
					'namespace' => self::DO_NOT_RENDER,
					'type' => 'image',
					'types' => 'image/gif,image/png',
					'label' => __('Your logo', Plugin::TEXT_DOMAIN),
					'comment' => __('Image should be approximately 600 pixels wide/high.', Plugin::TEXT_DOMAIN) . ' ' . __('Use a transparent PNG for best results.', Plugin::TEXT_DOMAIN),
					'default' => get_option(self::OPTION_PREFIX . 'image_logo')
				],
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
			unset($options['meta']['logo_enabled']);
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

		/**
		 * Allow extra settings by add-ons
		 *
		 * @since 1.0.18
		 *
		 * @param array  $settings     Two-level array, yourGroup => [ yourSettings ]
		 * @see Plugin::field_list for examples.
		 */
		foreach (apply_filters('bsi_editor_fields', []) as $group => $fields) {
			// no, you are not allowed to override existing groups.
			if ($group === 'admin' || $group === 'meta') {
				continue;
			}
			$options[ $group ] = $fields;
		}

		return $options;
	}

	public static function text_fallback_chain(): array
	{
		static $chain; // prevent double work
		if ($chain) {
			return $chain;
		}

		$post_id = get_the_ID();
		$new_post = 'auto-draft' == get_post_status($post_id);

		$layers = [];

		$title = '';
		if ($post_id) {
			if (Plugin::setting('use_bare_post_title')) {
				$title = apply_filters('the_title', get_the_title($post_id), $post_id);
				if ($title) {
					$layers['wordpress'] = $title;
				}
			}

			if (!$title) {
				$title = Plugin::scrape_title($post_id);
				if ($title) {
					$layers['scraped'] = $title;
				}
			}
			if (!$title) { // no text from scraping, build it
				$title = Plugin::title_format($post_id, $new_post);
				if ($title) {
					$layers['by-format'] = $title;
				}
			}

			$layers['default'] = get_option(self::DEFAULTS_PREFIX . 'text', esc_attr(Plugin::getInstance()->dummy_data('text')));
		}

		return $chain = array_filter($layers);
	}

	public static function image_fallback_chain($with_post = false): array
	{
		if (!get_the_ID() && !is_home() && !is_archive()) {
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
				$layer = is_array($image) && !empty($image[0]) ? $image[0] : false;
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
		global $pagenow;
		if (!is_admin() || $pagenow == 'post.php' || $pagenow == 'post-new.php') {
			if (!is_admin()) {
				$permalink = is_single() ? get_permalink(get_the_ID()) : remove_query_arg('');
			}
			else {
				$permalink = get_permalink(get_the_ID());
			}
			if (!parse_url($permalink, PHP_URL_HOST)) {
				// somebody messed with the permalinks!
				$permalink = trailingslashit(get_home_url()) . ltrim(parse_url($permalink, PHP_URL_PATH), '/');
			}

			if (
				defined('BSI_SHOW_ADMIN_BAR_IMAGE_LINK') &&
				true === BSI_SHOW_ADMIN_BAR_IMAGE_LINK && array_filter(Plugin::image_fallback_chain(true))
			) {
				$args = array(
					'id' => self::ADMIN_SLUG . '-view',
					'title' => __('View Social Image', Plugin::TEXT_DOMAIN),
					'href' => $permalink . Plugin::output_filename() . '/',
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

			$args['href'] = sprintf($args['href'], urlencode($permalink));
			$admin_bar->add_node($args);

			add_action('wp_footer', [static::class, 'admin_bar_icon_style'], PHP_INT_MAX);
			add_action('admin_footer', [static::class, 'admin_bar_icon_style'], PHP_INT_MAX);
		}
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

			return str_replace('<path', '<path fill="currentColor"', $icon);
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
	 *
	 * @param $setting string can be one of ...
	 * $setting = use_bare_post_title, filter = bsi_settings_use_bare_post_title, expects true or false
	 *                                 with true, the WordPress title is used as default
	 *                                 with false, the default title is scraped from the HTML and will therefore be
	 *                                             influenced by plugins like Yoast SEO. This is standard behavior.
	 * $setting = png_compression_level, filter = bsi_settings_png_compression_level, expects number 0 - 9,
	 *                                 0 = no compression, 9 = highest compression, default = 2
	 *         WARNING                 If you change the format, you must flush all page-caches, flush the BSI Image
	 *                                 cache and re-save permalinks!  THIS IS YOUR OWN RESPONSIBILITY.
	 *
	 * @return mixed|void
	 */
	public static function setting($setting, $default = null)
	{
		return apply_filters('bsi_settings_' . $setting, $default);
	}

	public static function protect_dir($dir)
	{
		if (!file_exists($dir . '/.htaccess')) {
			// invalid HTACCESS code to prevent downloads the hard way
			file_put_contents($dir . '/.htaccess', 'You cannot be here');
		}
	}

	public static function text_is_identical($value1, $value2): bool
	{
		$value1 = trim(str_replace(["\n", "\r"], '', $value1));
		$value2 = trim(str_replace(["\n", "\r"], '', $value2));

		return strip_tags($value1) == strip_tags($value2);
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

	/**
	 * @param $what
	 *
	 * @return string|void
	 */
	public function dummy_data($what)
	{
		switch ($what) {
			case 'text':
				return __('Type here to change the text on the image', Plugin::TEXT_DOMAIN) . "\n" .
					__('Change logo and image below', Plugin::TEXT_DOMAIN);
		}
	}


	/**
	 * On plugin activation, register a flag to rewrite permalinks.
	 * The plugin will do so after adding the post-endpoint.
	 */
	public static function on_activation($network_wide)
	{
		global $wpdb;
		update_option("bsi_needs_rewrite_rules", true);
		if ($network_wide && function_exists('is_multisite') && is_multisite()) {
			$blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");
			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);
				update_option("bsi_needs_rewrite_rules", true);
				restore_current_blog();
			}
		}
	}

	public static function on_deactivation($network_wide)
	{
		global $wpdb;
		Plugin::purge_cache();
		if ($network_wide && function_exists('is_multisite') && is_multisite()) {
			$blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->base_prefix}blogs");
			foreach ($blog_ids as $blog_id) {
				switch_to_blog($blog_id);
				Plugin::purge_cache();
				restore_current_blog();
			}
		}
	}

	public static function on_uninstall($network_wide)
	{
		// cannot uninstall without deactivation, so we may expect WordPress to call the dectivation hook.
		// no need to do it ourselves.
	}
}
