<?php
/**
 * Site footer.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

$hpv_phone = hpv_opt( 'phone' );
$hpv_email = hpv_opt( 'email' );
?>
</main>

<?php if ( ! hpv_is_blank_canvas() ) : ?>
<footer class="site-footer on-dark">
	<div class="wrap">
		<div class="footer__top">
			<div class="footer__brand">
				<?php get_template_part( 'template-parts/wordmark' ); ?>
				<p><?php echo esc_html( hpv_opt( 'positioning' ) ); ?></p>
				<p class="footer__area"><?php echo esc_html( hpv_opt( 'service_area' ) ); ?></p>
			</div>
			<div class="footer__col">
				<h2><?php esc_html_e( 'Services', 'hpv' ); ?></h2>
				<ul><?php hpv_menu_items( 'footer_services', 'plain' ); ?></ul>
			</div>
			<div class="footer__col">
				<h2><?php esc_html_e( 'Explore', 'hpv' ); ?></h2>
				<ul><?php hpv_menu_items( 'footer_explore', 'plain' ); ?></ul>
			</div>
			<div class="footer__col">
				<h2><?php esc_html_e( 'Contact', 'hpv' ); ?></h2>
				<?php
				$hpv_tbd   = static function ( $placeholder ) {
					return hpv_is_editor_view() ? '<span class="tbd">' . esc_html( $placeholder ) . '</span>' : '';
				};
				$hpv_lines = array(
					hpv_opt( 'legal_name' ) ? esc_html( hpv_opt( 'legal_name' ) ) : $hpv_tbd( '[HelloProVision LLC]' ),
					esc_html( hpv_opt( 'address' ) ? hpv_opt( 'address' ) : __( 'Serving Southwest Florida', 'hpv' ) ),
					$hpv_phone ? '<a href="' . esc_url( hpv_tel( $hpv_phone ) ) . '" data-track="click_to_call">' . esc_html( $hpv_phone ) . '</a>' : $hpv_tbd( '[+1 (239) 000-0000]' ),
					$hpv_email ? '<a href="mailto:' . esc_attr( antispambot( $hpv_email ) ) . '" data-track="click_to_email">' . esc_html( antispambot( $hpv_email ) ) . '</a>' : $hpv_tbd( '[hello@domain]' ),
				);
				?>
				<address><?php echo implode( '<br>', array_filter( $hpv_lines ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each line escaped above. ?></address>
				<?php if ( hpv_opt( 'linkedin' ) ) : ?>
					<ul><li><a href="<?php echo esc_url( hpv_opt( 'linkedin' ) ); ?>" rel="noopener me">LinkedIn</a></li></ul>
				<?php endif; ?>
			</div>
		</div>
		<p class="footer__giant" aria-hidden="true"><?php echo wp_kses( hpv_giant_wordmark(), array( 'span' => array() ) ); ?></p>
		<div class="footer__bottom">
			<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> · <?php esc_html_e( 'Founder, HelloProVision', 'hpv' ); ?></p>
			<ul><?php hpv_menu_items( 'legal', 'plain' ); ?></ul>
		</div>
	</div>
</footer>

<?php if ( hpv_is_editor_view() ) : ?>
<div class="proto-bar" id="proto-bar" role="region" aria-label="<?php esc_attr_e( 'Editor notes', 'hpv' ); ?>">
	<span><span class="proto-bar__label"><?php esc_html_e( 'Editor view', 'hpv' ); ?> · </span><span class="proto-bar__count" id="tbd-count">0</span><span class="proto-bar__label"> <?php esc_html_e( 'facts to verify', 'hpv' ); ?></span></span>
	<button type="button" id="tbd-toggle" aria-pressed="true"><?php esc_html_e( 'Hide marks', 'hpv' ); ?></button>
</div>
<?php endif; ?>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
