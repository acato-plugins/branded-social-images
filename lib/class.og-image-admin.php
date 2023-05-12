<?php

namespace Clearsite\Plugins\OGImage;

defined('ABSPATH') or die('You cannot be here.');

use Clearsite\Tools\HTML_Inputs;
use Exception;

class Admin
{
	public $storage = '';

	public function __construct()
	{
		add_filter('wp_check_filetype_and_ext', function ($result, $file, $filename) {
			if (substr(strtolower($filename), -4, 4) == '.ttf') {
				$result['ext'] = 'ttf';
				$result['type'] = 'font/ttf';
				$result['proper_filename'] = $filename;
			}
			if (substr(strtolower($filename), -4, 4) == '.otf') {
				$result['ext'] = 'otf';
				$result['type'] = 'font/otf';
				$result['proper_filename'] = $filename;
			}
			return $result;
		}, 11, 3);

		add_filter('upload_mimes', function ($existing_mimes) {
			$existing_mimes['ttf'] = 'font/ttf';
			$existing_mimes['otf'] = 'font/otf';
			return $existing_mimes;
		});

		add_action('admin_head', [static::class, 'maybe_move_font']);
		add_action('admin_head', [static::class, 'add_fontface_definitions']);
		add_action('admin_init', [static::class, 'process_post'], 11);
		add_action('admin_enqueue_scripts', function () {
			$script = (!defined('BSI_UNMINIFIED') ? 'admin/admin.min.js' : 'admin/admin.js');
			$style = (!defined('BSI_UNMINIFIED') ? 'admin/admin.css' : 'admin/admin.min.css');
			wp_enqueue_script(Plugin::SCRIPT_STYLE_HANDLE, plugins_url($script, __DIR__), ['jquery', 'jquery-ui-slider'], filemtime(dirname(__DIR__) . '/' . $script), true);
			wp_localize_script(Plugin::SCRIPT_STYLE_HANDLE, 'bsi_settings', [
				'preview_url' => get_permalink() . Plugin::output_filename(),
				'image_size_name' => Plugin::IMAGE_SIZE_NAME,
				'title_format' => Plugin::title_format(1, true),
				'text' => [
					'image_upload_title' => __('Select an image or upload one.', Plugin::TEXT_DOMAIN),
					'image_upload_button' => __('Use this image', Plugin::TEXT_DOMAIN),
					'file_upload_title' => __('Select an file or upload one.', Plugin::TEXT_DOMAIN),
					'file_upload_button' => __('Use this file', Plugin::TEXT_DOMAIN),

				]
			]);

			wp_enqueue_style(Plugin::SCRIPT_STYLE_HANDLE, plugins_url($style, __DIR__), '', filemtime(dirname(__DIR__) . '/' . $style));
		});

		add_action('admin_menu', function () {
			$location_setting = get_option(Plugin::DEFAULTS_PREFIX . 'menu_location', 'main');
			$location = apply_filters('bsi_admin_menu_location', $location_setting);
			if ('main' == $location) {
				add_menu_page('Branded Social Images', 'Branded Social Images', Plugin::get_management_permission(), Plugin::ADMIN_SLUG, [self::class, 'admin_panel'], self::admin_icon());
			}
			else {
				$parent = 'options-general.php';
				if ('media' === $location_setting) {
					$parent = 'upload.php';
				}
				add_submenu_page($parent, 'Branded Social Images', 'Branded Social Images', Plugin::get_management_permission(), Plugin::ADMIN_SLUG, [self::class, 'admin_panel']);
			}
		});

		add_action('admin_init', [static::class, 'sanitize_fonts']);

		add_filter('image_size_names_choose', function ($default_sizes) {
			// todo: support the experimental ::AA feature here.
			return array_merge($default_sizes, array(
				Plugin::IMAGE_SIZE_NAME => __('The OG:Image recommended size', Plugin::TEXT_DOMAIN),
			));
		});

		add_action('save_post', [static::class, 'save_meta_data']);
		add_action('add_meta_boxes', [static::class, 'add_meta_boxes']);
		add_action('admin_notices', [static::class, 'admin_notices']);

		add_filter('plugin_action_links', [static::class, 'add_settings_link'], 10, 2);
		add_filter('network_admin_plugin_action_links', [static::class, 'add_settings_link'], 10, 2);

		add_action('wp_ajax_' . Plugin::ADMIN_SLUG . '_get-font', [static::class, 'wp_ajax_bsi_get_font']);

		add_action('bsi_footer', function () {
			?><p><?php
			print sprintf(__('<a href="%s" target="_blank">Branded Social Images</a> is a free plugin by <a href="%s" target="_blank">Acato</a>.', Plugin::TEXT_DOMAIN), Plugin::PLUGIN_URL_WPORG, Plugin::AUTHOR_URL_INFO)
				. ' ' . __('Please let us know what you think of this plugin and what you wish to see in future versions.', Plugin::TEXT_DOMAIN)
				. ' ' . sprintf(__('<a href="%s" target="_blank">Contact us here</a>.', Plugin::TEXT_DOMAIN), Plugin::BSI_URL_CONTACT); ?></p><?php
			if (get_the_ID()) {
				?>
				<p><?php print sprintf(__('Use <a href="%s" target="_blank">%s</a> to preview what your social image looks like on social media.', Plugin::TEXT_DOMAIN),
					sprintf(Plugin::EXTERNAL_INSPECTOR, urlencode(get_permalink(get_the_ID()))), Plugin::EXTERNAL_INSPECTOR_NAME); ?></p>
				<p><?php print sprintf(__('<a href="%s" target="_blank">Show debug information</a> for the social-image of this post.', Plugin::TEXT_DOMAIN),
					add_query_arg('debug', 'BSI', Plugin::get_og_image_url(get_the_ID()))); ?></p><?php
			}
		});

		add_action('bsi_settings_panels', [ static::class, 'config_panel']);
		add_action('bsi_settings_panels', [ static::class, 'log_panel']);
	}

