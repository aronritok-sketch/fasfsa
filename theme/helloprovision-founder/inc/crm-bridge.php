<?php
/**
 * Leads → HelloProVision CRM.
 *
 * Every new lead (strategy call, Growth Scorecard) is forwarded to the HelloProVision CRM after the
 * visitor already has their answer (on the WordPress `shutdown` hook, after fastcgi_finish_request()).
 *
 * 1. Preferred: the HelloProVision "Leads → CRM" mu-plugin is active on this WordPress
 *    (installed by the HelloProVision setup wizard in "site" mode). The lead goes through
 *    hpv_leads_map() + hpv_leads_send(), exactly like the other website forms, so queueing,
 *    hourly retry, the 3-day e-mail alert, signing and the hpv_attr attribution cookie are handled there.
 * 2. Fallback without the mu-plugin: the same signed request the mu-plugin makes
 *    (POST {HPV_CRM_URL}/wp-json/hpv/v1/bridge/lead, X-HPV-Timestamp + X-HPV-Signature =
 *    HMAC-SHA256( "timestamp.body", HPV_BRIDGE_SECRET )), or a direct hpv_leads_ingest() call when the
 *    CRM runs on this WordPress. Failures are retried hourly by wp-cron for 3 days.
 * 3. Neither configured: nothing happens; the lead stays in the theme's lead manager only.
 *
 * Lead meta: _hpvf_crm_sent (Unix time of the last successful send), _hpvf_crm_status
 * (sent | queued | retry | failed | skipped), _hpvf_crm_error.
 *
 * HPV_CRM_URL, HPV_BRIDGE_SECRET and the hpv_leads_* functions belong to the HelloProVision system and are
 * intentionally NOT renamed to the theme's hpvf_ prefix.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

const HPVF_CRM_MAX_AGE = 3 * DAY_IN_SECONDS; // Same as the mu-plugin's HPV_LEADS_MAX_AGE.

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

/**
 * How leads reach the CRM on this site.
 *
 * @return string 'leads-plugin' | 'direct' (CRM on this WordPress) | 'signed' (HPV_CRM_URL + secret) | ''.
 */
function hpvf_crm_mode() {
	if ( function_exists( 'hpv_leads_send' ) && function_exists( 'hpv_leads_map' ) ) {
		return 'leads-plugin';
	}
	if ( function_exists( 'hpv_leads_ingest' ) && apply_filters( 'hpv_leads_direct', true ) ) {
		return 'direct';
	}
	if ( defined( 'HPV_CRM_URL' ) && '' !== trim( (string) HPV_CRM_URL ) && defined( 'HPV_BRIDGE_SECRET' ) && strlen( (string) HPV_BRIDGE_SECRET ) >= 16 ) {
		return 'signed';
	}
	return '';
}

/**
 * Whether a lead would actually be delivered (the mu-plugin also needs its keys).
 */
function hpvf_crm_configured() {
	$mode = hpvf_crm_mode();
	if ( 'leads-plugin' === $mode ) {
		return ! function_exists( 'hpv_leads_crm_configured' ) || hpv_leads_crm_configured();
	}
	return '' !== $mode;
}

/* -------------------------------------------------------------------------
 * Mapping
 * ---------------------------------------------------------------------- */

/**
 * Form label shown in the CRM ("Kapcsolati űrlap (…)").
 *
 * @param string $type strategy_call | scorecard.
 */
function hpvf_crm_form_label( $type ) {
	$labels = array(
		'strategy_call' => 'Áron – stratégiai hívás',
		'scorecard'     => 'Áron – Growth Scorecard',
	);
	return (string) apply_filters( 'hpvf_crm_form_label', $labels[ $type ] ?? 'Áron – ' . $type, $type );
}

/**
 * This submission's UTM / referrer data (from the form, not the merged lead).
 *
 * @param int   $lead_id Lead.
 * @param array $data    Raw submission.
 * @return array
 */
function hpvf_crm_utm( $lead_id, $data ) {
	$utm = $data['utm'] ?? '';
	$utm = is_array( $utm ) ? $utm : json_decode( (string) $utm, true );
	if ( ! is_array( $utm ) ) {
		$utm = array();
	}
	$out = array();
	foreach ( array_slice( $utm, 0, 8, true ) as $k => $v ) {
		if ( is_scalar( $v ) && '' !== (string) $v ) {
			$out[ sanitize_key( (string) $k ) ] = mb_substr( sanitize_text_field( (string) $v ), 0, 200 );
		}
	}
	return $out;
}

