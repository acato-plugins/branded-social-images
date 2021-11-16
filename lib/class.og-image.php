<?php

namespace Clearsite\Plugins\OGImage;

defined('ABSPATH') or die('You cannot be here.');

use QueriedObject;
use RankMath;

class Image
{
	private $manager;
	public $image_id;

	/**
	 * @deprecated, please use QueriedObject::getInstance()->object_id, QueriedObject::getInstance()->object_type and ->base_type
	 */
	public $post_id;

	private $use_existing_cached_image = true;

	public function __construct(Plugin $manager)
	{
		$this->manager = $manager;

		list($object_id, $object_type, $base_type, $link, $ogimage, $go) = QueriedObject::getInstance();
		// hack for home (posts on front)
		if (is_home()) {
			$object_id = 0;
			Plugin::log('Page is home (latest posts), post_id set to 0');
		}

		// hack for front-page
		$current_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		if ('/' . Plugin::output_filename() . '/' === $current_url) {
			Plugin::log('URI = Homepage BSI; ' . $current_url);
			$front = get_option('page_on_front');
			if ($front) {
				Plugin::log('Using post_id for front-page: ' . $front);
			}
		}

		if ('post' === $base_type) {
			$this->image_id = $this->getImageIdForPost($object_id);
		}
		else {
			$this->image_id = $this->getImageIdForQueriedObject();
		}
		Plugin::log('Image selected: ' . $this->image_id);

		if (defined('WP_DEBUG') && WP_DEBUG) {
			$this->use_existing_cached_image = false;
			Plugin::log('Cache ignored because of WP_DEBUG');
		}

		if (!empty($_GET['rebuild'])) {
			$this->use_existing_cached_image = false;
			Plugin::log('Cache ignored because of rebuild flag');
		}

		if (!empty($_GET['debug']) && 'BSI' == $_GET['debug']) {
			$this->use_existing_cached_image = false;
			Plugin::log('Cache ignored because of debug=BSI flag');
		}
	}

	public function getManager(): Plugin
	{
		return $this->manager;
	}

	public function serve()
	{
		// well, we tried :(
		if (!$this->image_id) {
			header('HTTP/1.1 404 Not found');
			$error = __('Sorry, could not find an OG Image configured.', Plugin::TEXT_DOMAIN);
			header('X-OG-Error: ' . $error);
			Plugin::log($error);
			Plugin::display_log();
			// if we get here, display_log was unavailable
			print $error;
			exit;
		}

		Plugin::no_output_buffers(true);

		$qo = QueriedObject::getInstance();
		$image_cache = $this->cache($this->image_id, $qo);
		if ($image_cache) {
			// we have cache, or have created cache. In any way, we have an image :)
			// serve-type = redirect?
			header('Content-Type: '. mime_content_type($image_cache['file']));
			header('Content-Disposition: inline; filename=' . Plugin::output_filename());
			header('Content-Length: ' . filesize($image_cache['file']));
			readfile($image_cache['file']);
			exit;
		}
		$error = __('Sorry, we could not create the image.', Plugin::TEXT_DOMAIN);
		header('X-OG-Error: ' . $error);
		Plugin::log($error);
		Plugin::display_log();
		// if we get here, display_log was unavailable
		print $error;
		exit;
	}

	public function cache($image_id, QueriedObject $queriedObject, $retry = 0)
	{
		// do we have cache?
		$cache_file = wp_upload_dir();
		$base_url = $cache_file['baseurl'];
		$base_dir = $cache_file['basedir'];
		$cache_file = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/' . $image_id . '/' . $queriedObject->cacheDir() . '/' . Plugin::output_filename();
		$lock_file = $cache_file . '.lock';

		if ($retry >= 2) {
			header('X-OG-Error-Fail: Generating image failed.');
			if (is_file($lock_file)) {
				unlink($lock_file);
			}
			return false;
		}

		header('X-OG-Cache: miss');
		if (!$this->use_existing_cached_image) {
			header('X-OG-Cache: ignored', true);
		}
		else if (is_file($cache_file)) {
			header('X-OG-Cache: hit', true);
			return ['file' => $cache_file, 'url' => str_replace($base_dir, $base_url, $cache_file)];
		}
		if (is_file($lock_file)) {
			// we're already building this file.
			if (filemtime($lock_file) > time() - 3600) {
				// but if we already took an hour.
				// we can safely assume we failed
				// right now, at this point, we must assume 'busy'
				header('Retry-After: 10'); // try again in 10 seconds
				http_response_code(503);
				exit;
			}
		}
		$this->manager->file_put_contents($lock_file, date('r'));
		$cache_file = $this->build($image_id, $queriedObject);
		if (is_file($cache_file)) {
			do_action('bsi_image_cache_built', $cache_file);
			return ['file' => $cache_file, 'url' => str_replace($base_dir, $base_url, $cache_file)];
		}
		elseif ($retry < 2) {
			return $this->cache($image_id, $queriedObject, $retry + 1);
		}
	}

