<?php
/**
 * Plugin Name: Mira Language Switcher
 * Plugin URI: https://example.com/mira-language-switcher
 * Description: A simple language switcher plugin with setup and settings pages
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mira-language-switcher
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MIRA_LS_VERSION', '1.0.0');
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
     * @return string Language code (en, it, es)
     */
    public function detect_language() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $detected_lang = null;

        // Get WordPress home path (handles subdirectory installations)
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path) {
            $home_path = rtrim($home_path, '/');
        }

        // Check for language in URL pattern after the WordPress path
        // Example: /plug/en/about-us/ where /plug is the WordPress subdirectory
        $pattern = '#^' . preg_quote($home_path, '#') . '/(' . implode('|', MIRA_LS_SUPPORTED_LANGUAGES) . ')(/|$)#';

        if (preg_match($pattern, $request_uri, $matches)) {
            $detected_lang = $matches[1];

            // Set cookie when language is detected from URL (30 days)
            if (!headers_sent()) {
                setcookie('mira_language', $detected_lang, time() + (30 * 24 * 60 * 60), '/');
            }

            return $detected_lang;
        }

        // Check if language cookie exists
        if (isset($_COOKIE['mira_language']) && in_array($_COOKIE['mira_language'], MIRA_LS_SUPPORTED_LANGUAGES)) {
            return $_COOKIE['mira_language'];
        }

        // Return default language if no language detected
        return MIRA_LS_DEFAULT_LANGUAGE;
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

        // Check if the requested URL contains a language prefix
        $home_path = parse_url(home_url(), PHP_URL_PATH);
        if ($home_path) {
            $home_path = rtrim($home_path, '/');
        }

        $pattern = '#' . preg_quote($home_path, '#') . '/(' . implode('|', MIRA_LS_SUPPORTED_LANGUAGES) . ')(/|$)#';

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

        // If no language in URL or it's the default language, do nothing
        if (empty($lang) || $lang === MIRA_LS_DEFAULT_LANGUAGE) {
            return;
        }

        // Get the page name from query
        $pagename = get_query_var('pagename');
        if (empty($pagename)) {
            return;
        }

        // Find the English page by slug
        $english_page = get_page_by_path($pagename);
        if (!$english_page) {
            return;
        }

        // Get the translated page ID for this language
        $translated_id = self::get_translation($english_page->ID, $lang);

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

        // Get all pages
        $all_pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        // Filter pages by language
        $english_pages = array();
        $italian_pages = array();
        $spanish_pages = array();

        foreach ($all_pages as $page) {
            $page_lang = self::get_page_language($page->ID);

            if ($page_lang === 'en') {
                $english_pages[] = $page;
            } elseif ($page_lang === 'it') {
                $italian_pages[] = $page;
            } elseif ($page_lang === 'es') {
                $spanish_pages[] = $page;
            }
        }

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
            <p><?php echo esc_html__('Link English pages to their Italian and Spanish translations.', 'mira-language-switcher'); ?></p>

            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Page counts:', 'mira-language-switcher'); ?></strong>
                    <?php printf(__('English: %d', 'mira-language-switcher'), count($english_pages)); ?> |
                    <?php printf(__('Italian: %d', 'mira-language-switcher'), count($italian_pages)); ?> |
                    <?php printf(__('Spanish: %d', 'mira-language-switcher'), count($spanish_pages)); ?>
                </p>
            </div>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('save_translation_links_action', 'translation_links_nonce'); ?>
                <input type="hidden" name="action" value="save_translation_links">

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 35%;"><?php _e('English Page', 'mira-language-switcher'); ?></th>
                            <th style="width: 30%;"><?php _e('Italian Translation', 'mira-language-switcher'); ?></th>
                            <th style="width: 30%;"><?php _e('Spanish Translation', 'mira-language-switcher'); ?></th>
                            <th style="width: 5%;"><?php _e('ID', 'mira-language-switcher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($english_pages)): ?>
                            <tr>
                                <td colspan="4">
                                    <?php _e('No English pages found. Please set page languages first.', 'mira-language-switcher'); ?>
                                    <br>
                                    <small><?php _e('Edit a page and set its language in the Language metabox.', 'mira-language-switcher'); ?></small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($english_pages as $page): ?>
                                <?php
                                $page_id = $page->ID;
                                $italian_page = isset($translation_links[$page_id]['it']) ? $translation_links[$page_id]['it'] : '';
                                $spanish_page = isset($translation_links[$page_id]['es']) ? $translation_links[$page_id]['es'] : '';
                                ?>
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
                                    <td>
                                        <select name="translations[<?php echo $page_id; ?>][it]" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Italian Page --', 'mira-language-switcher'); ?></option>
                                            <?php foreach ($italian_pages as $option_page): ?>
                                                <option value="<?php echo $option_page->ID; ?>"
                                                    <?php selected($italian_page, $option_page->ID); ?>>
                                                    <?php echo esc_html($option_page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($italian_pages)): ?>
                                            <small style="color: #999;"><?php _e('No Italian pages available', 'mira-language-switcher'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select name="translations[<?php echo $page_id; ?>][es]" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Spanish Page --', 'mira-language-switcher'); ?></option>
                                            <?php foreach ($spanish_pages as $option_page): ?>
                                                <option value="<?php echo $option_page->ID; ?>"
                                                    <?php selected($spanish_page, $option_page->ID); ?>>
                                                    <?php echo esc_html($option_page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($spanish_pages)): ?>
                                            <small style="color: #999;"><?php _e('No Spanish pages available', 'mira-language-switcher'); ?></small>
                                        <?php endif; ?>
                                    </td>
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
                            <th><?php _e('English Page', 'mira-language-switcher'); ?></th>
                            <th><?php _e('Italian Translation', 'mira-language-switcher'); ?></th>
                            <th><?php _e('Spanish Translation', 'mira-language-switcher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($translation_links as $en_id => $translations): ?>
                            <?php if (!empty($translations['it']) || !empty($translations['es'])): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $en_page = get_post($en_id);
                                        if ($en_page) {
                                            echo '<strong>' . esc_html($en_page->post_title) . '</strong>';
                                            echo '<br><small>ID: ' . $en_id . '</small>';
                                        } else {
                                            echo 'ID: ' . $en_id . ' <em>(page not found)</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($translations['it'])) {
                                            $it_page = get_post($translations['it']);
                                            if ($it_page) {
                                                echo esc_html($it_page->post_title);
                                                echo '<br><small>ID: ' . $translations['it'] . '</small>';
                                            } else {
                                                echo 'ID: ' . $translations['it'] . ' <em>(page not found)</em>';
                                            }
                                        } else {
                                            echo '<em>' . __('Not set', 'mira-language-switcher') . '</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($translations['es'])) {
                                            $es_page = get_post($translations['es']);
                                            if ($es_page) {
                                                echo esc_html($es_page->post_title);
                                                echo '<br><small>ID: ' . $translations['es'] . '</small>';
                                            } else {
                                                echo 'ID: ' . $translations['es'] . ' <em>(page not found)</em>';
                                            }
                                        } else {
                                            echo '<em>' . __('Not set', 'mira-language-switcher') . '</em>';
                                        }
                                        ?>
                                    </td>
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

        // Get the translations data
        $translations = isset($_POST['translations']) ? $_POST['translations'] : array();

        // Clean and validate the data
        $clean_translations = array();
        foreach ($translations as $page_id => $langs) {
            $page_id = absint($page_id);

            if ($page_id > 0) {
                $clean_translations[$page_id] = array();

                // Italian translation
                if (!empty($langs['it'])) {
                    $clean_translations[$page_id]['it'] = absint($langs['it']);
                }

                // Spanish translation
                if (!empty($langs['es'])) {
                    $clean_translations[$page_id]['es'] = absint($langs['es']);
                }

                // Remove entry if no translations set
                if (empty($clean_translations[$page_id]['it']) &&
                    empty($clean_translations[$page_id]['es'])) {
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
        add_meta_box(
            'mira_page_language',
            __('Language', 'mira-language-switcher'),
            array($this, 'render_language_metabox'),
            'page',
            'side',
            'high'
        );
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

        // If no language set, default to default language
        if (empty($current_language)) {
            $current_language = MIRA_LS_DEFAULT_LANGUAGE;
        }

        // Language options
        $languages = array(
            'en' => __('English', 'mira-language-switcher'),
            'it' => __('Italian', 'mira-language-switcher'),
            'es' => __('Spanish', 'mira-language-switcher')
        );

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
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }

        // Check if this is a page
        if (get_post_type($post_id) !== 'page') {
            return;
        }

        // Save the language value
        if (isset($_POST['mira_page_language'])) {
            $language = sanitize_text_field($_POST['mira_page_language']);

            // Validate language code
            if (in_array($language, MIRA_LS_SUPPORTED_LANGUAGES)) {
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
     * Get URL for current page in specified language
     *
     * @param string $target_lang Target language code (en, it, es)
     * @return string|false URL or false if not available
     */
    private function get_language_url($target_lang) {
        // Get current page ID
        $current_page_id = get_the_ID();
        if (!$current_page_id) {
            return home_url('/' . ($target_lang !== 'en' ? $target_lang . '/' : ''));
        }

        // Get current page language
        $current_lang = self::get_page_language($current_page_id);

        // If target is current language, return current URL
        if ($target_lang === $current_lang) {
            return get_permalink($current_page_id);
        }

        // If current page is English and we want a translation
        if ($current_lang === 'en' && $target_lang !== 'en') {
            $translated_id = self::get_translation($current_page_id, $target_lang);
            if ($translated_id) {
                $current_slug = get_post($current_page_id)->post_name;
                return home_url('/' . $target_lang . '/' . $current_slug . '/');
            }
            // No translation, return language homepage
            return home_url('/' . $target_lang . '/');
        }

        // If current page is translated and we want English
        if ($current_lang !== 'en' && $target_lang === 'en') {
            // Find the English page by reverse lookup
            $links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());
            foreach ($links as $en_id => $translations) {
                if (isset($translations[$current_lang]) && $translations[$current_lang] == $current_page_id) {
                    return get_permalink($en_id);
                }
            }
            // No English version found, return homepage
            return home_url('/');
        }

        // If current page is translated and we want another translation
        if ($current_lang !== 'en' && $target_lang !== 'en') {
            // Find the English page first
            $links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());
            foreach ($links as $en_id => $translations) {
                if (isset($translations[$current_lang]) && $translations[$current_lang] == $current_page_id) {
                    // Found English page, now get other translation
                    if (isset($translations[$target_lang])) {
                        $en_slug = get_post($en_id)->post_name;
                        return home_url('/' . $target_lang . '/' . $en_slug . '/');
                    }
                    break;
                }
            }
            // No translation, return language homepage
            return home_url('/' . $target_lang . '/');
        }

        return false;
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
