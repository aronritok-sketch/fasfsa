<?php
/**
 * Strategy call: two-step qualifying form → booking step.
 *
 * Without JavaScript the form posts to admin-post.php and lands on the thank-you page.
 * With JavaScript (assets/js/forms.js) it posts to the REST API and shows the booking step.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

$hpvf_status = isset( $_GET['hpvf_error'] ) ? sanitize_key( wp_unslash( $_GET['hpvf_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
$hpvf_select = static function ( $id, $name, $label, $options ) {
	printf( '<div class="field"><label for="%1$s">%2$s</label><select class="select" id="%1$s" name="%3$s" required><option value="">%4$s</option>', esc_attr( $id ), esc_html( $label ), esc_attr( $name ), esc_html__( 'Select…', 'hpv' ) );
	foreach ( $options as $o ) {
		printf( '<option>%s</option>', esc_html( $o ) );
	}
	echo '</select></div>';
};
?>
<div class="form-panel">
	<div class="form-steps" aria-hidden="true">
		<span class="is-current" id="step-ind-1"><?php esc_html_e( '1 · About you', 'hpv' ); ?></span>
		<span id="step-ind-2"><?php esc_html_e( '2 · Your business', 'hpv' ); ?></span>
		<span id="step-ind-3"><?php esc_html_e( '3 · Choose a time', 'hpv' ); ?></span>
	</div>

	<?php if ( $hpvf_status ) : ?>
		<p class="field__error" role="alert"><?php esc_html_e( 'Something was missing or looked automated. Please check the fields and try again.', 'hpv' ); ?></p>
	<?php endif; ?>

	<form class="form" id="call-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-form-id="strategy_call">
		<input type="hidden" name="action" value="hpvf_strategy_call">
		<input type="hidden" name="source_page" value="<?php echo esc_url( get_permalink() ); ?>">
		<input type="hidden" name="utm" id="cf-utm" value="">
		<div class="hp" aria-hidden="true"><label for="cf-company"><?php esc_html_e( 'Company', 'hpv' ); ?></label><input id="cf-company" name="company_hp" tabindex="-1" autocomplete="off"></div>
		<input type="hidden" name="ts" id="cf-ts" value="<?php echo esc_attr( (string) ( time() * 1000 ) ); ?>">

		<fieldset class="choice-group" id="call-step-1">
			<legend class="h3"><?php esc_html_e( 'Step 1 of 2 — How can I reach you?', 'hpv' ); ?></legend>
			<div class="form-row form-row--2 mt-3">
				<div class="field"><label for="cf-name"><?php esc_html_e( 'Name', 'hpv' ); ?></label><input class="input" id="cf-name" name="name" autocomplete="name" required></div>
				<div class="field"><label for="cf-email"><?php esc_html_e( 'Email', 'hpv' ); ?></label><input class="input" id="cf-email" name="email" type="email" autocomplete="email" required></div>
			</div>
			<div class="form-row form-row--2 mt-3">
				<div class="field"><label for="cf-phone"><?php esc_html_e( 'Phone', 'hpv' ); ?></label><input class="input" id="cf-phone" name="phone" type="tel" autocomplete="tel" required></div>
				<div class="field"><label for="cf-site"><?php esc_html_e( 'Company website', 'hpv' ); ?> <span class="hint"><?php esc_html_e( '(if you have one)', 'hpv' ); ?></span></label><input class="input" id="cf-site" name="website" type="url" inputmode="url" placeholder="https://" autocomplete="url"></div>
			</div>
			<div class="actions mt-4">
				<button class="btn" type="button" id="call-next"><?php esc_html_e( 'Continue', 'hpv' ); ?> <span aria-hidden="true">→</span></button>
			</div>
		</fieldset>

		<fieldset class="choice-group" id="call-step-2">
			<legend class="h3"><?php esc_html_e( 'Step 2 of 2 — A little about the business', 'hpv' ); ?></legend>
			<div class="form-row form-row--2 mt-3">
				<?php
				$hpvf_select( 'cf-industry', 'industry', __( 'Industry', 'hpv' ), hpvf_lead_choices( 'industry' ) );
				$hpvf_select( 'cf-city', 'city', __( 'City', 'hpv' ), hpvf_lead_choices( 'city' ) );
				?>
			</div>
			<div class="form-row form-row--2 mt-3">
				<?php
				$hpvf_select( 'cf-challenge', 'challenge', __( 'What’s the main challenge?', 'hpv' ), hpvf_lead_choices( 'challenge' ) );
				$hpvf_select( 'cf-budget', 'budget', __( 'Monthly marketing budget', 'hpv' ), hpvf_lead_choices( 'budget' ) );
				?>
			</div>
			<fieldset class="choice-group field mt-3">
				<legend><?php esc_html_e( 'Timing', 'hpv' ); ?></legend>
				<div class="chips">
					<?php foreach ( hpvf_lead_choices( 'timing' ) as $hpvf_i => $hpvf_t ) : ?>
						<label class="chip"><input type="radio" name="timing" value="<?php echo esc_attr( $hpvf_t ); ?>" id="cf-t<?php echo (int) $hpvf_i; ?>"<?php checked( 0, $hpvf_i ); ?>><span><?php echo esc_html( $hpvf_t ); ?></span></label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<div class="actions mt-4">
				<button class="btn" type="submit"><?php esc_html_e( 'Continue to choose a time', 'hpv' ); ?> <span aria-hidden="true">→</span></button>
				<button class="arrow-link" type="button" id="call-back"><?php esc_html_e( '← Back', 'hpv' ); ?></button>
			</div>
		</fieldset>

		<div id="call-step-3" hidden>
			<div class="booking-facade">
				<span class="eyebrow"><?php esc_html_e( 'Step 3 — Choose a time', 'hpv' ); ?></span>
				<h2 class="h3"><?php esc_html_e( 'Thanks,', 'hpv' ); ?> <span id="cf-first"><?php esc_html_e( 'there', 'hpv' ); ?></span>. <?php esc_html_e( 'Pick a 30-minute slot that suits you.', 'hpv' ); ?></h2>
				<p class="body"><?php esc_html_e( 'Your details are in. The calendar opens in a new tab with your name and email filled in.', 'hpv' ); ?></p>
				<a class="btn" id="call-book" href="<?php echo esc_url( hpvf_opt( 'booking_url' ) ? hpvf_opt( 'booking_url' ) : hpvf_page_url( 'strategy-call/thank-you' ) ); ?>" data-track="booking_open"<?php echo hpvf_opt( 'booking_url' ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php esc_html_e( 'Open the calendar', 'hpv' ); ?> <span aria-hidden="true">→</span></a>
				<?php if ( ! hpvf_opt( 'booking_url' ) && hpvf_is_editor_view() ) : ?>
					<p class="small"><span class="tbd"><?php esc_html_e( '[Set the booking page URL in Appearance → Customize → HelloProVision site]', 'hpv' ); ?></span></p>
				<?php endif; ?>
			</div>
		</div>

		<p class="form-note" id="call-reassure"><?php esc_html_e( 'You’ll get a confirmation email right away. If it’s not a fit, I’ll tell you — and point you somewhere better.', 'hpv' ); ?></p>
		<p class="form-note" role="status" aria-live="polite" id="call-status"></p>
	</form>
</div>