	public static function admin_icon(): string {
		$icon_file = '/img/' . basename( '/' . Plugin::ADMIN_ICON );
		if ( is_file( dirname( __DIR__ ) . $icon_file ) ) {
			$icon_url = plugins_url( $icon_file, __DIR__ );

			return $icon_url;
		}

		return Plugin::ADMIN_ICON;
	}

	/**
	 * todo: This does not change the defaults, nor is it used in this fashion anymore
	 * todo: This needs to be refactored!
	 */
	public static function base_settings(): array
	{
		$defaults = [];
		$defaults['text_options'] = [ // colors are RGBA in hex format
			'enabled' => 'on',
			'left' => null, 'bottom' => null, 'top' => null, 'right' => null,
			'position' => 'bottom-left',
			'font-size' => Plugin::DEF_FONT_SIZE,
			'color' => '#ffffffff', 'line-height' => Plugin::DEF_FONT_SIZE * 1.25,
			'font-file' => '',
			'font-family' => 'Roboto-Bold',
			'font-weight' => 700,
			'font-style' => 'normal',
			'display' => 'inline', // determines background-dimensions block: 100% width??? inline-block: rectangle around all text, inline: behind text only
			'padding' => '20', // background padding
			'background-color' => '#66666666',
			'background-enabled' => 'on',
			'text-shadow-color' => '',
			'text-shadow-left' => '2',
			'text-shadow-top' => '-2',
			'text-shadow-enabled' => 'off',
			'text-stroke-color' => '',
			'text-stroke' => '2',
		];
		$defaults['logo_options'] = [
			'enabled' => 'on',
			'position' => 'top-left',
			'left' => null, 'bottom' => null, 'top' => null, 'right' => null,
			'size' => get_option(Plugin::OPTION_PREFIX . 'image_logo_size', '100'),
		];

		return $defaults;
	}

	public static function getInstance(): Admin
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public static function admin_panel()
	{
		$action = !empty($_REQUEST['bsi-action']) ? $_REQUEST['bsi-action'] : 'show-config';

		?>
		<div class="wrap">
			<h2>Branded Social Images <span style="opacity: 0.2"><?php print Plugin::get_version(); ?></span></h2>
			<?php
			$errors = self::getErrors();
			foreach ($errors as $error) {
				?>
				<div class="updated error"><p><?php print $error; ?></p></div><?php
			}
			?>
			<div><?php
				switch ($action) {
					case 'purge-cache':
						$purgable = Plugin::get_purgable_cache('images');
						$purgable_dirs = Plugin::get_purgable_cache('directories');
						if (!$purgable && !$purgable_dirs) {
							_e('The cache is empty', Plugin::TEXT_DOMAIN);
							?><br /><a class="action button-primary"
									   href="<?php print esc_attr(remove_query_arg('bsi-action')); ?>"><?php _e('Ok', Plugin::TEXT_DOMAIN); ?></a><?php
							break;
						}
						else {
							print sprintf(__('This will clear the cache, %d image(s) and %d folder(s) will be removed. New images will be generated on demand.', Plugin::TEXT_DOMAIN), count($purgable), count($purgable_dirs));
						}
						?>
						<form method="POST"
							  action="<?php print esc_attr(add_query_arg('bsi-action', 'purge-cache-confirm')); ?>">
							<?php
							self::nonce_field('purge-cache-confirm');
							?>
							<input type="hidden" name="bsi-action" value="purge-cache-confirm"/>
							<button
								class="action button-primary"><?php _e('Confirm', Plugin::TEXT_DOMAIN); ?></button>
							<a class="action button cancel"
							   href="<?php print esc_attr(remove_query_arg('bsi-action')); ?>"><?php _e('Cancel', Plugin::TEXT_DOMAIN); ?></a>
						</form>
						<?php
						break;
					case 'show-config':
						$fields = Plugin::field_list()['admin'];
						?>
						<form method="POST"
							  action="<?php print esc_attr(add_query_arg('bsi-action', 'save-settings')); ?>">
							<?php
							self::nonce_field('save-settings');
							self::show_editor($fields);
							?>
							<br/>
							<br/>
							<button
								class="action button-primary"><?php _e('Save settings', Plugin::TEXT_DOMAIN); ?></button>
							<a class="action button-secondary" target="_blank"
							   href="<?php print esc_attr(add_query_arg('bsi-action', 'purge-cache')); ?>"><?php _e('Purge cache', Plugin::TEXT_DOMAIN); ?></a>
						</form>

						<?php
						do_action('bsi_footer');

						break;
				}
				?></div>
		</div>
		<?php
	}


