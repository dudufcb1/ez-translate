# Site Name Translation Feature

## Overview

The EZ Translate plugin now supports **site name translation** per language, allowing you to have different site names for different languages in the page title.

## Example Use Case

**Original Problem:**
- Site name: "Especialista en WordPress" (Spanish)
- English pages show: "Page Title - Especialista en WordPress"
- This is incorrect for English visitors

**Solution with EZ Translate:**
- Spanish language: site_name = "Especialista en WordPress"
- English language: site_name = "WordPress Specialist"
- Spanish pages show: "Título de Página - Especialista en WordPress"
- English pages show: "Page Title - WordPress Specialist"

## Configuration

### 1. Language Settings

When creating or editing a language, you now have these fields:

```
Site Name: WordPress Specialist
Site Title: WordPress Specialist - Professional Services
Site Description: Expert WordPress development and consulting services
```

**Field Differences:**
- **Site Name**: Short name used in page titles (e.g., "WordPress Specialist")
- **Site Title**: Full title used in landing pages and SEO metadata
- **Site Description**: Description used in meta tags and landing pages

### 2. How It Works

The plugin automatically detects the language of each page and applies the appropriate site name:

```html
<!-- Before (Spanish site with English page) -->
<title>About Us - Especialista en WordPress</title>

<!-- After (with site name translation) -->
<title>About Us - WordPress Specialist</title>
```

## Technical Implementation

### 1. Database Schema

Languages now store an additional `site_name` field:

```php
$language_data = array(
    'code' => 'en',
    'name' => 'English',
    'slug' => 'english',
    'site_name' => 'WordPress Specialist',        // NEW FIELD
    'site_title' => 'WordPress Specialist - Professional Services',
    'site_description' => 'Expert WordPress development...'
);
```

### 2. Title Filter Enhancement

The `filter_document_title` function now handles both page titles and site names:

```php
public function filter_document_title($title_parts) {
    // Get language-specific metadata
    $language_site_metadata = LanguageManager::get_language_site_metadata($current_language);
    
    // Apply custom SEO title if available
    if (!empty($seo_title)) {
        $title_parts['title'] = $seo_title;
    }
    
    // Apply custom site name if available
    if (!empty($language_site_metadata['site_name'])) {
        $title_parts['site'] = $language_site_metadata['site_name'];
    }
    
    return $title_parts;
}
```

### 3. Complete Title Structure

WordPress combines title parts like this:
- `$title_parts['title']` - The page/post title
- `$title_parts['site']` - The site name
- Final result: "Page Title - Site Name"

## Examples

### Example 1: Multilingual Business Site

**Spanish Configuration:**
```
Site Name: Especialista en WordPress
Site Title: Especialista en WordPress - Servicios Profesionales
Site Description: Desarrollo y consultoría experta en WordPress
```

**English Configuration:**
```
Site Name: WordPress Specialist
Site Title: WordPress Specialist - Professional Services  
Site Description: Expert WordPress development and consulting services
```

**Results:**
- Spanish page: "Servicios - Especialista en WordPress"
- English page: "Services - WordPress Specialist"

### Example 2: E-commerce Site

**Spanish Configuration:**
```
Site Name: Tienda Online
```

**English Configuration:**
```
Site Name: Online Store
```

**Results:**
- Spanish product: "Producto Ejemplo - Tienda Online"
- English product: "Example Product - Online Store"

## Testing

### Automated Tests

Run the SEO Title Tests to verify functionality:
1. Go to EZ Translate > Languages
2. Click "Run SEO Title Tests"
3. Look for "Site Name Translation" test

### Manual Testing

1. Create a language with custom site name
2. Create a page with that language assigned
3. View the page source
4. Check the `<title>` tag contains the translated site name

### Debug Test

Run the debug test file to see the functionality in action:
```bash
php debug-title-test.php
```

## Benefits

1. **Consistent Branding**: Site name matches the language of the content
2. **Better SEO**: Search engines see appropriate site names for each language
3. **User Experience**: Visitors see familiar terminology in their language
4. **Professional Appearance**: No language mixing in page titles

## Backward Compatibility

- Existing sites without site_name configured will continue to work normally
- The original WordPress site name is used as fallback
- No breaking changes to existing functionality