	public function build($image_id, QueriedObject $queriedObject)
	{
		$cache_file = wp_upload_dir();
		$base_url = $cache_file['baseurl'];
		$base_dir = $cache_file['basedir'];
		$cache_file = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/' . $image_id . '/' . $queriedObject->cacheDir() . '/' . Plugin::output_filename();
		$lock_file = $cache_file . '.lock';
		$temp_file = $cache_file . '.tmp';

		Plugin::log('Base URL: ' . $base_url);
		Plugin::log('Base DIR: ' . $base_dir);
		Plugin::log('Lock File: ' . $lock_file);
		Plugin::log('Cache File: ' . $cache_file);

		$source = '';
		for ($i = Plugin::AA; $i > 1; $i--) {
			$tag = "@{$i}x";
			$source = Plugin::wp_get_attachment_image_data($image_id, Plugin::IMAGE_SIZE_NAME . $tag);
			Plugin::log('Source: trying image size "' . Plugin::IMAGE_SIZE_NAME . $tag . '" for ' . $image_id);
			if ($source && !empty($source[1]) && $source[1] * $this->manager->width * $i) {
				break;
			}
		}

		if (!$source) {
			// use x1 source, no matter what dimensions
			Plugin::log('Source: trying image size "' . Plugin::IMAGE_SIZE_NAME . '" for ' . $image_id);
			$source = Plugin::wp_get_attachment_image_data($image_id, Plugin::IMAGE_SIZE_NAME);
		}

		if (!$source) {
			Plugin::log('Source: failed. Could not get meta-data for image with id ' . $image_id);
			header('X-OG-Error-Source: Could not get meta-data for image with id ' . $image_id);
			return false;
		}

		if ($source) {
			list($image, $width, $height, $_, $image_file) = $source;
			Plugin::log('Source: found: ' . "W: $width, H: $height,\n URL: $image,\n Filepath: $image_file");
			if ($this->manager->height > $height || $this->manager->width > $width) {
				header('X-OG-Error-Size: Image sizes do not match, web-master should rebuild thumbnails and use images of sufficient size.');
			}
			if (!$image_file || !is_file($image_file)) {
				$image_file = str_replace($base_url, $base_dir, $image);
			}

			// situation: replacement failed. the url is not like the uploads url
			if ($image_file === $image) {
				$error = 'Image appears not to be in the regular path structure. Trying to get the path by checking for path fraction';
				Plugin::log("Source error: $error");
				Plugin::log("Source error: $image");
				header('X-OG-Error: ' . $error);
				$base_url_path_only = parse_url($base_url, PHP_URL_PATH);
				$image_file = explode($base_url_path_only, $image);
				$image_file = $base_dir . end($image_file);
				Plugin::log("Source error fixed?: $image_file; " . is_file($image_file) ? 'yes' : 'no');

				if (!is_file($image_file)) {
					// create temp file
					$error = 'Attempt 2 at getting image path failed, fetching file from web.';
					header('X-OG-Error: ' . $error);
					Plugin::log("Source error: $error");
					$this->manager->file_put_contents($temp_file, wp_remote_retrieve_body(wp_remote_get($image)));
					$image_file = $temp_file;
					Plugin::log("Source error fixed?: $image_file; " . is_file($image_file) ? 'yes' : 'no');
				}
			}

			if (!is_file($image_file)) {
				Plugin::log('Source: not found: ' . "Filepath: $image_file does not exist");
				header('X-OG-Error-File: Source image not found. This is a 404 on the source image.');
				unlink($lock_file);
				return false;
			}

//			$editor = wp_get_image_editor( $image_file );
			// we assume GD because we cannot be sure Imagick is there.
			// TODO: add IMagick variant
//			if (is_a($editor, \WP_Image_Editor_Imagick::class)) {
//				require_once __DIR__ .'/class.og-image-imagick.php';
//				$image = new IMagick($this, $image_file, $cache_file);
//			}
//			elseif (is_a($editor, \WP_Image_Editor_GD::class)) {
			if (true) { // hard coded GD now
				require_once __DIR__ . '/class.og-image-gd.php';
				$image = new GD($this, $image_file, $cache_file);
			}
			else {
				header('X-OG-Error-Editor: No software present to manipulate images.');
				unlink($lock_file);
				return false;
			}

			if ($this->manager->logo_options['enabled']) {
				Plugin::log("Logo overlay: enabled");
				$image->logo_overlay($this->manager->logo_options);
			}
			else {
				Plugin::log("Logo overlay: disabled");
			}

			if ($this->manager->text_options['enabled']) {
				Plugin::log("Text overlay: enabled");
				$image->text_overlay($this->manager->text_options, $this->getTextForQueriedObject());
			}
			else {
				Plugin::log("Text overlay: disabled");
			}

			add_action('shutdown', function () use ($lock_file, $temp_file) {
				@unlink($lock_file);
				@unlink($temp_file);
			});

			$filename = Plugin::output_filename();
			$format = explode('.', $filename); $format = end($format);
			Plugin::log('Using output format: '. $format);
			switch($format) {
				case 'jpg':
				default:
					$quality = Plugin::setting('jpg_quality_level', 75);
					$quality = is_int($quality) ? min(max(0, $quality), 100) : 75;
					Plugin::log('Using JPEG quality: '. $quality .' ( 0 - 100 )');
					break;
				case 'webp':
					$quality = Plugin::setting('webp_quality_level', 75);
					$quality = is_int($quality) ? min(max(0, $quality), 100) : 75;
					Plugin::log('Using WEBP quality: '. $quality .' ( 0 - 100 )');
					break;
				case 'png':
					$quality = Plugin::setting('png_compression_level', 2);
					$quality = is_int($quality) ? min(max(0, $quality), 9) : 2;
					Plugin::log('Using PNG quality: '. $quality .' ( 9 - 0 )');
					break;
			}

			if (!empty($_GET['debug']) && $_GET['debug'] == 'BSI') {
				Plugin::display_log();
			}

			$image->save($format, $quality);

			return is_file($cache_file) ? $cache_file : false;
		}

		return false;
	}


