<?php
/**
 * Wordmark: person first, role in hand lettering.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;
?>
<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
	<span class="wordmark__name"><?php bloginfo( 'name' ); ?></span>
	<span class="wordmark__role"><?php echo esc_html( get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : __( 'Digital Growth Strategist', 'hpv' ) ); ?></span>
</a>
