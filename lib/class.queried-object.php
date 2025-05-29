<?php
/**
 * Helper class to get the queried object data.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Plugins\OGImage;

/**
 * Exceptions to the WordPress coding standards:
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- We use camelCase for properties and methods.
 * phpcs:disable WordPress.Security.NonceVerification.Recommended -- not processing any user input here.
 */

use ArrayAccess;

/**
 * Class QueriedObject
 *
 * This class is a singleton that provides access to the queried object data.
 * It implements ArrayAccess to allow accessing properties as array keys.
 *
 * @package Acato\Plugins\OGImage
 */
class QueriedObject implements ArrayAccess {
	/**
	 * The queried object data.
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * The keys for the queried object data.
	 *
	 * @var array
	 */
	private $keys = [ 'object_id', 'object_type', 'base_type', 'permalink', 'og_image', 'go' ];

	/**
	 * Is the queried object public?
	 *
	 * @var bool
	 */
	private $is_public;

	/**
	 * Constructor (NOP).
	 */
	public function __construct() {
	}

	/**
	 * Get the singleton instance of the QueriedObject class.
	 *
	 * @return static
	 */
	public static function instance() {
		static $instance;
		if ( ! $instance ) {
			$instance = new static();
		}

		return $instance;
	}

	/**
	 * Set the queried object data.
	 *
	 * If $newData is null, it will determine the data based on the current query.
	 * If $newData is provided, it will set the data to that value.
	 *
	 * @param array|null $newData The new data to set, or null to determine from the current query.
	 *
	 * @return static
	 */
	public static function setData( $newData = null ) {
		$that = self::instance();

		if ( is_null( $newData ) ) {
			global $wp_query, $pagenow;
			if ( ! $that->data ) {
				$link          = null;
				$id            = get_queried_object_id();
				$qo            = get_queried_object();
				$is_front_page = false;

				// front-page hack.
				$current_url = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
				$front       = get_option( 'page_on_front' );
				if ( $front && ( '/' . Plugin::output_filename() . '/' === $current_url || '/' === $current_url ) ) {
					$id            = $front;
					$is_front_page = true;
					// $qo is ignored for pages.
				}
				$that->is_public = true;

				switch ( true ) {
					// post edit.
					case is_admin() && in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ):
						$id        = empty( $_GET['post'] ) ? 'new' : (int) $_GET['post'];
						$type      = empty( $_GET['post_type'] ) ? false : $_GET['post_type'];
						$type      = ! $type && 'post-new.php' === $pagenow ? 'post' : $type;
						$type      = $type ?: get_post_type( $id );
						$base_type = 'post';
						$link      = get_permalink( $id );
						break;

					// post.
					case is_single():
					case is_page():
					case $is_front_page: // front-page social-image-url hack.
					case is_front_page() && ! is_home():
					case is_privacy_policy():
					case is_singular():
						$type      = get_post_type();
						$base_type = 'post';
						$link      = get_permalink( $id );
						break;

					// post archive.
					case is_post_type_archive():
					case is_archive() && ! is_category() && ! is_tag() && ! is_tax():
					case is_home():
						$id        = 'archive';
						$type      = get_post_type();
						$base_type = 'post';
						$link      = get_post_type_archive_link( $type );
						break;

					// category edit.
					case is_admin() && in_array( $pagenow, [ 'term.php', 'edit-tags.php' ], true ):
						$id        = empty( $_GET['tag_ID'] ) ? 'new' : (int) $_GET['tag_ID'];
						$type      = empty( $_GET['taxonomy'] ) ? get_post_type( $id ) : $_GET['taxonomy'];
						$base_type = 'category';
						$link      = get_term_link( $id, $type );
						if ( is_wp_error( $link ) ) {
							$link = null;
						}
						break;

					// category archive.
					case is_category():
					case is_tag():
					case is_tax():
						$type      = $qo->taxonomy;
						$base_type = 'category';
						$link      = get_term_link( $id, $type );
						break;

					// unsupported.
					case is_404():
						$type      = '404';
						$base_type = 'unsupported';
						break;
					case is_robots():
					case is_favicon():
					case is_embed():
					case is_paged():
					case is_admin():
					case is_attachment():
					case is_preview():
					case is_author():
					case is_date():
					case is_year():
					case is_month():
					case is_day():
					case is_time():
					case is_search():
					case is_feed():
					case is_comment_feed():
					case is_trackback():
					default:
						$id        = null;
						$type      = 'unsupported';
						$base_type = 'unsupported';
						break;

				}

				switch ( $base_type ) {
					case 'post':
						$type_o = get_post_type_object( $type );
						if ( ! $type_o || ! $type_o->public ) {
							$that->is_public = false;
						}
						break;
					case 'category':
						$type_o = get_taxonomy( $type );
						if ( ! $type_o || ! $type_o->public ) {
							$that->is_public = false;
						}
						break;
				}

				$link          = $link ?: '';
				$og_link_perma = trailingslashit( trailingslashit( $link ) . Plugin::output_filename() );
				$og_link_param = add_query_arg( Plugin::QUERY_VAR, '1', $link );
				$og_link       = is_preview() || ! Plugin::url_can_be_rewritten( $link ) || ! Plugin::url_can_be_rewritten( $og_link_perma ) ? $og_link_param : $og_link_perma;

				$result     = [ $id, $type, $base_type, $link, $link ? $og_link : null, $that->is_public ];
				$that->data = $result; // save, because next step is querying ... sorry...
				if ( $that->is_public ) {
					$result[5] = Plugin::go_for_id( $id, $type, $base_type ) && $result[3] && $result[4];
				} else {
					$result[4] = false;
				}
				$that->data = $result;
			}
		} else {
			$that->data = $newData;
		}