	private function getTextForQueriedObject()
	{
		list($object_id, $object_type, $base_type, $permalink, $ogimage, $go) = QueriedObject::getInstance();
		return $this->getTextForMeta($base_type, $object_id, $permalink);
	}

	private function getTextForPost($post_id)
	{
		return $this->getTextForMeta('post', $post_id, get_permalink($post_id));
	}

	public function getTextForMeta($base_type, $object_id, $permalink)
	{
		Plugin::log('Using meta-data from '. $base_type .' with id '. $object_id);
		$default = $this->manager->text_options['text'];
		if (Plugin::text_is_identical($default, Plugin::getInstance()->dummy_data('text'))) {
			$default = '';
		}
		Plugin::log('Text setting: default text; ' . ($default ?: '( no text )'));

		switch($base_type) {
			case 'post':
				$function = 'get_post_meta';
				$title = apply_filters('the_title', get_the_title($object_id), $object_id);
				break;
			case 'category':
				$function = 'get_term_meta';
				$title = apply_filters('the_title', get_cat_name($object_id), $object_id);
				break;
			default:
				Plugin::log('Unsupported type. sorry');
				$function = '__return_false';
				$title = $default;
		}

		$enabled = $function($object_id, Plugin::OPTION_PREFIX . 'text_enabled', true);
		if ('off' === $enabled) {
			Plugin::log('Text setting: post-meta has "text on this image" set to No');
			return '';
		}
		$text = '';
		$type = 'none';

		if (Plugin::setting('use_bare_post_title')) {
			$type = 'wordpress';
			$text = $title;
			Plugin::log('Text consideration: WordPress title (bare); ' . $text);
		}

		$meta = $function($object_id, Plugin::OPTION_PREFIX . 'text', true);
		if ($meta) {
			$type = 'meta';
			$text = trim($meta);
			Plugin::log('Text consideration: Meta-box text; ' . ($text ?: '( no text )'));
		}

		if (!$text && intval($object_id)) {
			Plugin::log('Text: no text detected in meta-data, getting text from page;');
			$scraped = Plugin::scrape_title($permalink);
			if ($scraped) {
				$type = 'scraped';
				$text = $scraped;
			}

			if (!$text) { // no text from scraping, build it
				$text = Plugin::title_format($object_id);
				$type = 'by-format';
			}
		}

		if (!$text) {
			$text = $default;
			Plugin::log('Text: No text found, using default; ' . $text);
			$type = 'default';
		}
		if (false !== strpos($text, '{title}')) {
			Plugin::log('{title} placeholder stored in database. Replacing with actual title.');
			Plugin::log(' This is a failsafe, should not happen. Please check the editor javascript console.');
			$text = str_replace('{title}', $title, $text);
		}
		if (false !== strpos($text, '{blogname}')) {
			Plugin::log('{blogname} placeholder stored in database. Replacing with actual blogname.');
			Plugin::log(' This is a failsafe, should not happen. Please check the editor javascript console.');
			$text = str_replace('{blogname}', get_bloginfo('name'), $text);
		}

		Plugin::log('Text determination: text before filter  bsi_text; ' . ($text ?: '( no text )'));
		$text = apply_filters('bsi_text', $text, $object_id, $this->image_id, $type);
		Plugin::log('Text determination: text after filter  bsi_text; ' . ($text ?: '( no text )'));

		return $text;
	}


