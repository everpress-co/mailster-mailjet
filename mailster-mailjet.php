<?php
/*
Plugin Name: Mailster Mailjet
Requires Plugins: mailster
Plugin URI: https://mailster.co/?utm_campaign=wporg&utm_source=wordpress.org&utm_medium=plugin&utm_term=Mailjet
Description: Uses Mailjet to deliver emails for the Mailster Newsletter Plugin for WordPress.
Version: 1.1.2
Author: EverPress
Author URI: https://mailster.co
Text Domain: mailster-mailjet
License: GPLv2 or later
*/


define( 'MAILSTER_MAILJET_VERSION', '1.1.2' );
define( 'MAILSTER_MAILJET_REQUIRED_VERSION', '4.0' );
define( 'MAILSTER_MAILJET_FILE', __FILE__ );

require_once __DIR__ . '/classes/mailjet.class.php';
new MailsterMailjet();
