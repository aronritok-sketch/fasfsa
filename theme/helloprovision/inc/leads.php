<?php
/**
 * Leads: capture (REST + no-JS POST), spam protection, de-duplication, notifications.
 *
 * One lead = one person (matched by email). Every form submission, stage change and note
 * is appended to the lead's activity timeline, so a scorecard taker who later books a call
 * shows up as one record with both events.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Vocabulary
 * ---------------------------------------------------------------------- */

/**
 * Form choices (shared by the form markup and server-side validation).
 *
 * @param string $field Field.
 */
function hpv_lead_choices( $field ) {
	$choices = array(
		'industry'  => array( 'Home services & contractors', 'Remodeling & custom building', 'Pool, roofing, HVAC or landscaping', 'Marine', 'Hospitality (hotel, restaurant, charter, venue)', 'Clinic or professional services', 'Specialty retail', 'Other' ),
		'city'      => array( 'Fort Myers', 'Naples', 'Cape Coral', 'Other SWFL', 'Other' ),
		'challenge' => array( 'Not enough leads', 'Wrong-fit leads', 'Low visibility on Google', 'Website outdated', 'Not sure what’s working' ),
		'budget'    => array( 'Under $1,000', '$1,000–$3,000', '$3,000–$7,500', '$7,500+', 'Not sure yet' ),
		'timing'    => array( 'Now', '1–3 months', 'Just exploring' ),
	);
	return apply_filters( 'hpv_lead_choices', $choices[ $field ] ?? array(), $field );
}

/**
 * Pipeline stages: slug => array( label, colour ).
 */
function hpv_lead_stages() {
	return apply_filters(
		'hpv_lead_stages',
		array(
			'new'       => array( __( 'New', 'hpv' ), '#FF3EA5' ),
			'contacted' => array( __( 'Contacted', 'hpv' ), '#FFB23F' ),
			'qualified' => array( __( 'Qualified', 'hpv' ), '#FF5A1F' ),
			'booked'    => array( __( 'Call booked', 'hpv' ), '#8FA0FF' ),
			'proposal'  => array( __( 'Proposal sent', 'hpv' ), '#D9C9A8' ),
			'won'       => array( __( 'Won', 'hpv' ), '#2FBF71' ),
			'lost'      => array( __( 'Lost / not a fit', 'hpv' ), '#9A948A' ),
		)
	);
}

/**
 * Scorecard content used in the results email (mirrors assets/js/scorecard.js).
 */
function hpv_scorecard_areas() {
	return array(
		'positioning' => array( 'Positioning', 'Visitors can’t quickly tell why they should choose you. Every other channel works harder when the message is sharp.', array( 'Ask your last five good customers why they chose you, and write down their exact words.', 'Rewrite your first screen in one sentence: what you do, for whom, and where, plus one clear next step.', 'Pick the one service you want more of and make it the most visible thing on the site for 90 days.' ) ),
		'website'     => array( 'Website', 'Your site is where interested people decide. Right now it’s losing some of them before they reach out.', array( 'Test your site on your phone like a customer would: phone number, a project and a quote request in under a minute?', 'Give your top service its own page: what it includes, who it’s for, one project and one review.', 'Move proof next to every call to action: a named review or a project photo where people decide.' ) ),
		'search'      => array( 'Search visibility', 'Customers searching for what you do are finding competitors first. Local search is often the fastest channel to fix.', array( 'Complete your Google Business Profile: every service, service area, category and at least ten real photos.', 'Ask your last ten happy customers for a review, with a direct link, the day the job is finished.', 'Search “[service] [city]” in a private window and note who shows up.' ) ),
		'conversion'  => array( 'Conversion', 'Without knowing what produces calls, every marketing dollar is a guess. Measurement comes before more spend.', array( 'Count last month’s calls and form requests. Even a manual tally gives you a starting point.', 'Set up call and form tracking, so you know which pages and searches bring inquiries.', 'Make contact one step from every page: tap-to-call, a short form or a booking link.' ) ),
		'followup'    => array( 'Follow-up', 'Leads are arriving but some go cold before you reply. This is usually the cheapest growth you’ll ever find.', array( 'Measure your reply time this week: when each inquiry came in and when someone answered.', 'Set a one-hour reply rule: who answers new inquiries during business hours, and what they say.', 'Follow up twice with anyone who doesn’t book: after two days and after a week.' ) ),
	);
}