	/**
	 * Add a link to the settings on the Plugins screen.
	 * @return array list of links to show in the plugins table
	 */
	public static function add_settings_link($links, $file): array
	{
		if ($file === Plugin::get_plugin_file() && current_user_can(Plugin::get_management_permission())) {
			// add setting link for anyone that is allowed to alter the settings.
			$url = add_query_arg('page', 'branded-social-images', admin_url('admin.php'));
			if (!is_array($links)) {
				$links = (array)$links;
			}
			$links[] = sprintf('<a href="%s">%s</a>', $url, __('Settings', Plugin::TEXT_DOMAIN));

			// add support link
			$links[] = sprintf('<a href="%s">%s</a>', Plugin::BSI_URL_CONTACT, __('Support', Plugin::TEXT_DOMAIN));

			// add contribute link
			$links[] = sprintf('<a href="%s">%s</a>', Plugin::BSI_URL_CONTRIBUTE, __('Contribute', Plugin::TEXT_DOMAIN));
		}

		return $links;
	}

	public static function valid_fonts(): array
	{
		$fonts = glob(self::storage() . '/*.?tf'); // matches ttf and otf, and more, but this is checked better later on
		$list = [];
		foreach ($fonts as $font) {
			$b = basename($font);
			$base = basename($font, '.ttf');
			$t = 'ttf';
			if ($base === $b) {
				$base = basename($font, '.otf');
				$t = 'otf';
			}
			$json = preg_replace('/\.[ot]tf$/', '.json', $font);
			$meta = [];
			if (is_file($json)) {
				$meta = json_decode(file_get_contents($json));
			}
			preg_match('/-w([1-9]00)(-italic)?\./', $font, $m);
			$entry = [
				'weight' => !empty($m[1]) ? $m[1] : 400,
				'style' => !empty($m[2]) ? trim($m[2], '-') : 'normal',
				'name' => $meta && !empty($meta->font_name) ? $meta->font_name : self::nice_font_name($base),
				'valid' => true,
				$t => self::storage() . '/' . $base . '.' . $t,
			];
			$weights = implode('|', self::font_name_weights());
			if (preg_match("/-($weights)?(Italic)?$/", $base, $m) && !empty($m[1])) {
				$weight = array_search($m[1], self::font_name_weights());
				if ($weight) {
					$entry['weight'] = $weight;
				}
			}
			if (!empty($m[2]) && $m[2] === 'Italic') {
				$entry['style'] = 'italic';
			}
//			$entry[]

			// display name
			$entry['display_name'] = $entry['name'] . ' - ' . self::weight_to_suffix($entry['weight'], $entry['style'] == 'italic');
			$entry['display_name'] = str_replace('Italic', ' Italic', $entry['display_name']);
			$entry['display_name'] = str_replace('  Italic', ' Italic', $entry['display_name']);
			$list[$base] = $entry;
		}

		return $list;
	}

	private static function storage(): string
	{
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/' . Plugin::STORAGE;
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		if (!is_dir($dir)) {
			self::setError('storage', __('Could not create the storage directory in the uploads folder.', Plugin::TEXT_DOMAIN) . ' ' . __('In a WordPress site the uploads folder should always be writable.', Plugin::TEXT_DOMAIN) . ' ' . __('Please fix this.', Plugin::TEXT_DOMAIN) . ' ' . __('This error will disappear once the problem has been corrected.', Plugin::TEXT_DOMAIN));
		}
		Plugin::protect_dir($dir);

		return $dir;
	}

	public static function nice_font_name($font)
	{
		// w400 to normal, w700 to bold etc
		list($name) = explode('-w', $font . '-w400', 2);
		$weights = implode('|', self::font_name_weights());
		return preg_replace("/-($weights)?(Italic)?$/", '', $name);
	}

	public static function getErrors()
	{
		$errors = get_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', []);
		update_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', []);
		return $errors;
	}

