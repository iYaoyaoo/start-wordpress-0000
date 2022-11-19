<?php
/**
 * Blocks API: GC_Block_Type class
 *
 * @package GeChiUI
 * @subpackage Blocks
 *
 */

/**
 * Core class representing a block type.
 *
 *
 *
 * @see register_block_type()
 */
class GC_Block_Type {

	/**
	 * Block API version.
	 *
	 * @var int
	 */
	public $api_version = 1;

	/**
	 * Block type key.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Human-readable block type label.
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Block type category classification, used in search interfaces
	 * to arrange block types by category.
	 *
	 * @var string|null
	 */
	public $category = null;

	/**
	 * Setting parent lets a block require that it is only available
	 * when nested within the specified blocks.
	 *
	 * @var array|null
	 */
	public $parent = null;

	/**
	 * Block type icon.
	 *
	 * @var string|null
	 */
	public $icon = null;

	/**
	 * A detailed block type description.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Additional keywords to produce block type as result
	 * in search interfaces.
	 *
	 * @var string[]
	 */
	public $keywords = array();

	/**
	 * The translation textdomain.
	 *
	 * @var string|null
	 */
	public $textdomain = null;

	/**
	 * Alternative block styles.
	 *
	 * @var array
	 */
	public $styles = array();

	/**
	 * Block variations.
	 *
	 * @var array
	 */
	public $variations = array();

	/**
	 * Supported features.
	 *
	 * @var array|null
	 */
	public $supports = null;

	/**
	 * Structured data for the block preview.
	 *
	 * @var array|null
	 */
	public $example = null;

	/**
	 * Block type render callback.
	 *
	 * @var callable
	 */
	public $render_callback = null;

	/**
	 * Block type attributes property schemas.
	 *
	 * @var array|null
	 */
	public $attributes = null;

	/**
	 * 此类区块所继承的上下文的值。
	 *
	 * @var array
	 */
	public $uses_context = array();

	/**
	 * 此类区块所提供的上下文。
	 *
	 * @var array|null
	 */
	public $provides_context = null;

	/**
	 * Block type editor only script handle.
	 *
	 * @var string|null
	 */
	public $editor_script = null;

	/**
	 * Block type front end and editor script handle.
	 *
	 * @var string|null
	 */
	public $script = null;

	/**
	 * Block type front end only script handle.
	 *
	 * @var string|null
	 */
	public $view_script = null;

	/**
	 * Block type editor only style handle.
	 *
	 * @var string|null
	 */
	public $editor_style = null;

	/**
	 * Block type front end and editor style handle.
	 *
	 * @var string|null
	 */
	public $style = null;

	/**
	 * Constructor.
	 *
	 * Will populate object properties from the provided arguments.
	 *
	 *              `keywords`, `textdomain`, `styles`, `supports`, `example`,
	 *              `uses_context`, and `provides_context` properties.
	 *
	 * @see register_block_type()
	 *
	 * @param string       $block_type Block type name including namespace.
	 * @param array|string $args       {
	 *     Optional. Array or string of arguments for registering a block type. Any arguments may be defined,
	 *     however the ones described below are supported by default. Default empty array.
	 *
	 *     @type string        $api_version      Block API version.
	 *     @type string        $title            Human-readable block type label.
	 *     @type string|null   $category         Block type category classification, used in
	 *                                           search interfaces to arrange block types by category.
	 *     @type array|null    $parent           Setting parent lets a block require that it is only
	 *                                           available when nested within the specified blocks.
	 *     @type string|null   $icon             Block type icon.
	 *     @type string        $description      A detailed block type description.
	 *     @type string[]      $keywords         Additional keywords to produce block type as
	 *                                           result in search interfaces.
	 *     @type string|null   $textdomain       The translation textdomain.
	 *     @type array         $styles           Alternative block styles.
	 *     @type array         $variations       Block variations.
	 *     @type array|null    $supports         Supported features.
	 *     @type array|null    $example          Structured data for the block preview.
	 *     @type callable|null $render_callback  Block type render callback.
	 *     @type array|null    $attributes       Block type attributes property schemas.
	 *     @type array         $uses_context     此类区块所继承的上下文的值。
	 *     @type array|null    $provides_context 此类区块所提供的上下文。
	 *     @type string|null   $editor_script    Block type editor only script handle.
	 *     @type string|null   $script           Block type front end and editor script handle.
	 *     @type string|null   $view_script      Block type front end only script handle.
	 *     @type string|null   $editor_style     Block type editor only style handle.
	 *     @type string|null   $style            Block type front end and editor style handle.
	 * }
	 */
	public function __construct( $block_type, $args = array() ) {
		$this->name = $block_type;

		$this->set_props( $args );
	}

