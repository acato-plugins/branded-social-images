<?php

namespace Clearsite\Plugins\OGImage;

class Image {
	private $manager;
	public $image_id;
	public $post_id;
	public $serve_type;

	public function __construct( Plugin $manager)
	{
		$this->manager = $manager;

		$this->post_id = get_the_ID();
		// This plugin provides a meta box ...
		$this->image_id = $this->getImageIdForPost( $this->post_id );
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

			$editor = wp_get_image_editor( $image_file );
			// we assume GD because we cannot be sure Imagick is there.
			// TODO: check for existence and make use of imagick
			if (true) { // debug
//			if (is_a($editor, \WP_Image_Editor_Imagick::class)) {
//				require_once __DIR__ .'/class.og-image-imagick.php';
//				$image = new IMagick($this, $image_file, $cache_file);
//			}
//			elseif (is_a($editor, \WP_Image_Editor_GD::class)) {
				require_once __DIR__ .'/class.og-image-gd.php';
				$image = new GD($this, $image_file, $cache_file);
			}
			else {
				header('X-OG-Error-Editor: No software present to manipulate images.');
				unlink($lock_file);
				return false;
			}

			if ($this->manager->logo_options['enabled']) {
				// todo: overlay logo
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
			return is_file($cache_file) ? $cache_file : false;
		}
		unlink($lock_file);
		return false;
	}

	public function getTextForPost($post_id)
	{
		// default text
		if (trim($this->manager->text_options['text'])) {
			$type = 'default';
			$text = $this->manager->text_options['text'];
		}
		$meta = get_post_meta($post_id, 'cls_og_text', true);
		if ($meta) {
			$type = 'meta';
			$text = trim($meta);
		}

		ob_start();
		do_action('wp_head');
		$head = ob_get_clean();
		// this is a lousy way of getting a processed og:title, but unfortunately, no easy options exist.
		// also; poor excuse for tag parsing. sorry.
		if ($head && false !== strpos($head, 'og:title')) {
			preg_match('/og:title.+content=(.)([^\n]+)/', $head, $m);
			$title = $m[2];
			$quote = $m[1];

			$text = trim($title, ' />'. $quote);
			$type = 'scraped';
		}

		return apply_filters('cls_og_text', $text, $post_id, $this->getImageIdForPost($post_id), $type);
	}

	private function getImageIdForPost($post_id)
	{
		$image_id = get_post_meta($post_id, 'cls_og_image', true);
		// maybe Yoast SEO?
		if (defined('WPSEO_VERSION') && !$image_id) {
			$image_id = get_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', true);
		}
		// maybe RankMath?
		if (defined('WPSEO_VERSION') && !$image_id) { // TODO: Detect and Use RankMath
			$image_id = get_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', true);
		}
		// thumbnail?
		if (!$image_id) {
			$image_id = get_post_thumbnail_id($post_id);
		}
		// global Image?
		if (!$image_id) {
			$image_id = get_site_option('cls_og_image');
		}

		return $image_id;
	}
}
