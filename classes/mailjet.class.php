<?php

class MailsterMailjet {

	private $plugin_path;
	private $plugin_url;

	/**
	 *
	 */
	public function __construct() {

		$this->plugin_path = plugin_dir_path( MAILSTER_MAILJET_FILE );
		$this->plugin_url  = plugin_dir_url( MAILSTER_MAILJET_FILE );

		register_activation_hook( MAILSTER_MAILJET_FILE, array( &$this, 'activate' ) );
		register_deactivation_hook( MAILSTER_MAILJET_FILE, array( &$this, 'deactivate' ) );

		load_plugin_textdomain( 'mailster-mailjet' );

		add_action( 'init', array( &$this, 'init' ), 1 );
	}


	/*
	 * init the plugin
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		if ( ! function_exists( 'mailster' ) ) {

			add_action( 'admin_notices', array( &$this, 'notice' ) );

		} else {

			add_filter( 'mailster_delivery_methods', array( &$this, 'delivery_method' ) );
			add_action( 'mailster_deliverymethod_tab_mailjet', array( &$this, 'deliverytab' ) );

			add_filter( 'mailster_verify_options', array( &$this, 'verify_options' ) );

			if ( mailster_option( 'deliverymethod' ) == 'mailjet' ) {
				add_action( 'mailster_initsend', array( &$this, 'initsend' ) );
				add_action( 'mailster_presend', array( &$this, 'presend' ) );
				add_action( 'mailster_dosend', array( &$this, 'dosend' ) );
				add_action( 'mailster_section_tab_bounce', array( &$this, 'section_tab_bounce' ) );
				add_filter( 'mailster_subscriber_errors', array( $this, 'subscriber_errors' ) );

				if ( isset( $_GET['mailster_mailjet'] ) ) {
					$this->handle_webhook();
				}
			}
		}
	}


	/**
	 * initsend function.
	 *
	 * uses mailster_initsend hook to set initial settings
	 *
	 * @access public
	 * @return void
	 * @param mixed $mailobject
	 */
	public function initsend( $mailobject ) {

		// Mailjet will handle DKIM integration
		$mailobject->dkim = false;
	}


	/**
	 * presend function.
	 *
	 * uses the mailster_presend hook to apply settings before each mail
	 *
	 * @access public
	 * @return void
	 * @param mixed $mailobject
	 */
	public function presend( $mailobject ) {

		$mailobject->pre_send();

		$message_obj = array();

		$data = array(
			'mailster_id'   => mailster_option( 'ID' ),
			'campaign_id'   => (string) $mailobject->campaignID,
			'index'         => (string) $mailobject->index,
			'subscriber_id' => (string) $mailobject->subscriberID,
		);

		$data = json_encode( $data );
		$data = base64_encode( $data );

		$message_obj['EventPayload'] = $data;

		$recipients = array();

		foreach ( $mailobject->to as $i => $to ) {
			$recipients[] = array(
				'Email' => $mailobject->to[ $i ],
				'Name'  => $mailobject->to_name[ $i ],
			);
		}

		$message_obj['From'] = array(
			'Email' => $mailobject->from,
			'Name'  => $mailobject->from_name,
		);
		$message_obj['To']   = $recipients;

		$message_obj['Subject']  = $mailobject->subject;
		$message_obj['TextPart'] = $mailobject->mailer->AltBody;
		$message_obj['HTMLPart'] = $mailobject->mailer->Body;

		$message_obj['Headers'] = array( 'Reply-To' => $mailobject->reply_to );

		if ( $mailobject->headers ) {
			foreach ( $mailobject->headers as $key => $value ) {
				if ( ! in_array( $key, array( 'List-ID' ) ) ) {
					$message_obj['Headers'][ $key ] = $value;
				}
			}
		}

		if ( ! empty( $mailobject->attachments ) || $mailobject->embed_images ) {

			$org_attachments                   = $mailobject->mailer->getAttachments();
			$message_obj['Attachments']        = array();
			$message_obj['InlinedAttachments'] = array();

			foreach ( $org_attachments as $attachment ) {

				$a = array(
					'ContentType'   => $attachment[4],
					'Filename'      => $attachment[1],
					'Base64Content' => base64_encode( file_get_contents( $attachment[0] ) ),
				);

				if ( 'inline' == $attachment[6] ) {
					$message_obj['HTMLPart'] = str_replace( '"cid:' . $attachment[7] . '"', '"cid:' . $attachment[1] . '"', $message_obj['HTMLPart'] );
					$a['ContentID']          = $attachment[1];

					$message_obj['InlinedAttachments'][] = $a;
				} else {
					$message_obj['Attachments'][] = $a;
				}
			}
		}

		$mailobject->mailjet_object = array(
			'Messages'    => array( $message_obj ),
			'SandboxMode' => false,
		);

		$mailobject->mailjet_object = apply_filters( 'mailster_mailjet_object', $mailobject->mailjet_object, $mailobject );
	}


