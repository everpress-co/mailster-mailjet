<?php
/*
Plugin Name: Mailster Mailjet
Plugin URI: https://mailster.co/?utm_campaign=wporg&utm_source=wordpress.org&utm_medium=plugin&utm_term=Mailjet
Description: Uses Mailjet to deliver emails for the Mailster Newsletter Plugin for WordPress.
Version: 1.1.1
Author: EverPress
Author URI: https://mailster.co
Text Domain: mailster-mailjet
License: GPLv2 or later
*/


define( 'MAILSTER_MAILJET_VERSION', '1.1.1' );
define( 'MAILSTER_MAILJET_REQUIRED_VERSION', '3.0' );
define( 'MAILSTER_MAILJET_FILE', __FILE__ );

require_once dirname( __FILE__ ) . '/classes/mailjet.class.php';
new MailsterMailjet();
