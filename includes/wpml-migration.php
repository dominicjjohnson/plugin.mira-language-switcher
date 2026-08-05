<?php
/**
 * WPML Migration Page
 * Imports translation relationships and page languages from WPML data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the WPML Import admin page.
 */
function mira_ls_wpml_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'mira-language-switcher' ) );
	}

	global $wpdb;

	$migrate_result = null;
	$cleanup_result = null;

	// Handle migrate action
	if (
		isset( $_POST['mira_wpml_action'] ) &&
		$_POST['mira_wpml_action'] === 'migrate' &&
		check_admin_referer( 'mira_wpml_migrate', 'mira_wpml_nonce' )
	) {
		$selected_post_types = isset( $_POST['mira_wpml_post_types'] ) && is_array( $_POST['mira_wpml_post_types'] )
			? array_map( 'sanitize_key', $_POST['mira_wpml_post_types'] )
			: array();
		$migrate_result = mira_ls_run_wpml_migration( $selected_post_types );
	}

	// Handle cleanup action
	if (
		isset( $_POST['mira_wpml_action'] ) &&
		$_POST['mira_wpml_action'] === 'cleanup' &&
		check_admin_referer( 'mira_wpml_cleanup', 'mira_wpml_cleanup_nonce' ) &&
		! empty( $_POST['mira_wpml_confirm_cleanup'] )
	) {
		$cleanup_result = mira_ls_run_wpml_cleanup();
	}

	// Check if WPML tables exist
	$wpml_table  = $wpdb->prefix . 'icl_translations';
	$wpml_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpml_table}'" ) === $wpml_table;

	// Per-post-type breakdown, one row per post type currently translatable on this
	// site (Pages always; Posts too if "Translate Blog Posts" is enabled in Settings),
	// so each can be imported independently — e.g. skip Pages if they've already been
	// migrated/hand-adjusted and you only want to bring in Posts this time.
	$type_counts     = $wpml_exists ? mira_ls_wpml_type_counts() : array();
	$wpml_page_count = array_sum( wp_list_pluck( $type_counts, 'item_count' ) );
	$wpml_pair_count = array_sum( wp_list_pluck( $type_counts, 'pair_count' ) );

	// Count WPML tables present in DB
	$wpml_tables      = mira_ls_get_wpml_tables();
	$wpml_table_count = count( $wpml_tables );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WPML Import', 'mira-language-switcher' ); ?></h1>
		<p><?php esc_html_e( 'Import language assignments and translation pairs from existing WPML data into the Mira Language Switcher.', 'mira-language-switcher' ); ?></p>

		<?php if ( $migrate_result ) : ?>
			<div class="notice notice-<?php echo $migrate_result['success'] ? 'success' : 'error'; ?> is-dismissible">
				<p><?php echo esc_html( $migrate_result['message'] ); ?></p>
				<?php if ( ! empty( $migrate_result['details'] ) ) : ?>
					<ul>
						<?php foreach ( $migrate_result['details'] as $detail ) : ?>
							<li><?php echo esc_html( $detail ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $cleanup_result ) : ?>
			<div class="notice notice-<?php echo $cleanup_result['success'] ? 'success' : 'error'; ?> is-dismissible">
				<p><?php echo esc_html( $cleanup_result['message'] ); ?></p>
				<?php if ( ! empty( $cleanup_result['details'] ) ) : ?>
					<ul>
						<?php foreach ( $cleanup_result['details'] as $detail ) : ?>
							<li><?php echo esc_html( $detail ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Section 1: Migrate -->
		<div class="card" style="max-width: 700px; margin-bottom: 20px; padding: 20px;">
			<h2><?php esc_html_e( 'Step 1: Import Translation Data', 'mira-language-switcher' ); ?></h2>

			<?php if ( ! $wpml_exists ) : ?>
				<p class="description" style="color: #d63638;">
					<?php esc_html_e( 'No WPML translation table found (icl_translations). Nothing to import.', 'mira-language-switcher' ); ?>
				</p>
			<?php elseif ( empty( $type_counts ) ) : ?>
				<p class="description"><?php esc_html_e( 'No matching WPML data found for the currently translatable post types.', 'mira-language-switcher' ); ?></p>
			<?php else : ?>
				<p>
					<?php printf(
						esc_html__( 'Found %1$d items in WPML data with %2$d EN/IT translation pairs. Choose which post types to import below — already-imported or hand-adjusted types can be left unchecked.', 'mira-language-switcher' ),
						$wpml_page_count,
						$wpml_pair_count
					); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'This sets the language (EN or IT) and links translation pairs for the checked post types only. It will not delete any content, and it overwrites any language/translation-link data already set on those post types (from a previous import or manual edits) — uncheck anything you don’t want touched.', 'mira-language-switcher' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'mira_wpml_migrate', 'mira_wpml_nonce' ); ?>
					<input type="hidden" name="mira_wpml_action" value="migrate">
					<table class="form-table" role="presentation" style="margin-bottom: 0;">
						<?php foreach ( $type_counts as $tc ) : ?>
							<tr>
								<td style="padding-left:0;">
									<label>
										<input type="checkbox" name="mira_wpml_post_types[]" value="<?php echo esc_attr( $tc['post_type'] ); ?>" checked>
										<strong><?php echo esc_html( $tc['label'] ); ?></strong>
										&mdash;
										<?php printf(
											esc_html__( '%1$d items, %2$d EN/IT pairs', 'mira-language-switcher' ),
											$tc['item_count'],
											$tc['pair_count']
										); ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php submit_button( __( 'Import Selected from WPML', 'mira-language-switcher' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>

		<!-- Section 2: Cleanup -->
		<div class="card" style="max-width: 700px; padding: 20px; border-left: 4px solid #d63638;">
			<h2><?php esc_html_e( 'Step 2: Remove WPML Data (Optional)', 'mira-language-switcher' ); ?></h2>

			<?php if ( $wpml_table_count === 0 ) : ?>
				<p class="description"><?php esc_html_e( 'No WPML tables found. Already cleaned up.', 'mira-language-switcher' ); ?></p>
			<?php else : ?>
				<p>
					<?php printf(
						esc_html__( 'Found %d WPML-related tables and option entries that can be removed.', 'mira-language-switcher' ),
						$wpml_table_count
					); ?>
				</p>
				<details style="margin-bottom: 12px;">
					<summary><?php esc_html_e( 'Show tables to be removed', 'mira-language-switcher' ); ?></summary>
					<ul style="margin-top: 8px; font-family: monospace;">
						<?php foreach ( $wpml_tables as $t ) : ?>
							<li><?php echo esc_html( $t ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
				<p style="color: #d63638; font-weight: bold;">
					<?php esc_html_e( 'Warning: This is irreversible. Only do this after confirming the migration above worked correctly.', 'mira-language-switcher' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'mira_wpml_cleanup', 'mira_wpml_cleanup_nonce' ); ?>
					<input type="hidden" name="mira_wpml_action" value="cleanup">
					<p>
						<label>
							<input type="checkbox" name="mira_wpml_confirm_cleanup" value="1">
							<?php esc_html_e( 'I understand this cannot be undone and I have a database backup.', 'mira-language-switcher' ); ?>
						</label>
					</p>
					<?php submit_button( __( 'Remove WPML Data', 'mira-language-switcher' ), 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Post types eligible for WPML import: whichever are currently translatable
 * on this site (Pages always; Posts too when "Translate Blog Posts" is
 * enabled in Settings). Used both as the checkbox list and as the allowlist
 * for sanitizing the submitted selection.
 *
 * @return string[] e.g. ['page'] or ['page', 'post']
 */
function mira_ls_wpml_eligible_post_types() {
	return class_exists( 'Mira_Language_Switcher' )
		? Mira_Language_Switcher::get_translatable_post_types()
		: array( 'page' );
}

/**
 * WPML element_type values to import, based on which post types are
 * currently translatable on this site.
 *
 * @return string[] e.g. ['post_page'] or ['post_page', 'post_post']
 */
function mira_ls_wpml_element_types() {
	return array_map( function ( $pt ) {
		return 'post_' . $pt;
	}, mira_ls_wpml_eligible_post_types() );
}

/**
 * Per-post-type item/pair counts from the WPML data, for the checkbox list.
 *
 * @return array[] Each: ['post_type' => 'page', 'label' => 'Pages', 'item_count' => int, 'pair_count' => int]
 */
function mira_ls_wpml_type_counts() {
	global $wpdb;

	$wpml_table = $wpdb->prefix . 'icl_translations';
	$rows       = array();

	foreach ( mira_ls_wpml_eligible_post_types() as $post_type ) {
		$element_type = 'post_' . $post_type;

		$item_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpml_table} WHERE element_type = %s",
			$element_type
		) );

		if ( $item_count === 0 ) {
			continue;
		}

		$pair_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT a.trid)
			 FROM {$wpml_table} a
			 JOIN {$wpml_table} b ON a.trid = b.trid AND b.language_code = 'it'
			 WHERE a.element_type = %s AND a.language_code = 'en'",
			$element_type
		) );

		$pt_obj = get_post_type_object( $post_type );
		$label  = $pt_obj ? $pt_obj->labels->name : ucfirst( $post_type );

		$rows[] = array(
			'post_type'  => $post_type,
			'label'      => $label,
			'item_count' => $item_count,
			'pair_count' => $pair_count,
		);
	}

	return $rows;
}

