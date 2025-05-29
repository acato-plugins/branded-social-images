<?php
/**
 * Image generation class.
 *
 * @package Acato\Plugins\OGImage
 */

/**
 * We disable a few WPCS rules here.
 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- not needed here, this is not a form.
 * phpcs:disable WordPress.DateTime.RestrictedFunctions.date_date -- we don't care about timezones here, we just need a date.
 * phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- we like to use camelCase here, sorry.
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- we like to use camelCase here, sorry.
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- we will try to catch errors, but we don't want to throw exceptions here.
 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- serving a file without readfile would be highly inefficient.
 */

namespace Acato\Plugins\OGImage;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

use RankMath;

/**
 * Class Image
 *
 * This class is responsible for generating the Open Graph image based on the queried object.
 * It handles caching, image processing, and serving the final image.
 */
class Image {
	/**
	 * The plugin manager instance.
	 *
	 * @var Plugin
	 */
	private $manager;

	/**
	 * The ID of the image to be generated.
	 *
	 * @var int
	 */
	public $image_id;

	/**
	 * The ID of the post for which the image is generated.
	 *
	 * @deprecated, please use QueriedObject::instance()->object_id, QueriedObject::instance()->object_type and ->base_type
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Whether to use an existing cached image or not.
	 *
	 * @var bool
	 */
	private $use_existing_cached_image = true;

	/**
	 * Image constructor.
	 *
	 * Initializes the image generation process based on the current queried object.
	 *
	 * @param Plugin $manager The plugin manager instance.
	 */
	public function __construct( Plugin $manager ) {
		$this->manager = $manager;

		list( $object_id, $object_type, $base_type, $link, $ogimage, $go ) = QueriedObject::instance();
		// hack for home (posts on front) .
		if ( is_home() ) {
			$object_id = 0;
			Plugin::log( 'Page is home (latest posts), post_id set to 0' );
		}

		// hack for front-page.
		$current_url = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( '/' . Plugin::output_filename() . '/' === $current_url ) {
			Plugin::log( 'URI = Homepage BSI; ' . $current_url );
			$front = get_option( 'page_on_front' );
			if ( $front ) {
				Plugin::log( 'Using post_id for front-page: ' . $front );
			}
		}

		$this->image_id = 'post' === $base_type ? $this->getImageIdForPost( $object_id ) : $this->getImageIdForQueriedObject();
		Plugin::log( 'Image selected: ' . $this->image_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->use_existing_cached_image = false;
			Plugin::log( 'Cache ignored because of WP_DEBUG' );
		}

		if ( ! empty( $_GET['rebuild'] ) ) {
			$this->use_existing_cached_image = false;
			Plugin::log( 'Cache ignored because of rebuild flag' );
		}

		if ( ! empty( $_GET['debug'] ) && 'BSI' === $_GET['debug'] ) {
			$this->use_existing_cached_image = false;
			Plugin::log( 'Cache ignored because of debug=BSI flag' );
		}
	}

	/**
	 * Get the plugin manager instance.
	 *
	 * @return Plugin
	 */
	public function getManager(): Plugin {
		return $this->manager;
	}

	/**
	 * Serve the generated image.
	 *
	 * This method checks if the image ID is set, retrieves the cached image if available,
	 * and serves it with the appropriate headers. If the image cannot be found or generated,
	 * it returns a 404 error.
	 */
	public function serve() {
		// well, we tried :( .
		if ( ! $this->image_id ) {
			header( 'HTTP/1.1 404 Not found' );
			$error = __( 'Sorry, could not find an OG Image configured.', 'bsi' );
			header( 'X-OG-Error: ' . $error );
			Plugin::log( $error );
			Plugin::display_log();
			// if we get here, display_log was unavailable.
			print esc_html( $error );
			exit;
		}

		Plugin::no_output_buffers( true );

		$qo          = QueriedObject::instance();
		$image_cache = $this->cache( $this->image_id, $qo );
		if ( $image_cache ) {
			// we have cache, or have created cache. In any way, we have an image :) .
			// serve-type = redirect? .
			header( 'Content-Type: ' . mime_content_type( $image_cache['file'] ) );
			header( 'Content-Disposition: inline; filename=' . Plugin::output_filename() );
			header( 'Content-Length: ' . filesize( $image_cache['file'] ) );
			if ( is_file( $image_cache['file'] . '.lock' ) ) {
				header( 'X-OG-Stray-Lock: removed' );
				wp_delete_file( $image_cache['file'] . '.lock' );
			}
			readfile( $image_cache['file'] );
			exit;
		}
		$error = __( 'Sorry, we could not create the image.', 'bsi' );
		header( 'X-OG-Error: ' . $error );
		Plugin::log( $error );
		Plugin::display_log();
		// if we get here, display_log was unavailable .
		print esc_html( $error );
		exit;
	}

