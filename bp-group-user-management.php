<?php

/**
 * The BuddyPress Group User Management Plugin
 *
 * @package BP Group User Management
 * @subpackage Main
 */

/**
 * Plugin Name:       BP Group User Management
 * Plugin URI:        https://github.com/lmoffereins/bp-group-user-management
 * Description:       Integrate BuddyPress group member management with WordPress user management.
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins
 * Version:           0.0.2
 * Text Domain:       bp-group-user-management
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/bp-group-user-management
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_Group_User_Management' ) ) :
/**
 * Main plugin class
 *
 * @since 0.0.1
 */
class BP_Group_User_Management {

	/**
	 * Main plugin instance follows singleton pattern
	 *
	 * @since 0.0.1
	 *
	 * @uses BP_Group_User_Management::setup_globals()
	 * @uses BP_Group_User_Management::includes()
	 * @uses BP_Group_User_Management::setup_actions()
	 * @see bp_group_user_management()
	 * @return The single plugin instance
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		// Only run these methods if they haven't been ran previously
		if ( null === $instance ) {
			$instance = new BP_Group_User_Management;		
			$instance->setup_globals();
			$instance->includes();
			$instance->setup_actions();
		}

		// Always return the instance
		return $instance;
	}

	/**
	 * Setup class default variables
	 *
	 * @since 0.0.1
	 */
	private function setup_globals() {

		/** Version **************************************************/

		$this->version      = '0.0.2';

		/** Plugin ***************************************************/

		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url(  $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes' );

		// Languages
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );

		/** Supports *************************************************/

		$this->bp_group_hierarchy = defined( 'BP_GROUP_HIERARCHY_VERSION' );
		$this->bp_group_organizer = function_exists( 'bp_group_organizer_admin' );

		/** Flags ****************************************************/

		// For BP pre-2.0
		$this->unblock_search     = false;
	}

