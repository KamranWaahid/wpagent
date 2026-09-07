<?php
/**
 * Settings / connection view.
 *
 * @package WPAgent
 *
 * @var bool         $available
 * @var array        $stats
 * @var string[]     $disabled
 * @var bool         $insecure
 * @var bool         $delete_data
 * @var string       $remote_url
 * @var array        $option_extras
 * @var string|false $new_secret
 * @var string|false $config
 * @var array        $passwords
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

settings_errors( 'wpagent' );
$auth_url     = WPAgent_Admin::authorization_url();
$just_created = is_string( $new_secret ) && '' !== $new_secret;
?>
<div class="wrap wpagent-wrap">
	<h1><?php esc_html_e( 'WPAgent', 'wpagent' ); ?></h1>
	<p class="wpagent-lede"><?php esc_html_e( 'Connect an MCP-compatible AI client (Claude Desktop, Cursor, ChatGPT) to this WordPress site. Every write is capability-checked and audit-logged.', 'wpagent' ); ?></p>

	<div class="wpagent-strip" role="status">
		<span class="wpagent-strip__item"><?php echo esc_html( sprintf( /* translators: %s plugin version */ __( 'Plugin v%s', 'wpagent' ), WPAGENT_VERSION ) ); ?></span>
		<span class="wpagent-strip__item"><code><?php echo esc_html( WPAGENT_REST_NAMESPACE ); ?></code></span>
		<span class="wpagent-strip__item">
			<?php if ( $available ) : ?>
				<span class="wpagent-ok"><?php esc_html_e( 'App passwords available', 'wpagent' ); ?></span>
			<?php else : ?>
				<span class="wpagent-dim"><?php esc_html_e( 'App passwords unavailable (HTTPS required unless local HTTP is enabled)', 'wpagent' ); ?></span>
			<?php endif; ?>
		</span>
		<span class="wpagent-strip__item">
			<?php
			printf(
				/* translators: %s write action count */
				esc_html__( 'Writes %s', 'wpagent' ),
				esc_html( (string) ( $stats['write_count'] ?? 0 ) )
			);
			?>
		</span>
		<span class="wpagent-strip__item">
			<?php
			printf(
				/* translators: %s denied action count */
				esc_html__( 'Denied %s', 'wpagent' ),
				esc_html( (string) ( $stats['denied_count'] ?? 0 ) )
			);
			?>
		</span>
	</div>

	<section class="wpagent-section" aria-labelledby="wpagent-authorize-title">
		<h2 id="wpagent-authorize-title" class="wpagent-section__title"><?php esc_html_e( 'Authorize AI connection', 'wpagent' ); ?></h2>
		<p class="wpagent-section__help"><?php esc_html_e( 'Creates a WordPress Application Password for your user. The password is shown once and is never stored by this plugin.', 'wpagent' ); ?></p>

		<?php if ( $just_created ) : ?>
			<div class="wpagent-secret">
				<div class="wpagent-copy-row">
					<label for="wpagent-app-password"><?php esc_html_e( 'Application password (shown once)', 'wpagent' ); ?></label>
					<input id="wpagent-app-password" type="text" readonly value="<?php echo esc_attr( $new_secret ); ?>" />
					<button type="button" class="wpagent-btn wpagent-btn--primary" data-wpagent-copy="wpagent-app-password">
						<?php esc_html_e( 'Copy password', 'wpagent' ); ?>
					</button>
				</div>
				<?php if ( is_string( $config ) ) : ?>
					<div class="wpagent-copy-row">
						<label for="wpagent-mcp-snippet"><?php esc_html_e( 'MCP snippet', 'wpagent' ); ?></label>
						<textarea id="wpagent-mcp-snippet" readonly rows="14"><?php echo esc_textarea( $config ); ?></textarea>
						<button type="button" class="wpagent-btn wpagent-btn--secondary" data-wpagent-copy="wpagent-mcp-snippet">
							<?php esc_html_e( 'Copy snippet', 'wpagent' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<form method="post" class="wpagent-actions" data-wpagent-generate-form>
				<?php wp_nonce_field( 'wpagent_admin' ); ?>
				<input type="hidden" name="wpagent_action" value="create_app_password" />
				<button type="submit" class="wpagent-btn wpagent-btn--primary" data-wpagent-generate>
					<?php esc_html_e( 'Generate Application Password', 'wpagent' ); ?>
				</button>
				<a class="wpagent-text-link" href="<?php echo esc_url( $auth_url ); ?>">
					<?php esc_html_e( 'Open WordPress authorize screen', 'wpagent' ); ?>
				</a>
			</form>
		<?php endif; ?>

		<?php if ( $passwords ) : ?>
			<div class="wpagent-footnote">
				<ul class="wpagent-passwords">
					<?php foreach ( $passwords as $item ) : ?>
						<li>
							<strong><?php echo esc_html( $item['name'] ?? 'unnamed' ); ?></strong>
							<span class="wpagent-dim"><?php echo esc_html( isset( $item['last_used'] ) && $item['last_used'] ? (string) $item['last_used'] : __( 'never used', 'wpagent' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Revoke all WPAgent MCP passwords for your user?', 'wpagent' ) ); ?>');">
					<?php wp_nonce_field( 'wpagent_admin' ); ?>
					<input type="hidden" name="wpagent_action" value="revoke_app_passwords" />
					<button type="submit" class="wpagent-btn wpagent-btn--hazard">
						<?php esc_html_e( 'Revoke WPAgent passwords', 'wpagent' ); ?>
					</button>
				</form>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( ! $just_created ) : ?>
	<form method="post" class="wpagent-recessed">
		<?php wp_nonce_field( 'wpagent_admin' ); ?>
		<input type="hidden" name="wpagent_action" value="link_remote_session" />
		<h2 class="wpagent-section__title"><?php esc_html_e( 'Link to hosted WPAgent (optional)', 'wpagent' ); ?></h2>
		<p class="wpagent-section__help"><?php esc_html_e( 'Only needed if the MCP server runs WPAGENT_HTTP_MODE=hosted for other people. For your own ChatGPT, use a tunnel (docs/chatgpt.md) and the same Application Password as Cursor — skip this form.', 'wpagent' ); ?></p>
		<div class="wpagent-fields">
			<div class="wpagent-field">
				<label for="remote_mcp_url"><?php esc_html_e( 'MCP server URL', 'wpagent' ); ?></label>
				<input type="url" id="remote_mcp_url" name="remote_mcp_url" placeholder="https://mcp.example.com" value="<?php echo esc_attr( $remote_url ); ?>" />
			</div>
			<div class="wpagent-field">
				<label for="pairing_code"><?php esc_html_e( 'Pairing code', 'wpagent' ); ?></label>
				<input type="text" id="pairing_code" name="pairing_code" placeholder="ABCD-EF01" autocomplete="off" />
			</div>
		</div>
		<button type="submit" class="wpagent-btn wpagent-btn--secondary">
			<?php esc_html_e( 'Authorize hosted connection', 'wpagent' ); ?>
		</button>
	</form>
	<?php endif; ?>

	<form method="post" class="wpagent-section">
		<?php wp_nonce_field( 'wpagent_admin' ); ?>
		<input type="hidden" name="wpagent_action" value="save_settings" />
		<h2 class="wpagent-section__title"><?php esc_html_e( 'Safety settings', 'wpagent' ); ?></h2>
		<div class="wpagent-checks">
			<label class="wpagent-check">
				<input type="checkbox" name="allow_insecure" value="1" <?php checked( $insecure ); ?> />
				<span><?php esc_html_e( 'Allow Application Passwords over HTTP (local sites only)', 'wpagent' ); ?></span>
			</label>
			<label class="wpagent-check">
				<input type="checkbox" name="delete_data" value="1" <?php checked( $delete_data ); ?> />
				<span><?php esc_html_e( 'Delete audit log and settings when the plugin is uninstalled', 'wpagent' ); ?></span>
			</label>
		</div>

		<h3 class="wpagent-section__title"><?php esc_html_e( 'Command allowlist', 'wpagent' ); ?></h3>
		<p class="wpagent-section__help"><?php esc_html_e( 'Default-deny: only these commands exist. Uncheck a command to disable it. You cannot add commands that are not shipped with WPAgent.', 'wpagent' ); ?></p>
		<div class="wpagent-table-wrap">
			<table class="wpagent-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Enabled', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Command', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Capability', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Write', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Description', 'wpagent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WPAgent_Allowlist::definitions() as $name => $def ) : ?>
						<tr>
							<td>
								<input type="checkbox"
									class="wpagent-toggle-command"
									data-command="<?php echo esc_attr( $name ); ?>"
									<?php checked( ! in_array( $name, $disabled, true ) ); ?> />
							</td>
							<td><code><?php echo esc_html( $name ); ?></code></td>
							<td><code><?php echo esc_html( $def['capability'] ); ?></code></td>
							<td><?php echo ! empty( $def['write'] ) ? esc_html__( 'Yes', 'wpagent' ) : esc_html__( 'No', 'wpagent' ); ?></td>
							<td><?php echo esc_html( $def['description'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div id="wpagent-disabled-fields">
			<?php foreach ( $disabled as $cmd ) : ?>
				<input type="hidden" name="disabled_commands[]" value="<?php echo esc_attr( $cmd ); ?>" />
			<?php endforeach; ?>
		</div>

		<h3 class="wpagent-section__title"><?php esc_html_e( 'Options allowlist', 'wpagent' ); ?></h3>
		<p class="wpagent-section__help"><?php esc_html_e( 'Built-in keys are always present. You may add extra option keys; forbidden keys (active_plugins, cron, siteurl writes, secrets, …) are rejected.', 'wpagent' ); ?></p>
		<div class="wpagent-table-wrap">
			<table class="wpagent-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Key', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Writable', 'wpagent' ); ?></th>
						<th><?php esc_html_e( 'Description', 'wpagent' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'wpagent' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WPAgent_Options_Allowlist::builtin() as $key => $meta ) : ?>
						<tr class="wpagent-row-record">
							<td><code><?php echo esc_html( $key ); ?></code> <span class="wpagent-mark"><?php esc_html_e( 'built-in', 'wpagent' ); ?></span></td>
							<td><?php echo ! empty( $meta['writable'] ) ? esc_html__( 'Yes', 'wpagent' ) : esc_html__( 'No', 'wpagent' ); ?></td>
							<td><?php echo esc_html( $meta['description'] ); ?></td>
							<td></td>
						</tr>
					<?php endforeach; ?>
					<?php
					$i = 0;
					foreach ( $option_extras as $key => $meta ) :
						?>
						<tr class="wpagent-row-extra">
							<td>
								<input type="text" name="option_keys[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo esc_attr( $key ); ?>" />
							</td>
							<td>
								<input type="checkbox" name="option_writable[<?php echo esc_attr( (string) $i ); ?>]" value="1" <?php checked( ! empty( $meta['writable'] ) ); ?> />
							</td>
							<td>
								<input type="text" class="wpagent-input-ui" name="option_descriptions[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo esc_attr( $meta['description'] ); ?>" />
							</td>
							<td>
								<button type="button" class="wpagent-btn wpagent-btn--hazard wpagent-remove-option">
									<?php esc_html_e( 'Remove', 'wpagent' ); ?>
								</button>
							</td>
						</tr>
						<?php
						++$i;
					endforeach;
					?>
					<tr class="wpagent-row-add">
						<td><input type="text" name="new_option_key" placeholder="custom_option_key" /></td>
						<td><input type="checkbox" name="new_option_writable" value="1" /></td>
						<td><input type="text" class="wpagent-input-ui" name="new_option_description" placeholder="<?php esc_attr_e( 'What this option is', 'wpagent' ); ?>" /></td>
						<td><span class="wpagent-dim"><?php esc_html_e( 'Add on save', 'wpagent' ); ?></span></td>
					</tr>
				</tbody>
			</table>
		</div>
		<button type="submit" class="wpagent-btn wpagent-btn--primary">
			<?php esc_html_e( 'Save settings', 'wpagent' ); ?>
		</button>
	</form>
</div>
