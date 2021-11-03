<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

class radio extends Input
{
	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
		$this->type = 'radio';
	}

	public function generate_html(): string
	{
		$label = '';
		if ($this->label) {
			$label = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}

		$this->atts['value'] = $this->get_tag_value();
		if (!isset($this->atts['checked']) && $this->get_current_value() == $this->get_tag_value()) {
			$this->atts['checked'] = 'checked';
		}
		$atts = $this->attributes(); // builds HTML attributes


		return '<input type="' . $this->type . '" ' . $atts . '>' . ($this->empty ? '' : $this->content . '</' . $this->type . '>') . $label;
	}
}
