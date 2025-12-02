<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Save settings
if (isset($_POST['mira_ls_save_settings']) && check_admin_referer('mira_ls_settings_nonce')) {
    update_option('mira_ls_default_language', sanitize_text_field($_POST['mira_ls_default_language']));
    update_option('mira_ls_enabled_languages', isset($_POST['mira_ls_enabled_languages']) ? array_map('sanitize_text_field', $_POST['mira_ls_enabled_languages']) : array());
    update_option('mira_ls_show_flags', isset($_POST['mira_ls_show_flags']) ? 1 : 0);

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'mira-language-switcher') . '</p></div>';
}

// Get current settings
$default_language = get_option('mira_ls_default_language', 'en');
$enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
$show_flags = get_option('mira_ls_show_flags', 1);

// Available languages
$available_languages = array(
    'en' => 'English',
    'es' => 'Spanish',
    'fr' => 'French',
    'de' => 'German',
    'it' => 'Italian',
    'pt' => 'Portuguese',
    'ru' => 'Russian',
    'ja' => 'Japanese',
    'zh' => 'Chinese',
    'ar' => 'Arabic'
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('mira_ls_settings_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mira_ls_default_language"><?php _e('Default Language', 'mira-language-switcher'); ?></label>
                </th>
                <td>
                    <select name="mira_ls_default_language" id="mira_ls_default_language" class="regular-text">
                        <?php foreach ($available_languages as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($default_language, $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the default language for your site.', 'mira-language-switcher'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php _e('Enabled Languages', 'mira-language-switcher'); ?></label>
                </th>
                <td>
                    <fieldset>
                        <?php foreach ($available_languages as $code => $name): ?>
                            <label>
                                <input type="checkbox"
                                       name="mira_ls_enabled_languages[]"
                                       value="<?php echo esc_attr($code); ?>"
                                       <?php checked(in_array($code, $enabled_languages)); ?>>
                                <?php echo esc_html($name); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description"><?php _e('Select which languages to enable on your site.', 'mira-language-switcher'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="mira_ls_show_flags"><?php _e('Display Options', 'mira-language-switcher'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="mira_ls_show_flags"
                               id="mira_ls_show_flags"
                               value="1"
                               <?php checked($show_flags, 1); ?>>
                        <?php _e('Show country flags next to language names', 'mira-language-switcher'); ?>
                    </label>
                    <p class="description"><?php _e('Enable this to display country flags alongside language names.', 'mira-language-switcher'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit"
                   name="mira_ls_save_settings"
                   class="button button-primary"
                   value="<?php _e('Save Settings', 'mira-language-switcher'); ?>">
        </p>
    </form>

    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php _e('Current Configuration', 'mira-language-switcher'); ?></h2>
        <p><strong><?php _e('Default Language:', 'mira-language-switcher'); ?></strong>
            <?php echo esc_html($available_languages[$default_language] ?? 'Not set'); ?>
        </p>
        <p><strong><?php _e('Enabled Languages:', 'mira-language-switcher'); ?></strong>
            <?php
            if (!empty($enabled_languages)) {
                $enabled_names = array_map(function($code) use ($available_languages) {
                    return $available_languages[$code] ?? $code;
                }, $enabled_languages);
                echo esc_html(implode(', ', $enabled_names));
            } else {
                _e('None', 'mira-language-switcher');
            }
            ?>
        </p>
        <p><strong><?php _e('Show Flags:', 'mira-language-switcher'); ?></strong>
            <?php echo $show_flags ? __('Yes', 'mira-language-switcher') : __('No', 'mira-language-switcher'); ?>
        </p>
    </div>
</div>