/**
 * Run the WPML to Mira LS migration.
 *
 * @param string[]|null $selected_post_types Post types to import (subset of
 *   mira_ls_wpml_eligible_post_types()). Null imports all eligible types
 *   (legacy default); an explicit array — including an empty one — imports
 *   only what's listed, so callers can scope the import to just the types
 *   the user checked.
 * @return array Result with 'success', 'message', and 'details' keys.
 */
function mira_ls_run_wpml_migration( $selected_post_types = null ) {
	global $wpdb;

	$wpml_table = $wpdb->prefix . 'icl_translations';

	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpml_table}'" ) !== $wpml_table ) {
		return array(
			'success' => false,
			'message' => 'WPML translations table not found.',
			'details' => array(),
		);
	}

	$eligible_types = mira_ls_wpml_eligible_post_types();
	$post_types     = $selected_post_types === null
		? $eligible_types
		: array_values( array_intersect( $selected_post_types, $eligible_types ) );

	if ( empty( $post_types ) ) {
		return array(
			'success' => false,
			'message' => 'No post types selected — nothing imported.',
			'details' => array(),
		);
	}

	$element_types    = array_map( function ( $pt ) {
		return 'post_' . $pt;
	}, $post_types );
	$element_types_in = "'" . implode( "','", array_map( 'esc_sql', $element_types ) ) . "'";

	// Fetch all matching entries from WPML for the selected post types only
	$rows = $wpdb->get_results(
		"SELECT trid, element_id, language_code
		 FROM {$wpml_table}
		 WHERE element_type IN ({$element_types_in})
		 ORDER BY trid, language_code"
	);

	// Group by trid
	$groups = array();
	foreach ( $rows as $row ) {
		$groups[ $row->trid ][ $row->language_code ] = (int) $row->element_id;
	}

	$pages_tagged   = 0;
	$pairs_imported = 0;
	$enabled_langs  = get_option( 'mira_ls_enabled_languages', array( 'en', 'it' ) );
	$default_lang   = get_option( 'mira_ls_default_language', 'en' );

	// Load existing translation links so we merge rather than overwrite
	$translation_links = get_option( MIRA_LS_TRANSLATIONS_OPTION, array() );

	foreach ( $groups as $trid => $langs ) {
		// Tag each page with its language
		foreach ( $langs as $lang => $page_id ) {
			if ( in_array( $lang, $enabled_langs, true ) && $page_id > 0 ) {
				update_post_meta( $page_id, '_mira_page_language', $lang );
				$pages_tagged++;
			}
		}

		// Link translation pairs: default language page → other languages
		if ( isset( $langs[ $default_lang ] ) ) {
			$default_id = $langs[ $default_lang ];

			foreach ( $langs as $lang => $page_id ) {
				if ( $lang === $default_lang ) {
					continue;
				}
				if ( in_array( $lang, $enabled_langs, true ) && $page_id > 0 ) {
					if ( ! isset( $translation_links[ $default_id ] ) ) {
						$translation_links[ $default_id ] = array();
					}
					$translation_links[ $default_id ][ $lang ] = $page_id;
					$pairs_imported++;
				}
			}
		}
	}

	update_option( MIRA_LS_TRANSLATIONS_OPTION, $translation_links );

	return array(
		'success' => true,
		'message' => 'WPML data imported successfully.',
		'details' => array(
			sprintf( '%1$d items tagged with language (%2$s).', $pages_tagged, implode( ', ', $element_types ) ),
			sprintf( '%d translation pairs imported.', $pairs_imported ),
		),
	);
}

