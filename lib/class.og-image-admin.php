<?php

namespace Clearsite\Plugins\OGImage;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use RankMath;

class Admin_Native
{
	const OPTION_PREFIX = '_bsi_';
	const DEFAULTS_PREFIX = '_bsi_default_';
	const CF_OPTION_PREFIX = 'bsi_';
	const CF_DEFAULTS_PREFIX = 'bsi_default_';
	const SCRIPT_STYLE_HANDLE = 'bsi';
	const BSI_IMAGE_NAME = 'social-image.png';
	const ICON = '';
	const ADMIN_SLUG = 'branded-social-images';

	public $storage = '';

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	private static function get_font_list()
	{
		$fonts = glob(self::getInstance()->storage . '/*.ttf');
		sort($fonts);
		$options = [];
		foreach ($fonts as $font) {
			$options[basename($font)] = basename($font, '.ttf');
		}

		return $options;
	}

	private static function position_grid()
	{
		return [
			'top-left' => 'Top Left',
			'top' => 'Top Center',
			'top-right' => 'Top Right',
			'left' => 'Left Middle',
			'center' => 'Centered',
			'right' => 'Right Middle',
			'bottom-left' => 'Bottom Left',
			'bottom' => 'Bottom Center',
			'bottom-right' => 'Bottom Right',
		];
	}

