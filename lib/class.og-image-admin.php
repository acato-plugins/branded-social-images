<?php

namespace Clearsite\Plugins\OGImage;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use RankMath;

class Admin {
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
		add_action( 'carbon_fields_register_fields', [static::class, 'carbon_fields']);

		// be late with this init to allow other instances to take priority. this way,
		// Carbon is only loaded here if it doesn't already exist
		add_action( 'after_setup_theme', [ static::class, 'carbon_load' ], 0302640605 );

		add_filter('wp_check_filetype_and_ext', function($result, $file, $filename, $mimes, $realmime){
			if (substr(strtolower($filename), -4, 4) == '.ttf') {
				$result['ext'] = 'ttf';
				$result['type'] = 'font/ttf';
				$result['proper_filename'] = $filename;
			}
			return $result;
		}, 11, 5);
		add_filter('upload_mimes', function($existing_mimes){
			$existing_mimes['ttf'] = 'font/ttf';
			return $existing_mimes;
		});

		add_action('admin_head', [static::class, 'maybe_move_font']);
		add_action('admin_enqueue_scripts', function(){
			wp_enqueue_script('cls-og-admin', plugins_url('admin/admin.js', __DIR__), 'jquery', filemtime(dirname(__DIR__) .'/admin/admin.js'), true);
			wp_localize_script('cls-og-admin', 'cls_og', ['preview_url' => get_permalink() .'og-image.png']);
		});
	}

	public static function carbon_load() {
		if (defined('Carbon_Fields\VERSION')) {
			// carbon already present
			if (version_compare(constant('Carbon_Fields\VERSION'), '3.0.0', '>=')) {
				// this is a problem.
				add_action('admin_notices', function(){
					?><div id="message" class="updated error notice-error"><p>Problem detected: CarbonFields v3 detected. CLS OG Image will function but cannot produce previews while editing a post. This is due to API changes in Carbon Fields, we're working on a fix.</p></div><?php
				});
			}
		}

		if (!class_exists(Carbon_Fields::class)) {
			require_once( __DIR__ .'/../vendor/autoload.php');
			Carbon_Fields::boot();
		}
	}

	public static function carbon_fields()
	{
		$fields = array(
			Field::make( 'image', 'cls_default_og_image', 'The default OG:Image for any page/post/... that has no OG:Image defined.' )->set_help_text('This should be a generic image that is applicable to the entire website.'),
			Field::make( 'text', 'cls_default_og_text', 'The default Text overlay for any page/post/... that has no OG:Title.' )->set_help_text('This should be a generic text that is applicable to the entire website.'),
		);

		self::carbon_field__fonts($fields);
		$fields[] = self::carbon_field__position('cls_default_og_text_position', true);

		$fields[] =	Field::make( 'image', 'cls_og_image_logo', 'Your logo' )->set_help_text('For best results, use PNG with transparency at at least (!) 600 pixels wide and/or high. If you get "gritty" results, use higher values.');
		$fields[] = self::carbon_field__position('cls_default_og_logo_position', 'bottom-right');
		$fields[] =	Field::make( 'text', 'cls_og_image_logo_size', 'Size' )->set_help_text('You can use a width (like 200), width and height (like 200x160) or a percentage (like 20%). This determines the bounding box, the logo aspect ratio will remain in tact.')->set_default_value('20%');

		Container::make( 'theme_options', __( 'OG Image by Clearsite' ) )
			->add_fields( $fields );

		// POSTS

		$fields = [];
		$fields[] = Field::make( 'image', 'cls_og_image', __( 'You can upload/select a specific OG Image here') );

		$fields[] = Field::make( 'checkbox', 'cls_og_text_enabled', __( 'Use text on this image?') )->set_default_value('yes')->set_help_text('Uncheck if you do not wish text on this image, or choose a position below');
		$fields[] = Field::make( 'text', 'cls_og_text', __( 'Text on image') );
		$fields[] = self::carbon_field__position('cls_og_text_position', get_site_option('_cls_default_og_text_position', 'bottom-right'));

		$fields[] = Field::make( 'checkbox', 'cls_og_logo_enabled', __( 'Use a logo on this image?') )->set_default_value('yes')->set_help_text('Uncheck if you do not wish a logo on this image, or choose a position below');
		$fields[] = self::carbon_field__position('cls_og_logo_position', get_site_option('_cls_default_og_logo_position', 'bottom-right'));

//		$fields[] = self::carbon_field__logo();

		if (defined('WPSEO_VERSION')) {
			$fields[0]->set_help_text('Yoast SEO has been detected. If you set-up an OG Image with Yoast and not here, the image selected with Yoast SEO will be used.');
		}
		// maybe RankMath?
		elseif (class_exists(RankMath::class)) {
			$fields[0]->set_help_text('SEO by Rank Math has been detected. If you set-up an OG Image with Rank Math and not here, the image selected with Rank Math will be used.');
		}
		elseif (!get_site_option('_cls_default_og_image')) {
			$fields[0]->set_help_text('No Fallback images have been detected. If you do not set-up an image here, no OG:Image will be available for this '. get_post_type());
		}

		$killswitch = Field::make( 'checkbox', 'cls_og_disabled', __( 'Check this box to disable OG by Clearsite for this post/page/item') )->set_help_text('This does NOT disable the OG image url, so you can still test it, but, the Clearsite OG image will not be advertised to browsers.<br /><br />If you use a plugin like Yoast SEO or Rank Math, their OG image might still be advertised. This checkbox does <strong>not</strong>strong> change that.');
		array_unshift($fields, $killswitch);

		Container::make('post_meta', __('OG Image'))
			->set_context('advanced')
			->set_priority('high')
			->add_fields( $fields );
	}

	private static function carbon_field__position($field_name, $default = false) {
		static $once;
		if (!$once) {
			$once = true;
			add_action('admin_footer', function() { ?><style><?php print file_get_contents(__DIR__ .'/../css/positions.css'); ?></style><?php });
		}

		$positions = [
			'top-left', 	'top',    'top-right',
			'left',     	'center', 'right',
			'bottom-left',  'bottom', 'bottom-right',
		];
		$positions = array_map(function($item){
			return plugins_url('img/'. $item .'.svg', __DIR__);
		}, array_combine($positions, $positions));

		$default_value = is_string($default) ? $default : reset($positions); // ffing hack!

		return Field::make('radio_image', $field_name, $default ? 'Default position' : 'Position')
			->set_options($positions)->set_default_value($default_value)
			->set_classes( 'position-grid' );
	}

	private static function carbon_field__fonts(&$fields) {
		// google fonts
		$field = Field::make('text', 'cls_default_og_text__font', 'Font');
		$field->set_help_text('If you want to use a Google font, search for it here: <a href="https://fonts.google.com" target="_blank">Google Fonts</a> and copy the font Name. Fill it in as <strong>google:the font name</strong>, for example; <strong>google:New Tegomin</strong>');
		$fields[] = $field;

		// TTF upload
		$fields[] = Field::make('file', 'cls_default_og_text__ttf_upload', 'Font upload')
			->set_help_text('You can upload your own font here, but this MUST be a TTF font-file. You <strong>AND YOU ALONE</strong> are responsible for the proper permissions and usage rights of the font on your website.')
			->set_type(['font/ttf']);
		return $fields;
	}

	public static function maybe_move_font()
	{
		if (is_admin() && ($font_id = get_site_option('_cls_default_og_text__ttf_upload'))) {
			$font = get_attached_file($font_id);
			if (is_file($font)) {
				$instance = self::getInstance();
				update_site_option('_cls_default_og_text__ttf_upload', false);
				update_site_option('_cls_default_og_text__font', basename($font));
				rename($font, $instance->storage . '/' . basename($font));
				wp_delete_post($font_id);
			}
		}
	}
}
