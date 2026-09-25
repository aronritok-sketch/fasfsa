<?php
/**
 * Pages. Designed pages start with a hero section inside their blocks; plain pages
 * (created later without a pattern) get a standard hero and a readable text column.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$hpv_content = get_the_content();

	if ( false !== strpos( $hpv_content, 'hero' ) ) {
		the_content();
	} else {
		?>
		<section class="hero hero--inner">
			<div class="wrap grid">
				<div class="hero__text col-9">
					<?php hpv_breadcrumbs(); ?>
					<h1 class="h1"><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<section class="section section--flush-top">
			<div class="wrap"><div class="legal entry"><?php the_content(); ?></div></div>
		</section>
		<?php
	}
endwhile;

get_footer();
