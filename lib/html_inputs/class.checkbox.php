<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

class checkbox extends Input
{
	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
		$this->type = 'checkbox';
	}

	public function set_value()
	{
		$this->atts['value'] = $this->atts['value'] ?? 'on';
		$this->value = $this->atts['value'];
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

		return '<span class="field-wrap"><input type="hidden" name="' . $this->atts['name'] . '" value="off" />' .
			'<input type="' . $this->type . '" ' . $atts . '>' . ($this->empty ? '' : $this->content . '</' . $this->type . '>') . $label . '</span>';
	}
}
