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
    update_option('mira_ls_add_to_menu', isset($_POST['mira_ls_add_to_menu']) ? 'yes' : 'no');
    update_option('mira_ls_menu_location', sanitize_text_field($_POST['mira_ls_menu_location']));
    update_option('mira_ls_menu_flag_type', sanitize_text_field($_POST['mira_ls_menu_flag_type']));

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'mira-language-switcher') . '</p></div>';
}

// Get current settings
$default_language = get_option('mira_ls_default_language', 'en');
$enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
$show_flags = get_option('mira_ls_show_flags', 1);
$add_to_menu = get_option('mira_ls_add_to_menu', 'no');
$menu_location = get_option('mira_ls_menu_location', 'all');
$menu_flag_type = get_option('mira_ls_menu_flag_type', 'emoji');

// Get registered menu locations
$menu_locations = get_registered_nav_menus();

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

        <h2><?php _e('Menu Integration', 'mira-language-switcher'); ?></h2>
        <p><?php _e('Automatically add language flags to your navigation menu.', 'mira-language-switcher'); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="mira_ls_add_to_menu"><?php _e('Add to Menu', 'mira-language-switcher'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="mira_ls_add_to_menu"
                               id="mira_ls_add_to_menu"
                               value="1"
                               <?php checked($add_to_menu, 'yes'); ?>>
                        <?php _e('Automatically add language flags to navigation menu', 'mira-language-switcher'); ?>
                    </label>
                    <p class="description"><?php _e('Enable this to show language switcher flags in your menu without editing theme files.', 'mira-language-switcher'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="mira_ls_menu_location"><?php _e('Menu Location', 'mira-language-switcher'); ?></label>
                </th>
                <td>
                    <select name="mira_ls_menu_location" id="mira_ls_menu_location" class="regular-text">
                        <option value="all" <?php selected($menu_location, 'all'); ?>>
                            <?php _e('All Menus', 'mira-language-switcher'); ?>
                        </option>
                        <?php if (!empty($menu_locations)): ?>
                            <?php foreach ($menu_locations as $location => $description): ?>
                                <option value="<?php echo esc_attr($location); ?>" <?php selected($menu_location, $location); ?>>
                                    <?php echo esc_html($description . ' (' . $location . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <p class="description">
                        <?php _e('Choose which menu to add the language flags to.', 'mira-language-switcher'); ?>
                        <?php if (empty($menu_locations)): ?>
                            <br><strong><?php _e('Note: Your theme has no registered menu locations.', 'mira-language-switcher'); ?></strong>
                        <?php endif; ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="mira_ls_menu_flag_type"><?php _e('Flag Display', 'mira-language-switcher'); ?></label>
                </th>
                <td>
                    <select name="mira_ls_menu_flag_type" id="mira_ls_menu_flag_type" class="regular-text">
                        <option value="emoji" <?php selected($menu_flag_type, 'emoji'); ?>>
                            <?php _e('Flag Emojis (🇬🇧 🇮🇹 🇪🇸)', 'mira-language-switcher'); ?>
                        </option>
                        <option value="text" <?php selected($menu_flag_type, 'text'); ?>>
                            <?php _e('Text Codes (EN IT ES)', 'mira-language-switcher'); ?>
                        </option>
                    </select>
                    <p class="description"><?php _e('Choose how to display the language switcher in the menu.', 'mira-language-switcher'); ?></p>
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
