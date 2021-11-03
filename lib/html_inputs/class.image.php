<?php

namespace Clearsite\Tools\HTML_Inputs;

use Clearsite\Plugins\OGImage\Plugin;

defined('ABSPATH') or die('You cannot be here.');

require_once __DIR__ . '/class.text.php'; // dependency

class image extends text
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

		$this->atts['value'] = $this->get_current_value();
		$atts = $this->attributes(); // builds HTML attributes


		return $label . '<span class="add-image-select" data-types="' . $types . '">
			<input type="' . $this->type . '" ' . $atts . '/>
			<div class="image-preview-wrapper">
				<img src="' . esc_attr(wp_get_attachment_url($this->get_current_value())) . '" width="200">
			</div>
			<input type="button" class="button" value="' . esc_attr(__("Choose image", Plugin::TEXT_DOMAIN)) . '"/>
			<input type="button" class="button remove" value="' . esc_attr(__("Remove image", Plugin::TEXT_DOMAIN)) . '"/>
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
						$('.add-image-select').attachMediaUpload();
					});
				})(jQuery);
			</script><?php
		});
	}
}
