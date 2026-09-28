<?php
/**
 * Standalone test for theme/helloprovision-founder/inc/crm-bridge.php (no WordPress needed).
 *
 *     php tests/crm-bridge.php
 *     HPV_LEADS_MU=/path/to/hlprv/mu-plugins/helloprovision-leads.php php tests/crm-bridge.php
 *
 * Every scenario runs in its own PHP process (functions cannot be undefined):
 *   stub    — a stub hpv_leads_map() / hpv_leads_send(): the bridge calls them with the right values
 *   mu      — the real HelloProVision leads mu-plugin (if HPV_LEADS_MU or ../hlprv is found): mapping + signed request
 *   signed  — no mu-plugin, HPV_CRM_URL + HPV_BRIDGE_SECRET: signed request, retry on failure
 *   none    — nothing configured: nothing is sent
 */

$scenario = $argv[1] ?? '';
$root     = dirname( __DIR__ );

if ( '' === $scenario ) {
	$mu   = getenv( 'HPV_LEADS_MU' ) ?: dirname( $root ) . '/hlprv/mu-plugins/helloprovision-leads.php';
	$fail = 0;
	foreach ( array( 'stub', 'mu', 'signed', 'none' ) as $s ) {
		if ( 'mu' === $s && ! is_file( $mu ) ) {
			echo "- mu: skipped (HelloProVision leads mu-plugin not found; set HPV_LEADS_MU)\n";
			continue;
		}
		$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $s . ' ' . escapeshellarg( $mu );
		passthru( $cmd, $code );
		$fail += $code ? 1 : 0;
	}
	echo $fail ? "FAILED ($fail scenario)\n" : "OK\n";
	exit( $fail ? 1 : 0 );
}

/* ─── Minimal WordPress stubs ─────────────────────────────── */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['t'] = array(
	'meta'     => array(),
	'options'  => array(),
	'actions'  => array(),
	'posts'    => array(),
	'sched'    => array(),
	'activity' => array(),
	'response' => 200,
);
$GLOBALS['stub_sent'] = array();
$GLOBALS['stub_map']  = array();

