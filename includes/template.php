<?php

/**
 * BuddyPress Group User Mangament Template
 *
 * @package BP Group User Management
 * @subpackage Template
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Dropdown *********************************************************/

/**
 * Output a select box allowing to pick a group/groups.
 *
 * @since 0.0.1
 *
 * @param mixed $args See {@link bp_groups_get_dropdown()} for arguments
 */
function bp_groups_dropdown( $args = '' ) {
	echo bp_groups_get_dropdown( $args );
}
	/**
	 * Output a select box allowing to pick a group/groups.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $args The function supports these args:
	 *  - selected: Selected ID or array of selected IDs, to not have any
	 *               value as selected, pass anything smaller than 0 (due
	 *               to the nature of select box, the first value would of
	 *               course be selected - though you can have that as none
	 *               (pass 'show_none' arg))
	 *  - orderby: Defaults to 'name'
	 *  - order: Defaults to 'ASC'
	 *  - type: Shorthand for orderby and order arg, which overrides them.
	 *           Defaults to 'alphabetical'
	 *  - parent_id: Group parent when using BP Group Hierarchy. Defaults to 0
	 *  - visibility: Which all visibility to find in? Can be an array or
	 *                 CSV of publish, private, hidden - if not set, all
	 *                 will be selected
	 *  - posts_per_page: Retrieve all groups. Defaults to -1 to get all groups
	 *  - walker: Which walker to use? Defaults to
	 *             {@link BP_Group_Dropdown}
	 *  - select_id: ID of the select box. Defaults to 'bp_group_id'
	 *  - tab: Tabindex value. False or integer
	 *  - options_only: Show only <options>? No <select>?
	 *  - show_none: Boolean or String __( '(No Group)', 'bp-group-user-management' )
	 * @uses BP_Group_Dropdown() As the default walker to generate the
	 *                              dropdown
	 * @uses walk_page_dropdown_tree() To generate the dropdown using the
	 *                                  walker
	 * @uses apply_filters() Calls 'bp_groups_get_dropdown' with the dropdown
	 *                        and args
	 * @return string The dropdown
	 */
	function bp_groups_get_dropdown( $args = '' ) {

		$bpgum = bp_group_user_management();

		/** Arguments *********************************************************/

		/**
		 * Parse arguments against default values
		 *
		 * bp_parse_args() is only available since BP 2.0 see #BP5306
		 * https://buddypress.trac.wordpress.org/ticket/5306
		 */
		$r = bp_parse_args( $args, array(
			'parent_id'          => 0,
			'visibility'         => null,
			'selected'           => 0,
			'exclude'            => array(),
			'orderby'            => 'name',
			'order'              => 'ASC',
			'type'               => 'alphabetical',
			'show_hidden'        => current_user_can( 'bp_moderate' ),
			'walker'             => '',

			// Output-related
			'select_id'          => 'bp_group_id',
			'tab'                => '',
			'options_only'       => false,
			'show_none'          => false,
			'show_without_group' => false,
			'disabled'           => '',
			'multiple'           => ''
		), 'groups_get_dropdown' );

		if ( empty( $r['walker'] ) ) {
			$r['walker'] = new BP_Group_Dropdown();
		}

		// Force array
		if ( ! empty( $r['selected'] ) && ! is_array( $r['selected'] ) ) {

			// Force 0
			if ( is_numeric( $r['selected'] ) && $r['selected'] < 0 ) {
				$r['selected'] = 0;
			}

			$r['selected'] = explode( ',', $r['selected'] );
		}

		// Force array
		if ( ! empty( $r['exclude'] ) && ! is_array( $r['exclude'] ) ) {
			$r['exclude'] = explode( ',', $r['exclude'] );
		}

		/** Setup variables ***************************************************/

		$retval = '';
		$groups = groups_get_groups( array(
			'visibility'         => $r['visibility'],
			'exclude'            => $r['exclude'],
			'parent_id'          => $r['parent_id'],
			'orderby'            => $r['orderby'],
			'order'              => $r['order'],
			'type'               => $r['type'],
			'show_hidden'        => $r['show_hidden'],
			'walker'             => $r['walker'],
		) );
		$groups = $groups['groups'];

		// Ensure groups have a parent_id
		foreach ( $groups as $group ) {
			if ( ! isset( $group->parent_id ) )
				$group->parent_id = 0;
		}

		/** Drop Down *********************************************************/

		// Build the opening tag for the select element
		if ( empty( $r['options_only'] ) ) {

			// Should this select appear disabled?
			$disabled  = disabled( $r['disabled'], true, false );

			// Setup the tab index attribute
			$tab       = ! empty( $r['tab'] ) ? ' tabindex="' . intval( $r['tab'] ) . '"' : '';

			// Open the select tag
			$retval   .= '<select name="' . esc_attr( $r['select_id'] ) . '" id="' . esc_attr( $r['select_id'] ) . '"' . $disabled . $tab . '>' . "\n";
		}

		// Display a leading 'no-value' option, with or without custom text
		if ( ! empty( $r['show_none'] ) ) {

			// Open the 'no-value' option tag
			$retval .= "\t<option value=\"\" class=\"level-0\">";

			// Use 'show_none'
			if ( ! empty( $r['show_none'] ) && is_string( $r['show_none'] ) ) {
				$retval .= esc_html( $r['show_none'] );

			// Otherwise, make some educated guesses
			} else {
				$retval .= esc_html__( 'No group', 'bp-group-user-management' );
			}

			// Close the 'no-value' option tag
			$retval .= '</option>';
		}

		// Items found so walk the tree
		if ( ! empty( $groups ) ) {
			$retval .= walk_page_dropdown_tree( $groups, 0, $r );
		}

		// Close the selecet tag
		if ( empty( $r['options_only'] ) ) {
			$retval .= '</select>';
		}

		return apply_filters( 'bp_groups_get_dropdown', $retval, $r );
	}

