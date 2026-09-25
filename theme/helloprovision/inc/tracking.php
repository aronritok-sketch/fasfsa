<?php
/**
 * Analytics: GA4 (gtag.js) or Google Tag Manager, with Google Consent Mode v2 and an
 * optional theme-native consent banner. Logged-in editors are not tracked.
 *
 * Events pushed by assets/js/site.js (blueprint §10): cta_click, form_start, form_submit,
 * generate_lead, scorecard_start, scorecard_complete, booking_open, click_to_call,
 * click_to_email. qualified_lead / close_convert_lead are sent server-side from the lead manager.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Should this request be tracked?
 */
function hpv_should_track() {
	$track = ( hpv_opt( 'ga4_id' ) || hpv_opt( 'gtm_id' ) ) && ! current_user_can( 'edit_posts' );
	return (bool) apply_filters( 'hpv_should_track', $track );
}

/**
 * Tag snippet in <head>.
 */
function hpv_tracking_head() {
	if ( ! hpv_should_track() ) {
		return;
	}
	$consent = hpv_opt( 'consent_banner' );
	$ga4     = hpv_opt( 'ga4_id' );
	$gtm     = hpv_opt( 'gtm_id' );
	?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
<?php if ( $consent ) : ?>
(function(){var c=null;try{c=localStorage.getItem('hpv-consent');}catch(e){}
gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:c==='granted'?'granted':'denied',wait_for_update:500});})();
<?php endif; ?>
<?php if ( $gtm ) : ?>
(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $gtm ); ?>');
<?php elseif ( $ga4 ) : ?>
gtag('js', new Date());
gtag('config', '<?php echo esc_js( $ga4 ); ?>');
<?php endif; ?>
</script>
	<?php if ( $ga4 && ! $gtm ) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4 ); ?>"></script>
	<?php endif; ?>
	<?php
}
add_action( 'wp_head', 'hpv_tracking_head', 3 );

/**
 * Consent banner (only when enabled and analytics is configured).
 */
function hpv_consent_banner() {
	if ( ! hpv_should_track() || ! hpv_opt( 'consent_banner' ) ) {
		return;
	}
	$privacy = get_privacy_policy_url() ? get_privacy_policy_url() : hpv_page_url( 'privacy-policy' );
	?>
<div class="consent" id="hpv-consent" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Cookie consent', 'hpv' ); ?>" hidden>
	<p><?php esc_html_e( 'I use analytics cookies to see which pages help owners find what they need. No ads, no selling data.', 'hpv' ); ?> <a class="link" href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'Privacy policy', 'hpv' ); ?></a></p>
	<div class="actions">
		<button class="btn btn--sm" type="button" data-consent="granted"><?php esc_html_e( 'Accept', 'hpv' ); ?></button>
		<button class="arrow-link" type="button" data-consent="denied"><?php esc_html_e( 'No thanks', 'hpv' ); ?></button>
	</div>
</div>
<script>
(function(){var b=document.getElementById('hpv-consent'),v=null;try{v=localStorage.getItem('hpv-consent');}catch(e){}
if(!v){b.hidden=false;}
b.addEventListener('click',function(e){var t=e.target.closest('[data-consent]');if(!t)return;var s=t.getAttribute('data-consent');
try{localStorage.setItem('hpv-consent',s);}catch(e){}
if(typeof gtag==='function'){gtag('consent','update',{analytics_storage:s});}b.hidden=true;});})();
</script>
	<?php
}
add_action( 'wp_footer', 'hpv_consent_banner', 5 );
