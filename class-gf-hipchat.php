<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
	
GFForms::include_feed_addon_framework();

class GFHipChat extends GFFeedAddOn {

	protected $_version = GF_HIPCHAT_VERSION;
	protected $_min_gravityforms_version = '1.9.14.26';
	protected $_slug = 'gravityformshipchat';
	protected $_path = 'gravityformshipchat/hipchat.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms HipChat Add-On';
	protected $_short_title = 'HipChat';
	protected $_enable_rg_autoupgrade = true;
	protected $api = null;
	private static $_instance = null;

	/* Permissions */
	protected $_capabilities_settings_page = 'gravityforms_hipchat';
	protected $_capabilities_form_settings = 'gravityforms_hipchat';
	protected $_capabilities_uninstall = 'gravityforms_hipchat_uninstall';

	/* Members plugin integration */
	protected $_capabilities = array( 'gravityforms_hipchat', 'gravityforms_hipchat_uninstall' );

	/**
	 * Get instance of this class.
	 * 
	 * @access public
	 * @static
	 * @return $_instance
	 */
	public static function get_instance() {
		
		if ( self::$_instance == null ) {
			self::$_instance = new self;
		}

		return self::$_instance;
		
	}

	/**
	 * Setup plugin settings fields.
	 * 
	 * @access public
	 * @return array
	 */
	public function plugin_settings_fields() {
						
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'oauth_token',
						'label'             => esc_html__( 'Authentication Token', 'gravityformshipchat' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'type'              => 'save',
						'messages'          => array(
							'success' => esc_html__( 'HipChat settings have been updated.', 'gravityformshipchat' )
						),
					),
				),
			),
		);
		
	}
	
	/**
	 * Plugin starting point. Adds PayPal delayed payment support.
	 * 
	 * @access public
	 * @return void
	 */
	public function init() {
		
		parent::init();
		
		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Send message to HipChat only when payment is received.', 'gravityformshipchat' )
			)
		);
		
	}

	/**
	 * Prepare plugin settings description.
	 * 
	 * @access public
	 * @return string $description
	 */
	public function plugin_settings_description() {
		
		$description  = '<p>';
		$description .= sprintf(
			esc_html__( 'HipChat provides simple group and private chat for your team and your customers. Use Gravity Forms to alert your HipChat rooms of a new form submission. If you don\'t have a HipChat account, you can %1$s sign up for one here.%2$s', 'gravityformshipchat' ),
			'<a href="http://www.hipchat.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';
		
		if ( ! $this->initialize_api() ) {
			
			$description .= '<p>';
			$description .= sprintf(
				esc_html__( 'Gravity Forms HipChat Add-On requires an API token %1$screated by the group admin.%2$s The API token type must be set to admin.', 'gravityformshipchat' ),
				'<a href="https://www.hipchat.com/admin/api" target="_blank">', '</a>'
			);
			$description .= '</p>';
			
		}
		
		return $description;
		
	}
	
	/**
	 * Setup fields for feed settings.
	 * 
	 * @access public
	 * @return array $settings
	 */
	public function feed_settings_fields() {	        

		return array(
			array(
				'title' =>	'',
				'fields' =>	array(
					array(
						'name'           => 'feed_name',
						'label'          => esc_html__( 'Name', 'gravityformshipchat' ),
						'type'           => 'text',
						'class'          => 'medium',
						'required'       => true,
						'tooltip'        => '<h6>'. esc_html__( 'Name', 'gravityformshipchat' ) .'</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformshipchat' ),
						'default_value'  => $this->get_default_feed_name()
					),
					array(
						'name'           => 'room',
						'label'          => esc_html__( 'HipChat Room', 'gravityformshipchat' ),
						'type'           => 'select',
						'required'       => true,
						'choices'        => $this->rooms_for_feed_setting(),
						'tooltip'        => '<h6>'. esc_html__( 'HipChat Room', 'gravityformshipchat' ) .'</h6>' . esc_html__( 'Select which HipChat Room this feed will post a notification to.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'color',
						'label'          => esc_html__( 'Notification Color', 'gravityformshipchat' ),
						'type'           => 'select',
						'choices'        => $this->colors_for_feed_setting(),
						'tooltip'        => '<h6>'. esc_html__( 'Notification Color', 'gravityformshipchat' ) .'</h6>' . esc_html__( 'Select which color the notification will be displayed as.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'notify',
						'label'          => esc_html__( 'Send User Notification', 'gravityformshipchat' ),
						'type'           => 'radio',
						'horizontal'     => true,
						'default_value'  => 0,
						'choices'        => array(
							array( 'label' => 'No',  'value' => 0 ),
							array( 'label' => 'Yes', 'value' => 1 )
						),
						'tooltip'        => '<h6>'. esc_html__( 'Send User Notification', 'gravityformshipchat' ) .'</h6>' . esc_html__( 'Choose whether users in the room will be notified (change the tab color, play a sound, etc) when the message is posted.', 'gravityformshipchat' )
					),
					array(
						'name'           => 'message',
						'label'          => esc_html__( 'Message', 'gravityformshipchat' ),
						'type'           => 'textarea',
						'required'       => true,
						'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'tooltip'        => '<h6>'. esc_html__( 'Message', 'gravityformshipchat' ) .'</h6>' . esc_html__( 'Enter the message that will be posted to the room. Maximum message length: 10,000 characters.', 'gravityformshipchat' ),
						'value'          => '<a href="{entry_url}">Entry #{entry_id} has been added.</a>'
					),
					array(
						'name'           => 'feed_condition',
						'label'          => esc_html__( 'Conditional Logic', 'gravityformshipchat' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable', 'gravityformshipchat' ),
						'instructions'   => esc_html__( 'Post to HipChat if', 'gravityformshipchat' ),
						'tooltip'        => '<h6>'. esc_html__( 'Conditional Logic', 'gravityformshipchat' ) .'</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be posted to HipChat when the condition is met. When disabled, all form submissions will be posted.', 'gravityformshipchat' )

					)
				)
			)
		);
	
	}
	
	/**
	 * Get HipChat rooms for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
	public function rooms_for_feed_setting() {
		
		/* If HipChat API instance is not initialized, return an empty array. */
		if ( ! $this->initialize_api() ) {
			return array();
		}
		
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

	/**
	 * Get notification colors for feed settings field.
	 * 
	 * @access public
	 * @return array $choices
	 */
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

	/**
	 * Set feed creation control.
	 * 
	 * @access public
	 * @return bool
	 */
	public function can_create_feed() {
		
		return $this->initialize_api();
		
	}

	/**
	 * Enable feed duplication.
	 * 
	 * @access public
	 * @param  int|array $feed_id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 * @return bool
	 */
	public function can_duplicate_feed( $feed_id ) {
		
		return true;
		
	}

	/**
	 * Setup columns for feed list table.
	 * 
	 * @access public
	 * @return array
	 */
	public function feed_list_columns() {
		
		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformshipchat' ),
			'room'      => esc_html__( 'HipChat Room', 'gravityformshipchat' )
		);
		
	}

	/**
	 * Get value for room feed list column.
	 * 
	 * @access public
	 * @param mixed $item
	 * @return string
	 */
	public function get_column_value_room( $item ) {
			
		/* If HipChat instance is not initialized, return room ID. */
		if ( ! $this->initialize_api() ) {
			return $item['meta']['room'];
		}
		
		/* Get campaign and return name */
		$room = $this->api->get_room( $item['meta']['room'] );		
		return isset( $room['error'] ) ? $item['meta']['room'] : $room['name'];
		
	}

	/**
	 * Process feed.
	 * 
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Processing feed.' );
		
		/* If HipChat instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Feed was not processed because API was not initialized.', 'gravityformsslack' ), $feed, $entry, $form );
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
	
		/* If message is empty, exit. */
		if ( rgblank( $notification['message'] ) ) {
			$this->add_feed_error( esc_html__( 'Notification was not posted to room because message was empty.', 'gravityformshipchat' ), $feed, $entry, $form );
			return;
		}
	
		/* If message is too long, cut it off at 10,000 characters. */
		if ( strlen( $notification['message'] ) > 10000 ) {
			$notification['message'] = substr( $notification['message'], 0, 10000 );
		}
		
		/* Post notification to room. */
		$this->log_debug( __METHOD__ . '(): Posting notification: ' . print_r( $notification, true ) );
		$notify_room = $this->api->notify_room( $notification );
		
		if ( isset( $notify_room['status'] ) && $notify_room['status'] == 'sent' ) {
			$this->log_debug( __METHOD__ . '(): Notification was posted to room.' );
		} else {
			$this->add_feed_error( esc_html__( 'Notification was not posted to room.', 'gravityformshipchat' ), $feed, $entry, $form );
		}
									
	}

	/**
	 * Initializes HipChat API if credentials are valid.
	 * 
	 * @access public
	 * @return bool
	 */
	public function initialize_api() {
		
		if ( ! is_null( $this->api ) ) {
			return true;
		}
		
		/* Load the API library. */
		if ( ! class_exists( 'HipChat' ) ) {
			require_once( 'includes/class-hipchat.php' );
		}

		/* Get the OAuth token. */
		$oauth_token = $this->get_plugin_setting( 'oauth_token' );
		
		/* If the OAuth token, do not run a validation check. */
		if ( rgblank( $oauth_token ) ) {
			return null;
		}
		
		$this->log_debug( __METHOD__ . '(): Validating API Info.' );

		/* Setup a new HipChat object with the API credentials. */

		/**
		 * Enable or disable Verification of Hipchat SSL
		 *
		 * @param bool True or False to verify SSL
		 */
		$verify_ssl = apply_filters( 'gform_hipchat_verify_ssl', true );
		$hipchat = new HipChat( $oauth_token, $verify_ssl );
		
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