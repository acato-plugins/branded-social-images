<?php

namespace Clearsite\Plugins\OGImage;

use RankMath;

class Image {
	private $manager;
	public $image_id;
	public $post_id;
	public $serve_type;

	private $use_cache = true; // for skipping caching, set to false

	public function __construct( Plugin $manager)
	{
		$this->manager = $manager;

		$this->post_id = get_the_ID();
		// hack for front-page
		$current_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		if ('/og-image.png/' === $current_url) {
			$front = get_site_option('page_on_front');
			if ($front) {
				$this->post_id = $front;
			}
		}

		// This plugin provides a meta box ...
		$this->image_id = $this->getImageIdForPost( $this->post_id );

		if (defined('WP_DEBUG') && WP_DEBUG) {
			$this->use_cache = false;
		}

		if (isset($_GET['_preview'])) {
			$this->use_cache = false;
		}
	}

	public function getManager()
	{
		return $this->manager;
	}

	public function serve()
	{
		// well, we tried :(
		if (!$this->image_id) {
			header('HTTP/1.1 404 Not found');
			echo 'Sorry, could not find an OG Image configured. This is probably a temporary error.';
			exit;
		}

		$image_cache = $this->cache($this->image_id, $this->post_id);
		if ($image_cache) {
			// we have cache, or have created cache. In any way, we have an image :)
			// serve-type = redirect?
			if ('redirect' === $this->serve_type) {
				wp_redirect($image_cache['url']);
				exit;
			}
			if ('inline' === $this->serve_type) {
				header('Content-Type: image/png');
				header('Content-Disposition: inline; filename=og-image.png');
				header('Content-Length: '. filesize($image_cache['file']));
				readfile($image_cache['file']);
				exit;
			}
			echo 'Sorry, we have an image, but we don\'t know how to serve it.';
			exit;
		}
		echo 'Sorry, we could not create the image. This is probably a temporary error.';
		exit;
	}

