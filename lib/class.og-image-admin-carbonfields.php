<?php

/**
 * This is a known working but(t) ugly interface
 */

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Clearsite\Plugins\OGImage\Plugin;

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
		$advanced_fields = [];

		$fields[] = Field::make('image', self::CF_OPTION_PREFIX . 'image', __('You can upload/select a specific OG Image here'))->set_help_text("You can use " . ($support_webp ? "JPEG, PNG and WEBP" : "JPEG and PNG") . " as a source image, but the output will ALWAYS be PNG because of restrictions on Facebook and LinkedIn.");

		$fields[] = Field::make('checkbox', self::CF_OPTION_PREFIX . 'text_enabled', __('Use text on this image?'))->set_default_value('yes')->set_help_text('Uncheck if you do not wish text on this image, or choose a position below');
		$fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text', __('Text on image'))->set_help_text('If you leave this blank, the current page title is used as it appears in the webpage HTML. If you have Yoast SEO or RankMath installed, the title is taken from that.');
		self::carbon_field__color($fields, self::CF_OPTION_PREFIX . 'color', 'Text color', get_option(self::DEFAULTS_PREFIX . 'color', '#FFFFFFFF'));
		$fields[] = self::carbon_field__position(self::CF_OPTION_PREFIX . 'text_position', 'Text position', get_option(self::DEFAULTS_PREFIX . 'text_position', 'bottom-right'));
		self::carbon_field__color($advanced_fields, self::CF_OPTION_PREFIX . 'background_color', 'Text background color', get_option(self::DEFAULTS_PREFIX . 'background_color', '#66666666'));
		if ('on' === Plugin::FEATURE_STROKE) {
			self::carbon_field__color($advanced_fields, self::CF_OPTION_PREFIX . 'text_stroke_color', 'Text stroke color', get_option(self::DEFAULTS_PREFIX . 'text_stroke_color', '#00000000'));
			$advanced_fields[count($advanced_fields) - 1]->set_help_text('Text-stroke in image-software is not a real stroke and will behave weirdly with text-transparency.');
			$advanced_fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text_stroke', 'Stroke width')->set_default_value(get_option(self::DEFAULTS_PREFIX . 'text_stroke', '0'));
		}
		if ('on' === Plugin::FEATURE_SHADOW) {
			self::carbon_field__color($advanced_fields, self::CF_OPTION_PREFIX . 'text_shadow_color', 'Text shadow color', get_option(self::DEFAULTS_PREFIX . 'text_shadow', '#00000000'));
			$advanced_fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text_shadow_top', 'Shadow offset - vertical. Negative numbers to top, Positive numbers to bottom.')->set_default_value(get_option(self::DEFAULTS_PREFIX . 'shadow_top', '-2'));
			$advanced_fields[] = Field::make('text', self::CF_OPTION_PREFIX . 'text_shadow_left', 'Shadow offset - horizontal. Negative numbers to left, Positive numbers to right.')->set_default_value(get_option(self::DEFAULTS_PREFIX . 'shadow_left', '2'));
		}
		if ('simple' === Plugin::FEATURE_SHADOW) {
			$advanced_fields[] = Field::make('checkbox', self::CF_OPTION_PREFIX . 'text_shadow_enabled', 'Use a text shadow')->set_default_value(get_option(self::DEFAULTS_PREFIX . 'shadow_enabled', 'off'));
		}

		$advanced_fields[] = Field::make('checkbox', self::CF_OPTION_PREFIX . 'logo_enabled', __('Use a logo on this image?'))->set_default_value('yes')->set_help_text('Uncheck if you do not wish a logo on this image, or choose a position below');
		$advanced_fields[] = self::carbon_field__position(self::CF_OPTION_PREFIX . 'logo_position', 'Logo position', get_option(self::DEFAULTS_PREFIX . 'logo_position', 'bottom-right'));

//		$fields[] = self::carbon_field__logo();

		if (defined('WPSEO_VERSION')) {
			$fields[0]->set_help_text('Yoast SEO has been detected. If you set-up an OG Image with Yoast and not here, the image selected with Yoast SEO will be used.');
		} // maybe RankMath?
		elseif (class_exists(RankMath::class)) {
			$fields[0]->set_help_text('SEO by Rank Math has been detected. If you set-up an OG Image with Rank Math and not here, the image selected with Rank Math will be used.');
		} elseif (!get_option(self::DEFAULTS_PREFIX . 'image')) {
			$fields[0]->set_help_text('No Fallback images have been detected. If you do not set-up an image here, no OG:Image will be available for this ' . get_post_type());
		}

		$killswitch = Field::make('checkbox', self::CF_OPTION_PREFIX . 'disabled', __('Check this box to disable OG by Clearsite for this post/page/item'))->set_help_text('This does NOT disable the OG image url, so you can still test it, but, the Clearsite OG image will not be advertised to browsers.<br /><br />If you use a plugin like Yoast SEO or Rank Math, their OG image might still be advertised. This checkbox does <strong>not</strong>strong> change that.');
		array_unshift($fields, $killswitch);

		Container::make('post_meta', __('OG Image'))
			->set_context('advanced')
			->set_priority('high')
			->add_fields($fields);

		Container::make('post_meta', __('OG Image (advanced)'))
			->set_context('advanced')
			->add_fields($advanced_fields);
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
		if (is_admin() && ($font_id = get_option(self::DEFAULTS_PREFIX . 'text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = self::getInstance();
				update_option(self::DEFAULTS_PREFIX . 'text__ttf_upload', false);
				update_option(self::DEFAULTS_PREFIX . 'text__font', basename($font));
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
