<?php
/**
 * Image generation using GD library.
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

use GDText\Box;
use GDText\Color;

/**
 * Class GD
 *
 * This class handles the image generation using the GD library.
 * It supports text overlays, logo overlays, and saving the final image.
 */
class GD {
	/**
	 * The GD image handler.
	 *
	 * @var Image
	 */
	private $handler;

	/**
	 * The image manager.
	 *
	 * @var Plugin
	 */
	private $manager;

	/**
	 * The source image file.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Indicates if the source image is temporary.
	 *
	 * @var bool
	 */
	private $source_is_temporary = false;

	/**
	 * The target image file.
	 *
	 * @var string
	 */
	private $target;

	/**
	 * The line height factor for text rendering.
	 *
	 * @var float
	 */
	private $line_height_factor = 1;

	/**
	 * The width of the text area for text rendering.
	 *
	 * @var float
	 */
	private $text_area_width = Plugin::TEXT_AREA_WIDTH;

	/**
	 * The GD resource for the image.
	 *
	 * @var resource
	 */
	private $resource;

	/**
	 * GD constructor.
	 *
	 * @param Image  $handler The image handler.
	 * @param string $source  The source image file.
	 * @param string $target  The target image file.
	 */
	public function __construct( Image $handler, $source, $target ) {
		$this->handler = $handler;
		$this->manager = $handler->getManager();

		// use this construction, so we don't have to check file mime .
		if ( is_file( $source ) && preg_match( '@\.webp$@', strtolower( trim( $source ) ) ) ) {
			// do we have webp support?
			$support = function_exists( 'imagewebp' );

			if ( ! $support ) {
				// we cannot support natively .
				$support = Plugin::maybe_fake_support_webp();
				if ( $support ) {
					// we fake support, so we need to convert the input image to PNG .
					$source                    = Plugin::convert_webp_to_png( $source );
					$this->source_is_temporary = true;
				}
			}
		}

		$this->source = $source;
		$this->target = $target;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$baseImage      = imagecreatefromstring( file_get_contents( $this->source ) );
		$w              = $this->manager->width * Plugin::AA;
		$h              = $this->manager->height * Plugin::AA;
		$this->resource = imagecreatetruecolor( $w, $h );

		// with WP_DEBUG_DISPLAY, don't throw deprecation errors on the screen as it will interfere with the image output.
		$action_function = ( defined( 'WP_DEBUG_DISPLAY' ) && true === WP_DEBUG_DISPLAY ) ? 'do_action_ref_array' : 'do_action_deprecated';
		$action_function(
			'bsi_image_gd',
			[
				&$this->resource,
				'after_creating_canvas',
				$this->handler->post_id,
				$this->handler->image_id,
			],
			'1.1.0',
			'bsi_image_layer'
		);
		do_action_ref_array(
			'bsi_image_layer',
			[
				&$this->resource,
				'gd_after_creating_canvas',
				QueriedObject::instance(),
				$this->handler->image_id,
			]
		);

		imagealphablending( $this->resource, true );
		imagesavealpha( $this->resource, true );

		$source_width  = imagesx( $baseImage );
		$source_height = imagesy( $baseImage );

		// just in time cropping.

		$target_ratio = $h / $w;
		$source_ratio = $source_height / $source_width;
		$source_x     = 0;
		$source_y     = 0;
		if ( $source_ratio > $target_ratio ) { // image is too high.
			$source_height = $source_width * $target_ratio;
			$source_y      = ( imagesy( $baseImage ) - $source_height ) / 2;
		}
		if ( $source_ratio < $target_ratio ) { // image is too wide.
			$source_width = $source_height / $target_ratio;
			$source_x     = ( imagesx( $baseImage ) - $source_width ) / 2;
		}

		imagecopyresampled( $this->resource, $baseImage, 0, 0, (int) $source_x, (int) $source_y, (int) $w, (int) $h, (int) $source_width, (int) $source_height );
		imagedestroy( $baseImage );
		if ( $this->source_is_temporary ) {
			wp_delete_file( $this->source );
		}

		// with WP_DEBUG_DISPLAY, don't throw deprecation errors on the screen as it will interfere with the image output.
		$action_function = ( defined( 'WP_DEBUG_DISPLAY' ) && true === WP_DEBUG_DISPLAY ) ? 'do_action_ref_array' : 'do_action_deprecated';
		$action_function(
			'bsi_image_gd',
			[
				&$this->resource,
				'after_adding_background',
				$this->handler->post_id,
				$this->handler->image_id,
			],
			'1.1.0',
			'bsi_image_layer'
		);
		do_action_ref_array(
			'bsi_image_layer',
			[
				&$this->resource,
				'gd_after_adding_background',
				QueriedObject::instance(),
				$this->handler->image_id,
			]
		);
	}

