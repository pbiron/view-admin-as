<?php
/**
 * View Admin As - Admin Bar UI
 *
 * Admin Bar UI for View Admin As
 *
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package view-admin-as
 * @since   1.5
 * @version 1.6.2
 */

! defined( 'VIEW_ADMIN_AS_DIR' ) and die( 'You shall not pass!' );

final class VAA_View_Admin_As_Admin_Bar extends VAA_View_Admin_As_Class_Base
{
	/**
	 * The single instance of the class.
	 *
	 * @since  1.5
	 * @static
	 * @var    VAA_View_Admin_As_Admin_Bar
	 */
	private static $_instance = null;

	/**
	 * Admin bar root item ID
	 *
	 * @since  1.6.1
	 * @static
	 * @var    string
	 */
	public static $root = 'vaa';

	/**
	 * Group the users under their roles?
	 *
	 * @since  1.5
	 * @var    bool
	 */
	private $groupUserRoles = false;

	/**
	 * Enable search bar for users?
	 *
	 * @since  1.5
	 * @var    bool
	 */
	private $searchUsers = false;

	/**
	 * Construct function
	 * Protected to make sure it isn't declared elsewhere
	 *
	 * @since   1.5
	 * @since   1.6.1  $vaa param
	 * @access  protected
	 * @param   VAA_View_Admin_As  $vaa
	 */
	protected function __construct( $vaa ) {
		self::$_instance = $this;
		parent::__construct( $vaa );

		if ( $this->is_vaa_enabled() ) {
			add_action( 'vaa_view_admin_as_init', array( $this, 'vaa_init' ) );
		}
	}