	public static function show_editor($fields, $is_meta_panel = false)
	{
		$fields['text']['current_value'] = trim( $fields['text']['current_value'] ?? "" ) ? $fields['text']['current_value'] : self::array_first( Plugin::text_fallback_chain() );

		$text_settings = Plugin::getInstance()->text_options;
		$logo_settings = Plugin::getInstance()->logo_options;

		$image = $fields['image']['current_value'];
		if ($image && is_numeric($image)) {
			$image = wp_get_attachment_image($image, Plugin::IMAGE_SIZE_NAME);
			preg_match('/src="(.+)"/U', $image, $m);
			$image = $m[1];
		}

		$logo = $fields['image_logo']['current_value'];
		$width = $height = 0;
		if ($logo && is_numeric($logo)) {
			$logo = wp_get_attachment_image($logo, 'full');
			preg_match('/width="(.+)"/U', $logo, $width);
			$width = $width[1];
			preg_match('/height="(.+)"/U', $logo, $height);
			$height = $height[1];
			preg_match('/src="(.+)"/U', $logo, $m);
			$logo = $m[1];
		}

		add_filter('bsi_editor_variables', function($list) use ($text_settings, $logo_settings, $logo, $width, $height) {
			return array_merge($list, [
				'padding' => Plugin::PADDING . 'px',
				'text-width' => ceil(Plugin::getInstance()->width * Plugin::TEXT_AREA_WIDTH - 2 * $text_settings['padding']) . 'px',
				'text-height' => ceil(Plugin::getInstance()->height * Plugin::TEXT_AREA_WIDTH - 2 * $text_settings['padding']) . 'px',

				'text-background' => Admin::hex_to_rgba($text_settings['background-color'], true),
				'text-color' => Admin::hex_to_rgba($text_settings['color'], true),
				'text-font' => $text_settings['font-file'],
				'letter-spacing' => '1px',
				'text-shadow-color' => Admin::hex_to_rgba($text_settings['text-shadow-color'], true),
				'text-shadow-top' => intval($text_settings['text-shadow-top']) . 'px',
				'text-shadow-left' => intval($text_settings['text-shadow-left']) . 'px',
				'font-size' => $text_settings['font-size'] . 'px',
				'text-padding' => $text_settings['padding'] . 'px',
				'line-height' => $text_settings['line-height'] . 'px',

				'logo-scale' => $logo_settings['size'],
				'logo-width' => ($logo ? $width : 410), /* example logo */
				'logo-height' => ($logo ? $height : 82),
			]);
			}, PHP_INT_MAX);
		?>
		<style>
			#branded-social-images-editor {
				<?php
				/**
				 * Allow extra editor-variables by add-ons
 				 * Note: you cannot overrule the BSI standard variables
				 *
				 * @since 1.0.18
				 *
				 * @param array  $variables     Add your variables to the list. skip the -- prefix.
				 */
				$variables = apply_filters('bsi_editor_variables', []);
				foreach ($variables as $variable => $value) {
					print '
--'. $variable .': '. $value .';';
				}
				?>
			}

		</style>
		<?php // self::render_options($fields, ['disabled']);

		$editor_class = [];
		$editor_class[] = 'logo_position-' . (!empty($fields['logo_position']) ? $fields['logo_position']['current_value'] : $logo_settings['position']);
		$editor_class[] = 'text_position-' . (!empty($fields['text_position']) ? $fields['text_position']['current_value'] : $text_settings['position']);

		if ((empty($fields['disabled']) && 'on' === get_option(Plugin::DEFAULTS_PREFIX . 'disabled', 'off')) || $fields['disabled']['current_value'] == 'on') {
			$editor_class[] = 'bsi-disabled';
		}
		if ($logo) {
			$editor_class[] = 'with-logo';
		}
		if ('on' === $text_settings['background-enabled']) {
			$editor_class[] = 'with-text-background';
		}

		$text_fallback_chain = Plugin::text_fallback_chain();
		// in case of no 'scraped' title which resulted of a non-normal-page-code, build the title on-the-fly
		if (get_the_ID() && empty($text_fallback_chain['meta'])) { // post but no title configured
			$scraped = Plugin::scrape_title_data(get_the_ID());
			if ($scraped[0] >= 300) { // non-normal state
				$editor_class[] = 'auto-title';
			}
		}

