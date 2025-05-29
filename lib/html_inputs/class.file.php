<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

require_once __DIR__ . '/class.text.php';

/**
 * File input class.
 *
 * This class extends the Text class to create a file input field.
 */
class File extends Text {
	/**
	 * Constructor for the File class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           Additional attributes for the input.
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
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- intentional to use WordPress translation functions.
		$button = $this->atts['upload'] ?? __( 'Choose file' );
		unset( $this->atts['upload'] );

		$this->atts['value'] = $this->get_current_value();

		$atts = $this->attributes();

		return $label . '<span class="add-file-select" data-types="' . $types . '">
			<input type="' . $this->type . '" ' . $atts . '/>
			<input type="button" class="button" value="' . esc_attr( $button ) . '"/>
			<span class="filename"></span>
			<span class="message">File will be processed when saving settings</span>
		</span>';
	}

	/**
	 * Initialize the file input functionality.
	 *
	 * This method enqueues the necessary media scripts and adds a script to handle the file upload.
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
							$('.add-file-select').BSIattachFileUpload();
						});
					})(jQuery);
				</script>
				<?php
			}
		);
	}
}
