<?php
/**
 * View Admin As - Groups plugin
 *
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package View_Admin_As
 */

if ( ! defined( 'VIEW_ADMIN_AS_DIR' ) ) {
	die();
}

add_action( 'vaa_view_admin_as_modules_loaded', array( 'VAA_View_Admin_As_Groups', 'get_instance' ) );

/**
 * Compatibility class for the Groups plugin
 *
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package View_Admin_As
 * @since   1.7.x
 * @version 1.7.x
 * @uses    VAA_View_Admin_As_Class_Base Extends class
 */
final class VAA_View_Admin_As_Groups extends VAA_View_Admin_As_Class_Base
{
	/**
	 * The single instance of the class.
	 *
	 * @since  1.7.x
	 * @static
	 * @var    VAA_View_Admin_As_Groups
	 */
	private static $_instance = null;

	/**
	 * The existing groups.
	 *
	 * @since  1.7.x
	 * @see    groups/lib/core/class-groups-group.php -> Groups_Groups
	 * @var    array of objects: Groups_Group
	 */
	private $groups;

	/**
	 * @since  1.7.x
	 * @see    groups/lib/core/class-groups-group.php -> Groups_Groups
	 * @var    Groups_Group
	 */
	private $selectedGroup;

	/**
	 * @since  1.7.x
	 * @var    string
	 */
	private $viewKey = 'groups';

	/**
	 * Populate the instance and validate Groups plugin.
	 *
	 * @since   1.7.x
	 * @access  protected
	 * @param   VAA_View_Admin_As  $vaa  The main VAA object.
	 */
	protected function __construct( $vaa ) {
		self::$_instance = $this;
		parent::__construct( $vaa );

		if ( is_callable( array( 'Groups_Group', 'get_groups' ) )
		  && defined( 'GROUPS_ADMINISTER_GROUPS' )
		  && current_user_can( GROUPS_ADMINISTER_GROUPS )
		  && ! is_network_admin()
		) {

			$this->vaa->register_module( array(
				'id'       => $this->viewKey,
				'instance' => self::$_instance,
			) );

			$this->store_groups();

			add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu' ), 40, 2 );
			add_filter( 'view_admin_as_view_types', array( $this, 'add_view_type' ) );

			add_filter( 'view_admin_as_validate_view_data_' . $this->viewKey, array( $this, 'validate_view_data' ), 10, 2 );
			add_filter( 'view_admin_as_update_view_' . $this->viewKey, array( $this, 'update_view' ), 10, 3 );
		}

		add_action( 'vaa_view_admin_as_do_view', array( $this, 'do_view' ) );
	}

	/**
	 * Initialize the Groups module.
	 * @since   1.7.x
	 * @access  public
	 */
	public function do_view() {

		if ( $this->get_groups( $this->store->get_view( $this->viewKey ) ) ) {

			$this->selectedGroup = new Groups_Group( $this->store->get_view( $this->viewKey ) );

			add_filter( 'vaa_admin_bar_viewing_as_title', array( $this, 'vaa_viewing_as_title' ) );

			$this->vaa->view()->init_user_modifications();
			add_action( 'vaa_view_admin_as_modify_user', array( $this, 'modify_user' ), 10, 2 );

			// Filter user-group relationships.
			//add_filter( 'groups_user_is_member', array( $this, 'groups_user_is_member' ), 20, 3 );

			// @see Groups_User::init_cache()
			/*Groups_Cache::set(
				Groups_User::GROUP_IDS . $this->store->get_curUser()->ID,
				array( $this->store->get_view( $this->viewKey ) ),
				Groups_User::CACHE_GROUP
			);*/

			/**
			 * Filters
			 *
			 * - groups_post_access_user_can_read_post
			 *     class-groups-post-access -> line 419
			 */
		}

		// Filter group capabilities.
		if ( VAA_API::is_user_modified() ) {
			add_filter( 'groups_group_can', array( $this, 'groups_group_can' ), 20, 3 );
			add_filter( 'groups_user_can', array( $this, 'groups_user_can' ), 20, 3 );
		}
	}

