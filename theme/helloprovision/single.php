<?php
/**
 * Insights article.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$hpv_topic   = hpv_primary_topic();
	$hpv_service = hpv_topic_service( $hpv_topic ? $hpv_topic->slug : '' );
	$hpv_minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) );
	?>
	<article>
		<section class="hero hero--inner" id="hero">
			<div class="wrap grid">
				<div class="hero__text col-9">
					<?php hpv_breadcrumbs(); ?>
					<span class="eyebrow"><?php echo $hpv_topic ? esc_html( $hpv_topic->name ) . ' · ' : ''; ?><?php /* translators: %d: minutes */ printf( esc_html__( '%d min read', 'hpv' ), (int) $hpv_minutes ); ?></span>
					<h1 class="h1"><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
					<div class="author mt-2">
						<?php hpv_author_photo(); ?>
						<div>
							<p class="author__name"><?php the_author(); ?></p>
							<p class="caption"><?php echo esc_html( get_bloginfo( 'description' ) ); ?> · <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="section section--flush-top">
			<div class="wrap grid">
				<div class="col-7 start-2 article-body">
					<?php the_content(); ?>
				</div>
				<aside class="col-3 article-aside" aria-label="<?php esc_attr_e( 'Related', 'hpv' ); ?>">
					<span class="eyebrow"><?php esc_html_e( 'Related', 'hpv' ); ?></span>
					<?php if ( $hpv_service ) : ?>
						<p><a class="arrow-link" href="<?php echo esc_url( $hpv_service[1] ); ?>"><?php echo esc_html( $hpv_service[0] ); ?> <span aria-hidden="true">→</span></a></p>
					<?php endif; ?>
					<?php
					$hpv_case = get_posts( array( 'post_type' => 'case_study', 'numberposts' => 1, 'orderby' => 'menu_order date', 'order' => 'ASC' ) );
					if ( $hpv_case ) :
						?>
						<p><a class="arrow-link" href="<?php echo esc_url( get_permalink( $hpv_case[0] ) ); ?>"><?php /* translators: %s: client */ printf( esc_html__( 'Case study: %s', 'hpv' ), esc_html( get_the_title( $hpv_case[0] ) ) ); ?> <span aria-hidden="true">→</span></a></p>
					<?php endif; ?>
					<hr class="rule">
					<?php
					$hpv_siblings = $hpv_topic ? get_posts( array( 'category' => $hpv_topic->term_id, 'numberposts' => 2, 'exclude' => array( get_the_ID() ) ) ) : array();
					foreach ( $hpv_siblings as $hpv_s ) :
						?>
						<p class="small"><a class="link" href="<?php echo esc_url( get_permalink( $hpv_s ) ); ?>"><?php echo esc_html( get_the_title( $hpv_s ) ); ?></a></p>
					<?php endforeach; ?>
					<?php if ( ! $hpv_siblings && $hpv_topic ) : ?>
						<?php $hpv_next = hpv_planned_articles()[ $hpv_topic->slug ][0] ?? ''; ?>
						<?php if ( $hpv_next ) : ?>
							<p class="small"><?php esc_html_e( 'Next in this series:', 'hpv' ); ?> <?php echo esc_html( $hpv_next ); ?> <span class="muted">(<?php esc_html_e( 'in writing', 'hpv' ); ?>)</span></p>
						<?php endif; ?>
					<?php endif; ?>
				</aside>
			</div>
		</section>
	</article>
	<?php
	echo hpv_render_pattern( 'hpv/scorecard-band' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks.
endwhile;

get_footer();
