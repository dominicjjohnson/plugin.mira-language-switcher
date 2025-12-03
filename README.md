# Mira Language Switcher

A WordPress plugin for managing multi-language content with URL-based language switching.

## Description

Mira Language Switcher provides a simple and efficient way to handle multiple languages on your WordPress site. The plugin uses URL prefixes to detect and switch between languages, making it SEO-friendly and easy to use.

### Features

- URL-based language detection (/en/, /it/, /es/)
- Automatic rewrite rules for clean URLs
- Admin bar language indicator
- Multiple language support (English, Italian, Spanish)
- Easy-to-use settings interface
- Default language fallback

## Installation

1. Upload the `mira-language-switcher` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Mira Language' in the admin menu
4. Configure your language settings

## Usage

### URL Structure

The plugin detects language from the URL prefix:

- `http://yoursite.com/en/page-name/` - English
- `http://yoursite.com/it/page-name/` - Italian
- `http://yoursite.com/es/page-name/` - Spanish
- `http://yoursite.com/page-name/` - Default language (English)

### Admin Interface

**Setup Page**
- Welcome information
- Quick start guide
- Feature overview

**Settings Page**
- Default language selection
- Enable/disable specific languages
- Display options (flags, language names)
- Current configuration summary

### Admin Bar Indicator

When browsing your site, the current language is displayed in the WordPress admin bar with a globe icon (🌐) showing:
- Language name
- Language code
- Click to access settings

## Development

### Stage 1: Core Structure & URL Handling ✅

Current implementation includes:
- Plugin initialization and constants
- Language detection from URL
- Rewrite rules for language prefixes
- Default language (English)
- Admin bar display for testing

### Supported Languages

- English (en)
- Italian (it)
- Spanish (es)

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Frequently Asked Questions

### How do I add a new language?

Currently, the plugin supports English, Italian, and Spanish. Additional languages can be added in future updates.

### Will this work with existing pages?

Yes, existing pages will continue to work normally. The plugin adds language prefix support without breaking existing URLs.

### How do I flush rewrite rules?

Deactivate and reactivate the plugin, or go to Settings > Permalinks and click Save Changes.

## Changelog

### Version 1.0.0
- Initial release
- Basic URL detection and rewriting
- Admin interface (Setup and Settings pages)
- Admin bar language indicator
- Support for English, Italian, and Spanish

## License

This plugin is licensed under the GPL v2 or later.

## Author

Your Name

## Support

For support, please visit [GitHub Issues](https://github.com/dominicjjohnson/plugin.mira-language-switcher/issues)