	private static function field_list()
	{
		static $once, $support_webp;
		if (!$once) {
			// TODO: ImageMagick?! for now, GD only
			$support_webp = function_exists('imagewebp') || Plugin::maybe_fake_support_webp();
			$once = true;
		}

		$image_comment = '';
		if (defined('WPSEO_VERSION')) {
			$image_comment = '<br />Yoast SEO has been detected. If you set-up an OG Image with Yoast and not here, the image selected with Yoast SEO will be used.';
		} // maybe RankMath?
		elseif (class_exists(RankMath::class)) {
			$image_comment = '<br />SEO by Rank Math has been detected. If you set-up an OG Image with Rank Math and not here, the image selected with Rank Math will be used.';
		} elseif (!get_site_option(self::DEFAULTS_PREFIX . 'image')) {
			$image_comment = '<br />No Fallback images have been detected. If you do not set-up an image here, no OG:Image will be available for this ' . get_post_type();
		}

		$options = [
			'admin' => [
				'image' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'image', 'label' => 'The default OG:Image for any page/post/... that has no OG:Image defined.', 'comment' => 'todo: info', 'default' => false],
				'image_use_thumbnail' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use the WordPress Featured image, if selected, before using the default image selected above.', 'default' => true],

				'image_logo' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'label' => 'Your logo', 'comment' => 'For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.'],
				'logo_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid(), ';abel' => 'Default logo position', 'default' => 'bottom-right'],
				'image_logo_size' => ['namespace' => self::OPTION_PREFIX, 'slider', 'label' => 'Size', 'comment' => '', 'default' => '20%', 'attributes' => ['min' => 5, 'max' => 95]],


				'text' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'The default text to overlay if no other text or title can be found.', 'comment' => 'This should be a generic text that is applicable to the entire website.'],
				'text__font' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'select', 'label' => 'Font', 'options' => self::get_font_list()],
				'text__ttf_upload' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'file', 'types' => 'font/ttf', 'label' => 'Font upload'],
				'text__google_download' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Google Font Download', 'comment' => 'Enter a Google font name as it is listed on fonts.google.com'],

				'text_position' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid()],
				'color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'class' => 'color-picker', 'attributes' => 'rgba', 'label' => 'Default Text color', 'default' => '#FFFFFFFF'],
				'background_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'class' => 'color-picker', 'attributes' => 'rgba', 'label' => 'Default Text background color', 'default' => '#66666666'],

				'text_stroke_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'class' => 'color-picker', 'attributes' => 'rgba', 'label' => 'Stroke color', 'default' => '#00000000'],
				'text_stroke' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Default stroke width', 'default' => 0],

				'text_shadow_color' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Default Text shadow color', '#00000000'],
				'text_shadow_top' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.', 'default' => '-2'],
				'text_shadow_left' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.', 'default' => '2'],
				'text_shadow_enabled' => ['namespace' => self::DEFAULTS_PREFIX, 'type' => 'text', 'label' => 'Use a text shadow', 'default' => 'off'],
			],
			'meta' => [
				'disabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Check this box to disable OG by Clearsite for this post/page/item'],
				'image' => ['namespace' => self::OPTION_PREFIX, 'type' => 'image', 'label' => 'You can upload/select a specific OG Image here', 'comment' => 'You can use ' . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . ' as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.' . $image_comment],

				'text_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use text on this image?', 'default' => 'yes', 'comment' => 'Uncheck if you do not wish text on this image.'],
				'text' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Text on image', 'If you leave this blank, the current page title is used as it appears in the webpage HTML. If you have Yoast SEO or RankMath installed, the title is taken from that.'],
				'color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'class' => 'color-picker', 'attributes' => 'rgba', 'label' => 'Text color', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF')],
				'text_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid(), 'default' => get_site_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-right')],

				'background_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'class' => 'color-picker', 'attributes' => 'rgba', 'label' => 'Text background color', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666')],

				'text_stroke_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'class' => 'color-picker', 'attributes' => 'rgba', 'label' => 'Stroke color', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000')],
				'text_stroke' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Default stroke width', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'text_stroke', '0')],

				'text_shadow_color' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Text shadow color', get_site_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000')],
				'text_shadow_top' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2')],
				'text_shadow_left' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'shadow_left', '2')],
				'text_shadow_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'text', 'label' => 'Use a text shadow', 'default' => get_site_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off')],

				'logo_enabled' => ['namespace' => self::OPTION_PREFIX, 'type' => 'checkbox', 'label' => 'Use a logo on this image?', 'default' => 'yes', 'comment' => 'Uncheck if you do not wish a logo on this image, or choose a position below'],
				'logo_position' => ['namespace' => self::OPTION_PREFIX, 'type' => 'radios', 'class' => 'position-grid', 'options' => self::position_grid(), 'default' => get_site_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right')],
			]
		];

		if ('on' !== Plugin::FEATURE_STROKE) {
			unset($options['admin'][self::CF_DEFAULTS_PREFIX . 'text_stroke_color']);
			unset($options['admin'][self::CF_DEFAULTS_PREFIX . 'text_stroke']);
			unset($options['meta'][self::CF_OPTION_PREFIX . 'text_stroke_color']);
			unset($options['meta'][self::CF_OPTION_PREFIX . 'text_stroke']);
		}

		if ('on' !== Plugin::FEATURE_SHADOW) {
			unset($options['admin'][self::CF_DEFAULTS_PREFIX . 'text_shadow_color']);
			unset($options['admin'][self::CF_DEFAULTS_PREFIX . 'text_shadow_top']);
			unset($options['admin'][self::CF_DEFAULTS_PREFIX . 'text_shadow_left']);
			unset($options['meta'][self::CF_OPTION_PREFIX . 'text_shadow_color']);
			unset($options['meta'][self::CF_OPTION_PREFIX . 'text_shadow_top']);
			unset($options['meta'][self::CF_OPTION_PREFIX . 'text_shadow_left']);
		}
		if ('simple' !== Plugin::FEATURE_SHADOW) {
			unset($options['admin'][self::CF_DEFAULTS_PREFIX . 'text_shadow_enabled']);
			unset($options['meta'][self::CF_OPTION_PREFIX . 'text_shadow_enabled']);
		}

		foreach ($options['admin'] as $field => $_) {
			$options['admin'][$field]['value'] = get_site_option($_['namespace'] . $field, !empty($_['default']) ? $_['default'] : null);
		}

		if (get_the_ID()) {
			foreach ($options['meta'] as $field => $_) {
				$options['meta'][$field]['value'] = get_post_meta(get_the_ID(), $_['namespace'] . $field, true) ?: (!empty($_['default']) ? $_['default'] : null);
			}
		}

		return $options;
	}

	public static function render_options($options, $filter = [])
	{

	}

	public static function show_editor($fields)
	{
		$image = $fields['image']['value'];
		if (is_numeric($image)) {
			$image = wp_get_attachment_url($image);
		}
		?>
		<?php self::render_options($fields, ['disabled']); ?>
		<div id="branded-social-images-editor">
			<div class="area--background">
				<div class="background" style="background-image:url('<?php print esc_attr($image); ?>')"/>
			</div>
			<div class="area--options">
				<?php self::render_options($fields, [
					'image', 'image_use_thumbnail',
					'image_logo', 'logo_position', 'image_logo_size',
					'background_color',
				]); ?>
			</div>
			<div class="area--settings">
				<?php self::render_options($fields, [
					'text', 'text__font', 'text__ttf_upload', 'text_position', 'color',
					'text__google_download',
					'text_shadow_color', 'text_shadow_top', 'text_shadow_left', 'text_shadow_enabled',
					'text_stroke_color', 'text_stroke',
				]); ?>
			</div>
		</div>
		<?php
	}

	public static function admin_panel()
	{
		$fields = self::field_list()['admin'];
		?>
		<div class="wrap">
			<h2>Branded Social Images</h2>
			<div>
				<?php self::show_editor($fields); ?>
			</div>
			<p>Branded Social Images is a free plugin by Clearsite. We are working on a full-featured Pro version,
				please let us know what you think of this plugin and what you wish to see in the Pro version. <a
					href="mailto:branded-social-images@clearsite.nl">Contact us here</a>.</p>
		</div>
		<?php
	}

	public function __construct()
	{
		add_filter('wp_check_filetype_and_ext', function ($result, $file, $filename, $mimes, $realmime) {
			if (substr(strtolower($filename), -4, 4) == '.ttf') {
				$result['ext'] = 'ttf';
				$result['type'] = 'font/ttf';
				$result['proper_filename'] = $filename;
			}
			return $result;
		}, 11, 5);
		add_filter('upload_mimes', function ($existing_mimes) {
			$existing_mimes['ttf'] = 'font/ttf';
			return $existing_mimes;
		});

		add_action('admin_head', [static::class, 'maybe_move_font']);
		add_action('admin_head', [static::class, 'process_post'], 11);
		add_action('admin_enqueue_scripts', function () {
			wp_enqueue_script(self::SCRIPT_STYLE_HANDLE, plugins_url('admin/admin.js', __DIR__), ['jquery', 'jquery-ui-slider'], filemtime(dirname(__DIR__) . '/admin/admin.js'), true);
			wp_localize_script(self::SCRIPT_STYLE_HANDLE, 'bsi_settings', ['preview_url' => get_permalink() . self::BSI_IMAGE_NAME]);

			wp_enqueue_style(self::SCRIPT_STYLE_HANDLE, plugins_url('css/admin.css', __DIR__), '', filemtime(dirname(__DIR__) . '/css/admin.css'), 'all');
		});

		add_action('admin_menu', function () {
			add_menu_page('Branded Social Images', 'Branded Social Images', 'edit_posts', self::ADMIN_SLUG, [self::class, 'admin_panel'], self::ICON);
		});
	}

	public static function maybe_move_font()
	{
		if (is_admin() && ($font_id = get_site_option(self::DEFAULTS_PREFIX . 'text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = self::getInstance();
				update_site_option(self::DEFAULTS_PREFIX . 'text__ttf_upload', false);
				update_site_option(self::DEFAULTS_PREFIX . 'text__font', basename($font));
				rename($font, $instance->storage . '/' . basename($font));
				wp_delete_post($font_id);
			}
		}
	}

	public static function process_post()
	{
		if (is_admin() && !empty($_GET['page']) && $_GET['page'] === self::ADMIN_SLUG && !empty($_POST)) {
			// handle $_POST
		}
	}

	private static function hex_to_hex_opacity($hex_color): array
	{
		if (substr($hex_color, 0, 1) !== '#') {
			$hex_color = '#ffffffff';
		}
		$hex_values = str_split(substr($hex_color . 'FF', 1, 8), 6);

		return [$hex_values[0], intval((hexdec($hex_values[1]) + 1) / 256 * 100)];
	}

	public static function font_weights()
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
			'ultra bold' => 800,
		];
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
}

