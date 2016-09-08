<?php
	
	class HipChat {
		
		protected $api_url = 'https://api.hipchat.com/v1/';
		
		function __construct( $oauth_token, $verify_ssl = true ) {
			
			$this->oauth_token = $oauth_token;
			$this->verify_ssl = $verify_ssl;
			
		}

		/**
		 * Make API request.
		 * 
		 * @access public
		 * @param string $path
		 * @param array $options
		 * @param bool $return_status (default: false)
		 * @param string $method (default: 'GET')
		 * @return void
		 */
		function make_request( $path, $options = array(), $method = 'GET', $return_key = null ) {
			
			/* Build base request options string. */
			$request_options = '?format=json&auth_token='. $this->oauth_token;
			
			/* Add options if this is a GET request. */
			$request_options .= ( $method == 'GET' ) ? '&'. http_build_query( $options ) : null;
			
			/* Build request URL. */
			$request_url = $this->api_url . $path . $request_options;
			
			/* Setup request arguments. */
			$args = array(
				'method'    => $method,
				'sslverify' => $this->verify_ssl	
			);
			
			/* Add request options to body of POST and PUT requests. */
			if ( $method == 'POST' || $method == 'PUT' ) {
				$args['body'] = $options;
			}
			
			/* Execute request. */
			$result = wp_remote_request( $request_url, $args );
			
			/* If WP_Error, throw exception */
			if ( is_wp_error( $result ) ) {
				throw new Exception( 'Request failed. '. $result->get_error_messages() );
			}

			/* Decode JSON. */
			$decoded_result = json_decode( $result['body'], true );
			
			/* If invalid JSON, return original result body. */
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return $result['body'];
			}
			
			/* If return key is set and exists, return array item. */
			if ( $return_key && array_key_exists( $return_key, $decoded_result ) ) {
				return $decoded_result[ $return_key ];
			}
			
			return $decoded_result;
			
		}

		/**
		 * Test OAuth token.
		 * 
		 * @access public
		 * @return bool
		 */
		public function auth_test() {
			$auth_test_request = $this->make_request( 'rooms/list', array( 'auth_test' => 'true' ) );
			return isset( $auth_test_request['success'] );
		}
		
		/**
		 * Get a room.
		 * 
		 * @access public
		 * @param mixed $room
		 * @return void
		 */
		public function get_room( $room ) {
			return $this->make_request( 'rooms/show', array( 'room_id' => $room ), 'GET', 'room' );
		}
		
		/**
		 * Get all rooms.
		 * 
		 * @access public
		 * @return array
		 */
		public function get_rooms() {
			return $this->make_request( 'rooms/list', array(), 'GET', 'rooms' );
		}
		
		/**
		 * Send room notification.
		 * 
		 * @access public
		 * @param array $notification (default: array())
		 * @return void
		 */
		public function notify_room( $notification = array() ) {
			return $this->make_request( 'rooms/message', $notification, 'POST' );
		}
		
	}