	/**
	 * Update the current user's WP_User instance with the current view data.
	 *
	 * @since   1.7.x
	 * @param   WP_User  $user        User object.
	 * @param   bool     $accessible  Are the WP_User properties accessible?
	 */
	public function modify_user( $user, $accessible ) {

		$caps = array();
		if ( $this->selectedGroup ) {
			$group_caps = (array) $this->selectedGroup->capabilities_deep;
			foreach ( $group_caps as $group_cap ) {
				if ( isset( $group_cap->capability->capability ) ) {
					$caps[ $group_cap->capability->capability ] = 1;
				}
			}
		}

		$caps = array_merge( $this->store->get_selectedCaps(), $caps );

		$this->store->set_selectedCaps( $caps );

		if ( $accessible ) {
			// Merge the caps with the current user caps, overwrite existing.
			$user->allcaps = array_merge( $user->caps, $caps );
		}
	}

	/**
	 * Filter the user-group relation.
	 *
	 * @see  groups/lib/core/class-groups-user-group.php -> Groups_User_Group->read()
	 *
	 * @since   1.7.x
	 * @access  public
	 * @param   bool  $result    Current result.
	 * @param   int   $user_id   User ID.
	 * @param   int   $group_id  Group ID.
	 * @return  bool|object
	 */
	/*public function groups_user_is_member( $result, $user_id, $group_id ) {
		if ( (int) $user_id === (int) $this->store->get_curUser()->ID
		     && $this->selectedGroup
		     && (int) $group_id === (int) $this->selectedGroup->group->group_id
		) {
			$result = $this->selectedGroup->group;
		}
		return $result;
	}*/

	/**
	 * Filter for the current view.
	 * Only use this function if the current view is a validated group object!
	 *
	 * @see  Groups_Group::can() >> groups/lib/core/class-groups-group.php
	 *
	 * @since   1.7.x
	 * @access  public
	 * @param   bool          $result  Current result.
	 * @param   Groups_Group  $object  (not used) Group object.
	 * @param   string        $cap     Capability.
	 * @return  bool
	 */
	public function groups_user_can( $result, $object = null, $cap = '' ) {
		// Fallback PHP < 5.4 due to apply_filters_ref_array
		// See https://codex.wordpress.org/Function_Reference/apply_filters_ref_array
		if ( is_array( $result ) ) {
			$cap = $result[2];
			//$object = $result[1];
			$result = $result[0];
		}

		if ( $this->selectedGroup &&
		     is_callable( array( $this->selectedGroup, 'can' ) ) &&
		     ! $this->selectedGroup->can( $cap )
		) {
			$result = false;
		} else {
			// For other view types.
			$result = VAA_API::current_view_can( $cap );
		}
		return $result;
	}

	/**
	 * Add view type.
	 *
	 * @since   1.7.x
	 * @param   array  $types  Existing view types.
	 * @return  array
	 */
	public function add_view_type( $types ) {
		$types[] = $this->viewKey;
		return $types;
	}

	/**
	 * Validate data for this view type
	 *
	 * @since   1.7.x
	 * @param   null   $null  Default return (invalid)
	 * @param   mixed  $data  The view data
	 * @return  mixed
	 */
	public function validate_view_data( $null, $data ) {
		if ( is_numeric( $data ) && $this->get_groups( (int) $data ) ) {
			return $data;
		}
		return $null;
	}

	/**
	 * View update handler (Ajax probably), called from main handler.
	 *
	 * @since   1.7.x
	 * @access  public
	 * @param   null    $null    Null.
	 * @param   array   $data    The ajax data for this module.
	 * @param   string  $type    The view type.
	 * @return  bool
	 */
	public function update_view( $null, $data, $type ) {

		if ( ! $this->is_valid_ajax() || $type !== $this->viewKey ) {
			return $null;
		}

		if ( is_numeric( $data ) && $this->get_groups( (int) $data ) ) {
			$this->store->set_view( (int) $data, $this->viewKey, true );
			return true;
		}
		return false;
	}

	/**
	 * Change the VAA admin bar menu title.
	 *
	 * @since   1.7.x
	 * @access  public
	 * @param   string  $title  The current title.
	 * @return  string
	 */
	public function vaa_viewing_as_title( $title ) {
		if ( $this->get_groups( $this->store->get_view( $this->viewKey ) ) ) {
			// @codingStandardsIgnoreLine >> Use translate() to prevent groups translation from getting parsed by translate.wordpress.org
			$title = sprintf( __( 'Viewing as %s', VIEW_ADMIN_AS_DOMAIN ), translate( 'Group', GROUPS_PLUGIN_DOMAIN ) ) . ': '
			         . $this->get_groups( $this->store->get_view( $this->viewKey ) )->name;
		}
		return $title;
	}

