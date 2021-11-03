<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

require_once __DIR__ . '/class.option.php'; // dependency

class select extends Input
{
	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
	}

	public function generate_html(): string
	{
		$label = '';
		if ($this->label) {
			$label = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}

		$options = '';
		foreach ($this->atts['options'] as $option_value => $option_label) {
			$_atts = $this->atts;
			$_atts['value'] = $option_value;
			unset($_atts['class'], $_atts['options'], $_atts['current_value'], $_atts['default']);
			$option = new option($this->name, $_atts);
			$option->set_label($option_label, true);
			$option->current_value = $this->get_current_value();
			$options .= "$option";
		}

		$atts = $this->attributes(); // builds HTML attributes

		return $label . '<select ' . $atts . '/>' . $options . '</select>';
	}
}
