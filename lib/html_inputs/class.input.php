<?php

namespace Clearsite\Tools\HTML_Inputs;

class Input {

	protected $type = 'text';
	protected $empty = true;

	protected $name;
	protected $id;
	protected $label;
	protected $atts = [];
	protected $content = '';
	protected $current_value = null;

	public function __construct($attribute_name, $atts)
	{
		$this->name = $attribute_name;
		$this->set_attributes($atts);

		$this->set_current_value();
		$this->set_attribute_name();
	}

	public function set_current_value()
	{
		$this->atts['value'] = $this->atts['value'] ?? '';
		$this->current_value = $this->atts['value'];
		if (array_key_exists('default', $this->atts)) {
			$this->current_value = $this->atts['default'];
		}
		if (array_key_exists('current_value', $this->atts)) {
			$this->current_value = $this->atts['current_value'];
		}
	}

	public function set_label($text)
	{
		$this->label = $text;
		if (!$this->id) {
			$this->generate_id();
		}
	}

	public function set_attributes($atts)
	{
		$this->atts = $atts;
	}

	public function set_attribute_name() {
		// default is "just set"
		$this->atts['name'] = '[' . $this->name . ']';
		if (!empty($this->atts['namespace'])) {
			$this->atts['name'] = '[' . $this->atts['namespace'] . ']' . $this->atts['name'];
			unset($this->atts['namespace']);
		}
		if (!empty($this->atts['multiple'])) {
			$this->atts['name'] .= '[]';
		}
		$this->atts['name'] = 'branded_social_images' . $this->atts['name'];
	}

	public static function getClass($type)
	{
		if (file_exists( __DIR__ .'/class.'. $type .'.php')) {
			$class = __NAMESPACE__ . '\\' . ucfirst($type);
			if (!class_exists($class)) {
				require_once __DIR__ .'/class.'. $type .'.php';
			}
			return $class;
		}
		if ($type != 'text') {
			return self::getClass('text');
		}
		return false;
	}

	public function attributes(): string
	{
		$atts = $this->atts;
		$output = [];
		foreach ($atts as $attribute_name => $attribute_values) {
			if (is_array($attribute_values)) {
				$attribute_values = implode('', $attribute_values);
			}
			$attribute_values = esc_attr($attribute_values);
			$output[] = "$attribute_name=\"$attribute_values\"";
		}
		return implode(' ', $output);
	}

	public function get_tag_value()
	{
		return $this->current_value; // a text field had it's current value in value;
	}

	public function generate_html(): string
	{
		$label = '';
		if ($this->label) {
			$label = '<label for="'. $this->id .'">'. $this->label .'</label>';
			$this->atts['id'] = $this->id;
		}

		$this->atts['value'] = $this->get_tag_value();
		$atts = $this->attributes();


		return $label .'<input type="'. $this->type .'" '. $atts .'>' . ($this->empty ? '' : $this->content . '</'. $this->type .'>');
	}

	private function generate_id($id_suffix = ''): string
	{
		static $ids;
		if (!$ids) {
			$ids = [];
		}
		if (!empty($this->id)) {
			return $this->id;
		}
		if (!empty($this->atts['id'])) {
			$this->id = $this->atts['id'];
			return $this->id;
		}
		$id = $this->name . '';
		$i = 0;
		while (in_array($id, $ids)) {
			$id = $this->name . '' . (++$i);
		}
		$this->id = $id;
		return $id;
	}

	public function __toString() {
		return $this->generate_html();
	}
}
