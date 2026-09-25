<?php
/**
 * Fallback template (search results and anything without a specific template).
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="hero hero--inner" id="hero">
	<div class="wrap">
		<div class="hero__text">
			<span class="eyebrow"><?php echo is_search() ? esc_html__( 'Search', 'hpv' ) : esc_html__( 'Archive', 'hpv' ); ?></span>
			<h1 class="h1">
				<?php
				if ( is_search() ) {
					/* translators: %s: search query */
					printf( esc_html__( 'Results for “%s”', 'hpv' ), esc_html( get_search_query() ) );
				} else {
					echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
				}
				?>
			</h1>
			<?php get_search_form(); ?>
		</div>
	</div>
</section>
<section class="section section--flush-top">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<div class="insights">
				<?php
				while ( have_posts() ) {
					the_post();
					echo hpv_insight_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				}
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p class="lead"><?php esc_html_e( 'Nothing found. Try the Growth Scorecard, or book a call and ask directly.', 'hpv' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