	/**
	 * dosend function.
	 *
	 * uses the mailster_dosend hook and triggers the send
	 *
	 * @access public
	 * @param mixed $mailobject
	 * @return void
	 */
	public function dosend( $mailobject ) {

		if ( ! isset( $mailobject->mailjet_object ) ) {
			$mailobject->set_error( __( 'Mailjet options not defined', 'mailster-mailjet' ) );
			$mailobject->sent = false;
			return false;
		}

		$response = $this->do_post( 'v3.1/send', $mailobject->mailjet_object, 60 );

		if ( is_wp_error( $response ) ) {
			$code = $response->get_error_code();
			if ( 403 == $code ) {
				$errormessage = __( 'Not able to send message. Make sure your API Key is allowed to read and write Transmissions!', 'mailster-mailjet' );
			} else {
				$errormessage = $response->get_error_message();
			}
			$mailobject->set_error( $errormessage );
			$mailobject->sent = false;
		} else {
			$mailobject->sent = true;
		}
	}



	/**
	 * delivery_method function.
	 *
	 * add the delivery method to the options
	 *
	 * @access public
	 * @param mixed $delivery_methods
	 * @return void
	 */
	public function delivery_method( $delivery_methods ) {
		$delivery_methods['mailjet'] = 'Mailjet';
		return $delivery_methods;
	}


	/**
	 * deliverytab function.
	 *
	 * the content of the tab for the options
	 *
	 * @access public
	 * @return void
	 */
	public function deliverytab() {

		$verified = mailster_option( 'mailjet_verified' );

		include $this->plugin_path . '/views/settings.php';
	}


	public function do_get( $endpoint, $args = array(), $timeout = 15 ) {
		return $this->do_call( 'GET', $endpoint, $args, $timeout );
	}
	public function do_post( $endpoint, $args = array(), $timeout = 15 ) {
		return $this->do_call( 'POST', $endpoint, $args, $timeout );
	}
	public function do_put( $endpoint, $args = array(), $timeout = 15 ) {
		return $this->do_call( 'PUT', $endpoint, $args, $timeout );
	}