/**
 * Attribution in the CRM's format ({ first, last } touches) from the theme's own UTM capture.
 * Used when the mu-plugin's hpv_attr cookie is not there.
 *
 * @param array  $utm  hpvf_crm_utm().
 * @param string $page Source page URL.
 */
function hpvf_crm_attribution_from_utm( $utm, $page ) {
	$touch = array();
	foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid' ) as $k ) {
		if ( ! empty( $utm[ $k ] ) ) {
			$touch[ $k ] = $utm[ $k ];
		}
	}
	if ( ! empty( $utm['referrer'] ) ) {
		$touch['ref'] = mb_substr( $utm['referrer'], 0, 200 );
	}
	if ( ! $touch ) {
		return array();
	}
	$path = $page ? (string) wp_parse_url( $page, PHP_URL_PATH ) : '';
	if ( '' !== $path ) {
		$touch['land'] = mb_substr( $path, 0, 150 );
	}
	$touch['at'] = time();
	return array( 'first' => $touch, 'last' => $touch );
}

/**
 * Form values ("question" => answer) for hpv_leads_map(). The keys are chosen so the mu-plugin
 * recognises email, name, phone, website and message, and lists everything else as "question: answer".
 *
 * @param int    $lead_id Lead.
 * @param string $type    strategy_call | scorecard.
 * @param array  $data    Raw submission.
 * @return array
 */
function hpvf_crm_values( $lead_id, $type, $data ) {
	$m      = static function ( $k ) use ( $lead_id ) {
		return trim( (string) get_post_meta( $lead_id, '_hpvf_' . $k, true ) );
	};
	$values = array(
		'name'    => $m( 'name' ),
		'email'   => $m( 'email' ),
		'phone'   => $m( 'phone' ),
		'website' => $m( 'website' ),
	);
	if ( '' !== trim( (string) ( $data['company'] ?? '' ) ) ) {
		$values['company'] = mb_substr( sanitize_text_field( (string) $data['company'] ), 0, 120 );
	}

	if ( 'scorecard' === $type ) {
		$areas  = hpvf_scorecard_areas();
		$scores = json_decode( $m( 'areas' ), true );
		$weak   = $m( 'bottleneck' );
		$parts  = array();
		foreach ( (array) $scores as $k => $v ) {
			$parts[] = ( $areas[ $k ][0] ?? $k ) . ' ' . (int) $v;
		}
		$values['message']             = sprintf( 'Kitöltötte a Growth Scorecardot: %s/100, leggyengébb terület: %s.', $m( 'score' ), $areas[ $weak ][0] ?? '—' );
		$values['Scorecard eredmény']  = $m( 'score' ) . '/100';
		$values['Leggyengébb terület'] = $areas[ $weak ][0] ?? '';
		$values['Területek']           = implode( ', ', $parts );
	} else {
		$values['message']     = sprintf( 'Stratégiai hívást kért. Fő kihívás: %s. Időzítés: %s.', $m( 'challenge' ) ?: '—', $m( 'timing' ) ?: '—' );
		$values['Iparág']      = $m( 'industry' );
		$values['Város']       = $m( 'city' );
		$values['Fő kihívás']  = $m( 'challenge' );
		$values['Büdzsé']      = $m( 'budget' );
		$values['Időzítés']    = $m( 'timing' );
	}

	$utm = hpvf_crm_utm( $lead_id, $data );
	if ( $utm ) {
		$values['Kampány'] = implode( ', ', array_map( static function ( $k, $v ) { return $k . '=' . $v; }, array_keys( $utm ), $utm ) );
	}

	return (array) apply_filters( 'hpvf_crm_values', array_filter( $values, 'strlen' ), $lead_id, $type, $data );
}

/**
 * The source page of this submission.
 *
 * @param int   $lead_id Lead.
 * @param array $data    Raw submission.
 */
function hpvf_crm_page( $lead_id, $data ) {
	$page = esc_url_raw( (string) ( $data['source_page'] ?? '' ) );
	if ( ! $page ) {
		$page = esc_url_raw( (string) wp_get_referer() );
	}
	return $page ? $page : home_url( '/' );
}

/**
 * The lead in the CRM bridge format. Uses the mu-plugin's mapper when available, so the CRM gets exactly
 * what the other website forms send.
 *
 * @param array  $values hpvf_crm_values().
 * @param string $form   Form label.
 * @param string $page   Source page URL.
 * @return array|null
 */
