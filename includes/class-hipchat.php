<?php
	
	class HipChat {
		
		protected $api_url = 'https://api.hipchat.com/v1/';
		
		function __construct( $oauth_token ) {
			
			$this->oauth_token = $oauth_token;
			
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
		function make_request( $path, $options = array(), $method = 'GET' ) {
			
			/* Build base request options string. */
			$request_options = '?format=json&auth_token='. $this->oauth_token;
			
			/* Add options if this is a GET request. */
			$request_options .= ( $method == 'GET' ) ? '&'. http_build_query( $options ) : null;
			
			/* Build request URL. */
			$request_url = $this->api_url . $path . $request_options;
						
			/* Initialize cURL session. */
			$curl = curl_init();
			
			/* Setup cURL options. */
			curl_setopt( $curl, CURLOPT_URL, $request_url );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
			
			/* If this is a POST request, pass the request options via cURL option. */
			if ( $method == 'POST' ) {
				
				curl_setopt( $curl, CURLOPT_POST, true );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $options );
				
			}

			/* If this is a PUT request, pass the request options via cURL option. */
			if ( $method == 'PUT' ) {
				
				curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, $request_options );
				
			}
			
			/* Execute cURL request. */
			$curl_result = curl_exec( $curl );
			
			/* If there is an error, die with error message. */
			if ( $curl_result === false ) {
				
				die( 'cURL error: '. curl_error( $curl ) );
				
			}
			
			/* Close cURL session. */
			curl_close( $curl );
			
			/* Attempt to decode JSON. If isn't JSON, return raw cURL result. */
			$json_result = json_decode( $curl_result, true );		
			return ( json_last_error() == JSON_ERROR_NONE ) ? $json_result : $curl_result;
			
		}

		/**
		 * Test OAuth token.
		 * 
		 * @access public
		 * @return bool
		 */
		function auth_test() {
			
			$auth_test_request = $this->make_request( 'rooms/list', array( 'auth_test' => 'true' ) );
			
			if ( isset( $auth_test_request['success'] ) ) {
				
				return true;
				
			} else {
				
				return false;
				
			}
			
		}
		
		/**
		 * Get a room.
		 * 
		 * @access public
		 * @param mixed $room
		 * @return void
		 */
		function get_room( $room ) {
			
			$request = $this->make_request( 'rooms/show', array( 'room_id' => $room ) );
			return isset( $request['room'] ) ? $request['room'] : $request;
			
		}
		
		/**
		 * Get all rooms.
		 * 
		 * @access public
		 * @return array
		 */
		function get_rooms() {
			
			$request = $this->make_request( 'rooms/list' );
			return $request['rooms'];
			
		}
		
		/**
		 * Send room notification.
		 * 
		 * @access public
		 * @param array $params (default: array())
		 * @return void
		 */
		function notify_room( $params = array() ) {
			
			return $this->make_request( 'rooms/message', $params, 'POST' );
			
		}
		
	}