	/**
	 * Include required files
	 *
	 * @since 0.0.1
	 */
	private function includes() {

		/** Template *************************************************/
		
		require( $this->includes_dir . 'template.php' );

		/** Extend ***************************************************/
		
		// BP Group Hierarchy
		if ( $this->bp_group_hierarchy ) {
			require( $this->includes_dir . 'extend/bp-group-hierarchy.php' );
		}
		
		// BP Group Organizer
		if ( $this->bp_group_organizer ) {
			require( $this->includes_dir . 'extend/bp-group-organizer.php' );
		}
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 0.0.1
	 */
	private function setup_actions() {

		/** Query ****************************************************/

		// User Queries
		add_action( 'pre_user_query',                 array( $this, 'user_group_query'            )        );

		/** Management ***********************************************/

		// User Management screen
		add_action( 'restrict_manage_users',          array( $this, 'users_add_to_group'          )        );
		add_action( 'restrict_manage_users',          array( $this, 'users_remove_from_group'     )        );
		add_action( 'load-users.php',                 array( $this, 'users_group_bulk_change'     )        );

		add_action( 'restrict_manage_users',          array( $this, 'users_filter_by_group'       )        );
		add_filter( 'views_users',                    array( $this, 'users_filter_role_views'     )        );

		add_action( 'admin_print_styles-users.php',   array( $this, 'users_print_styles'          )        );

		add_filter( 'manage_users_columns',           array( $this, 'users_add_group_column'      )        );
		add_filter( 'manage_users_custom_column',     array( $this, 'users_custom_group_column'   ), 10, 3 );

		// Group Queries
		add_filter( 'bp_groups_get_paged_groups_sql', array( $this, 'groups_query_unblock_search' ), 10, 3 );

		/** Profile **************************************************/

		// User Profile screen
		add_action( 'edit_user_profile',              array( $this, 'profile_edit_membership'     )        );

		/** Misc *****************************************************/

		// Dropdown
		add_filter( 'bp_groups_get_dropdown',         array( $this, 'dropdown_show_without_group' ), 10, 2 );
		add_filter( 'bp_walker_dropdown_group_title', array( $this, 'dropdown_show_member_count'  ), 10, 5 );

		// Admin Bar
		add_action( 'admin_bar_menu',                 array( $this, 'admin_bar_menu'              ), 90    );
	}

	/** Query ********************************************************/

	/**
	 * Filter WP_User_Query to only return users in selected group
	 *
	 * Will run when 'bp_group_id' query arg is set in either get_users()
	 * or in $_REQUEST global. Additionally 'bp_group_hierarchy' query var
	 * can be set to include sub group users when using BP Group Hierarchy.
	 *
	 * Returns users that have no groups if 'bp_group_id' is set to -1.
	 *
	 * @since 0.0.1
	 *
	 * @uses BP_Groups_Hierarchy::has_children()
	 * 
	 * @param WP_User_Query $query
	 */
	public function user_group_query( $query ) {
		global $wpdb;

		// Setup local vars
		$qv = $query->query_vars;
		$bp = buddypress();

		// Bail if no group ID query var, direct or from $_REQUEST
		if (   ( ! isset( $qv['bp_group_id']       ) || empty( $qv['bp_group_id']       ) )
			&& ( ! isset( $_REQUEST['bp_group_id'] ) || empty( $_REQUEST['bp_group_id'] ) )
			)
			return;

		// $qv prevails over $_REQUEST
		$group_id = isset( $qv['bp_group_id'] ) ? (int) $qv['bp_group_id'] : (int) $_REQUEST['bp_group_id'];

		// Query users within designated group(s)
		if ( -1 != $group_id ) {

			// Setup array of group ids
			$group_ids = array( $group_id );

			// Add users from group hierarchy
			if ( $this->bp_group_hierarchy && ( 
					   ( isset( $qv['bp_group_hierarchy']       ) && $qv['bp_group_hierarchy']       )
					|| ( isset( $_REQUEST['bp_group_hierarchy'] ) && $_REQUEST['bp_group_hierarchy'] )
				) ) {

				// Walk hierarchy
				$hierarchy = new ArrayIterator( $group_ids );
				foreach ( $hierarchy as $gid ) {

					// Add child group ids when found
					if ( $children = BP_Groups_Hierarchy::has_children( $gid ) ) {
						foreach ( $children as $child_id )
							$hierarchy->append( (int) $child_id );
					}
				}

				// Set hierarchy group id collection
				$group_ids = $hierarchy->getArrayCopy();
			}

			// Append sql to query WHERE clause
			$query->query_where .= sprintf( " AND $wpdb->users.ID IN ( SELECT user_id FROM {$bp->groups->table_name_members} WHERE group_id IN (%s) )", implode( ',', array_unique( $group_ids ) ) );
		
		// Query users not in any group
		} else {

			// Append sql to query WHERE clause
			$query->query_where .= " AND $wpdb->users.ID NOT IN ( SELECT user_id FROM {$bp->groups->table_name_members} )";
		}
	}

	/** Management ***************************************************/

	/**
	 * Output the add users to group dropdown
	 *
	 * @since 0.0.1
	 *
	 * @uses bp_groups_dropdown()
	 */
	public function users_add_to_group() {

		// Bail if user can not moderate groups
		if ( ! current_user_can( 'bp_moderate' ) )
			return;

		/**
		 * BP pre-2.0 solution to override group query search params
		 * @see BP_Group_User_Management::groups_query_unblock_search()
		 */
		$this->unblock_search = true;

		// Setup dropdown args
		$args = array(
			'select_id' => 'join_group',
			'show_none' => __('Add to group&hellip;', 'bp-group-user-management')
		); ?>

		<label class="screen-reader-text" for="join_group"><?php _e( 'Add to group&hellip;', 'bp-group-user-management' ); ?></label>
		<?php bp_groups_dropdown( $args ); 
	}

	/**
	 * Output the remove users from group dropdown
	 *
	 * @since 0.0.1
	 *
	 * @uses bp_groups_dropdown()
	 */
	public function users_remove_from_group() {

		// Bail if user can not moderate groups
		if ( ! current_user_can( 'bp_moderate' ) )
			return;

		/**
		 * BP pre-2.0 solution to override group query search params
		 * @see BP_Group_User_Management::groups_query_unblock_search()
		 */
		$this->unblock_search = true;

		// Setup dropdown args
		$args = array(
			'select_id' => 'leave_group',
			'show_none' => __('Remove from group&hellip;', 'bp-group-user-management')
		); ?>

		<label class="screen-reader-text" for="leave_group"><?php _e( 'Remove from group&hellip;', 'bp-group-user-management' ); ?></label>
		<?php bp_groups_dropdown( $args ); 
	}

	/**
	 * Process bulk dropdown form submission from the WordPress Users
	 * Table
	 *
	 * The BP groups join and leave functions have their own internal checks.
	 * 
	 * @since 0.0.1
	 *
	 * @todo redirect to current page (search/paging)
	 * @todo send user feedback message
	 *
	 * @uses current_user_can()
	 * @uses call_user_func_array() To call 'groups_join_group' and 'groups_leave_group'
	 */
	public function users_group_bulk_change() {

		// Bail if user can not moderate groups
		if ( ! current_user_can( 'bp_moderate' ) )
			return;

		// Bail if no users specified
		if ( empty( $_REQUEST['users'] ) )
			return;

		// Fetch actions
		$actions = array_filter( array( 
			'join'  => (int) $_REQUEST['join_group'],
			'leave' => (int) $_REQUEST['leave_group']
		) );

		// Bail if this isn't one of our actions or selected groups are the same
		if ( empty( $actions ) || $actions != array_unique( $actions ) )
			return;

		// Run through user ids
		foreach ( (array) $_REQUEST['users'] as $user_id ) {
			$user_id = (int) $user_id;

			// Run trough actions
			foreach ( $actions as $action => $group_id ) {

				// Run action callback
				call_user_func_array( "groups_{$action}_group", array( $group_id, $user_id ) );
			}
		}
	}

	/**
	 * Output the filter users by group dropdown
	 *
	 * @since 0.0.1
	 *
	 * @uses submit_button()
	 * @uses bp_groups_dropdown()
	 */
	public function users_filter_by_group() {

		// Bail if user can not moderate/view(?) groups
		if ( ! current_user_can( 'bp_moderate' ) )
			return;

		/**
		 * BP pre-2.0 solution to override group query search params
		 * @see BP_Group_User_Management::groups_query_unblock_search()
		 */
		$this->unblock_search = true;

		// Setup dropdown args
		$args = array(
			'selected'           => isset( $_GET['bp_group_id'] ) ? $_GET['bp_group_id'] : false,
			'show_none'          => __('Filter by group&hellip;', 'bp-group-user-management'),
			'show_without_group' => true,
			'show_member_count'  => true
		); ?>

		<p class="bp-filter-by-group-box">

			<label class="screen-reader-text" for="bp-filter-by-group"><?php _e( 'Filter by group&hellip;', 'bp-group-user-management' ); ?></label>
			<?php bp_groups_dropdown( $args ); ?>

			<?php if ( $this->bp_group_hierarchy ) : ?>
			
			<span class="sticky-checkbox-right">
				<label class="screen-reader-text" for="bp-group-user-filter-hierarchy"><?php _e( 'Include hierarchy', 'bp-group-user-management' ) ?></label>
				<input id="bp-group-user-filter-hierarchy" name="bp_group_hierarchy" type="checkbox" value="1" <?php checked( isset( $_GET['bp_group_hierarchy'] ) && $_GET['bp_group_hierarchy'] ); ?> title="<?php esc_attr_e('Include hierarchy', 'bp-group-user-management'); ?>" />
			</span>
			
			<?php endif; ?>

			<?php submit_button( __('Filter', 'bp-group-user-management'), 'secondary', '', false, array( 'id' => 'changeit' ) ); ?>

		</p>

		<?php
	}

	/**
	 * Display a 'without-group' dropdown option, with or without custom text
	 *
	 * @since 0.0.1
	 * 
	 * @param string $dropdown HTML element
	 * @param array $args Dropdown arguments
	 * @return string HTML element
	 */
	public function dropdown_show_without_group( $dropdown, $args ) {

		// Display a second 'without-value' option, with or without custom text
		if ( ! isset( $args['show_without_group'] ) || empty( $args['show_without_group'] ) )
			return $dropdown;

		// Setup local vars and load dropdown
		$dom = new DomDocument();
		$dom->loadHTML( $dropdown );
		$pos = 0;

		// Setup walker title filter group argument
		$title  = esc_html__( 'Without group', 'bp-group-user-management' );
		$group  = array( 
			'id'           => -1, 
			'creator_id'   => 0,
			'name'         => $title,
			'slug'         => '',
			'description'  => '',
			'status'       => 'hidden',
			'enable_forum' => 0,
			'date_created' => null
		);
		if ( $this->bp_group_hierarchy )
			$group['parent_id'] = 0;

		// Create the 'without-value' option tag
		$without = $dom->createElement( 'option', apply_filters( 'bp_walker_dropdown_group_title', $title, '', (object) $group, 0, $args ) );
		$without->setAttribute( 'value', '-1' );
		$without->setAttribute( 'class', 'level-0' );

		// Position increments when 'no-value' option is present
		if ( ! empty( $args['show_none'] ) )
			$pos++;

		// Get the select element options
		$options = $dom->getElementsByTagName( 'option' );

		// Insert option in the DOM
		$options->item(0)->parentNode->insertBefore( $without, $options->item( $pos ) );

		// Save and return manipulations
		return $dom->saveHTML();
	}

	/**
	 * Display the member count per group in the dropdown
	 *
	 * @since 0.0.1
	 * 
	 * @param string $title Group dropdown title value
	 * @param string $output HTML select element
	 * @param object $group Current group object data
	 * @param int $depth Current hierarchy depth
	 * @param array $args Dropdown arguments
	 * @return string Group title
	 */
	public function dropdown_show_member_count( $title, $output, $group, $depth, $args ) {
		global $wpdb, $bp;

		// Bail if member count isn't requested
		if ( ! isset( $args['show_member_count'] ) || empty( $args['show_member_count'] ) || ! isset( $group->id ) )
			return $title;

		// Get the group member count
		if ( -1 != $group->id ) {
			$count = BP_Groups_Group::get_total_member_count( $group->id );

		// Users without group count
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->users WHERE $wpdb->users.ID NOT IN ( SELECT user_id FROM {$bp->groups->table_name_members} )" );
		}

		// Append count to the title
		if ( ! empty( $count ) )
			$title .= ' (' . $count . ')';

		return $title;
	}

	/**
	 * Add current group query args to the role view links
	 *
	 * @since 0.0.1
	 *
	 * @uses DomDocument
	 * 
	 * @param array $views Role views
	 * @return array Views
	 */
	public function users_filter_role_views( $views ) {

		// Only if there's a group id param
		if ( isset( $_GET['bp_group_id'] ) ) {

			// Setup local vars
			$dom    = new DomDocument();
			$params = array( 'bp_group_id' => $_GET['bp_group_id'] );

			// Add hierarchy param if set
			if ( isset( $_GET['bp_group_hierarchy'] ) )
				$params['bp_group_hierarchy'] = $_GET['bp_group_hierarchy'];

			// Run through the views
			foreach ( $views as $role => $link ) {

				// Load link
				$dom->loadHTML( $link );

				// Walk the a tags
				foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {

					// Replace href attribute
					$a->setAttribute('href', add_query_arg( $params, $a->getAttribute( 'href' ) ) );
				}

				// Save new link
				$views[$role] = $dom->saveHTML();
			}			
		}

		return $views;
	}

	/**
	 * Output custom styling
	 *
	 * @since 0.0.1
	 */
	public function users_print_styles() {
		?>
<style type="text/css">
	.bp-filter-by-group-box {
		display: inline-block;
		margin: 0;
	}

<?php if ( $this->bp_group_hierarchy ) : ?>
	#bp_group_id {
		margin-right: 0;
	}
<?php endif; ?>

	p select + .sticky-checkbox-right {
		float: left;
		background: #fff;
		border: 1px solid #ddd;
		border-left: 0;
		padding: 5px;
		margin: 1px 6px 1px 0;
	}

		p select:focus + .sticky-checkbox-right {
			border-color: #999;
		}

	p select + .sticky-checkbox-right input[type=checkbox] {
		float: left;
		margin: 0;
	}

	#changeit {
		margin-right: 16px;
	}

