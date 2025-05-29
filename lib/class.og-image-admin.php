<?php
/**
 * Admin class for the OGImage plugin.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Plugins\OGImage;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

use Acato\Tools\HTML_Inputs;
use WP_Term;

/**
 * Admin class for the OGImage plugin.
 *
 * This class handles the admin panel, settings, and other admin-related functionality.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Get the singleton instance of the Admin class.
	 *
	 * @return Admin The singleton instance of the Admin class.
	 */
	public static function instance() {
		static $instance;
		if ( ! $instance ) {
			$instance = new static();
		}

		return $instance;
	}

	/**
	 * The constructor for the Admin class.
	 *
	 * This sets up various hooks and filters for the plugin, including file type checks,
	 * mime type additions, admin menu creation, and script/style enqueuing.
	 */
	public function __construct() {
		add_filter(
			'wp_check_filetype_and_ext',
			function ( $result, $file, $filename ) {
				if ( substr( strtolower( $filename ), - 4, 4 ) === '.ttf' ) {
					$result['ext']             = 'ttf';
					$result['type']            = 'font/ttf';
					$result['proper_filename'] = $filename;
				}
				if ( substr( strtolower( $filename ), - 4, 4 ) === '.otf' ) {
					$result['ext']             = 'otf';
					$result['type']            = 'font/otf';
					$result['proper_filename'] = $filename;
				}

				return $result;
			},
			11,
			3
		);

		add_filter(
			'upload_mimes',
			function ( $existing_mimes ) {
				$existing_mimes['ttf'] = 'font/ttf';
				$existing_mimes['otf'] = 'font/otf';

				return $existing_mimes;
			}
		);

		add_action( 'admin_head', [ static::class, 'maybe_move_font' ] );
		add_action( 'admin_head', [ static::class, 'add_fontface_definitions' ] );
		add_action( 'admin_init', [ static::class, 'process_post' ], 11 );
		add_action(
			'admin_enqueue_scripts',
			function () {
				$script = ( defined( 'BSI_UNMINIFIED' ) ? 'admin/admin.js' : 'admin/admin.min.js' );
				$style  = ( defined( 'BSI_UNMINIFIED' ) ? 'admin/admin.min.css' : 'admin/admin.css' );
				wp_enqueue_script(
					Plugin::SCRIPT_STYLE_HANDLE,
					plugins_url( $script, __DIR__ ),
					[
						'jquery',
						'jquery-ui-slider',
					],
					filemtime( dirname( __DIR__ ) . '/' . $script ),
					true
				);
				wp_localize_script(
					Plugin::SCRIPT_STYLE_HANDLE,
					'bsi_settings',
					[
						'preview_url'     => get_permalink() . Plugin::output_filename(),
						'image_size_name' => Plugin::IMAGE_SIZE_NAME,
						'title_format'    => Plugin::title_format( 1, true ),
						'text'            => [
							'image_upload_title'  => __( 'Select an image or upload one.', 'bsi' ),
							'image_upload_button' => __( 'Use this image', 'bsi' ),
							'file_upload_title'   => __( 'Select an file or upload one.', 'bsi' ),
							'file_upload_button'  => __( 'Use this file', 'bsi' ),

						],
					]
				);

				wp_enqueue_style( Plugin::SCRIPT_STYLE_HANDLE, plugins_url( $style, __DIR__ ), '', filemtime( dirname( __DIR__ ) . '/' . $style ) );
			}
		);

		add_action(
			'admin_menu',
			function () {
				$location_setting = get_option( Plugin::DEFAULTS_PREFIX . 'menu_location', 'main' );
				$location         = apply_filters( 'bsi_admin_menu_location', $location_setting );
				if ( 'main' === $location ) {
					add_menu_page(
						'Branded Social Images',
						'Branded Social Images',
						Plugin::get_management_permission(),
						Plugin::ADMIN_SLUG,
						[
							self::class,
							'admin_panel',
						],
						self::admin_icon()
					);
				} else {
					$parent = 'options-general.php';
					if ( 'media' === $location_setting ) {
						$parent = 'upload.php';
					}
					add_submenu_page(
						$parent,
						'Branded Social Images',
						'Branded Social Images',
						Plugin::get_management_permission(),
						Plugin::ADMIN_SLUG,
						[
							self::class,
							'admin_panel',
						]
					);
				}
			}
		);

		add_action( 'admin_init', [ static::class, 'sanitize_fonts' ] );

		add_filter(
			'image_size_names_choose',
			function ( $default_sizes ) {
				// Future: support the experimental ::AA feature here.
				return array_merge(
					$default_sizes,
					[ Plugin::IMAGE_SIZE_NAME => __( 'The OG:Image recommended size', 'bsi' ) ]
				);
			}
		);

		/**
		 * Posts.
		 */

		add_action( 'save_post', [ static::class, 'save_post_meta_data' ] );
		add_action( 'add_meta_boxes', [ static::class, 'add_post_meta_boxes' ] );

		/**
		 * Categories.
		 */

		$taxonomies = apply_filters( 'bsi_taxonomies', [] );
		foreach ( $taxonomies as $taxonomy ) {
			add_action(
				'create_' . $taxonomy,
				[
					static::class,
					'save_category_meta_data',
				]
			);
			add_action( 'edit_' . $taxonomy, [ static::class, 'save_category_meta_data' ] );
			add_action( $taxonomy . '_add_form_fields', [ static::class, 'add_category_meta_boxes' ] );
			add_action( $taxonomy . '_edit_form', [ static::class, 'add_category_meta_boxes' ] );
		}

		add_action( 'admin_notices', [ static::class, 'admin_notices' ] );

		add_filter( 'plugin_action_links', [ static::class, 'add_settings_link' ], 10, 2 );
		add_filter( 'network_admin_plugin_action_links', [ static::class, 'add_settings_link' ], 10, 2 );

		add_action( 'wp_ajax_' . Plugin::ADMIN_SLUG . '_get-font', [ static::class, 'wp_ajax_bsi_get_font' ] );

		add_action(
			'bsi_footer',
			function () {
				$footer = '';

				$footer .= '<p>';
				$footer .=
					// translators: %1$s is the plugin URL, %2$s is the author URL.
					sprintf( __( '<a href="%1$s" target="_blank">Branded Social Images</a> is a free plugin by <a href="%2$s" target="_blank">Acato</a>.', 'bsi' ), Plugin::PLUGIN_URL_WPORG, Plugin::AUTHOR_URL_INFO )
					. ' ' . __( 'Please let us know what you think of this plugin and what you wish to see in future versions.', 'bsi' )
					// translators: %s is the contact URL.
					. ' ' . sprintf( __( '<a href="%s" target="_blank">Contact us here</a>.', 'bsi' ), Plugin::BSI_URL_CONTACT );
				$footer .= '</p>';

				$object_id   = QueriedObject::instance()->object_id;
				$base_type   = QueriedObject::instance()->base_type;
				$object_type = QueriedObject::instance()->object_type;

				if ( $object_id ) {
					$footer .= '<ul>';
					if ( get_the_ID() ) {
						$footer .=
							'<li>' . sprintf(
							// translators: %1$s is the URL to the social image inspector, %2$s is the name of the inspector.
								__( 'Use <a href="%1$s" target="_blank">%2$s</a> to preview what your social image looks like on social media.', 'bsi' ),
								sprintf( Plugin::EXTERNAL_INSPECTOR, self::encode_url_for_external_tool( get_permalink( get_the_ID() ) ) ),
								Plugin::EXTERNAL_INSPECTOR_NAME
							) . '</li>';

						$footer .=
							'<li>' . sprintf(
							// translators: %s is the URL to the social image of this post.
								__( '<a href="%s" target="_blank">Show the social-image of this post</a>.', 'bsi' ),
								Plugin::get_og_image_url( get_the_ID(), get_post_type(), 'post' )
							) . '</li>';

						$footer .=
							'<li>' . sprintf(
							// translators: %s is the URL to the debug information of the social image of this post.
								__( '<a href="%s" target="_blank">Show debug information</a> for the social-image of this post.', 'bsi' ),
								add_query_arg( 'debug', 'BSI', Plugin::get_og_image_url( get_the_ID(), get_post_type(), 'post' ) )
							) . '</li>';
					}

					if ( 'category' === $base_type && $object_id ) {
						$term = get_term( $object_id, $object_type );
						if ( $term instanceof WP_Term ) {
							$footer .=
								'<li>' . sprintf(
								// translators: %1$s is the URL to the social image inspector, %2$s is the name of the inspector.
									__( 'Use <a href="%1$s" target="_blank">%2$s</a> to preview what your social image looks like on social media.', 'bsi' ),
									sprintf( Plugin::EXTERNAL_INSPECTOR, self::encode_url_for_external_tool( get_term_link( $term ) ) ),
									Plugin::EXTERNAL_INSPECTOR_NAME
								) . '</li>';

							$footer .=
								'<li>' . sprintf(
								// translators: %s is the URL to the social image of this category.
									__( '<a href="%s" target="_blank">Show the social-image of this category</a>.', 'bsi' ),
									Plugin::get_og_image_url( $object_id, $object_type, 'category' )
								) . '</li>';

							$footer .=
								'<li>' . sprintf(
								// translators: %s is the URL to the debug information of the social image of this category.
									__( '<a href="%s" target="_blank">Show debug information</a> for the social-image of this category.', 'bsi' ),
									add_query_arg( 'debug', 'BSI', Plugin::get_og_image_url( $object_id, $object_type, 'category' ) )
								) . '</li>';
						}
					}
					$footer .= '</ul>';
				}

				print wp_kses_post( $footer );
			}
		);

		add_action( 'bsi_settings_panels', [ static::class, 'config_panel' ] );
		add_action( 'bsi_settings_panels', [ static::class, 'log_panel' ] );
	}

	/**
	 * Get the URL to the admin icon.
	 *
	 * @return string The URL to the admin icon.
	 */
	public static function admin_icon() {
		if ( is_file( dirname( __DIR__ ) . '/assets/' . basename( '/' . Plugin::ADMIN_ICON ) ) ) {
			return plugins_url( '/assets/' . basename( '/' . Plugin::ADMIN_ICON ), __DIR__ );
		}

		return Plugin::ADMIN_ICON;
	}

	/**
	 * Get the base settings for the plugin.
	 *
	 * Note: This does not change the defaults, nor is it used in this fashion anymore.
	 * Note: This needs to be refactored!
	 *
	 * @return array The base settings for the plugin.
	 */
	public static function base_settings() {
		return [
			// colors are RGBA in hex format.
			'text_options' => [
				'enabled'             => 'on',
				'left'                => null,
				'bottom'              => null,
				'top'                 => null,
				'right'               => null,
				'position'            => 'bottom-left',
				'font-size'           => Plugin::DEF_FONT_SIZE,
				'color'               => '#ffffffff',
				'line-height'         => Plugin::DEF_FONT_SIZE * 1.25,
				'font-file'           => '',
				'font-family'         => 'Roboto-Bold',
				'font-weight'         => 700,
				'font-style'          => 'normal',
				'display'             => 'inline',
				// determines background-dimensions block: 100% width??? inline-block: rectangle around all text, inline: behind text only.
				'padding'             => '20',
				// background padding.
				'background-color'    => '#66666666',
				'background-enabled'  => 'on',
				'text-shadow-color'   => '',
				'text-shadow-left'    => '2',
				'text-shadow-top'     => '-2',
				'text-shadow-enabled' => 'off',
				'text-stroke-color'   => '',
				'text-stroke'         => '2',
			],
			'logo_options' => [
				'enabled'  => 'on',
				'position' => 'top-left',
				'left'     => null,
				'bottom'   => null,
				'top'      => null,
				'right'    => null,
				'size'     => get_option( Plugin::OPTION_PREFIX . 'image_logo_size', '100' ),
			],
		];
	}

	/**
	 * Output configuration panel.
	 *
	 * @return void
	 */
	public static function admin_panel() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we're not storing the data, no risk of XSS.
		$action = empty( $_REQUEST['bsi-action'] ) ? 'show-config' : $_REQUEST['bsi-action'];

		?>
		<div class="wrap">
			<h2>Branded Social Images <span
					style="opacity: 0.2"><?php print esc_html( Plugin::get_version() ); ?></span></h2>
			<?php
			$errors = self::get_errors();
			foreach ( $errors as $error ) {
				?>
				<div class="updated error"><p><?php print wp_kses_post( $error ); ?></p></div>
				<?php
			}
			?>
			<div>
				<?php
				switch ( $action ) {
					case 'purge-cache':
						// Purgables for images.
						$purgable = Plugin::get_purgable_cache( 'images' );
						// Purgables for directories.
						$purgable_dirs = Plugin::get_purgable_cache( 'directories' );

						if ( ! $purgable && ! $purgable_dirs ) {
							esc_html_e( 'The cache is empty', 'bsi' );
							?>
							<br/><a
								class="action button-primary"
								href="<?php print esc_attr( remove_query_arg( 'bsi-action' ) ); ?>"><?php esc_html_e( 'Ok', 'bsi' ); ?></a>
							<?php
							break;
						} else {
							// translators: %1$d is the number of images, %2$d is the number of directories.
							print esc_html( sprintf( __( 'This will clear the cache, %1$d image(s) and %2$d folder(s) will be removed. New images will be generated on demand.', 'bsi' ), count( $purgable ), count( $purgable_dirs ) ) );
						}
						?>
						<form
							method="POST"
							action="<?php print esc_attr( add_query_arg( 'bsi-action', 'purge-cache-confirm' ) ); ?>">
							<input type="hidden" name="bsi-action" value="purge-cache-confirm"/>
							<?php self::nonce_field( 'purge-cache-confirm' ); ?>
							<button
								class="action button-primary"><?php esc_html_e( 'Confirm', 'bsi' ); ?></button>
							<a
								class="action button cancel"
								href="<?php print esc_attr( remove_query_arg( 'bsi-action' ) ); ?>"><?php esc_html_e( 'Cancel', 'bsi' ); ?></a>
						</form>
						<?php
						break;
					case 'show-config':
					default:
						$fields = Plugin::field_list()['admin'];
						?>
						<form
							method="POST"
							action="<?php print esc_attr( add_query_arg( 'bsi-action', 'save-settings' ) ); ?>">
							<?php
							self::nonce_field( 'save-settings' );
							self::show_editor( $fields );
							?>
							<br/>
							<br/>
							<button
								class="action button-primary"><?php esc_html_e( 'Save settings', 'bsi' ); ?></button>
							<a
								class="action button-secondary" target="_blank"
								href="<?php print esc_attr( add_query_arg( 'bsi-action', 'purge-cache' ) ); ?>"><?php esc_html_e( 'Purge cache', 'bsi' ); ?></a>
						</form>

						<?php
						do_action( 'bsi_footer' );

						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add a link to the settings on the Plugins screen.
	 *
	 * @param array  $links list of links to show in the plugins table.
	 * @param string $file  the plugin file name.
	 *
	 * @return array list of links to show in the plugins table.
	 */
	public static function add_settings_link( $links, $file ) {
		if ( Plugin::get_plugin_file() === $file && current_user_can( Plugin::get_management_permission() ) ) {
			// add setting link for anyone that is allowed to alter the settings.
			$url = add_query_arg( 'page', 'branded-social-images', admin_url( 'admin.php' ) );
			if ( ! is_array( $links ) ) {
				$links = (array) $links;
			}
			$links[] = sprintf( '<a href="%s">%s</a>', $url, __( 'Settings', 'bsi' ) );

			// add support link.
			$links[] = sprintf( '<a href="%s">%s</a>', Plugin::BSI_URL_CONTACT, __( 'Support', 'bsi' ) );

			// add contribute link.
			$links[] = sprintf( '<a href="%s">%s</a>', Plugin::BSI_URL_CONTRIBUTE, __( 'Contribute', 'bsi' ) );
		}

		return $links;
	}

	/**
	 * Get the list of valid fonts.
	 *
	 * @return array List of valid fonts with their metadata.
	 */
	public static function valid_fonts() {
		$fonts = glob( self::storage() . '/*.?tf' ); // matches ttf and otf, and more, but this is checked better later on.
		$list  = [];
		foreach ( $fonts as $font ) {
			$b    = basename( $font );
			$base = basename( $font, '.ttf' );
			$t    = 'ttf';
			if ( $base === $b ) {
				$base = basename( $font, '.otf' );
				$t    = 'otf';
			}
			$json = preg_replace( '/\.[ot]tf$/', '.json', $font );
			$meta = [];
			if ( is_file( $json ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- no, stop proposing a URL based function for a local file.
				$meta = json_decode( file_get_contents( $json ) );
			}
			preg_match( '/-w([1-9]00)(-italic)?\./', $font, $m );
			$entry   = [
				'weight' => empty( $m[1] ) ? 400 : $m[1],
				'style'  => empty( $m[2] ) ? 'normal' : trim( $m[2], '-' ),
				'name'   => $meta && ! empty( $meta->font_name ) ? $meta->font_name : self::nice_font_name( $base ),
				'valid'  => true,
				$t       => self::storage() . '/' . $base . '.' . $t,
			];
			$weights = implode( '|', self::font_name_weights() );
			if ( preg_match( "/-($weights)?(Italic)?$/", $base, $m ) && ! empty( $m[1] ) ) {
				$weight = array_search( $m[1], self::font_name_weights(), true );
				if ( $weight ) {
					$entry['weight'] = $weight;
				}
			}
			if ( ! empty( $m[2] ) && 'Italic' === $m[2] ) {
				$entry['style'] = 'italic';
			}

			// display name.
			$entry['display_name'] = $entry['name'] . ' - ' . self::weight_to_suffix( $entry['weight'], 'italic' === $entry['style'] );
			$entry['display_name'] = str_replace( 'Italic', ' Italic', $entry['display_name'] );
			$entry['display_name'] = str_replace( '  Italic', ' Italic', $entry['display_name'] );
			$list[ $base ]         = $entry;
		}

		return $list;
	}

	/**
	 * Get the storage directory for the plugin.
	 *
	 * @return string
	 */
	private static function storage() {
		$dir = wp_upload_dir();
		$dir = $dir['basedir'] . '/' . Plugin::STORAGE;
		Plugin::mkdir( $dir );
		if ( ! is_dir( $dir ) ) {
			self::set_error( 'storage', __( 'Could not create the storage directory in the uploads folder.', 'bsi' ) . ' ' . __( 'In a WordPress site the uploads folder should always be writable.', 'bsi' ) . ' ' . __( 'Please fix this.', 'bsi' ) . ' ' . __( 'This error will disappear once the problem has been corrected.', 'bsi' ) );
		}
		Plugin::protect_dir( $dir );

		return $dir;
	}

	/**
	 * Transform a font filename into a nice font name.
	 *
	 * @param string $font The font filename.
	 *
	 * @return string
	 */
	public static function nice_font_name( $font ) {
		// w400 to normal, w700 to bold etc.
		$name    = self::array_first( explode( '-w', $font . '-w400', 2 ) );
		$weights = implode( '|', self::font_name_weights() );

		return preg_replace( "/-($weights)?(Italic)?$/", '', $name );
	}

	/**
	 * Get a list of errors that have been stored for display in the admin panel.
	 *
	 * @return array
	 */
	public static function get_errors() {
		$errors = get_option( Plugin::DEFAULTS_PREFIX . '_admin_errors', [] );
		update_option( Plugin::DEFAULTS_PREFIX . '_admin_errors', [] );

		return $errors;
	}

	/**
	 * Show the editor.
	 *
	 * @param array $fields        the fields to show in the editor.
	 * @param bool  $is_meta_panel whether this is a meta panel or not.
	 *
	 * @return void
	 */
	public static function show_editor( $fields, $is_meta_panel = false ) {
		$fields['text']['current_value'] = trim( $fields['text']['current_value'] ) !== '' ? $fields['text']['current_value'] : self::array_first( Plugin::text_fallback_chain() );

		$text_settings = Plugin::instance()->text_options;
		$logo_settings = Plugin::instance()->logo_options;

		$image = $fields['image']['current_value'];
		if ( $image && is_numeric( $image ) ) {
			$image = wp_get_attachment_image( $image, Plugin::IMAGE_SIZE_NAME );
			preg_match( '/src="(.+)"/U', $image, $m );
			$image = $m[1];
		}

		$logo   = $fields['image_logo']['current_value'];
		$width  = 0;
		$height = 0;
		if ( $logo && is_numeric( $logo ) ) {
			$logo = wp_get_attachment_image( $logo, 'full' );
			preg_match( '/width="(.+)"/U', $logo, $width );
			$width = $width[1];
			preg_match( '/height="(.+)"/U', $logo, $height );
			$height = $height[1];
			preg_match( '/src="(.+)"/U', $logo, $m );
			$logo = $m[1];
		}

		add_filter(
			'bsi_editor_variables',
			function ( $variables ) use ( $text_settings, $logo_settings, $logo, $width, $height ) {
				return array_merge(
					$variables,
					[
						'padding'           => Plugin::PADDING . 'px',
						'text-width'        => ceil( Plugin::instance()->width * Plugin::TEXT_AREA_WIDTH - 2 * $text_settings['padding'] ) . 'px',
						'text-height'       => ceil( Plugin::instance()->height * Plugin::TEXT_AREA_WIDTH - 2 * $text_settings['padding'] ) . 'px',
						'text-background'   => Admin::hex_to_rgba( $text_settings['background-color'], true ),
						'text-color'        => Admin::hex_to_rgba( $text_settings['color'], true ),
						'text-font'         => $text_settings['font-file'],
						'letter-spacing'    => '1px',
						'text-shadow-color' => Admin::hex_to_rgba( $text_settings['text-shadow-color'], true ),
						'text-shadow-top'   => (int) $text_settings['text-shadow-top'] . 'px',
						'text-shadow-left'  => (int) $text_settings['text-shadow-left'] . 'px',
						'font-size'         => $text_settings['font-size'] . 'px',
						'text-padding'      => $text_settings['padding'] . 'px',
						'line-height'       => $text_settings['line-height'] . 'px',
						'logo-scale'        => $logo_settings['size'],
						'logo-width'        => ( $logo ? $width : 410 ), /* example logo */
						'logo-height'       => ( $logo ? $height : 82 ),
					]
				);
			},
			PHP_INT_MAX
		);
		?>
		<style>
			#branded-social-images-editor {
			<?php
			/**
			 * Allow extra editor-variables by add-ons
			 * Note: you cannot overrule the BSI standard variables
			 *
			 * @param array  $variables     Add your variables to the list. skip the -- prefix.
			 *
			 * @since 1.0.18
			 */
			$variables = apply_filters( 'bsi_editor_variables', [] );
			foreach ( $variables as $variable => $value ) {
				print '
--' . esc_html( $variable ) . ': ' . esc_html( $value ) . ';';
			}
			?>
			}

		</style>
		<?php

		$editor_class   = [];
		$editor_class[] = 'logo_position-' . ( empty( $fields['logo_position'] ) ? $logo_settings['position'] : $fields['logo_position']['current_value'] );
		$editor_class[] = 'text_position-' . ( empty( $fields['text_position'] ) ? $text_settings['position'] : $fields['text_position']['current_value'] );

		if ( ( empty( $fields['disabled'] ) && 'on' === get_option( Plugin::DEFAULTS_PREFIX . 'disabled', 'off' ) ) || 'on' === $fields['disabled']['current_value'] ) {
			$editor_class[] = 'bsi-disabled';
		}
		if ( $logo ) {
			$editor_class[] = 'with-logo';
		}
		if ( 'on' === $text_settings['background-enabled'] ) {
			$editor_class[] = 'with-text-background';
		}

		$text_fallback_chain = Plugin::text_fallback_chain();

		$auto_title = false;
		$qo         = QueriedObject::instance();
		if ( 'new' === $qo->object_id ) {
			$auto_title = true;
		} elseif ( $qo->object_id ) { // not new, but still see object.
			$scraped = Plugin::scrape_title_data( $qo->permalink );
			if ( $scraped[0] >= 300 ) { // non-normal state.
				$auto_title = true;
			}
		}

		if ( $auto_title ) {
			$editor_class[] = 'auto-title';
		}

		$editor_class = implode( ' ', $editor_class );
		?>
		<div
			id="branded-social-images-editor"
			class="<?php print esc_attr( $editor_class ); ?>"
			data-font="<?php print esc_attr( $text_settings['font-file'] ); ?>"
			data-use-thumbnail="<?php print esc_attr( Plugin::field_list()['admin']['image_use_thumbnail']['current_value'] ); ?>">
			<?php if ( $is_meta_panel ) { /* meta panel has shorter, more compact view */ ?>
				<div class="settings">
					<div class="area--settings">
						<h2><?php esc_html_e( 'Settings', 'bsi' ); ?></h2>
						<div class="inner">
							<?php self::render_options( $fields ); ?>
						</div>
					</div>
				</div>
			<?php } ?>
			<div class="grid">
				<div class="area--background-canvas"><?php include __DIR__ . '/../img/example.svg'; ?></div>
				<?php foreach ( Plugin::image_fallback_chain() as $kind => $fallback_image ) { ?>
					<div class="area--background-alternate image-source-<?php print esc_attr( $kind ); ?>">
						<div
							class="background"
							<?php
							if ( $fallback_image ) {
								?>
								style="background-image:url('<?php print esc_attr( $fallback_image ); ?>')"<?php } ?>>
						</div>
					</div>
				<?php } ?>
				<div class="area--logo logo-alternate">
					<div
						class="logo"
						style="background-image:url('<?php print esc_attr( plugins_url( 'img/example-logo.svg', __DIR__ ) ); ?>')"></div>
				</div>
				<?php do_action( 'bsi_image_editor', 'after_creating_canvas' ); ?>
				<div class="area--background">
					<div
						class="background" style="background-image:url('<?php print esc_attr( $image ); ?>')"></div>
				</div>
				<?php do_action( 'bsi_image_editor', 'after_adding_background' ); ?>
				<div class="area--logo">
					<div class="logo" style="background-image:url('<?php print esc_attr( $logo ); ?>')"></div>
				</div>
				<?php do_action( 'bsi_image_editor', 'after_adding_logo' ); ?>
				<div class="area--text">
					<div class="editable-container">
						<pre
							contenteditable="true"
							class="editable"><?php print wp_kses_post( $fields['text']['current_value'] ); ?></pre>
						<?php
						foreach ( $text_fallback_chain as $type => $text ) {
							?>
							<div
								class="text-alternate type-<?php print esc_attr( $type ); ?>"><?php print wp_kses_post( $text ); ?></div>
							<?php
						}
						?>
					</div>
				</div>
				<?php do_action( 'bsi_image_editor', 'after_adding_text' ); ?>
			</div>
			<?php if ( ! $is_meta_panel ) { /* Admin panel is longer */ ?>
				<div class="settings">
					<div class="area--options collapsible">
						<h2><?php esc_html_e( 'Image and Logo options', 'bsi' ); ?><span class="toggle"></span>
						</h2>
						<div class="inner">
							<?php
							self::render_options(
								$fields,
								[
									'image',
									'image_use_thumbnail',
									'image_logo',
									'logo_position',
									'image_logo_size',
								]
							);
							?>
						</div>
					</div>
					<div class="area--settings collapsible">
						<h2><?php esc_html_e( 'Text settings', 'bsi' ); ?><span class="toggle"></span></h2>
						<div class="inner">
							<?php
							self::render_options(
								$fields,
								[
									'text',
									'text_enabled',
									'color',
									'text_shadow_enabled',
									'text__font',
									'text__ttf_upload',
									'text_position',
									'text__font_size',
									'background_enabled',
									'background_color',
									'text_shadow_color',
									'text_shadow_top',
									'text_shadow_left',
									'text_stroke_color',
									'text_stroke',
									'text__google_download',
								]
							);
							?>
						</div>
					</div>
					<?php
					/**
					 * Allow extra settings panels by add-ons
					 *
					 * @param array $fields All available fields
					 *
					 * @since 1.0.18
					 */
					do_action( 'bsi_settings_panels', $fields );
					?>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Render the options in the admin panel.
	 *
	 * @param array $options The options to render.
	 * @param array $filter  The filter to apply to the options.
	 */
	public static function render_options( $options, $filter = [] ) {
		static $seen = [];
		require_once __DIR__ . '/class.html_inputs.php';
		if ( ! $filter ) {
			$filter = array_keys( $options );
		}

		$filter = array_diff( $filter, $seen );

		foreach ( $filter as $option_name ) {
			if ( ! empty( $options[ $option_name ] ) ) {
				$seen[] = $option_name;
				self::render_option( $option_name, $options[ $option_name ] );
			}
		}
	}

	/**
	 * Render a single option in the admin panel.
	 *
	 * @param string $option_name The name of the option.
	 * @param array  $option_atts The attributes of the option.
	 */
	private static function render_option( $option_name, $option_atts ) {
		if ( ! empty( $option_atts['namespace'] ) && Plugin::DO_NOT_RENDER === $option_atts['namespace'] ) {
			return;
		}
		print '<span data-name="' . esc_attr( $option_name ) . '" class="input-wrap name-' . esc_attr( $option_name ) . ' input-' . esc_attr( $option_atts['type'] ) . ( empty( $option_atts['class'] ) ? '' : esc_attr( str_replace( ' ', ' wrap-', ' ' . $option_atts['class'] ) ) ) . '">';
		$label = '';
		if ( ! empty( $option_atts['label'] ) ) {
			$label = $option_atts['label'];
			unset( $option_atts['label'] );
		}
		HTML_Inputs::render( $option_name, $option_atts, $label );
		print '</span>';
	}

	/**
	 * Output the debug log panel.
	 *
	 * @return void
	 */
	public static function log_panel() {
		$log = get_transient( Plugin::OPTION_PREFIX . '_debug_log' );
		if ( $log ) {
			?>
			<div class="area--debug closed collapsible">
				<h2><?php esc_html_e( 'Debug log', 'bsi' ); ?><span class="toggle"></span></h2>
				<div class="inner">
					<pre><?php print wp_kses_post( $log ); ?></pre>
					<em>
						<?php
						// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- timezoned date intended.
						$date = date( 'd-m-Y H:i:s', get_option( '_transient_timeout_' . Plugin::OPTION_PREFIX . '_debug_log' ) );
						// translators: %s is the date when the log will be removed.
						print esc_html( sprintf( __( 'This log will be available until %s or until overwritten by a new log.', 'bsi' ), $date ) );
						?>
					</em>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Output the configuration panel.
	 *
	 * @param array $fields The fields to render in the configuration panel.
	 *
	 * @return void
	 */
	public static function config_panel( $fields ) {
		?>
		<div class="area--config closed collapsible">
			<h2><?php esc_html_e( 'Plugin configuration', 'bsi' ); ?><span class="toggle"></span></h2>
			<div class="inner">
				<?php self::render_options( $fields, [ 'disabled' ] ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Convert a hex color to RGBA format.
	 *
	 * @param string $hex    The hex color code.
	 * @param bool   $asrgba Whether to return the color as an RGBA string or an associative array.
	 *
	 * @return array|string The RGBA color as an associative array or a string.
	 */
	public static function hex_to_rgba( $hex, $asrgba = false ) {
		$hex = str_replace( '#', '', $hex );
		if ( ! $hex ) {
			$hex = '0000';
		}
		if ( strlen( $hex ) <= 4 ) {
			$hex = str_split( $hex . 'F' );
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		$hex = substr( $hex . 'FF', 0, 8 );

		$int  = hexdec( $hex );
		$rgba = [ ( $int >> 24 ) & 255, ( $int >> 16 ) & 255, ( $int >> 8 ) & 255, (float) ( $int & 255 ) / 255 ];

		return $asrgba ? vsprintf( 'rgba(%d, %d, %d, %0.1F)', $rgba ) : array_combine(
			[
				'red',
				'green',
				'blue',
				'alpha',
			],
			$rgba
		);
	}

	/**
	 * Add the post meta boxes for the plugin.
	 *
	 * @return void
	 */
	public static function add_post_meta_boxes() {
		$post_types    = apply_filters( 'bsi_post_types', [] );
		$meta_location = get_option( Plugin::DEFAULTS_PREFIX . 'meta_location', 'advanced' );
		foreach ( $post_types as $post_type ) {
			$context = apply_filters( 'bsi_meta_box_context', $meta_location, $post_type );
			if ( ! in_array( $context, [ 'advanced', 'normal', 'side' ], true ) ) {
				$context = $meta_location;
			}
			add_meta_box(
				Plugin::ADMIN_SLUG,
				'Branded Social Images',
				[ static::class, 'post_meta_panel' ],
				$post_type,
				$context
			);
		}
	}

	/**
	 * Add the category meta boxes for the plugin.
	 *
	 * @return void
	 */
	public static function add_category_meta_boxes() {
		self::category_meta_panel();
	}

	/**
	 * Get the taxonomy for a term ID.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return string The taxonomy for the term ID.
	 */
	private static function get_taxonomy_for_term( $term_id ) {
		// why the @#$% does WordPress not have this.
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id ) ) ?: 'category';
	}

	/**
	 * Save the category meta data.
	 *
	 * @param WP_Term|int $term_or_termid The term object or term ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function save_category_meta_data( $term_or_termid ) {
		if ( ! is_a( $term_or_termid, WP_Term::class ) && is_numeric( $term_or_termid ) ) {
			// create a fake object because without Taxonmy, WordPress will not allow lookup by id ... !@#$ knows why.
			$term_or_termid = (object) [
				'term_id'  => $term_or_termid,
				'taxonomy' => self::get_taxonomy_for_term( $term_or_termid ),
			];
		}

		return self::save_meta_data( $term_or_termid->term_id, $term_or_termid->taxonomy, 'category' );
	}

	/**
	 * Save the post meta data.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function save_post_meta_data( $post_id ) {
		return self::save_meta_data( $post_id, get_post_type( $post_id ), 'post' );
	}

	/**
	 * Save the meta data for a post or category.
	 *
	 * @param int    $object_id   The ID of the object (post or term).
	 * @param string $object_type The type of the object (post or term).
	 * @param string $base_type   The base type of the object (post or category).
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function save_meta_data( $object_id, $object_type, $base_type ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- done elsewhere.
		if ( array_key_exists( 'branded_social_images', $_POST ) ) {
			switch ( $base_type ) {
				case 'post':
					$function = 'update_post_meta';
					break;
				case 'category':
					$function = 'update_term_meta';
					break;
				default:
					$function = '__return_false';
			}

			// save new BSI meta values.
			$valid_post_keys = Plugin::get_valid_post_keys( 'meta' );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is checked in the form.
			foreach ( $_POST['branded_social_images'] as $namespace => $values ) {
				if ( is_array( $values ) ) {
					foreach ( $values as $key => $value ) {
						if ( ! in_array( $key, $valid_post_keys[ $namespace ], true ) ) {
							continue;
						}
						if ( 'text' === $key && ! empty( $value ) ) {
							$value = wp_strip_all_tags( $value );
						}
						if ( 'text' === $key && Plugin::text_is_identical( $value, self::array_first( Plugin::text_fallback_chain() ) ) ) {
							$value = '';
						}
						if ( 'text' === $key && Plugin::text_is_identical( $value, Plugin::instance()->dummy_data( 'text' ) ) ) {
							$value = '';
						}
						$function( $object_id, "$namespace$key", $value );
					}
				}
			}

			// clean the cache.
			$cache_file  = wp_upload_dir();
			$cache_files = $cache_file['basedir'] . '/' . Plugin::STORAGE . '/*/' . QueriedObject::cacheDirFor( $object_id, $object_type, $base_type ) . '/' . Plugin::output_filename();
			$lock_files  = $cache_files . '.lock';
			array_map( 'unlink', array_merge( glob( $lock_files ), glob( $cache_files ) ) );
		}

		return true;
	}

	/**
	 * Output the post meta panel.
	 *
	 * @return void
	 */
	public static function post_meta_panel() {
		self::meta_panel();
	}

	/**
	 * Output the category meta panel.
	 *
	 * @return void
	 */
	public static function category_meta_panel() {
		// Taxonomy panel has no wrappings.
		?>
		<div id="branded-social-images" class="fake-postbox">
			<div class="postbox-header"><h2 class="wp-ui-primary">Branded Social Images</h2></div>
			<div class="inside">
				<?php
				self::meta_panel();
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the meta panel.
	 *
	 * @return void
	 */
	public static function meta_panel() {
		$fields = Plugin::field_list()['meta'];
		self::show_editor( $fields, true );
		do_action( 'bsi_footer' );
	}

	/**
	 * Print the admin notices.
	 */
	public static function admin_notices() {
		$errors = self::get_errors();
		foreach ( $errors as $error ) {
			?>
			<div class="updated error"><p><?php print esc_html( $error ); ?></p></div>
			<?php
		}
	}

	/**
	 * Ajax handler to get a font file served to the browser.
	 */
	public static function wp_ajax_bsi_get_font() {
		// prevent path-traversal.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ajax call is admin only, not storing data, no nonce needed.
		$font = basename( 'fake-dir/' . $_GET['font'] );
		$file = self::storage() . '/' . $font;
		if ( is_file( $file ) ) {
			$mime = mime_content_type( $file );
			header( 'Content-Type: ' . $mime );
			header( 'Content-Disposition: inline; filename="' . $font . '"' );
			header( 'Content-Length: ' . filesize( $file ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			readfile( $file );
			exit;
		}
		header( 'HTTP/1.1 404 Not Found', true, 404 );
		exit;
	}

	/**
	 * Add the font-face definitions to the page.
	 *
	 * @return void
	 */
	public static function add_fontface_definitions() {
		$fonts     = self::valid_fonts();
		$faces     = [];
		$protected = admin_url( 'admin-ajax.php?action=' . Plugin::ADMIN_SLUG . '_get-font' );

		foreach ( $fonts as $font_base => $font ) {
			if ( ! $font['valid'] ) {
				continue;
			}
			$style   = $font['style'];
			$weight  = $font['weight'];
			$sources = [];
			foreach (
				[
					'ttf' => 'truetype',
					'otf' => 'opentype',
				] as $extension => $format
			) {
				if ( empty( $font[ $extension ] ) ) {
					continue;
				}
				$sources[] = "url('$protected&font=$font_base.$extension') format('$format')";
			}
			$sources = implode( ',', $sources );
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
		foreach ( $tweaks as $font => &$tweak ) {
			$tweak = $tweak['admin'];
			foreach ( $tweak as $prop => &$val ) {
				$val = "$prop: $val;";
			}
			$tweak = implode( "\n", $tweak );
			$tweak = "#branded-social-images-editor[data-font='$font'] .editable-container {\n$tweak}\n";
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		print '<style id="branded-social-images-css">' . implode( "\n\n", $faces ) . "\n\n" . implode( "\n\n", $tweaks ) . '</style>';
	}

	/**
	 * Sanitize the fonts by checking if they are available in the storage directory.
	 */
	public static function sanitize_fonts() {
		$storage    = trailingslashit( self::storage() );
		$missed_one = false;
		foreach ( Plugin::default_google_fonts() as $font ) {
			$font_family    = $font['font_family'];
			$font_weight    = empty( $font['font_weight'] ) ? 400 : $font['font_weight'];
			$font_style     = empty( $font['font_style'] ) ? 'normal' : $font['font_style'];
			$local_filename = self::google_font_filename( $font_family, $font_weight, $font_style, 'ttf' );
			if ( ! is_file( $storage . $local_filename ) ) {
				self::download_google_font( $font_family, $font_weight, $font_style );
				$missed_one = true;
			}
		}
		if ( $missed_one ) {
			Plugin::font_rendering_tweaks( true );
		}
	}

	/**
	 * Get the filename for a Google font.
	 *
	 * @param string $font_family The font family name.
	 * @param int    $font_weight The font weight.
	 * @param string $font_style  The font style (normal or italic).
	 * @param string $extension   The file extension (e.g., 'ttf', 'woff', etc.).
	 *
	 * @return string
	 */
	public static function google_font_filename( $font_family, $font_weight, $font_style, $extension = '' ) {
		$italic        = 'italic' === $font_style ? 'italic' : '';
		$suffix        = self::weight_to_suffix( $font_weight, $italic );
		$font_filename = str_replace( ' ', '', $font_family ) . '-' . $suffix;
		if ( $extension ) {
			$font_filename .= '.' . $extension;
		}

		return $font_filename;
	}

	/**
	 * Download a Google font and save it to the storage directory.
	 *
	 * @param string $font_family The font family name.
	 * @param int    $font_weight The font weight.
	 * @param string $font_style  The font style (normal or italic).
	 */
	public static function download_google_font( $font_family, $font_weight, $font_style ) {
		$font_filename = self::google_font_filename( $font_family, $font_weight, $font_style );
		$font_url      = self::google_font_url( $font_family, $font_weight, $font_style );
		$font_url      = str_replace( ' ', '%20', $font_url );

		/**
		 * Formats to download.
		 * formats array is formatted as User-Agent => file extension
		 * the ' ' will force Google to serve a TTF.
		 *
		 * @var $formats array
		 */
		$formats = [ ' ' => '.ttf' ];
		// also get woff2? doesn't seem required as all browsers currently support rendering ttf... .
		// $formats['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36'] = '.woff'; .

		foreach ( $formats as $user_agent => $extension ) {
			$font_css = wp_remote_retrieve_body(
				wp_remote_get(
					$font_url,
					[
						'headers'     => [
							'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						],
						'user-agent'  => $user_agent,
						'httpversion' => '1.1',
						'referer'     => site_url(),
					]
				)
			);
			if ( false !== strpos( $font_css, 'Font family not found' ) ) {
				ob_start();
				print '<h1>Font download failed</h1>';
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_dump
				var_dump( $font_family, $font_weight, $font_style, $font_css, $font_url );
				wp_die( wp_kses_post( ob_get_clean() ) );
			}
			if ( ! $font_css ) {
				self::set_error( 'font-family', __( 'Could not download font from Google Fonts.', 'bsi' ) . ' ' . __( 'Please download yourself and upload here.', 'bsi' ) );
			} else {
				$font_css_parts = explode( '@font-face', $font_css );
				$font_css       = '@font-face' . end( $font_css_parts );
				// use the last one, it should be latin. future task: verify; if not always latin last, build checks to actually GET latin.

				if ( preg_match( '@https?://[^)]+' . $extension . '@', $font_css, $n ) ) {
					$font_ttf = wp_remote_retrieve_body( wp_remote_get( $n[0] ) );
					self::file_put_contents( self::storage() . '/' . $font_filename . $extension, $font_ttf );
				}
			}
		}

		return $font_family;
	}

	/**
	 * Get the URL for a Google font.
	 *
	 * @param string $font_family The font family name.
	 * @param int    $font_weight The font weight.
	 * @param string $font_style  The font style (normal or italic).
	 *
	 * @return string The URL for the Google font.
	 */
	public static function google_font_url( $font_family, $font_weight, $font_style ) {
		$italic = 'italic' === $font_style ? 'italic' : '';

		return 'https://fonts.googleapis.com/css?family=' . $font_family . ':' . $font_weight . $italic;
	}

	/**
	 * Set an error message for the admin panel.
	 *
	 * @param string $tag  The tag for the error message.
	 *                     'generic' for a generic error, or a specific tag for a specific error.
	 * @param string $text The error message text.
	 */
	public static function set_error( $tag, $text ) {
		if ( 'generic' === $tag ) {
			$errors   = get_option( Plugin::DEFAULTS_PREFIX . '_admin_errors', [] );
			$errors[] = $text;
			$errors   = array_filter( $errors );
			$errors   = array_unique( $errors );
			update_option( Plugin::DEFAULTS_PREFIX . '_admin_errors', $errors );
		} else {
			$errors         = get_option( Plugin::DEFAULTS_PREFIX . '_errors', [] );
			$errors[ $tag ] = $text;
			$errors         = array_filter( $errors );
			update_option( Plugin::DEFAULTS_PREFIX . '_errors', $errors );
		}
	}

	/**
	 * Wrapper for file_put_contents that ensures the file is in the storage directory.
	 *
	 * @param string $filename The name of the file to write.
	 * @param string $content  The content to write to the file.
	 */
	public static function file_put_contents( $filename, $content ) {
		// for security reasons, $filename must be in $this->storage() .
		if ( substr( trim( $filename ), 0, strlen( self::storage() ) ) !== self::storage() ) {
			return false;
		}
		Plugin::mkdir( dirname( $filename ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return file_put_contents( $filename, $content );
	}

	/**
	 * Maybe move the font file from the uploads directory to the storage directory and remove the upload from the media library.
	 */
	public static function maybe_move_font() {
		$font_id = get_option( Plugin::DEFAULTS_PREFIX . 'text__ttf_upload' );
		if ( is_admin() && $font_id ) {
			$font = get_attached_file( $font_id );
			if ( is_file( $font ) ) {
				$instance = Plugin::instance();
				update_option( Plugin::DEFAULTS_PREFIX . 'text__ttf_upload', false );
				$b    = basename( $font );
				$base = basename( $font, '.ttf' );
				if ( $base === $b ) {
					$base = basename( $font, '.otf' );
				}
				update_option( Plugin::DEFAULTS_PREFIX . 'text__font', $base );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $font, $instance->storage() . '/' . basename( $font ) );
				wp_delete_post( $font_id );
			}
		}
	}

	/**
	 * Process the POST request for the admin panel.
	 *
	 * @return void
	 */
	public static function process_post() {
		$base = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- nonce is checked about 6 lines below.
		if ( is_admin() && current_user_can( Plugin::get_management_permission() ) && ! empty( $_GET['page'] ) && Plugin::ADMIN_SLUG === $_GET['page'] && ! empty( $_POST ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- nonce is checked about 4 lines below.
			$action = empty( $_REQUEST['bsi-action'] ) ? 'nop' : $_REQUEST['bsi-action'];
			switch ( $action ) {
				case 'save-settings':
					self::verify_nonce( $action );
					$valid_post_keys = Plugin::get_valid_post_keys( 'admin' );
					$fields          = Plugin::field_list();

					foreach ( array_keys( $fields ) as $group ) {
						if ( 'admin' === $group || 'meta' === $group ) { // skip groups already here.
							continue;
						}
						$valid_post_keys = array_merge( $valid_post_keys, Plugin::get_valid_post_keys( $group ) );
					}

					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is checked above.
					foreach ( $_POST['branded_social_images'] as $namespace => $values ) {
						if ( is_array( $values ) ) {
							foreach ( $values as $key => $value ) {
								if ( ! in_array( $key, $valid_post_keys[ $namespace ], true ) ) {
									continue;
								}
								if ( 'text' === $key && ! empty( $value ) ) {
									$value = strip_tags( $value, '<br>' );
								}
								update_option( "$namespace$key", $value );
							}
						}
					}

					wp_safe_redirect( remove_query_arg( 'bsi-action', add_query_arg( 'updated', 1 ) ) );
					exit;
				case 'purge-cache-confirm':
					self::verify_nonce( $action );
					Plugin::purge_cache();

					$purgable = Plugin::get_purgable_cache();
					if ( $purgable ) {
						// translators: %s is the cache-folder location in which there are items that could not be removed.
						self::set_error( 'generic', sprintf( __( 'Not all cache items could be removed. Please try again, or check the cache folder yourself. Location: %s', 'bsi' ), $base ) );
						wp_safe_redirect( remove_query_arg( 'bsi-action', add_query_arg( 'purged', 'error' ) ) );
					} else {
						wp_safe_redirect( remove_query_arg( 'bsi-action', add_query_arg( 'purged', 1 ) ) );
					}
					exit;
			}
		}
	}

	/**
	 * Convert a weight to a suffix for the font name.
	 *
	 * @param int  $weight    The font weight.
	 * @param bool $is_italic Whether the font is italic.
	 *
	 * @return string The suffix for the font name.
	 */
	private static function weight_to_suffix( $weight, $is_italic ) {
		$weight  = (int) ( round( $weight / 100 ) * 100 );
		$weights = self::font_name_weights();

		$suffix = ! array_key_exists( $weight, $weights ) || ( /* Special case; RegularItalic is just called Italic */
			400 === $weight && $is_italic ) ? '' : $weights[ $weight ];
		if ( $is_italic ) {
			$suffix .= 'Italic';
		}

		return $suffix;
	}

	/**
	 * These might seem duplicate, but they serve a very different function.
	 * Please do not try to refactor this ;)
	 *
	 * @method font_name_weights
	 * @method font_weights
	 */

	/**
	 * Weight to name mapping.
	 *
	 * @return array The font weights with their names.
	 */
	private static function font_name_weights() {
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

	/**
	 * Name to weight mapping.
	 *
	 * @return array The font weights.
	 */
	public static function font_weights() {
		return [
			'thin'        => 100,
			'extra light' => 200,
			'ultra light' => 200,
			'light'       => 300,
			'normal'      => 400,
			'book'        => 400,
			'regular'     => 400,
			'medium'      => 500,
			'semi bold'   => 600,
			'demi bold'   => 600,
			'bold'        => 700,
			'extra bold'  => 800,
			'ultra bold'  => 900,
		];
	}

	/**
	 * Get the errors from the admin panel.
	 *
	 * @param string|null $tag The tag for the error message. If null, all errors are returned.
	 *
	 * @return array|string The error message or an array of error messages.
	 */
	public static function get_error( $tag = null ) {
		$errors = get_option( Plugin::DEFAULTS_PREFIX . '_errors', [] );

		if ( $tag ) {
			$return = $errors[ $tag ];
			unset( $errors[ $tag ] );
			$errors = array_filter( $errors );
		} else {
			$return = $errors;
			$errors = [];
		}

		update_option( Plugin::DEFAULTS_PREFIX . '_errors', $errors );

		return $return;
	}

	/**
	 * Get first element of an array.
	 *
	 * @param array $the_array The array to get the first element from.
	 */
	private static function array_first( array $the_array ) {
		return reset( $the_array );
	}

	/**
	 * Generate a nonce field for the admin panel.
	 *
	 * @param string $action   The action name for the nonce.
	 * @param bool   $referer  Whether to include a referer field.
	 * @param bool   $print_it Whether to echo the nonce field or return it.
	 *
	 * @return string The nonce field HTML.
	 */
	private static function nonce_field( $action, $referer = true, $print_it = true ) {
		return wp_nonce_field( "bsi-$action", '_bsinonce', $referer, $print_it );
	}

	/**
	 * Verify the nonce for the admin panel.
	 *
	 * @param string $action The action name for the nonce.
	 *
	 * @throws \Exception If the nonce verification fails.
	 */
	private static function verify_nonce( $action ) {
		if ( ! wp_verify_nonce( $_POST['_bsinonce'] ?? false, "bsi-$action" ) ) {
			wp_die( esc_html__( 'Nonce verification failed, please try again.', 'bsi' ) );
		}
	}

	/**
	 * Encode a permalink for use in an external tool.
	 *
	 * @param string $get_permalink The permalink to encode.
	 *
	 * @return string The encoded URL.
	 */
	private static function encode_url_for_external_tool( $get_permalink ) {
		// force SSL - opengraph.xyz demands it.
		$url = set_url_scheme( $get_permalink, 'https' );
		// encode the URL for use in an external tool.
		$url = rawurlencode( $url );

		return $url;
	}
}
