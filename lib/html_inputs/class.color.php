<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

class color extends Input
{
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

	public function generate_html(): string
	{
		$label = '';
		if ($this->label) {
			$label = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}

		$this->atts['value'] = $this->get_tag_value();
		$atts = $this->attributes();


		return $label . '<span class="field-wrap"><input type="' . $this->type . '" ' . $atts . '/><span class="swatch"></span></span>';
	}
}
