<?php
/**
 * Single case study: hero from the title and snapshot fields, story from the blocks.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$hpv_industries = get_the_terms( get_the_ID(), 'industry' );
	$hpv_eyebrow    = array( __( 'Case study', 'hpv' ) );
	if ( $hpv_industries && ! is_wp_error( $hpv_industries ) ) {
		$hpv_eyebrow[] = $hpv_industries[0]->name;
	}
	if ( get_post_meta( get_the_ID(), 'hpv_location', true ) ) {
		$hpv_eyebrow[] = get_post_meta( get_the_ID(), 'hpv_location', true );
	}
	?>
	<section class="hero hero--inner" id="hero">
		<div class="wrap grid">
			<div class="hero__text col-10">
				<?php hpv_breadcrumbs(); ?>
				<span class="eyebrow"><?php echo esc_html( implode( ' · ', $hpv_eyebrow ) ); ?></span>
				<h1 class="h1"><?php echo wp_kses( get_the_title(), hpv_inline_kses() ); ?></h1>
			</div>
		</div>
	</section>
	<?php
	the_content();
endwhile;

get_footer();
