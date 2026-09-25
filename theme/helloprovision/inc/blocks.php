<?php
/**
 * Dynamic blocks, pattern categories and the renderers shared with templates.
 *
 * Every block is server-rendered (PHP below) and edited through a small no-build editor
 * script (assets/js/editor/blocks.js) that shows a live ServerSideRender preview plus
 * sidebar controls. No npm build step is needed to maintain the theme.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Registration
 * ---------------------------------------------------------------------- */

/**
 * Editor script + block registration.
 */
function hpv_register_blocks() {
	$file = HPV_DIR . '/assets/js/editor/blocks.js';
	wp_register_script(
		'hpv-editor',
		HPV_URI . '/assets/js/editor/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-rich-text', 'wp-data', 'wp-core-data', 'wp-plugins', 'wp-editor' ),
		file_exists( $file ) ? (string) filemtime( $file ) : HPV_VERSION,
		true
	);
	wp_localize_script(
		'hpv-editor',
		'hpvEditor',
		array(
			'industries' => hpv_term_options( 'industry' ),
			'topics'     => hpv_term_options( 'category' ),
		)
	);

	$common = array(
		'api_version'           => 3,
		'category'              => 'hpv',
		'editor_script_handles' => array( 'hpv-editor' ),
		'supports'              => array( 'html' => false, 'className' => true, 'customClassName' => true ),
	);

	$blocks = array(
		'hpv/photo'               => array(
			'attributes'      => array(
				'imageId' => array( 'type' => 'integer', 'default' => 0 ),
				'src'     => array( 'type' => 'string', 'default' => '' ),
				'ratio'   => array( 'type' => 'string', 'default' => 'portrait' ),
				'alt'     => array( 'type' => 'string', 'default' => '' ),
				'note'    => array( 'type' => 'string', 'default' => 'Photo|Shoot pending' ),
				'dark'    => array( 'type' => 'boolean', 'default' => false ),
				'eager'   => array( 'type' => 'boolean', 'default' => false ),
			),
			'render_callback' => 'hpv_render_photo',
		),
		'hpv/project'             => array(
			'attributes'      => array(
				'name'    => array( 'type' => 'string', 'default' => 'Project' ),
				'imageId' => array( 'type' => 'integer', 'default' => 0 ),
				'src'     => array( 'type' => 'string', 'default' => '' ),
				'ratio'   => array( 'type' => 'string', 'default' => 'land' ),
				'tone'    => array( 'type' => 'integer', 'default' => 0 ),
				'alt'     => array( 'type' => 'string', 'default' => '' ),
				'eager'   => array( 'type' => 'boolean', 'default' => false ),
			),
			'render_callback' => 'hpv_render_project',
		),
		'hpv/case-grid'           => array(
			'attributes'      => array(
				'layout'   => array( 'type' => 'string', 'default' => 'hub' ),
				'count'    => array( 'type' => 'integer', 'default' => 12 ),
				'upcoming' => array( 'type' => 'string', 'default' => '' ),
				'filter'   => array( 'type' => 'boolean', 'default' => false ),
			),
			'render_callback' => 'hpv_render_case_grid',
		),
		'hpv/insights'            => array(
			'attributes'      => array(
				'topic' => array( 'type' => 'string', 'default' => '' ),
				'count' => array( 'type' => 'integer', 'default' => 3 ),
				'fill'  => array( 'type' => 'boolean', 'default' => true ),
			),
			'render_callback' => 'hpv_render_insights',
		),
		'hpv/breadcrumbs'         => array( 'render_callback' => 'hpv_render_breadcrumbs_block' ),
		'hpv/case-snapshot'       => array( 'render_callback' => 'hpv_render_case_snapshot' ),
		'hpv/case-result'         => array( 'render_callback' => 'hpv_render_case_result' ),
		'hpv/contact'             => array( 'render_callback' => 'hpv_render_contact' ),
		'hpv/strategy-call-form'  => array( 'render_callback' => 'hpv_render_strategy_form' ),
		'hpv/scorecard'           => array( 'render_callback' => 'hpv_render_scorecard' ),
		'hpv/ticker'              => array(
			'attributes'      => array(
				'items' => array( 'type' => 'string', 'default' => "More calls\nFort Myers\nLess guesswork\nNaples\nOne partner\nCape Coral\nReal numbers\nSouthwest Florida" ),
			),
			'render_callback' => 'hpv_render_ticker',
		),
	);

	foreach ( $blocks as $name => $args ) {
		register_block_type( $name, array_merge( $common, $args ) );
	}
}
add_action( 'init', 'hpv_register_blocks' );