function hpvf_crm_map( $values, $form, $page ) {
	if ( function_exists( 'hpv_leads_map' ) ) {
		return hpv_leads_map( $values, 'contact', $form, $page );
	}
	if ( empty( $values['email'] ) || ! is_email( $values['email'] ) ) {
		return null;
	}
	$lead = array(
		'source' => 'contact',
		'form'   => $form,
		'page'   => $page,
		'fields' => array(),
	);
	$core = array( 'email' => 'email', 'name' => 'name', 'phone' => 'phone', 'website' => 'website', 'company' => 'business', 'message' => 'message' );
	foreach ( $values as $k => $v ) {
		if ( isset( $core[ $k ] ) ) {
			$lead[ $core[ $k ] ] = $v;
		} else {
			$lead['fields'][ $k ] = $v;
		}
	}
	$lead['country'] = 0 === strpos( get_locale(), 'hu' ) ? 'HU' : 'US';
	return $lead;
}

/* -------------------------------------------------------------------------
 * Sending
 * ---------------------------------------------------------------------- */

/**
 * New lead: prepare now (the visitor's cookies and IP are still here), send after the response.
 *
 * @param int    $lead_id Lead.
 * @param string $type    strategy_call | scorecard.
 * @param array  $data    Raw submission.
 */
function hpvf_crm_on_lead( $lead_id, $type, $data ) {
	if ( ! hpvf_crm_configured() ) {
		return;
	}
	$data = (array) $data;
	$page = hpvf_crm_page( $lead_id, $data );
	$lead = hpvf_crm_map( hpvf_crm_values( $lead_id, $type, $data ), hpvf_crm_form_label( $type ), $page );
	if ( ! $lead ) {
		return;
	}
	// Attribution: the mu-plugin's hpv_attr cookie (first + last visit) if present, else the form's UTM capture.
	$attr = function_exists( 'hpv_leads_attribution' ) ? hpv_leads_attribution() : array();
	if ( ! $attr ) {
		$attr = hpvf_crm_attribution_from_utm( hpvf_crm_utm( $lead_id, $data ), $page );
	}
	if ( $attr ) {
		$lead['attribution'] = $attr;
	}
	// Same per-IP limit the mu-plugin applies to every other form.
	if ( 'leads-plugin' === hpvf_crm_mode() && function_exists( 'hpv_leads_ip_ok' ) && ! hpv_leads_ip_ok() ) {
		hpvf_crm_mark( $lead_id, 'skipped', 'rate limit' );
		return;
	}

	$GLOBALS['hpvf_crm_pending'][] = array( (int) $lead_id, $lead );
	if ( ! has_action( 'shutdown', 'hpvf_crm_flush' ) ) {
		add_action( 'shutdown', 'hpvf_crm_flush', 1 );
	}
}
add_action( 'hpvf_lead_created', 'hpvf_crm_on_lead', 20, 3 );

/**
 * After the response: send the prepared leads.
 */
function hpvf_crm_flush() {
	$pending = $GLOBALS['hpvf_crm_pending'] ?? array();
	$GLOBALS['hpvf_crm_pending'] = array();
	if ( ! $pending ) {
		return;
	}
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	}
	foreach ( $pending as $item ) {
		hpvf_crm_send( $item[0], $item[1] );
	}
}

/**
 * Send one lead and record the result on the lead.
 *
 * @param int   $lead_id Lead.
 * @param array $lead    Bridge payload.
 * @return string Status: sent | queued | retry | failed | skipped.
 */
function hpvf_crm_send( $lead_id, $lead ) {
	if ( 'leads-plugin' === hpvf_crm_mode() ) {
		$outbox = defined( 'HPV_LEADS_OUTBOX' ) ? HPV_LEADS_OUTBOX : 'hpv_leads_outbox';
		$before = count( (array) get_option( $outbox, array() ) );
		if ( hpv_leads_send( $lead ) ) {
			return hpvf_crm_mark( $lead_id, 'sent' );
		}
		if ( function_exists( 'hpv_leads_crm_configured' ) && ! hpv_leads_crm_configured() ) {
			return hpvf_crm_mark( $lead_id, 'skipped', 'HPV_CRM_URL / HPV_BRIDGE_SECRET missing' );
		}
		// hpv_leads_send() queued it for its own hourly retry, or a hpv_leads_capture filter / the CRM rejected it.
		$queued = count( (array) get_option( $outbox, array() ) ) > $before;
		return hpvf_crm_mark( $lead_id, $queued ? 'queued' : 'skipped', $queued ? 'waiting in the Leads → CRM outbox' : 'not accepted' );
	}

	$error = hpvf_crm_deliver( $lead );
	if ( '' === $error ) {
		delete_post_meta( $lead_id, '_hpvf_crm_pending' );
		return hpvf_crm_mark( $lead_id, 'sent' );
	}
	if ( 'rejected' === $error || '' === hpvf_crm_mode() ) {
		delete_post_meta( $lead_id, '_hpvf_crm_pending' );
		return hpvf_crm_mark( $lead_id, 'failed', $error );
	}
	$queue   = get_post_meta( $lead_id, '_hpvf_crm_pending', true );
	$queue   = is_array( $queue ) ? $queue : array();
	$queue[] = array( 'lead' => $lead, 'at' => time() );
	update_post_meta( $lead_id, '_hpvf_crm_pending', array_slice( $queue, -5 ) );
	if ( ! wp_next_scheduled( 'hpvf_crm_retry' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'hpvf_crm_retry' );
	}
	return hpvf_crm_mark( $lead_id, 'retry', $error );
}