	/**
	 * Overlay text on the image.
	 *
	 * @param array  $textOptions The options for the text overlay.
	 * @param string $text        The text to overlay on the image.
	 */
	public function text_overlay( $textOptions, $text ) {
		$image_width  = imagesx( $this->resource ); // already is Plugin::AA .
		$image_height = imagesy( $this->resource );

		$text_color = $this->manager->hex_to_rgba( $textOptions['color'], true );
		$text_color = new Color( $text_color[0], $text_color[1], $text_color[2], $text_color[3] );

		$background_color = false;
		if ( 'on' === $textOptions['background-enabled'] && $textOptions['background-color'] ) {
			$background_color_rgba = $this->manager->hex_to_rgba( $textOptions['background-color'], true );
			if ( $background_color_rgba[3] < 127 ) { // not 100% transparent .
				$background_color = imagecolorallocatealpha( $this->resource, $background_color_rgba[0], $background_color_rgba[1], $background_color_rgba[2], $background_color_rgba[3] );
				imagecolortransparent( $this->resource, $background_color );
			}
		} else {
			$textOptions['padding'] = 0;
		}

		$text_shadow_color = false;
		if ( $textOptions['text-shadow-color'] ) {
			$text_shadow_color_rgba = $this->manager->hex_to_rgba( $textOptions['text-shadow-color'], true );
			if ( $text_shadow_color_rgba[3] < 127 ) { // not 100% transparent .
				$text_shadow_color = new Color( $text_shadow_color_rgba[0], $text_shadow_color_rgba[1], $text_shadow_color_rgba[2], $text_shadow_color_rgba[3] );
			}
		}

		$text_stroke_color = false;
		if ( $textOptions['text-stroke-color'] ) {
			$text_stroke_color_rgba = $this->manager->hex_to_rgba( $textOptions['text-stroke-color'], true );
			if ( $text_stroke_color_rgba[3] < 127 ) { // not 100% transparent .
				$text_stroke_color = new Color( $text_stroke_color_rgba[0], $text_stroke_color_rgba[1], $text_stroke_color_rgba[2], $text_stroke_color_rgba[3] );
			}
		}
		$font = $textOptions['font-file'];
		Plugin::log( "Text overlay: using font: $font" );
		$tweaks = Plugin::font_rendering_tweaks_for( $font, 'gd' );
		if ( $tweaks ) {
			if ( ! empty( $tweaks['line-height'] ) ) {
				$this->line_height_factor = $tweaks['line-height'];
			}
			if ( ! empty( $tweaks['text-area-width'] ) ) {
				$this->text_area_width = $tweaks['text-area-width'] * $this->text_area_width;
			}
		}
		$fontSize = $textOptions['font-size'] * Plugin::AA;
		if ( trim( $text ) === '' ) {
			$background_color = false;
		}

		$text = str_replace( [ '<br>', '<br />', '<br/>', '\\n' ], "\n", $text ); // predefined line breaks .
		$text = str_replace( '\\r', '', $text );
		$text = $this->wrapTextByPixels( $text, $image_width * $this->text_area_width, $fontSize, $font );

		$textDim     = imagettfbbox( $fontSize, 0, $font, implode( ' ', explode( "\n", $text . 'Hj' ) ) ); // Hj is to make sure the correct line-height is calculated.
		$line_height = $textDim[1] - $textDim[7];

		$lines      = explode( "\n", $text );
		$text_width = 0;
		foreach ( $lines as $line ) {
			$textDim    = imagettfbbox( $fontSize, 0, $font, $line );
			$text_width = max( $text_width, $textDim[2] - $textDim[0] );
		}
		$text_width += 2;

		$line_height *= $this->line_height_factor;

		$text_height = $line_height * count( $lines );

		$p = $textOptions['padding'] * Plugin::AA;

		$textOptions['left']   *= Plugin::AA;
		$textOptions['right']  *= Plugin::AA;
		$textOptions['top']    *= Plugin::AA;
		$textOptions['bottom'] *= Plugin::AA;

		$text_posX = 0;
		$text_posY = 0;

		if ( 'center' === $textOptions['halign'] ) {
			$text_posX = ( $image_width - $textOptions['left'] - $textOptions['right'] ) / 2 - $text_width / 2 + $textOptions['left'];
		}
		if ( 'right' === $textOptions['halign'] ) {
			$text_posX = $image_width - $textOptions['right'] - $text_width - $p;
		}
		if ( 'left' === $textOptions['halign'] ) {
			$text_posX = $textOptions['left'] + $p;
		}

		if ( 'center' === $textOptions['valign'] ) {
			$text_posY = ( $image_height - $textOptions['top'] - $textOptions['bottom'] ) / 2 - $text_height / 2 + $textOptions['top'];
		}
		if ( 'bottom' === $textOptions['valign'] ) {
			$text_posY = $image_height - $textOptions['bottom'] - ( $text_height / .75 ) - $p;
		}
		if ( 'top' === $textOptions['valign'] ) {
			$text_posY = $textOptions['top'] + $p;
		}

		// text-background .
		if ( false !== $background_color && 'inline' === $textOptions['display'] ) {
			// .75 points to pixels .
			imagefilledrectangle( $this->resource, (int) $text_posX - $p, (int) $text_posY - $p, (int) $text_posX + $text_width + $p, (int) $text_posY + ( $text_height / .75 ) + $p, $background_color );
		}

		// NOTE: imagettf uses Y position for bottom!! of the text, not the top.
		// ALSO: this is for the text BASE, so some text might stick out below. compensate by 18% of text height.
		if ( false !== $text_shadow_color ) {
			$shiftX      = $textOptions['text-shadow-left'] * Plugin::AA;
			$shiftY      = $textOptions['text-shadow-top'] * Plugin::AA;
			$steps       = max( absint( $shiftX ), absint( $shiftY ) );
			$start_color = $textOptions['color'];
			$end_color   = $textOptions['text-shadow-color'];
			if ( 'open' === $textOptions['text-shadow-type'] ) {
				$steps = 1;
			}
			if ( 'solid' === $textOptions['text-shadow-type'] ) {
				$start_color = $end_color;
			}

			for ( $step = $steps; $step > 0; $step -- /* skip step 0 as it is the text-position */ ) {
				$shiftX_step            = self::gradient_value( 0, $shiftX, $step, $steps );
				$shiftY_step            = self::gradient_value( 0, $shiftY, $step, $steps );
				$text_shadow_color_step = $this->gradient_color( $start_color, $end_color, $step, $steps, true );
				$text_shadow_color_step = $this->manager->hex_to_rgba( $text_shadow_color_step, true );
				$text_shadow_color_step = new Color( $text_shadow_color_step[0], $text_shadow_color_step[1], $text_shadow_color_step[2], $text_shadow_color_step[3] );
				$this->imagettftextbox( $this->resource, $fontSize, $text_posX + $shiftX_step, $text_posY + $shiftY_step, $text_width, $text_height, $text_shadow_color_step, $font, $text, [ 'align' => $textOptions['halign'] ] );
			}
		}

		$this->imagettftextbox(
			$this->resource,
			$fontSize,
			$text_posX,
			$text_posY,
			$text_width,
			$text_height,
			$text_color,
			$font,
			$text,
			[
				'align'        => $textOptions['halign'],
				'stroke_width' => $textOptions['text-stroke'],
				'stroke_color' => $text_stroke_color,
			]
		);

		// with WP_DEBUG_DISPLAY, don't throw deprecation errors on the screen as it will interfere with the image output.
		$action_function = ( defined( 'WP_DEBUG_DISPLAY' ) && true === WP_DEBUG_DISPLAY ) ? 'do_action_ref_array' : 'do_action_deprecated';
		$action_function(
			'bsi_image_gd',
			[
				&$this->resource,
				'after_adding_text',
				$this->handler->post_id,
				$this->handler->image_id,
			],
			'1.1.0',
			'bsi_image_layer'
		);
		do_action_ref_array(
			'bsi_image_layer',
			[
				&$this->resource,
				'gd_after_adding_text',
				QueriedObject::instance(),
				$this->handler->image_id,
			]
		);
	}

