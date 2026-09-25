<?php
/**
 * Site setup: one-click import of the approved pages, menus and sample content, and a
 * launch checklist. Appearance → HelloProVision setup, or `wp hpv import-demo [--force]`.
 *
 * Content comes from the block patterns in /patterns (generated from the approved prototype)
 * and the manifest patterns/demo.json. Existing pages are never overwritten without --force.
 *
 * @package HelloProVision
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read the demo manifest.
 */
function hpv_demo_manifest() {
	$file = HPV_DIR . '/patterns/demo.json';
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	return is_array( $data ) ? $data : array();
}

/**
 * Block markup of a registered pattern.
 *
 * @param string $slug Pattern slug.
 */
function hpv_pattern_content( $slug ) {
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
	return $pattern ? trim( $pattern['content'] ) : '';
}

/**
 * Create or update one post from the manifest.
 *
 * @param array $args  wp_insert_post args (post_type, post_name, post_title, post_content, …).
 * @param bool  $force Overwrite an existing post.
 * @param array $log   Log lines (by reference).
 * @return int Post ID (0 on failure).
 */
function hpv_demo_upsert( $args, $force, &$log ) {
	$existing = get_page_by_path( $args['_path'], OBJECT, $args['post_type'] );
	$path     = $args['_path'];
	unset( $args['_path'] );

	// An untouched draft (like WordPress's default privacy page) is fair game.
	$untouched = $existing && 'publish' !== $existing->post_status && $existing->post_modified === $existing->post_date;
	if ( $existing && ! $force && ! $untouched ) {
		$log[] = sprintf( 'kept    %s /%s/', $args['post_type'], $path );
		return (int) $existing->ID;
	}
	$args['post_status'] = 'publish';
	$args['post_author'] = hpv_demo_author();
	if ( $existing ) {
		$args['ID'] = $existing->ID;
	}
	$id = wp_insert_post( wp_slash( $args ), true );
	if ( is_wp_error( $id ) ) {
		$log[] = sprintf( 'FAILED  %s /%s/: %s', $args['post_type'], $path, $id->get_error_message() );
		return 0;
	}
	$log[] = sprintf( '%s %s /%s/', $existing ? 'updated' : 'created', $args['post_type'], $path );
	return (int) $id;
}

/**
 * Author for imported content: the current user, or the first administrator (WP-CLI).
 */
function hpv_demo_author() {
	if ( get_current_user_id() ) {
		return get_current_user_id();
	}
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
	return $admins ? (int) $admins[0] : 0;
}

/**
 * Run the import.
 *
 * @param bool $force Overwrite existing pages, posts and menus.
 * @return array Log lines.
 */
