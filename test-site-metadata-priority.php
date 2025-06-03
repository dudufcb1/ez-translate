<?php
/**
 * Test Site Metadata Priority in Landing Pages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Include required files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-logger.php';

use EZTranslate\LanguageManager;

echo "<h1>Test Site Metadata Priority in Landing Pages</h1>";
echo "<p>Verificando que las landing pages usen correctamente los metadatos del sitio por idioma.</p>";

// Clean up any existing test data
$test_codes = ['test1', 'test2', 'test3'];
foreach ($test_codes as $code) {
    LanguageManager::delete_language($code);
}
echo "<p>✅ Limpieza de datos de prueba completada</p>";

// Test cases
$test_cases = [
    [
        'name' => 'Caso 1: Metadatos completos (site_title + site_description)',
        'data' => [
            'code' => 'test1',
            'name' => 'Test Language 1',
            'slug' => 'test1',
            'enabled' => true,
            'site_title' => 'Especialista en WordPress',
            'site_description' => 'Tu experto en desarrollo WordPress personalizado'
        ],
        'expected_title' => 'Especialista en WordPress',
        'expected_description' => 'Tu experto en desarrollo WordPress personalizado'
    ],
    [
        'name' => 'Caso 2: Solo site_name (sin site_title)',
        'data' => [
            'code' => 'test2',
            'name' => 'Test Language 2',
            'slug' => 'test2',
            'enabled' => true,
            'site_name' => 'WordPress Specialist',
            'site_description' => 'Professional WordPress development services'
        ],
        'expected_title' => 'WordPress Specialist',
        'expected_description' => 'Professional WordPress development services'
    ],
    [
        'name' => 'Caso 3: Sin metadatos específicos (fallback)',
        'data' => [
            'code' => 'test3',
            'name' => 'Test Language 3',
            'slug' => 'test3',
            'enabled' => true
        ],
        'expected_title_pattern' => '.*- Test Language 3$', // Regex pattern
        'expected_description_pattern' => '^Welcome to .* in Test Language 3\.'
    ]
];

echo "<h2>Ejecutando Tests de Prioridad de Metadatos</h2>";

$results = [];

foreach ($test_cases as $index => $test_case) {
    echo "<h3>{$test_case['name']}</h3>";
    
    $result = LanguageManager::add_language($test_case['data']);
    
    if (is_wp_error($result)) {
        echo "<p style='color: red;'>❌ Error creando idioma: " . $result->get_error_message() . "</p>";
        $results[$index] = 'ERROR';
        continue;
    }
    
    if (!isset($result['landing_page_id'])) {
        echo "<p style='color: red;'>❌ No se creó landing page</p>";
        $results[$index] = 'NO_LANDING';
        continue;
    }
    
    $landing_page_id = $result['landing_page_id'];
    $post = get_post($landing_page_id);
    
    if (!$post) {
        echo "<p style='color: red;'>❌ Landing page no encontrada</p>";
        $results[$index] = 'NOT_FOUND';
        continue;
    }
    
    // Get SEO metadata
    $seo_title = get_post_meta($landing_page_id, '_ez_translate_seo_title', true);
    $seo_description = get_post_meta($landing_page_id, '_ez_translate_seo_description', true);
    
    echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa;'>";
    echo "<h4>Datos de la Landing Page:</h4>";
    echo "<p><strong>Post Title:</strong> " . esc_html($post->post_title) . "</p>";
    echo "<p><strong>SEO Title:</strong> " . esc_html($seo_title) . "</p>";
    echo "<p><strong>SEO Description:</strong> " . esc_html($seo_description) . "</p>";
    echo "<p><strong>Slug:</strong> " . esc_html($post->post_name) . "</p>";
    echo "</div>";
    
    // Check title
    $title_correct = false;
    if (isset($test_case['expected_title'])) {
        $title_correct = ($seo_title === $test_case['expected_title']);
        echo "<p><strong>Título esperado:</strong> " . esc_html($test_case['expected_title']) . "</p>";
        echo "<p><strong>Título actual:</strong> " . esc_html($seo_title) . "</p>";
    } elseif (isset($test_case['expected_title_pattern'])) {
        $title_correct = preg_match('/' . $test_case['expected_title_pattern'] . '/', $seo_title);
        echo "<p><strong>Patrón de título esperado:</strong> " . esc_html($test_case['expected_title_pattern']) . "</p>";
        echo "<p><strong>Título actual:</strong> " . esc_html($seo_title) . "</p>";
    }
    
    // Check description
    $description_correct = false;
    if (isset($test_case['expected_description'])) {
        $description_correct = ($seo_description === $test_case['expected_description']);
        echo "<p><strong>Descripción esperada:</strong> " . esc_html($test_case['expected_description']) . "</p>";
        echo "<p><strong>Descripción actual:</strong> " . esc_html($seo_description) . "</p>";
    } elseif (isset($test_case['expected_description_pattern'])) {
        $description_correct = preg_match('/' . $test_case['expected_description_pattern'] . '/', $seo_description);
        echo "<p><strong>Patrón de descripción esperado:</strong> " . esc_html($test_case['expected_description_pattern']) . "</p>";
        echo "<p><strong>Descripción actual:</strong> " . esc_html($seo_description) . "</p>";
    }
    
    if ($title_correct && $description_correct) {
        echo "<p style='color: green; font-weight: bold;'>✅ Test PASADO - Metadatos correctos</p>";
        $results[$index] = 'SUCCESS';
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ Test FALLIDO - Metadatos incorrectos</p>";
        if (!$title_correct) echo "<p style='color: red;'>- Título incorrecto</p>";
        if (!$description_correct) echo "<p style='color: red;'>- Descripción incorrecta</p>";
        $results[$index] = 'FAILED';
    }
    
    echo "<hr>";
}

// Summary
echo "<h2>Resumen de Resultados</h2>";

$success_count = array_count_values($results)['SUCCESS'] ?? 0;
$total_count = count($test_cases);

echo "<div style='background: #e7f3ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;'>";
echo "<h3>Estadísticas</h3>";
echo "<p><strong>Tests ejecutados:</strong> {$total_count}</p>";
echo "<p><strong>Tests exitosos:</strong> {$success_count}</p>";
echo "<p><strong>Tests fallidos:</strong> " . ($total_count - $success_count) . "</p>";

if ($success_count === $total_count) {
    echo "<p style='color: green; font-weight: bold;'>🎉 ¡Todos los tests pasaron! La prioridad de metadatos funciona correctamente.</p>";
    echo "<p>Las landing pages ahora usan:</p>";
    echo "<ul>";
    echo "<li>✅ <code>site_title</code> cuando está disponible</li>";
    echo "<li>✅ <code>site_name</code> como fallback</li>";
    echo "<li>✅ <code>site_description</code> cuando está disponible</li>";
    echo "<li>✅ Descripción generada como fallback</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠️ Algunos tests fallaron. Revisar la implementación.</p>";
}
echo "</div>";

// Clean up
echo "<h2>Limpieza</h2>";
foreach ($test_codes as $code) {
    LanguageManager::delete_language($code);
}
echo "<p>✅ Datos de prueba eliminados</p>";

echo "<p><a href='wp-admin/admin.php?page=ez-translate'>← Volver a EZ Translate Settings</a></p>";
?>