	/**
	 * Generate a gradient color between two hex colors.
	 *
	 * @param string $hex_rgba_start The starting color in hex format.
	 * @param string $hex_rgba_end   The ending color in hex format.
	 * @param int    $step           The current step in the gradient.
	 * @param int    $steps          The total number of steps in the gradient.
	 * @param bool   $skip_alpha     Whether to skip the alpha channel.
	 * @param bool   $return_as_hex  Whether to return the result as a hex color.
	 *
	 * @return string|array The gradient color in hex or RGBA format.
	 */
	private function gradient_color( $hex_rgba_start, $hex_rgba_end, $step, $steps = 100, $skip_alpha = false, $return_as_hex = true ) {
		$hex_rgba_start_rgba = $this->manager->hex_to_rgba( $hex_rgba_start );
		$hex_rgba_end_rgba   = $this->manager->hex_to_rgba( $hex_rgba_end );
		if ( $skip_alpha ) {
			if ( is_bool( $skip_alpha ) ) {
				$skip_alpha = $hex_rgba_end;
			}
			$skip_alpha             = $this->manager->hex_to_rgba( $skip_alpha );
			$hex_rgba_start_rgba[3] = $skip_alpha[3];
			$hex_rgba_end_rgba[3]   = $skip_alpha[3];
		}

		$gradient_hex_rgba = [
			self::gradient_value( $hex_rgba_start_rgba[0], $hex_rgba_end_rgba[0], $step, $steps ),
			self::gradient_value( $hex_rgba_start_rgba[1], $hex_rgba_end_rgba[1], $step, $steps ),
			self::gradient_value( $hex_rgba_start_rgba[2], $hex_rgba_end_rgba[2], $step, $steps ),
			self::gradient_value( $hex_rgba_start_rgba[3], $hex_rgba_end_rgba[3], $step, $steps ),
		];

		return $return_as_hex ? $this->manager->rgba_to_hex( $gradient_hex_rgba ) : $gradient_hex_rgba;
	}

