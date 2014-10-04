<?php

/**
 * BuddyPress Group Organizer Extension
 *
 * @package BP Group User Management
 * @subpackage Extend
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BPGUM_Organizer' ) ) :
/**
 * Extension class for BP Group Organizer
 *
 * @since 1.0.0
 */
class BPGUM_Organizer {

	/**
	 * Setup default actions
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_print_styles',  array( $this, 'print_styles'      ) );
		// add_filter( 'bp_get_group_avatar', array( $this, 'group_avatar_meso' ) );

		// Admin Bar
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 91 );
	}

	/** Methods ******************************************************/

	/**
	 * Output custom styles for the organizer admin page
	 *
	 * @since 1.0.0
	 */
	public function print_styles() { ?>

		<style type="text/css">
			.menu-item-handle .item-title {
				padding-left: 25px;
			}

			.menu-item-handle .item-title .avatar {
				position: absolute;
				left: 0;
				top: 0;
				margin: 5px;
			}
		</style>

		<?php
	}

	/**
	 * Enlarge the 'micro' group avatar from 15px to 30px
	 *
	 * @see bp_get_group_avatar_micro() in BP Group Organizer
	 *
	 * @since 1.0.0
	 *
	 * @param string $avatar Avatar img element
	 * @return string Avatar
	 */
	public function group_avatar_meso( $avatar ) {

		// Prevent infinite loop, so unset filter
		remove_filter( 'bp_get_group_avatar', array( $this, __METHOD__ ) );

		// Generate a genuine avatar without filter
		$avatar = bp_get_group_avatar( array( 'type' => 'thumb', 'width' => '30', 'height' => '30' ) );

		// Setup later filter to restore this one later
		add_filter( 'bp_get_group_avatar', array( $this, 'reset_group_avatar_filter' ), 999 );

		return $avatar;
	}

	/**
	 * Restore the group avatar filter
	 *
	 * @since 1.0.0
	 *
	 * @param string $avatar Avatar img element
	 * @return string Avatar
	 */
	public function reset_group_avatar_filter( $avatar ) {

		// Disable current filter
		remove_filter( 'bp_get_group_avatar', array( $this, __METHOD__ ), 999 );

		// Restore the avatar filter
		add_filter( 'bp_get_group_avatar', array( $this, 'group_avatar_meso' ) );

		// Just send through
		return $avatar;
	}

	/** Admin Bar ****************************************************/

	/**
	 * Modify New Group link in the Create New admin bar menu
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {

		// Get the New Group node
		if ( $node = (array) $wp_admin_bar->get_node( 'new-bp_group' ) ) {

			// Manipulate node for admins
			if ( current_user_can( 'bp_moderate' ) ) {

				// Send admins to Group Organizer page
				$node['href'] = add_query_arg( 'page', 'group_organizer', admin_url( 'admin.php' ) );
			}

			// Overwrite node
			$wp_admin_bar->add_node( $node );
		}
	}
}

/**
 * Setup extension for BP Group Organizer
 *
 * @since 1.0.0
 *
 * @uses BPGUM_Organizer
 */
function bpgum_setup_organizer() {
	new BPGUM_Organizer;
}

/* Fire on organizer page */
add_action( 'load-groups_page_group_organizer', 'bpgum_setup_organizer', 20 );

endif;