		#changeit:last-child {
			margin-right: 8px;
		}
</style>
		<?php
	}

	/**
	 * Add group column to user management panel
	 *
	 * @since 0.0.1
	 * 
	 * @uses current_user_can()
	 * 
	 * @param array $columns
	 * @return array $columns
	 */
	public function users_add_group_column( $columns ) {

		// Show group column if user is capable
		if ( current_user_can( 'bp_moderate' ) ) { // view?
			$columns['bp_groups'] = __('Groups', 'buddypress');			
		}

		return $columns;
	}

	/**
	 * Return group column content on user management panel
	 * 
	 * @since 0.0.1
	 * 
	 * @uses groups_total_groups_for_user()
	 * 
	 * @param string $content
	 * @param string $column Column ID
	 * @param int $user_id User ID
	 * @return string $content HTML output
	 */
	public function users_custom_group_column( $content, $column, $user_id ) {
		global $groups_template;

		// When in groups column
		if ( 'bp_groups' == $column ) {

			/**
			 * BP pre-2.0 solution to override group query search params
			 * @see BP_Group_User_Management::groups_query_unblock_search()
			 */
			$this->unblock_search = true;

			// User has groups
			if ( bp_has_groups( array( 'user_id' => $user_id ) ) ) : 

				while ( bp_groups() ) : bp_the_group();

					// Display group name wit link to edit group page
					$content .= '<a href="users.php?group_id=' . bp_get_group_id() . '" title="' . sprintf( esc_attr__('View all %d members of %s', 'bp-group-user-management'), bp_get_group_total_members(), bp_get_group_name() ) . '">' . bp_get_group_name() . '</a>';

					// This is not the last group
					if ( $groups_template->group_count - 1 != $groups_template->current_group ) 
						$content .= ', ';

				endwhile;

			// User has no groups
			else :
				$content = '&#8212;';
			endif;
		}

		return $content;
	}

	/** Group Queries ************************************************/

	/**
	 * Unset search query parameter when not searching for groups (BP pre-2.0)
	 * 
	 * @link https://buddypress.trac.wordpress.org/ticket/5456
	 *
	 * @since 0.0.1
	 * 
	 * @param  string $query
	 * @param  array $sql
	 * @param  array $args
	 * @return string Query
	 */
	public function groups_query_unblock_search( $query, $sql, $args ) {

		// Is unblock_search var true?
		if ( $this->unblock_search ) {

			// Empty search statement
			$sql['search'] = '';

			// Rebuild query
			$query = join( ' ', (array) $sql );

			// Reset unblock_search var
			$this->unblock_search = false;
		}

		return $query;
	}

	/** Admin Bar ****************************************************/

	/**
	 * Add new group link to the Create New admin bar menu
	 *
	 * @since 0.0.1
	 * 
	 * @param object $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {

		// Bail if user cannot manage groups
		if ( ! bp_user_can_create_groups() )
			return;

		// Set node href
		if ( $this->bp_group_organizer && current_user_can( 'bp_moderate' ) ) {
			$href = add_query_arg( 'page', 'group_organizer', admin_url( 'admin.php' ) );
		} else {
			$href = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/create' );
		}

		// New Group
		$wp_admin_bar->add_node( array(
			'parent' => 'new-content', // Add to new-content menu node
			'id'     => 'new-bp_group',
			'title'  => __('Group', 'buddypress'),
			'href'   => $href
		) );
	}
}

/**
 * Initialize the plugin
 *
 * @since 0.0.1
 *
 * @uses bp_is_active() To check if groups component is active
 */
function bp_group_user_management() {

	// Only if groups are active
	if ( bp_is_active( 'groups' ) ) {
		return BP_Group_User_Management::instance();
	}
}

// Fire on bp_include
add_action( 'bp_include', 'bp_group_user_management' );

endif;