	/**
	 * Cache the generated image.
	 *
	 * This method checks if a cached image exists, and if not, it attempts to build the image.
	 * If the image is successfully built, it returns the cache file and URL.
	 *
	 * @param int           $image_id      The ID of the image to be cached.
	 * @param QueriedObject $queriedObject The queried object containing metadata for the image.
	 * @param int           $retry         The number of retries for building the image (default is 0).
	 *
	 * @return array|false|void Returns an array with 'file' and 'url' if successful, false otherwise.
	 */
	public function cache( $image_id, QueriedObject $queriedObject, $retry = 0 ) {
		// do we have cache?
		$cache_file = wp_upload_dir();
		$base_url   = $cache_file['baseurl'];
		$base_dir   = $cache_file['basedir'];
		$cache_file = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/' . $image_id . '/' . $queriedObject->cacheDir() . '/' . Plugin::output_filename();
		$lock_file  = $cache_file . '.lock';

		if ( $retry >= 2 ) {
			header( 'X-OG-Error-Fail: Generating image failed.' );
			if ( is_file( $lock_file ) ) {
				wp_delete_file( $lock_file );
			}

			return false;
		}

		header( 'X-OG-Cache: miss' );
		if ( ! $this->use_existing_cached_image ) {
			header( 'X-OG-Cache: ignored', true );
		} elseif ( is_file( $cache_file ) ) {
			header( 'X-OG-Cache: hit', true );

			return [
				'file' => $cache_file,
				'url'  => str_replace( $base_dir, $base_url, $cache_file ),
			];
		}
		// we're already building this file.
		if ( is_file( $lock_file ) && filemtime( $lock_file ) > time() - 3600 ) {
			// but if we already took an hour.
			// we can safely assume we failed.
			// right now, at this point, we must assume 'busy'.
			header( 'Retry-After: 10' );
			// try again in 10 seconds.
			http_response_code( 503 );
			exit;
		}
		$this->manager->file_put_contents( $lock_file, date( 'r' ) );
		$cache_file = $this->build( $image_id, $queriedObject );
		if ( is_file( $cache_file ) ) {
			do_action( 'bsi_image_cache_built', $cache_file );

			return [
				'file' => $cache_file,
				'url'  => str_replace( $base_dir, $base_url, $cache_file ),
			];
		} elseif ( $retry < 2 ) {
			return $this->cache( $image_id, $queriedObject, $retry + 1 );
		}
	}

