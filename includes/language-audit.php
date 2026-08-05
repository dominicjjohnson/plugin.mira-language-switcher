<?php
/**
 * Language Audit
 *
 * Read-only report that flags translatable posts/pages whose tagged
 * language (_mira_page_language) looks like it disagrees with the language
 * the content is actually written in. Useful after a WPML import, since
 * WPML's source data (icl_translations.language_code) is sometimes wrong
 * for individual posts — this surfaces the likely mistagged ones instead
 * of requiring every imported item to be checked by hand.
 *
 * This never changes anything. It only reports; corrections are made by
 * editing the post's Language metabox as normal.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common function words per language, used as a crude but effective
 * bag-of-words signal. Word-boundary matched, case-insensitive.
 */
function mira_ls_audit_stopwords() {
	return array(
		'en' => array( 'the', 'and', 'of', 'to', 'in', 'is', 'are', 'was', 'were', 'this', 'that', 'these', 'those', 'with', 'for', 'on', 'at', 'by', 'from', 'as', 'it', 'its', 'be', 'been', 'being', 'have', 'has', 'had', 'not', 'but', 'or', 'if', 'when', 'where', 'because', 'which', 'who', 'their', 'his', 'her', 'our', 'your', 'you', 'we', 'they' ),
		'it' => array( 'il', 'lo', 'la', 'gli', 'le', 'un', 'uno', 'una', 'di', 'da', 'con', 'su', 'per', 'tra', 'fra', 'che', 'chi', 'cui', 'non', 'è', 'sono', 'questo', 'questa', 'questi', 'queste', 'come', 'più', 'anche', 'ma', 'se', 'quando', 'dove', 'perché', 'quale', 'loro', 'suo', 'sua', 'suoi', 'sue', 'del', 'della', 'dei', 'delle', 'dello', 'degli', 'nel', 'nella', 'nei', 'nelle', 'dal', 'dalla', 'dai', 'dalle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle' ),
		'es' => array( 'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'de', 'del', 'en', 'con', 'por', 'para', 'que', 'quien', 'no', 'es', 'son', 'este', 'esta', 'estos', 'estas', 'como', 'más', 'también', 'pero', 'si', 'cuando', 'donde', 'porque', 'cual', 'su', 'sus', 'al', 'lo', 'les', 'nos', 'ellos', 'ellas' ),
	);
}

/**
 * Guess a post's language from its content using stopword frequency.
 *
 * @param string   $text            Plain text (tags/shortcodes already stripped).
 * @param string[] $candidate_langs Language codes to score against.
 * @return array{lang: string|null, confident: bool, scores: array<string,int>}
 */
function mira_ls_audit_detect_language( $text, $candidate_langs ) {
	$stopwords = mira_ls_audit_stopwords();
	$scores    = array();

	foreach ( $candidate_langs as $lang ) {
		if ( empty( $stopwords[ $lang ] ) ) {
			continue;
		}
		$pattern      = '/\b(' . implode( '|', array_map( 'preg_quote', $stopwords[ $lang ] ) ) . ')\b/iu';
		$matches      = array();
		preg_match_all( $pattern, $text, $matches );
		$scores[ $lang ] = count( $matches[0] );
	}

	if ( empty( $scores ) ) {
		return array( 'lang' => null, 'confident' => false, 'scores' => $scores );
	}

	arsort( $scores );
	$top_lang  = array_key_first( $scores );
	$top_score = reset( $scores );
	$second    = count( $scores ) > 1 ? array_values( $scores )[1] : 0;

	// Require a reasonable amount of signal and a clear lead before calling it confident.
	$confident = $top_score >= 8 && $top_score >= ( $second * 1.3 );

	return array( 'lang' => $top_lang, 'confident' => $confident, 'scores' => $scores );
}

/**
 * Register the Language Audit submenu page.
 */
add_action( 'admin_menu', 'mira_ls_register_language_audit_menu', 20 );
function mira_ls_register_language_audit_menu() {
	add_submenu_page(
		'mira-language-switcher',
		__( 'Language Audit', 'mira-language-switcher' ),
		__( 'Language Audit', 'mira-language-switcher' ),
		'manage_options',
		'mira-language-switcher-audit',
		'mira_ls_language_audit_page'
	);
}

/**
 * Render the Language Audit admin page.
 */
function mira_ls_language_audit_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'mira-language-switcher' ) );
	}

	$post_types     = class_exists( 'Mira_Language_Switcher' ) ? Mira_Language_Switcher::get_translatable_post_types() : array( 'page' );
	$enabled_langs  = get_option( 'mira_ls_enabled_languages', array( 'en', 'it' ) );
	$default_lang   = get_option( 'mira_ls_default_language', 'en' );
	$stopwords      = mira_ls_audit_stopwords();
	$scorable_langs = array_values( array_intersect( $enabled_langs, array_keys( $stopwords ) ) );

	$posts = get_posts( array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	$flagged  = array();
	$checked  = 0;
	$skipped  = 0;

	if ( count( $scorable_langs ) >= 2 ) {
		foreach ( $posts as $post ) {
			$tagged_lang = get_post_meta( $post->ID, '_mira_page_language', true );
			if ( empty( $tagged_lang ) ) {
				$tagged_lang = $default_lang;
			}
			if ( ! in_array( $tagged_lang, $scorable_langs, true ) ) {
				continue; // Can't score a language we don't have stopwords for.
			}

			$text = wp_strip_all_tags( strip_shortcodes( $post->post_title . ' ' . $post->post_content ) );
			$result = mira_ls_audit_detect_language( $text, $scorable_langs );
			$checked++;

			if ( ! $result['confident'] ) {
				$skipped++;
				continue;
			}

			if ( $result['lang'] !== $tagged_lang ) {
				$flagged[] = array(
					'post'         => $post,
					'tagged_lang'  => $tagged_lang,
					'detected_lang' => $result['lang'],
					'scores'       => $result['scores'],
				);
			}
		}
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Language Audit', 'mira-language-switcher' ); ?></h1>
		<p><?php esc_html_e( 'Compares each post/page’s tagged language against a guess based on its actual text (common word frequency), and lists ones that disagree. This is a heuristic, not a fix — it only flags likely mistakes for you to check and correct by hand in the Language metabox. Nothing on this page changes any content.', 'mira-language-switcher' ); ?></p>

		<?php if ( count( $scorable_langs ) < 2 ) : ?>
			<p class="description" style="color:#d63638;">
				<?php esc_html_e( 'Need at least two enabled languages with known word lists (currently: en, it, es) to compare against. Check Settings > Enabled Languages.', 'mira-language-switcher' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php printf(
					esc_html__( 'Checked %1$d items (%2$s). %3$d skipped as too short/ambiguous to call confidently. %4$d flagged below.', 'mira-language-switcher' ),
					$checked,
					esc_html( implode( ', ', $post_types ) ),
					$skipped,
					count( $flagged )
				); ?>
			</p>

			<?php if ( empty( $flagged ) ) : ?>
				<p><strong><?php esc_html_e( 'No likely mismatches found.', 'mira-language-switcher' ); ?></strong></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width: 900px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Post', 'mira-language-switcher' ); ?></th>
							<th><?php esc_html_e( 'Tagged as', 'mira-language-switcher' ); ?></th>
							<th><?php esc_html_e( 'Looks like', 'mira-language-switcher' ); ?></th>
							<th><?php esc_html_e( 'Word-count signal', 'mira-language-switcher' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $flagged as $row ) :
							$post       = $row['post'];
							$edit_link  = get_edit_post_link( $post->ID );
							$scores_str = array();
							foreach ( $row['scores'] as $lang => $score ) {
								$scores_str[] = strtoupper( $lang ) . ': ' . $score;
							}
						?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $post->post_title ); ?></a>
									<div class="description"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></div>
								</td>
								<td><?php echo esc_html( strtoupper( $row['tagged_lang'] ) ); ?></td>
								<td><strong><?php echo esc_html( strtoupper( $row['detected_lang'] ) ); ?></strong></td>
								<td><?php echo esc_html( implode( ', ', $scores_str ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
