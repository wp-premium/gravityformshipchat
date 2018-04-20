<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}
	
/*
Plugin Name: Gravity Forms HipChat Add-On
Plugin URI: https://www.gravityforms.com
Description: Integrates Gravity Forms with HipChat, allowing alerts for Gravity Forms activity to be posted to your HipChat rooms.
Version: 1.2
Author: rocketgenius
Author URI: https://www.rocketgenius.com
License: GPL-2.0+
Text Domain: gravityformshipchat
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009 rocketgenius
last updated: October 20, 2010

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define('GF_HIPCHAT_VERSION', '1.2');

add_action('gform_loaded', array('GF_HipChat_Bootstrap', 'load'), 5);

class GF_HipChat_Bootstrap {

	public static function load(){
		require_once('class-gf-hipchat.php');
		GFAddOn::register('GFHipChat');
	}

}

function gf_hipchat() {
	return GFHipChat::get_instance();
}