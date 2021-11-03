<?php

namespace Clearsite\Tools\HTML_Inputs;

defined('ABSPATH') or die('You cannot be here.');

require_once __DIR__ . '/class.text.php'; // dependency

class file extends text
{
	public function __construct($attribute_name, $atts)
	{
		parent::__construct($attribute_name, $atts);
		$this->type = 'hidden';
	}

	public function generate_html(): string
	{
		$this->init();
		$label = '';
		if ($this->label) {
			$label = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}
		$types = $this->atts['types'] ?? '';
		unset($this->atts['types']);
		$button = $this->atts['upload'] ?? __('Choose file');
		unset($this->atts['upload']);

		$this->atts['value'] = $this->get_current_value();
		$atts = $this->attributes(); // builds HTML attributes


		return $label . '<span class="add-file-select" data-types="' . $types . '">
			<input type="' . $this->type . '" ' . $atts . '/>
			<input type="button" class="button" value="' . esc_attr($button) . '"/>
			<span class="filename"></span>
			<span class="message">File will be processed when saving settings</span>
		</span>';
	}

	public function init(): void
	{
		static $once;
		if ($once) {
			return;
		}
		$once = true;
		wp_enqueue_media();
		add_action('admin_footer', function () {
			?>
			<script>
				;(function ($) {
					$(document).ready(function () {
						$('.add-file-select').BSIattachFileUpload();
					});
				})(jQuery);
			</script><?php
		});
	}
}
