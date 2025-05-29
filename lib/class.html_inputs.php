<?php
/**
 * HTML Inputs.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools;

use Acato\Tools\HTML_Inputs\Input;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

require_once __DIR__ . '/html_inputs/class.input.php';
require_once __DIR__ . '/html_inputs/class.text.php';
require_once __DIR__ . '/html_inputs/class.textarea.php';
require_once __DIR__ . '/html_inputs/class.select.php';
require_once __DIR__ . '/html_inputs/class.checkbox.php';
require_once __DIR__ . '/html_inputs/class.radio.php';
require_once __DIR__ . '/html_inputs/class.radios.php';
require_once __DIR__ . '/html_inputs/class.slider.php';
require_once __DIR__ . '/html_inputs/class.image.php';
require_once __DIR__ . '/html_inputs/class.color.php';

/**
 * Class HTML_Inputs
 *
 * This class is responsible for rendering various HTML input types based on the provided attributes.
 */
class HTML_Inputs {
	/**
	 * Renders an HTML input element based on the provided attributes.
	 *
	 * @param string $option_name  The name of the option to render.
	 * @param array  $option_atts  An associative array of attributes for the input element.
	 * @param string $option_label The label for the input element.
	 * @param bool   $print_it     Whether to echo the output or return it as a string.
	 *
	 * @return string|null
	 */
	public static function render( $option_name, $option_atts = [], $option_label = '', $print_it = true ) {
		$type  = $option_atts['type'] ?? 'text';
		$class = Input::get_input_class( $type );
		if ( $class ) {
			$input = new $class( $option_name, $option_atts );
			$input->set_label( $option_label );
			if ( $print_it ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				print "$input";
			}

			return "$input";
		}

		return '<!-- could not render ' . $type . '-input with name ' . $option_name . ' with atts ' . wp_json_encode( $option_atts ) . ' -->';
	}
}
