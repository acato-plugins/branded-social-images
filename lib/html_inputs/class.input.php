<?php
/**
 * Input definition file.
 *
 * @package Acato\Plugins\OGImage
 */

namespace Acato\Tools\HTML_Inputs;

defined( 'ABSPATH' ) || die( 'You cannot be here.' );

/**
 * Class Input
 *
 * Basis for all input fields.
 */
class Input {

	/**
	 * The type of the input field.
	 *
	 * @var string
	 */
	protected $type = 'text';

	/**
	 * Whether the input field is empty or not.
	 *
	 * @var bool
	 */
	protected $empty = true;

	/**
	 * The name of the input field.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The ID of the input field.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * The label for the input field.
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * Additional information for the input field.
	 *
	 * @var string
	 */
	protected $info;

	/**
	 * Icon for the additional information.
	 *
	 * @var string
	 */
	protected $info_icon;

	/**
	 * Comment for the input field.
	 *
	 * @var string
	 */
	protected $comment;

	/**
	 * Icon for the comment.
	 *
	 * @var string
	 */
	protected $comment_icon;

	/**
	 * Attributes for the input field.
	 *
	 * @var array
	 */
	protected $atts = [];

	/**
	 * Content for the input field.
	 *
	 * @var string
	 */
	protected $content = '';

	/**
	 * The value of the input field.
	 *
	 * @var mixed
	 */
	protected $value = null;

	/**
	 * The current value of the input field.
	 *
	 * @var mixed
	 */
	protected $current_value = null;

	/**
	 * Constructor for the Input class.
	 *
	 * @param string $attribute_name The name of the attribute.
	 * @param array  $atts           The attributes for the input.
	 */
	public function __construct( $attribute_name, $atts ) {
		$this->name = $attribute_name;
		$this->set_attributes( $atts );

		$this->set_value();
		$this->set_current_value();
		$this->set_attribute_name();
		$this->set_comment();
		$this->set_comment_icon();
		$this->set_info();
		$this->set_info_icon();
	}

	/**
	 * Sets the value for the input field.
	 *
	 * If 'default' is set in attributes, it will override the 'value'.
	 */
	public function set_value() {
		$this->atts['value'] = $this->atts['value'] ?? '';
		$this->value         = $this->atts['value'];
		if ( array_key_exists( 'default', $this->atts ) ) {
			$this->value = $this->atts['default'];
		}
	}

	/**
	 * Sets the current value for the input field.
	 *
	 * If 'default' is set in attributes, it will override the 'value'.
	 * If 'current_value' is set in attributes, it will override the 'value'.
	 */
	public function set_current_value() {
		$this->atts['value'] = $this->atts['value'] ?? '';
		$this->current_value = $this->atts['value'];
		if ( array_key_exists( 'default', $this->atts ) ) {
			$this->current_value = $this->atts['default'];
		}
		if ( array_key_exists( 'current_value', $this->atts ) ) {
			$this->current_value = $this->atts['current_value'];
		}
	}

	/**
	 * Sets the label for the input field.
	 *
	 * @param string $text     The label text.
	 * @param bool   $id_sufix Whether to use the label text as a suffix for the ID.
	 */
	public function set_label( $text, $id_sufix = false ) {
		$this->label = $text;
		if ( ! $this->id ) {
			$this->generate_id( $id_sufix ? $text : '' );
		}
	}

	/**
	 * Sets the comment for the input field.
	 */
	public function set_comment() {
		$this->comment = empty( $this->atts['comment'] ) ? '' : $this->atts['comment'];
		unset( $this->atts['comment'] );
	}

	/**
	 * Sets the icon for the comment.
	 */
	public function set_comment_icon() {
		$this->comment_icon = empty( $this->atts['comment-icon'] ) ? '' : $this->atts['comment-icon'];
		unset( $this->atts['comment-icon'] );
	}

	/**
	 * Sets the additional information for the input field.
	 */
	public function set_info() {
		$this->info = empty( $this->atts['info'] ) ? '' : $this->atts['info'];
		unset( $this->atts['info'] );
	}

	/**
	 * Sets the icon for the additional information.
	 */
	public function set_info_icon() {
		$this->info_icon = empty( $this->atts['info-icon'] ) ? '' : $this->atts['info-icon'];
		unset( $this->atts['info-icon'] );
	}

	/**
	 * Sets the attributes for the input field.
	 *
	 * @param array $atts The attributes to set.
	 */
	public function set_attributes( $atts ) {
		$this->atts = $atts;
	}

	/**
	 * Sets the name attribute for the input field.
	 *
	 * This method constructs the name attribute based on the input field's name,
	 * namespace, and whether it is a multiple input.
	 */
	public function set_attribute_name() {
		// default is "just set" .
		$this->atts['name'] = '[' . $this->name . ']';
		if ( ! empty( $this->atts['namespace'] ) ) {
			$this->atts['name'] = '[' . $this->atts['namespace'] . ']' . $this->atts['name'];
			unset( $this->atts['namespace'] );
		}
		if ( ! empty( $this->atts['multiple'] ) ) {
			$this->atts['name'] .= '[]';
		}
		$this->atts['name'] = 'branded_social_images' . $this->atts['name'];
	}

