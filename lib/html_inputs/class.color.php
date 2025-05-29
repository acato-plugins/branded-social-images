<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

/**
 * Class Color
 *
 * Represents a color input field.
 */
class Color extends Input {
	/**
	 * Constructor for the Color Input class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		if ( empty( $atts['class'] ) ) {
			$atts['class'] = '';
		}
		$atts['class'] .= ' color-picker';

		parent::__construct( $attribute_name, $atts );
		$this->type = 'text';
	}

	/**
	 * Gets the value for the color input as it is rendered in a tag attribute.
	 *
	 * @return string
	 */
	public function get_tag_value() {
		return $this->current_value;
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
		$atts                = $this->attributes();

		return $label . '<span class="field-wrap"><input type="' . $this->type . '" ' . $atts . '/><span class="swatch"></span></span>';
	}
}