function hpv_import_demo( $force = false ) {
	$log      = array();
	$manifest = hpv_demo_manifest();
	if ( ! $manifest ) {
		return array( 'FAILED  patterns/demo.json is missing. Run tools/build_theme.py.' );
	}
	kses_remove_filters(); // Patterns contain classes and data attributes the editor keeps anyway.

	// Topics and industries.
	foreach ( $manifest['topics'] as $t ) {
		$term = get_term_by( 'slug', $t['slug'], 'category' );
		if ( ! $term ) {
			wp_insert_term( $t['name'], 'category', array( 'slug' => $t['slug'], 'description' => $t['description'] ) );
			$log[] = 'created topic ' . $t['name'];
		}
	}
	foreach ( $manifest['industries'] as $name ) {
		if ( ! term_exists( $name, 'industry' ) ) {
			wp_insert_term( $name, 'industry' );
			$log[] = 'created industry ' . $name;
		}
	}

	// Pages, parents first (the manifest is ordered that way).
	$ids = array();
	foreach ( $manifest['pages'] as $p ) {
		$parent_path = dirname( $p['path'] );
		$id          = hpv_demo_upsert(
			array(
				'_path'        => $p['path'],
				'post_type'    => 'page',
				'post_title'   => $p['title'],
				'post_name'    => basename( $p['path'] ),
				'post_parent'  => '.' !== $parent_path && isset( $ids[ $parent_path ] ) ? $ids[ $parent_path ] : 0,
				'post_content' => hpv_pattern_content( $p['pattern'] ),
				'menu_order'   => count( $ids ),
			),
			$force,
			$log
		);
		if ( ! $id ) {
			continue;
		}
		$ids[ $p['path'] ] = $id;
		$meta              = array(
			'hpv_seo_title'       => $p['seo_title'],
			'hpv_seo_description' => $p['seo_description'],
			'hpv_page_type'       => $p['page_type'],
			'hpv_service_name'    => $p['service_name'],
			'hpv_noindex'         => $p['noindex'],
		);
		foreach ( $meta as $key => $value ) {
			if ( $value && ( $force || ! get_post_meta( $id, $key, true ) ) ) {
				update_post_meta( $id, $key, $value );
			}
		}
	}

	// Case studies.
	foreach ( $manifest['case_studies'] as $c ) {
		$id = hpv_demo_upsert(
			array(
				'_path'        => $c['slug'],
				'post_type'    => 'case_study',
				'post_title'   => $c['title'],
				'post_name'    => $c['slug'],
				'post_excerpt' => $c['excerpt'],
				'post_content' => hpv_pattern_content( $c['pattern'] ),
			),
			$force,
			$log
		);
		if ( $id ) {
			wp_set_object_terms( $id, $c['industry'], 'industry' );
			$meta = array_merge( $c['meta'], array( 'hpv_seo_title' => $c['seo_title'], 'hpv_seo_description' => $c['seo_description'] ) );
			foreach ( $meta as $key => $value ) {
				if ( $force || '' === get_post_meta( $id, $key, true ) ) {
					update_post_meta( $id, $key, $value );
				}
			}
		}
	}

	// Sample article.
	foreach ( $manifest['posts'] as $a ) {
		$args = array(
			'_path'        => $a['slug'],
			'post_type'    => 'post',
			'post_title'   => $a['title'],
			'post_name'    => $a['slug'],
			'post_excerpt' => $a['excerpt'],
			'post_content' => hpv_pattern_content( $a['pattern'] ),
		);
		if ( ! empty( $a['date'] ) && strtotime( $a['date'] ) ) {
			$args['post_date'] = gmdate( 'Y-m-d H:i:s', strtotime( $a['date'] . ' 09:00' ) );
		}
		$id = hpv_demo_upsert( $args, $force, $log );
		if ( $id ) {
			$cat = get_term_by( 'slug', $a['category'], 'category' );
			if ( $cat ) {
				wp_set_post_categories( $id, array( $cat->term_id ) );
			}
			update_post_meta( $id, 'hpv_seo_title', $a['seo_title'] );
			update_post_meta( $id, 'hpv_seo_description', $a['seo_description'] );
		}
	}

	// WordPress's own sample content, only while untouched.
	foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $sample ) {
		$post = get_page_by_path( $sample[0], OBJECT, $sample[1] );
		if ( $post && $post->post_modified === $post->post_date ) {
			wp_trash_post( $post->ID );
			$log[] = 'trashed WordPress sample ' . $sample[1] . ' "' . $post->post_title . '"';
		}
	}

	// Reading, permalinks, privacy page, site identity.
	if ( isset( $ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
	}
	if ( isset( $ids['insights'] ) ) {
		update_option( 'page_for_posts', $ids['insights'] );
	}
	if ( isset( $ids['privacy-policy'] ) ) {
		update_option( 'wp_page_for_privacy_policy', $ids['privacy-policy'] );
	}
	update_option( 'posts_per_page', 12 );
	$defaults_name = array( '', 'WordPress', 'My WordPress Website', 'My WordPress Blog', 'My Blog' );
	if ( $force || in_array( get_option( 'blogname' ), $defaults_name, true ) ) {
		update_option( 'blogname', 'Áron Ritók-Filip' );
	}
	if ( $force || in_array( get_option( 'blogdescription' ), array( '', 'Just another WordPress site' ), true ) ) {
		update_option( 'blogdescription', 'Digital Growth Strategist' );
	}
	update_option( 'permalink_structure', '/insights/%postname%/' );
	$log[] = 'set front page, posts page (Insights), privacy page and permalinks /insights/%postname%/';

	// Menus.
	$log = array_merge( $log, hpv_demo_menus( $force ) );

	hpv_rewrite_rules_for_import();
	update_option( 'hpv_demo_imported', time() );
	kses_init_filters();
	$log[] = 'done';
	return $log;
}

/**
 * Rebuild rewrite rules after the permalink and topic changes.
 */
function hpv_rewrite_rules_for_import() {
	global $wp_rewrite;
	$wp_rewrite->init();
	flush_rewrite_rules( false );
}

