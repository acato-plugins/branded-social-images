<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

require_once __DIR__ . '/class.option.php';

/**
 * The select input class.
 *
 * This class extends the Input class to create a select input.
 */
class Select extends Input {
	/**
	 * Generates the HTML for the input field.
	 *
	 * @return string
	 */
	public function generate_html(): string {
		$label = '';
		if ( $this->label ) {
			$label            = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}

		$options = '';
		foreach ( $this->atts['options'] as $option_value => $option_label ) {
			$_atts          = $this->atts;
			$_atts['value'] = $option_value;
			unset( $_atts['class'], $_atts['options'], $_atts['current_value'], $_atts['default'] );
			$option = new Option( $this->name, $_atts );
			$option->set_label( $option_label, true );
			$option->current_value = $this->get_current_value();

			$options .= "$option";
		}

		$atts = $this->attributes();

		return $label . '<select ' . $atts . '/>' . $options . '</select>';
	}
}