/**
 * Block inserter category.
 *
 * @param array $categories Categories.
 */
function hpv_block_categories( $categories ) {
	array_unshift( $categories, array( 'slug' => 'hpv', 'title' => __( 'HelloProVision', 'hpv' ) ) );
	return $categories;
}
add_filter( 'block_categories_all', 'hpv_block_categories' );

/**
 * Pattern categories (patterns themselves are auto-registered from /patterns).
 */
function hpv_pattern_categories() {
	register_block_pattern_category( 'hpv-sections', array( 'label' => __( 'HelloProVision: sections', 'hpv' ) ) );
	register_block_pattern_category( 'hpv-pages', array( 'label' => __( 'HelloProVision: full pages', 'hpv' ) ) );
}
add_action( 'init', 'hpv_pattern_categories', 9 );

/**
 * Editor-only assets: the design CSS is loaded via add_editor_style; this adds the
 * sidebar panels and formats even where no block of ours is on the page yet.
 */
function hpv_editor_assets() {
	wp_enqueue_script( 'hpv-editor' );
}
add_action( 'enqueue_block_editor_assets', 'hpv_editor_assets' );

/**
 * Terms as select options for the editor.
 *
 * @param string $taxonomy Taxonomy.
 */
function hpv_term_options( $taxonomy ) {
	$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	$out   = array( array( 'label' => __( 'All', 'hpv' ), 'value' => '' ) );
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$out[] = array( 'label' => $t->name, 'value' => $t->slug );
		}
	}
	return $out;
}

/**
 * Render a registered pattern's blocks (used by archive templates).
 *
 * @param string $slug Pattern name.
 */
function hpv_render_pattern( $slug ) {
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
	return $pattern ? do_blocks( $pattern['content'] ) : '';
}

/* -------------------------------------------------------------------------
 * Images
 * ---------------------------------------------------------------------- */

/**
 * A bundled image in assets/img/{src}.{ext}, if the theme ships one.
 *
 * @param string $src Base path without extension.
 * @return string URL or ''.
 */
function hpv_theme_image( $src ) {
	if ( ! $src ) {
		return '';
	}
	foreach ( array( 'avif', 'webp', 'jpg', 'jpeg', 'png' ) as $ext ) {
		if ( file_exists( HPV_DIR . '/assets/img/' . $src . '.' . $ext ) ) {
			return HPV_URI . '/assets/img/' . $src . '.' . $ext;
		}
	}
	return '';
}

/**
 * Attachment or bundled image tag.
 *
 * @param int    $id    Attachment ID.
 * @param string $src   Bundled fallback.
 * @param string $class Class.
 * @param string $alt   Alt text.
 * @param bool   $eager Above the fold.
 * @param string $size  Image size.
 */
function hpv_img( $id, $src, $class, $alt, $eager, $size = 'large' ) {
	$load = $eager ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' );
	if ( $id && wp_attachment_is_image( $id ) ) {
		return wp_get_attachment_image( $id, $size, false, array_merge( array( 'class' => $class, 'alt' => $alt ? $alt : get_post_meta( $id, '_wp_attachment_image_alt', true ), 'decoding' => 'async' ), $load ) );
	}
	$url = hpv_theme_image( $src );
	if ( $url ) {
		return sprintf( '<img class="%s" src="%s" alt="%s" loading="%s" decoding="async"%s>', esc_attr( $class ), esc_url( $url ), esc_attr( $alt ), $eager ? 'eager' : 'lazy', $eager ? ' fetchpriority="high"' : '' );
	}
	return '';
}

/**
 * hpv/photo: a real photo, or a halftone polaroid until the shoot is done.
 *
 * @param array $a Attributes.
 */
