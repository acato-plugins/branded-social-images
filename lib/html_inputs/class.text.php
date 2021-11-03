<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

class text extends Input
{

	public function get_tag_value()
	{
		return $this->current_value; // a text field had it's current value in value;
	}
}