class Admin_CarbonFields
{
	const OPTION_PREFIX = '_bsi_';
	const DEFAULTS_PREFIX = '_bsi_default_';
	const CF_OPTION_PREFIX = 'bsi_';
	const CF_DEFAULTS_PREFIX = 'bsi_default_';
	const SCRIPT_STYLE_HANDLE = 'bsi';
	const BSI_IMAGE_NAME = 'social-image.png';

	public $storage = '';

	public static function getInstance()
	{
		static $instance;
		if (!$instance) {
			$instance = new static();
		}

		return $instance;
	}

	public function __construct()
	{
		add_action('carbon_fields_register_fields', [static::class, 'carbon_fields']);

		// be late with this init to allow other instances to take priority. this way,
		// Carbon is only loaded here if it doesn't already exist
		add_action('after_setup_theme', [static::class, 'carbon_load'], 0302640605);

		add_filter('wp_check_filetype_and_ext', function ($result, $file, $filename, $mimes, $realmime) {
			if (substr(strtolower($filename), -4, 4) == '.ttf') {
				$result['ext'] = 'ttf';
				$result['type'] = 'font/ttf';
				$result['proper_filename'] = $filename;
			}
			return $result;
		}, 11, 5);
		add_filter('upload_mimes', function ($existing_mimes) {
			$existing_mimes['ttf'] = 'font/ttf';
			return $existing_mimes;
		});

		add_action('admin_head', [static::class, 'maybe_move_font']);
		add_action('admin_enqueue_scripts', function () {
			wp_enqueue_script(self::SCRIPT_STYLE_HANDLE, plugins_url('admin/admin.js', __DIR__), ['jquery', 'jquery-ui-slider'], filemtime(dirname(__DIR__) . '/admin/admin.js'), true);
			wp_localize_script(self::SCRIPT_STYLE_HANDLE, 'bsi_settings', ['preview_url' => get_permalink() . self::BSI_IMAGE_NAME]);

			wp_enqueue_style(self::SCRIPT_STYLE_HANDLE, plugins_url('css/admin.css', __DIR__), '', filemtime(dirname(__DIR__) . '/css/admin.css'), 'all');
		});
	}

