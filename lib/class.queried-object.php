<?php
namespace Acato\Plugins\OGImage;

use ArrayAccess;

class QueriedObject implements ArrayAccess
{
	private $data;
	private $keys = ['object_id', 'object_type', 'base_type', 'permalink', 'og_image', 'go'];
	private $is_public;

	public function __construct()
	{
		$this->data = [];
	}

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}
		return $instance;
	}

	public static function setData($newData = null)
	{
		$that = self::getInstance();

		if (is_null($newData)) {
			global $wp_query, $pagenow;
			if (!$that->data) {
				$link = null;
				$id = get_queried_object_id();
				$qo = get_queried_object();
				$is_front_page = false;

				// front-page hack
				$current_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
				$front = get_option('page_on_front');
				if ($front && ('/' . Plugin::output_filename() . '/' === $current_url || '/' === $current_url) ) {
					$id = $front;
					$is_front_page = true;
					// $qo is ignored for pages
				}
				$that->is_public = true;

				switch (true) {
					// post edit
					case is_admin() && in_array($pagenow, ['post.php', 'post-new.php']):
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$id = !empty($_GET['post']) ? intval($_GET['post']) : 'new';
						$type = empty($_GET['post_type']) ? false : $_GET['post_type'];
						$type = !$type && $pagenow == 'post-new.php' ? 'post' : $type;
						$type = !$type ? get_post_type($id) : $type;
						$base_type = 'post';
						$link = get_permalink($id);
						break;

					// post
					case is_single():
					case is_page():
					case $is_front_page: // front-page social-image-url hack
					case is_front_page() && !is_home():
					case is_privacy_policy():
					case is_singular():
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$type = get_post_type();
						$base_type = 'post';
						$link = get_permalink($id);
						break;

					// post archive
					case is_post_type_archive():
					case is_archive() && !is_category() && !is_tag() && !is_tax():
					case is_home():
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$id = 'archive';
						$type = get_post_type();
						$base_type = 'post';
						$link = get_post_type_archive_link($type);
						break;

					// category edit
					case is_admin() && in_array($pagenow, ['term.php', 'edit-tags.php']):
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$id = !empty($_GET['tag_ID']) ? intval($_GET['tag_ID']) : 'new';
						$type = !empty($_GET['taxonomy']) ? $_GET['taxonomy'] : get_post_type($id);
						$base_type = 'category';
						$link = get_term_link($id, $type);
						if (is_wp_error($link)) {
							$link = null;
						}
						break;


					// category archive
					case is_category():
					case is_tag():
					case is_tax():
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$type = $qo->taxonomy;
						$base_type = 'category';
						$link = get_term_link($id, $type);
						break;

					// unsupported
					case is_404():
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$type = '404';
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
//						var_dump(__LINE__, $wp_query, $id, $qo);exit;
						$id = null;
						$type = 'unsupported';
						$base_type = 'unsupported';
						break;

				}

				switch($base_type) {
					case 'post':
						$type_o = get_post_type_object($type);
						if (!$type_o->public) {
							$that->is_public = false;
						}
						break;
					case 'category':
						$type_o = get_taxonomy($type);
						if (!$type_o->public) {
							$that->is_public = false;
						}
						break;
				}

				$og_link_perma = trailingslashit(trailingslashit($link) . Plugin::output_filename());
				$og_link_param = add_query_arg(Plugin::QUERY_VAR, '1', $link);
				$og_link = is_preview() || !Plugin::urlCanBeRewritten($link) || !Plugin::urlCanBeRewritten($og_link_perma) ? $og_link_param : $og_link_perma;

				$result = [$id, $type, $base_type, $link, $link ? $og_link : null, $that->is_public];
				$that->data = $result; // save, because next step is querying ... sorry...
				if ($that->is_public) {
					$result[5] = Plugin::go_for_id($id, $type, $base_type) && $result[3] && $result[4];
				}
				else {
					$result[4] = false;
				}
				$that->data = $result;
			}
		}
		else {
			$that->data = $newData;
		}

		return $that; // allow chaining
	}

	public function getData()
	{
		return $this->data;
	}

	public function getTable()
	{
		$base_data = array_combine($this->keys, $this->data);
		$base_data['go'] = $base_data['go'] ? 'yes' : 'no';
		$base_data['object is public?'] = $this->is_public ? 'yes' : 'no';
		$matching_rules = [];

		$url_differs_just_param = function($haystack, $needle) {
			$diff = str_replace($needle, '', $haystack);
			$diff = trim($diff, '&?');
			return $diff == Plugin::QUERY_VAR .'=1';
		};

		if ($this->is_public) {
			if (get_option('permalink_structure')) {
				$rewrites_to = [];
				foreach (['permalink' => $base_data['permalink'], 'og_image' => $base_data['og_image']] as $group => $item) {
					if ($rewrite = Plugin::urlCanBeRewritten( $item )) {
						$rewrites_to[$group] = $rewrite['target'];
						$matching_rules[$group . ' matches rule #' . ($rewrite['rule#'] + 1)] = $rewrite['rule'] . '<br />' . $rewrites_to[$group];
					}
					else {
						$matching_rules[$group . ' Rewrite Error'] = 'There are no rewrite rules that match this URL';
					}
				}
				if (!$rewrites_to && $url_differs_just_param($base_data['og_image'], $base_data['permalink'])) {
					$matching_rules['Rewrite OK'] = 'URL based on parameter ' . Plugin::QUERY_VAR . ' should work fine';
				}
				elseif (count($rewrites_to) !== 2 || !$url_differs_just_param($rewrites_to['og_image'], $rewrites_to['permalink'])) {
					$matching_rules['Rewrite Error'] = 'Rewrite targets should only differ ' . Plugin::QUERY_VAR . '=1 parameter';
				}
				else {
					$matching_rules['Rewrite OK'] = 'Rewrite should work just fine!';
				}
			}
			else {
				$matching_rules['Rewrite Disabled in settings'] = 'URL based on parameter ' . Plugin::QUERY_VAR . ' should work fine';
			}
		}
		return array_merge($base_data, $matching_rules);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetExists($offset): bool
	{
		if (is_numeric($offset)) {
			return $offset < count($this->data);
		}
		return in_array($offset, $this->keys);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet($offset)
	{
		if (is_numeric($offset)) {
			return $this->data[$offset];
		}
		return $this->$offset;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetSet($offset, $value)
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset($offset)
	{
		return false;
	}

	public function __get($offset)
	{
		if ($this->offsetExists($offset)) {
			while (count($this->data) < count($this->keys)) {
				$this->data[] = false;
			}
			return array_combine($this->keys, $this->data)[$offset];
		}
		return false;
	}

	public function isPost($post_type = null): bool
	{
		return $post_type ? $this->object_type == $post_type : $this->base_type == 'post';
	}

	public function isCategory($taxonomy = null): bool
	{
		return $taxonomy ? $this->object_type == $taxonomy : $this->base_type == 'category';
	}

	public function showInterface(): bool
	{
		return 'unsupported' !== $this->base_type;
	}

	public function cacheDir(): string
	{
		return self::cacheDirFor($this->object_id, $this->object_type, $this->base_type);
	}

	public static function cacheDirFor($object_id, $object_type, $base_type): string
	{
		$dir = $object_id;
		if ('post' !== $base_type) {
			$dir = $base_type .'-'. $dir;
		}
		return $dir;
	}
}
