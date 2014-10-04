<?php

/**
 * BP Group Hierarchy Extension Functions
 *
 * @package BP Group User Management
 * @subpackage Extend
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BPGUM_Hierarchy' ) ) :
/**
 * Extension class for BP Group Hierarchy
 */
class BPGUM_Hierarchy {

	/** Methods ******************************************************/

}

/**
 * Setup extension for BP Group Hierarchy
 *
 * @since 1.0.0
 *
 * @uses BPGUM_Hierarchy
 */
function bpgum_setup_hierarchy() {
	new BPGUM_Hierarchy;
}

// Fire on bp_include
add_action( 'bp_include', 'bpgum_setup_hierarchy', 20 );

endif;