function hpv_render_photo( $a ) {
	$a     = wp_parse_args( $a, array( 'imageId' => 0, 'src' => '', 'ratio' => 'portrait', 'alt' => '', 'note' => '', 'dark' => false, 'eager' => false, 'className' => '' ) );
	$ratio = in_array( $a['ratio'], array( 'portrait', 'land', 'wide', 'square' ), true ) ? $a['ratio'] : 'portrait';
	$img   = hpv_img( (int) $a['imageId'], $a['src'], trim( 'photo-img photo-img--' . $ratio . ' ' . $a['className'] ), $a['alt'], (bool) $a['eager'], 'portrait' === $ratio ? 'hpv-portrait' : 'large' );
	if ( $img ) {
		return $img;
	}
	$parts = array_pad( explode( '|', (string) $a['note'], 2 ), 2, '' );
	return sprintf(
		'<div class="%s" role="img" aria-label="%s"><span class="photo__note"><span>%s</span><span>%s</span></span></div>',
		esc_attr( trim( 'photo photo--' . $ratio . ( $a['dark'] ? ' photo--dark' : '' ) . ' ' . $a['className'] ) ),
		esc_attr( $a['alt'] ? $a['alt'] : __( 'Photo coming soon', 'hpv' ) ),
		esc_html( $parts[0] ),
		esc_html( $parts[1] )
	);
}

/**
 * hpv/project: a project screenshot taped to the wall (browser frame).
 *
 * @param array $a Attributes.
 */
function hpv_render_project( $a ) {
	$a     = wp_parse_args( $a, array( 'name' => '', 'imageId' => 0, 'src' => '', 'ratio' => 'land', 'tone' => 0, 'alt' => '', 'eager' => false, 'className' => '' ) );
	$ratio = in_array( $a['ratio'], array( 'portrait', 'land', 'wide', 'square' ), true ) ? $a['ratio'] : 'land';
	$tone  = (int) $a['tone'] >= 1 && (int) $a['tone'] <= 6 ? (int) $a['tone'] : ( abs( crc32( $a['name'] . $ratio ) ) % 6 ) + 1;
	/* translators: %s: project name */
	$alt  = $a['alt'] ? $a['alt'] : sprintf( __( '%s — project by HelloProVision', 'hpv' ), $a['name'] );
	$img  = hpv_img( (int) $a['imageId'], $a['src'], '', $alt, (bool) $a['eager'], 'hpv-shot' );
	$view = $img ? $img : sprintf(
		'<div class="shot__ph" aria-hidden="true"><span class="shot__nav"><i></i><i></i><i></i></span><span class="shot__title">%s</span><span class="shot__lines"><i></i><i></i><i></i></span><span class="shot__btn"></span></div>',
		esc_html( $a['name'] )
	);
	return sprintf(
		'<figure class="%s"%s><div class="shot__bar" aria-hidden="true"><i></i><i></i><i></i><span>%s</span></div><div class="shot__view">%s</div></figure>',
		esc_attr( trim( 'shot shot--' . $ratio . ' tone-' . $tone . ' ' . $a['className'] ) ),
		$img ? '' : ' role="img" aria-label="' . esc_attr( $alt ) . '"',
		esc_html( $a['name'] ),
		$view
	);
}

/* -------------------------------------------------------------------------
 * Case studies
 * ---------------------------------------------------------------------- */

/**
 * One case-study card.
 *
 * @param WP_Post $post     Case study.
 * @param bool    $featured Featured layout.
 * @param int     $index    Position (varies the placeholder tone).
 */
function hpv_case_card( $post, $featured = false, $index = 0 ) {
	$industries = get_the_terms( $post, 'industry' );
	$industry   = ( $industries && ! is_wp_error( $industries ) ) ? $industries[0] : null;
	$meta       = array_filter( array( get_post_meta( $post->ID, 'hpv_client', true ) ? get_post_meta( $post->ID, 'hpv_client', true ) : get_the_title( $post ), $industry ? $industry->name : '', get_post_meta( $post->ID, 'hpv_location', true ) ) );
	$title      = get_post_meta( $post->ID, 'hpv_card_title', true );
	$shot       = hpv_render_project(
		array(
			'name'    => get_post_meta( $post->ID, 'hpv_client', true ) ? get_post_meta( $post->ID, 'hpv_client', true ) : get_the_title( $post ),
			'imageId' => (int) get_post_thumbnail_id( $post ),
			'ratio'   => 'land',
			'tone'    => $featured ? 1 : ( $index % 6 ) + 1,
		)
	);
	return sprintf(
		'<article class="case-card%s" data-industry="%s">%s<span class="case-card__meta">%s</span><h3 class="case-card__title">%s</h3>%s<p><a class="arrow-link" href="%s">%s <span aria-hidden="true">→</span></a></p></article>',
		$featured ? ' case-card--featured' : '',
		esc_attr( $industry ? $industry->slug : '' ),
		$shot,
		esc_html( implode( ' · ', $meta ) ),
		wp_kses( $title ? $title : get_the_title( $post ), hpv_inline_kses() ),
		has_excerpt( $post ) && $featured ? '<p class="body">' . esc_html( get_the_excerpt( $post ) ) . '</p>' : '',
		esc_url( get_permalink( $post ) ),
		esc_html__( 'Read the case study', 'hpv' )
	);
}