if ( class_exists( 'Walker' ) && ! class_exists( 'BP_Group_Dropdown' ) ) :
/**
 * Create HTML dropdown list for BuddyPress groups.
 *
 * @since 0.0.1
 * @uses Walker
 */
class BP_Group_Dropdown extends Walker {

	/**
	 * @see Walker::$tree_type
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	var $tree_type;

	/**
	 * @see Walker::$db_fields
	 *
	 * @since 0.0.1
	 *
	 * @var array
	 */
	var $db_fields = array( 'parent' => 'parent_id', 'id' => 'id' );

	/** Methods ***************************************************************/

	/**
	 * Set the tree_type
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->tree_type = 'group';
	}

	/**
	 * @see Walker::start_el()
	 *
	 * @since 0.0.1
	 *
	 * @param string $output Passed by reference. Used to append additional
	 *                        content.
	 * @param object $group Group data object.
	 * @param int $depth Depth of group in reference to parent groups. Used
	 *                    for padding.
	 * @param array $args Uses 'selected' argument for selected group to set
	 *                     selected HTML attribute for option element.
	 * @param int $current_object_id
	 * @uses apply_filters() Calls 'bbp_walker_dropdown_group_title' with the
	 *                        title, output, group, depth and args
	 */
	public function start_el( &$output, $group, $depth = 0, $args = array(), $current_object_id = 0 ) {
		$pad     = str_repeat( '&nbsp;', (int) $depth * 3 );
		$output .= '<option class="level-' . (int) $depth . '"';

		// Disable the <option> if we're told to do so
		if ( apply_filters( 'bp_walker_dropdown_disable_option', false, $group ) ) {
			$output .= ' disabled="disabled" value=""';
		} else {
			$output .= ' value="' . (int) $group->id .'"' . selected( in_array( $group->id, (array) $args['selected'] ), true, false );
		}

		$output .= '>';
		$title   = apply_filters( 'bp_walker_dropdown_group_title', $group->name, $output, $group, $depth, $args );
		$output .= $pad . esc_html( $title );
		$output .= "</option>\n";
	}

}
endif;