	/**
	 * Renders the block type output for given attributes.
	 *
	 *
	 * @param array  $attributes Optional. Block attributes. Default empty array.
	 * @param string $content    Optional. Block content. Default empty string.
	 * @return string Rendered block type output.
	 */
	public function render( $attributes = array(), $content = '' ) {
		if ( ! $this->is_dynamic() ) {
			return '';
		}

		$attributes = $this->prepare_attributes_for_render( $attributes );

		return (string) call_user_func( $this->render_callback, $attributes, $content );
	}

	/**
	 * Returns true if the block type is dynamic, or false otherwise. A dynamic
	 * block is one which defers its rendering to occur on-demand at runtime.
	 *
	 *
	 * @return bool Whether block type is dynamic.
	 */
	public function is_dynamic() {
		return is_callable( $this->render_callback );
	}

	/**
	 * Validates attributes against the current block schema, populating
	 * defaulted and missing values.
	 *
	 *
	 * @param array $attributes Original block attributes.
	 * @return array Prepared block attributes.
	 */
	public function prepare_attributes_for_render( $attributes ) {
		// If there are no attribute definitions for the block type, skip
		// processing and return verbatim.
		if ( ! isset( $this->attributes ) ) {
			return $attributes;
		}

		foreach ( $attributes as $attribute_name => $value ) {
			// If the attribute is not defined by the block type, it cannot be
			// validated.
			if ( ! isset( $this->attributes[ $attribute_name ] ) ) {
				continue;
			}

			$schema = $this->attributes[ $attribute_name ];

			// Validate value by JSON schema. An invalid value should revert to
			// its default, if one exists. This occurs by virtue of the missing
			// attributes loop immediately following. If there is not a default
			// assigned, the attribute value should remain unset.
			$is_valid = rest_validate_value_from_schema( $value, $schema, $attribute_name );
			if ( is_gc_error( $is_valid ) ) {
				unset( $attributes[ $attribute_name ] );
			}
		}

		// Populate values of any missing attributes for which the block type
		// defines a default.
		$missing_schema_attributes = array_diff_key( $this->attributes, $attributes );
		foreach ( $missing_schema_attributes as $attribute_name => $schema ) {
			if ( isset( $schema['default'] ) ) {
				$attributes[ $attribute_name ] = $schema['default'];
			}
		}

		return $attributes;
	}

	/**
	 * Sets block type properties.
	 *
	 *
	 * @param array|string $args Array or string of arguments for registering a block type.
	 *                           See GC_Block_Type::__construct() for information on accepted arguments.
	 */
	public function set_props( $args ) {
		$args = gc_parse_args(
			$args,
			array(
				'render_callback' => null,
			)
		);

		$args['name'] = $this->name;

		/**
		 * Filters the arguments for registering a block type.
		 *
		 *
		 * @param array  $args       Array of arguments for registering a block type.
		 * @param string $block_type Block type name including namespace.
		 */
		$args = apply_filters( 'register_block_type_args', $args, $this->name );

		foreach ( $args as $property_name => $property_value ) {
			$this->$property_name = $property_value;
		}
	}

	/**
	 * Get all available block attributes including possible layout attribute from Columns block.
	 *
	 *
	 * @return array Array of attributes.
	 */
	public function get_attributes() {
		return is_array( $this->attributes ) ?
			$this->attributes :
			array();
	}
}