/**
 * hpv/case-grid: published case studies (featured first) + "in preparation" cards.
 *
 * @param array $a Attributes.
 */
function hpv_render_case_grid( $a ) {
	$a     = wp_parse_args( $a, array( 'layout' => 'hub', 'count' => 12, 'upcoming' => '', 'filter' => false ) );
	$posts = get_posts(
		array(
			'post_type'   => 'case_study',
			'numberposts' => max( 1, (int) $a['count'] ),
			'orderby'     => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			'tax_query'   => is_tax( 'industry' ) ? array( array( 'taxonomy' => 'industry', 'terms' => get_queried_object_id() ) ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		)
	);
	usort(
		$posts,
		static function ( $x, $y ) {
			return (int) get_post_meta( $y->ID, 'hpv_featured', true ) <=> (int) get_post_meta( $x->ID, 'hpv_featured', true );
		}
	);

	$cards = array();
	foreach ( $posts as $i => $post ) {
		$cards[] = hpv_case_card( $post, 0 === $i, $i );
	}

	// Projects without a published case study yet.
	$titles   = array_map( static function ( $p ) { return strtolower( get_the_title( $p ) ); }, $posts );
	$upcoming = array_filter( array_map( 'trim', explode( ',', (string) $a['upcoming'] ) ) );
	$slots    = max( 0, (int) $a['count'] - count( $cards ) );
	foreach ( array_slice( $upcoming, 0, $slots ) as $j => $name ) {
		if ( in_array( strtolower( $name ), $titles, true ) ) {
			continue;
		}
		$cards[] = sprintf(
			'<article class="case-card" data-industry="">%s<span class="case-card__meta">%s</span><h3 class="case-card__title">%s</h3><span class="caption">%s</span></article>',
			hpv_render_project( array( 'name' => $name, 'ratio' => 'land', 'tone' => ( ( count( $cards ) + $j ) % 6 ) + 1 ) ),
			esc_html( $name ),
			esc_html__( 'Results write-up in preparation', 'hpv' ),
			esc_html__( 'Case study coming soon', 'hpv' )
		);
	}

	if ( ! $cards ) {
		return '<p class="lead">' . esc_html__( 'Case studies are being written up. Book a call and I’ll walk you through recent work.', 'hpv' ) . '</p>';
	}

	$filter = '';
	if ( $a['filter'] ) {
		$terms = get_terms( array( 'taxonomy' => 'industry', 'hide_empty' => true ) );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$filter  = '<div class="chips" role="group" aria-label="' . esc_attr__( 'Filter by industry', 'hpv' ) . '">';
			$filter .= '<label class="chip"><input type="radio" name="industry" value="all" checked><span>' . esc_html__( 'All work', 'hpv' ) . '</span></label>';
			foreach ( $terms as $t ) {
				$filter .= sprintf( '<label class="chip"><input type="radio" name="industry" value="%s"><span>%s</span></label>', esc_attr( $t->slug ), esc_html( $t->name ) );
			}
			$filter .= '</div>';
		}
	}

	$class = 'home' === $a['layout'] ? 'cases cases--scroll' : 'cases mt-6';
	return $filter . '<div class="' . esc_attr( $class ) . '" id="case-list">' . implode( '', $cards ) . '</div><p class="small mt-6" id="case-empty" hidden>' . esc_html__( 'No case studies in this industry yet.', 'hpv' ) . '</p>';
}

/**
 * hpv/case-snapshot: the sticky facts panel of a case study.
 *
 * @param array $a Block attributes.
 */