/**
 * Run the WPML data cleanup.
 *
 * @return array Result with 'success', 'message', and 'details' keys.
 */
function mira_ls_run_wpml_cleanup() {
	global $wpdb;

	$dropped  = array();
	$failed   = array();
	$details  = array();

	// Drop WPML tables
	$wpml_table_names = array(
		'icl_translations',
		'icl_languages',
		'icl_language_pairs',
		'icl_locale_map',
		'icl_flags',
		'icl_languages_translations',
		'icl_strings',
		'icl_string_translations',
		'icl_string_pages',
		'icl_string_packages',
		'icl_translation_batches',
		'icl_translation_status',
		'icl_message_status',
		'icl_mo_files_domains',
		'icl_node',
		'icl_core_status',
		'icl_content_status',
	);

	foreach ( $wpml_table_names as $table_name ) {
		$full_name = $wpdb->prefix . $table_name;
		$exists    = $wpdb->get_var( "SHOW TABLES LIKE '{$full_name}'" );
		if ( $exists === $full_name ) {
			$result = $wpdb->query( "DROP TABLE IF EXISTS `{$full_name}`" );
			if ( $result !== false ) {
				$dropped[] = $full_name;
			} else {
				$failed[] = $full_name;
			}
		}
	}

	// Remove WPML options
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE 'wpml_%'
		 OR option_name LIKE 'icl_%'
		 OR option_name LIKE '_wpml_%'
		 OR option_name LIKE 'WPML_%'"
	);
	$options_removed = $wpdb->rows_affected;

	if ( count( $dropped ) > 0 ) {
		$details[] = sprintf( 'Dropped %d tables: %s', count( $dropped ), implode( ', ', $dropped ) );
	}
	if ( $options_removed > 0 ) {
		$details[] = sprintf( 'Removed %d WPML option entries.', $options_removed );
	}
	if ( count( $failed ) > 0 ) {
		$details[] = sprintf( 'Failed to drop: %s', implode( ', ', $failed ) );
	}

	return array(
		'success' => count( $failed ) === 0,
		'message' => count( $failed ) === 0 ? 'WPML data removed successfully.' : 'Some tables could not be dropped.',
		'details' => $details,
	);
}

