<?php
/**
 * UI Text Overrides
 *
 * Lets an admin override the display text of fixed strings coming from a
 * theme or third-party plugin that don't switch with the site's language —
 * e.g. WPBakery's "Read more" grid button, which is translated via
 * WordPress's own .mo/.po locale system, not by this plugin's per-visitor
 * language cookie, so it always shows in whatever the site's single
 * WordPress locale is regardless of which language a visitor is viewing.
 *
 * Stored as: mira_ls_ui_strings = [ 'Source text' => [ 'it' => 'Testo', 'es' => '...' ], ... ]
 * Applied via the 'gettext' filter — exact, case-sensitive string match, and
 * only when viewing a non-default language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'gettext', 'mira_ls_override_ui_string', 20, 3 );
/**
 * @param string $translated
 * @param string $original
 * @param string $domain
 * @return string
 */
function mira_ls_override_ui_string( $translated, $original, $domain ) {
	// Only affects front-end/AJAX rendering — never the wp-admin UI itself.
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $translated;
	}

	$default_language = get_option( 'mira_ls_default_language', 'en' );
	$current_language = ! empty( $_COOKIE['mira_language'] ) ? sanitize_key( $_COOKIE['mira_language'] ) : $default_language;

	if ( $current_language === $default_language ) {
		return $translated;
	}

	$overrides = get_option( 'mira_ls_ui_strings', array() );
	if ( isset( $overrides[ $original ][ $current_language ] ) && $overrides[ $original ][ $current_language ] !== '' ) {
		return $overrides[ $original ][ $current_language ];
	}

	return $translated;
}

add_action( 'admin_menu', 'mira_ls_register_ui_strings_menu', 20 );
function mira_ls_register_ui_strings_menu() {
	add_submenu_page(
		'mira-language-switcher',
		__( 'UI Text', 'mira-language-switcher' ),
		__( 'UI Text', 'mira-language-switcher' ),
		'manage_options',
		'mira-language-switcher-ui-strings',
		'mira_ls_ui_strings_page'
	);
}

/**
 * Render the UI Text admin page.
 */
function mira_ls_ui_strings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'mira-language-switcher' ) );
	}

	$enabled_languages = get_option( 'mira_ls_enabled_languages', array( 'en' ) );
	$default_language  = get_option( 'mira_ls_default_language', 'en' );
	$other_languages   = array_values( array_diff( $enabled_languages, array( $default_language ) ) );
	$overrides         = get_option( 'mira_ls_ui_strings', array() );

	if ( isset( $_POST['mira_ls_ui_strings_save'] ) && check_admin_referer( 'mira_ls_ui_strings_save' ) ) {
		$new_overrides = array();

		if ( ! empty( $_POST['mira_ui_source'] ) && is_array( $_POST['mira_ui_source'] ) ) {
			foreach ( $_POST['mira_ui_source'] as $i => $source ) {
				$source = trim( sanitize_text_field( wp_unslash( $source ) ) );
				if ( $source === '' ) {
					continue; // Blank source = delete this row.
				}
				$row = array();
				foreach ( $other_languages as $lang ) {
					$val = isset( $_POST['mira_ui_translation'][ $i ][ $lang ] ) ? sanitize_text_field( wp_unslash( $_POST['mira_ui_translation'][ $i ][ $lang ] ) ) : '';
					if ( $val !== '' ) {
						$row[ $lang ] = $val;
					}
				}
				if ( ! empty( $row ) ) {
					$new_overrides[ $source ] = $row;
				}
			}
		}

		if ( ! empty( $_POST['mira_ui_new_source'] ) ) {
			$source = trim( sanitize_text_field( wp_unslash( $_POST['mira_ui_new_source'] ) ) );
			if ( $source !== '' ) {
				$row = array();
				foreach ( $other_languages as $lang ) {
					$val = isset( $_POST['mira_ui_new_translation'][ $lang ] ) ? sanitize_text_field( wp_unslash( $_POST['mira_ui_new_translation'][ $lang ] ) ) : '';
					if ( $val !== '' ) {
						$row[ $lang ] = $val;
					}
				}
				if ( ! empty( $row ) ) {
					$new_overrides[ $source ] = $row;
				}
			}
		}

		update_option( 'mira_ls_ui_strings', $new_overrides );
		$overrides = $new_overrides;
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mira-language-switcher' ) . '</p></div>';
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'UI Text', 'mira-language-switcher' ); ?></h1>
		<p>
			<?php esc_html_e( 'Override the display text of fixed strings from your theme or other plugins that don\'t translate on their own — e.g. WPBakery\'s "Read more" button. Enter the text exactly as it appears on the site (case-sensitive) and its translation per language. Leave a translation blank to leave that string untouched in that language. Only applies on the front end, and only when viewing a non-default language.', 'mira-language-switcher' ); ?>
		</p>

		<?php if ( empty( $other_languages ) ) : ?>
			<p class="description" style="color:#d63638;">
				<?php esc_html_e( 'No non-default languages are enabled — nothing to translate to. Check Settings > Enabled Languages.', 'mira-language-switcher' ); ?>
			</p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'mira_ls_ui_strings_save' ); ?>
				<table class="widefat striped" style="max-width: 900px;">
					<thead>
						<tr>
							<th style="width: 35%;"><?php esc_html_e( 'Original text', 'mira-language-switcher' ); ?></th>
							<?php foreach ( $other_languages as $lang ) : ?>
								<th><?php echo esc_html( strtoupper( $lang ) ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<?php $i = 0; foreach ( $overrides as $source => $translations ) : ?>
							<tr>
								<td><input type="text" name="mira_ui_source[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $source ); ?>" style="width:100%;" /></td>
								<?php foreach ( $other_languages as $lang ) : ?>
									<td><input type="text" name="mira_ui_translation[<?php echo (int) $i; ?>][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( isset( $translations[ $lang ] ) ? $translations[ $lang ] : '' ); ?>" style="width:100%;" /></td>
								<?php endforeach; ?>
							</tr>
						<?php $i++; endforeach; ?>
						<tr>
							<td><input type="text" name="mira_ui_new_source" value="" placeholder="<?php esc_attr_e( 'Add new… e.g. Read more', 'mira-language-switcher' ); ?>" style="width:100%;" /></td>
							<?php foreach ( $other_languages as $lang ) : ?>
								<td><input type="text" name="mira_ui_new_translation[<?php echo esc_attr( $lang ); ?>]" value="" style="width:100%;" /></td>
							<?php endforeach; ?>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save', 'mira-language-switcher' ), 'primary', 'mira_ls_ui_strings_save' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
