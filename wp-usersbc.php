    <?php
    /*
     * Plugin Name: WP user list
     * Plugin URI: https://github.com/andresgmh/wp-usersbc
     * Description: Create a simple list of WordPress users.
     * Author: Andres Menco Haeckermann
     * Version: 1
     * Author URI: https://github.com/andresgmh/wp-usersbc
     * Text Domain: wp-usersbc
     */
    if (! class_exists('WP_List_Table')) {
      require_once (ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
    }
    // WP_List_Table verication
    if (! class_exists('WP_Users_List_Table')) {
      require_once (ABSPATH . 'wp-admin/includes/class-wp-users-list-table.php');
    }

    class WP_Users_Bclist extends WP_Users_List_Table
    {

      /**
       * Constructor.
       */
      public function __construct()
      {
        parent::__construct(array(
          'singular' => "wp_userbc",
          'plural' => "wp_usersbc",
          'ajax' => true
        ));
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        
        $this->_column_headers = array(
          $columns,
          $hidden,
          $sortable
        );
      }

      /**
       *
       * @see WP_List_Table::get_columns()
       */
      public function get_columns()
      {
        $columns = array(
          'cb' => '<input type="checkbox" />',
          'username' => __('Username'),
          'name' => __('Name'),
          'email' => __('Email'),
          'role' => __('Role'),
          'wp_usersbc_disabled' => __('Status')
        );
        return $columns;
      }

      /**
       * Retrieve an associative array of bulk actions available on this table.
       *
       * @since 3.1.0
       *       
       * @return array Array of bulk actions.
       */
      protected function get_bulk_actions()
      {
        $actions = array();
        
        if (current_user_can('edit_users')) {
          $actions['deactivate'] = __('Deactivate');
        }
        
        return $actions;
      }

      /**
       * Single row
       */
      public function single_row($user_object, $style = '', $role = '', $numposts = 0)
      {
        $user_roles = $this->get_role_list($user_object);
        
        $actions = array();
        
        $checkbox = '';
        
        $edit_link = sprintf('<a href="?page=%s&action=edit-user-profile&id=%s">%s</a>', $_REQUEST['page'], $user_object->ID, __('Edit', 'wp_usersbc'));
        if (current_user_can('edit_user', $user_object->ID)) {
          $edit = "<strong>" . $user_object->user_login . "</strong>";
          $actions['edit'] = sprintf('<a href="?page=%s&action=edit-user-profile&id=%s">%s</a>', $_REQUEST['page'], $user_object->ID, __('Edit', 'wp_usersbc'));
          
          if (! (get_user_meta($user_object->ID, 'wp_usersbc_disabled', true))) {
            $actions['deactivate'] = sprintf('<a href="?page=%s&action=deactivate&id=%s">%s</a>', $_REQUEST['page'], $user_object->ID, __('Deactivate', 'wp_usersbc'));
          }
        }
        
        // Set up the checkbox ( because the user is editable, otherwise it's empty )
        $checkbox = '<label class="screen-reader-text" for="user_' . $user_object->ID . '">' . sprintf(__('Select %s'), $user_object->user_login) . '</label>' . "<input type='checkbox' name='users[]' id='user_{$user_object->ID}' class='{$role_classes}' value='{$user_object->ID}' />";
        
        $email = $user_object->user_email;
        
        // Comma-separated list of user roles.
        $roles_list = implode(', ', $user_roles);
        
        $r = "<tr id='user-$user_object->ID'>";
        
        list ($columns, $hidden, $sortable, $primary) = $this->get_column_info();
        
        foreach ($columns as $column_name => $column_display_name) {
          $classes = "$column_name column-$column_name";
          if ($primary === $column_name) {
            $classes .= ' has-row-actions column-primary';
          }
          
          $data = 'data-colname="' . wp_strip_all_tags($column_display_name) . '"';
          
          $attributes = "class='$classes' $data";
          
          if ('cb' === $column_name) {
            $r .= "<th scope='row' class='check-column'>$checkbox</th>";
          } else {
            $r .= "<td $attributes>";
            switch ($column_name) {
              case 'username':
                $r .= "$edit";
                break;
              case 'name':
                if ($user_object->first_name && $user_object->last_name) {
                  $r .= "$user_object->first_name $user_object->last_name";
                } elseif ($user_object->first_name) {
                  $r .= $user_object->first_name;
                } elseif ($user_object->last_name) {
                  $r .= $user_object->last_name;
                } else {
                  $r .= '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . _x('Unknown', 'name') . '</span>';
                }
                break;
              case 'email':
                $r .= "<a href='" . esc_url("mailto:$email") . "'>$email</a>";
                break;
              case 'role':
                $r .= esc_html($roles_list);
                break;
              case 'wp_usersbc_disabled':
                $status = (get_user_meta($user_object->ID, 'wp_usersbc_disabled', true)) ? "Deactivated" : "Active";
                $r .= $status;
                
                break;
              
              default:
                
                $r .= apply_filters('manage_users_custom_column', '', $column_name, $user_object->ID);
            }
            
            if ($primary === $column_name) {
              $r .= $this->row_actions($actions);
            }
            $r .= "</td>";
          }
        }
        $r .= '</tr>';
        
        return $r;
      }

      /**
       * Page controller menu
       */
      public function add_menu_WP_Users_Clist_page()
      {
        add_menu_page('Custom Users List', 'Custom Users List', 'manage_options', 'custom-users-list.php', array(
          'WP_Users_Bclist',
          'users_list_page_controller'
        ));
      }

      /**
       * Display the list table page
       *
       * @return Void
       */
      public function users_list_page_controller()
      {
        echo '<div class="wrap"><h2>' . __('Users list') . '</h2>';
        
        if (! empty($_REQUEST['action'])) {
          
          $action = $_REQUEST['action'];
          
          switch ($action) {
            
            case 'edit-user-profile':
              
              $user_id = intval($_REQUEST['id']);
              $user_meta = get_userdata($user_id);
              $user_role = $user_meta->roles;
              $status = (get_user_meta($user_id, 'wp_usersbc_disabled', true));
              
              ?>
              <form method="post" id="edituser">
              
              	<p>
              		<strong>User profile ID:</strong> <?php echo $user_id; ?></p>
              
              	<p class="form-wp_usersbc_disabled">
              		<label for="first-wp_usersbc_disabled"><?php _e('Status', 'wp_usersbc'); ?></label>
              		<select name="wp_usersbc_disabled">
              			<option value="0" <?php echo (!$status)?"selected":"";?>>Active</option>
              			<option value="1" <?php echo ($status)?"selected":"";?>>Deactivated</option>
              		</select>
              	</p>
              	<p class="form-role">
              		<label for="first-wp_usersbc_disabled"><?php _e('Status', 'wp_usersbc'); ?></label>
              		<select name="user_role">
                             <?php wp_dropdown_roles( $user_role[0] ); ?>
                           </select>
              	</p>
              	<input name="updateuser" type="submit" id="updateuser"
              		class="submit button" value="<?php _e('Update', 'wp_usersbc'); ?>" />
                         <?php wp_nonce_field( 'doedit-user-profile' ) ?>
                         <input name="action" type="hidden" id="action"
              		value="doedit-user-profile" />
              </form>
              <?php
              break;
            
            case 'doedit-user-profile':
              if (! current_user_can('edit_users'))
                return;
              
              if (isset($_REQUEST['id']) && isset($_POST['wp_usersbc_disabled']) && isset($_POST['user_role'])) {
                $user_id = wp_update_user(array(
                  'ID' => $_REQUEST['id'],
                  'role' => esc_attr($_POST['user_role'])
                ));
                update_user_meta($_REQUEST['id'], 'wp_usersbc_disabled', esc_attr($_POST['wp_usersbc_disabled']));
              }
              
              $userlist = new WP_Users_Bclist();
              $userlist->prepare_items();
              $userlist->display();
              
              break;
            
            case 'deactivate':
              
              if (! current_user_can('edit_users'))
                return;
              
              ?>

            <form method="post" name="updateusers">
            	<p>Do you want deactive User ID: <?php echo ( intval( $_REQUEST['id'] )); ?>?</p>
            	<input type="hidden" name="user_id"
            		value="<?php echo ( intval( $_REQUEST['id'] )); ?>" /> <input
            		name="updateuser" type="submit" id="updateuser"
            		class="submit button primary"
            		value="<?php _e('Confirm Deactivation', 'wp_usersbc'); ?>" />
                    <?php wp_nonce_field( 'doactivate' ) ?>
                    <input name="action" type="hidden" id="action"
            		value="doactivate" />
            
            </form>

			<?php
              
              break;
            
            case 'doactivate':
              
              if (! current_user_can('edit_users'))
                return;
              
              if (isset($_POST['user_id'])) {
                update_user_meta($_POST['user_id'], 'wp_usersbc_disabled', 1);
              }
              
              $userlist = new WP_Users_Bclist();
              $userlist->prepare_items();
              $userlist->display();
              
              break;
          }
        } else {
          $userlist = new WP_Users_Bclist();
          $userlist->prepare_items();
          $userlist->display();
        }
      }
    }
    
    add_action('admin_menu', array(
      'WP_Users_Bclist',
      'add_menu_WP_Users_Clist_page'
    ));

