<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

/**
 * Class Checkbox
 *
 * Represents a checkbox input field.
 */
class Checkbox extends Input {
	/**
	 * Constructor for the Checkbox class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		parent::__construct( $attribute_name, $atts );
		$this->type = 'checkbox';
	}

	/**
	 * Sets the value for the checkbox input.
	 *
	 * @return void
	 */
	public function set_value() {
		$this->atts['value'] = $this->atts['value'] ?? 'on';
		$this->value         = $this->atts['value'];
	}

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

		$this->atts['value'] = $this->get_tag_value();
		if ( ! isset( $this->atts['checked'] ) && $this->get_current_value() === $this->get_tag_value() ) {
			$this->atts['checked'] = 'checked';
		}

		$atts = $this->attributes();

		return '<span class="field-wrap"><input type="hidden" name="' . $this->atts['name'] . '" value="off" />' .
		       // phpcs:ignore Universal.WhiteSpace.PrecisionAlignment.Found,Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed
		       '<input type="' . $this->type . '" ' . $atts . '>' . ( $this->empty ? '' : $this->content . '</' . $this->type . '>' ) . $label . '</span>';
	}
}
