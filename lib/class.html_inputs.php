<?php

namespace Clearsite\Tools;

use Clearsite\Tools\HTML_Inputs\Input;

require __DIR__ .'/html_inputs/class.input.php';
require __DIR__ .'/html_inputs/class.text.php';
require __DIR__ .'/html_inputs/class.textarea.php';
//require __DIR__ .'/html_inputs/class.select.php';
//require __DIR__ .'/html_inputs/class.email.php';
//require __DIR__ .'/html_inputs/class.tel.php';
//require __DIR__ .'/html_inputs/class.number.php';
//require __DIR__ .'/html_inputs/class.checkbox.php';
//require __DIR__ .'/html_inputs/class.radio.php';

class HTML_Inputs {

	/**
	 * @param $option_name
	 * @param array $option_atts
	 * @param bool $echo
	 * @return string|null
	 */
	public static function render($option_name, array $option_atts = [], string $option_label = '', bool $echo = true): ?string
	{
		$type = $option_atts['type'] ?? 'text';
		$class = Input::getClass($type);
		if ($class) {
			$input = new $class($option_name, $option_atts);
			$input->set_label($option_label);
			if ($echo) {
				print "$input";
			}
			return "$input";
		}
		return '<!-- could not render '. $type .'-input with name '. $option_name .' with atts '. json_encode($option_atts) .' -->';
	}
}
