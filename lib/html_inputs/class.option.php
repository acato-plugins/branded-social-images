<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

class option extends Input
{
	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
		$this->type = 'option';
	}

	public function generate_html(): string
	{
		$this->atts['value'] = $this->get_tag_value();
		if (!isset($this->atts['selected']) && $this->get_current_value() == $this->get_tag_value()) {
			$this->atts['selected'] = 'selected';
		}
		$atts = $this->attributes(); // builds HTML attributes

		return '<option ' . $atts . '>' . $this->label . '</option>';
	}
}