	/**
	 * Calculate the gradient value between two values based on the step and total steps.
	 *
	 * @param float $start The starting value.
	 * @param float $end   The ending value.
	 * @param int   $step  The current step.
	 * @param int   $steps The total number of steps.
	 *
	 * @return float The calculated gradient value.
	 */
	private static function gradient_value( $start, $end, $step, $steps = 100 ) {
		$percentage  = $step / $steps;
		$value_range = $end - $start;

		return $value_range * $percentage + $start;
	}

	/**
	 * Overlay logo on the image.
	 *
	 * @param array $logoOptions The options for the logo overlay.
	 */
	public function logo_overlay( $logoOptions ) {
		Plugin::log( 'Overlay: logo' );
		if ( ! $logoOptions['file'] ) {
			Plugin::log( 'Logo overlay: ( no logo )' );

			return;
		}
		$file = $logoOptions['file'];
		Plugin::log( 'Logo overlay: logo file; ' . $file );
		if ( ! is_file( $file ) ) {
			Plugin::log( 'Logo overlay: logo file not found!' );

			return;
		}

		// source.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$logo        = imagecreatefromstring( file_get_contents( $file ) );
		$logo_width  = imagesx( $logo );
		$logo_height = imagesy( $logo );

		// target.
		$image_width  = imagesx( $this->resource );
		$image_height = imagesy( $this->resource );

		Plugin::log( "Logo overlay: logo dimensions; W:$logo_width, H:$logo_height" );

		// logo overlay.
		$w = $logoOptions['w'] * Plugin::AA * 0.5;
		$h = $logoOptions['h'] * Plugin::AA * 0.5;

		$logoOptions['left']   *= Plugin::AA;
		$logoOptions['right']  *= Plugin::AA;
		$logoOptions['top']    *= Plugin::AA;
		$logoOptions['bottom'] *= Plugin::AA;

		$p         = 0;
		$logo_posX = 0;
		$logo_posY = 0;

		if ( 'center' === $logoOptions['halign'] ) {
			$logo_posX = ( $image_width - $logoOptions['left'] - $logoOptions['right'] ) / 2 - $w / 2 + $logoOptions['left'];
		}
		if ( 'right' === $logoOptions['halign'] ) {
			$logo_posX = $image_width - $logoOptions['right'] - $w - $p;
		}
		if ( 'left' === $logoOptions['halign'] ) {
			$logo_posX = $logoOptions['left'] + $p;
		}

		if ( 'center' === $logoOptions['valign'] ) {
			$logo_posY = ( $image_height - $logoOptions['top'] - $logoOptions['bottom'] ) / 2 - $h / 2 + $logoOptions['top'];
		}
		if ( 'bottom' === $logoOptions['valign'] ) {
			$logo_posY = $image_height - $logoOptions['bottom'] - $h - $p;
		}
		if ( 'top' === $logoOptions['valign'] ) {
			$logo_posY = $logoOptions['top'] + $p;
		}

		imagecopyresampled( $this->resource, $logo, (int) $logo_posX, (int) $logo_posY, 0, 0, (int) $w, (int) $h, (int) $logo_width, (int) $logo_height );
		imagedestroy( $logo );

		// with WP_DEBUG_DISPLAY, don't throw deprecation errors on the screen as it will interfere with the image output.
		$action_function = ( defined( 'WP_DEBUG_DISPLAY' ) && true === WP_DEBUG_DISPLAY ) ? 'do_action_ref_array' : 'do_action_deprecated';
		$action_function(
			'bsi_image_gd',
			[
				&$this->resource,
				'after_adding_logo',
				$this->handler->post_id,
				$this->handler->image_id,
			],
			'1.1.0',
			'bsi_image_layer'
		);
		do_action_ref_array(
			'bsi_image_layer',
			[
				&$this->resource,
				'gd_after_adding_logo',
				QueriedObject::instance(),
				$this->handler->image_id,
			]
		);
	}