		return $that; // allow chaining.
	}

	/**
	 * Get the queried object data.
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Get the queried object as a table.
	 *
	 * @return array
	 */
	public function getTable() {
		$base_data                      = array_combine( $this->keys, $this->data );
		$base_data['go']                = $base_data['go'] ? 'yes' : 'no';
		$base_data['object is public?'] = $this->is_public ? 'yes' : 'no';
		$matching_rules                 = [];

		$url_differs_just_param = function ( $haystack, $needle ) {
			$diff = str_replace( $needle, '', $haystack );
			$diff = trim( $diff, '&?' );

			return ( Plugin::QUERY_VAR . '=1' ) === $diff;
		};

		if ( $this->is_public ) {
			if ( get_option( 'permalink_structure' ) ) {
				$rewrites_to = [];
				foreach (
					[
						'permalink' => $base_data['permalink'],
						'og_image'  => $base_data['og_image'],
					] as $group => $item
				) {
					$rewrite = Plugin::url_can_be_rewritten( $item );
					if ( $rewrite ) {
						$rewrites_to[ $group ] = $rewrite['target'];

						$matching_rules[ $group . ' matches rule #' . ( $rewrite['rule#'] + 1 ) ] = $rewrite['rule'] . '<br />' . $rewrites_to[ $group ];
					} else {
						$matching_rules[ $group . ' Rewrite Error' ] = 'There are no rewrite rules that match this URL';
					}
				}
				if ( ! $rewrites_to && $url_differs_just_param( $base_data['og_image'], $base_data['permalink'] ) ) {
					$matching_rules['Rewrite OK'] = 'URL based on parameter ' . Plugin::QUERY_VAR . ' should work fine';
				} elseif ( count( $rewrites_to ) !== 2 || ! $url_differs_just_param( $rewrites_to['og_image'], $rewrites_to['permalink'] ) ) {
					$matching_rules['Rewrite Error'] = 'Rewrite targets should only differ ' . Plugin::QUERY_VAR . '=1 parameter';
				} else {
					$matching_rules['Rewrite OK'] = 'Rewrite should work just fine!';
				}
			} else {
				$matching_rules['Rewrite Disabled in settings'] = 'URL based on parameter ' . Plugin::QUERY_VAR . ' should work fine';
			}
		}

		return array_merge( $base_data, $matching_rules );
	}

	/**
	 * Magic method to get properties as array keys.
	 *
	 * @param string $offset The property name.
	 *
	 * @return mixed
	 */
	public function __get( $offset ) {
		if ( $this->offsetExists( $offset ) ) {
			$data_count = count( $this->data );
			$key_count  = count( $this->keys );
			while ( $data_count < $key_count ) {
				$this->data[] = false;
				++ $data_count;
			}

			return array_combine( $this->keys, $this->data )[ $offset ];
		}

		return false;
	}

	/**
	 * Check if the queried object is a post.
	 *
	 * @param string|null $post_type The post type to check against.
	 *
	 * @return bool
	 */
	public function isPost( $post_type = null ): bool {
		return $post_type ? $this->object_type === $post_type : 'post' === $this->base_type;
	}

	/**
	 * Check if the queried object is a category.
	 *
	 * @param string|null $taxonomy The taxonomy to check against.
	 *
	 * @return bool
	 */
	public function isCategory( $taxonomy = null ): bool {
		return $taxonomy ? $this->object_type === $taxonomy : 'category' === $this->base_type;
	}

	/**
	 * Check if the queried object is supported.
	 *
	 * @return bool
	 */
	public function showInterface(): bool {
		return 'unsupported' !== $this->base_type;
	}

	/**
	 * Get the cache directory for the queried object.
	 *
	 * @return int|string
	 */
	public function cacheDir(): string {
		return self::cacheDirFor( $this->object_id, $this->object_type, $this->base_type );
	}

	/**
	 * Get the cache directory for a given object.
	 *
	 * @param int|string $object_id   The ID of the object.
	 * @param string     $object_type The type of the object.
	 * @param string     $base_type   The base type of the object (e.g., 'post', 'category').
	 *
	 * @return string
	 */
	public static function cacheDirFor( $object_id, $object_type, $base_type ): string {
		$dir = $object_id;
		if ( 'post' !== $base_type ) {
			$dir = $base_type . '-' . $dir;
		}

		return $dir;
	}

	// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.MissingParamTag -- From here on, we use the inherited generic ArrayAccess interface, so no need for a doc comment.

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ): bool {
		if ( is_numeric( $offset ) ) {
			return $offset < count( $this->data );
		}

		return in_array( $offset, $this->keys, true );
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		if ( is_numeric( $offset ) ) {
			return $this->data[ $offset ];
		}

		return $this->$offset;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ) {
		return false;
	}
}