	public function cache($image_id, $post_id, $retry=0)
	{
		if ($this->manager->preview) {
			// skip caching, build and serve image.
			// yes, PREVIEW ALWAYS SERVES THE IMAGE INLINE!
			$this->build($image_id, $post_id, true);
		}

		// do we have cache?
		$cache_file = wp_upload_dir();
		$base_url = $cache_file['baseurl'];
		$base_dir = $cache_file['basedir'];
		$lock_file = $cache_file['basedir'] . '/og-images/' . $image_id . '/' . $post_id . '/og-image.jpg.lock';
		$cache_file = $cache_file['basedir'] . '/og-images/' . $image_id . '/' . $post_id . '/og-image.png';
		if ($retry >= 2) {
			header('X-OG-Error-Fail: Generating image failed.');
			unlink($lock_file);
			return false;
		}

		if (!$retry && !$this->use_cache) {
			@unlink($cache_file);
			@unlink($lock_file);
		}

		if (is_file($cache_file)) {
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
		$cache_file = $this->build($image_id, $post_id);
		if (is_file($cache_file)) {
			return ['file' => $cache_file, 'url' => str_replace($base_dir, $base_url, $cache_file)];
		}
		elseif ($retry < 2) {
			return $this->cache($image_id, $post_id, $retry +1);
		}
	}

	public function build($image_id, $post_id, $push_to_browser=false) {
		$cache_file = wp_upload_dir();
		$base_url = $cache_file['baseurl'];
		$base_dir = $cache_file['basedir'];
		$lock_file = $cache_file['basedir'] . '/og-images/' . $image_id . '/' . $post_id . '/og-image.jpg.lock';
		$cache_file = $cache_file['basedir'] . '/og-images/' . $image_id . '/' . $post_id . '/og-image.png';

		$source = wp_get_attachment_image_src($image_id, 'og-image');
		if ($source) {
			list($image, $width, $height) = $source;
			if ($this->manager->height != $height || $this->manager->width != $width) {
				header('X-OG-Error-Size: Image sizes do not match, web-master should rebuild thumbnails and use images of sufficient size.');
			}
			$image_file = str_replace($base_url, $base_dir, $image);
			if (!is_file($image_file)) {
				header('X-OG-Error-File: Source image not found. This is a 404 on the source image.');
				unlink($lock_file);
				return false;
			}

//			$editor = wp_get_image_editor( $image_file );
			// we assume GD because we cannot be sure Imagick is there.
			// TODO: add IMagick variant, work in progress
//			if (is_a($editor, \WP_Image_Editor_Imagick::class)) {
//				require_once __DIR__ .'/class.og-image-imagick.php';
//				$image = new IMagick($this, $image_file, $cache_file);
//			}
//			elseif (is_a($editor, \WP_Image_Editor_GD::class)) {
			if (true) { // hard coded GD now
				require_once __DIR__ .'/class.og-image-gd.php';
				$image = new GD($this, $image_file, $cache_file);
			}
			else {
				header('X-OG-Error-Editor: No software present to manipulate images.');
				unlink($lock_file);
				return false;
			}

			if ($this->manager->logo_options['enabled']) {
				$image->logo_overlay($this->manager->logo_options);
			}

			if ($this->manager->text_options['enabled']) {
				$image->text_overlay($this->manager->text_options, $this->getTextForPost($post_id));
			}

			if ($push_to_browser) {
				$image->push_to_browser( microtime(true) . '.png');
			}
			else {
				$image->save();
			}

			unlink($lock_file);
			if (!$this->use_cache) {
				add_action('shutdown', function () use ($cache_file) { @unlink($cache_file); });
			}
			return is_file($cache_file) ? $cache_file : false;
		}
		unlink($lock_file);
		return false;
	}

	public function getTextForPost($post_id)
	{
		$meta = get_post_meta($post_id, '_cls_og_text', true);
		if ($meta) {
			$type = 'meta';
			$text = trim($meta);
		}

		if (!$text) {
			ob_start();
			do_action('wp_head');
			$head = ob_get_clean();
			// this is a lousy way of getting a processed og:title, but unfortunately, no easy options exist.
			// also; poor excuse for tag parsing. sorry.
			if ($head && false !== strpos($head, 'og:title')) {
				preg_match('/og:title.+content=(.)([^\n]+)/', $head, $m);
				$title = $m[2];
				$quote = $m[1];

				$text = trim($title, ' />' . $quote);
				$type = 'scraped';
			}
		}
		// default text
		if (!$text && trim($this->manager->text_options['text'])) {
			$type = 'default';
			$text = $this->manager->text_options['text'];
		}

		$text = apply_filters('cls_og_text', $text, $post_id, $this->getImageIdForPost($post_id), $type);

		return $text;
	}

	private function getImageIdForPost($post_id)
	{
		$the_img = 'meta';
		$image_id = get_post_meta($post_id, '_cls_og_image', true);

		// maybe Yoast SEO?
		if (defined('WPSEO_VERSION') && !$image_id) {
			$image_id = get_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', true);
			$the_img = 'yoast';
		}
		// maybe RankMath?
		if (class_exists(RankMath::class) && !$image_id) {
			$image_id = get_post_meta($post_id, 'rank_math_facebook_image_id', true);
			$the_img = 'rankmath';
		}
		// thumbnail?
		if (!$image_id && ('yes' === get_site_option('_cls_og_image_use_thumbnail'))) { // this is a Carbon Fields field, defined in class.og-image-admin.php
			$the_img = 'thumbnail';
			$image_id = get_post_thumbnail_id($post_id);
		}
		// global Image?
		if (!$image_id) {
			$the_img = 'global';
			$image_id = get_site_option('_cls_default_og_image'); // this is a Carbon Fields field, defined in class.og-image-admin.php
		}

		if ($image_id) { // this is for LOCAL DEBUGGING ONLY
//			add_filter('cls_og_text', function() use ($the_img) { return $the_img; }, PHP_INT_MAX);
		}

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
	public static function replaceFirstOccurence(string $haystack, string $needle, string $replace): string {
		// reference: https://stackoverflow.com/a/1252710/3679900
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
	public static function removeFirstOccurrence(string $haystack, string $needle): string {
		return self::replaceFirstOccurence($haystack, $needle, '');
	}
}
