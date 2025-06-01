# EZ Translate - WordPress Multilingual Plugin

A comprehensive multilingual system for WordPress that simplifies managing content in multiple languages with advanced SEO optimization.

## Features

- **Language Management**: Define and manage multiple languages for your WordPress site
- **SEO Optimization**: Advanced SEO features including hreflang tags and language-specific meta data
- **Gutenberg Integration**: Seamless integration with the WordPress block editor
- **Translation Groups**: Organize related content across languages
- **Landing Pages**: Designate specific pages as language landing pages with custom SEO
- **Developer Friendly**: Clean code structure with comprehensive logging and debugging

## Current Status

**Phase 1: Plugin Foundation** - ✅ **COMPLETED**
- ✅ Step 1.1: Basic plugin structure with activation/deactivation
- ✅ Step 1.2: Administrative menu and main page
- ✅ Step 1.3: Complete database system for language management

**Current Features:**
- ✅ Complete CRUD operations for languages
- ✅ 70+ common languages selector with ISO codes
- ✅ Auto-population of language fields (code, name, native name, flag)
- ✅ Robust validation and sanitization
- ✅ Duplicate prevention system
- ✅ Cache optimization with WordPress transients
- ✅ Complete admin interface with edit modal
- ✅ Comprehensive test suite (9 automated tests)
- ✅ RTL language auto-detection
- ✅ URL-friendly slug generation

The plugin now provides a complete language management system ready for multilingual content organization. Next phase will implement page metadata and Gutenberg integration.

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/ez-translate` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to the 'EZ Translate' menu in your WordPress admin to configure languages

## Development

This plugin follows WordPress coding standards and uses a modular architecture:

```
ez-translate/
├── admin/              # Administrative pages
├── includes/           # Core PHP classes
├── assets/            # Compiled CSS/JS files
│   ├── css/
│   └── js/
├── src/               # Source files for build process
│   ├── gutenberg/     # React components for Gutenberg
│   └── admin/         # Admin interface sources
├── languages/         # Translation files
├── memory_bank/       # Development documentation
├── ez-translate.php   # Main plugin file
├── uninstall.php      # Cleanup script
└── README.md          # This file
```

## Logging

The plugin includes comprehensive logging for debugging:

- **Development Mode**: Detailed logs when `WP_DEBUG` is enabled
- **Production Mode**: Only critical errors and important operations
- **Log Format**: `[EZ-Translate] [TIMESTAMP] LEVEL: Message`

## Architecture

The plugin uses:

- **Namespace**: `EZTranslate\` for all PHP classes
- **Data Storage**: WordPress native `wp_options` and `wp_postmeta` tables
- **REST API**: Custom endpoints under `/wp-json/ez-translate/v1/`
- **Autoloader**: PSR-4 compatible class autoloading

## Contributing

1. Follow WordPress coding standards
2. Include comprehensive logging for all operations
3. Write tests for new functionality
4. Update documentation as needed

## License

GPL v2 or later

## Support

For support and documentation, please refer to the plugin documentation or contact the development team.

## Changelog

### 1.0.0
- Initial release
- Complete plugin foundation with activation/deactivation hooks
- Administrative menu and interface
- Complete language management system with CRUD operations
- 70+ common languages selector with ISO 639-1 codes
- Auto-population of language fields (code, name, native name, flag)
- Robust validation and sanitization system
- Duplicate prevention and data integrity
- Cache optimization with WordPress transients
- Edit modal with JavaScript for enhanced UX
- Comprehensive test suite with 9 automated tests
- RTL language auto-detection
- URL-friendly slug generation
- Comprehensive logging and debugging framework