/* -------------------------------------------------------------------------
 * REST API
 * ---------------------------------------------------------------------- */

/**
 * Routes.
 */
function hpv_register_lead_routes() {
	register_rest_route(
		'hpv/v1',
		'/lead',
		array(
			'methods'             => 'POST',
			'callback'            => 'hpv_rest_lead',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'hpv/v1',
		'/scorecard',
		array(
			'methods'             => 'POST',
			'callback'            => 'hpv_rest_scorecard',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'hpv_register_lead_routes' );

/**
 * Strategy call via REST.
 *
 * @param WP_REST_Request $request Request.
 */
function hpv_rest_lead( WP_REST_Request $request ) {
	$result = hpv_process_strategy_call( (array) $request->get_json_params() );
	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 422 );
	}
	return new WP_REST_Response( array( 'ok' => true ), 201 );
}

/**
 * Scorecard via REST.
 *
 * @param WP_REST_Request $request Request.
 */
function hpv_rest_scorecard( WP_REST_Request $request ) {
	$result = hpv_process_scorecard( (array) $request->get_json_params() );
	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 422 );
	}
	return new WP_REST_Response( array( 'ok' => true ), 201 );
}

/**
 * No-JavaScript fallback for the strategy call form.
 */
function hpv_admin_post_strategy_call() {
	$data   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- public form; protected by honeypot, timing and rate limit (nonces break with page caching).
	$result = hpv_process_strategy_call( (array) $data );
	$back   = wp_get_referer() ? wp_get_referer() : hpv_page_url( 'strategy-call' );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'hpv_error', $result->get_error_code(), $back ) . '#call-form' );
		exit;
	}
	$booking = hpv_opt( 'booking_url' );
	if ( $booking ) {
		// Booking tools are external: send the visitor there with their details filled in.
		wp_redirect( add_query_arg( array( 'name' => rawurlencode( $data['name'] ?? '' ), 'email' => rawurlencode( $data['email'] ?? '' ) ), $booking ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- admin-configured booking URL.
		exit;
	}
	wp_safe_redirect( hpv_page_url( 'strategy-call/thank-you' ) );
	exit;
}
add_action( 'admin_post_nopriv_hpv_strategy_call', 'hpv_admin_post_strategy_call' );
add_action( 'admin_post_hpv_strategy_call', 'hpv_admin_post_strategy_call' );

/* -------------------------------------------------------------------------
 * Processing
 * ---------------------------------------------------------------------- */

/**
 * Spam and abuse checks shared by all forms.
 *
 * @param array $data Raw submission.
 * @return true|WP_Error
 */
function hpv_lead_guard( $data ) {
	if ( ! empty( $data['company_hp'] ) ) {
		return new WP_Error( 'spam', __( 'Something looked automated. Please try again.', 'hpv' ) );
	}
	$ts = isset( $data['ts'] ) ? (int) ( (float) $data['ts'] / 1000 ) : 0;
	if ( $ts && ( time() - $ts ) < 3 ) {
		return new WP_Error( 'too_fast', __( 'That was quick. Please take a second and try again.', 'hpv' ) );
	}
	$key   = 'hpv_rl_' . md5( hpv_client_ip() );
	$count = (int) get_transient( $key );
	if ( $count >= 6 ) {
		return new WP_Error( 'rate', __( 'Too many submissions from your connection. Please email or call instead.', 'hpv' ) );
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return true;
}

/**
 * Visitor IP (only used hashed, for rate limiting).
 */
function hpv_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
}

/**
 * GA client ID from the _ga cookie, so stage changes can be reported to GA4 later.
 */
function hpv_ga_client_id() {
	if ( empty( $_COOKIE['_ga'] ) ) {
		return '';
	}
	$parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) );
	return count( $parts ) >= 4 ? $parts[2] . '.' . $parts[3] : '';
}

/**
 * Clean contact fields.
 *
 * @param array $data Raw submission.
 */