	/**
	 * Add the Groups admin bar items.
	 *
	 * @since   1.7.x
	 * @access  public
	 * @param   WP_Admin_Bar  $admin_bar  The toolbar object.
	 * @param   string        $root       The root item.
	 */
	public function admin_bar_menu( $admin_bar, $root ) {

		if ( ! $this->get_groups() || ! count( $this->get_groups() ) ) {
			return;
		}

		$admin_bar->add_group( array(
			'id'        => $root . '-groups',
			'parent'    => $root,
			'meta'      => array(
				'class'     => 'ab-sub-secondary',
			),
		) );

		$root = $root . '-groups';

		$admin_bar->add_node( array(
			'id'        => $root . '-title',
			'parent'    => $root,
			'title'     => VAA_View_Admin_As_Form::do_icon( 'dashicons-image-filter dashicons-itthinx-groups' )
			               // @codingStandardsIgnoreLine >> Use translate() to prevent groups translation from getting parsed by translate.wordpress.org
			               . translate( 'Groups', GROUPS_PLUGIN_DOMAIN ),
			'href'      => false,
			'meta'      => array(
				'class'    => 'vaa-has-icon ab-vaa-title ab-vaa-toggle active',
				'tabindex' => '0',
			),
		) );

		/**
		 * Add items at the beginning of the groups group.
		 *
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 * @param   WP_Admin_Bar  $admin_bar   The toolbar object.
		 * @param   string        $root        The current root item.
		 */
		do_action( 'vaa_admin_bar_groups_before', $admin_bar, $root );

		// Add the groups.
		foreach ( $this->get_groups() as $group_key => $group ) {
			$view_value = $group->name;
			$view_data = array( $this->viewKey => $view_value );
			$href = VAA_API::get_vaa_action_link( $view_data, $this->store->get_nonce( true ) );
			$class = 'vaa-' . $this->viewKey . '-item';
			$title = VAA_View_Admin_As_Form::do_view_title( $group->name, $this->viewKey, $view_value );
			// Check if this group is the current view.
			if ( $this->store->get_view( $this->viewKey ) ) {
				if ( (int) $this->store->get_view( $this->viewKey ) === (int) $group->group_id ) {
					$class .= ' current';
					$href = false;
				}
				elseif ( $current_parent = $this->get_groups( $this->store->get_view( $this->viewKey ) ) ) {
					if ( (int) $current_parent->parent_id === (int) $group->group_id ) {
						$class .= ' current-parent';
					}
				}
			}
			$parent = $root;
			if ( ! empty( $group->parent_id ) ) {
				$parent = $root . '-' . $this->viewKey . '-' . (int) $group->parent_id;
			}
			$admin_bar->add_node( array(
				'id'        => esc_attr( $root . '-' . $this->viewKey . '-' . (int) $group->group_id ),
				'parent'    => $parent,
				'title'     => $title,
				'href'      => $href,
				'meta'      => array(
					'title'     => sprintf( esc_attr__( 'View as %s', VIEW_ADMIN_AS_DOMAIN ), $view_value ),
					'class'     => $class,
					'rel'       => $group->group_id,
				),
			) );
		}

		/**
		 * Add items at the end of the groups group.
		 *
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 * @param   WP_Admin_Bar  $admin_bar   The toolbar object.
		 * @param   string        $root        The current root item.
		 */
		do_action( 'vaa_admin_bar_groups_after', $admin_bar, $root );
	}

	/**
	 * Store the available groups.
	 * @since   1.7.x
	 * @access  private
	 */
	private function store_groups() {
		$groups = Groups_Group::get_groups();

		if ( ! empty( $groups ) ) {
			foreach ( $groups as $group ) {
				$this->groups[ $group->group_id ] = $group;
			}
		}
	}

	/**
	 * Get a group by ID.
	 *
	 * @since   1.7.x
	 * @access  public
	 * @param   string  $key  The group key.
	 * @return  mixed
	 */
	public function get_groups( $key = '-1' ) {
		if ( ! is_numeric( $key ) ) {
			return false;
		}
		if ( '-1' === $key ) {
			$key = null;
		}
		return VAA_API::get_array_data( $this->groups, $key );
	}

	/**
	 * Main Instance.
	 *
	 * Ensures only one instance of this class is loaded or can be loaded.
	 *
	 * @since   1.7.x
	 * @access  public
	 * @static
	 * @param   VAA_View_Admin_As  $caller  The referrer class.
	 * @return  VAA_View_Admin_As_Groups
	 */
	public static function get_instance( $caller = null ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $caller );
		}
		return self::$_instance;
	}

} // end class.
