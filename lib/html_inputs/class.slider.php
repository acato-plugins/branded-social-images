<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

require_once __DIR__ . '/class.text.php';

/**
 * The slider input class.
 *
 * This class extends the Text class to create a slider input.
 */
class Slider extends Text {
	/**
	 * Constructor for the Slider class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		parent::__construct( $attribute_name, $atts );
		$this->type = 'text';
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

		return $label . '<span class="add-slider"><input type="' . $this->type . '" ' . $atts . '>' . ( $this->empty ? '' : $this->content . '</' . $this->type . '>' ) . '</span>';
	}
}
