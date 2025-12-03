<?php
/**
 * Test Menu Flags Integration
 *
 * Run this from command line: php test-menu-flags.php
 * Or access via browser: http://localhost/plug/wp-content/plugins/mira-language-switcher/test-menu-flags.php
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         MENU FLAGS INTEGRATION TEST                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Check plugin
if (!class_exists('Mira_Language_Switcher')) {
    echo "✗ Plugin not loaded\n";
    exit;
}

echo "✓ Plugin loaded\n\n";

// Check settings
echo "📋 Current Settings:\n";
echo str_repeat("─", 65) . "\n";

$add_to_menu = get_option('mira_ls_add_to_menu', 'no');
$menu_location = get_option('mira_ls_menu_location', 'all');
$menu_flag_type = get_option('mira_ls_menu_flag_type', 'emoji');

echo "Add to Menu: " . ($add_to_menu === 'yes' ? '✓ ENABLED' : '✗ DISABLED') . "\n";
echo "Menu Location: $menu_location\n";
echo "Flag Type: $menu_flag_type\n\n";

if ($add_to_menu !== 'yes') {
    echo "⚠️  MENU INTEGRATION IS DISABLED!\n\n";
    echo "To enable:\n";
    echo "1. Go to: Mira Language → Settings\n";
    echo "2. Scroll to 'Menu Integration' section\n";
    echo "3. Check 'Automatically add language flags to navigation menu'\n";
    echo "4. Click 'Save Settings'\n\n";
    echo "Direct link:\n";
    echo admin_url('admin.php?page=mira-language-switcher-settings') . "\n\n";
}

// Check menu locations
echo "🎯 Available Menu Locations:\n";
echo str_repeat("─", 65) . "\n";

$menu_locations = get_registered_nav_menus();

if (empty($menu_locations)) {
    echo "⚠️  No menu locations registered in theme\n\n";
} else {
    foreach ($menu_locations as $location => $description) {
        $status = ($menu_location === 'all' || $menu_location === $location) ? '✓' : ' ';
        echo "[$status] $location - $description\n";

        // Check if menu is assigned
        $nav_menu_locations = get_nav_menu_locations();
        if (isset($nav_menu_locations[$location])) {
            $menu = wp_get_nav_menu_object($nav_menu_locations[$location]);
            if ($menu) {
                echo "    Assigned: {$menu->name}\n";
            }
        }
    }
    echo "\n";
}

// Test shortcode output
echo "🧪 Shortcode Output Test:\n";
echo str_repeat("─", 65) . "\n";

if ($menu_flag_type === 'emoji') {
    $test_shortcode = '[lang_flag_en] [lang_flag_it] [lang_flag_es]';
} else {
    $test_shortcode = '[lang_flag_en type="text"] [lang_flag_it type="text"] [lang_flag_es type="text"]';
}

echo "Shortcode: $test_shortcode\n";
echo "Output: " . do_shortcode($test_shortcode) . "\n\n";

// Simulate menu integration
if ($add_to_menu === 'yes') {
    echo "✅ Menu Integration Active:\n";
    echo str_repeat("─", 65) . "\n";

    $flags = do_shortcode($test_shortcode);
    $menu_item = '<li class="menu-item menu-item-language-switcher">' . $flags . '</li>';

    echo "HTML that will be added to menu:\n";
    echo $menu_item . "\n\n";

    echo "When user visits menu:\n";
    if ($menu_location === 'all') {
        echo "• Flags will appear in ALL menus\n";
    } else {
        echo "• Flags will appear in: $menu_location menu only\n";
    }
    echo "• Position: End of menu\n";
    echo "• Style: " . ($menu_flag_type === 'emoji' ? 'Flag emojis 🇬🇧 🇮🇹 🇪🇸' : 'Text codes EN IT ES') . "\n\n";
}

// Translation links check
echo "🔗 Translation Links:\n";
echo str_repeat("─", 65) . "\n";

$translation_links = get_option(MIRA_LS_TRANSLATIONS_OPTION, array());

if (empty($translation_links)) {
    echo "⚠️  No translation links set up\n";
    echo "Flags will link to language homepages\n\n";
} else {
    echo "✓ " . count($translation_links) . " translation link(s) configured\n";
    echo "Flags will link to translated pages\n\n";

    // Show first example
    foreach ($translation_links as $en_id => $translations) {
        $en_page = get_post($en_id);
        if (!$en_page) continue;

        echo "Example: {$en_page->post_title}\n";
        echo "  • English URL: " . get_permalink($en_id) . "\n";

        if (!empty($translations['it'])) {
            echo "  • Italian URL: " . home_url('/it/' . $en_page->post_name . '/') . "\n";
        }

        if (!empty($translations['es'])) {
            echo "  • Spanish URL: " . home_url('/es/' . $en_page->post_name . '/') . "\n";
        }

        break; // Only show first
    }
    echo "\n";
}

// Testing instructions
echo "╔════════════════════════════════════════════════════════════════╗\n";
if ($add_to_menu === 'yes') {
    echo "║            ✅ MENU INTEGRATION ACTIVE!                        ║\n";
} else {
    echo "║            ⚠️  MENU INTEGRATION DISABLED                      ║\n";
}
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "📝 How to Test in Browser:\n";
echo str_repeat("─", 65) . "\n";

if ($add_to_menu !== 'yes') {
    echo "1. Enable menu integration:\n";
    echo "   " . admin_url('admin.php?page=mira-language-switcher-settings') . "\n\n";
    echo "2. Check the 'Add to Menu' checkbox\n";
    echo "3. Select menu location\n";
    echo "4. Save settings\n";
    echo "5. Then continue with steps below\n\n";
}

echo "1. Visit your site homepage:\n";
echo "   http://localhost/plug/\n\n";

echo "2. Look at your main navigation menu\n";
echo "   You should see flags at the END of the menu\n\n";

echo "3. Expected appearance:\n";
if ($menu_flag_type === 'emoji') {
    echo "   Home | About | Contact | Services | 🇬🇧 🇮🇹 🇪🇸\n\n";
} else {
    echo "   Home | About | Contact | Services | EN | IT | ES\n\n";
}

echo "4. Test clicking flags:\n";
echo "   • Current language flag should NOT be clickable (dimmed)\n";
echo "   • Other flags should be clickable links\n";
echo "   • Click Italian flag → goes to /it/current-page/\n";
echo "   • Click Spanish flag → goes to /es/current-page/\n\n";

echo "5. Check on different pages:\n";
echo "   • Visit: http://localhost/plug/it/about-us/\n";
echo "   • Italian flag should now be dimmed (current)\n";
echo "   • English and Spanish flags should be clickable\n\n";

// Browser inspection
echo "🔍 Browser DevTools Inspection:\n";
echo str_repeat("─", 65) . "\n";
echo "1. Open DevTools (F12)\n";
echo "2. Inspect the menu\n";
echo "3. Look for:\n";
echo "   <li class=\"menu-item menu-item-language-switcher\">\n";
echo "4. You should see the flag links inside\n\n";

// Troubleshooting
echo "🔧 Troubleshooting:\n";
echo str_repeat("─", 65) . "\n";

if ($add_to_menu !== 'yes') {
    echo "❌ Flags not showing → Enable in settings first!\n";
} else {
    echo "✓ Settings enabled\n";
}

if (empty($menu_locations)) {
    echo "⚠️  No menu locations → Theme may not support menus\n";
} else {
    echo "✓ Menu locations available\n";
}

echo "\nCommon issues:\n";
echo "• Clear browser cache\n";
echo "• Clear WordPress cache (if caching plugin active)\n";
echo "• Check menu is assigned to location\n";
echo "• Verify plugin is activated\n\n";

// Quick links
echo "🔗 Quick Links:\n";
echo str_repeat("─", 65) . "\n";
echo "Settings Page:\n";
echo "  " . admin_url('admin.php?page=mira-language-switcher-settings') . "\n\n";
echo "Translation Links:\n";
echo "  " . admin_url('admin.php?page=mira-language-switcher-translations') . "\n\n";
echo "Menus:\n";
echo "  " . admin_url('nav-menus.php') . "\n\n";

echo "✅ Test complete!\n";