function hpv_render_case_snapshot( $a = array() ) {
	$class = trim( 'snapshot ' . ( $a['className'] ?? '' ) );
	$id    = get_the_ID();
	$terms = get_the_terms( $id, 'industry' );
	$rows  = array(
		__( 'Client', 'hpv' )   => get_post_meta( $id, 'hpv_client', true ),
		__( 'Industry', 'hpv' ) => ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '',
		__( 'Location', 'hpv' ) => get_post_meta( $id, 'hpv_location', true ),
		__( 'Services', 'hpv' ) => get_post_meta( $id, 'hpv_services', true ),
		__( 'Timeline', 'hpv' ) => get_post_meta( $id, 'hpv_timeline', true ),
		__( 'Website', 'hpv' )  => get_post_meta( $id, 'hpv_website', true ),
	);
	$out = '<aside class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Project snapshot', 'hpv' ) . '"><dl>';
	foreach ( $rows as $label => $value ) {
		if ( '' === (string) $value && ! hpv_is_editor_view() ) {
			continue;
		}
		$shown = '' === (string) $value ? '<span class="tbd">[' . esc_html( $label ) . ']</span>' : esc_html( $value );
		$out  .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . $shown . '</dd></div>';
	}
	return $out . '</dl></aside>';
}

/**
 * hpv/case-result: the headline number of a case study.
 */
function hpv_render_case_result() {
	$id     = get_the_ID();
	$value  = get_post_meta( $id, 'hpv_result_value', true );
	$ctx    = get_post_meta( $id, 'hpv_result_context', true );
	$source = get_post_meta( $id, 'hpv_result_source', true );
	if ( ! $value && ! hpv_is_editor_view() ) {
		return '';
	}
	return sprintf(
		'<div class="stat stat--headline"><span class="eyebrow">%s</span><span class="stat__num">%s</span><span class="stat__ctx">%s</span>%s</div>',
		esc_html__( 'Headline result', 'hpv' ),
		$value ? esc_html( $value ) : '<span class="tbd">[X]</span>',
		$ctx ? esc_html( $ctx ) : '<span class="tbd">[' . esc_html__( 'what, and in what timeframe', 'hpv' ) . ']</span>',
		$source ? '<span class="stat__src">' . esc_html__( 'Source:', 'hpv' ) . ' ' . esc_html( $source ) . '</span>' : ''
	);
}

/* -------------------------------------------------------------------------
 * Insights
 * ---------------------------------------------------------------------- */

/**
 * The article's main topic (first category that isn't "Uncategorized").
 *
 * @param WP_Post|int|null $post Post.
 * @return WP_Term|null
 */
function hpv_primary_topic( $post = null ) {
	$cats = get_the_category( $post ? ( is_object( $post ) ? $post->ID : $post ) : get_the_ID() );
	foreach ( $cats as $c ) {
		if ( 'uncategorized' !== $c->slug ) {
			return $c;
		}
	}
	return null;
}

/**
 * Service page linked to a topic (blueprint internal-linking rules).
 *
 * @param string $slug Topic slug.
 * @return array|null array( name, url ).
 */
function hpv_topic_service( $slug ) {
	$map = array(
		'websites'  => array( __( 'Website Design & Development', 'hpv' ), 'services/website-design-development' ),
		'local-seo' => array( __( 'Local SEO', 'hpv' ), 'services/local-seo' ),
		'growth'    => array( __( 'Digital Growth Strategy', 'hpv' ), 'services/digital-growth-strategy' ),
	);
	if ( empty( $map[ $slug ] ) ) {
		return null;
	}
	return array( $map[ $slug ][0], hpv_page_url( $map[ $slug ][1] ) );
}

/**
 * One insight card for a published post.
 *
 * @param WP_Post $post Post.
 */