	/**
	 * Save the image to the target file.
	 *
	 * @param string $format  The format to save the image in (jpg, webp, png).
	 * @param int    $quality The quality of the saved image (0-100).
	 */
	public function save( $format, $quality ) {
		$this->manager->file_put_contents( $this->target, '' ); // prime the file, creating all directories .
		$scaled = imagescale( $this->resource, $this->manager->width, $this->manager->height, IMG_BICUBIC_FIXED );
		header( 'X-OG-Scaler: imagescale' );
		if ( false === $scaled ) {
			header( 'X-OG-Scaler: imagecopyresized', true );
			$scaled = imagecreatetruecolor( $this->manager->width, $this->manager->height );
			imagecopyresized( $scaled, $this->resource, 0, 0, 0, 0, $this->manager->width, $this->manager->height, imagesx( $this->resource ), imagesy( $this->resource ) );
		}
		if ( false === $scaled ) {
			header( 'X-OG-Scaler: none', true );
			$scaled = &$this->resource;
		}

		switch ( $format ) {
			case 'jpg':
				imagejpeg( $scaled, $this->target, $quality );
				break;
			case 'webp':
				imagewebp( $scaled, $this->target, $quality );
				break;
			case 'png':
			default:
				imagepng( $scaled, $this->target, $quality );
				break;
		}
	}