/**
 * Build the four menus from the same lists the theme falls back to.
 *
 * @param bool $force Rebuild existing menus.
 * @return array Log lines.
 */
function hpv_demo_menus( $force ) {
	$log       = array();
	$names     = array(
		'primary'         => 'Main navigation',
		'footer_services' => 'Footer: Services',
		'footer_explore'  => 'Footer: Explore',
		'legal'           => 'Legal',
	);
	$locations = get_theme_mod( 'nav_menu_locations', array() );

	foreach ( $names as $location => $name ) {
		$menu = wp_get_nav_menu_object( $name );
		if ( $menu && ! $force ) {
			$locations[ $location ] = $menu->term_id;
			$log[]                  = 'kept    menu ' . $name;
			continue;
		}
		if ( $menu ) {
			foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
				wp_delete_post( $item->ID, true );
			}
			$menu_id = $menu->term_id;
		} else {
			$menu_id = wp_create_nav_menu( $name );
		}
		if ( is_wp_error( $menu_id ) ) {
			$log[] = 'FAILED  menu ' . $name;
			continue;
		}
		foreach ( hpv_menu_fallback( $location ) as $i => $item ) {
			$args = array(
				'menu-item-title'    => $item[0],
				'menu-item-status'   => 'publish',
				'menu-item-position' => $i + 1,
			);
			$page = url_to_postid( $item[1] );
			if ( $page && 'page' === get_post_type( $page ) ) {
				$args += array( 'menu-item-object-id' => $page, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type' );
			} elseif ( untrailingslashit( $item[1] ) === untrailingslashit( (string) get_post_type_archive_link( 'case_study' ) ) ) {
				$args += array( 'menu-item-object' => 'case_study', 'menu-item-type' => 'post_type_archive' );
			} else {
				$args += array( 'menu-item-url' => $item[1], 'menu-item-type' => 'custom' );
			}
			wp_update_nav_menu_item( $menu_id, 0, $args );
		}
		$locations[ $location ] = $menu_id;
		$log[]                  = ( $menu ? 'rebuilt menu ' : 'created menu ' ) . $name;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
	return $log;
}

/* -------------------------------------------------------------------------
 * Launch checklist
 * ---------------------------------------------------------------------- */

/**
 * Count "fact to verify" placeholders left in published content.
 *
 * @return array[] Rows of [title, edit link, count].
 */
function hpv_tbd_report() {
	$rows  = array();
	$posts = get_posts(
		array(
			'post_type'      => array( 'page', 'post', 'case_study' ),
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			's'              => 'class="tbd"',
			'search_columns' => array( 'post_content', 'post_title' ),
		)
	);
	foreach ( $posts as $p ) {
		$n = substr_count( hpv_fill_business_tokens( $p->post_content ) . $p->post_title, 'class="tbd"' );
		if ( $n ) {
			$rows[] = array( wp_strip_all_tags( get_the_title( $p ) ), get_edit_post_link( $p->ID, 'raw' ), $n );
		}
	}
	return $rows;
}

/**
 * The checks shown on the setup screen.
 *
 * @return array[] Rows of [ok, label, hint, link].
 */
function hpv_launch_checks() {
	$customize = static function ( $section ) {
		return admin_url( 'customize.php?autofocus[section]=' . $section );
	};
	$tbd       = hpv_tbd_report();
	$env       = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
	$photos    = glob( HPV_DIR . '/assets/img/people/*.{jpg,jpeg,png,webp}', GLOB_BRACE );

	return array(
		array( (bool) get_option( 'hpv_demo_imported' ), __( 'Pages, menus and sample content imported', 'hpv' ), __( 'Use the button below.', 'hpv' ), '' ),
		array( (bool) hpv_opt( 'booking_url' ), __( 'Booking link set', 'hpv' ), __( 'Cal.com / Calendly / Google Calendar URL for the strategy call.', 'hpv' ), $customize( 'hpv_leads' ) ),
		array( hpv_opt( 'phone' ) && hpv_opt( 'email' ), __( 'Phone and email set (NAP)', 'hpv' ), __( 'Must match the Google Business Profile exactly.', 'hpv' ), $customize( 'hpv_business' ) ),
		array( (bool) hpv_opt( 'legal_name' ), __( 'Legal business name set', 'hpv' ), __( 'Shown in the footer and legal pages.', 'hpv' ), $customize( 'hpv_business' ) ),
		array( hpv_opt( 'ga4_id' ) || hpv_opt( 'gtm_id' ), __( 'Analytics connected', 'hpv' ), __( 'GA4 measurement ID or GTM container.', 'hpv' ), $customize( 'hpv_analytics' ) ),
		array( ! empty( $photos ), __( 'Real photos added', 'hpv' ), __( 'Upload photos in the editor (Photo block → Replace) or drop files into assets/img/people and assets/img/projects.', 'hpv' ), '' ),
		array( empty( $tbd ), __( 'No “fact to verify” marks left', 'hpv' ), sprintf( /* translators: %d: number of pages */ _n( '%d page still has placeholders (listed below).', '%d pages still have placeholders (listed below).', count( $tbd ), 'hpv' ), count( $tbd ) ), '' ),
		array( (bool) get_option( 'permalink_structure' ), __( 'Pretty permalinks on', 'hpv' ), '/insights/%postname%/', admin_url( 'options-permalink.php' ) ),
		array( 'production' === $env, __( 'Environment is production', 'hpv' ), sprintf( /* translators: %s: environment type */ __( 'Currently “%s”. Non-production sites are noindexed automatically.', 'hpv' ), $env ), '' ),
		array( '1' === (string) get_option( 'blog_public' ), __( 'Search engines allowed', 'hpv' ), __( 'Settings → Reading → Search engine visibility.', 'hpv' ), admin_url( 'options-reading.php' ) ),
		array( (bool) get_option( 'hpv_test_lead_ok' ), __( 'Test lead received', 'hpv' ), __( 'Submit the strategy call form once and check the Leads screen and your inbox.', 'hpv' ), admin_url( 'edit.php?post_type=hpv_lead' ) ),
	);
}

/**
 * Remember that a lead came in (for the checklist).
 */
add_action(
	'hpv_lead_created',
	static function () {
		update_option( 'hpv_test_lead_ok', 1, false );
	}
);

/**
 * Admin page.
 */
function hpv_setup_menu() {
	add_theme_page( __( 'HelloProVision setup', 'hpv' ), __( 'HelloProVision setup', 'hpv' ), 'edit_theme_options', 'hpv-setup', 'hpv_setup_screen' );
}
add_action( 'admin_menu', 'hpv_setup_menu' );

/**
 * Handle the import form.
 */
function hpv_setup_handle() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'hpv' ) );
	}
	check_admin_referer( 'hpv_import_demo' );
	$log = hpv_import_demo( ! empty( $_POST['force'] ) );
	set_transient( 'hpv_import_log_' . get_current_user_id(), $log, 300 );
	wp_safe_redirect( admin_url( 'themes.php?page=hpv-setup&imported=1' ) );
	exit;
}
add_action( 'admin_post_hpv_import_demo', 'hpv_setup_handle' );

