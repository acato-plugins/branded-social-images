<?php

namespace Clearsite\Tools\HTML_Inputs;

class color extends Input {
	public function __construct($attribute_name, $atts)
	{
		if (empty($atts['class'])) {
			$atts['class'] = '';
		}
		$atts['class'] .= ' color-picker';

		parent::__construct($attribute_name, $atts);
		$this->type = 'text';
	}

	public function get_tag_value()
	{
		return $this->current_value; // a text field had it's current value in value;
	}
}
