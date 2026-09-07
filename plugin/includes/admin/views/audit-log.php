<?php
/**
 * Audit log viewer.
 *
 * @package WPAgent
 *
 * @var array $result
 * @var int   $page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items    = $result['items'] ?? array();
$total    = (int) ( $result['total'] ?? 0 );
$per_page = (int) ( $result['per_page'] ?? 25 );
$pages    = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );

/**
 * @param mixed $state Stored state.
 * @return mixed
 */
$decode_state = static function ( $state ) {
	if ( is_string( $state ) && '' !== $state ) {
		$decoded = json_decode( $state, true );
		return null !== $decoded ? $decoded : $state;
	}
	return $state;
};
?>
<div class="wrap wpagent-wrap wpagent-wrap--audit">
	<h1><?php esc_html_e( 'WPAgent audit log', 'wpagent' ); ?></h1>
	<p class="wpagent-lede">
		<?php esc_html_e( 'Append-only record of write actions. Rows are never edited.', 'wpagent' ); ?>
		<?php
		echo ' ';
		printf(
			/* translators: 1: shown count, 2: total */
			esc_html__( 'Showing %1$d of %2$d entries.', 'wpagent' ),
			count( $items ),
			$total
		);
		?>
	</p>

	<div class="wpagent-table-wrap">
		<table class="wpagent-table wpagent-table--audit">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When (UTC)', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Request', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'User', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Command', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Object', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Result', 'wpagent' ); ?></th>
					<th><?php esc_html_e( 'Details', 'wpagent' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $items ) : ?>
					<tr><td colspan="7" class="wpagent-empty"><?php esc_html_e( 'No audit entries yet.', 'wpagent' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $items as $row ) : ?>
					<?php
					$result_key = sanitize_key( (string) ( $row['result'] ?? '' ) );
					$request_id = (string) ( $row['request_id'] ?? '' );
					$request_short = '' !== $request_id ? substr( $request_id, 0, 8 ) : '';
					?>
					<tr>
						<td class="wpagent-mono"><?php echo esc_html( (string) ( $row['created_at'] ?? '' ) ); ?></td>
						<td class="wpagent-mono" title="<?php echo esc_attr( $request_id ); ?>"><?php echo esc_html( $request_short ); ?></td>
						<td><?php echo esc_html( (string) ( $row['user_login'] ?? '' ) ); ?></td>
						<td class="wpagent-mono"><?php echo esc_html( (string) ( $row['command'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( trim( ( $row['object_type'] ?? '' ) . ' ' . ( $row['object_id'] ?? '' ) ) ); ?></td>
						<td class="wpagent-result<?php echo $result_key ? ' wpagent-result--' . esc_attr( $result_key ) : ''; ?>"><?php echo esc_html( (string) ( $row['result'] ?? '' ) ); ?></td>
						<td>
							<?php if ( ! empty( $row['error_message'] ) ) : ?>
								<?php echo esc_html( (string) $row['error_message'] ); ?>
							<?php else : ?>
								<details class="wpagent-details">
									<summary><?php esc_html_e( 'Before/after', 'wpagent' ); ?></summary>
									<pre class="wpagent-json"><?php echo esc_html( wp_json_encode( array( 'before' => $decode_state( $row['before_state'] ?? null ), 'after' => $decode_state( $row['after_state'] ?? null ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $pages > 1 ) : ?>
		<nav class="wpagent-pager" aria-label="<?php esc_attr_e( 'Audit log pages', 'wpagent' ); ?>">
			<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
				<?php if ( $i === $page ) : ?>
					<span aria-current="page"><?php echo esc_html( (string) $i ); ?></span>
				<?php else : ?>
					<a class="wpagent-btn wpagent-btn--secondary" href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
</div>