function hpv_insight_card( $post ) {
	if ( 'post' === $post->post_type ) {
		$topic   = hpv_primary_topic( $post );
		$label   = $topic ? $topic->name : __( 'Insights', 'hpv' );
		$minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $post->post_content ) ) / 220 ) );
		/* translators: %d: minutes */
		$meta = sprintf( __( '%d min read', 'hpv' ), $minutes );
	} else {
		// Search results can also be pages and case studies.
		$label = 'case_study' === $post->post_type ? __( 'Case study', 'hpv' ) : __( 'Page', 'hpv' );
		$meta  = '';
	}
	$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : get_post_meta( $post->ID, 'hpv_seo_description', true );
	return sprintf(
		'<article class="insight-card"><span class="insight-card__topic">%s</span><h3 class="insight-card__title"><a href="%s">%s</a></h3>%s%s</article>',
		esc_html( $label ),
		esc_url( get_permalink( $post ) ),
		esc_html( wp_strip_all_tags( get_the_title( $post ) ) ),
		$excerpt ? '<p>' . esc_html( $excerpt ) . '</p>' : '',
		$meta ? '<span class="insight-card__meta">' . esc_html( $meta ) . '</span>' : ''
	);
}

/**
 * Keep noindexed pages (thank-you, drafts of legal pages) out of site search.
 *
 * @param WP_Query $query Query.
 */
function hpv_search_exclude_noindex( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			array( 'key' => 'hpv_noindex', 'compare' => 'NOT EXISTS' ),
			array( 'key' => 'hpv_noindex', 'value' => '1', 'compare' => '!=' ),
		)
	);
	$query->set( 'post__not_in', array_filter( array( (int) get_option( 'page_on_front' ) ) ) );
}
add_action( 'pre_get_posts', 'hpv_search_exclude_noindex' );

/**
 * hpv/insights: latest articles (optionally one topic), filled with "In writing" cards.
 *
 * @param array $a Attributes.
 */
function hpv_render_insights( $a ) {
	$a     = wp_parse_args( $a, array( 'topic' => '', 'count' => 3, 'fill' => true ) );
	$count = max( 1, (int) $a['count'] );
	$args  = array( 'numberposts' => $count, 'post_status' => 'publish' );
	if ( $a['topic'] ) {
		$args['category_name'] = $a['topic'];
	}
	if ( is_singular( 'post' ) ) {
		$args['exclude'] = array( get_the_ID() );
	}
	$posts = get_posts( $args );
	$cards = array_map( 'hpv_insight_card', $posts );

	if ( $a['fill'] && count( $cards ) < $count ) {
		$planned = hpv_planned_articles();
		$pool    = $a['topic'] ? array( $a['topic'] => $planned[ $a['topic'] ] ?? array() ) : $planned;
		$titles  = array_map( static function ( $p ) { return strtolower( get_the_title( $p ) ); }, $posts );
		$terms   = array();
		foreach ( $pool as $slug => $list ) {
			foreach ( $list as $title ) {
				if ( count( $cards ) >= $count ) {
					break 2;
				}
				if ( in_array( strtolower( $title ), $titles, true ) ) {
					continue;
				}
				if ( ! isset( $terms[ $slug ] ) ) {
					$term           = get_category_by_slug( $slug );
					$terms[ $slug ] = $term ? $term->name : ucwords( str_replace( '-', ' ', $slug ) );
				}
				$cards[] = sprintf(
					'<article class="insight-card insight-card--draft"><span class="insight-card__topic">%s</span><h3 class="insight-card__title">%s</h3><span class="insight-card__meta">%s</span></article>',
					esc_html( $terms[ $slug ] ),
					esc_html( $title ),
					esc_html__( 'In writing', 'hpv' )
				);
				if ( ! $a['topic'] ) {
					continue 2; // One planned title per topic keeps the mix varied.
				}
			}
		}
	}

	return $cards ? '<div class="insights">' . implode( '', $cards ) . '</div>' : '';
}

/* -------------------------------------------------------------------------
 * Small blocks
 * ---------------------------------------------------------------------- */

/**
 * Breadcrumb trail items for the current request.
 *
 * @return array[] Each: array( name, url ).
 */