/**
 * Deliver without the mu-plugin. Same request and signature as hpv_leads_deliver().
 *
 * @param array $lead Bridge payload.
 * @return string '' on success, 'rejected' for a 400, otherwise the error.
 */
function hpvf_crm_deliver( $lead ) {
	if ( function_exists( 'hpv_leads_ingest' ) && apply_filters( 'hpv_leads_direct', true ) ) {
		$res = hpv_leads_ingest( $lead );
		return is_wp_error( $res ) ? $res->get_error_message() : '';
	}
	if ( 'signed' !== hpvf_crm_mode() ) {
		return 'not configured';
	}
	$body = wp_json_encode( $lead );
	$ts   = time();
	$res  = wp_remote_post(
		untrailingslashit( (string) HPV_CRM_URL ) . '/wp-json/hpv/v1/bridge/lead',
		array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type'    => 'application/json',
				'X-HPV-Timestamp' => (string) $ts,
				'X-HPV-Signature' => hash_hmac( 'sha256', $ts . '.' . $body, (string) HPV_BRIDGE_SECRET ),
			),
			'body'    => $body,
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res->get_error_message();
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 === $code ) {
		return '';
	}
	// 400: the CRM rejected it (e.g. invalid email), retrying will not help.
	return 400 === $code ? 'rejected' : 'HTTP ' . $code;
}

/**
 * Hourly retry of the fallback path (the mu-plugin retries its own outbox).
 *
 * @return array { sent, waiting, dropped }
 */
