<?php
/**
 * Plugin Name: Mira Language Switcher
 * Plugin URI: https://example.com/mira-language-switcher
 * Description: A simple language switcher plugin with setup and settings pages
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mira-language-switcher
 *
 * Changelog:
 * 1.1.0 - Added language-specific menu locations and dynamic language support based on settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MIRA_LS_VERSION', '1.1.0');
define('MIRA_LS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MIRA_LS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MIRA_LS_DEFAULT_LANGUAGE', 'en');
define('MIRA_LS_SUPPORTED_LANGUAGES', array('en', 'it', 'es'));
define('MIRA_LS_TRANSLATIONS_OPTION', 'mira_ls_translation_links');

/**
 * Main Mira Language Switcher Class
 */
class Mira_Language_Switcher {

    /**
     * Current language code
     */
    private $current_language;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize current language
        $this->current_language = $this->detect_language();

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_bar_menu', array($this, 'add_language_to_admin_bar'), 100);
        add_action('admin_post_save_translation_links', array($this, 'save_translation_links'));

        // Page language metabox
        add_action('add_meta_boxes', array($this, 'add_language_metabox'));
        add_action('save_post', array($this, 'save_language_metabox'));

        // URL rewrite hooks
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Prevent WordPress from redirecting language URLs
        add_filter('redirect_canonical', array($this, 'prevent_language_redirect'), 10, 2);

        // Load translated content
        add_action('pre_get_posts', array($this, 'load_translated_content'));

        // Register shortcodes
        add_shortcode('lang_flag_en', array($this, 'shortcode_lang_flag_en'));
        add_shortcode('lang_flag_it', array($this, 'shortcode_lang_flag_it'));
        add_shortcode('lang_flag_es', array($this, 'shortcode_lang_flag_es'));

        // Language-specific menu locations
        add_action('after_setup_theme', array($this, 'register_language_menu_locations'), 999);
        add_filter('theme_mod_nav_menu_locations', array($this, 'filter_menu_locations'), 10, 1);

        // Add language flags to menu
        add_filter('wp_nav_menu_items', array($this, 'add_flags_to_menu'), 10, 2);
        add_action('wp_head', array($this, 'menu_flags_css'));

        // Redirect URLs without language prefix to include default language
        add_action('template_redirect', array($this, 'redirect_to_language_prefix'), 5);

        // Automatic redirects based on cookie
        add_action('template_redirect', array($this, 'auto_redirect_to_translation'), 10);

        // Title modification
        add_filter('the_title', array($this, 'modify_title_with_language'), 10, 2);
        add_filter('wp_title', array($this, 'modify_wp_title'), 10, 2);

        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add main menu item
        add_menu_page(
            __('Mira Language Switcher', 'mira-language-switcher'),
            __('Mira Language', 'mira-language-switcher'),
            'manage_options',
            'mira-language-switcher',
            array($this, 'setup_page'),
            'dashicons-translation',
            30
        );

        // Add Setup submenu
        add_submenu_page(
            'mira-language-switcher',
            __('Setup', 'mira-language-switcher'),
            __('Setup', 'mira-language-switcher'),
            'manage_options',
            'mira-language-switcher',
            array($this, 'setup_page')
        );

        // Add Settings submenu
        add_submenu_page(
            'mira-language-switcher',
            __('Settings', 'mira-language-switcher'),
            __('Settings', 'mira-language-switcher'),
            'manage_options',
            'mira-language-switcher-settings',
            array($this, 'settings_page')
        );

        // Add Translation Links submenu
        add_submenu_page(
            'mira-language-switcher',
            __('Translation Links', 'mira-language-switcher'),
            __('Translation Links', 'mira-language-switcher'),
            'manage_options',
            'mira-language-switcher-translations',
            array($this, 'translation_links_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mira_ls_settings_group', 'mira_ls_default_language');
        register_setting('mira_ls_settings_group', 'mira_ls_enabled_languages');
        register_setting('mira_ls_settings_group', 'mira_ls_show_flags');
        register_setting('mira_ls_settings_group', 'mira_ls_add_to_menu');
        register_setting('mira_ls_settings_group', 'mira_ls_menu_location');
        register_setting('mira_ls_settings_group', 'mira_ls_menu_flag_type');
        register_setting('mira_ls_settings_group', 'mira_ls_auto_redirect');
        register_setting('mira_ls_settings_group', 'mira_ls_show_lang_in_title');
    }