/**
 * Get list of WPML tables and option counts present in the database.
 *
 * @return array List of table/option names found.
 */
function mira_ls_get_wpml_tables() {
	global $wpdb;

	$found = array();

	$wpml_table_names = array(
		'icl_translations', 'icl_languages', 'icl_language_pairs', 'icl_locale_map',
		'icl_flags', 'icl_languages_translations', 'icl_strings', 'icl_string_translations',
		'icl_string_pages', 'icl_string_packages', 'icl_translation_batches',
		'icl_translation_status', 'icl_message_status', 'icl_mo_files_domains',
		'icl_node', 'icl_core_status', 'icl_content_status',
	);

	foreach ( $wpml_table_names as $table_name ) {
		$full_name = $wpdb->prefix . $table_name;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$full_name}'" ) === $full_name ) {
			$found[] = $full_name;
		}
	}

	// Check if any WPML options exist
	$option_count = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->options}
		 WHERE option_name LIKE 'wpml_%'
		 OR option_name LIKE 'icl_%'
		 OR option_name LIKE '_wpml_%'
		 OR option_name LIKE 'WPML_%'"
	);
	if ( $option_count > 0 ) {
		$found[] = sprintf( '%d WPML option entries (in wp_options)', $option_count );
	}

	return $found;
}