function hpvf_crm_retry() {
	$out = array( 'sent' => 0, 'waiting' => 0, 'dropped' => 0 );
	$ids = get_posts(
		array(
			'post_type'      => 'hpvf_lead',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_key'       => '_hpvf_crm_pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
	foreach ( $ids as $id ) {
		$queue = get_post_meta( $id, '_hpvf_crm_pending', true );
		$keep  = array();
		$error = '';
		foreach ( is_array( $queue ) ? $queue : array() as $item ) {
			$error = hpvf_crm_deliver( $item['lead'] );
			if ( '' === $error ) {
				$out['sent']++;
				hpvf_crm_mark( $id, 'sent' );
			} elseif ( 'rejected' === $error || time() - (int) $item['at'] > HPVF_CRM_MAX_AGE ) {
				$out['dropped']++;
				hpvf_crm_mark( $id, 'failed', $error );
			} else {
				$keep[] = $item;
				$out['waiting']++;
			}
		}
		if ( $keep ) {
			update_post_meta( $id, '_hpvf_crm_pending', $keep );
			hpvf_crm_mark( $id, 'retry', $error );
		} else {
			delete_post_meta( $id, '_hpvf_crm_pending' );
		}
	}
	if ( ! $out['waiting'] ) {
		wp_clear_scheduled_hook( 'hpvf_crm_retry' );
	}
	return $out;
}
add_action( 'hpvf_crm_retry', 'hpvf_crm_retry' );

/**
 * Record the result on the lead (and site-wide, for the checklist).
 *
 * @param int    $lead_id Lead.
 * @param string $status  sent | queued | retry | failed | skipped.
 * @param string $error   Error, if any.
 * @return string Status.
 */
function hpvf_crm_mark( $lead_id, $status, $error = '' ) {
	$prev = get_post_meta( $lead_id, '_hpvf_crm_status', true );
	update_post_meta( $lead_id, '_hpvf_crm_status', $status );
	if ( 'sent' === $status ) {
		update_post_meta( $lead_id, '_hpvf_crm_sent', time() );
		delete_post_meta( $lead_id, '_hpvf_crm_error' );
	} elseif ( '' !== $error ) {
		update_post_meta( $lead_id, '_hpvf_crm_error', mb_substr( $error, 0, 200 ) );
	}
	update_option( 'hpvf_crm_last', array( 'at' => time(), 'status' => $status, 'error' => $error ), false );
	if ( $prev !== $status || 'sent' === $status ) {
		$texts = array(
			'sent'    => __( 'Sent to the HelloProVision CRM.', 'hpv' ),
			'queued'  => __( 'HelloProVision CRM not reachable; the Leads → CRM plugin will retry.', 'hpv' ),
			'retry'   => __( 'HelloProVision CRM not reachable; retrying hourly.', 'hpv' ),
			'failed'  => __( 'Could not be sent to the HelloProVision CRM.', 'hpv' ),
			'skipped' => __( 'Not sent to the HelloProVision CRM.', 'hpv' ),
		);
		hpvf_add_activity( $lead_id, 'crm', ( $texts[ $status ] ?? $status ) . ( $error && 'sent' !== $status ? ' (' . $error . ')' : '' ), 0 );
	}
	return $status;
}

/* -------------------------------------------------------------------------
 * Admin: lead box + launch checklist line
 * ---------------------------------------------------------------------- */

/**
 * One-line connection status.
 *
 * @return array( bool ok, string text )
 */
function hpvf_crm_status() {
	$mode = hpvf_crm_mode();
	$last = get_option( 'hpvf_crm_last' );
	$tail = is_array( $last ) && ! empty( $last['at'] )
		/* translators: 1: date, 2: status */
		? ' ' . sprintf( __( 'Last send: %1$s (%2$s).', 'hpv' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['at'] ), $last['status'] . ( $last['error'] ? ': ' . $last['error'] : '' ) )
		: '';
	if ( 'leads-plugin' === $mode && hpvf_crm_configured() ) {
		return array( true, __( 'Through the HelloProVision “Leads → CRM” mu-plugin (queue and retry there).', 'hpv' ) . $tail );
	}
	if ( 'leads-plugin' === $mode ) {
		return array( false, __( 'The Leads → CRM mu-plugin is active, but HPV_CRM_URL and HPV_BRIDGE_SECRET are missing (HelloProVision setup wizard, “site” mode).', 'hpv' ) );
	}
	if ( 'direct' === $mode ) {
		return array( true, __( 'The CRM runs on this WordPress: leads go in directly.', 'hpv' ) . $tail );
	}
	if ( 'signed' === $mode ) {
		/* translators: %s: CRM URL */
		return array( true, sprintf( __( 'Signed requests to %s (without the Leads → CRM mu-plugin; hourly retry).', 'hpv' ), (string) HPV_CRM_URL ) . $tail );
	}
	return array( false, __( 'Not connected: leads stay on this site only. Install the Leads → CRM mu-plugin with the HelloProVision setup wizard (“site” mode), or define HPV_CRM_URL and HPV_BRIDGE_SECRET in wp-config.php.', 'hpv' ) );
}

/**
 * "CRM-kapcsolat" line on the setup screen's launch checklist.
 *
 * @param array $checks Rows of [ok, label, hint, link].
 */
function hpvf_crm_launch_check( $checks ) {
	$s        = hpvf_crm_status();
	$checks[] = array( $s[0], __( 'CRM-kapcsolat (HelloProVision CRM)', 'hpv' ), $s[1], '' );
	return $checks;
}
add_filter( 'hpvf_launch_checks', 'hpvf_crm_launch_check' );

/**
 * Side box on the lead screen.
 */
function hpvf_crm_meta_box() {
	add_meta_box( 'hpvf_lead_crm', __( 'HelloProVision CRM', 'hpv' ), 'hpvf_crm_box', 'hpvf_lead', 'side', 'default' );
}
add_action( 'add_meta_boxes_hpvf_lead', 'hpvf_crm_meta_box' );

/**
 * Render the side box.
 *
 * @param WP_Post $post Lead.
 */
function hpvf_crm_box( $post ) {
	$status = (string) get_post_meta( $post->ID, '_hpvf_crm_status', true );
	$sent   = (int) get_post_meta( $post->ID, '_hpvf_crm_sent', true );
	$error  = (string) get_post_meta( $post->ID, '_hpvf_crm_error', true );
	if ( $sent ) {
		/* translators: %s: date */
		echo '<p>' . esc_html( sprintf( __( 'Sent: %s', 'hpv' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sent ) ) ) . '</p>';
	}
	if ( $status && 'sent' !== $status ) {
		echo '<p><strong>' . esc_html( $status ) . '</strong>' . ( $error ? ': ' . esc_html( $error ) : '' ) . '</p>';
	}
	if ( ! $sent && ! $status ) {
		echo '<p>' . esc_html__( 'Not sent.', 'hpv' ) . '</p>';
	}
	echo '<p class="description">' . esc_html( hpvf_crm_status()[1] ) . '</p>';
}