function hpv_clean_contact( $data ) {
	$website = trim( (string) ( $data['website'] ?? '' ) );
	if ( $website && ! preg_match( '#^https?://#i', $website ) ) {
		$website = 'https://' . $website;
	}
	return array(
		'name'    => mb_substr( sanitize_text_field( $data['name'] ?? '' ), 0, 120 ),
		'email'   => sanitize_email( $data['email'] ?? '' ),
		'phone'   => mb_substr( sanitize_text_field( $data['phone'] ?? '' ), 0, 40 ),
		'website' => $website ? esc_url_raw( mb_substr( $website, 0, 200 ) ) : '',
	);
}

/**
 * Handle a strategy-call request.
 *
 * @param array $data Raw submission.
 * @return int|WP_Error Lead ID.
 */
function hpv_process_strategy_call( $data ) {
	$guard = hpv_lead_guard( $data );
	if ( is_wp_error( $guard ) ) {
		return $guard;
	}
	$c = hpv_clean_contact( $data );
	if ( ! $c['name'] || ! is_email( $c['email'] ) || strlen( preg_replace( '/\D/', '', $c['phone'] ) ) < 7 ) {
		return new WP_Error( 'invalid', __( 'Please add your name, a valid email and a phone number.', 'hpv' ) );
	}
	$fields = array();
	foreach ( array( 'industry', 'city', 'challenge', 'budget', 'timing' ) as $f ) {
		$v            = sanitize_text_field( $data[ $f ] ?? '' );
		$fields[ $f ] = in_array( $v, hpv_lead_choices( $f ), true ) ? $v : '';
	}
	$utm = json_decode( (string) ( $data['utm'] ?? '' ), true );
	$utm = is_array( $utm ) ? array_map( 'sanitize_text_field', array_slice( $utm, 0, 8 ) ) : array();

	$lead_id = hpv_upsert_lead(
		$c,
		array_merge(
			$fields,
			array(
				'source_page' => esc_url_raw( $data['source_page'] ?? '' ),
				'utm'         => $utm,
			)
		),
		'strategy_call',
		sprintf(
			/* translators: 1: challenge, 2: timing */
			__( 'Requested a strategy call. Challenge: %1$s. Timing: %2$s.', 'hpv' ),
			$fields['challenge'] ? $fields['challenge'] : '—',
			$fields['timing'] ? $fields['timing'] : '—'
		)
	);
	if ( is_wp_error( $lead_id ) ) {
		return $lead_id;
	}

	hpv_notify_team( $lead_id, 'strategy_call' );
	hpv_confirm_visitor( $lead_id, 'strategy_call' );
	do_action( 'hpv_lead_created', $lead_id, 'strategy_call', $data );
	return $lead_id;
}

/**
 * Handle a completed scorecard.
 *
 * @param array $data Raw submission.
 * @return int|WP_Error Lead ID.
 */
function hpv_process_scorecard( $data ) {
	$guard = hpv_lead_guard( $data );
	if ( is_wp_error( $guard ) ) {
		return $guard;
	}
	$c = hpv_clean_contact( $data );
	if ( ! $c['name'] || ! is_email( $c['email'] ) ) {
		return new WP_Error( 'invalid', __( 'Please add your name and a valid email.', 'hpv' ) );
	}
	$areas      = array();
	$valid_keys = array_keys( hpv_scorecard_areas() );
	foreach ( (array) ( $data['areas'] ?? array() ) as $k => $v ) {
		if ( in_array( $k, $valid_keys, true ) ) {
			$areas[ $k ] = max( 0, min( 100, (int) $v ) );
		}
	}
	$answers = array();
	foreach ( (array) ( $data['answers'] ?? array() ) as $k => $v ) {
		if ( preg_match( '/^q-[a-z]+-\d$/', (string) $k ) ) {
			$answers[ $k ] = max( 0, min( 2, (int) $v ) );
		}
	}
	$bottleneck = in_array( $data['bottleneck'] ?? '', $valid_keys, true ) ? $data['bottleneck'] : '';
	$total      = max( 0, min( 100, (int) ( $data['total'] ?? 0 ) ) );

	$lead_id = hpv_upsert_lead(
		$c,
		array(
			'score'       => $total,
			'bottleneck'  => $bottleneck,
			'areas'       => $areas,
			'answers'     => $answers,
			'source_page' => esc_url_raw( $data['source_page'] ?? '' ),
		),
		'scorecard',
		sprintf(
			/* translators: 1: score, 2: area */
			__( 'Completed the Growth Scorecard: %1$d/100, weakest area %2$s.', 'hpv' ),
			$total,
			$bottleneck ? hpv_scorecard_areas()[ $bottleneck ][0] : '—'
		)
	);
	if ( is_wp_error( $lead_id ) ) {
		return $lead_id;
	}

	hpv_notify_team( $lead_id, 'scorecard' );
	hpv_confirm_visitor( $lead_id, 'scorecard' );
	do_action( 'hpv_lead_created', $lead_id, 'scorecard', $data );
	return $lead_id;
}

