<?php
/**
 * Test ISO Code Slugs for Landing Pages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once '../../../wp-load.php';
}

// Include required files
require_once 'includes/class-ez-translate-language-manager.php';
require_once 'includes/class-ez-translate-logger.php';

use EZTranslate\LanguageManager;

echo "<h1>Test ISO Code Slugs for Landing Pages</h1>";
echo "<p>Verificando que las landing pages usen códigos ISO (fr, en, es) en lugar de nombres completos.</p>";

// Clean up any existing test data
$test_languages = ['fr', 'en', 'es', 'de', 'pt'];
foreach ($test_languages as $code) {
    LanguageManager::delete_language($code);
}
echo "<p>✅ Limpieza de datos de prueba completada</p>";

// Test different languages
$languages_to_test = [
    [
        'code' => 'fr',
        'name' => 'Français',
        'slug' => 'french',
        'expected_landing_slug' => 'fr'
    ],
    [
        'code' => 'en',
        'name' => 'English',
        'slug' => 'english',
        'expected_landing_slug' => 'en'
    ],
    [
        'code' => 'es',
        'name' => 'Español',
        'slug' => 'spanish',
        'expected_landing_slug' => 'es'
    ],
    [
        'code' => 'de',
        'name' => 'Deutsch',
        'slug' => 'german',
        'expected_landing_slug' => 'de'
    ],
    [
        'code' => 'pt',
        'name' => 'Português',
        'slug' => 'portuguese',
        'expected_landing_slug' => 'pt'
    ]
];

echo "<h2>Probando Creación de Landing Pages con Códigos ISO</h2>";

$results = [];

foreach ($languages_to_test as $lang_data) {
    echo "<h3>Probando: {$lang_data['name']} ({$lang_data['code']})</h3>";
    
    $language_data = [
        'code' => $lang_data['code'],
        'name' => $lang_data['name'],
        'slug' => $lang_data['slug'],
        'enabled' => true,
        'site_name' => 'Test Site'
    ];
    
    $result = LanguageManager::add_language($language_data);
    
    if (is_wp_error($result)) {
        echo "<p style='color: red;'>❌ Error creando idioma: " . $result->get_error_message() . "</p>";
        $results[$lang_data['code']] = 'ERROR';
        continue;
    }
    
    if (!isset($result['landing_page_id'])) {
        echo "<p style='color: red;'>❌ No se creó landing page</p>";
        $results[$lang_data['code']] = 'NO_LANDING';
        continue;
    }
    
    $landing_page_id = $result['landing_page_id'];
    $post = get_post($landing_page_id);
    
    if (!$post) {
        echo "<p style='color: red;'>❌ Landing page no encontrada en base de datos</p>";
        $results[$lang_data['code']] = 'NOT_FOUND';
        continue;
    }
    
    $actual_slug = $post->post_name;
    $expected_slug = $lang_data['expected_landing_slug'];
    
    echo "<p><strong>Slug esperado:</strong> <code>{$expected_slug}</code></p>";
    echo "<p><strong>Slug actual:</strong> <code>{$actual_slug}</code></p>";
    
    if ($actual_slug === $expected_slug) {
        echo "<p style='color: green;'>✅ Slug correcto - usa código ISO</p>";
        $results[$lang_data['code']] = 'SUCCESS';
    } else {
        echo "<p style='color: red;'>❌ Slug incorrecto - no usa código ISO</p>";
        $results[$lang_data['code']] = 'WRONG_SLUG';
    }
    
    // Show additional details
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;'>";
    echo "<p><strong>Título:</strong> " . esc_html($post->post_title) . "</p>";
    echo "<p><strong>URL:</strong> " . get_permalink($landing_page_id) . "</p>";
    echo "<p><strong>Estado:</strong> " . esc_html($post->post_status) . "</p>";
    echo "</div>";
}

// Summary
echo "<h2>Resumen de Resultados</h2>";

$success_count = 0;
$total_count = count($languages_to_test);

echo "<table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
echo "<tr style='background: #f9f9f9;'>";
echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Idioma</th>";
echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Código</th>";
echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Resultado</th>";
echo "<th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Estado</th>";
echo "</tr>";

foreach ($languages_to_test as $lang_data) {
    $code = $lang_data['code'];
    $result = $results[$code] ?? 'NOT_TESTED';
    
    $status_color = 'red';
    $status_text = '❌ Error';
    
    if ($result === 'SUCCESS') {
        $status_color = 'green';
        $status_text = '✅ Éxito';
        $success_count++;
    }
    
    echo "<tr>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . esc_html($lang_data['name']) . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'><code>" . esc_html($code) . "</code></td>";
    echo "<td style='border: 1px solid #ddd; padding: 10px;'>" . esc_html($result) . "</td>";
    echo "<td style='border: 1px solid #ddd; padding: 10px; color: {$status_color};'>{$status_text}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<div style='background: #e7f3ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;'>";
echo "<h3>Estadísticas Finales</h3>";
echo "<p><strong>Idiomas probados:</strong> {$total_count}</p>";
echo "<p><strong>Éxitos:</strong> {$success_count}</p>";
echo "<p><strong>Fallos:</strong> " . ($total_count - $success_count) . "</p>";

if ($success_count === $total_count) {
    echo "<p style='color: green; font-weight: bold;'>🎉 ¡Todos los tests pasaron! Las landing pages ahora usan códigos ISO correctamente.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠️ Algunos tests fallaron. Revisar la implementación.</p>";
}
echo "</div>";

// Clean up
echo "<h2>Limpieza</h2>";
foreach ($test_languages as $code) {
    LanguageManager::delete_language($code);
}
echo "<p>✅ Datos de prueba eliminados</p>";

echo "<p><a href='wp-admin/admin.php?page=ez-translate'>← Volver a EZ Translate Settings</a></p>";
?>
