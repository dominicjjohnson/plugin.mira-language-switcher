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

/**
 * Main Mira Language Switcher Class
 */
class Mira_Language_Switcher {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
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
}

// Initialize the plugin
function mira_language_switcher_init() {
    new Mira_Language_Switcher();
}
add_action('plugins_loaded', 'mira_language_switcher_init');
