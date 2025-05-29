<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

use Acato\Plugins\OGImage\Plugin;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

require_once __DIR__ . '/class.text.php';

/**
 * The Image input class.
 */
class Image extends Text {
	/**
	 * Constructor for the Image input class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		parent::__construct( $attribute_name, $atts );
		$this->type = 'hidden';
	}

	/**
	 * Generates the HTML for the input field.
	 *
	 * @return string
	 */
	public function generate_html(): string {
		$this->init();
		$label = '';
		if ( $this->label ) {
			$label            = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}
		$types = $this->atts['types'] ?? '';
		unset( $this->atts['types'] );

		$this->atts['value'] = $this->get_current_value();
		$atts                = $this->attributes();

		return $label . '<span class="add-image-select" data-types="' . $types . '">
			<input type="' . $this->type . '" ' . $atts . '/>
			<div class="image-preview-wrapper">
				<img src="' . esc_attr( wp_get_attachment_url( $this->get_current_value() ) ) . '" width="200">
			</div>
			<input type="button" class="button" value="' . esc_attr( __( 'Choose image', 'bsi' ) ) . '"/>
			<input type="button" class="button remove" value="' . esc_attr( __( 'Remove image', 'bsi' ) ) . '"/>
		</span>';
	}

	/**
	 * Initializes the media uploader for the image input.
	 *
	 * This method ensures that the media uploader is only initialized once.
	 */
	public function init(): void {
		static $once;
		if ( $once ) {
			return;
		}
		$once = true;
		wp_enqueue_media();
		add_action(
			'admin_footer',
			function () {
				?>
				<script>
					;(function ($) {
						$(document).ready(function () {
							$('.add-image-select').attachMediaUpload();
						});
					})(jQuery);
				</script>
				<?php
			}
		);
	}
}
