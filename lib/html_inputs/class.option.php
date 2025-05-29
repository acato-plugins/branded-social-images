<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

/**
 * Option class
 *
 * Represents an option element within a select input.
 */
class Option extends Input {
	/**
	 * Constructor for the Option class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		parent::__construct( $attribute_name, $atts );
		$this->type = 'option';
	}

	/**
	 * Generates the HTML for the input field.
	 *
	 * @return string
	 */
	public function generate_html(): string {
		$this->atts['value'] = $this->get_tag_value();
		if ( ! isset( $this->atts['selected'] ) && $this->get_current_value() === $this->get_tag_value() ) {
			$this->atts['selected'] = 'selected';
		}
		$atts = $this->attributes();

		return '<option ' . $atts . '>' . $this->label . '</option>';
	}
}
