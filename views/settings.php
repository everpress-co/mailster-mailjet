<?php $mailjet_key = mailster_option( 'mailjet_key', md5( uniqid() ) ); ?>
<table class="form-table">
	<?php if ( ! $verified ) : ?>
	<tr valign="top">
		<th scope="row">&nbsp;</th>
		<td><p class="description"><?php printf( __( 'You need a %s account to use this service!', 'mailster-mailjet' ), '<a href="https://www.mailjet.com/" class="external">Mailjet</a>' ); ?></p>
		</td>
	</tr>
	<?php endif; ?>
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Mailjet API key', 'mailster-mailjet' ); ?></th>
		<td><input type="password" name="mailster_options[mailjet_apikey]" value="<?php echo esc_attr( mailster_option( 'mailjet_apikey' ) ); ?>" class="regular-text"></td>
	</tr>
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Mailjet Secret key', 'mailster-mailjet' ); ?></th>
		<td><input type="password" name="mailster_options[mailjet_secret]" value="<?php echo esc_attr( mailster_option( 'mailjet_secret' ) ); ?>" class="regular-text"></td>
	</tr>
	<tr valign="top">
		<th scope="row">&nbsp;</th>
		<td>
			<?php if ( $verified ) : ?>
			<span style="color:#3AB61B">&#10004;</span> <?php esc_html_e( 'Your API Key and Secret are ok!', 'mailster-mailjet' ); ?>
			<?php else : ?>
			<span style="color:#D54E21">&#10006;</span> <?php esc_html_e( 'Your API Key and Secret are WRONG!', 'mailster-mailjet' ); ?>
			<?php endif; ?>

			<input type="hidden" name="mailster_options[mailjet_verified]" value="<?php echo $verified; ?>">
		</td>
	</tr>
</table>
<?php if ( 'mailjet' == mailster_option( 'deliverymethod' ) ) : ?>
<div class="<?php echo ( ! $verified ) ? 'hidden' : ''; ?>">
<table class="form-table">
	<tr valign="top">
		<th scope="row"><?php esc_html_e( 'Bounce Handling', 'mailster-mailjet' ); ?></th>
		<td>
			<?php if ( mailster_is_local() ) : ?>
			<div class="notice error inline"><p><?php esc_html_e( 'Bounce Handling is not available on localhost!', 'mailster-mailjet' ); ?></p></div>
			<?php else : ?>
			<p class="description"><?php esc_html_e( 'Mailster can handle bounces via Mailjet. This is the recommended and most reliable way and requires some setup. During the setup Mailster will create webhooks on Mailjet which get triggered on certain events.', 'mailster-mailjet' ); ?></p>
				<?php $enpoint = add_query_arg( array( 'mailster_mailjet' => $mailjet_key ), home_url( '/' ) ); ?>
				<?php $last_response = get_option( 'mailster_mailjet_last_response' ); ?>
			<p><strong><?php esc_html_e( 'Endpoint', 'mailster-mailjet' ); ?></strong></p>
			<div class="<?php echo $last_response ? 'verified' : 'not-verified'; ?>"><a href="<?php esc_attr_e( $enpoint ); ?>" class="external"><code id="mailjet-endpoint"><?php esc_attr_e( $enpoint ); ?></code></a> <a class="clipboard" data-clipboard-target="#mailjet-endpoint"><?php esc_html_e( 'copy', 'mailster-mailjet' ); ?></a></div>
				<?php if ( $last_response ) : ?>
					<p><strong><?php esc_html_e( 'Last Response', 'mailster-mailjet' ); ?></strong></p>
					<textarea rows="10" cols="40" class="large-text code"><?php print_r( $last_response ); ?></textarea>
				<?php endif; ?>
			<?php endif; ?>

		</td>
	</tr>
</table></div>
<?php else : ?>
<input type="hidden" name="mailster_options[mailjet_domain]" value="<?php echo esc_attr( mailster_option( 'mailjet_domain' ) ); ?>">
<input type="hidden" name="mailster_options[mailjet_track]" value="<?php echo esc_attr( mailster_option( 'mailjet_track' ) ); ?>">
<input type="hidden" name="mailster_options[mailjet_tags]" value="<?php echo esc_attr( mailster_option( 'mailjet_tags' ) ); ?>">
	<?php if ( $verified ) : ?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">&nbsp;</th>
			<td><div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Please save your settings to access further delivery options!', 'mailster-mailjet' ); ?></strong></p></div></td>
		</tr>
	</table>
	<?php endif; ?>
<?php endif; ?>
<input type="hidden" name="mailster_options[mailjet_key]" value="<?php echo esc_attr( $mailjet_key ); ?>">