	/**
	 * Build the image based on the provided image ID and queried object.
	 *
	 * This method retrieves the source image, processes it, applies overlays, and saves the final image.
	 * It handles errors and returns the cache file if successful.
	 *
	 * @param int           $image_id      The ID of the image to be built.
	 * @param QueriedObject $queriedObject The queried object containing metadata for the image.
	 *
	 * @return string|false Returns the cache file path if successful, false otherwise.
	 */
	public function build( $image_id, QueriedObject $queriedObject ) {
		$cache_file = wp_upload_dir();
		$base_url   = $cache_file['baseurl'];
		$base_dir   = $cache_file['basedir'];
		$cache_file = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/' . $image_id . '/' . $queriedObject->cacheDir() . '/' . Plugin::output_filename();
		$lock_file  = $cache_file . '.lock';
		$temp_file  = $cache_file . '.tmp';

		Plugin::log( 'Base URL: ' . $base_url );
		Plugin::log( 'Base DIR: ' . $base_dir );
		Plugin::log( 'Lock File: ' . $lock_file );
		Plugin::log( 'Cache File: ' . $cache_file );

		$source = '';
		for ( $i = Plugin::AA; $i > 1; $i -- ) {
			$tag    = "@{$i}x";
			$source = Plugin::wp_get_attachment_image_data( $image_id, Plugin::IMAGE_SIZE_NAME . $tag );
			Plugin::log( 'Source: trying image size "' . Plugin::IMAGE_SIZE_NAME . $tag . '" for ' . $image_id );
			if ( $source && ! empty( $source[1] ) && $source[1] * $this->manager->width * $i ) {
				break;
			}
		}

		if ( '' === $source ) {
			// use x1 source, no matter what dimensions.
			Plugin::log( 'Source: trying image size "' . Plugin::IMAGE_SIZE_NAME . '" for ' . $image_id );
			$source = Plugin::wp_get_attachment_image_data( $image_id, Plugin::IMAGE_SIZE_NAME );
		}

		if ( ! $source ) {
			Plugin::log( 'Source: failed. Could not get meta-data for image with id ' . $image_id );
			header( 'X-OG-Error-Source: Could not get meta-data for image with id ' . $image_id );

			return false;
		}

		if ( [] !== $source ) {
			list( $image, $width, $height, $_, $image_file ) = $source;
			Plugin::log( 'Source: found: ' . "W: $width, H: $height,\n URL: $image,\n Filepath: $image_file" );
			if ( $this->manager->height > $height || $this->manager->width > $width ) {
				header( 'X-OG-Error-Size: Image sizes do not match, web-master should rebuild thumbnails and use images of sufficient size.' );
			}
			if ( ! $image_file || ! is_file( $image_file ) ) {
				$image_file = str_replace( $base_url, $base_dir, $image );
			}

			// situation: replacement failed. the url is not like the uploads url.
			if ( $image_file === $image ) {
				$error = 'Image appears not to be in the regular path structure. Trying to get the path by checking for path fraction';
				Plugin::log( "Source error: $error" );
				Plugin::log( "Source error: $image" );
				header( 'X-OG-Error: ' . $error );
				$base_url_path_only = wp_parse_url( $base_url, PHP_URL_PATH );
				$image_file         = explode( $base_url_path_only, $image );
				$image_file         = $base_dir . end( $image_file );
				Plugin::log( "Source error fixed?: $image_file; " . is_file( $image_file ) !== '' ? 'yes' : 'no' );

				if ( ! is_file( $image_file ) ) {
					// create temp file.
					$error = 'Attempt 2 at getting image path failed, fetching file from web.';
					header( 'X-OG-Error: ' . $error );
					Plugin::log( "Source error: $error" );
					$this->manager->file_put_contents( $temp_file, wp_remote_retrieve_body( wp_remote_get( $image ) ) );
					$image_file = $temp_file;
					Plugin::log( "Source error fixed?: $image_file; " . is_file( $image_file ) !== '' ? 'yes' : 'no' );
				}
			}

			if ( ! is_file( $image_file ) ) {
				Plugin::log( 'Source: not found: ' . "Filepath: $image_file does not exist" );
				header( 'X-OG-Error-File: Source image not found. This is a 404 on the source image.' );
				wp_delete_file( $lock_file );

				return false;
			}

			if ( function_exists( 'imagecreatefromstring' ) ) {
				require_once __DIR__ . '/class.og-image-gd.php';
				$image = new GD( $this, $image_file, $cache_file );
			} else {
				header( 'X-OG-Error-Editor: GD2 Image processor missing.' );
				wp_delete_file( $lock_file );

				return false;
			}

			if ( $this->manager->logo_options['enabled'] ) {
				Plugin::log( 'Logo overlay: enabled' );
				$image->logo_overlay( $this->manager->logo_options );
			} else {
				Plugin::log( 'Logo overlay: disabled' );
			}

			if ( $this->manager->text_options['enabled'] ) {
				Plugin::log( 'Text overlay: enabled' );
				$image->text_overlay( $this->manager->text_options, $this->getTextForQueriedObject() );
			} else {
				Plugin::log( 'Text overlay: disabled' );
			}

			add_action(
				'shutdown',
				function () use ( $lock_file, $temp_file ) {
					wp_delete_file( $lock_file );
					wp_delete_file( $temp_file );
				}
			);

			$filename = Plugin::output_filename();
			$format   = explode( '.', $filename );
			$format   = end( $format );
			Plugin::log( 'Using output format: ' . $format );
			switch ( $format ) {
				case 'jpg':
				default:
					$quality = Plugin::setting( 'jpg_quality_level', 75 );
					$quality = is_int( $quality ) ? min( max( 0, $quality ), 100 ) : 75;
					Plugin::log( 'Using JPEG quality: ' . $quality . ' ( 0 - 100 )' );
					break;
				case 'webp':
					$quality = Plugin::setting( 'webp_quality_level', 75 );
					$quality = is_int( $quality ) ? min( max( 0, $quality ), 100 ) : 75;
					Plugin::log( 'Using WEBP quality: ' . $quality . ' ( 0 - 100 )' );
					break;
				case 'png':
					$quality = Plugin::setting( 'png_compression_level', 2 );
					$quality = is_int( $quality ) ? min( max( 0, $quality ), 9 ) : 2;
					Plugin::log( 'Using PNG quality: ' . $quality . ' ( 9 - 0 )' );
					break;
			}

			if ( ! empty( $_GET['debug'] ) && 'BSI' === $_GET['debug'] ) {
				Plugin::display_log();
			}

			$image->save( $format, $quality );

			return is_file( $cache_file ) ? $cache_file : false;
		}

		return false;
	}

