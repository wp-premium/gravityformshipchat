<?php
	
GFForms::include_feed_addon_framework();

class GFHipChat extends GFFeedAddOn {

	protected $_version = GF_HIPCHAT_VERSION;
	protected $_min_gravityforms_version = '1.9.5.1';
	protected $_slug = 'gravityformshipchat';
	protected $_path = 'gravityformshipchat/hipchat.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms HipChat Add-On';
	protected $_short_title = 'HipChat';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_hipchat', 'gravityforms_hipchat_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_hipchat';
	protected $_capabilities_form_settings = 'gravityforms_hipchat';
	protected $_capabilities_uninstall = 'gravityforms_hipchat_uninstall';
	protected $_enable_rg_autoupgrade = true;

	protected $api = null;
	private static $_instance = null;

	public static function get_instance() {
		
		if ( self::$_instance == null )
			self::$_instance = new GFHipChat();

		return self::$_instance;
		
	}

	/* Settings Page */
	public function plugin_settings_fields() {
						
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'oauth_token',
						'label'             => __( 'Authentication Token', 'gravityformshipchat' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'type'              => 'save',
						'messages'          => array(
							'success' => __( 'HipChat settings have been updated.', 'gravityformshipchat' )
						),
					),
				),
			),
		);
		
	}
	
	/* Prepare plugin settings description */
	public function plugin_settings_description() {
		
		$description  = '<p>';
		$description .= sprintf(
			__( 'HipChat provides simple group and private chat for your team and your customers. Use Gravity Forms to alert your HipChat rooms of a new form submission. If you don\'t have a HipChat account, you can %1$s sign up for one here.%2$s', 'gravityformshipchat' ),
			'<a href="http://www.hipchat.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';
		
		if ( ! $this->initialize_api() ) {
			
			$description .= '<p>';
			$description .= sprintf(
				__( 'Gravity Forms HipChat Add-On requires an API token %1$screated by the group admin.%2$s The API token type must be set to admin.', 'gravityformshipchat' ),
				'<a href="https://www.hipchat.com/admin/api" target="_blank">', '</a>'
			);
			$description .= '</p>';
			
		}
		
		return $description;
		
	}
	
	/* Setup feed settings fields */
	public function feed_settings_fields() {	        

		return array(
			array(
				'title' =>	'',
				'fields' =>	array(
					array(
						'name'           => 'feed_name',
						'label'          => __( 'Name', 'gravityformshipchat' ),
						'type'           => 'text',
						'class'          => 'medium',
						'required'       => true,
						'tooltip'        => '<h6>'. __( 'Name', 'gravityformshipchat' ) .'</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'room',
						'label'          => __( 'HipChat Room', 'gravityformshipchat' ),
						'type'           => 'select',
						'required'       => true,
						'choices'        => $this->rooms_for_feed_setting(),
						'tooltip'        => '<h6>'. __( 'HipChat Room', 'gravityformshipchat' ) .'</h6>' . __( 'Select which HipChat Room this feed will post a notification to.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'color',
						'label'          => __( 'Notification Color', 'gravityformshipchat' ),
						'type'           => 'select',
						'choices'        => $this->colors_for_feed_setting(),
						'tooltip'        => '<h6>'. __( 'Notification Color', 'gravityformshipchat' ) .'</h6>' . __( 'Select which color the notification will be displayed as.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'notify',
						'label'          => __( 'Send User Notification', 'gravityformshipchat' ),
						'type'           => 'radio',
						'horizontal'     => true,
						'default_value'  => 0,
						'choices'        => array(
							array( 'label' => 'No',  'value' => 0 ),
							array( 'label' => 'Yes', 'value' => 1 )
						),
						'tooltip'        => '<h6>'. __( 'Send User Notification', 'gravityformshipchat' ) .'</h6>' . __( 'Choose whether users in the room will be notified (change the tab color, play a sound, etc) when the message is posted.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'message',
						'label'          => __( 'Message', 'gravityformshipchat' ),
						'type'           => 'textarea',
						'required'       => true,
						'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'tooltip'        => '<h6>'. __( 'Message', 'gravityformshipchat' ) .'</h6>' . __( 'Enter the message that will be posted to the room. Maximum message length: 10,000 characters.', 'gravityformshipchat' ),
						'value'          => '<a href="{entry_url}">Entry #{entry_id} has been added.</a>'
					),
					array(
						'name'           => 'feed_condition',
						'label'          => __( 'Conditional Logic', 'gravityformshipchat' ),
						'type'           => 'feed_condition',
						'checkbox_label' => __( 'Enable', 'gravityformshipchat' ),
						'instructions'   => __( 'Post to HipChat if', 'gravityformshipchat' ),
						'tooltip'        => '<h6>'. __( 'Conditional Logic', 'gravityformshipchat' ) .'</h6>' . __( 'When conditional logic is enabled, form submissions will only be posted to HipChat when the condition is met. When disabled, all form submissions will be posted.', 'gravityformshipchat' )

					)
				)
			)
		);
	
	}
	
	/* Get HipChat rooms for feed settings field */
	public function rooms_for_feed_setting() {
		
		/* If HipChat API instance is not initialized, return an empty array. */
		if ( ! $this->initialize_api() )
			return array();
		
		/* Setup choices array */
		$choices = array();
		
		/* Get the rooms */
		$rooms = $this->api->get_rooms();
		
		/* Add lists to the choices array */
		if ( ! empty( $rooms ) ) {
			
			foreach ( $rooms as $room ) {
				
				$choices[] = array(
					'label' => $room['name'],
					'value' => $room['room_id']
				);
				
			}
			
		}
		
		return $choices;

		
	}	

	/* Get notification colors for feed settings field */
	public function colors_for_feed_setting() {
		
		/* Set array of available colors. */
		$colors = array( 'yellow', 'green', 'red', 'purple', 'gray', 'random' );
		
		/* Setup choices array. */
		$choices = array();
		
		/* Push colors to array. */
		foreach ( $colors as $color ) {
			
			$choices[] = array(
				'label' => ucfirst( $color ),
				'value' => $color	
			);
			
		}
		
		return $choices;
		
	}

	/* Hide "Add New" feed button if API credentials are invalid */		
	public function feed_list_title() {
		
		if ( $this->initialize_api() )
			return parent::feed_list_title();
			
		return sprintf( __( '%s Feeds', 'gravityforms' ), $this->get_short_title() );
		
	}

	/* Notify user to configure add-on before setting up feeds */
	public function feed_list_message() {

		$message = parent::feed_list_message();
		
		if ( $message !== false )
			return $message;

		if ( ! $this->initialize_api() )
			return $this->configure_addon_message();

		return false;
		
	}
	
	/* Feed list message for user to configure add-on */
	public function configure_addon_message() {
		
		$settings_label = sprintf( __( '%s Settings', 'gravityformshipchat' ), $this->get_short_title() );
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		return sprintf( __( 'To get started, please configure your %s.', 'gravityformshipchat' ), $settings_link );
		
	}

	/* Setup feed list columns */
	public function feed_list_columns() {
		
		return array(
			'feed_name' => 'Name',
			'room'      => 'HipChat Room'
		);
		
	}

	/* Change value of room feed column to room name */
	public function get_column_value_room( $item ) {
			
		/* If HipChat instance is not initialized, return room ID. */
		if ( ! $this->initialize_api() )
			return $item['meta']['room'];
		
		/* Get campaign and return name */
		$room = $this->api->get_room( $item['meta']['room'] );		
		return ( isset( $room['error'] ) ) ? $item['meta']['room'] : $room['name'];
		
	}

	/* Process feed */
	public function process_feed( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Processing feed.' );
		
		/* If HipChat instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): API not initialized; feed will not be processed.' );
			return;
		}
		
		/* Prepare notification array. */
		$notification = array(
			'color'   => $feed['meta']['color'],
			'from'    => 'Gravity Forms',
			'message' => $feed['meta']['message'],
			'notify'  => $feed['meta']['notify'],
			'room_id' => $feed['meta']['room']
		);
		
		/* Replace merge tags on notification message. */
		$notification['message'] = GFCommon::replace_variables( $notification['message'], $form, $entry );
	
		/* Strip unallowed tags */
		$notification['message'] = strip_tags( $notification['message'], '<a><b><i><strong><em><br><img><pre><code><lists><tables>' );
	
		/* If message is too long, cut it off at 10,000 characters. */
		if ( strlen( $notification['message'] ) > 10000 )
			$notification['message'] = substr( $notification['message'], 0, 10000 );
		
		/* Post notification to room. */
		$notify_room = $this->api->notify_room( $notification );
		
		if ( isset( $notify_room['status'] ) && $notify_room['status'] == 'sent' ) {
			$this->log_debug( __METHOD__ . "(): Notification was posted to room." );			
		} else {
			$this->log_error( __METHOD__ . "(): Notification was not posted to room." );
		}
									
	}

	/* Checks validity of HipChat credentials and initializes API if valid. */
	public function initialize_api() {
		
		if ( ! is_null( $this->api ) )
			return true;
		
		/* Load the API library. */
		require_once( 'includes/class-hipchat.php' );

		/* Get the OAuth token. */
		$oauth_token = $this->get_plugin_setting( 'oauth_token' );
		
		/* If the OAuth token, do not run a validation check. */
		if ( rgblank( $oauth_token ) )
			return null;
		
		$this->log_debug( __METHOD__ . "(): Validating login for API Info for {$oauth_token}." );

		/* Setup a new HipChat object with the API credentials. */
		$hipchat = new HipChat( $oauth_token );
		
		/* Run an authentication test. */
		if ( $hipchat->auth_test() ) {
		
			$this->api = $hipchat;
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );
			return true;
			
		} else {
			
			$this->log_error( __METHOD__ . '(): API credentials are invalid.' );
			return false;			
			
		}
		
	}

}