    /**
     * Register language-specific menu locations
     * Duplicates each menu location for each enabled language
     */
    public function register_language_menu_locations() {
        // Get currently registered nav menus from the theme
        $theme_locations = get_registered_nav_menus();

        if (empty($theme_locations)) {
            return;
        }

        // Get enabled languages
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));

        // Language names mapping
        $language_names = array(
            'en' => __('English', 'mira-language-switcher'),
            'es' => __('Spanish', 'mira-language-switcher'),
            'fr' => __('French', 'mira-language-switcher'),
            'de' => __('German', 'mira-language-switcher'),
            'it' => __('Italian', 'mira-language-switcher'),
            'pt' => __('Portuguese', 'mira-language-switcher'),
            'ru' => __('Russian', 'mira-language-switcher'),
            'ja' => __('Japanese', 'mira-language-switcher'),
            'zh' => __('Chinese', 'mira-language-switcher'),
            'ar' => __('Arabic', 'mira-language-switcher')
        );

        $new_locations = array();

        // For each language, create language-specific menu locations
        foreach ($enabled_languages as $lang) {
            $lang_name = isset($language_names[$lang]) ? $language_names[$lang] : strtoupper($lang);

            foreach ($theme_locations as $location => $description) {
                // Create language-specific location key
                $lang_location = $location . '_' . $lang;

                // Create language-specific description
                /* translators: 1: original menu location name, 2: language name */
                $lang_description = sprintf(__('%1$s (%2$s)', 'mira-language-switcher'), $description, $lang_name);

                $new_locations[$lang_location] = $lang_description;
            }
        }

        // Register the new language-specific menu locations
        if (!empty($new_locations)) {
            register_nav_menus($new_locations);
        }
    }

    /**
     * Filter menu locations to show correct menu based on current language
     *
     * @param array $locations Menu locations with assigned menus
     * @return array Modified locations
     */
    public function filter_menu_locations($locations) {
        // Only filter on frontend, not in admin
        if (is_admin()) {
            return $locations;
        }

        // Get current language
        $current_lang = $this->get_current_language();

        // Get original theme locations
        $theme_locations = get_registered_nav_menus();

        $filtered_locations = array();

        foreach ($theme_locations as $location => $description) {
            // Check if there's a language-specific menu assigned
            $lang_location = $location . '_' . $current_lang;

            if (isset($locations[$lang_location]) && !empty($locations[$lang_location])) {
                // Use the language-specific menu
                $filtered_locations[$location] = $locations[$lang_location];
            } elseif (isset($locations[$location])) {
                // Fallback to the original location if no language-specific menu
                $filtered_locations[$location] = $locations[$location];
            }
        }

        return $filtered_locations;
    }

    /**
     * Setup page callback
     */
    public function setup_page() {
        include_once MIRA_LS_PLUGIN_DIR . 'includes/setup-page.php';
    }

    /**
     * Settings page callback
     */
    public function settings_page() {
        include_once MIRA_LS_PLUGIN_DIR . 'includes/settings-page.php';
    }

    /**
     * Detect current language from URL
     *
     * @return string Language code (en, it, es, etc.)
     */
    public function detect_language() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $detected_lang = null;

        // Get enabled languages from settings
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
        $default_language = get_option('mira_ls_default_language', 'en');

        // Get WordPress home path (handles subdirectory installations)
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path) {
            $home_path = rtrim($home_path, '/');
        }

        // Check for language in URL pattern after the WordPress path
        // Example: /plug/en/about-us/ where /plug is the WordPress subdirectory
        $pattern = '#^' . preg_quote($home_path, '#') . '/(' . implode('|', $enabled_languages) . ')(/|$)#';

        if (preg_match($pattern, $request_uri, $matches)) {
            $detected_lang = $matches[1];

            // Set cookie when language is detected from URL (30 days)
            if (!headers_sent()) {
                setcookie('mira_language', $detected_lang, time() + (30 * 24 * 60 * 60), '/');
            }

            return $detected_lang;
        }

        // Check if language cookie exists
        if (isset($_COOKIE['mira_language']) && in_array($_COOKIE['mira_language'], $enabled_languages)) {
            return $_COOKIE['mira_language'];
        }

        // Return default language if no language detected
        return $default_language;
    }

    /**
     * Get current language
     *
     * @return string Current language code
     */
    public function get_current_language() {
        return $this->current_language;
    }

    /**
     * Add rewrite rules for language prefixes
     */
    public function add_rewrite_rules() {
        $languages = MIRA_LS_SUPPORTED_LANGUAGES;

        foreach ($languages as $lang) {
            // Rule for homepage with language prefix
            add_rewrite_rule(
                '^' . $lang . '/?$',
                'index.php?lang=' . $lang,
                'top'
            );

            // Rule for single-level pages/posts (e.g., /en/about-us)
            add_rewrite_rule(
                '^' . $lang . '/([^/]+)/?$',
                'index.php?lang=' . $lang . '&pagename=$matches[1]',
                'top'
            );

            // Rule for nested paths (e.g., /en/parent/child)
            add_rewrite_rule(
                '^' . $lang . '/(.+)/?$',
                'index.php?lang=' . $lang . '&pagename=$matches[1]',
                'top'
            );
        }
    }

    /**
     * Add custom query vars
     *
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'lang';
        return $vars;
    }

    /**
     * Prevent WordPress from redirecting language URLs
     *
     * @param string $redirect_url The redirect URL
     * @param string $requested_url The requested URL
     * @return string|false Modified redirect URL or false to prevent redirect
     */
    public function prevent_language_redirect($redirect_url, $requested_url) {
        // Get the actual request URI
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Get enabled languages from settings
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));

        // Check if the requested URL contains a language prefix
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path) {
            $home_path = rtrim($home_path, '/');
        }

        $pattern = '#' . preg_quote($home_path, '#') . '/(' . implode('|', $enabled_languages) . ')(/|$)#';

        // If URL has language prefix in REQUEST_URI, don't redirect
        if (preg_match($pattern, $request_uri)) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Load translated content based on language URL
     *
     * @param WP_Query $query The WordPress query object
     */
    public function load_translated_content($query) {
        // Only modify main query on frontend
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Get the language from query var
        $lang = get_query_var('lang');

        // Get default language from settings
        $default_language = get_option('mira_ls_default_language', 'en');

        // If no language in URL, do nothing
        if (empty($lang)) {
            return;
        }

        // Get the page name from query
        $pagename = get_query_var('pagename');

        // Handle front page (homepage) specially
        if (empty($pagename)) {
            // Check if this is a front page request (just the language prefix with no page)
            $page_on_front = get_option('page_on_front');

            if ($page_on_front) {
                // If we're on a language URL like /en/ or /it/ with no page
                // and WordPress has a static front page set, load the appropriate translation

                if ($lang === $default_language) {
                    // Show the default language front page
                    $query->set('page_id', $page_on_front);
                    $query->set('post_type', 'page');
                } else {
                    // Get the translated front page for this language
                    $translated_id = self::get_translation($page_on_front, $lang);

                    if ($translated_id) {
                        $query->set('page_id', $translated_id);
                        $query->set('post_type', 'page');
                    } else {
                        // No translation exists, show default language front page
                        $query->set('page_id', $page_on_front);
                        $query->set('post_type', 'page');
                    }
                }
            }
            return;
        }

        // Find the default language page by slug
        $default_page = get_page_by_path($pagename);
        if (!$default_page) {
            return;
        }

        // If the requested language is the default language, show the default page
        if ($lang === $default_language) {
            // The default page is already being loaded, no need to modify query
            return;
        }

        // Get the translated page ID for this language
        $translated_id = self::get_translation($default_page->ID, $lang);

        // If translation exists, modify the query to load it
        if ($translated_id) {
            $query->set('page_id', $translated_id);
            $query->set('pagename', '');
        }
    }

    /**
     * Add language indicator to admin bar
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function add_language_to_admin_bar($wp_admin_bar) {
        $current_lang = $this->get_current_language();

        $language_names = array(
            'en' => 'English',
            'it' => 'Italian',
            'es' => 'Spanish'
        );

        $lang_name = isset($language_names[$current_lang]) ? $language_names[$current_lang] : $current_lang;

        $wp_admin_bar->add_node(array(
            'id'    => 'mira-current-language',
            'title' => '🌐 ' . $lang_name . ' (' . strtoupper($current_lang) . ')',
            'href'  => admin_url('admin.php?page=mira-language-switcher-settings'),
            'meta'  => array(
                'title' => __('Current Language - Click to manage', 'mira-language-switcher'),
            ),
        ));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Add rewrite rules
        $this->add_rewrite_rules();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Translation Links page callback
     */
    public function translation_links_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get enabled languages from settings
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
        $default_language = get_option('mira_ls_default_language', 'en');

        // Language names mapping
        $language_names = array(
            'en' => __('English', 'mira-language-switcher'),
            'es' => __('Spanish', 'mira-language-switcher'),
            'fr' => __('French', 'mira-language-switcher'),
            'de' => __('German', 'mira-language-switcher'),
            'it' => __('Italian', 'mira-language-switcher'),
            'pt' => __('Portuguese', 'mira-language-switcher'),
            'ru' => __('Russian', 'mira-language-switcher'),
            'ja' => __('Japanese', 'mira-language-switcher'),
            'zh' => __('Chinese', 'mira-language-switcher'),
            'ar' => __('Arabic', 'mira-language-switcher')
        );

        // Get all pages
        $all_pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Filter pages by language - build array dynamically based on enabled languages
        $pages_by_language = array();
        foreach ($enabled_languages as $lang) {
            $pages_by_language[$lang] = array();
        }

        foreach ($all_pages as $page) {
            $page_lang = self::get_page_language($page->ID);

            // Only include pages for enabled languages
            if (in_array($page_lang, $enabled_languages)) {
                $pages_by_language[$page_lang][] = $page;
            }
        }

        // Get pages in default language for the main column
        $default_pages = isset($pages_by_language[$default_language]) ? $pages_by_language[$default_language] : array();

        // Get translation languages (all enabled languages except default)
        $translation_languages = array_diff($enabled_languages, array($default_language));

        // Get saved translation links
        $translation_links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());
        if (!is_array($translation_links)) {
            $translation_links = array();
        }

        // Display success message if just saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 __('Translation links saved successfully!', 'mira-language-switcher') .
                 '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Translation Links', 'mira-language-switcher'); ?></h1>
            <p><?php
                /* translators: %s: name of default language */
                printf(__('Link %s pages to their translations.', 'mira-language-switcher'),
                    isset($language_names[$default_language]) ? $language_names[$default_language] : $default_language
                );
            ?></p>

            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Page counts:', 'mira-language-switcher'); ?></strong>
                    <?php
                    $count_parts = array();
                    foreach ($enabled_languages as $lang) {
                        $lang_name = isset($language_names[$lang]) ? $language_names[$lang] : strtoupper($lang);
                        $count = isset($pages_by_language[$lang]) ? count($pages_by_language[$lang]) : 0;
                        /* translators: 1: language name, 2: number of pages */
                        $count_parts[] = sprintf(__('%1$s: %2$d', 'mira-language-switcher'), $lang_name, $count);
                    }
                    echo implode(' | ', $count_parts);
                    ?>
                </p>
            </div>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('save_translation_links_action', 'translation_links_nonce'); ?>
                <input type="hidden" name="action" value="save_translation_links">

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: <?php echo count($translation_languages) > 0 ? 35 : 95; ?>%;">
                                <?php
                                /* translators: %s: name of default language */
                                printf(__('%s Page', 'mira-language-switcher'),
                                    isset($language_names[$default_language]) ? $language_names[$default_language] : ucfirst($default_language)
                                );
                                ?>
                            </th>
                            <?php
                            $col_width = count($translation_languages) > 0 ? floor(60 / count($translation_languages)) : 0;
                            foreach ($translation_languages as $lang):
                            ?>
                                <th style="width: <?php echo $col_width; ?>%;">
                                    <?php
                                    /* translators: %s: name of language */
                                    printf(__('%s Translation', 'mira-language-switcher'),
                                        isset($language_names[$lang]) ? $language_names[$lang] : ucfirst($lang)
                                    );
                                    ?>
                                </th>
                            <?php endforeach; ?>
                            <th style="width: 5%;"><?php _e('ID', 'mira-language-switcher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($default_pages)): ?>
                            <tr>
                                <td colspan="<?php echo count($translation_languages) + 2; ?>">
                                    <?php
                                    /* translators: %s: name of default language */
                                    printf(__('No %s pages found. Please set page languages first.', 'mira-language-switcher'),
                                        isset($language_names[$default_language]) ? $language_names[$default_language] : ucfirst($default_language)
                                    );
                                    ?>
                                    <br>
                                    <small><?php _e('Edit a page and set its language in the Language metabox.', 'mira-language-switcher'); ?></small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($default_pages as $page): ?>
                                <?php $page_id = $page->ID; ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($page->post_title); ?></strong>
                                        <br>
                                        <small style="color: #666;">
                                            <a href="<?php echo get_permalink($page->ID); ?>" target="_blank">
                                                <?php echo esc_html($page->post_name); ?>
                                            </a>
                                        </small>
                                    </td>
                                    <?php foreach ($translation_languages as $lang): ?>
                                        <?php
                                        $lang_pages = isset($pages_by_language[$lang]) ? $pages_by_language[$lang] : array();
                                        $selected_page = isset($translation_links[$page_id][$lang]) ? $translation_links[$page_id][$lang] : '';
                                        ?>
                                        <td>
                                            <select name="translations[<?php echo $page_id; ?>][<?php echo $lang; ?>]" style="width: 100%;">
                                                <option value="">
                                                    <?php
                                                    /* translators: %s: name of language */
                                                    printf(__('-- Select %s Page --', 'mira-language-switcher'),
                                                        isset($language_names[$lang]) ? $language_names[$lang] : ucfirst($lang)
                                                    );
                                                    ?>
                                                </option>
                                                <?php foreach ($lang_pages as $option_page): ?>
                                                    <option value="<?php echo $option_page->ID; ?>"
                                                        <?php selected($selected_page, $option_page->ID); ?>>
                                                        <?php echo esc_html($option_page->post_title); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (empty($lang_pages)): ?>
                                                <small style="color: #999;">
                                                    <?php
                                                    /* translators: %s: name of language */
                                                    printf(__('No %s pages available', 'mira-language-switcher'),
                                                        isset($language_names[$lang]) ? $language_names[$lang] : ucfirst($lang)
                                                    );
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <small style="color: #666;"><?php echo $page_id; ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary"
                           value="<?php echo esc_attr__('Save Translation Links', 'mira-language-switcher'); ?>">
                </p>
            </form>

            <hr>

            <h2><?php _e('Translation Links Summary', 'mira-language-switcher'); ?></h2>
            <p><?php _e('Current translation links stored in the database:', 'mira-language-switcher'); ?></p>

            <?php if (empty($translation_links)): ?>
                <p><em><?php _e('No translation links have been saved yet.', 'mira-language-switcher'); ?></em></p>
            <?php else: ?>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>
                                <?php
                                /* translators: %s: name of default language */
                                printf(__('%s Page', 'mira-language-switcher'),
                                    isset($language_names[$default_language]) ? $language_names[$default_language] : ucfirst($default_language)
                                );
                                ?>
                            </th>
                            <?php foreach ($translation_languages as $lang): ?>
                                <th>
                                    <?php
                                    /* translators: %s: name of language */
                                    printf(__('%s Translation', 'mira-language-switcher'),
                                        isset($language_names[$lang]) ? $language_names[$lang] : ucfirst($lang)
                                    );
                                    ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($translation_links as $default_id => $translations): ?>
                            <?php
                            // Check if any translation exists for enabled languages
                            $has_translation = false;
                            foreach ($translation_languages as $lang) {
                                if (!empty($translations[$lang])) {
                                    $has_translation = true;
                                    break;
                                }
                            }
                            ?>
                            <?php if ($has_translation): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $default_page = get_post($default_id);
                                        if ($default_page) {
                                            echo '<strong>' . esc_html($default_page->post_title) . '</strong>';
                                            echo '<br><small>ID: ' . $default_id . '</small>';
                                        } else {
                                            echo 'ID: ' . $default_id . ' <em>(page not found)</em>';
                                        }
                                        ?>
                                    </td>
                                    <?php foreach ($translation_languages as $lang): ?>
                                        <td>
                                            <?php
                                            if (!empty($translations[$lang])) {
                                                $trans_page = get_post($translations[$lang]);
                                                if ($trans_page) {
                                                    echo esc_html($trans_page->post_title);
                                                    echo '<br><small>ID: ' . $translations[$lang] . '</small>';
                                                } else {
                                                    echo 'ID: ' . $translations[$lang] . ' <em>(page not found)</em>';
                                                }
                                            } else {
                                                echo '<em>' . __('Not set', 'mira-language-switcher') . '</em>';
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <hr>

            <h2><?php _e('Database Information', 'mira-language-switcher'); ?></h2>
            <p>
                <strong><?php _e('Option Name:', 'mira-language-switcher'); ?></strong>
                <code><?php echo MIRA_LS_TRANSLATIONS_OPTION; ?></code>
            </p>
            <p>
                <strong><?php _e('Storage Location:', 'mira-language-switcher'); ?></strong>
                <?php _e('WordPress options table (wp_options)', 'mira-language-switcher'); ?>
            </p>
            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; font-weight: bold;">
                    <?php _e('View Raw Data (for debugging)', 'mira-language-switcher'); ?>
                </summary>
                <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; margin-top: 10px; overflow: auto; max-height: 300px;"><?php
                echo esc_html(print_r($translation_links, true));
                ?></pre>
            </details>
        </div>
        <?php
    }

    /**
     * Save translation links
     */
    public function save_translation_links() {
        // Check nonce
        if (!isset($_POST['translation_links_nonce']) ||
            !wp_verify_nonce($_POST['translation_links_nonce'], 'save_translation_links_action')) {
            wp_die(__('Security check failed', 'mira-language-switcher'));
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get enabled languages and default language
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
        $default_language = get_option('mira_ls_default_language', 'en');

        // Get translation languages (all enabled except default)
        $translation_languages = array_diff($enabled_languages, array($default_language));

        // Get the translations data
        $translations = isset($_POST['translations']) ? $_POST['translations'] : array();

        // Clean and validate the data
        $clean_translations = array();
        foreach ($translations as $page_id => $langs) {
            $page_id = absint($page_id);

            if ($page_id > 0) {
                $clean_translations[$page_id] = array();

                // Process each translation language dynamically
                foreach ($translation_languages as $lang) {
                    if (!empty($langs[$lang])) {
                        $clean_translations[$page_id][$lang] = absint($langs[$lang]);
                    }
                }

                // Remove entry if no translations set
                if (empty($clean_translations[$page_id])) {
                    unset($clean_translations[$page_id]);
                }
            }
        }

        // Save to database
        update_option(MIRA_LS_TRANSLATIONS_OPTION, $clean_translations);

        // Redirect back to settings page with success message
        wp_redirect(add_query_arg(
            array(
                'page' => 'mira-language-switcher-translations',
                'settings-updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Get translation for a specific page
     *
     * @param int $page_id The page ID
     * @param string $lang The language code (it, es)
     * @return int|false The translated page ID or false if not found
     */
    public static function get_translation($page_id, $lang) {
        $links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());

        if (isset($links[$page_id][$lang])) {
            return absint($links[$page_id][$lang]);
        }

        return false;
    }

    /**
     * Get all translations for a specific page
     *
     * @param int $page_id The page ID
     * @return array Array with 'it' and 'es' keys
     */
    public static function get_all_translations($page_id) {
        $links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());

        if (isset($links[$page_id])) {
            return $links[$page_id];
        }

        return array('it' => false, 'es' => false);
    }

    /**
     * Add language metabox to page edit screen
     */
    public function add_language_metabox() {
        $post_types = array('page', 'post');

        foreach ($post_types as $post_type) {
            add_meta_box(
                'mira_page_language',
                __('Language', 'mira-language-switcher'),
                array($this, 'render_language_metabox'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render language metabox content
     *
     * @param WP_Post $post The current post object
     */
    public function render_language_metabox($post) {
        // Add nonce for security
        wp_nonce_field('mira_language_metabox', 'mira_language_metabox_nonce');

        // Get current language value
        $current_language = get_post_meta($post->ID, '_mira_page_language', true);

        // Get enabled languages from settings
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
        $default_language = get_option('mira_ls_default_language', 'en');

        // If no language set, default to default language
        if (empty($current_language)) {
            $current_language = $default_language;
        }

        // All available language names
        $all_language_names = array(
            'en' => __('English', 'mira-language-switcher'),
            'es' => __('Spanish', 'mira-language-switcher'),
            'fr' => __('French', 'mira-language-switcher'),
            'de' => __('German', 'mira-language-switcher'),
            'it' => __('Italian', 'mira-language-switcher'),
            'pt' => __('Portuguese', 'mira-language-switcher'),
            'ru' => __('Russian', 'mira-language-switcher'),
            'ja' => __('Japanese', 'mira-language-switcher'),
            'zh' => __('Chinese', 'mira-language-switcher'),
            'ar' => __('Arabic', 'mira-language-switcher')
        );

        // Build language options from enabled languages only
        $languages = array();
        foreach ($enabled_languages as $code) {
            if (isset($all_language_names[$code])) {
                $languages[$code] = $all_language_names[$code];
            } else {
                $languages[$code] = strtoupper($code);
            }
        }

        ?>
        <div class="mira-language-metabox">
            <p>
                <label for="mira_page_language">
                    <strong><?php _e('Page Language:', 'mira-language-switcher'); ?></strong>
                </label>
            </p>
            <select name="mira_page_language" id="mira_page_language" style="width: 100%;">
                <?php foreach ($languages as $code => $name): ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($current_language, $code); ?>>
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php _e('Select the language this page is written in.', 'mira-language-switcher'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save language metabox data
     *
     * @param int $post_id The post ID
     */
    public function save_language_metabox($post_id) {
        // Check if nonce is set
        if (!isset($_POST['mira_language_metabox_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['mira_language_metabox_nonce'], 'mira_language_metabox')) {
            return;
        }

        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        $post_type = get_post_type($post_id);
        $post_type_object = get_post_type_object($post_type);
        if (!current_user_can($post_type_object->cap->edit_post, $post_id)) {
            return;
        }

        // Check if this is a supported post type
        if (!in_array($post_type, array('page', 'post'))) {
            return;
        }

        // Save the language value
        if (isset($_POST['mira_page_language'])) {
            $language = sanitize_text_field($_POST['mira_page_language']);

            // Get enabled languages from settings
            $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));

            // Validate language code against enabled languages
            if (in_array($language, $enabled_languages)) {
                update_post_meta($post_id, '_mira_page_language', $language);
            }
        }
    }

    /**
     * Get page language
     *
     * @param int $page_id The page ID
     * @return string Language code (en, it, es)
     */
    public static function get_page_language($page_id) {
        $language = get_post_meta($page_id, '_mira_page_language', true);

        // If no language set, return default
        if (empty($language)) {
            return MIRA_LS_DEFAULT_LANGUAGE;
        }

        return $language;
    }

    /**
     * Redirect URLs without language prefix to include default language prefix
     */
    public function redirect_to_language_prefix() {
        // Only on frontend, not admin
        if (is_admin()) {
            return;
        }

        // Get the request URI
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Get enabled languages and default language
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
        $default_language = get_option('mira_ls_default_language', 'en');

        // Get WordPress home path
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path) {
            $home_path = rtrim($home_path, '/');
        }

        // Check if URL already has a language prefix
        $pattern = '#^' . preg_quote($home_path, '#') . '/(' . implode('|', $enabled_languages) . ')(/|$)#';
        if (preg_match($pattern, $request_uri)) {
            // URL already has language prefix, no redirect needed
            return;
        }

        // Check if this is a page request (not homepage, not admin, not wp-content, etc.)
        // Only redirect actual page URLs
        if (is_404() || is_search() || is_feed()) {
            return;
        }

        // Build the redirect URL with default language prefix
        // Remove home_path from request_uri if present
        $relative_uri = $request_uri;
        if ($home_path && strpos($request_uri, $home_path) === 0) {
            $relative_uri = substr($request_uri, strlen($home_path));
        }

        // Remove query string for processing
        $query_string = '';
        if (strpos($relative_uri, '?') !== false) {
            list($relative_uri, $query_string) = explode('?', $relative_uri, 2);
            $query_string = '?' . $query_string;
        }

        // Add default language prefix
        $redirect_url = home_url('/' . $default_language . $relative_uri . $query_string);

        // Redirect
        wp_redirect($redirect_url, 301);
        exit;
    }

    /**
     * Automatically redirect to translated version if available
     */
    public function auto_redirect_to_translation() {
        // Only on frontend, not admin
        if (is_admin()) {
            return;
        }

        // Check if auto-redirect is enabled
        $auto_redirect = get_option('mira_ls_auto_redirect', 'no');
        if ($auto_redirect !== 'yes') {
            return;
        }

        // Get default language from settings
        $default_language = get_option('mira_ls_default_language', 'en');

        // Get current page
        $current_page_id = get_the_ID();
        if (!$current_page_id || !is_page()) {
            return;
        }

        // Get current language from URL and cookie
        $url_lang = $this->detect_language_from_url();
        $cookie_lang = isset($_COOKIE['mira_language']) ? $_COOKIE['mira_language'] : '';

        // If there's a language in the URL, don't redirect (user explicitly chose it)
        if ($url_lang !== $default_language) {
            return;
        }

        // If cookie language is same as default, no redirect needed
        if (empty($cookie_lang) || $cookie_lang === $default_language) {
            return;
        }

        // Check if current page is in default language
        $page_lang = self::get_page_language($current_page_id);
        if ($page_lang !== $default_language) {
            return; // Already on a translated page
        }

        // Get translation for cookie language
        $translated_id = self::get_translation($current_page_id, $cookie_lang);

        if ($translated_id) {
            // Build redirect URL
            $current_page = get_post($current_page_id);
            $redirect_url = home_url('/' . $cookie_lang . '/' . $current_page->post_name . '/');

            // Redirect
            wp_redirect($redirect_url, 302);
            exit;
        }

        // No translation exists - show default language version (do nothing)
    }

    /**
     * Detect language from URL only (not cookie)
     *
     * @return string Language code
     */
    private function detect_language_from_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        // Get enabled languages from settings
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));
        $default_language = get_option('mira_ls_default_language', 'en');

        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path) {
            $home_path = rtrim($home_path, '/');
        }

        $pattern = '#^' . preg_quote($home_path, '#') . '/(' . implode('|', $enabled_languages) . ')(/|$)#';

        if (preg_match($pattern, $request_uri, $matches)) {
            return $matches[1];
        }

        return $default_language;
    }

    /**
     * Modify page/post title to include language prefix
     *
     * @param string $title The title
     * @param int $id Post ID
     * @return string Modified title
     */
    public function modify_title_with_language($title, $id = null) {
        // Check if feature is enabled
        $show_lang = get_option('mira_ls_show_lang_in_title', 'no');
        if ($show_lang !== 'yes') {
            return $title;
        }

        // Only modify on frontend
        if (is_admin()) {
            return $title;
        }

        // Get page language
        if ($id) {
            $page_lang = self::get_page_language($id);

            // Only add prefix for non-English pages
            if ($page_lang !== 'en') {
                $lang_labels = array(
                    'it' => '[IT]',
                    'es' => '[ES]'
                );

                if (isset($lang_labels[$page_lang])) {
                    $title = $lang_labels[$page_lang] . ' ' . $title;
                }
            }
        }

        return $title;
    }

    /**
     * Modify wp_title (browser title bar)
     *
     * @param string $title The title
     * @param string $sep Separator
     * @return string Modified title
     */
    public function modify_wp_title($title, $sep = '|') {
        // Check if feature is enabled
        $show_lang = get_option('mira_ls_show_lang_in_title', 'no');
        if ($show_lang !== 'yes') {
            return $title;
        }

        // Get current language
        $current_lang = $this->get_current_language();

        // Only add for non-English
        if ($current_lang !== 'en') {
            $lang_labels = array(
                'it' => '[IT]',
                'es' => '[ES]'
            );

            if (isset($lang_labels[$current_lang])) {
                $title = $lang_labels[$current_lang] . ' ' . $title;
            }
        }

        return $title;
    }

    /**
     * Add language flags to navigation menu
     *
     * @param string $items The menu items HTML
     * @param object $args Menu arguments
     * @return string Modified menu items
     */
    public function add_flags_to_menu($items, $args) {
        // Check if feature is enabled
        $add_to_menu = get_option('mira_ls_add_to_menu', 'no');
        if ($add_to_menu !== 'yes') {
            return $items;
        }

        // Get configured menu location (default to all menus if not set)
        $target_location = get_option('mira_ls_menu_location', 'all');

        // Check if we should add to this menu
        if ($target_location !== 'all' && $args->theme_location !== $target_location) {
            return $items;
        }

        // Get enabled languages
        $enabled_languages = get_option('mira_ls_enabled_languages', array('en'));

        // Get flag type (emoji or text)
        $flag_type = get_option('mira_ls_menu_flag_type', 'emoji');

        // Flag emojis mapping
        $flag_emojis = array(
            'en' => '🇬🇧',
            'es' => '🇪🇸',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            'ru' => '🇷🇺',
            'ja' => '🇯🇵',
            'zh' => '🇨🇳',
            'ar' => '🇸🇦'
        );

        // Generate flags for each enabled language
        $flags = '';
        foreach ($enabled_languages as $lang) {
            $url = $this->get_language_url($lang);
            $current_lang = $this->get_current_language();
            $is_current = ($current_lang === $lang);

            if ($flag_type === 'text') {
                $label = strtoupper($lang);
            } else {
                $label = isset($flag_emojis[$lang]) ? $flag_emojis[$lang] : strtoupper($lang);
            }

            if ($is_current) {
                $flags .= '<span class="lang-flag-' . esc_attr($lang) . ' current-lang">' . $label . '</span> ';
            } else {
                $flags .= '<a href="' . esc_url($url) . '" class="lang-flag-' . esc_attr($lang) . '">' . $label . '</a> ';
            }
        }

        // Wrap in menu item
        $lang_item = '<li class="menu-item menu-item-type-custom menu-item-language-switcher">';
        $lang_item .= trim($flags);
        $lang_item .= '</li>';

        // Add to end of menu
        $items .= $lang_item;

        return $items;
    }

    /**
     * Add CSS for language flags in menu
     */
    public function menu_flags_css() {
        // Only output if feature is enabled
        $add_to_menu = get_option('mira_ls_add_to_menu', 'no');
        if ($add_to_menu !== 'yes') {
            return;
        }
        ?>
        <style>
        .menu-item-language-switcher {
            display: flex !important;
            align-items: center;
        }
        .menu-item-language-switcher a,
        .menu-item-language-switcher span {
            font-size: 22px;
            text-decoration: none;
            margin: 0 8px;
            transition: opacity 0.3s ease;
        }
        .menu-item-language-switcher a:hover {
            opacity: 0.7;
        }
        .menu-item-language-switcher .current-lang {
            opacity: 0.5;
            cursor: default;
        }
        @media (max-width: 768px) {
            .menu-item-language-switcher a,
            .menu-item-language-switcher span {
                font-size: 18px;
                margin: 0 5px;
            }
        }
        </style>
        <?php
    }

    /**
     * Get URL for current page in specified language
     *
     * @param string $target_lang Target language code (en, it, es, etc.)
     * @return string|false URL or false if not available
     */
    private function get_language_url($target_lang) {
        // Get default language
        $default_language = get_option('mira_ls_default_language', 'en');

        // Get current page ID
        $current_page_id = get_the_ID();
        if (!$current_page_id) {
            // Return homepage with language prefix (always include prefix now)
            return home_url('/' . $target_lang . '/');
        }

        // Check if current page is the front page
        $page_on_front = get_option('page_on_front');
        if ($page_on_front && $current_page_id == $page_on_front) {
            // This is the front page in the default language
            // Get the translated front page if it exists
            if ($target_lang === $default_language) {
                // Same language - return homepage with language prefix
                return home_url('/' . $target_lang . '/');
            } else {
                // Different language - check if translation exists
                $translated_id = self::get_translation($page_on_front, $target_lang);
                if ($translated_id) {
                    // Translation exists - return language homepage
                    return home_url('/' . $target_lang . '/');
                }
                // No translation, return language homepage anyway
                return home_url('/' . $target_lang . '/');
            }
        }

        // Check if current page is a translated front page
        $links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());
        if (isset($links[$page_on_front])) {
            foreach ($links[$page_on_front] as $lang => $translated_front_id) {
                if ($translated_front_id == $current_page_id) {
                    // Current page is a translated front page
                    // Return the target language homepage
                    return home_url('/' . $target_lang . '/');
                }
            }
        }

        // Get current page language
        $current_lang = self::get_page_language($current_page_id);

        // Get current page slug
        $current_page = get_post($current_page_id);
        if (!$current_page) {
            return home_url('/' . $target_lang . '/');
        }

        // If current page is in default language
        if ($current_lang === $default_language) {
            $current_slug = $current_page->post_name;

            if ($target_lang === $default_language) {
                // Same language - return URL with language prefix
                return home_url('/' . $target_lang . '/' . $current_slug . '/');
            } else {
                // Different language - find translation
                $translated_id = self::get_translation($current_page_id, $target_lang);
                if ($translated_id) {
                    return home_url('/' . $target_lang . '/' . $current_slug . '/');
                }
                // No translation, return language homepage
                return home_url('/' . $target_lang . '/');
            }
        }

        // Current page is a translation
        // Find the default language page by reverse lookup
        $default_page_id = null;

        foreach ($links as $def_id => $translations) {
            if (isset($translations[$current_lang]) && $translations[$current_lang] == $current_page_id) {
                $default_page_id = $def_id;
                break;
            }
        }

        if (!$default_page_id) {
            // Can't find default page, return language homepage
            return home_url('/' . $target_lang . '/');
        }

        $default_page = get_post($default_page_id);
        if (!$default_page) {
            return home_url('/' . $target_lang . '/');
        }

        $default_slug = $default_page->post_name;

        // If target is default language
        if ($target_lang === $default_language) {
            return home_url('/' . $target_lang . '/' . $default_slug . '/');
        }

        // If target is another translation
        if (isset($links[$default_page_id][$target_lang])) {
            return home_url('/' . $target_lang . '/' . $default_slug . '/');
        }

        // No translation exists, return language homepage
        return home_url('/' . $target_lang . '/');
    }

    /**
     * Shortcode: [lang_flag_en]
     * Display English flag/link
     */
    public function shortcode_lang_flag_en($atts) {
        $atts = shortcode_atts(array(
            'type' => 'emoji', // emoji or text
            'class' => 'lang-flag-en'
        ), $atts);

        $url = $this->get_language_url('en');
        $current_lang = $this->get_current_language();
        $is_current = ($current_lang === 'en');

        if ($atts['type'] === 'emoji') {
            $label = '🇬🇧';
        } else {
            $label = 'EN';
        }

        if ($is_current) {
            return '<span class="' . esc_attr($atts['class']) . ' current-lang">' . $label . '</span>';
        }

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($atts['class']) . '">' . $label . '</a>';
    }

    /**
     * Shortcode: [lang_flag_it]
     * Display Italian flag/link
     */
    public function shortcode_lang_flag_it($atts) {
        $atts = shortcode_atts(array(
            'type' => 'emoji', // emoji or text
            'class' => 'lang-flag-it'
        ), $atts);

        $url = $this->get_language_url('it');
        $current_lang = $this->get_current_language();
        $is_current = ($current_lang === 'it');

        if ($atts['type'] === 'emoji') {
            $label = '🇮🇹';
        } else {
            $label = 'IT';
        }

        if ($is_current) {
            return '<span class="' . esc_attr($atts['class']) . ' current-lang">' . $label . '</span>';
        }

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($atts['class']) . '">' . $label . '</a>';
    }

    /**
     * Shortcode: [lang_flag_es]
     * Display Spanish flag/link
     */
    public function shortcode_lang_flag_es($atts) {
        $atts = shortcode_atts(array(
            'type' => 'emoji', // emoji or text
            'class' => 'lang-flag-es'
        ), $atts);

        $url = $this->get_language_url('es');
        $current_lang = $this->get_current_language();
        $is_current = ($current_lang === 'es');

        if ($atts['type'] === 'emoji') {
            $label = '🇪🇸';
        } else {
            $label = 'ES';
        }

        if ($is_current) {
            return '<span class="' . esc_attr($atts['class']) . ' current-lang">' . $label . '</span>';
        }

        return '<a href="' . esc_url($url) . '" class="' . esc_attr($atts['class']) . '">' . $label . '</a>';
    }
}

// Initialize the plugin
function mira_language_switcher_init() {
    new Mira_Language_Switcher();
}
add_action('plugins_loaded', 'mira_language_switcher_init');