	/**
	 * init function to store data from the main class and enable functionality based on the current view
	 *
	 * @since   1.5
	 * @access  public
	 * @see     'vaa_view_admin_as_init' action
	 * @return  void
	 */
	public function vaa_init() {

		// If the amount of items (roles and users combined) is more than 15 users, group them under their roles
		if ( "yes" == $this->get_userSettings('force_group_users')
			 || 15 < ( count( $this->get_users() ) + count( $this->get_roles() ) ) ) {
			$this->groupUserRoles = true;
			$this->searchUsers = true;
		}

		// There are no roles to group users on network pages
		if ( is_network_admin() ) {
			$this->groupUserRoles = false;
		}

		// Add the default nodes to the WP admin bar
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ) );
		add_action( 'vaa_toolbar_menu', array( $this, 'admin_bar_menu' ), 10, 2 );

		// Add the global nodes to the admin bar
		add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu_info' ), 1 );
		add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu_settings' ), 2 );

		// Add the caps nodes to the admin bar
		add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu_caps' ), 10 );

		if ( ! is_network_admin() ) {

			// Add the roles nodes to the admin bar
			// Roles are not used on network pages
			add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu_roles' ), 20 );

			// Add the visitor view nodes under roles
			// There are no outside visitors on network pages
			add_action( 'vaa_admin_bar_roles_after', array( $this, 'admin_bar_menu_visitor' ), 10, 2 );
			// Fallback action for when there are no roles available
			add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu_visitor' ), 31 );
		}

		// Add the users nodes to the admin bar
		add_action( 'vaa_admin_bar_menu', array( $this, 'admin_bar_menu_users' ), 30 );
	}

	/**
	 * Add admin bar menu items
	 *
	 * @since   1.5
	 * @access  public
	 * @see     'admin_bar_menu' action
	 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
	 * @param   object  $admin_bar
	 * @param   string  $root
	 * @return  void
	 */
	public function admin_bar_menu( $admin_bar, $root = '' ) {

		$icon = 'dashicons-hidden';
		$title = __( 'Default view (Off)', VIEW_ADMIN_AS_DOMAIN );

		if ( $this->get_viewAs() ) {
			$icon = 'dashicons-visibility';
		}

		if ( $this->get_viewAs('caps') ) {
			$title = __( 'Modified view', VIEW_ADMIN_AS_DOMAIN );
		}
		if ( $this->get_viewAs('role') ) {
			$title = __( 'Viewing as role', VIEW_ADMIN_AS_DOMAIN ) . ': '
			         . translate_user_role( $this->get_roles( $this->get_viewAs('role') )->name );
		}
		if ( $this->get_viewAs('user') ) {
			$selected_user_roles = array();
			foreach ( $this->get_selectedUser()->roles as $role ) {
				$selected_user_roles[] = translate_user_role( $this->get_roles( $role )->name );
			}
			$title = __( 'Viewing as user', VIEW_ADMIN_AS_DOMAIN ) . ': '
			         . $this->get_selectedUser()->data->display_name
			         . ' <span class="user-role">(' . implode( ', ', $selected_user_roles ) . ')</span>';
		}
		if ( $this->get_viewAs('visitor') ) {
			$title = __( 'Viewing as site visitor', VIEW_ADMIN_AS_DOMAIN );
		}

		/**
		 * Filter the text to show when a view is applied
		 *
		 * @since  1.6
		 * @param  string      $title
		 * @param  bool|array  The view
		 * @return string
		 */
		$title = apply_filters( 'vaa_admin_bar_viewing_as_title', $title, $this->get_viewAs() );

		if ( empty( $root ) ) {
			$root = 'top-secondary';
			if ( $this->get_userSettings('admin_menu_location')
			     && in_array( $this->get_userSettings('admin_menu_location'), $this->get_allowedUserSettings('admin_menu_location') )
			) {
				$root = $this->get_userSettings('admin_menu_location');
			}
		}

		// Add menu item
		$admin_bar->add_node( array(
			'id'     => self::$root,
			'parent' => $root,
			'title'  => '<span class="ab-label">' . $title . '</span><span class="ab-icon alignright dashicons ' . $icon . '"></span>',
			'href'   => false,
			'meta'   => array(
				'title'    => __( 'View Admin As', VIEW_ADMIN_AS_DOMAIN ),
				'tabindex' => '0'
			),
		) );

		/**
		 * Add items as first
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_menu_before', $admin_bar, self::$root );

		// Add reset button
		if ( $this->get_viewAs() ) {
			$rel = 'reset';
			$name = 'reset-view';
			if ( $this->get_userSettings('view_mode') == 'single' ) {
				$rel = 'reload';
				$name = 'reload';
			}
			$admin_bar->add_node( array(
				'id'     => self::$root . '-reset',
				'parent' => self::$root,
				'title'  => self::do_button( array(
					'name'    => self::$root . '-' . $name,
					'label'   => __( 'Reset to default', VIEW_ADMIN_AS_DOMAIN ),
					'classes' => 'button-secondary'
				) ),
				'href'   => false,
				'meta'   => array(
					'title' => esc_attr__( 'Reset to default', VIEW_ADMIN_AS_DOMAIN ),
					'class' => 'vaa-reset-item vaa-button-container',
					'rel'   => $rel
				),
			) );
		}

		/**
		 * Add items
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_menu', $admin_bar, self::$root );

	}

	/**
	 * Add admin bar menu info items
	 *
	 * @since   1.6
	 * @access  public
	 * @see     'vaa_admin_bar_menu' action
	 * @param   object  $admin_bar
	 * @return  void
	 */
	public function admin_bar_menu_info( $admin_bar ) {

		$root = self::$root . '-info';

		$admin_bar->add_node( array(
			'id'     => $root,
			'parent' => self::$root,
			'title'  => self::do_icon( 'dashicons-info' ) . __( 'Info', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => false,
			'meta'   => array(
				'class'    => 'vaa-has-icon',
				'tabindex' => '0'
			),
		) );

		$admin_bar->add_group( array(
			'id'     => $root . '-about',
			'parent' => $root,
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			)
		) );

		$admin_bar->add_node(
			array(
				'parent' => $root . '-about',
				'id'     => $root . '-about-version',
				'title'  => __( 'Version', VIEW_ADMIN_AS_DOMAIN ) . ': ' . VIEW_ADMIN_AS_VERSION,
				'href'   => false,
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => $root . '-about',
				'id'     => $root . '-about-author',
				'title'  => 'Keraweb • Jory Hogeveen',
				'href'   => 'https://profiles.wordpress.org/keraweb/',
				'meta'   => array(
					'target' => '_blank'
				)
			)
		);

		/**
		 * Add items at the beginning of the info group
		 * @since   1.6
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_info_before', $admin_bar, $root, self::$root );

		// Add the general admin links
		if ( is_callable( array( $this->vaa->get_ui('admin'), 'get_links' ) ) ) {
			$info_links = $this->vaa->get_ui('admin')->get_links();

			foreach ( $info_links as $id => $link ) {
				$admin_bar->add_node( array(
					'parent' => $root,
					'id'     => $root . '-' . $id,
					'title'  => self::do_icon( $link['icon'] ) . $link['description'],
					'href'   => esc_url( $link['url'] ),
					'meta'   => array(
						'class'  => 'auto-height vaa-has-icon',
						'target' => '_blank'
					),
				) );
			}
		}

		/**
		 * Add items at the end of the info group
		 * @since   1.6
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_info_after', $admin_bar, $root, self::$root );

	}

	/**
	 * Add admin bar menu settings items
	 *
	 * @since   1.5
	 * @access  public
	 * @see     'vaa_admin_bar_menu' action
	 * @param   object  $admin_bar
	 * @return  void
	 */
	public function admin_bar_menu_settings( $admin_bar ) {

		$root = self::$root . '-settings';

		$admin_bar->add_node( array(
			'id'     => $root,
			'parent' => self::$root,
			'title'  => self::do_icon( 'dashicons-admin-settings' ) . __( 'Settings', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => false,
			'meta'   => array(
				'class'    => 'vaa-has-icon',
				'tabindex' => '0'
			),
		) );

		/**
		 * Add items at the beginning of the settings group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_settings_before', $admin_bar, $root, self::$root );

		$admin_bar->add_node( array(
			'id'     => $root . '-admin-menu-location',
			'parent' => $root,
			'title'  => self::do_select( array(
				'name'        => $root . '-admin-menu-location',
				'value'       => $this->get_userSettings('admin_menu_location'),
				'label'       => __( 'Location', VIEW_ADMIN_AS_DOMAIN ) . ': &nbsp; ',
				'description' => __( 'Change the location of this menu node', VIEW_ADMIN_AS_DOMAIN ),
				'values'      => array(
					array(
						'compare' => 'top-secondary',
						'label' => __( 'Default', VIEW_ADMIN_AS_DOMAIN )
					),
					array(
						'compare' => 'my-account',
						'label' => __( 'My account', VIEW_ADMIN_AS_DOMAIN )
					)
				)
			) ),
			'href'   => false,
			'meta'   => array(
				'class' => 'auto-height',
			),
		) );

		$admin_bar->add_node( array(
			'id'     => $root . '-view-mode',
			'parent' => $root,
			'title'  => self::do_radio( array(
				'name'     => $root . '-view-mode',
				'value'    => $this->get_userSettings('view_mode'),
				'values'   => array(
					array(
						'compare'     => 'browse',
						'label'       => __( 'Browse mode', VIEW_ADMIN_AS_DOMAIN ),
						'description' => __( 'Store view and use WordPress with this view', VIEW_ADMIN_AS_DOMAIN )
					),
					array(
						'compare'     => 'single',
						'label'       => __( 'Single switch mode', VIEW_ADMIN_AS_DOMAIN ),
						'description' => __( 'Choose view on every pageload. This setting doesn\'t store views', VIEW_ADMIN_AS_DOMAIN )
					)
				)
			) ),
			'href'   => false,
			'meta'   => array(
				'class' => 'auto-height',
			),
		) );

		$admin_bar->add_node( array(
			'id'     => $root . '-hide-front',
			'parent' => $root,
			'title'  => self::do_checkbox( array(
				'name'        => $root . '-hide-front',
				'value'       => $this->get_userSettings('hide_front'),
				'compare'     => 'yes',
				'label'       => __( 'Hide on frontend', VIEW_ADMIN_AS_DOMAIN ),
				'description' => __( 'Hide on frontend when no view is selected and the admin bar is not shown', VIEW_ADMIN_AS_DOMAIN )
			) ),
			'href'   => false,
			'meta'   => array(
				'class' => 'auto-height',
			),
		) );

		/**
		 * Force own locale on view, WP 4.7+ only
		 * @see     https://github.com/JoryHogeveen/view-admin-as/issues/21
		 * @since   1.6.1
		 */
		if ( function_exists( 'get_user_locale' ) && function_exists( 'switch_to_locale' ) ) {
			$admin_bar->add_node( array(
				'id'     => $root . '-freeze-locale',
				'parent' => $root,
				'title'  => self::do_checkbox( array(
					'name'        => $root . '-freeze-locale',
					'value'       => $this->get_userSettings('freeze_locale'),
					'compare'     => 'yes',
					'label'       => __( 'Freeze locale', VIEW_ADMIN_AS_DOMAIN ),
					'description' => __( 'Force your own locale setting to the current view', VIEW_ADMIN_AS_DOMAIN )
				) ),
				'href'   => false,
				'meta'   => array(
					'class' => 'auto-height',
				),
			) );
		}

		/**
		 * force_group_users setting
		 * @since   1.5.2
		 */
		if ( true !== $this->groupUserRoles || 15 >= ( count( $this->get_users() ) + count( $this->get_roles() ) ) ) {
			$admin_bar->add_node( array(
				'id'     => $root . '-force-group-users',
				'parent' => $root,
				'title'  => self::do_checkbox( array(
					'name'        => $root . '-force-group-users',
					'value'       => $this->get_userSettings('force_group_users'),
					'compare'     => 'yes',
					'label'       => __( 'Group users', VIEW_ADMIN_AS_DOMAIN ),
					'description' => __( 'Group users under their assigned roles', VIEW_ADMIN_AS_DOMAIN )
				) ),
				'href'   => false,
				'meta'   => array(
					'class'    => 'auto-height',
					'tabindex' => '0'
				),
			) );
		}

		/**
		 * Add items at the end of the settings group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_settings_after', $admin_bar, $root, self::$root );
	}

	/**
	 * Add admin bar menu caps items
	 *
	 * @since   1.5
	 * @access  public
	 * @see     'vaa_admin_bar_menu' action
	 * @param   object  $admin_bar
	 * @return  void
	 */
	public function admin_bar_menu_caps( $admin_bar ) {
		static $done;
		if ( $done ) return;

		/**
		 * Make sure we have the latest added capabilities.
		 * I can be that a plugin/theme adds a capability after the initial call to store_caps (hook: 'plugins_loaded').
		 * @see    VAA_View_Admin_As->run()
		 * @since  1.4.1
		 */
		$this->store->store_caps();

		if ( ! $this->get_caps() || ! count( $this->get_caps() ) ) {
			return;
		}

		$root = self::$root . '-caps';

		$admin_bar->add_group( array(
			'id'     => $root,
			'parent' => self::$root,
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		) );
		$admin_bar->add_node( array(
			'id'     => $root . '-title',
			'parent' => $root,
			'title'  => self::do_icon( 'dashicons-admin-generic' ) . __( 'Capabilities', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => false,
			'meta'   => array(
				'class'    => 'vaa-has-icon ab-vaa-title ab-vaa-toggle active',
				'tabindex' => '0'
			),
		) );

		/**
		 * Add items at the beginning of the caps group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_caps_before', $admin_bar, $root, self::$root );

		$caps_quickselect_class = '';
		if ( $this->get_viewAs('caps') ) {
			$caps_quickselect_class .= ' current';
		}
		$admin_bar->add_node( array(
			'id'     => $root . '-quickselect',
			'parent' => $root,
			'title'  => __( 'Select', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => false,
			'meta'   => array(
				'class'    => $caps_quickselect_class,
				'tabindex' => '0'
			),
		) );

		// Capabilities submenu
		$admin_bar->add_node( array(
			'id'     => $root . '-applycaps',
			'parent' => $root . '-quickselect',
			'title'  => self::do_button( array(
					'name'    => 'apply-caps-view',
					'label'   => __( 'Apply', VIEW_ADMIN_AS_DOMAIN ),
					'classes' => 'button-primary'
				) ) . self::do_button( array(
					'name'    => 'close-caps-popup',
					'label'   => self::do_icon( 'dashicons-editor-contract' ),
					'classes' => 'button-secondary vaa-icon vaa-hide-responsive',
					'element' => 'a'
				) ) . self::do_button( array(
					'name'    => 'open-caps-popup',
					'label'   => self::do_icon( 'dashicons-editor-expand' ),
					'classes' => 'button-secondary vaa-icon vaa-hide-responsive',
					'element' => 'a'
				)
			),
			'href'   => false,
			'meta'   => array(
				'class' => 'vaa-button-container',
			),
		) );

		$admin_bar->add_node( array(
			'id'     => $root . '-filtercaps',
			'parent' => $root . '-quickselect',
			'title'  => self::do_input( array(
				'name'        => $root . '-filtercaps',
				'placeholder' => esc_attr__( 'Filter', VIEW_ADMIN_AS_DOMAIN )
			) ),
			'href'   => false,
			'meta'   => array(
				'class' => 'ab-vaa-filter filter-caps vaa-column-one-half vaa-column-first',
			),
		) );

		$role_select_options = array(
			array(
				'value' => 'default',
				'label' => __( 'Default', VIEW_ADMIN_AS_DOMAIN )
			)
		);
		if ( $this->store->get_viewAs() ) {
			$role_select_options[] = array(
				'compare' => 'vaa',
				'label'   => '= ' . __( 'Current view', VIEW_ADMIN_AS_DOMAIN ),
				'attr'    => array(
					'data-caps' => json_encode( $this->store->get_selectedCaps() ),
				)
			);
			$role_select_options[] = array(
				'compare' => 'reversed-vaa',
				'label'   => '≠ ' . __( 'Current view', VIEW_ADMIN_AS_DOMAIN ),
				'attr'    => array(
					'data-caps'    => json_encode( $this->store->get_selectedCaps() ),
					'data-reverse' => '1'
				)
			);
		}
		foreach ( $this->get_roles() as $role_key => $role ) {
			$role_select_options[] = array(
				'compare' => esc_attr( $role_key ),
				'label'   => '= ' . translate_user_role( $role->name ),
				'attr'    => array(
					'data-caps' => json_encode( $role->capabilities ),
				)
			);
			$role_select_options[] = array(
				'compare' => 'reversed-' . esc_attr( $role_key ),
				'label'   => '≠ ' . translate_user_role( $role->name ),
				'attr'    => array(
					'data-caps'    => json_encode( $role->capabilities ),
					'data-reverse' => '1'
				)
			);
		}
		$admin_bar->add_node( array(
			'id'     => $root . '-selectrolecaps',
			'parent' => $root . '-quickselect',
			'title'  => self::do_select( array(
				'name'   => $root . '-selectrolecaps',
				'values' => $role_select_options
			) ),
			'href'   => false,
			'meta'   => array(
				'class' => 'ab-vaa-select select-role-caps vaa-column-one-half vaa-column-last',
				'html'  => '',
			),
		) );

		$admin_bar->add_node( array(
			'id'     => $root . '-bulkselectcaps',
			'parent' => $root . '-quickselect',
			'title'  => self::do_button( array(
				'name'    => 'select-all-caps',
				'label'   => __( 'Select', VIEW_ADMIN_AS_DOMAIN ),
				'classes' => 'button-secondary'
			) ) . ' ' . self::do_button( array(
				'name'    => 'deselect-all-caps',
				'label'   => __( 'Deselect', VIEW_ADMIN_AS_DOMAIN ),
				'classes' => 'button-secondary'
			) ),
			'href'   => false,
			'meta'   => array(
				'class' => 'vaa-button-container vaa-clear-float',
			),
		) );

		$caps_quickselect_content = '';
		foreach ( $this->get_caps() as $cap_name => $cap_val ) {
			$class = 'vaa-cap-item';
			$checked = false;
			// check if we've selected a capability view and we've changed some capabilities
			$selected_caps = $this->get_viewAs('caps');
			if ( isset( $selected_caps[ $cap_name ] ) ) {
				if ( 1 == $selected_caps[ $cap_name ] ) {
					$checked = true;
				}
			} elseif ( 1 == $cap_val ) {
				$checked = true;
			}
			// Check for this capability in any view set
			if ( $this->vaa->view()->current_view_can( $cap_name ) ) {
				$class .= ' current';
			}
			// The list of capabilities
			$caps_quickselect_content .=
				'<div class="ab-item '.$class.'">'
					. self::do_checkbox( array(
						'name'           => 'vaa_cap_' . esc_attr( $cap_name ),
						'value'          => $checked,
						'compare'        => true,
						'checkbox_value' => esc_attr( $cap_name ),
						'label'          => $cap_name
					) )
				. '</div>';
		}
		$admin_bar->add_node( array(
			'id'     => $root . '-quickselect-options',
			'parent' => $root . '-quickselect',
			'title'  => $caps_quickselect_content,
			'href'   => false,
			'meta'   => array(
				'class' => 'ab-vaa-multipleselect auto-height',
			),
		) );

		/**
		 * Add items at the end of the caps group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_caps_after', $admin_bar, $root, self::$root );

		$done = true;
	}

	/**
	 * Add admin bar menu roles items
	 *
	 * @since   1.5
	 * @access  public
	 * @see     'vaa_admin_bar_menu' action
	 * @param   object  $admin_bar
	 * @return  void
	 */
	public function admin_bar_menu_roles( $admin_bar ) {
		static $done;
		if ( $done ) return;

		/**
		 * Make sure we have the latest added roles.
		 * I can be that a plugin/theme adds a role after the initial call to store_roles (hook: 'plugins_loaded').
		 * @see    VAA_View_Admin_As->run()
		 * @since  1.6.x
		 */
		$this->store->store_roles();

		if ( ! $this->get_roles() || ! count( $this->get_roles() ) ) {
			return;
		}

		$root = self::$root . '-roles';

		$admin_bar->add_group( array(
			'id'     => $root,
			'parent' => self::$root,
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		) );
		$admin_bar->add_node( array(
			'id'     => $root . '-title',
			'parent' => $root,
			'title'  => self::do_icon( 'dashicons-groups' ) . __( 'Roles', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => false,
			'meta'   => array(
				'class'    => 'vaa-has-icon ab-vaa-title ab-vaa-toggle active',
				'tabindex' => '0'
			),
		) );

		/**
		 * Add items at the beginning of the roles group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_roles_before', $admin_bar, self::$root );

		// Add the roles
		foreach ( $this->get_roles() as $role_key => $role ) {
			$parent = $root;
			$href   = '#';
			$class  = 'vaa-role-item';
			$title  = translate_user_role( $role->name );
			// Check if the users need to be grouped under their roles
			if ( true === $this->groupUserRoles ) {
				$class .= ' vaa-menupop'; // make sure items are aligned properly when some roles don't have users
				// Check if the current view is a user with this role
				if ( $this->get_viewAs('user') && $this->get_selectedUser() && in_array( $role_key, $this->get_selectedUser()->roles ) ) {
					$class .= ' current-parent';
				}
				// If there are users with this role, add a counter
				$user_count = 0;
				foreach ( $this->get_users() as $user ) {
					if ( in_array( $role_key, $user->roles ) ) {
						$user_count++;
					}
				}
				if ( 0 < $user_count ) {
					$title = $title . ' <span class="user-count">(' . $user_count . ')</span>';
				}
			}
			// Check if this role is the current view
			if ( $this->get_viewAs('role') && $this->get_viewAs('role') == strtolower( $role->name ) ) {
				$class .= ' current';
				if ( 1 === count( $this->get_viewAs() ) ) {
					$href = false;
				}
			}
			$admin_bar->add_node( array(
				'id'     => $root . '-role-' . $role_key,
				'parent' => $parent,
				'title'  => $title,
				'href'   => $href,
				'meta'   => array(
					// Translators: %s stands for the translated role name
					'title' => sprintf( esc_attr__( 'View as %s', VIEW_ADMIN_AS_DOMAIN ), translate_user_role( $role->name ) ),
					'class' => $class,
					'rel'   => $role_key
				),
			) );
		}

		/**
		 * Add items at the end of the roles group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_roles_after', $admin_bar, $root, self::$root );

		$done = true;
	}

	/**
	 * Add admin bar menu users items
	 *
	 * @since   1.5
	 * @access  public
	 * @see     'vaa_admin_bar_menu' action
	 * @param   object  $admin_bar
	 * @return  void
	 */
	public function admin_bar_menu_users( $admin_bar ) {
		static $done;
		if ( $done ) return;

		if ( ! $this->get_users() || ! count( $this->get_users() ) ) {
			return;
		}

		$root = self::$root . '-users';

		$admin_bar->add_group( array(
			'id'     => $root,
			'parent' => self::$root,
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		) );
		$admin_bar->add_node( array(
			'id'     => $root . '-title',
			'parent' => $root,
			'title'  => self::do_icon( 'dashicons-admin-users' ) . __( 'Users', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => false,
			'meta'   => array(
				'class'    => 'vaa-has-icon ab-vaa-title ab-vaa-toggle active',
				'tabindex' => '0'
			),
		) );

		/**
		 * Add items at the beginning of the users group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_users_before', $admin_bar, $root, self::$root );

		if ( true === $this->searchUsers ) {
			$admin_bar->add_node( array(
				'id'     => $root . '-searchusers',
				'parent' => $root,
				'title'  => self::do_input( array(
					'name' => $root . '-searchusers',
					'placeholder' => esc_attr__( 'Search', VIEW_ADMIN_AS_DOMAIN ) . ' (' . strtolower( __( 'Username', VIEW_ADMIN_AS_DOMAIN ) ) . ')'
				) ),
				'href'   => false,
				'meta'   => array(
					'class' => 'ab-vaa-search search-users',
					'html'  => '<ul id="vaa-searchuser-results" class="ab-sub-secondary ab-submenu"></ul>',
				),
			) );
		}

		// Add the users
		foreach ( $this->get_users() as $user_key => $user ) {
			$parent = $root;
			$href   = '#';
			$class  = 'vaa-user-item';
			$title  = $user->data->display_name;
			// Check if this user is the current view
			if ( $this->get_viewAs('user') && $this->get_viewAs('user') == $user->data->ID ) {
				$class .= ' current';
				if ( 1 === count( $this->get_viewAs() ) ) {
					$href = false;
				}
			}
			if ( true === $this->groupUserRoles ) {
				// Users grouped under roles
				foreach ( $user->roles as $role ) {
					$parent = self::$root . '-roles-role-' . $role;
					$admin_bar->add_node( array(
						'id'     => $root . '-user-' . $user->data->ID . '-' . $role,
						'parent' => $parent,
						'title'  => $title,
						'href'   => $href,
						'meta'   => array(
							// Translators: %s stands for the user display name
							'title' => sprintf( esc_attr__( 'View as %s', VIEW_ADMIN_AS_DOMAIN ), $user->data->display_name ),
							'class' => $class,
							'rel'   => $user->data->ID,
						),
					) );
				}
			} else {
				// Users displayed as normal
				$all_roles = $this->get_roles();
				$user_roles = array();
				// Add the roles of this user in the name
				foreach ( $user->roles as $role ) {
					$user_roles[] = translate_user_role( $all_roles[ $role ]->name );
				}
				$title = $title.' &nbsp; <span class="user-role">(' . implode( ', ', $user_roles ) . ')</span>';
				$admin_bar->add_node( array(
					'id'     => $root . '-user-' . $user->data->ID,
					'parent' => $parent,
					'title'  => $title,
					'href'   => $href,
					'meta'   => array(
						// Translators: %s stands for the user display name
						'title' => sprintf( esc_attr__( 'View as %s', VIEW_ADMIN_AS_DOMAIN ), $user->data->display_name ),
						'class' => $class,
						'rel'   => $user->data->ID,
					),
				) );
			}
		}

		/**
		 * Add items at the end of the users group
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 */
		do_action( 'vaa_admin_bar_users_after', $admin_bar, $root, self::$root );

		$done = true;
	}

	/**
	 * Add admin bar menu visitor view
	 *
	 * @since   1.6.2
	 * @access  public
	 * @see     'vaa_admin_bar_menu' action
	 * @param   object  $admin_bar
	 * @param   string  $root  Optional
	 * @return  void
	 */
	public function admin_bar_menu_visitor( $admin_bar, $root = '' ) {
		static $done;
		if ( $done ) return;

		$class = 'vaa-visitor-item vaa-has-icon';

		if ( empty( $root ) || $root == self::$root ) {

			$admin_bar->add_group( array(
				'id'     => self::$root . '-visitor',
				'parent' => self::$root,
				'meta'   => array(
					'class' => 'ab-sub-secondary',
				),
			) );

			$root = self::$root . '-visitor';
			$class .= ' ab-vaa-title';
		} else {
			$class .= ' vaa-menupop';
		}

		$admin_bar->add_node( array(
			'id'     => self::$root . '-visitor-view',
			'parent' => $root,
			'title'  => self::do_icon( 'dashicons-universal-access' ) . __( 'Site visitor', VIEW_ADMIN_AS_DOMAIN ),
			'href'   => '#',
			'meta'   => array(
				'title' => esc_attr__( 'View as site visitor', VIEW_ADMIN_AS_DOMAIN ),
				'class' => $class,
				'rel'   => true
			)
		) );

		$done = true;
	}

	/**
	 * Generate button HTML for node
	 *
	 * @since   1.6.1
	 * @since   1.6.2  Added $element option
	 * @access  public
	 * @static
	 * @param   array  $args {
	 *     Required. An array of field arguments
	 *     @type  string  $name     Required
	 *     @type  string  $id       Optional (Will be generated from $name if empty)
	 *     @type  string  $label    Optional
	 *     @type  string  $classes  Optional
	 *     @type  string  $element  Optional
	 *     @type  array   $attr     Optional
	 * }
	 * @return  string
	 */
	public static function do_button( $args ) {
		$id = esc_attr( ( ! empty( $args['id'] ) ) ? $args['id'] : $args['name'] );
		$name = str_replace( '-', '_', esc_attr( $args['name'] ) );
		$elem = ( ! empty( $args['element'] ) ) ? $args['element'] : 'button';
		$label = ( ! empty( $args['label'] ) ) ? $args['label'] : '';
		$classes = ( ( ! empty( $args['classes'] ) ) ? ' ' . $args['classes'] : '' );

		$args['attr']['id'] = $id;
		$args['attr']['name'] = $name;
		$args['attr']['class'] = 'button' . $classes;

		$attr = self::parse_attr_to_html( $args['attr'] );

		return '<' . $elem . ' ' . $attr . '>' . $label . '</' . $elem . '>';
	}

	/**
	 * Generate text input HTML for node
	 *
	 * @since   1.6.1
	 * @since   1.6.x  Automatic show/hide description option
	 * @access  public
	 * @static
	 * @param   array  $args {
	 *     Required. An array of field arguments
	 *     @type  string  $name         Required
	 *     @type  string  $id           Optional (Will be generated from $name if empty)
	 *     @type  string  $placeholder  Optional
	 *     @type  string  $default      Optional
	 *     @type  string  $value        Optional
	 *     @type  string  $label        Optional
	 *     @type  string  $description  Optional
	 *     @type  string  $classes      Optional
	 *     @type  array   $attr         Optional
	 *     @type  bool    $auto_showhide_desc  Optional
	 * }
	 * @return  string
	 */
	public static function do_input( $args ) {

		$html = '';

		$id = esc_attr( ( ! empty( $args['id'] ) ) ? $args['id'] : $args['name'] );
		$name = str_replace( '-', '_', esc_attr( $args['name'] ) );
		$default = ( ! empty( $args['default'] ) ) ? $args['default'] : '';
		$placeholder = ( ! empty( $args['placeholder'] ) ) ? $args['placeholder'] : '';
		$classes = ( ! empty( $args['classes'] ) ) ? $args['classes'] : '';

		$args['attr']['type'] = 'text';
		$args['attr']['id'] = $id;
		$args['attr']['name'] = $name;
		$args['attr']['placeholder'] = $placeholder;
		$args['attr']['value'] = ( ! empty( $args['value'] ) ) ? $args['value'] : $default;
		$args['attr']['class'] = $classes;

		$attr = self::parse_attr_to_html( $args['attr'] );

		$label_attr = array();
		$desc_attr = array();
		if ( ! empty( $args[ 'auto_showhide_desc' ] ) ) {
			$showhide = $id . '-desc';
			$label_attr = array(
				'class' => 'ab-vaa-showhide',
				'data-showhide' => '.' . $showhide
			);
			$desc_attr = array( 'class' => $showhide );
		}

		if ( ! empty( $args['label'] ) ) {
			$html .= self::do_label( $args['label'], $id, $label_attr );
		}
		$html .= '<input ' . $attr . '/>';
		if ( ! empty( $args['description'] ) ) {
			$html .= self::do_description( $args['description'], $desc_attr );
		}
		return $html;
	}

	/**
	 * Generate checkbox HTML for node
	 *
	 * @since   1.6.1
	 * @since   1.6.x  Automatic show/hide description option
	 * @access  public
	 * @static
	 * @param   array  $args {
	 *     Required. An array of field arguments
	 *     @type  string  $name            Required
	 *     @type  string  $id              Optional (Will be generated from $name if empty)
	 *     @type  string  $compare         Optional
	 *     @type  string  $value           Optional
	 *     @type  string  $checkbox_value  Optional  (default: 1)
	 *     @type  string  $label           Optional
	 *     @type  string  $description     Optional
	 *     @type  string  $classes         Optional
	 *     @type  array   $attr            Optional
	 *     @type  bool    $auto_showhide_desc   Optional
	 *     @type  bool    $removable       Optional
	 * }
	 * @return  string
	 */
	public static function do_checkbox( $args ) {

		$html = '';

		$id = esc_attr( ( ! empty( $args['id'] ) ) ? $args['id'] : $args['name'] );
		$name = str_replace( '-', '_', esc_attr( $args['name'] ) );

		if ( empty( $args['value'] ) ) {
			$args['value'] = null;
		}
		if ( empty( $args['compare'] ) ) {
			$args['compare'] = 1;
		}
		$checked = checked( $args['value'], $args['compare'], false );
		$classes = ( ! empty( $args['classes'] ) ) ? ' ' . $args['classes'] : '';

		$args['attr']['type'] = 'checkbox';
		$args['attr']['id'] = $id;
		$args['attr']['name'] = $name;
		$args['attr']['value'] = ( ! empty( $args['checkbox_value'] ) ) ? $args['checkbox_value'] : '1';
		$args['attr']['class'] = 'checkbox' . $classes;

		$attr = self::parse_attr_to_html( $args['attr'] );

		$label_attr = array();
		$desc_attr = array();
		if ( ! empty( $args[ 'auto_showhide_desc' ] ) ) {
			$showhide = $id . '-desc';
			$label_attr = array(
				'class' => 'ab-vaa-showhide',
				'data-showhide' => '.' . $showhide
			);
			$desc_attr = array( 'class' => $showhide );
		}

		$html .= '<input ' . $attr . ' ' . $checked . '/>';
		if ( ! empty( $args['label'] ) ) {
			$html .= self::do_label( $args['label'], $id, $label_attr );
		}
		if ( ! empty( $args['removable'] ) ) {
			$html .= self::do_icon( 'dashicons-dismiss remove', array( 'title' => __( 'Remove', VIEW_ADMIN_AS_DOMAIN ) ) );
		}
		if ( ! empty( $args['description'] ) ) {
			$html .= self::do_description( $args['description'], $desc_attr );
		}
		return $html;
	}

	/**
	 * Generate radio HTML for node
	 *
	 * @since   1.6.1
	 * @since   1.6.x  Automatic show/hide description option
	 * @access  public
	 * @static
	 * @param   array  $data {
	 *     Required. An array of arrays with field arguments
	 *     @type  string  $name         Required
	 *     @type  string  $id           Optional (Will be generated from $name if empty)
	 *     @type  string  $value        Optional
	 *     @type  string  $description  Optional
	 *     @type  bool    $auto_showhide_desc   Optional
	 *     @type  array   $values {
	 *         @type  array  $args {
	 *             @type  string  $compare      Required
	 *             @type  string  $label        Optional
	 *             @type  string  $description  Optional
	 *             @type  string  $classes      Optional
	 *             @type  array   $attr         Optional
	 *             @type  bool    $auto_showhide_desc   Optional  (overwrite $data)
	 *         }
	 *     }
	 * }
	 * @return  string
	 */
	public static function do_radio( $data ) {

		$html = '';

		if ( is_array( $data ) && ! empty( $data['values'] ) ) {
			foreach ( $data['values'] as $args ) {

				$id = esc_attr( ( ( ! empty( $data['id'] ) ) ? $data['id'] : $data['name'] ) . '-' . $args['compare'] );
				$name = str_replace( '-', '_', esc_attr( $data['name'] ) );

				if ( empty( $data['value'] ) ) {
					$data['value'] = null;
				}
				$checked = checked( $data['value'], $args['compare'], false );
				$classes = ( ! empty( $args['classes'] ) ) ? ' ' . $args['classes'] : '';
				$classes .= ' ' . esc_attr( $data['name'] );

				$args['attr']['type'] = 'radio';
				$args['attr']['id'] = $id;
				$args['attr']['name'] = $name;
				$args['attr']['value'] = $args['compare'];
				$args['attr']['class'] = 'radio' . $classes;

				$attr = self::parse_attr_to_html( $args['attr'] );

				$label_attr = array();
				$desc_attr = array();
				if (   ( ! empty( $args['auto_showhide_desc'] ) )
					|| ( ! isset( $args['auto_showhide_desc'] ) && ! empty( $data['auto_showhide_desc'] ) )
				) {
					$showhide = $id . '-desc';
					$label_attr = array(
						'class' => 'ab-vaa-showhide',
						'data-showhide' => '.' . $showhide
					);
					$desc_attr = array( 'class' => $showhide );
				}

				$html .= '<input ' . $attr . ' ' . $checked . '/>';
				if ( ! empty( $args['label'] ) ) {
					$html .= self::do_label( $args['label'], $id, $label_attr );
				}
				$html .= '<br>';
				if ( ! empty( $args['description'] ) ) {
					$html .= self::do_description( $args['description'], $desc_attr );
				}
			}
			if ( ! empty( $data['description'] ) ) {
				$html .= self::do_description( $data['description'] );
			}
		}
		return $html;
	}

	/**
	 * Generate selectbox HTML for node
	 *
	 * @since   1.6.1
	 * @since   1.6.x  Automatic show/hide description option
	 * @access  public
	 * @static
	 * @param   array  $data {
	 *     Required. An array of arrays with field arguments
	 *     @type  string  $name         Required
	 *     @type  string  $id           Optional (Will be generated from $name if empty)
	 *     @type  string  $value        Optional
	 *     @type  string  $label        Optional
	 *     @type  string  $description  Optional
	 *     @type  string  $classes      Optional
	 *     @type  array   $attr         Optional
	 *     @type  bool    $auto_showhide_desc   Optional
	 *     @type  array   $values {
	 *         Arrays of selectbox value data
	 *         @type  array  $args {
	 *             @type  string  $compare  Required
	 *             @type  string  $value    Optional  (Alias for compare)
	 *             @type  string  $label    Optional
	 *             @type  string  $classes  Optional
	 *             @type  array   $attr     Optional
	 *         }
	 *     }
	 * }
	 * @return  string
	 */
	public static function do_select( $data ) {

		$html = '';

		if ( is_array( $data ) && ! empty( $data['values'] ) ) {
			$id = esc_attr( ( ! empty( $data['id'] ) ) ? $data['id'] : $data['name'] );
			$name = str_replace( '-', '_', esc_attr( $data['name'] ) );

			$label_attr = array();
			$desc_attr = array();
			if ( ! empty( $data[ 'auto_showhide_desc' ] ) ) {
				$showhide = $id . '-desc';
				$label_attr = array(
					'class' => 'ab-vaa-showhide',
					'data-showhide' => '.' . $showhide
				);
				$desc_attr = array( 'class' => $showhide );
			}

			if ( ! empty( $data['label'] ) ) {
				$html .= self::do_label( $data['label'], $id, $label_attr );
			}

			if ( empty( $data['value'] ) ) {
				$data['value'] = null;
			}

			$classes = ( ! empty( $data['classes'] ) ) ? ' ' . $data['classes'] : '';

			$data['attr']['id'] = $id;
			$data['attr']['name'] = $name;
			$data['attr']['class'] = 'selectbox' . $classes;
			$attr = self::parse_attr_to_html( $data['attr'] );

			$html .= '<select ' . $attr . '/>';

			foreach ( $data['values'] as $args ) {

				if ( empty( $args['compare'] ) ) {
					$args['compare'] = ( ! empty( $args['value'] ) ) ? $args['value'] : false;
				}
				$label = ( ! empty( $args['label'] ) ) ? $args['label'] : $args['compare'];
				$selected = selected( $data['value'], $args['compare'], false );

				$args['attr']['value'] = $args['compare'];
				$attr = self::parse_attr_to_html( $args['attr'] );

				$html .= '<option ' . $attr . ' ' . $selected . '>' . $label . '</option>';

			}
			$html .= '</select>';

			if ( ! empty( $data['description'] ) ) {
				$html .= self::do_description( $data['description'], $desc_attr );
			}
		}
		return $html;
	}

	/**
	 * Returns icon html for WP admin bar
	 * @since   1.6.1
	 * @since   1.6.x   Added second $attr parameter
	 * @static
	 * @param   string  $icon
	 * @param   array   $attr
	 * @return  string
	 */
	public static function do_icon( $icon, $attr = array() ) {
		$attr['class'] = 'ab-icon dashicons ' . $icon;
		$attr['aria-hidden'] = 'true';
		$attr = self::parse_attr_to_html( $attr );
		return '<span' . $attr . '></span>';
	}

	/**
	 * Returns label html for WP admin bar
	 * @since   1.6.1
	 * @since   1.6.x   Added third $attr parameter
	 * @static
	 * @param   string  $label
	 * @param   string  $for    (optional) Add for attribute
	 * @param   array   $attr
	 * @return  string
	 */
	public static function do_label( $label, $for = '', $attr = array() ) {
		$attr['for'] = $for;
		$attr = self::parse_attr_to_html( $attr );
		return '<label' . $attr . '>' . $label . '</label>';
	}

	/**
	 * Returns description html for WP admin bar
	 * @since   1.6.1
	 * @since   1.6.x   Added second $attr parameter
	 * @static
	 * @param   string  $text
	 * @param   array   $attr
	 * @return  string
	 */
	public static function do_description( $text, $attr = array() ) {
		$attr['class'] = 'ab-item description' . ( ( ! empty( $attr['class'] ) ) ? ' ' . $attr['class'] : '');
		$attr = self::parse_attr_to_html( $attr );
		return '<p' . $attr . '>' . $text . '</p>';
	}

	/**
	 * Converts an array of attributes to a HTML string format starting with a space
	 * @since   1.6.1
	 * @static
	 * @param   array   $array
	 * @return  string
	 */
	public static function parse_attr_to_html( $array ) {
		$str = '';
		if ( is_array( $array ) && ! empty( $array ) ) {
			foreach ( $array as $attr => $value ) {
				$array[ $attr ] = esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
			}
			$str = ' ' . implode( ' ', $array );
		}
		return $str;
	}

	/**
	 * Main Instance.
	 *
	 * Ensures only one instance of this class is loaded or can be loaded.
	 *
	 * @since   1.5
	 * @access  public
	 * @static
	 * @param   VAA_View_Admin_As  $caller  The referrer class
	 * @return  VAA_View_Admin_As_Admin_Bar
	 */
	public static function get_instance( $caller = null ) {
		if ( is_object( $caller ) && 'VAA_View_Admin_As' == get_class( $caller ) ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self( $caller );
			}
			return self::$_instance;
		}
		return null;
	}

} // end class