/**
 * Create a lead, or merge into the existing lead with the same email.
 *
 * @param array  $contact  name, email, phone, website.
 * @param array  $fields   Extra fields (stored as _hpv_{key}).
 * @param string $type     strategy_call | scorecard.
 * @param string $activity Activity line.
 * @return int|WP_Error
 */
function hpv_upsert_lead( $contact, $fields, $type, $activity ) {
	$existing = get_posts(
		array(
			'post_type'      => 'hpv_lead',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_hpv_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => strtolower( $contact['email'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);

	if ( $existing ) {
		$lead_id = (int) $existing[0];
		foreach ( $contact as $k => $v ) {
			if ( $v && ! get_post_meta( $lead_id, '_hpv_' . $k, true ) ) {
				update_post_meta( $lead_id, '_hpv_' . $k, 'email' === $k ? strtolower( $v ) : $v );
			}
		}
		// A new request re-opens a closed lead.
		if ( 'strategy_call' === $type && in_array( get_post_meta( $lead_id, '_hpv_stage', true ), array( 'lost', 'won' ), true ) ) {
			hpv_set_stage( $lead_id, 'new', 0 );
		}
	} else {
		$lead_id = wp_insert_post(
			array(
				'post_type'   => 'hpv_lead',
				'post_status' => 'publish',
				'post_title'  => $contact['name'] . ( $contact['website'] ? ' — ' . wp_parse_url( $contact['website'], PHP_URL_HOST ) : '' ),
			),
			true
		);
		if ( is_wp_error( $lead_id ) ) {
			return $lead_id;
		}
		foreach ( $contact as $k => $v ) {
			update_post_meta( $lead_id, '_hpv_' . $k, 'email' === $k ? strtolower( $v ) : $v );
		}
		update_post_meta( $lead_id, '_hpv_stage', 'new' );
		update_post_meta( $lead_id, '_hpv_first_type', $type );
	}

	foreach ( $fields as $k => $v ) {
		if ( is_array( $v ) ) {
			if ( $v ) {
				update_post_meta( $lead_id, '_hpv_' . $k, wp_json_encode( $v ) );
			}
		} elseif ( '' !== (string) $v ) {
			update_post_meta( $lead_id, '_hpv_' . $k, $v );
		}
	}

	$types = (array) get_post_meta( $lead_id, '_hpv_types', true );
	$types = array_values( array_unique( array_filter( array_merge( $types, array( $type ) ) ) ) );
	update_post_meta( $lead_id, '_hpv_types', $types );
	update_post_meta( $lead_id, '_hpv_last_activity', time() );
	update_post_meta( $lead_id, '_hpv_unread', 1 );
	if ( hpv_ga_client_id() ) {
		update_post_meta( $lead_id, '_hpv_ga_cid', hpv_ga_client_id() );
	}
	hpv_add_activity( $lead_id, $type, $activity, 0 );
	return $lead_id;
}

/**
 * Append a timeline entry.
 *
 * @param int    $lead_id Lead.
 * @param string $type    strategy_call | scorecard | stage | note | email.
 * @param string $text    Text.
 * @param int    $user_id Acting user (0 = the visitor/system).
 */
function hpv_add_activity( $lead_id, $type, $text, $user_id = null ) {
	$log   = get_post_meta( $lead_id, '_hpv_activity', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		't'    => time(),
		'type' => $type,
		'text' => $text,
		'user' => null === $user_id ? get_current_user_id() : (int) $user_id,
	);
	update_post_meta( $lead_id, '_hpv_activity', $log );
}

/**
 * Move a lead to another pipeline stage.
 *
 * @param int    $lead_id Lead.
 * @param string $stage   Stage slug.
 * @param int    $user_id Acting user.
 */
function hpv_set_stage( $lead_id, $stage, $user_id = null ) {
	$stages = hpv_lead_stages();
	$old    = get_post_meta( $lead_id, '_hpv_stage', true );
	if ( ! isset( $stages[ $stage ] ) || $old === $stage ) {
		return;
	}
	update_post_meta( $lead_id, '_hpv_stage', $stage );
	update_post_meta( $lead_id, '_hpv_stage_' . $stage, time() );
	hpv_add_activity(
		$lead_id,
		'stage',
		/* translators: 1: old stage, 2: new stage */
		sprintf( __( 'Stage: %1$s → %2$s', 'hpv' ), $stages[ $old ][0] ?? '—', $stages[ $stage ][0] ),
		$user_id
	);
	do_action( 'hpv_lead_stage_changed', $lead_id, $stage, $old );
}

/**
 * Report qualified / won leads to GA4 (Measurement Protocol), the blueprint's real KPI.
 *
 * @param int    $lead_id Lead.
 * @param string $stage   New stage.
 */
function hpv_report_stage_to_ga4( $lead_id, $stage ) {
	$events = array( 'qualified' => 'qualified_lead', 'won' => 'close_convert_lead' );
	$secret = hpv_opt( 'ga4_api_secret' );
	$mid    = hpv_opt( 'ga4_id' );
	$cid    = get_post_meta( $lead_id, '_hpv_ga_cid', true );
	if ( empty( $events[ $stage ] ) || ! $secret || ! $mid || ! $cid ) {
		return;
	}
	wp_remote_post(
		add_query_arg( array( 'measurement_id' => $mid, 'api_secret' => $secret ), 'https://www.google-analytics.com/mp/collect' ),
		array(
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'client_id' => $cid,
					'events'    => array( array( 'name' => $events[ $stage ], 'params' => array( 'lead_source' => get_post_meta( $lead_id, '_hpv_first_type', true ) ) ) ),
				)
			),
		)
	);
}
add_action( 'hpv_lead_stage_changed', 'hpv_report_stage_to_ga4', 10, 2 );

/* -------------------------------------------------------------------------
 * Email
 * ---------------------------------------------------------------------- */

/**
 * Tell the team about a new submission.
 *
 * @param int    $lead_id Lead.
 * @param string $type    Submission type.
 */
function hpv_notify_team( $lead_id, $type ) {
	$to = hpv_opt( 'notify_email' ) ? hpv_opt( 'notify_email' ) : get_option( 'admin_email' );
	$m  = static function ( $k ) use ( $lead_id ) {
		return (string) get_post_meta( $lead_id, '_hpv_' . $k, true );
	};

	$lines = array(
		'Name'    => $m( 'name' ),
		'Email'   => $m( 'email' ),
		'Phone'   => $m( 'phone' ),
		'Website' => $m( 'website' ),
	);
	if ( 'strategy_call' === $type ) {
		$lines += array(
			'Industry'  => $m( 'industry' ),
			'City'      => $m( 'city' ),
			'Challenge' => $m( 'challenge' ),
			'Budget'    => $m( 'budget' ),
			'Timing'    => $m( 'timing' ),
		);
	} else {
		$areas = hpv_scorecard_areas();
		$lines += array(
			'Score'      => $m( 'score' ) . '/100',
			'Bottleneck' => $areas[ $m( 'bottleneck' ) ][0] ?? '',
		);
	}
	$body = '';
	foreach ( $lines as $k => $v ) {
		if ( '' !== $v ) {
			$body .= $k . ': ' . $v . "\n";
		}
	}
	$body .= "\n" . __( 'Open in the lead manager:', 'hpv' ) . ' ' . admin_url( 'post.php?post=' . $lead_id . '&action=edit' ) . "\n";
	/* translators: %s: reply window in hours */
	$body .= sprintf( __( 'Promise: a personal reply within %s business hour(s).', 'hpv' ), hpv_opt( 'reply_hours' ) ) . "\n";

	$subject = 'strategy_call' === $type
		/* translators: %s: name */
		? sprintf( __( 'New strategy call request: %s', 'hpv' ), $m( 'name' ) )
		/* translators: 1: name, 2: score */
		: sprintf( __( 'Scorecard completed: %1$s (%2$s/100)', 'hpv' ), $m( 'name' ), $m( 'score' ) );

	wp_mail( $to, $subject, $body, array( 'Reply-To: ' . $m( 'name' ) . ' <' . $m( 'email' ) . '>' ) );
}

/**
 * Confirmation to the visitor.
 *
 * @param int    $lead_id Lead.
 * @param string $type    Submission type.
 */
function hpv_confirm_visitor( $lead_id, $type ) {
	$email = get_post_meta( $lead_id, '_hpv_email', true );
	$first = strtok( (string) get_post_meta( $lead_id, '_hpv_name', true ), ' ' );
	$from  = get_bloginfo( 'name' );
	$reply = hpv_opt( 'email' ) ? hpv_opt( 'email' ) : get_option( 'admin_email' );

	if ( 'strategy_call' === $type ) {
		$subject = __( 'Your strategy call request', 'hpv' );
		/* translators: %s: first name */
		$body  = sprintf( __( 'Hi %s,', 'hpv' ), $first ) . "\n\n";
		$body .= __( 'Thanks for reaching out. Before we talk I’ll look at your website and your search visibility, so we can spend the 30 minutes on what matters.', 'hpv' ) . "\n\n";
		if ( hpv_opt( 'booking_url' ) ) {
			$body .= __( 'If you haven’t picked a time yet:', 'hpv' ) . ' ' . add_query_arg( array( 'name' => rawurlencode( get_post_meta( $lead_id, '_hpv_name', true ) ), 'email' => rawurlencode( $email ) ), hpv_opt( 'booking_url' ) ) . "\n\n";
		}
		/* translators: %s: hours */
		$body .= sprintf( __( 'You’ll hear from me personally within %s business hour(s). Just reply to this email if anything changes.', 'hpv' ), hpv_opt( 'reply_hours' ) ) . "\n\n";
	} else {
		$areas  = hpv_scorecard_areas();
		$scores = json_decode( (string) get_post_meta( $lead_id, '_hpv_areas', true ), true );
		$weak   = get_post_meta( $lead_id, '_hpv_bottleneck', true );
		$subject = __( 'Your Digital Growth Scorecard results', 'hpv' );
		/* translators: %s: first name */
		$body  = sprintf( __( 'Hi %s,', 'hpv' ), $first ) . "\n\n";
		/* translators: %s: score */
		$body .= sprintf( __( 'Your overall score: %s/100', 'hpv' ), get_post_meta( $lead_id, '_hpv_score', true ) ) . "\n\n";
		foreach ( (array) $scores as $k => $v ) {
			$body .= '• ' . ( $areas[ $k ][0] ?? $k ) . ': ' . (int) $v . "/100\n";
		}
		if ( isset( $areas[ $weak ] ) ) {
			/* translators: %s: area */
			$body .= "\n" . sprintf( __( 'Your #1 priority: %s', 'hpv' ), $areas[ $weak ][0] ) . "\n" . $areas[ $weak ][1] . "\n\n";
			$body .= __( 'Three steps for this month:', 'hpv' ) . "\n";
			foreach ( $areas[ $weak ][2] as $i => $step ) {
				$body .= ( $i + 1 ) . '. ' . $step . "\n";
			}
		}
		$body .= "\n" . __( 'Want me to review your results with you? Book a 30-minute call:', 'hpv' ) . ' ' . hpv_page_url( 'strategy-call' ) . "\n\n";
	}
	$body .= "— " . $from . "\n";

	$sent = wp_mail( $email, $subject, $body, array( 'Reply-To: ' . $from . ' <' . $reply . '>' ) );
	hpv_add_activity( $lead_id, 'email', $sent ? __( 'Confirmation email sent.', 'hpv' ) : __( 'Confirmation email could not be sent (check the site’s mail setup).', 'hpv' ), 0 );
}