	/**
	 * Get the text for the queried object.
	 *
	 * This method retrieves the text for the current queried object, applying filters to allow customization.
	 *
	 * @return string The text for the queried object.
	 */
	private function getTextForQueriedObject() {
		list( $object_id, $object_type, $base_type, $permalink, $ogimage, $go ) = QueriedObject::instance();

		$text = $this->getTextForMeta( $base_type, $object_id, $permalink );
		Plugin::log( 'Text determination: text before filter  bsi_image_text; ' . ( $text ?: '( no text )' ) );
		$text = apply_filters( 'bsi_image_text', $text, QueriedObject::instance(), $this->image_id );
		Plugin::log( 'Text determination: text after filter  bsi_image_text; ' . ( $text ?: '( no text )' ) );

		return $text;
	}

	/**
	 * Get the text for the specified meta type and object ID.
	 *
	 * This method retrieves the text from post meta or term meta, applying filters and defaults as necessary.
	 *
	 * @param string $base_type The base type of the object (e.g., 'post', 'category').
	 * @param int    $object_id The ID of the object.
	 * @param string $permalink The permalink of the object.
	 *
	 * @return string The text for the specified meta type and object ID.
	 */
	public function getTextForMeta( $base_type, $object_id, $permalink ) {
		Plugin::log( 'Using meta-data from ' . $base_type . ' with id ' . $object_id );
		$default = $this->manager->text_options['text'];
		if ( Plugin::text_is_identical( $default, Plugin::instance()->dummy_data( 'text' ) ) ) {
			$default = '';
		}
		Plugin::log( 'Text setting: default text; ' . ( $default ?: '( no text )' ) );

		switch ( $base_type ) {
			case 'post':
				$function = 'get_post_meta';
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- would not make sense.
				$title = apply_filters( 'the_title', get_the_title( $object_id ), $object_id );
				break;
			case 'category':
				$function = 'get_term_meta';
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- would not make sense.
				$title = apply_filters( 'the_title', get_cat_name( $object_id ), $object_id );
				break;
			default:
				Plugin::log( 'Unsupported type. sorry' );
				$function = '__return_false';
				$title    = $default;
		}

		$enabled = $function( $object_id, Plugin::OPTION_PREFIX . 'text_enabled', true );
		if ( 'off' === $enabled ) {
			Plugin::log( 'Text setting: post-meta has "text on this image" set to No' );

			return '';
		}
		$text = '';
		$type = 'none';

		if ( Plugin::setting( 'use_bare_post_title' ) ) {
			$type = 'WordPress';
			$text = $title;
			Plugin::log( 'Text consideration: WordPress title (bare); ' . $text );
		}

		$meta = $function( $object_id, Plugin::OPTION_PREFIX . 'text', true );
		if ( $meta ) {
			$type = 'meta';
			$text = trim( $meta );
			Plugin::log( 'Text consideration: Meta-box text; ' . ( $text ?: '( no text )' ) );
		}

		if ( ! $text && (int) $object_id ) {
			Plugin::log( 'Text: no text detected in meta-data, getting text from page;' );
			$scraped = Plugin::scrape_title( $permalink );
			if ( $scraped ) {
				$type = 'scraped';
				$text = $scraped;
			}

			if ( ! $text ) { // no text from scraping, build it.
				$text = Plugin::title_format( $object_id );
				$type = 'by-format';
			}
		}

		if ( ! $text ) {
			$text = $default;
			Plugin::log( 'Text: No text found, using default; ' . $text );
			$type = 'default';
		}
		if ( false !== strpos( $text, '{title}' ) ) {
			Plugin::log( '{title} placeholder stored in database. Replacing with actual title.' );
			Plugin::log( ' This is a failsafe, should not happen. Please check the editor javascript console.' );
			$text = str_replace( '{title}', $title, $text );
		}
		if ( false !== strpos( $text, '{blogname}' ) ) {
			Plugin::log( '{blogname} placeholder stored in database. Replacing with actual blogname.' );
			Plugin::log( ' This is a failsafe, should not happen. Please check the editor javascript console.' );
			$text = str_replace( '{blogname}', get_bloginfo( 'name' ), $text );
		}

		$old_text = $text;
		$text     = apply_filters_deprecated(
			'bsi_text',
			[
				$text,
				$object_id,
				$this->image_id,
				$type,
			],
			'1.1.0',
			'bsi_image_text'
		);
		if ( $text !== $old_text ) {
			Plugin::log( 'Text determination: text before filter  bsi_text; ' . ( $old_text ?: '( no text )' ) );
			Plugin::log( 'Text determination: text after filter  bsi_text; ' . ( $text ?: '( no text )' ) );
			Plugin::log( 'Warning: deprecated filter in use. Please switch to bsi_image_text with parameters  $text, QueriedObject $queriedObject, $image_id' );
		}

		return $text;
	}

