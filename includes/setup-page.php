<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="mira-ls-setup-container">
        <div class="card">
            <h2><?php _e('Welcome to Mira Language Switcher', 'mira-language-switcher'); ?></h2>
            <p><?php _e('Thank you for installing Mira Language Switcher! This plugin helps you manage multiple languages on your WordPress site.', 'mira-language-switcher'); ?></p>
        </div>

        <div class="card">
            <h2><?php _e('Getting Started', 'mira-language-switcher'); ?></h2>
            <ol>
                <li><?php _e('Go to the Settings page to configure your languages', 'mira-language-switcher'); ?></li>
                <li><?php _e('Select your default language', 'mira-language-switcher'); ?></li>
                <li><?php _e('Enable the languages you want to support', 'mira-language-switcher'); ?></li>
                <li><?php _e('Customize display options', 'mira-language-switcher'); ?></li>
            </ol>

            <p>
                <a href="<?php echo admin_url('admin.php?page=mira-language-switcher-settings'); ?>" class="button button-primary">
                    <?php _e('Go to Settings', 'mira-language-switcher'); ?>
                </a>
            </p>
        </div>

        <div class="card">
            <h2><?php _e('Features', 'mira-language-switcher'); ?></h2>
            <ul>
                <li><?php _e('Multiple language support', 'mira-language-switcher'); ?></li>
                <li><?php _e('Easy language switching', 'mira-language-switcher'); ?></li>
                <li><?php _e('Customizable display options', 'mira-language-switcher'); ?></li>
                <li><?php _e('User-friendly interface', 'mira-language-switcher'); ?></li>
            </ul>
        </div>
    </div>
</div>

<style>
    .mira-ls-setup-container .card {
        max-width: 800px;
        margin-bottom: 20px;
    }
    .mira-ls-setup-container h2 {
        margin-top: 0;
    }
    .mira-ls-setup-container ol,
    .mira-ls-setup-container ul {
        line-height: 1.8;
    }
</style>