	/**
	 * Create a text box with TTF font rendering.
	 *
	 * @param resource $image    The GD image resource.
	 * @param int      $size     The font size in pixels.
	 * @param int      $x        The x-coordinate of the text box.
	 * @param int      $y        The y-coordinate of the text box.
	 * @param int      $w        The width of the text box.
	 * @param int      $h        The height of the text box.
	 * @param Color    $color    The color of the text.
	 * @param string   $fontfile The path to the TTF font file.
	 * @param string   $text     The text to render in the box.
	 * @param array    $options  Additional options for rendering the text.
	 */
	private function imagettftextbox( $image, $size, $x, $y, $w, $h, Color $color, $fontfile, $text, $options = [] ) {
		/**
		 * Alignment of the text inside the box.
		 * left, center or right
		 *
		 * @var string $align
		 */
		$align = null;

		/**
		 * The width of the stroke around the text.
		 *
		 * @var int|null $stroke_width
		 */
		$stroke_width = null;

		/**
		 * The stroke color around the text.
		 *
		 * @var null|Color $stroke_color
		 */
		$stroke_color = null;

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract(
			shortcode_atts(
				[
					'align'        => 'left',
					'stroke_width' => 0,
					'stroke_color' => false,
				],
				$options
			)
		);
		$textbox = new Box( $image );
		$textbox->setFontSize( $size / .75 );
		$textbox->setFontFace( $fontfile );
		$textbox->setFontColor( $color );
		if ( false !== $stroke_color && $stroke_width > 0 ) {
			$textbox->setStrokeColor( $stroke_color );
			$textbox->setStrokeSize( $stroke_width );
		}
		$textbox->setBox(
			$x,  // distance from left edge.
			$y,  // distance from top edge.
			$w, // textbox width.
			$h  // textbox height.
		);

		// text will be aligned inside textbox to right horizontally and to top vertically.
		$textbox->setTextAlign( $align, 'top' );

		$textbox->draw( $text );
	}

	/**
	 * Returns expected width of rendered text in pixels
	 *
	 * @param string $text      The text to measure.
	 * @param string $font      The path to the font file.
	 * @param int    $font_size The size of the font in pixels.
	 *
	 * @return int The width of the text in pixels.
	 */
	private function getWidthPixels( $text, $font, $font_size ) {
		if ( trim( $text ) === '' ) {
			return 0;
		}
		static $widthCorrection;
		$bbox  = imageftbbox( $font_size, 0, $font, $text );
		$width = ( $bbox[2] - $bbox[0] );
		if ( $width && ! $widthCorrection ) {
			$widthCorrection = static::getWidthPixelsTrue( $text, $font, $font_size );
			$widthCorrection = $widthCorrection['width'] / $width;
		}

		return $width / $widthCorrection;
	}