	private function getImageIdForQueriedObject()
	{
		$qo = QueriedObject::getInstance();
		return $this->getImageIdForMeta($qo->base_type, $qo->object_id);
	}

	private function getImageIdForPost($post_id)
	{
		return $this->getImageIdForMeta('post', $post_id);
	}

	private function getImageIdForMeta($type, $id)
	{
		$the_img = 'meta';
		Plugin::log('Using meta-data from '. $type .' with id '. $id);
		switch($type) {
			case 'post':
				$function = 'get_post_meta';
				break;
			case 'category':
				$function = 'get_term_meta';
				break;
			default:
				Plugin::log('Unsupported type. sorry');
				$function = '__return_false';
		}
		$image_id = $function($id, Plugin::OPTION_PREFIX . 'image', true);
		Plugin::log('Image consideration: meta; ' . ($image_id ?: 'no image found'));
		// maybe Yoast SEO?
		if (defined('WPSEO_VERSION') && !$image_id) {
			$image_id = $function($id, '_yoast_wpseo_opengraph-image-id', true);
			Plugin::log('Image consideration: Yoast SEO; ' . ($image_id ?: 'no image found'));
			$the_img = 'yoast';
		}
		// maybe RankMath?
		if (class_exists(RankMath::class) && !$image_id) {
			$image_id = $function($id, 'rank_math_facebook_image_id', true);
			Plugin::log('Image consideration: SEO by RankMath; ' . ($image_id ?: 'no image found'));
			$the_img = 'rankmath';
		}
		// thumbnail?
		if ('post' === $type && !$image_id && ('on' === get_option(Plugin::OPTION_PREFIX . 'image_use_thumbnail'))) { // this is a Carbon Fields field, defined in class.og-image-admin.php
			$the_img = 'thumbnail';
			$image_id = get_post_thumbnail_id($id);
			Plugin::log('Image consideration: WordPress Featured Image; ' . ($image_id ?: 'no image found'));
		}
		// global Image?
		if (!$image_id) {
			$the_img = 'global';
			$image_id = get_option(Plugin::DEFAULTS_PREFIX . 'image'); // this is a Carbon Fields field, defined in class.og-image-admin.php
			Plugin::log('Image consideration: BSI Fallback Image; ' . ($image_id ?: 'no image found'));
		}

		if ('post' === $type) {
			Plugin::log('Image determination: ID before filter  bsi_image; ' . ($image_id ?: 'no image found'));
			$image_id = apply_filters_deprecated('bsi_image', [$image_id, $id, $the_img], '1.0.20', 'bsi_image_id', 'Please use filter bsi_image_id and query the QueriedObject for information on the object');
			Plugin::log('Image determination: ID after filter  bsi_image; ' . ($image_id ?: 'no image found'));
		}
		Plugin::log('Image determination: ID before filter  bsi_image_id; ' . ($image_id ?: 'no image found'));
		$image_id = apply_filters('bsi_image_id', $image_id, QueriedObject::getInstance(), $the_img);
		Plugin::log('Image determination: ID after filter  bsi_image_id; ' . ($image_id ?: 'no image found'));

		return $image_id;
	}

	/**
	 * Replaces the first occurrence of $needle from $haystack with $replace
	 * and returns the resultant string
	 * @param string $haystack
	 * @param string $needle
	 * @param string $replace
	 * @return string
	 */
	public static function replaceFirstOccurence(string $haystack, string $needle, string $replace): string
	{
		$new_string = $haystack;
		$pos = strpos($haystack, $needle);
		if ($pos !== false) {
			$new_string = substr_replace($haystack, $replace, $pos, strlen($needle));
		}
		return $new_string;
	}

	/**
	 * Removes the first occurrence $needle from $haystack and returns the resulting string
	 * @param string $haystack
	 * @param string $needle
	 * @return string
	 */
	public static function removeFirstOccurrence(string $haystack, string $needle): string
	{
		return self::replaceFirstOccurence($haystack, $needle, '');
	}

}
