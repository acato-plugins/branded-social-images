<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

class textarea extends Input
{
	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
		$this->type = 'textarea';
		$this->empty = false;
	}

	public function get_tag_value()
	{
		return null;
	}

	public function generate_html(): string
	{
		return '<' . $this->type . ' ' . $this->attributes() . '>' . ($this->empty ? '' : esc_textarea($this->current_value) . '</' . $this->type . '>');
	}
}