	/**
	 * Returns the class name for the specified input type.
	 *
	 * @param string $type The type of input (e.g., 'text', 'checkbox', etc.).
	 *
	 * @return string|false The class name if it exists, or false if not found.
	 */
	public static function get_input_class( $type ) {
		$type = strtolower( $type );
		if ( file_exists( __DIR__ . '/class.' . $type . '.php' ) ) {
			$class = __NAMESPACE__ . '\\' . ucfirst( $type );
			if ( ! class_exists( $class ) ) {
				require_once __DIR__ . '/class.' . $type . '.php';
			}

			return $class;
		}
		if ( 'text' !== $type ) {
			return self::get_input_class( 'text' );
		}

		return false;
	}

	/**
	 * Returns the attributes as a string.
	 *
	 * @return string
	 */
	public function attributes(): string {
		$atts   = $this->atts;
		$output = [];
		foreach ( $atts as $attribute_name => $attribute_values ) {
			if ( is_array( $attribute_values ) ) {
				$attribute_values = implode( '', $attribute_values );
			}
			$attribute_values = esc_attr( $attribute_values );
			$output[]         = "$attribute_name=\"$attribute_values\"";
		}

		return implode( ' ', $output );
	}

	/**
	 * Gets the value for the input field as it is rendered in a tag attribute.
	 *
	 * @return mixed
	 */
	public function get_tag_value() {
		return $this->value;
	}

	/**
	 * Gets the current value of the input field.
	 *
	 * This method returns the current value of the input field, which may differ from the tag value
	 * if the input has been modified or set to a different value.
	 *
	 * @return mixed The current value of the input field.
	 */
	public function get_current_value() {
		return $this->current_value;
	}

	/**
	 * Generates the HTML for the input field.
	 *
	 * @return string
	 */
	public function generate_html(): string {
		$label = '';
		if ( $this->label ) {
			$label            = '<label for="' . $this->id . '">' . $this->label . '</label>';
			$this->atts['id'] = $this->id;
		}

		$this->atts['value'] = $this->get_tag_value();
		$atts                = $this->attributes();

		return $label . '<input type="' . $this->type . '" ' . $atts . '>' . ( $this->empty ? '' : $this->content . '</' . $this->type . '>' );
	}

	/**
	 * Generates a unique ID for the input field.
	 *
	 * If an ID is already set, it returns that ID.
	 * If an ID is provided in the attributes, it uses that.
	 * Otherwise, it generates a new ID based on the name and an optional suffix.
	 *
	 * @param string $id_suffix Optional suffix to append to the ID.
	 *
	 * @return string The generated or provided ID.
	 */
	private function generate_id( $id_suffix = '' ): string {
		static $ids;
		if ( ! $ids ) {
			$ids = [];
		}
		if ( ! empty( $this->id ) ) {
			return $this->id;
		}
		if ( ! empty( $this->atts['id'] ) ) {
			$this->id = $this->atts['id'];

			return $this->id;
		}
		$id_suffix = sanitize_title( $id_suffix );
		$id        = $this->name . $id_suffix;
		$i         = 0;
		while ( in_array( $id, $ids, true ) ) {
			$id = $this->name . $id_suffix . ( ++ $i );
		}
		$this->id = $id;

		return $id;
	}

	/**
	 * Returns the comment for the input field.
	 *
	 * If a comment is set, it will be wrapped in a span with the class 'comment'.
	 * If an icon is set for the comment, it will be prepended to the comment.
	 *
	 * @return string The formatted comment.
	 */
	public function the_comment() {
		$comment = '';
		if ( $this->comment ) {
			$comment = '<span class="comment">' . $this->comment;
			if ( $this->the_info() ) {
				$comment .= $this->the_info();

				$this->info      = false;
				$this->info_icon = false;
			}
			$comment .= '</span>';
		}
		if ( ! empty( $this->comment_icon ) ) {
			$comment = '<i class="toggle-comment dashicons-before ' . $this->comment_icon . '"></i>' . $comment;
		}

		return $comment;
	}

	/**
	 * Returns the additional information for the input field.
	 *
	 * If info is set, it will be wrapped in a span with the class 'info'.
	 * If an icon is set for the info, it will be prepended to the info.
	 *
	 * @return string The formatted info.
	 */
	public function the_info() {
		$info = '';
		if ( $this->info ) {
			$info = '<span class="info">' . $this->info . '</span>';
		}
		if ( ! empty( $this->info_icon ) ) {
			$info = '<i class="toggle-info dashicons-before ' . $this->info_icon . '"></i>' . $info;
		}

		return $info;
	}

	/**
	 * Returns the HTML representation of the input field.
	 *
	 * This method generates the HTML for the input field, including its label, comment, and info.
	 *
	 * @return string The complete HTML representation of the input field.
	 */
	public function __toString() {
		return $this->generate_html() . $this->the_comment() . $this->the_info();
	}
}