	/**
	 * Get the image ID for the current queried object.
	 *
	 * This method retrieves the image ID based on the queried object type and ID,
	 * checking post meta, term meta, and global settings.
	 *
	 * @return int The image ID for the queried object.
	 */
	private function getImageIdForQueriedObject() {
		$qo = QueriedObject::instance();

		return $this->getImageIdForMeta( $qo->base_type, $qo->object_id );
	}

	/**
	 * Get the image ID for a post.
	 *
	 * This method retrieves the image ID for a post based on its ID,
	 * checking post meta, term meta, and global settings.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return int The image ID for the post.
	 */
	private function getImageIdForPost( $post_id ) {
		return $this->getImageIdForMeta( 'post', $post_id );
	}

	/**
	 * Get the image ID for a specific meta type and ID.
	 *
	 * This method retrieves the image ID based on the specified meta type (post or category)
	 * and the corresponding ID, checking various sources for the image.
	 *
	 * @param string $meta_type The type of meta (e.g., 'post', 'category').
	 * @param int    $id        The ID of the post or category.
	 *
	 * @return int The image ID for the specified meta type and ID.
	 */
	private function getImageIdForMeta( $meta_type, $id ) {
		$the_img = 'meta';
		Plugin::log( 'Using meta-data from ' . $meta_type . ' with id ' . $id );
		switch ( $meta_type ) {
			case 'post':
				$function = 'get_post_meta';
				break;
			case 'category':
				$function = 'get_term_meta';
				break;
			default:
				Plugin::log( 'Unsupported type. sorry' );
				$function = '__return_false';
		}
		$image_id = $function( $id, Plugin::OPTION_PREFIX . 'image', true );
		Plugin::log( 'Image consideration: meta; ' . ( $image_id ?: 'no image found' ) );
		// maybe Yoast SEO?
		if ( defined( 'WPSEO_VERSION' ) && ! $image_id ) {
			$image_id = $function( $id, '_yoast_wpseo_opengraph-image-id', true );
			Plugin::log( 'Image consideration: Yoast SEO; ' . ( $image_id ?: 'no image found' ) );
			$the_img = 'yoast';
		}
		// maybe RankMath?
		if ( class_exists( RankMath::class ) && ! $image_id ) {
			$image_id = $function( $id, 'rank_math_facebook_image_id', true );
			Plugin::log( 'Image consideration: SEO by RankMath; ' . ( $image_id ?: 'no image found' ) );
			$the_img = 'rankmath';
		}
		// thumbnail?
		if ( 'post' === $meta_type && ! $image_id && ( 'on' === get_option( Plugin::OPTION_PREFIX . 'image_use_thumbnail' ) ) ) {
			// this is a Carbon Fields field, defined in class.og-image-admin.php .
			$the_img  = 'thumbnail';
			$image_id = get_post_thumbnail_id( $id );
			Plugin::log( 'Image consideration: WordPress Featured Image; ' . ( $image_id ?: 'no image found' ) );
		}
		// is this the globally defined Image?
		if ( ! $image_id ) {
			$the_img  = 'global';
			$image_id = get_option( Plugin::DEFAULTS_PREFIX . 'image' );
			// this is a Carbon Fields field, defined in class.og-image-admin.php .
			Plugin::log( 'Image consideration: BSI Fallback Image; ' . ( $image_id ?: 'no image found' ) );
		}

		if ( 'post' === $meta_type ) {
			Plugin::log( 'Image determination: ID before filter  bsi_image; ' . ( $image_id ?: 'no image found' ) );
			$image_id = apply_filters_deprecated( 'bsi_image', [ $image_id, $id, $the_img ], '1.1.0', 'bsi_image_id' );
			Plugin::log( 'Image determination: ID after filter  bsi_image; ' . ( $image_id ?: 'no image found' ) );
		}
		Plugin::log( 'Image determination: ID before filter  bsi_image_id; ' . ( $image_id ?: 'no image found' ) );
		$image_id = apply_filters( 'bsi_image_id', $image_id, QueriedObject::instance(), $the_img );
		Plugin::log( 'Image determination: ID after filter  bsi_image_id; ' . ( $image_id ?: 'no image found' ) );

		return $image_id;
	}
}