	/**
	 * Returns the exact width of rendered text in pixels
	 *
	 * @param string $text       The text to measure.
	 * @param string $font_file  The path to the font file.
	 * @param int    $font_size  The size of the font in pixels.
	 * @param int    $font_angle The angle of the font in degrees.
	 *
	 * @return array|false An array with 'left', 'top', 'width', and 'height' keys, or false on failure.
	 */
	private function getWidthPixelsTrue( $text, $font_file, $font_size, $font_angle = 0 ) {
		$box = imagettfbbox( $font_size, $font_angle, $font_file, $text );
		if ( ! $box ) {
			return false;
		}
		$min_x  = min( [ $box[0], $box[2], $box[4], $box[6] ] );
		$max_x  = max( [ $box[0], $box[2], $box[4], $box[6] ] );
		$min_y  = min( [ $box[1], $box[3], $box[5], $box[7] ] );
		$max_y  = max( [ $box[1], $box[3], $box[5], $box[7] ] );
		$width  = ( $max_x - $min_x );
		$height = ( $max_y - $min_y );
		$left   = abs( $min_x ) + $width;
		$top    = abs( $min_y ) + $height;
		// to calculate the exact bounding box, I write the text in a large image .
		$img   = @imagecreatetruecolor( $width << 2, $height << 2 );
		$white = imagecolorallocate( $img, 255, 255, 255 );
		$black = imagecolorallocate( $img, 0, 0, 0 );
		imagefilledrectangle( $img, 0, 0, imagesx( $img ), imagesy( $img ), $black );
		// for sure the text is completely in the image! .
		imagettftext(
			$img,
			$font_size,
			$font_angle,
			$left,
			$top,
			$white,
			$font_file,
			$text
		);
		// start scanning (0=> black => empty) .
		$rleft   = $width << 2;
		$w4      = $width << 2;
		$rright  = 0;
		$rbottom = 0;
		$rtop    = $height << 2;
		$h4      = $height << 2;
		for ( $x = 0; $x < $w4; $x ++ ) {
			for ( $y = 0; $y < $h4; $y ++ ) {
				if ( imagecolorat( $img, $x, $y ) ) {
					$rleft   = min( $rleft, $x );
					$rright  = max( $rright, $x );
					$rtop    = min( $rtop, $y );
					$rbottom = max( $rbottom, $y );
				}
			}
		}
		// destroy img and return the result .
		imagedestroy( $img );

		return [
			'left'   => $left - $rleft,
			'top'    => $top - $rtop,
			'width'  => $rright - $rleft + 1,
			'height' => $rbottom - $rtop + 1,
		];
	}

	/**
	 * Wrap text by max amount of pixels.
	 * Returns wrapped format (with newlines) of a piece of text (meant to be rendered on an image) using the width of rendered bounding box of text
	 *
	 * @param string $text            The text to wrap.
	 * @param int    $line_max_pixels The maximum width of a line in pixels.
	 * @param int    $font_size       The size of the font in pixels.
	 * @param string $font            The path to the font file.
	 */
	private function wrapTextByPixels( $text, $line_max_pixels, $font_size, $font ) {
		$words        = explode( ' ', $text );
		$lines        = [];
		$crr_line_idx = 0;

		foreach ( $words as $word ) {
			if ( empty( $lines[ $crr_line_idx ] ) ) {
				$lines[ $crr_line_idx ] = '';
			}
			$before                 = $lines[ $crr_line_idx ];
			$lines[ $crr_line_idx ] = trim( $lines[ $crr_line_idx ] . ' ' . $word );
			if ( static::getWidthPixels( $lines[ $crr_line_idx ], $font, $font_size ) < $line_max_pixels ) {
				continue;
			}
			// word overflow; .
			$lines[ $crr_line_idx ] = $before;
			++ $crr_line_idx;
			$lines[ $crr_line_idx ] = $word;
			// protect against infinite loop .
			if ( static::getWidthPixels( $lines[ $crr_line_idx ], $font, $font_size ) >= $line_max_pixels ) {
				// this word on its own is too long, ignore that fact and move on .
				++ $crr_line_idx;
			}
		}

		return trim( implode( PHP_EOL, $lines ) );
	}
}
