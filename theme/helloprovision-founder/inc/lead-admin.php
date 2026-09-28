<?php
/**
 * Lead manager: list, pipeline views, lead record, notes, follow-ups, export, dashboard,
 * privacy tools.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Lead meta shortcut.
 *
 * @param int    $id  Lead.
 * @param string $key Key without prefix.
 */
function hpvf_lm( $id, $key ) {
	return get_post_meta( $id, '_hpvf_' . $key, true );
}

/**
 * Stage pill.
 *
 * @param string $stage Stage slug.
 */
function hpvf_stage_pill( $stage ) {
	$stages = hpvf_lead_stages();
	$s      = $stages[ $stage ] ?? array( $stage, '#ccc' );
	return sprintf( '<span class="hpvf-pill" style="--c:%s">%s</span>', esc_attr( $s[1] ), esc_html( $s[0] ) );
}

/**
 * Whether a new lead has waited longer than the reply promise.
 *
 * @param int $id Lead.
 */
function hpvf_lead_overdue( $id ) {
	if ( 'new' !== hpvf_lm( $id, 'stage' ) ) {
		return false;
	}
	$hours = max( 1, (int) hpvf_opt( 'reply_hours' ) );
	return ( time() - (int) get_post_time( 'U', true, $id ) ) > $hours * HOUR_IN_SECONDS;
}

/* -------------------------------------------------------------------------
 * Menu badge and admin styles
 * ---------------------------------------------------------------------- */

/**
 * Count of unread leads in the menu.
 */