	public static function carbon_load()
	{
		if (defined('Carbon_Fields\VERSION')) {
			// carbon already present
			if (version_compare(constant('Carbon_Fields\VERSION'), '3.0.0', '>=')) {
				// this is a problem.
				add_filter('admin_body_class', function ($classList) {
					$classList .= ' carbon-fields-3';
					return $classList;
				});
			}
		}

		if (!class_exists(Carbon_Fields::class)) {
			require_once(__DIR__ . '/../admin/vendor/autoload.php');
			Carbon_Fields::boot();
		}
	}

	public static function carbon_fields()
	{
		// TODO: ImageMagick?! for now, GD only
		$support_webp = function_exists('imagewebp') || Plugin::maybe_fake_support_webp();

		$selection = [];
		$selection[] = 'The image selected with this plugin at the post-edit page.';
		if (defined('WPSEO_VERSION')) {
			$selection[] = 'The image selected with Yoast SEO at the post-edit page.';
		}
		if (class_exists(RankMath::class)) {
			$selection[] = 'The image selected with RankMath at the post-edit page.';
		}
		$selection[] = 'The image selected as featured image at the post-edit page (if enabled below).';
		$selection[] = 'The image selected here as "Default OG:Image".';

		$selection = '<ul><li>' . implode('</li><li>', $selection) . '</li></ul>';

		$selection .= "You can use " . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . " as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.";

		$fields = array(
			Field::make('image', self::CF_DEFAULTS_PREFIX . 'image', 'The default OG:Image for any page/post/... that has no OG:Image defined.')->set_help_text(
				'This should be a generic image that is applicable to the entire website.' .
				'<br />' .
				'The Image to be used as OG:Image is selected in the following priority;' .
				'<br />' .
				$selection),
			Field::make('checkbox', self::CF_OPTION_PREFIX . 'image_use_thumbnail', 'Use the WordPress Featured image, if selected, before using the default image selected above.')->set_default_value(true),
			Field::make('text', self::CF_DEFAULTS_PREFIX . 'text', 'The default text to overlay if no other text or title can be found.')->set_help_text('This should be a generic text that is applicable to the entire website.'),
		);

		self::carbon_field__fonts($fields);
		$fields[] = self::carbon_field__position(self::CF_DEFAULTS_PREFIX . 'text_position', 'Default text position', true);

		self::carbon_field__color($fields, self::CF_DEFAULTS_PREFIX . 'color', 'Default Text color', '#FFFFFFFF');
		self::carbon_field__color($fields, self::CF_DEFAULTS_PREFIX . 'background_color', 'Default Text background color', '#66666666');

		if ('on' === Plugin::FEATURE_STROKE) {
			self::carbon_field__color($fields, self::CF_DEFAULTS_PREFIX . 'text_stroke_color', 'Default Text stroke color', '#00000000');
			$fields[count($fields) - 1]->set_help_text('Text-stroke in image-software is not a real stroke and will behave weirdly with text-transparency.');
			$fields[] = Field::make('text', self::CF_DEFAULTS_PREFIX . 'text_stroke', 'Default stroke width')->set_default_value(0);
		}
		if ('on' === Plugin::FEATURE_SHADOW) {
			self::carbon_field__color($fields, self::CF_DEFAULTS_PREFIX . 'text_shadow_color', 'Default Text shadow color', '#00000000');
			$fields[] = Field::make('text', self::CF_DEFAULTS_PREFIX . 'text_shadow_top', 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.')->set_default_value('-2');
			$fields[] = Field::make('text', self::CF_DEFAULTS_PREFIX . 'text_shadow_left', 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.')->set_default_value('2');
		}
		if ('simple' === Plugin::FEATURE_SHADOW) {
			$fields[] = Field::make('checkbox', self::CF_DEFAULTS_PREFIX . 'text_shadow_enabled', 'Use a text shadow')->set_default_value('off');
		}
		$fields[] = Field::make('image', self::CF_OPTION_PREFIX . 'image_logo', 'Your logo')->set_help_text('For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.');
		$fields[] = self::carbon_field__position(self::CF_DEFAULTS_PREFIX . 'logo_position', 'Default logo position', 'bottom-right');
		$fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'image_logo_size', 'Size')->set_help_text('Logo scale in %')->set_default_value(20)->set_attributes(['type' => 'number', 'maxLength' => 2, 'min' => 5, 'max' => 95, 'step' => 1])->set_classes('add-slider');

		Container::make('theme_options', __('Branded Social Images'))
			->add_fields($fields);

		// POSTS

		$fields = [];

		$fields[] = Field::make('image', self::CF_OPTION_PREFIX . 'image', __('You can upload/select a specific OG Image here'))->set_help_text("You can use " . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . " as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.");

		$fields[] = Field::make('checkbox', self::CF_OPTION_PREFIX . 'text_enabled', __('Use text on this image?'))->set_default_value('yes')->set_help_text('Uncheck if you do not wish text on this image, or choose a position below');
		$fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text', __('Text on image'))->set_help_text('If you leave this blank, the current page title is used as it appears in the webpage HTML. If you have Yoast SEO or RankMath installed, the title is taken from that.');
		self::carbon_field__color($fields, self::CF_OPTION_PREFIX . 'color', 'Text color', get_site_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF'));
		$fields[] = self::carbon_field__position(self::CF_OPTION_PREFIX . 'text_position', 'Text position', get_site_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-right'));
		self::carbon_field__color($fields, self::CF_OPTION_PREFIX . 'background_color', 'Text background color', get_site_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666'));
		if ('on' === Plugin::FEATURE_STROKE) {
			self::carbon_field__color($fields, self::CF_OPTION_PREFIX . 'text_stroke_color', 'Text stroke color', get_site_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000'));
			$fields[count($fields) - 1]->set_help_text('Text-stroke in image-software is not a real stroke and will behave weirdly with text-transparency.');
			$fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text_stroke', 'Stroke width')->set_default_value(get_site_option(self::DEFAULTS_PREFIX . 'text_stroke', '0'));
		}
		if ('on' === Plugin::FEATURE_SHADOW) {
			self::carbon_field__color($fields, self::CF_OPTION_PREFIX . 'text_shadow_color', 'Text shadow color', get_site_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000'));
			$fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text_shadow_top', 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.')->set_default_value(get_site_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2'));
			$fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text_shadow_left', 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.')->set_default_value(get_site_option(self::DEFAULTS_PREFIX . 'shadow_left', '2'));
		}
		if ('simple' === Plugin::FEATURE_SHADOW) {
			$fields[] = Field::make('checkbox', self::CF_OPTION_PREFIX . 'text_shadow_enabled', 'Use a text shadow')->set_default_value(get_site_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off'));
		}

		$fields[] = Field::make('checkbox', self::CF_OPTION_PREFIX . 'logo_enabled', __('Use a logo on this image?'))->set_default_value('yes')->set_help_text('Uncheck if you do not wish a logo on this image, or choose a position below');
		$fields[] = self::carbon_field__position(self::CF_OPTION_PREFIX . 'logo_position', 'Logo position', get_site_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right'));

//		$fields[] = self::carbon_field__logo();

		if (defined('WPSEO_VERSION')) {
			$fields[0]->set_help_text('Yoast SEO has been detected. If you set-up an OG Image with Yoast and not here, the image selected with Yoast SEO will be used.');
		} // maybe RankMath?
		elseif (class_exists(RankMath::class)) {
			$fields[0]->set_help_text('SEO by Rank Math has been detected. If you set-up an OG Image with Rank Math and not here, the image selected with Rank Math will be used.');
		} elseif (!get_site_option(self::DEFAULTS_PREFIX . 'image')) {
			$fields[0]->set_help_text('No Fallback images have been detected. If you do not set-up an image here, no OG:Image will be available for this ' . get_post_type());
		}

		$killswitch = Field::make('checkbox', self::CF_OPTION_PREFIX . 'disabled', __('Check this box to disable OG by Clearsite for this post/page/item'))->set_help_text('This does NOT disable the OG image url, so you can still test it, but, the Clearsite OG image will not be advertised to browsers.<br /><br />If you use a plugin like Yoast SEO or Rank Math, their OG image might still be advertised. This checkbox does <strong>not</strong>strong> change that.');
		array_unshift($fields, $killswitch);

		Container::make('post_meta', __('OG Image'))
			->set_context('advanced')
			->set_priority('high')
			->add_fields($fields);
	}

	private static function carbon_field__position($field_name, $field_label, $default = false)
	{
		$positions = static::position_grid();

		$default_value = is_string($default) ? $default : reset($positions); // ffing hack!

		return Field::make('radio', $field_name, $field_label)
			->set_options($positions)->set_default_value($default_value)
			->set_classes('position-grid');
	}

	private static function carbon_field__fonts(&$fields)
	{
		// google fonts
		$field = Field::make('text', self::CF_DEFAULTS_PREFIX . 'text__font', 'Font');
		$field->set_help_text('If you want to use a Google font, search for it here: <a href="https://fonts.google.com" target="_blank">Google Fonts</a> and copy the font Name. Fill it in as <strong>google:the font name</strong>, for example; <strong>google:New Tegomin</strong>');
		$fields[] = $field;

		// TTF upload
		$fields[] = Field::make('file', self::CF_DEFAULTS_PREFIX . 'text__ttf_upload', 'Font upload')
			->set_help_text('You can upload your own font here, but this MUST be a TTF font-file. You <strong>AND YOU ALONE</strong> are responsible for the proper permissions and usage rights of the font on your website.')
			->set_type(['font/ttf']);
	}

	private static function carbon_field__color(&$fields, $field_name, $field_label, $default_value = false)
	{
		// google fonts
		$field = Field::make('color', $field_name, $field_label);
		if ($default_value && strlen($default_value) > 7) { // default has alpha channel
			$field->set_alpha_enabled(true);
		}
		if ($default_value) {
			$field->set_default_value($default_value);
		}
		$field->set_palette([$default_value, '#FFFFFFFF', '#00000000']);
		list($hex, $dec) = self::hex_to_hex_opacity($default_value);
		$field->set_help_text('The default color for this option is: ' . $hex . ', ' . $dec . '%');
		$fields[] = $field;
	}

	public static function maybe_move_font()
	{
		if (is_admin() && ($font_id = get_site_option(self::DEFAULTS_PREFIX . 'text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = self::getInstance();
				update_site_option(self::DEFAULTS_PREFIX . 'text__ttf_upload', false);
				update_site_option(self::DEFAULTS_PREFIX . 'text__font', basename($font));
				rename($font, $instance->storage . '/' . basename($font));
				wp_delete_post($font_id);
			}
		}
	}

	private static function hex_to_hex_opacity($hex_color): array
	{
		if (substr($hex_color, 0, 1) !== '#') {
			$hex_color = '#ffffffff';
		}
		$hex_values = str_split(substr($hex_color . 'FF', 1, 8), 6);

		return [$hex_values[0], intval((hexdec($hex_values[1]) + 1) / 256 * 100)];
	}

	public static function font_weights()
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
			'ultra bold' => 800,
		];
	}

	private static function position_grid()
	{
		return [
			'top-left' => 'Top Left',
			'top' => 'Top Center',
			'top-right' => 'Top Right',
			'left' => 'Left Middle',
			'center' => 'Centered',
			'right' => 'Right Middle',
			'bottom-left' => 'Bottom Left',
			'bottom' => 'Bottom Center',
			'bottom-right' => 'Bottom Right',
		];
	}
}

class Admin extends Admin_CarbonFields
{
}
