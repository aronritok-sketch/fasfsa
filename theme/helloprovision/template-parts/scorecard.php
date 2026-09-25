<?php
/**
 * Digital Growth Scorecard: lead capture → 15 questions → results (assets/js/scorecard.js).
 * Results are shown instantly; the answers are saved as a lead and emailed via the REST API.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

$hpv_call = hpv_page_url( 'strategy-call' );
?>
<section class="section section--flush-top" id="scorecard-start">
	<div class="wrap grid">
		<div class="col-5 stack-lg">
			<h2 class="h3"><?php esc_html_e( 'What you get', 'hpv' ); ?></h2>
			<ul class="fit fit--yes">
				<li><?php esc_html_e( 'Your score across five areas', 'hpv' ); ?></li>
				<li><?php esc_html_e( 'Your #1 priority', 'hpv' ); ?></li>
				<li><?php esc_html_e( 'Three practical next steps you can take this month', 'hpv' ); ?></li>
			</ul>
		</div>
		<div class="col-6 start-7">
			<form class="form form-panel" id="sc-lead" novalidate data-form-id="scorecard">
				<div class="hp" aria-hidden="true"><label for="sc-hp"><?php esc_html_e( 'Company', 'hpv' ); ?></label><input id="sc-hp" name="company_hp" tabindex="-1" autocomplete="off"></div>
				<input type="hidden" name="ts" id="sc-ts" value="<?php echo esc_attr( (string) ( time() * 1000 ) ); ?>">
				<div class="field"><label for="sc-name"><?php esc_html_e( 'Name', 'hpv' ); ?></label><input class="input" id="sc-name" name="name" autocomplete="name" required></div>
				<div class="field"><label for="sc-email"><?php esc_html_e( 'Email', 'hpv' ); ?></label><input class="input" id="sc-email" name="email" type="email" autocomplete="email" required></div>
				<div class="field"><label for="sc-site"><?php esc_html_e( 'Company website', 'hpv' ); ?></label><input class="input" id="sc-site" name="website" type="url" inputmode="url" placeholder="https://" autocomplete="url"></div>
				<button class="btn" type="submit"><?php esc_html_e( 'Start the scorecard', 'hpv' ); ?> <span aria-hidden="true">→</span></button>
				<p class="form-note"><?php esc_html_e( 'Your results appear on screen right away. I’ll also email them to you — no newsletter, no spam.', 'hpv' ); ?></p>
			</form>
		</div>
	</div>
</section>

<section class="section section--flush-top" id="scorecard-quiz" hidden>
	<div class="wrap grid">
		<div class="col-8 start-3">
			<div class="score-progress" aria-live="polite">
				<div class="score-progress__bar"><i id="sc-bar"></i></div>
				<span class="score-progress__txt" id="sc-progress"><?php esc_html_e( '0 of 15 answered', 'hpv' ); ?></span>
			</div>
			<form id="sc-quiz" novalidate>
				<div id="sc-questions"></div>
				<div class="actions mt-6">
					<button class="btn" type="submit" id="sc-submit"><?php esc_html_e( 'See my results', 'hpv' ); ?> <span aria-hidden="true">→</span></button>
					<span class="small" id="sc-missing" role="status"></span>
				</div>
			</form>
		</div>
	</div>
</section>

<section class="section section--flush-top" id="scorecard-results" hidden tabindex="-1">
	<div class="wrap grid">
		<div class="col-4 stack">
			<span class="eyebrow"><?php esc_html_e( 'Your score', 'hpv' ); ?></span>
			<p class="score-total"><span id="sc-total">0</span><small>/100</small></p>
			<p class="body" id="sc-summary"></p>
		</div>
		<div class="col-7 start-6 score-results">
			<div class="score-bars" id="sc-bars"></div>
			<div class="stack">
				<p class="label"><?php esc_html_e( 'Your #1 priority', 'hpv' ); ?> · <span id="sc-weakest"></span></p>
				<p class="body" id="sc-why"></p>
				<h2 class="h3"><?php esc_html_e( 'Three next steps for this month', 'hpv' ); ?></h2>
				<ol class="steps steps--compact" id="sc-steps"></ol>
				<p class="small"><?php esc_html_e( 'Recommended reading:', 'hpv' ); ?> <a class="link" id="sc-article" href="<?php echo esc_url( get_permalink( get_option( 'page_for_posts' ) ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) ); ?>"></a></p>
			</div>
			<div class="form-panel">
				<h2 class="h3"><?php esc_html_e( 'Want me to review your results with you?', 'hpv' ); ?></h2>
				<p class="body"><?php esc_html_e( 'In a 30-minute call we’ll go through your scores and your website together, and decide what to fix first.', 'hpv' ); ?></p>
				<div class="actions"><a class="btn" href="<?php echo esc_url( $hpv_call ); ?>"><?php esc_html_e( 'Book a strategy call', 'hpv' ); ?> <span aria-hidden="true">→</span></a></div>
			</div>
		</div>
	</div>
</section>
<noscript><div class="wrap"><p class="body"><?php esc_html_e( 'The scorecard needs JavaScript. You can also', 'hpv' ); ?> <a class="link" href="<?php echo esc_url( $hpv_call ); ?>"><?php esc_html_e( 'book a strategy call', 'hpv' ); ?></a> <?php esc_html_e( 'and we’ll go through it together.', 'hpv' ); ?></p></div></noscript>