function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['t']['actions'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { return add_action( $hook, $cb ); }
function has_action( $hook, $cb ) { return in_array( $cb, $GLOBALS['t']['actions'][ $hook ] ?? array(), true ); }
function do_action( $hook, ...$args ) { foreach ( $GLOBALS['t']['actions'][ $hook ] ?? array() as $cb ) { $cb( ...$args ); } }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['t']['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['t']['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['t']['meta'][ $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return $GLOBALS['t']['options'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['t']['options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['t']['options'][ '_t_' . $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['t']['options'][ '_t_' . $k ] = $v; return true; }
function get_posts( $a ) { return array_keys( array_filter( $GLOBALS['t']['meta'], fn( $m ) => isset( $m[ $a['meta_key'] ] ) ) ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['t']['sched'][ $hook ] ?? false; }
function wp_schedule_event( $ts, $rec, $hook ) { $GLOBALS['t']['sched'][ $hook ] = $ts; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['t']['sched'][ $hook ] ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function esc_url_raw( $u ) { return preg_match( '#^https?://#', (string) $u ) ? (string) $u : ''; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '' ) { return 'https://aron.test' . $p; }
function wp_get_referer() { return ''; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function get_locale() { return 'en_US'; }
function wp_json_encode( $d ) { return json_encode( $d ); }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function wp_unslash( $v ) { return $v; }
function is_wp_error( $v ) { return false; }
function wp_remote_post( $url, $args ) { $GLOBALS['t']['posts'][] = array( $url, $args ); return array( 'code' => $GLOBALS['t']['response'] ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_mail() { return true; }
function is_admin() { return false; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function wp_date( $f, $t ) { return gmdate( 'Y-m-d H:i', $t ); }
function hpvf_add_activity( $id, $type, $text, $user = null ) { $GLOBALS['t']['activity'][ $id ][] = $type . ': ' . $text; }
function hpvf_scorecard_areas() {
	return array( 'positioning' => array( 'Positioning' ), 'website' => array( 'Website' ), 'search' => array( 'Search visibility' ), 'conversion' => array( 'Conversion' ), 'followup' => array( 'Follow-up' ) );
}

$fails = 0;
function check( $label, $ok ) {
	global $fails, $scenario;
	if ( ! $ok ) {
		$fails++;
		echo "  FAIL [$scenario] $label\n";
	}
}

/* ─── Scenario setup ──────────────────────────────────────── */

if ( 'stub' === $scenario ) {
	function hpv_leads_map( array $values, string $source, string $form, string $page = '' ): ?array {
		$GLOBALS['stub_map'][] = compact( 'values', 'source', 'form', 'page' );
		return array( 'email' => $values['email'], 'source' => $source, 'form' => $form, 'page' => $page, 'fields' => array() );
	}
	function hpv_leads_send( array $lead ): bool {
		$GLOBALS['stub_sent'][] = $lead;
		return true;
	}
	function hpv_leads_crm_configured(): bool { return true; }
}
if ( 'mu' === $scenario ) {
	define( 'HPV_CRM_URL', 'https://crm.test/' );
	define( 'HPV_BRIDGE_SECRET', 'test-secret-0123456789' );
	$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	require $argv[2];
}
if ( 'signed' === $scenario ) {
	define( 'HPV_CRM_URL', 'https://crm.test/' );
	define( 'HPV_BRIDGE_SECRET', 'test-secret-0123456789' );
}

require $root . '/theme/helloprovision-founder/inc/crm-bridge.php';

/* ─── Two leads: a strategy call and a scorecard ──────────── */

$GLOBALS['t']['meta'][11] = array(
	'_hpvf_name'      => 'Jane Doe',
	'_hpvf_email'     => 'jane@example.com',
	'_hpvf_phone'     => '+1 239 555 0100',
	'_hpvf_website'   => 'https://janepools.com',
	'_hpvf_industry'  => 'Pool, roofing, HVAC or landscaping',
	'_hpvf_city'      => 'Naples',
	'_hpvf_challenge' => 'Not enough leads',
	'_hpvf_budget'    => '$3,000–$7,500',
	'_hpvf_timing'    => 'Now',
);
$GLOBALS['t']['meta'][12] = array(
	'_hpvf_name'       => 'Bob Roe',
	'_hpvf_email'      => 'bob@example.com',
	'_hpvf_score'      => '48',
	'_hpvf_bottleneck' => 'search',
	'_hpvf_areas'      => json_encode( array( 'positioning' => 67, 'website' => 50, 'search' => 17 ) ),
);
$call_data  = array( 'source_page' => 'https://aron.test/strategy-call/', 'utm' => json_encode( array( 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'swfl-pools', 'gclid' => 'abc' ) ) );
$score_data = array( 'source_page' => 'https://aron.test/scorecard/' );

do_action( 'hpvf_lead_created', 11, 'strategy_call', $call_data );
do_action( 'hpvf_lead_created', 12, 'scorecard', $score_data );

if ( 'none' === $scenario ) {
	check( 'nothing configured: mode is empty', '' === hpvf_crm_mode() );
	check( 'nothing configured: no shutdown hook', ! has_action( 'shutdown', 'hpvf_crm_flush' ) );
	check( 'nothing configured: no status', '' === get_post_meta( 11, '_hpvf_crm_status' ) );
	check( 'checklist line says not connected', false === hpvf_crm_launch_check( array() )[0][0] );
	echo $fails ? '' : "- none: ok\n";
	exit( $fails ? 1 : 0 );
}

check( 'send waits for shutdown', has_action( 'shutdown', 'hpvf_crm_flush' ) && ! $GLOBALS['t']['posts'] && ! $GLOBALS['stub_sent'] );
do_action( 'shutdown' );

if ( 'stub' === $scenario ) {
	check( 'mode is leads-plugin', 'leads-plugin' === hpvf_crm_mode() );
	check( 'hpv_leads_map called twice', 2 === count( $GLOBALS['stub_map'] ) );
	$m = $GLOBALS['stub_map'][0];
	check( 'source contact', 'contact' === $m['source'] );
	check( 'form label', 'Áron – stratégiai hívás' === $m['form'] );
	check( 'page', 'https://aron.test/strategy-call/' === $m['page'] );
	foreach ( array( 'name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+1 239 555 0100', 'website' => 'https://janepools.com', 'Iparág' => 'Pool, roofing, HVAC or landscaping', 'Város' => 'Naples', 'Fő kihívás' => 'Not enough leads', 'Büdzsé' => '$3,000–$7,500', 'Időzítés' => 'Now' ) as $k => $v ) {
		check( "value $k", ( $m['values'][ $k ] ?? null ) === $v );
	}
	check( 'message summary', false !== strpos( $m['values']['message'] ?? '', 'Not enough leads' ) );
	check( 'utm as field', false !== strpos( $m['values']['Kampány'] ?? '', 'utm_campaign=swfl-pools' ) );
	$s = $GLOBALS['stub_map'][1];
	check( 'scorecard label', 'Áron – Growth Scorecard' === $s['form'] );
	check( 'scorecard result', '48/100' === ( $s['values']['Scorecard eredmény'] ?? '' ) && 'Search visibility' === ( $s['values']['Leggyengébb terület'] ?? '' ) );
	check( 'scorecard areas', 'Positioning 67, Website 50, Search visibility 17' === ( $s['values']['Területek'] ?? '' ) );
	check( 'hpv_leads_send called twice', 2 === count( $GLOBALS['stub_sent'] ) );
	$a = $GLOBALS['stub_sent'][0]['attribution'] ?? array();
	check( 'attribution from UTM', 'google' === ( $a['first']['utm_source'] ?? '' ) && 'abc' === ( $a['first']['gclid'] ?? '' ) && '/strategy-call/' === ( $a['first']['land'] ?? '' ) );
	check( 'no attribution without UTM', ! isset( $GLOBALS['stub_sent'][1]['attribution'] ) );
	check( '_hpvf_crm_sent stored', get_post_meta( 11, '_hpvf_crm_sent' ) > 0 && 'sent' === get_post_meta( 12, '_hpvf_crm_status' ) );
	check( 'activity logged', false !== strpos( implode( ' ', $GLOBALS['t']['activity'][11] ?? array() ), 'CRM' ) );
	check( 'checklist ok', true === hpvf_crm_launch_check( array() )[0][0] );
}

if ( 'mu' === $scenario || 'signed' === $scenario ) {
	check( 'mode', ( 'mu' === $scenario ? 'leads-plugin' : 'signed' ) === hpvf_crm_mode() );
	check( 'two requests', 2 === count( $GLOBALS['t']['posts'] ) );
	list( $url, $args ) = $GLOBALS['t']['posts'][0];
	check( 'bridge URL', 'https://crm.test/wp-json/hpv/v1/bridge/lead' === $url );
	$ts = $args['headers']['X-HPV-Timestamp'];
	check( 'signature', hash_equals( hash_hmac( 'sha256', $ts . '.' . $args['body'], 'test-secret-0123456789' ), $args['headers']['X-HPV-Signature'] ) );
	$lead = json_decode( $args['body'], true );
	check( 'core fields', 'jane@example.com' === $lead['email'] && 'Jane Doe' === $lead['name'] && '+1 239 555 0100' === $lead['phone'] && 'https://janepools.com' === $lead['website'] );
	check( 'source/form/page', 'contact' === $lead['source'] && 'Áron – stratégiai hívás' === $lead['form'] && 'https://aron.test/strategy-call/' === $lead['page'] );
	check( 'message', false !== strpos( $lead['message'] ?? '', 'Időzítés: Now' ) );
	check( 'extra fields', 'Naples' === ( $lead['fields']['Város'] ?? '' ) && '$3,000–$7,500' === ( $lead['fields']['Büdzsé'] ?? '' ) && 'Pool, roofing, HVAC or landscaping' === ( $lead['fields']['Iparág'] ?? '' ) );
	check( 'no business from industry', empty( $lead['business'] ) );
	check( 'country US', 'US' === $lead['country'] );
	check( 'attribution', 'swfl-pools' === ( $lead['attribution']['last']['utm_campaign'] ?? '' ) );
	$lead2 = json_decode( $GLOBALS['t']['posts'][1][1]['body'], true );
	check( 'scorecard fields', '48/100' === ( $lead2['fields']['Scorecard eredmény'] ?? '' ) && 'Áron – Growth Scorecard' === $lead2['form'] );
	check( 'sent meta', get_post_meta( 11, '_hpvf_crm_sent' ) > 0 && 'sent' === get_post_meta( 12, '_hpvf_crm_status' ) );
}

if ( 'signed' === $scenario ) {
	// CRM down: the lead waits and is retried by wp-cron.
	$GLOBALS['t']['response'] = 503;
	$GLOBALS['t']['meta'][13] = array( '_hpvf_name' => 'Cy', '_hpvf_email' => 'cy@example.com', '_hpvf_phone' => '2395550101' );
	do_action( 'hpvf_lead_created', 13, 'strategy_call', array() );
	do_action( 'shutdown' );
	check( 'retry status', 'retry' === get_post_meta( 13, '_hpvf_crm_status' ) && 'HTTP 503' === get_post_meta( 13, '_hpvf_crm_error' ) );
	check( 'retry scheduled', (bool) wp_next_scheduled( 'hpvf_crm_retry' ) );
	check( 'page fallback', 'https://aron.test/' === get_post_meta( 13, '_hpvf_crm_pending' )[0]['lead']['page'] );
	$GLOBALS['t']['response'] = 200;
	$r = hpvf_crm_retry();
	check( 'retry sends', 1 === $r['sent'] && 'sent' === get_post_meta( 13, '_hpvf_crm_status' ) && '' === get_post_meta( 13, '_hpvf_crm_pending' ) );
	check( 'retry unscheduled', ! wp_next_scheduled( 'hpvf_crm_retry' ) );
	// 400: rejected, not retried.
	$GLOBALS['t']['response'] = 400;
	do_action( 'hpvf_lead_created', 13, 'strategy_call', array() );
	do_action( 'shutdown' );
	check( 'rejected', 'failed' === get_post_meta( 13, '_hpvf_crm_status' ) && ! wp_next_scheduled( 'hpvf_crm_retry' ) );
}

echo $fails ? '' : "- $scenario: ok\n";
exit( $fails ? 1 : 0 );
