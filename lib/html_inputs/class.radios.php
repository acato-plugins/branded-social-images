<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

require_once __DIR__ . '/class.radio.php'; // dependency

class radios extends radio
{
	private $namespace;

	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
		$this->namespace = $atts['namespace']; // preserve the namespace ... it gets lost somewhere. THIS IS A TEMPORARY SOLUTION
	}

	public function generate_html(): string
	{
		$label = '';
		if ($this->label) {
			$label = '<label class="radios-label">' . $this->label . '</label>';
		}

		$options = '';
		foreach ($this->atts['options'] as $option_value => $option_label) {
			$_atts = $this->atts;
			$_atts['namespace'] = $this->namespace;
			$_atts['value'] = $option_value;
			unset($_atts['class'], $_atts['options'], $_atts['current_value'], $_atts['default']);
			$radio = new radio($this->name, $_atts);
			$radio->set_label($option_label, true);
			$radio->current_value = $this->get_current_value();
			$options .= "<span class='option-wrap'>$radio</span>";
		}

		return $label . '<span class="options-wrap">' . $options . '</span>';
	}
}