function hpv_breadcrumb_items() {
	if ( is_front_page() ) {
		return array();
	}
	$items = array( array( __( 'Home', 'hpv' ), home_url( '/' ) ) );
	$posts = (int) get_option( 'page_for_posts' );
	// In the editor preview (block renderer REST call) there is no main query, only the post.
	$ctx  = ( defined( 'REST_REQUEST' ) && REST_REQUEST && get_post() ) ? get_post()->post_type : '';
	$is   = static function ( $type ) use ( $ctx ) {
		return is_singular( $type ) || $ctx === $type;
	};

	if ( $is( 'case_study' ) ) {
		$items[] = array( __( 'Case Studies', 'hpv' ), get_post_type_archive_link( 'case_study' ) );
		$items[] = array( get_post_meta( get_the_ID(), 'hpv_client', true ) ? get_post_meta( get_the_ID(), 'hpv_client', true ) : wp_strip_all_tags( get_the_title() ), '' );
	} elseif ( is_post_type_archive( 'case_study' ) ) {
		$items[] = array( __( 'Case Studies', 'hpv' ), '' );
	} elseif ( $is( 'post' ) ) {
		$items[] = array( $posts ? get_the_title( $posts ) : __( 'Insights', 'hpv' ), $posts ? get_permalink( $posts ) : home_url( '/insights/' ) );
		$topic   = hpv_primary_topic();
		$items[] = $topic ? array( $topic->name, '' ) : array( get_the_title(), '' );
	} elseif ( is_category() ) {
		$items[] = array( $posts ? get_the_title( $posts ) : __( 'Insights', 'hpv' ), $posts ? get_permalink( $posts ) : home_url( '/insights/' ) );
		$items[] = array( single_cat_title( '', false ), '' );
	} elseif ( is_home() && $posts ) {
		$items[] = array( get_the_title( $posts ), '' );
	} elseif ( $is( 'page' ) ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $ancestor ) {
			$items[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
		}
		$items[] = array( get_the_title(), '' );
	}
	return count( $items ) > 1 ? $items : array();
}

/**
 * Print breadcrumbs.
 */
function hpv_breadcrumbs() {
	echo hpv_render_breadcrumbs_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
}

/**
 * hpv/breadcrumbs.
 */
function hpv_render_breadcrumbs_block() {
	$items = hpv_breadcrumb_items();
	if ( ! $items ) {
		return '';
	}
	$out = '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'hpv' ) . '"><ol>';
	foreach ( $items as $item ) {
		$out .= $item[1]
			? '<li><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>'
			: '<li aria-current="page">' . esc_html( $item[0] ) . '</li>';
	}
	return $out . '</ol></nav>';
}

/**
 * hpv/contact: phone and email from the Customizer.
 */
function hpv_render_contact() {
	$phone = hpv_opt( 'phone' );
	$email = hpv_opt( 'email' );
	ob_start();
	echo '<div class="contact-lines">';
	if ( $phone ) {
		printf( '<a href="%s" data-track="click_to_call">%s</a>', esc_attr( hpv_tel( $phone ) ), esc_html( $phone ) );
	} else {
		hpv_value_or_tbd( '', '[+1 (239) 000-0000]' );
	}
	if ( $email ) {
		printf( '<a href="mailto:%1$s" data-track="click_to_email">%1$s</a>', esc_html( antispambot( $email ) ) );
	} else {
		hpv_value_or_tbd( '', '[hello@domain]' );
	}
	echo '</div>';
	return ob_get_clean();
}

/**
 * hpv/ticker: the pasted fluoro band.
 *
 * @param array $a Attributes.
 */
function hpv_render_ticker( $a ) {
	$items = array_filter( array_map( 'trim', preg_split( '/\r?\n|,/', (string) ( $a['items'] ?? '' ) ) ) );
	if ( ! $items ) {
		return '';
	}
	$li = '';
	foreach ( $items as $item ) {
		$li .= '<li>' . esc_html( $item ) . '</li>';
	}
	return '<div class="ticker" aria-label="' . esc_attr( implode( ', ', $items ) ) . '"><div class="ticker__track"><ul class="ticker__group">' . $li . '</ul><ul class="ticker__group" aria-hidden="true">' . $li . '</ul></div></div>';
}

/**
 * Small author photo for articles.
 */
function hpv_author_photo() {
	echo hpv_render_photo( array( 'src' => 'people/aron-headshot-square', 'ratio' => 'square', 'alt' => get_the_author(), 'note' => '|' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
}

/* -------------------------------------------------------------------------
 * Forms (markup; handlers live in inc/leads.php)
 * ---------------------------------------------------------------------- */

/**
 * hpv/strategy-call-form.
 */
function hpv_render_strategy_form() {
	ob_start();
	get_template_part( 'template-parts/strategy-call-form' );
	return ob_get_clean();
}

/**
 * hpv/scorecard.
 */
function hpv_render_scorecard() {
	ob_start();
	get_template_part( 'template-parts/scorecard' );
	return ob_get_clean();
}
