<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

/**
 * Class Text
 *
 * Most basic text input field.
 */
class Text extends Input {
	/**
	 * Get the tag value for the text input.
	 */
	public function get_tag_value() {
		return $this->current_value;
	}
}