function hpvf_lead_menu_badge() {
	global $menu;
	$unread = count(
		get_posts(
			array(
				'post_type'      => 'hpvf_lead',
				'posts_per_page' => 99,
				'fields'         => 'ids',
				'meta_key'       => '_hpvf_unread', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		)
	);
	if ( ! $unread ) {
		return;
	}
	foreach ( (array) $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=hpvf_lead' === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $unread . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
add_action( 'admin_menu', 'hpvf_lead_menu_badge', 99 );

/**
 * Admin CSS for the lead screens and dashboard widget.
 */
function hpvf_lead_admin_css() {
	$screen = get_current_screen();
	if ( ! $screen || ( 'hpvf_lead' !== $screen->post_type && 'dashboard' !== $screen->id ) ) {
		return;
	}
	?>
	<style>
		.hpvf-pill{display:inline-block;padding:2px 9px;border-radius:99px;background:var(--c);color:#121212;font-weight:600;font-size:12px;line-height:1.6;white-space:nowrap}
		.hpvf-overdue{color:#b32d2e;font-weight:600}
		.hpvf-unread td.title strong a{font-weight:800}
		.wp-list-table tr.hpvf-unread > th.check-column{box-shadow:inset 4px 0 0 #FF3EA5}
		.column-hpvf_stage{width:130px}.column-hpvf_type{width:120px}.column-hpvf_score{width:70px}.column-hpvf_received{width:150px}.column-hpvf_follow{width:120px}
		.hpvf-grid{display:grid;grid-template-columns:140px 1fr;gap:6px 14px;margin:0}
		.hpvf-grid dt{color:#646970;font-weight:600}.hpvf-grid dd{margin:0}
		.hpvf-timeline{list-style:none;margin:0;padding:0;border-left:3px solid #FF3EA5}
		.hpvf-timeline li{margin:0 0 12px;padding-left:12px;position:relative}
		.hpvf-timeline li:before{content:"";position:absolute;left:-7px;top:6px;width:9px;height:9px;border-radius:50%;background:#121212}
		.hpvf-timeline time{display:block;color:#646970;font-size:12px}
		.hpvf-timeline .note{background:#fff8e5;padding:6px 8px;display:block;white-space:pre-wrap}
		.hpvf-bars div{display:grid;grid-template-columns:130px 1fr 40px;gap:8px;align-items:center;margin-bottom:6px}
		.hpvf-bars i{display:block;height:10px;background:#121212}.hpvf-bars .weak i{background:#FF3EA5}
		.hpvf-bars span:first-child{font-weight:600}
		.hpvf-dash-stages{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
		.hpvf-dash-stages a{text-decoration:none}
		.hpvf-dash-list{margin:0}.hpvf-dash-list li{display:flex;justify-content:space-between;gap:8px;border-bottom:1px solid #f0f0f1;padding:6px 0}
	</style>
	<?php
}
add_action( 'admin_head', 'hpvf_lead_admin_css' );

/* -------------------------------------------------------------------------
 * List screen
 * ---------------------------------------------------------------------- */

/**
 * Columns.
 *
 * @param array $cols Columns.
 */
function hpvf_lead_columns( $cols ) {
	return array(
		'cb'           => $cols['cb'],
		'title'        => __( 'Lead', 'hpv' ),
		'hpvf_stage'    => __( 'Stage', 'hpv' ),
		'hpvf_type'     => __( 'Source', 'hpv' ),
		'hpvf_details'  => __( 'Business', 'hpv' ),
		'hpvf_score'    => __( 'Score', 'hpv' ),
		'hpvf_received' => __( 'Received', 'hpv' ),
		'hpvf_follow'   => __( 'Follow up', 'hpv' ),
	);
}
add_filter( 'manage_hpvf_lead_posts_columns', 'hpvf_lead_columns' );

/**
 * Column content.
 *
 * @param string $col Column.
 * @param int    $id  Lead.
 */
function hpvf_lead_column( $col, $id ) {
	switch ( $col ) {
		case 'hpvf_stage':
			echo hpvf_stage_pill( hpvf_lm( $id, 'stage' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			break;
		case 'hpvf_type':
			$labels = array( 'strategy_call' => __( 'Strategy call', 'hpv' ), 'scorecard' => __( 'Scorecard', 'hpv' ) );
			$types  = array_map( static function ( $t ) use ( $labels ) { return $labels[ $t ] ?? $t; }, (array) hpvf_lm( $id, 'types' ) );
			echo esc_html( implode( ' + ', array_filter( $types ) ) );
			break;
		case 'hpvf_details':
			echo esc_html( implode( ' · ', array_filter( array( hpvf_lm( $id, 'industry' ), hpvf_lm( $id, 'city' ), hpvf_lm( $id, 'budget' ), hpvf_lm( $id, 'timing' ) ) ) ) );
			if ( hpvf_lm( $id, 'email' ) ) {
				echo '<br><a href="mailto:' . esc_attr( hpvf_lm( $id, 'email' ) ) . '">' . esc_html( hpvf_lm( $id, 'email' ) ) . '</a>';
			}
			if ( hpvf_lm( $id, 'phone' ) ) {
				echo ' · <a href="' . esc_attr( hpvf_tel( hpvf_lm( $id, 'phone' ) ) ) . '">' . esc_html( hpvf_lm( $id, 'phone' ) ) . '</a>';
			}
			break;
		case 'hpvf_score':
			echo '' !== (string) hpvf_lm( $id, 'score' ) ? (int) hpvf_lm( $id, 'score' ) : '—';
			break;
		case 'hpvf_received':
			/* translators: %s: time ago */
			echo esc_html( sprintf( __( '%s ago', 'hpv' ), human_time_diff( (int) get_post_time( 'U', true, $id ) ) ) );
			if ( hpvf_lead_overdue( $id ) ) {
				echo '<br><span class="hpvf-overdue">' . esc_html__( 'Reply overdue', 'hpv' ) . '</span>';
			}
			break;
		case 'hpvf_follow':
			$d = hpvf_lm( $id, 'follow_up' );
			if ( $d ) {
				$late = strtotime( $d ) < strtotime( 'today' );
				echo '<span class="' . ( $late ? 'hpvf-overdue' : '' ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $d ) ) ) . '</span>';
			} else {
				echo '—';
			}
			break;
	}
}
add_action( 'manage_hpvf_lead_posts_custom_column', 'hpvf_lead_column', 10, 2 );

/**
 * Highlight unread rows.
 *
 * @param array $classes Classes.
 * @param array $class   Extra.
 * @param int   $id      Post.
 */
function hpvf_lead_row_class( $classes, $class, $id ) {
	if ( 'hpvf_lead' === get_post_type( $id ) && hpvf_lm( $id, 'unread' ) ) {
		$classes[] = 'hpvf-unread';
	}
	return $classes;
}
add_filter( 'post_class', 'hpvf_lead_row_class', 10, 3 );

/**
 * Pipeline views: one tab per stage with counts.
 *
 * @param array $views Views.
 */
function hpvf_lead_views( $views ) {
	$current = isset( $_GET['hpvf_stage'] ) ? sanitize_key( wp_unslash( $_GET['hpvf_stage'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$out     = array( 'all' => $views['all'] ?? '' );
	foreach ( hpvf_lead_stages() as $slug => $s ) {
		$count = count(
			get_posts(
				array(
					'post_type'      => 'hpvf_lead',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => '_hpvf_stage', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
		$out[ 'hpvf_' . $slug ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( array( 'post_type' => 'hpvf_lead', 'hpvf_stage' => $slug ), admin_url( 'edit.php' ) ) ),
			$current === $slug ? ' class="current" aria-current="page"' : '',
			esc_html( $s[0] ),
			$count
		);
	}
	if ( isset( $views['trash'] ) ) {
		$out['trash'] = $views['trash'];
	}
	return $out;
}
add_filter( 'views_edit-hpvf_lead', 'hpvf_lead_views' );

/**
 * Source filter dropdown + export button.
 *
 * @param string $post_type Post type.
 */
function hpvf_lead_filters( $post_type ) {
	if ( 'hpvf_lead' !== $post_type ) {
		return;
	}
	$type = isset( $_GET['hpvf_type'] ) ? sanitize_key( wp_unslash( $_GET['hpvf_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<select name="hpvf_type">
		<option value=""><?php esc_html_e( 'All sources', 'hpv' ); ?></option>
		<option value="strategy_call" <?php selected( $type, 'strategy_call' ); ?>><?php esc_html_e( 'Strategy call', 'hpv' ); ?></option>
		<option value="scorecard" <?php selected( $type, 'scorecard' ); ?>><?php esc_html_e( 'Scorecard', 'hpv' ); ?></option>
	</select>
	<?php if ( isset( $_GET['hpvf_stage'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<input type="hidden" name="hpvf_stage" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['hpvf_stage'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
	<?php endif; ?>
	<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hpvf_export_leads' ), 'hpvf_export_leads' ) ); ?>"><?php esc_html_e( 'Export CSV', 'hpv' ); ?></a>
	<?php
}
add_action( 'restrict_manage_posts', 'hpvf_lead_filters' );

/**
 * Apply stage/source filters, search in email/phone, newest first.
 *
 * @param WP_Query $q Query.
 */
function hpvf_lead_query( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'hpvf_lead' !== $q->get( 'post_type' ) ) {
		return;
	}
	$meta = array();
	if ( ! empty( $_GET['hpvf_stage'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$meta[] = array( 'key' => '_hpvf_stage', 'value' => sanitize_key( wp_unslash( $_GET['hpvf_stage'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( ! empty( $_GET['hpvf_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$meta[] = array( 'key' => '_hpvf_types', 'value' => '"' . sanitize_key( wp_unslash( $_GET['hpvf_type'] ) ) . '"', 'compare' => 'LIKE' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( $meta ) {
		$q->set( 'meta_query', $meta );
	}
	if ( ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', 'date' );
		$q->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'hpvf_lead_query' );

/**
 * Search leads by email and phone too.
 *
 * @param string   $search SQL.
 * @param WP_Query $q      Query.
 */
function hpvf_lead_search( $search, $q ) {
	global $wpdb;
	if ( ! is_admin() || ! $q->is_main_query() || 'hpvf_lead' !== $q->get( 'post_type' ) || ! $q->get( 's' ) ) {
		return $search;
	}
	$like = '%' . $wpdb->esc_like( $q->get( 's' ) ) . '%';
	return $wpdb->prepare(
		" AND ( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_hpvf_email','_hpvf_phone','_hpvf_website') AND meta_value LIKE %s ) ) ",
		$like,
		$like
	);
}
add_filter( 'posts_search', 'hpvf_lead_search', 10, 2 );

/**
 * Bulk actions: move to a stage.
 *
 * @param array $actions Actions.
 */
function hpvf_lead_bulk_actions( $actions ) {
	unset( $actions['edit'] );
	foreach ( hpvf_lead_stages() as $slug => $s ) {
		/* translators: %s: stage */
		$actions[ 'hpvf_stage_' . $slug ] = sprintf( __( 'Move to: %s', 'hpv' ), $s[0] );
	}
	return $actions;
}
add_filter( 'bulk_actions-edit-hpvf_lead', 'hpvf_lead_bulk_actions' );

/**
 * Handle bulk stage moves.
 *
 * @param string $redirect Redirect URL.
 * @param string $action   Action.
 * @param int[]  $ids      Leads.
 */
function hpvf_lead_handle_bulk( $redirect, $action, $ids ) {
	if ( 0 !== strpos( $action, 'hpvf_stage_' ) ) {
		return $redirect;
	}
	$stage = substr( $action, 10 );
	foreach ( $ids as $id ) {
		if ( current_user_can( 'edit_post', $id ) ) {
			hpvf_set_stage( (int) $id, $stage );
		}
	}
	return add_query_arg( 'hpvf_moved', count( $ids ), $redirect );
}
add_filter( 'handle_bulk_actions-edit-hpvf_lead', 'hpvf_lead_handle_bulk', 10, 3 );

/**
 * Row actions: quick email / call.
 *
 * @param array   $actions Actions.
 * @param WP_Post $post    Lead.
 */
function hpvf_lead_row_actions( $actions, $post ) {
	if ( 'hpvf_lead' !== $post->post_type ) {
		return $actions;
	}
	unset( $actions['inline hide-if-no-js'], $actions['view'] );
	if ( hpvf_lm( $post->ID, 'email' ) ) {
		$actions['email'] = '<a href="mailto:' . esc_attr( hpvf_lm( $post->ID, 'email' ) ) . '">' . esc_html__( 'Email', 'hpv' ) . '</a>';
	}
	if ( hpvf_lm( $post->ID, 'phone' ) ) {
		$actions['call'] = '<a href="' . esc_attr( hpvf_tel( hpvf_lm( $post->ID, 'phone' ) ) ) . '">' . esc_html__( 'Call', 'hpv' ) . '</a>';
	}
	return $actions;
}
add_filter( 'post_row_actions', 'hpvf_lead_row_actions', 10, 2 );

/* -------------------------------------------------------------------------
 * Lead record screen
 * ---------------------------------------------------------------------- */

/**
 * Meta boxes.
 */
function hpvf_lead_meta_boxes() {
	remove_meta_box( 'slugdiv', 'hpvf_lead', 'normal' );
	add_meta_box( 'hpvf_lead_contact', __( 'Contact & request', 'hpv' ), 'hpvf_lead_box_contact', 'hpvf_lead', 'normal', 'high' );
	add_meta_box( 'hpvf_lead_scorecard', __( 'Scorecard', 'hpv' ), 'hpvf_lead_box_scorecard', 'hpvf_lead', 'normal', 'default' );
	add_meta_box( 'hpvf_lead_activity', __( 'Notes & activity', 'hpv' ), 'hpvf_lead_box_activity', 'hpvf_lead', 'normal', 'default' );
	add_meta_box( 'hpvf_lead_pipeline', __( 'Pipeline', 'hpv' ), 'hpvf_lead_box_pipeline', 'hpvf_lead', 'side', 'high' );
}
add_action( 'add_meta_boxes_hpvf_lead', 'hpvf_lead_meta_boxes' );

/**
 * Opening a lead marks it read.
 */
function hpvf_lead_mark_read() {
	$screen = get_current_screen();
	if ( $screen && 'hpvf_lead' === $screen->post_type && 'post' === $screen->base && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		delete_post_meta( (int) $_GET['post'], '_hpvf_unread' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}
add_action( 'current_screen', 'hpvf_lead_mark_read' );

/**
 * Contact & request box.
 *
 * @param WP_Post $post Lead.
 */
function hpvf_lead_box_contact( $post ) {
	$id   = $post->ID;
	$rows = array(
		__( 'Name', 'hpv' )      => esc_html( hpvf_lm( $id, 'name' ) ),
		__( 'Email', 'hpv' )     => hpvf_lm( $id, 'email' ) ? '<a href="mailto:' . esc_attr( hpvf_lm( $id, 'email' ) ) . '">' . esc_html( hpvf_lm( $id, 'email' ) ) . '</a>' : '',
		__( 'Phone', 'hpv' )     => hpvf_lm( $id, 'phone' ) ? '<a href="' . esc_attr( hpvf_tel( hpvf_lm( $id, 'phone' ) ) ) . '">' . esc_html( hpvf_lm( $id, 'phone' ) ) . '</a>' : '',
		__( 'Website', 'hpv' )   => hpvf_lm( $id, 'website' ) ? '<a href="' . esc_url( hpvf_lm( $id, 'website' ) ) . '" target="_blank" rel="noopener">' . esc_html( hpvf_lm( $id, 'website' ) ) . '</a>' : '',
		__( 'Industry', 'hpv' )  => esc_html( hpvf_lm( $id, 'industry' ) ),
		__( 'City', 'hpv' )      => esc_html( hpvf_lm( $id, 'city' ) ),
		__( 'Challenge', 'hpv' ) => esc_html( hpvf_lm( $id, 'challenge' ) ),
		__( 'Budget', 'hpv' )    => esc_html( hpvf_lm( $id, 'budget' ) ),
		__( 'Timing', 'hpv' )    => esc_html( hpvf_lm( $id, 'timing' ) ),
		__( 'Came from', 'hpv' ) => hpvf_lm( $id, 'source_page' ) ? '<a href="' . esc_url( hpvf_lm( $id, 'source_page' ) ) . '">' . esc_html( wp_parse_url( hpvf_lm( $id, 'source_page' ), PHP_URL_PATH ) ) . '</a>' : '',
	);
	$utm = json_decode( (string) hpvf_lm( $id, 'utm' ), true );
	if ( $utm ) {
		$rows[ __( 'Campaign', 'hpv' ) ] = esc_html( implode( ' · ', array_map( static function ( $k, $v ) { return $k . ': ' . $v; }, array_keys( $utm ), $utm ) ) );
	}
	if ( hpvf_lead_overdue( $id ) ) {
		echo '<p class="hpvf-overdue">' . esc_html__( 'This lead is waiting longer than your reply promise.', 'hpv' ) . '</p>';
	}
	echo '<dl class="hpvf-grid">';
	foreach ( $rows as $label => $value ) {
		if ( '' !== $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
	}
	echo '</dl>';
}

/**
 * Scorecard box.
 *
 * @param WP_Post $post Lead.
 */
function hpvf_lead_box_scorecard( $post ) {
	$areas  = hpvf_scorecard_areas();
	$scores = json_decode( (string) hpvf_lm( $post->ID, 'areas' ), true );
	if ( ! $scores ) {
		echo '<p>' . esc_html__( 'This lead hasn’t taken the scorecard.', 'hpv' ) . '</p>';
		return;
	}
	$weak = hpvf_lm( $post->ID, 'bottleneck' );
	/* translators: %d: score */
	echo '<p><strong>' . esc_html( sprintf( __( 'Overall: %d/100', 'hpv' ), (int) hpvf_lm( $post->ID, 'score' ) ) ) . '</strong></p><div class="hpvf-bars">';
	foreach ( $scores as $k => $v ) {
		printf( '<div class="%s"><span>%s</span><span style="background:#f0f0f1"><i style="width:%d%%"></i></span><span>%d</span></div>', $k === $weak ? 'weak' : '', esc_html( $areas[ $k ][0] ?? $k ), (int) $v, (int) $v );
	}
	echo '</div>';
	if ( isset( $areas[ $weak ] ) ) {
		/* translators: %s: area */
		echo '<p>' . esc_html( sprintf( __( 'Talking point for the call: %s', 'hpv' ), $areas[ $weak ][1] ) ) . '</p>';
	}
}

/**
 * Pipeline box (stage, value, follow-up).
 *
 * @param WP_Post $post Lead.
 */
function hpvf_lead_box_pipeline( $post ) {
	wp_nonce_field( 'hpvf_lead_save', 'hpvf_lead_nonce' );
	$stage = hpvf_lm( $post->ID, 'stage' );
	?>
	<p><label for="hpvf_stage"><strong><?php esc_html_e( 'Stage', 'hpv' ); ?></strong></label><br>
	<select name="hpvf_stage" id="hpvf_stage" style="width:100%">
		<?php foreach ( hpvf_lead_stages() as $slug => $s ) : ?>
			<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $stage, $slug ); ?>><?php echo esc_html( $s[0] ); ?></option>
		<?php endforeach; ?>
	</select></p>
	<p><label for="hpvf_value"><strong><?php esc_html_e( 'Deal value (USD)', 'hpv' ); ?></strong></label><br>
	<input type="number" min="0" step="100" name="hpvf_value" id="hpvf_value" value="<?php echo esc_attr( hpvf_lm( $post->ID, 'value' ) ); ?>" style="width:100%"></p>
	<p><label for="hpvf_follow_up"><strong><?php esc_html_e( 'Next follow-up', 'hpv' ); ?></strong></label><br>
	<input type="date" name="hpvf_follow_up" id="hpvf_follow_up" value="<?php echo esc_attr( hpvf_lm( $post->ID, 'follow_up' ) ); ?>" style="width:100%"></p>
	<p><label for="hpvf_lost_reason"><strong><?php esc_html_e( 'If lost: why?', 'hpv' ); ?></strong></label><br>
	<input type="text" name="hpvf_lost_reason" id="hpvf_lost_reason" value="<?php echo esc_attr( hpvf_lm( $post->ID, 'lost_reason' ) ); ?>" style="width:100%"></p>
	<p class="description"><?php esc_html_e( 'Moving a lead to Qualified or Won is reported to GA4 when the Measurement Protocol secret is set.', 'hpv' ); ?></p>
	<?php
}

/**
 * Notes & activity box.
 *
 * @param WP_Post $post Lead.
 */
function hpvf_lead_box_activity( $post ) {
	?>
	<p><label for="hpvf_note" class="screen-reader-text"><?php esc_html_e( 'Add a note', 'hpv' ); ?></label>
	<textarea name="hpvf_note" id="hpvf_note" rows="3" style="width:100%" placeholder="<?php esc_attr_e( 'Add a note: call summary, next step, objections…', 'hpv' ); ?>"></textarea></p>
	<p><button class="button button-primary" name="save" value="note"><?php esc_html_e( 'Save note', 'hpv' ); ?></button></p>
	<?php
	$log = get_post_meta( $post->ID, '_hpvf_activity', true );
	if ( ! is_array( $log ) || ! $log ) {
		return;
	}
	echo '<ul class="hpvf-timeline">';
	foreach ( array_reverse( $log ) as $e ) {
		$who = ! empty( $e['user'] ) ? get_the_author_meta( 'display_name', (int) $e['user'] ) : __( 'Website', 'hpv' );
		printf(
			'<li><time>%s · %s</time><span class="%s">%s</span></li>',
			esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $e['t'] ) ),
			esc_html( $who ),
			'note' === $e['type'] ? 'note' : '',
			esc_html( $e['text'] )
		);
	}
	echo '</ul>';
}

/**
 * Save pipeline fields and notes.
 *
 * @param int $post_id Lead.
 */
function hpvf_lead_save( $post_id ) {
	if ( ! isset( $_POST['hpvf_lead_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['hpvf_lead_nonce'] ) ), 'hpvf_lead_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['hpvf_stage'] ) ) {
		hpvf_set_stage( $post_id, sanitize_key( wp_unslash( $_POST['hpvf_stage'] ) ) );
	}
	foreach ( array( 'value' => 'absint', 'follow_up' => 'sanitize_text_field', 'lost_reason' => 'sanitize_text_field' ) as $k => $clean ) {
		if ( isset( $_POST[ 'hpvf_' . $k ] ) ) {
			$v   = call_user_func( $clean, wp_unslash( $_POST[ 'hpvf_' . $k ] ) );
			$old = hpvf_lm( $post_id, $k );
			if ( (string) $v !== (string) $old ) {
				update_post_meta( $post_id, '_hpvf_' . $k, $v );
				if ( 'follow_up' === $k && $v ) {
					/* translators: %s: date */
					hpvf_add_activity( $post_id, 'stage', sprintf( __( 'Follow-up set for %s', 'hpv' ), date_i18n( get_option( 'date_format' ), strtotime( $v ) ) ) );
				}
			}
		}
	}
	$note = isset( $_POST['hpvf_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hpvf_note'] ) ) : '';
	if ( $note ) {
		hpvf_add_activity( $post_id, 'note', $note );
	}
	delete_post_meta( $post_id, '_hpvf_unread' );
}
add_action( 'save_post_hpvf_lead', 'hpvf_lead_save' );

/* -------------------------------------------------------------------------
 * Export
 * ---------------------------------------------------------------------- */

/**
 * CSV export of every lead.
 */
function hpvf_export_leads() {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'hpvf_export_leads' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'hpv' ) );
	}
	$cols = array( 'name', 'email', 'phone', 'website', 'stage', 'types', 'industry', 'city', 'challenge', 'budget', 'timing', 'score', 'bottleneck', 'value', 'follow_up', 'lost_reason', 'source_page', 'utm' );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=leads-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array_merge( array( 'received' ), $cols, array( 'last_note' ) ) );
	$ids = get_posts( array( 'post_type' => 'hpvf_lead', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		$row = array( get_post_time( 'Y-m-d H:i', false, $id ) );
		foreach ( $cols as $c ) {
			$v = hpvf_lm( $id, $c );
			$v = is_array( $v ) ? implode( '+', $v ) : (string) $v;
			// Neutralise spreadsheet formulas.
			$row[] = preg_match( '/^[=+\-@]/', $v ) ? "'" . $v : $v;
		}
		$notes = array_filter( (array) get_post_meta( $id, '_hpvf_activity', true ), static function ( $e ) { return 'note' === ( $e['type'] ?? '' ); } );
		$last  = end( $notes );
		$row[] = $last ? $last['text'] : '';
		fputcsv( $out, $row );
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_hpvf_export_leads', 'hpvf_export_leads' );

/* -------------------------------------------------------------------------
 * Dashboard widget
 * ---------------------------------------------------------------------- */

/**
 * Register the widget.
 */
function hpvf_lead_dashboard() {
	if ( current_user_can( 'edit_posts' ) ) {
		wp_add_dashboard_widget( 'hpvf_leads', __( 'Leads pipeline', 'hpv' ), 'hpvf_lead_dashboard_render' );
	}
}
add_action( 'wp_dashboard_setup', 'hpvf_lead_dashboard' );

/**
 * Widget content.
 */
function hpvf_lead_dashboard_render() {
	echo '<div class="hpvf-dash-stages">';
	foreach ( hpvf_lead_stages() as $slug => $s ) {
		$n = count( get_posts( array( 'post_type' => 'hpvf_lead', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_hpvf_stage', 'meta_value' => $slug ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		printf( '<a href="%s">%s</a>', esc_url( admin_url( 'edit.php?post_type=hpvf_lead&hpvf_stage=' . $slug ) ), hpvf_stage_pill( $slug ) . ' <strong>' . (int) $n . '</strong>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
	}
	echo '</div>';

	$recent = get_posts( array( 'post_type' => 'hpvf_lead', 'numberposts' => 6 ) );
	if ( ! $recent ) {
		echo '<p>' . esc_html__( 'No leads yet. They appear here when someone books a call or completes the scorecard.', 'hpv' ) . '</p>';
		return;
	}
	echo '<ul class="hpvf-dash-list">';
	foreach ( $recent as $p ) {
		printf(
			'<li><a href="%s">%s</a><span>%s%s</span></li>',
			esc_url( get_edit_post_link( $p ) ),
			esc_html( get_the_title( $p ) ),
			hpvf_stage_pill( hpvf_lm( $p->ID, 'stage' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			hpvf_lead_overdue( $p->ID ) ? ' <span class="hpvf-overdue">' . esc_html__( 'overdue', 'hpv' ) . '</span>' : ''
		);
	}
	echo '</ul><p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=hpvf_lead' ) ) . '">' . esc_html__( 'Open lead manager', 'hpv' ) . '</a></p>';
}

/* -------------------------------------------------------------------------
 * Privacy (Tools → Export / Erase Personal Data)
 * ---------------------------------------------------------------------- */

/**
 * Register exporter and eraser.
 *
 * @param array $list Registered handlers.
 */
function hpvf_privacy_exporters( $list ) {
	$list['hpvf-leads'] = array( 'exporter_friendly_name' => __( 'Leads', 'hpv' ), 'callback' => 'hpvf_privacy_export' );
	return $list;
}
add_filter( 'wp_privacy_personal_data_exporters', 'hpvf_privacy_exporters' );

/**
 * Register eraser.
 *
 * @param array $list Registered handlers.
 */
function hpvf_privacy_erasers( $list ) {
	$list['hpvf-leads'] = array( 'eraser_friendly_name' => __( 'Leads', 'hpv' ), 'callback' => 'hpvf_privacy_erase' );
	return $list;
}
add_filter( 'wp_privacy_personal_data_erasers', 'hpvf_privacy_erasers' );

/**
 * Leads for an email.
 *
 * @param string $email Email.
 */
function hpvf_leads_by_email( $email ) {
	return get_posts( array( 'post_type' => 'hpvf_lead', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_hpvf_email', 'meta_value' => strtolower( $email ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
}

/**
 * Export.
 *
 * @param string $email Email.
 */
function hpvf_privacy_export( $email ) {
	$items = array();
	foreach ( hpvf_leads_by_email( $email ) as $id ) {
		$data = array();
		foreach ( array( 'name', 'email', 'phone', 'website', 'industry', 'city', 'challenge', 'budget', 'timing', 'score' ) as $k ) {
			if ( '' !== (string) hpvf_lm( $id, $k ) ) {
				$data[] = array( 'name' => ucfirst( $k ), 'value' => (string) hpvf_lm( $id, $k ) );
			}
		}
		$items[] = array( 'group_id' => 'hpvf-leads', 'group_label' => __( 'Inquiries', 'hpv' ), 'item_id' => 'lead-' . $id, 'data' => $data );
	}
	return array( 'data' => $items, 'done' => true );
}

/**
 * Erase.
 *
 * @param string $email Email.
 */
function hpvf_privacy_erase( $email ) {
	$ids = hpvf_leads_by_email( $email );
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	return array( 'items_removed' => count( $ids ), 'items_retained' => false, 'messages' => array(), 'done' => true );
}
