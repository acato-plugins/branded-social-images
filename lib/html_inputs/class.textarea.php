<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

/**
 * Class Textarea
 *
 * Represents a textarea input field.
 */
class Textarea extends Input {
	/**
	 * Constructor for the TextArea class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		parent::__construct( $attribute_name, $atts );
		$this->type  = 'textarea';
		$this->empty = false;
	}

	/**
	 * Gets the value for the textarea as it is rendered in a tag attribute. - which is always null for a textarea.
	 *
	 * @return null
	 */
	public function get_tag_value() {
		return null;
	}

	/**
	 * Generates the HTML for the input field.
	 *
	 * @return string
	 */
	public function generate_html(): string {
		return '<' . $this->type . ' ' . $this->attributes() . '>' . ( $this->empty ? '' : esc_textarea( $this->current_value ) . '</' . $this->type . '>' );
	}
}
