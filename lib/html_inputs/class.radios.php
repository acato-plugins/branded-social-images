<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

require_once __DIR__ . '/class.radio.php';

/**
 * The Radios class.
 *
 * This class extends the Radio class to create a group of radio inputs.
 */
class Radios extends Radio {
	/**
	 * The namespace for the input.
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * Constructor for the Radios class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		parent::__construct( $attribute_name, $atts );
		$this->namespace = $atts['namespace'];
	}

	/**
	 * Generates the HTML for the input field.
	 *
	 * @return string
	 */
	public function generate_html(): string {
		$label = '';
		if ( $this->label ) {
			$label = '<label class="radios-label">' . $this->label . '</label>';
		}

		$options = '';
		foreach ( $this->atts['options'] as $option_value => $option_label ) {
			$_atts              = $this->atts;
			$_atts['namespace'] = $this->namespace;
			$_atts['value']     = $option_value;
			unset( $_atts['class'], $_atts['options'], $_atts['current_value'], $_atts['default'] );
			$radio = new Radio( $this->name, $_atts );
			$radio->set_label( $option_label, true );
			$radio->current_value = $this->get_current_value();

			$options .= "<span class='option-wrap'>$radio</span>";
		}

		return $label . '<span class="options-wrap">' . $options . '</span>';
	}
}