	/**
	 *
	 * @access public
	 * @param unknown $apikey  (optional)
	 * @return void
	 */
	private function do_call( $method, $endpoint, $args = array(), $timeout = 15 ) {

		$args             = wp_parse_args( $args, array() );
		$body             = null;
		$apikey           = isset( $this->apikey ) ? $this->apikey : mailster_option( 'mailjet_apikey' );
		$secret           = isset( $this->secret ) ? $this->secret : mailster_option( 'mailjet_secret' );
		$mailjet_endpoint = 'https://api.mailjet.com/';
		$url              = $mailjet_endpoint . $endpoint;

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $apikey . ':' . $secret ),
		);

		if ( 'GET' == $method ) {
			$url = add_query_arg( $args, $url );
		} elseif ( 'POST' == $method ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = json_encode( $args );
		} elseif ( 'PUT' == $method ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = json_encode( $args );
		} else {
			return new WP_Error( 'method_not_allowed', 'This method is not allowed' );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => $timeout,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 != $code && 201 != $code ) {
			$body = json_decode( $body );
			if ( isset( $body->message ) ) {
				$message = $body->message;
			} else {
				$message = wp_remote_retrieve_response_message( $response );
			}
			return new WP_Error( $code, $message );
		} else {
			$body = json_decode( $body );
		}

		return $body;
	}


	/**
	 *
	 * @access public
	 * @return void
	 */
	public function verify( $apikey = null ) {

		if ( ! is_null( $apikey ) ) {
			$this->apikey = $apikey;
		}

		$response = $this->get_account_settings();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}


	/**
	 *
	 * @access public
	 * @return void
	 */
	public function maybe_create_webhooks() {

		if ( mailster_is_local() ) {
			return;
		}

		$mailjet_key = mailster_option( 'mailjet_key' );

		if ( ! $mailjet_key ) {
			return;
		}

		$url    = add_query_arg( array( 'mailster_mailjet' => $mailjet_key ), home_url( '/' ) );
		$events = array( 'bounce', 'spam', 'blocked', 'unsub' );
		$update = array();
		$args   = array(
			'IsBackup' => 'false',
			'Status'   => 'alive',
			'Url'      => rawurldecode( $url ),
		);

		$response = $this->do_get( 'v3/REST/eventcallbackurl' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		foreach ( $response->Data as $entry ) {
			if ( $entry->Url == $url && $entry->Status == 'alive' ) {
				$events = array_diff( $events, array( $entry->EventType ) );
			} else {
				$update[] = $entry;
			}
		}

		// update if needed
		foreach ( $update as  $entry ) {
			$args     = wp_parse_args( array( 'EventType' => $entry->EventType ), $args );
			$response = $this->do_put( 'v3/REST/eventcallbackurl/' . $entry->ID, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		// create new if needed
		foreach ( $events as $event ) {
			$args     = wp_parse_args( array( 'EventType' => $event ), $args );
			$response = $this->do_post( 'v3/REST/eventcallbackurl', $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}
	}


	/**
	 *
	 * @access public
	 * @return void
	 */
	public function get_account_settings() {

		$response = $this->do_get( 'v3/REST/myprofile' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$domains = $response->Data;

		return $domains;
	}


	/**
	 *
	 * @access public
	 * @return void
	 */
	public function get_sending_domains() {

		$response = $this->do_get( 'v3/REST/sender', 'limit=1000' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$domains = $response->Data;

		return $domains;
	}

	/**
	 *
	 * @access public
	 * @return void
	 */
	public function get_subaccounts() {

		$response = $this->do_get( 'subaccounts' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$accounts = $response->results;

		return $accounts;
	}



	/**
	 *
	 * @access public
	 * @param mixed $options
	 * @return void
	 */
	public function verify_options( $options ) {

		if ( $timestamp = wp_next_scheduled( 'mailster_mailjet_cron' ) ) {
			wp_unschedule_event( $timestamp, 'mailster_mailjet_cron' );
		}

		if ( $options['deliverymethod'] == 'mailjet' ) {

			$old_apikey          = mailster_option( 'mailjet_apikey' );
			$old_delivery_method = mailster_option( 'deliverymethod' );

			if ( ! wp_next_scheduled( 'mailster_mailjet_cron' ) ) {
				wp_schedule_event( time(), 'mailster_cron_interval', 'mailster_mailjet_cron' );
			}

			$this->maybe_create_webhooks();

			if ( $old_apikey != $options['mailjet_apikey'] || ! $options['mailjet_verified'] || $old_delivery_method != 'mailjet' ) {
				$response = $this->verify( $options['mailjet_apikey'] );

				if ( is_wp_error( $response ) ) {
					$options['mailjet_verified'] = false;
					add_settings_error( 'mailster_options', 'mailster_options', __( 'Not able to get Account details. Make sure your API Key is correct and allowed to read Account details!', 'mailster-mailjet' ) );
				} else {

					$options['mailjet_verified'] = true;
				}
			}
		}

		return $options;
	}



	public function subscriber_errors( $errors ) {
		$errors[] = 'Message generation rejected';
		$errors[] = '\'to\' parameter is not a valid address. please check documentation';
		return $errors;
	}



	private function handle_webhook() {

		if ( mailster_option( 'mailjet_key' ) == $_GET['mailster_mailjet'] ) {

			if ( ! ( $data = file_get_contents( 'php://input' ) ) ) {
				wp_die( 'This page handles the Bounces and messages from Mailjet for Mailster.', 'Mailster Mailjet Endpoint' );
			}

			$obj = json_decode( $data );

			$data = json_decode( base64_decode( $obj->Payload ) );
			$MID  = mailster_option( 'ID' );

			if ( ! isset( $data->mailster_id ) || $data->mailster_id != $MID ) {
				return;
			}

			update_option( 'mailster_mailjet_last_response', $obj, 'no' );

			if ( isset( $data->subscriber_id ) && $data->subscriber_id ) {
				$subscriber = mailster( 'subscribers' )->get( $data->subscriber_id );
			} else {
				$subscriber = mailster( 'subscribers' )->get_by_mail( $obj->email );
			}
			if ( ! $subscriber ) {
				return;
			}
			if ( isset( $data->campaign_id ) ) {
				$campaign_id = $data->campaign_id;
			} else {
				$campaign_id = null;
			}
			if ( isset( $data->index ) ) {
				$index = $data->index;
			} else {
				$index = null;
			}

			$status = '[' . $obj->error_related_to . '] ' . $obj->error;
			if ( isset( $obj->comment ) ) {
				$status .= ' ' . $obj->comment;
			}

			switch ( $obj->event ) {
				case 'spam':
				case 'unsub':
					mailster( 'subscribers' )->unsubscribe( $subscriber->ID, $campaign_id, $status, $index );
					break;
				case 'bounce':
					if ( ! $obj->hard_bounce ) {
						break;
					}
				case 'blocked':
					mailster( 'subscribers' )->bounce( $subscriber->ID, $campaign_id, true, $status, $index );
					break;
			}

			exit;

		}
	}


	/**
	 * section_tab_bounce function.
	 *
	 * displays a note on the bounce tab
	 *
	 * @access public
	 * @param mixed $options
	 * @return void
	 */
	public function section_tab_bounce() {

		?>
		<div class="error inline"><p><strong><?php esc_html_e( 'Bouncing is handled by Mailjet so all your settings will be ignored', 'mailster-mailjet' ); ?></strong></p></div>

		<?php
	}



	/**
	 * Notice if Mailster is not available
	 *
	 * @access public
	 * @return void
	 */
	public function notice() {
		?>
	<div id="message" class="error">
		<p>
		<strong>Mailjet integration for Mailster</strong> requires the <a href="https://mailster.co/?utm_campaign=wporg&utm_source=wordpress.org&utm_medium=plugin&utm_term=Mailjet+integration+for+Mailster">Mailster Newsletter Plugin</a>, at least version <strong><?php echo MAILSTER_MAILJET_REQUIRED_VERSION; ?></strong>.
		</p>
	</div>
		<?php
	}



	/**
	 * activate function
	 *
	 * @access public
	 * @return void
	 */
	public function activate() {

		if ( function_exists( 'mailster' ) ) {

			mailster_notice( sprintf( __( 'Change the delivery method on the %s!', 'mailster-mailjet' ), '<a href="edit.php?post_type=newsletter&page=mailster_settings&mailster_remove_notice=delivery_method#delivery">' . __( 'Settings Page', 'mailster-mailjet' ) . '</a>' ), '', 360, 'delivery_method' );

			$defaults = array(
				'mailjet_domain'   => null,
				'mailjet_verified' => false,
			);

			$mailster_options = mailster_options();

			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $mailster_options[ $key ] ) ) {
					mailster_update_option( $key, $value );
				}
			}
		}
	}


	/**
	 * deactivate function
	 *
	 * @access public
	 * @return void
	 */
	public function deactivate() {

		if ( function_exists( 'mailster' ) ) {
			if ( mailster_option( 'deliverymethod' ) == 'mailjet' ) {
				mailster_update_option( 'deliverymethod', 'simple' );
				mailster_notice( sprintf( __( 'Change the delivery method on the %s!', 'mailster-mailjet' ), '<a href="edit.php?post_type=newsletter&page=mailster_settings&mailster_remove_notice=delivery_method#delivery">Settings Page</a>' ), '', 360, 'delivery_method' );
			}
		}
	}
}