/**
 * Render the setup screen.
 */
function hpv_setup_screen() {
	$log    = get_transient( 'hpv_import_log_' . get_current_user_id() );
	$checks = hpv_launch_checks();
	$done   = count( array_filter( wp_list_pluck( $checks, 0 ) ) );
	?>
	<div class="wrap hpv-setup">
		<h1><?php esc_html_e( 'HelloProVision setup', 'hpv' ); ?></h1>

		<?php if ( $log ) : ?>
			<?php delete_transient( 'hpv_import_log_' . get_current_user_id() ); ?>
			<div class="notice notice-success"><p><strong><?php esc_html_e( 'Import finished.', 'hpv' ); ?></strong> <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'View the site', 'hpv' ); ?></a></p>
				<pre class="hpv-setup__log"><?php echo esc_html( implode( "\n", $log ) ); ?></pre></div>
		<?php endif; ?>

		<div class="hpv-setup__grid">
			<div class="card">
				<h2><?php esc_html_e( '1. Import the site', 'hpv' ); ?></h2>
				<p><?php esc_html_e( 'Creates every page of the approved design (home, services, about, case studies, insights, strategy call, scorecard, legal), the menus, one case study and one article, and sets the front page and permalinks.', 'hpv' ); ?></p>
				<p><?php esc_html_e( 'Pages that already exist are kept as they are unless you tick the box.', 'hpv' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hpv_import_demo">
					<?php wp_nonce_field( 'hpv_import_demo' ); ?>
					<p><label><input type="checkbox" name="force" value="1"> <?php esc_html_e( 'Overwrite existing pages, menus and site title (resets your edits)', 'hpv' ); ?></label></p>
					<?php submit_button( get_option( 'hpv_demo_imported' ) ? __( 'Import again', 'hpv' ) : __( 'Import pages & menus', 'hpv' ), 'primary', 'submit', false ); ?>
				</form>
				<p class="description"><?php esc_html_e( 'WP-CLI: wp hpv import-demo [--force]', 'hpv' ); ?></p>
			</div>

			<div class="card">
				<h2><?php esc_html_e( '2. Launch checklist', 'hpv' ); ?> <span class="hpv-setup__score"><?php echo esc_html( $done . ' / ' . count( $checks ) ); ?></span></h2>
				<ul class="hpv-setup__checks">
					<?php foreach ( $checks as $c ) : ?>
						<li class="<?php echo $c[0] ? 'is-ok' : 'is-todo'; ?>">
							<span class="dashicons <?php echo $c[0] ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span>
							<span><strong><?php echo esc_html( $c[1] ); ?></strong><br><span class="description"><?php echo esc_html( $c[2] ); ?></span></span>
							<?php if ( $c[3] && ! $c[0] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $c[3] ); ?>"><?php esc_html_e( 'Fix', 'hpv' ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<?php $tbd = hpv_tbd_report(); ?>
		<?php if ( $tbd ) : ?>
			<div class="card hpv-setup__wide">
				<h2><?php esc_html_e( 'Facts to verify before launch', 'hpv' ); ?></h2>
				<p><?php esc_html_e( 'Pink-outlined placeholders (numbers, client names, testimonials) are visible only to logged-in editors. Replace each one with a verified fact or delete it.', 'hpv' ); ?></p>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Page', 'hpv' ); ?></th><th><?php esc_html_e( 'Placeholders', 'hpv' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $tbd as $row ) : ?>
						<tr><td><a href="<?php echo esc_url( $row[1] ); ?>"><?php echo esc_html( $row[0] ); ?></a></td><td><?php echo esc_html( (string) $row[2] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<style>
		.hpv-setup__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:20px;max-width:1200px}
		.hpv-setup .card{max-width:none;margin-top:20px}
		.hpv-setup__wide{max-width:1200px!important}
		.hpv-setup__score{float:right;background:#121212;color:#fff;border-radius:99px;padding:2px 10px;font-size:13px}
		.hpv-setup__checks li{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f0f0f1;margin:0}
		.hpv-setup__checks li>span:nth-child(2){flex:1}
		.hpv-setup__checks .is-ok .dashicons{color:#00a32a}
		.hpv-setup__checks .is-todo .dashicons{color:#d63638}
		.hpv-setup__log{max-height:240px;overflow:auto;background:#f6f7f7;padding:10px;font-size:12px}
	</style>
	<?php
}

/**
 * Nudge right after activation.
 */
function hpv_setup_notice() {
	$screen = get_current_screen();
	if ( get_option( 'hpv_demo_imported' ) || ! current_user_can( 'edit_theme_options' ) || ( $screen && 'appearance_page_hpv-setup' === $screen->id ) ) {
		return;
	}
	printf(
		'<div class="notice notice-info"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
		esc_html__( 'HelloProVision theme is active.', 'hpv' ),
		esc_html__( 'Import the pages and menus of the approved design in one click.', 'hpv' ),
		esc_url( admin_url( 'themes.php?page=hpv-setup' ) ),
		esc_html__( 'Open setup', 'hpv' )
	);
}
add_action( 'admin_notices', 'hpv_setup_notice' );

/* -------------------------------------------------------------------------
 * WP-CLI
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Import the pages, menus and sample content of the HelloProVision design.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Overwrite existing pages, posts, menus and the site title.
	 *
	 * ## EXAMPLES
	 *
	 *     wp hpv import-demo
	 *     wp hpv import-demo --force
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	$hpv_cli_import = static function ( $args, $assoc_args ) {
		foreach ( hpv_import_demo( ! empty( $assoc_args['force'] ) ) as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::success( 'HelloProVision content imported.' );
	};
	WP_CLI::add_command( 'hpv import-demo', $hpv_cli_import );

	/**
	 * Print the launch checklist.
	 */
	WP_CLI::add_command(
		'hpv checklist',
		static function () {
			foreach ( hpv_launch_checks() as $c ) {
				WP_CLI::log( ( $c[0] ? '[x] ' : '[ ] ' ) . $c[1] . ( $c[0] ? '' : ' — ' . $c[2] ) );
			}
		}
	);
}