		$editor_class = implode(' ', $editor_class);
		?>
		<div id="branded-social-images-editor"
			 class="<?php print $editor_class; ?>"
			 data-font="<?php print $text_settings['font-file']; ?>"
			 data-use-thumbnail="<?php print Plugin::field_list()['admin']['image_use_thumbnail']['current_value']; ?>">
			<?php if ($is_meta_panel) { ?>
				<div class="settings">
					<div class="area--settings">
						<h2><?php _e('Settings', Plugin::TEXT_DOMAIN); ?></h2>
						<div class="inner">
							<?php self::render_options($fields); ?>
						</div>
					</div>
				</div>
			<?php } ?>
			<div class="grid">
				<div class="area--background-canvas"><?php include __DIR__ . '/../img/example.svg'; ?></div>
				<?php foreach (Plugin::image_fallback_chain() as $kind => $fallback_image) { ?>
					<div class="area--background-alternate image-source-<?php print $kind; ?>">
						<div class="background"
							 <?php if ($fallback_image) { ?>style="background-image:url('<?php print esc_attr($fallback_image); ?>')"<?php } ?>>
						</div>
					</div>
				<?php } ?>
				<div class="area--logo logo-alternate">
					<div class="logo"
						 style="background-image:url('<?php print plugins_url('img/example-logo.svg', __DIR__) ?>')"></div>
				</div>
				<?php do_action('bsi_image_editor', 'after_creating_canvas'); ?>
				<div class="area--background">
					<div class="background" style="background-image:url('<?php print esc_attr($image); ?>')"></div>
				</div>
				<?php do_action('bsi_image_editor', 'after_adding_background'); ?>
				<div class="area--logo">
					<div class="logo" style="background-image:url('<?php print esc_attr($logo); ?>')"></div>
				</div>
				<?php do_action('bsi_image_editor', 'after_adding_logo'); ?>
				<div class="area--text">
					<div class="editable-container">
						<pre contenteditable="true"
							 class="editable"><?php print $fields['text']['current_value']; ?></pre>
						<?php foreach ($text_fallback_chain as $type => $text) {
							?>
							<div class="text-alternate type-<?php print $type; ?>"><?php print $text; ?></div><?php
						} ?>
					</div>
				</div>
				<?php do_action('bsi_image_editor', 'after_adding_text'); ?>
			</div>
			<?php if (!$is_meta_panel) { ?>
				<div class="settings">
					<div class="area--options collapsible">
						<h2><?php _e('Image and Logo options', Plugin::TEXT_DOMAIN); ?><span class="toggle"></span></h2>
						<div class="inner">
							<?php self::render_options($fields, [
								'image', 'image_use_thumbnail',
								'image_logo', 'logo_position', 'image_logo_size',
							]); ?>
						</div>
					</div>
					<div class="area--settings collapsible">
						<h2><?php _e('Text settings', Plugin::TEXT_DOMAIN); ?><span class="toggle"></span></h2>
						<div class="inner">
							<?php self::render_options($fields, [
								'text', 'text_enabled',
								'color',
								'text_shadow_enabled',
								'text__font', 'text__ttf_upload', 'text_position',
								'text__font_size',
								'background_enabled', 'background_color',
								'text_shadow_color', 'text_shadow_top', 'text_shadow_left',
								'text_stroke_color', 'text_stroke',
								'text__google_download',
							]); ?>
						</div>
					</div>
					<?php
					/**
					 * Allow extra settings panels by add-ons
					 *
					 * @since 1.0.18
					 *
					 * @param array  $fields     All available fields
					 */
					do_action('bsi_settings_panels', $fields);
					?>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	public static function render_options($options, $filter = [])
	{
		static $seen = [];
		require_once __DIR__ . '/class.html_inputs.php';
		if (!$filter) {
			$filter = array_keys($options);
		}

		$filter = array_diff($filter, $seen);

		foreach ($filter as $option_name) {
			if (!empty ($options[$option_name])) {
				$seen[] = $option_name;
				self::render_option($option_name, $options[$option_name]);
			}
		}
	}

	private static function render_option($option_name, $option_atts)
	{
		if (!empty($option_atts['namespace']) && $option_atts['namespace'] == Plugin::DO_NOT_RENDER) {
			return;
		}
		print '<span data-name="' . esc_attr($option_name) . '" class="input-wrap name-' . esc_attr($option_name) . ' input-' . $option_atts['type'] . (!empty($option_atts['class']) ? str_replace(' ', ' wrap-', ' ' . $option_atts['class']) : '') . '">';
		$label = '';
		if (!empty($option_atts['label'])) {
			$label = $option_atts['label'];
			unset($option_atts['label']);
		}
		HTML_Inputs::render($option_name, $option_atts, $label);
		print '</span>';
	}

	public static function log_panel()
	{
		if ($log = get_transient(Plugin::OPTION_PREFIX .'_debug_log')) { ?>
		<div class="area--debug closed collapsible">
			<h2><?php _e('Debug log', Plugin::TEXT_DOMAIN); ?><span class="toggle"></span></h2>
			<div class="inner">
				<pre><?php print $log; ?></pre>
				<em><?php $date = date('d-m-Y H:i:s', get_option('_transient_timeout_'. Plugin::OPTION_PREFIX .'_debug_log'));
					print sprintf(__('This log will be available until %s or until overwritten by a new log.', Plugin::TEXT_DOMAIN), $date); ?></em>
			</div>
		</div>
	<?php }
	}

	public static function config_panel($fields)
	{
		?>
		<div class="area--config closed collapsible">
			<h2><?php _e('Plugin configuration', Plugin::TEXT_DOMAIN); ?><span class="toggle"></span></h2>
			<div class="inner">
				<?php self::render_options($fields, ['disabled']); ?>
			</div>
		</div>
		<?php
	}

	public static function hex_to_rgba($hex, $asRGBA = false)
	{
		$hex = str_replace('#', '', $hex);
		if (!$hex) {
			$hex = '0000';
		}
		if (strlen($hex) <= 4) {
			$hex = str_split($hex . 'F');
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		$hex = substr($hex . 'FF', 0, 8);

		$int = hexdec($hex);
		$rgba = [ ($int >> 24) & 255, ($int >> 16) & 255, ($int >> 8) & 255, floatval($int & 255) / 255 ];

		return $asRGBA ? vsprintf('rgba(%d, %d, %d, %0.1F)', $rgba) : array_combine(['red', 'green', 'blue', 'alpha'], $rgba);
	}

	public static function add_meta_boxes()
	{
		$post_types = apply_filters('bsi_post_types', []);
		$meta_location = get_option(Plugin::DEFAULTS_PREFIX . 'meta_location', 'advanced');
		foreach ($post_types as $post_type) {
			$context = apply_filters('bsi_meta_box_context', $meta_location, $post_type);
			if (!in_array($context, ['advanced', 'normal', 'side'])) {
				$context = $meta_location;
			}
			add_meta_box(
				Plugin::ADMIN_SLUG,
				'Branded Social Images',
				[static::class, 'meta_panel'],
				$post_type,
				$context
			);
		}
	}

	public static function save_meta_data($post_id)
	{

		if (array_key_exists('branded_social_images', $_POST)) {
			// save new BSI meta values
			$valid_post_keys = Plugin::get_valid_POST_keys('meta');

			foreach ($_POST['branded_social_images'] as $namespace => $values) {
				if (is_array($values)) {
					foreach ($values as $key => $value) {
						if (!in_array($key, $valid_post_keys[$namespace])) {
							continue;
						}
						if ($key === 'text' && !empty($value)) {
							$value = strip_tags($value);
						}
						if ($key === 'text' && Plugin::text_is_identical($value, self::array_first(Plugin::text_fallback_chain()))) {
							$value = '';
						}
						if ($key === 'text' && Plugin::text_is_identical($value, Plugin::getInstance()->dummy_data('text'))) {
							$value = '';
						}
						update_post_meta($post_id, "$namespace$key", $value);
					}
				}
			}

			// clean the cache
			$cache_file = wp_upload_dir();
			$lock_files = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/*/' . $post_id . '/' . Plugin::output_filename() . '.lock';
			$cache_files = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/*/' . $post_id . '/' . Plugin::output_filename();
			array_map('unlink', array_merge(glob($lock_files), glob($cache_files)));
		}
	}

	public static function meta_panel()
	{
		$fields = Plugin::field_list()['meta'];
		self::show_editor($fields, true);
		do_action('bsi_footer');
	}

	public static function admin_notices()
	{
		$errors = self::getErrors();
		foreach ($errors as $error) {
			?>
			<div class="updated error"><p><?php print $error; ?></p></div><?php
		}
	}

	public static function wp_ajax_bsi_get_font()
	{
		// prevent path-traversal
		$font = basename('fake-dir/' . $_GET['font']);
		$file = self::storage() . '/' . $font;
		if (is_file($file)) {
			$mime = mime_content_type($file);
			header('Content-Type: ' . $mime);
			header('Content-Disposition: inline; filename="' . $font . '"');
			header('Content-Length: ' . filesize($file));
			readfile($file);
			exit;
		}
		header('HTTP/1.1 404 Not Found', true, 404);
		exit;
	}

	public static function add_fontface_definitions()
	{
		$fonts = self::valid_fonts();
		$faces = [];
		$protected = admin_url('admin-ajax.php?action=' . Plugin::ADMIN_SLUG . '_get-font');

		foreach ($fonts as $font_base => $font) {
			if (!$font['valid']) {
				continue;
			}
			$style = $font['style'];
			$weight = $font['weight'];
			$sources = [];
			foreach (['ttf' => 'truetype', 'otf' => 'opentype'/*, 'woff2' => 'woff2', 'woff' => 'woff'*/] as $extention => $format) {
				if (empty($font[$extention])) {
					continue;
				}
				$sources[] = "url('$protected&font=$font_base.$extention') format('$format')";
			}
			$sources = implode(',', $sources);
			$faces[] = <<< EOCSS
@font-face {
	font-family: $font_base;
	font-style: $style;
	font-weight: $weight;
	src: $sources;
}
EOCSS;
		}

		$tweaks = Plugin::font_rendering_tweaks();
		foreach ($tweaks as $font => &$tweak) {
			$tweak = $tweak['admin'];
			foreach ($tweak as $prop => &$val) {
				$val = "$prop: $val;";
			}
			$tweak = implode("\n", $tweak);
			$tweak = "#branded-social-images-editor[data-font='$font'] .editable-container {\n$tweak}\n";
		}

		print '<style id="branded-social-images-css">' . implode("\n\n", $faces) . "\n\n" . implode("\n\n", $tweaks) . '</style>';
	}

	public static function sanitize_fonts()
	{
		$storage = trailingslashit(self::storage());
		$missed_one = false;
		foreach (Plugin::default_google_fonts() as $font) {
			$font_family = $font['font_family'];
			$font_weight = !empty($font['font_weight']) ? $font['font_weight'] : 400;
			$font_style = !empty($font['font_style']) ? $font['font_style'] : 'normal';
			$local_filename = self::google_font_filename($font_family, $font_weight, $font_style, 'ttf');
			if (!is_file($storage . $local_filename)) {
				self::download_google_font($font_family, $font_weight, $font_style);
				$missed_one = true;
			}
		}
		if ($missed_one) {
			Plugin::font_rendering_tweaks(true);
		}
	}

	public static function google_font_filename($font_family, $font_weight, $font_style, $extention = ''): string
	{
		$italic = $font_style == 'italic' ? 'italic' : '';
		$suffix = self::weight_to_suffix($font_weight, $italic);
		$font_filename = str_replace(' ', '', $font_family) . '-' . $suffix;
		if ($extention) {
			$font_filename .= '.' . $extention;
		}
		return $font_filename;
	}

	public static function download_google_font($font_family, $font_weight, $font_style)
	{
		$font_filename = self::google_font_filename($font_family, $font_weight, $font_style);
		$font_url = self::google_font_url($font_family, $font_weight, $font_style);
		$font_url = str_replace(' ', '%20', $font_url);

		/** @var $formats array User-Agent => file extension */
		$formats = [' ' => '.ttf'];
		// also get woff2? doesn't seem required as all browsers currently support rendering ttf...
//		$formats['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36'] = '.woff';

		foreach ($formats as $user_agent => $extension) {
			$font_css = wp_remote_retrieve_body(wp_remote_get($font_url, [
				'headers' => [
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', // emulate browser
				],
				'user-agent' => $user_agent,  // emulate browser
				'httpversion' => '1.1',  // emulate browser
				'referer' => site_url(),  // emulate browser
			]));
			if (false !== strpos($font_css, 'Font family not found')) {
				var_dump($font_family, $font_weight, $font_style, $font_css, $font_url);
				exit;
			}
			if (!$font_css) {
				self::setError('font-family', __('Could not download font from Google Fonts.', Plugin::TEXT_DOMAIN) . ' ' . __('Please download yourself and upload here.', Plugin::TEXT_DOMAIN));
			}
			else {
				$font_css_parts = explode('@font-face', $font_css);
				$font_css = '@font-face' . end($font_css_parts);
				// use the last one, it should be latin. todo: verify; if not always latin last, build checks to actually GET latin.

				if (preg_match('@https?://[^)]+' . $extension . '@', $font_css, $n)) {
					$font_ttf = wp_remote_retrieve_body(wp_remote_get($n[0]));
					self::file_put_contents(self::storage() . '/' . $font_filename . $extension, $font_ttf);
				}
			}
		}

		return $font_family;
	}

	public static function google_font_url($font_family, $font_weight, $font_style): string
	{
		$italic = $font_style == 'italic' ? 'italic' : '';
		return 'https://fonts.googleapis.com/css?family=' . $font_family . ':' . $font_weight . $italic;
	}

	public static function setError($tag, $text)
	{
		if ('generic' == $tag) {
			$errors = get_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', []);
			$errors[] = $text;
			$errors = array_filter($errors);
			$errors = array_unique($errors);
			update_option(Plugin::DEFAULTS_PREFIX . '_admin_errors', $errors);
		}
		else {
			$errors = get_option(Plugin::DEFAULTS_PREFIX . '_errors', []);
			$errors[$tag] = $text;
			$errors = array_filter($errors);
			update_option(Plugin::DEFAULTS_PREFIX . '_errors', $errors);
		}
	}

	public static function file_put_contents($filename, $content)
	{
		// for security reasons, $filename must be in $this->storage()
		if (substr(trim($filename), 0, strlen(self::storage())) !== self::storage()) {
			return false;
		}
		$dirs = [];
		$dir = $filename; // we will be dirname-ing this

		while (($dir = dirname($dir)) && $dir && $dir !== '.' && $dir !== self::storage() && !is_dir($dir)) {
			array_unshift($dirs, $dir);
		}

		array_map('mkdir', $dirs);

		return file_put_contents($filename, $content);
	}

	public static function maybe_move_font()
	{
		if (is_admin() && ($font_id = get_option(Plugin::DEFAULTS_PREFIX . 'text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = Plugin::getInstance();
				update_option(Plugin::DEFAULTS_PREFIX . 'text__ttf_upload', false);
				$b = basename($font);
				$base = basename($font, '.ttf');
				if ($base === $b) {
					$base = basename($font, '.otf');
				}
				update_option(Plugin::DEFAULTS_PREFIX . 'text__font', $base);
				rename($font, $instance->storage() . '/' . basename($font));
				wp_delete_post($font_id);
			}
		}
	}

	public static function process_post()
	{
		if (is_admin() && current_user_can( Plugin::get_management_permission() ) && !empty($_GET['page']) && $_GET['page'] === Plugin::ADMIN_SLUG && !empty($_POST)) {
			$action = !empty($_REQUEST['bsi-action']) ? $_REQUEST['bsi-action'] : 'nop';
			switch ($action) {
				case 'save-settings':
					self::verify_nonce( $action );
					$valid_post_keys = Plugin::get_valid_POST_keys('admin');
					$fields = Plugin::field_list();

					foreach ($fields as $group => $_fields) {
						if ($group === 'admin' || $group === 'meta') { // skip groups arelady here
							continue;
						}
						$valid_post_keys = array_merge($valid_post_keys, Plugin::get_valid_POST_keys($group));
					}

					foreach ($_POST['branded_social_images'] as $namespace => $values) {
						if (is_array($values)) {
							foreach ($values as $key => $value) {
								if (!in_array($key, $valid_post_keys[$namespace])) {
									continue;
								}
								if ($key === 'text' && !empty($value)) {
									$value = strip_tags($value, '<br>');
								}
								update_option("$namespace$key", $value);
							}
						}
					}

					wp_redirect(remove_query_arg('bsi-action', add_query_arg('updated', 1)));
					exit;
				case 'purge-cache-confirm':
					self::verify_nonce( $action );
					Plugin::purge_cache();

					$purgable = Plugin::get_purgable_cache();
					if ($purgable) {
						self::setError('generic', sprintf(__('Not all cache items could be removed. Please try again, or check the cache folder yourself. Location: %s', Plugin::TEXT_DOMAIN), $base));
						wp_redirect(remove_query_arg('bsi-action', add_query_arg('purged', 'error')));
					}
					else {
						wp_redirect(remove_query_arg('bsi-action', add_query_arg('purged', 1)));
					}
					exit;
			}
		}
	}

	private static function weight_to_suffix($weight, $is_italic): string
	{
		$weight = intval(round($weight / 100) * 100);
		$weights = self::font_name_weights();

		if (!array_key_exists($weight, $weights) || (/* Special case; RegularItalic is just called Italic */ 400 == $weight && $is_italic)) {
			$suffix = '';
		}
		else {
			$suffix = $weights[$weight];
		}
		if ($is_italic) {
			$suffix .= 'Italic';
		}

		return $suffix;
	}

	/**
	 * @method font_name_weights
	 * @method font_weights
	 * These might seem duplicate, but they serve a very different function.
	 * Please do not try to refactor this ;)
	 */

	private static function font_name_weights(): array
	{
		return [
			100 => 'Thin',
			200 => 'ExtraLight',
			300 => 'Light',
			400 => 'Regular',
			500 => 'Medium',
			600 => 'SemiBold',
			700 => 'Bold',
			800 => 'ExtraBold',
			900 => 'Black',
		];
	}

	public static function font_weights(): array
	{
		return [
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
			'ultra bold' => 900,
		];
	}

	public static function getError($tag = null)
	{
		$errors = get_option(Plugin::DEFAULTS_PREFIX . '_errors', []);

		if ($tag) {
			$return = $errors[$tag];
			unset($errors[$tag]);
			$errors = array_filter($errors);
		}
		else {
			$return = $errors;
			$errors = [];
		}

		update_option(Plugin::DEFAULTS_PREFIX . '_errors', $errors);

		return $return;
	}

	private static function array_first(array $array)
	{
		return reset($array);
	}

	private static function nonce_field( $action, $referer = true, $echo = true )
	{
		return wp_nonce_field( "bsi-$action", '_bsinonce', $referer, $echo );
	}

	private static function verify_nonce($action)
	{
		if (!wp_verify_nonce($_POST['_bsinonce'] ?? false, "bsi-$action")) {
			wp_die(__('Nonce verification failed, please try again.', Plugin::TEXT_DOMAIN));
		}
	}
}
