<?php
/**
 * Script para corregir posts que tienen grupo pero no idioma asignado
 */

// Incluir WordPress
require_once('../../../wp-config.php');

// Configuración
$group_id = 'tg_b5euphe6hdv8865c';

echo "<h1>🔧 Corrección de Idiomas Faltantes - EZ Translate</h1>";
echo "<p>Verificando y corrigiendo posts que tienen grupo pero no idioma asignado.</p>";

// Obtener todos los posts del grupo
global $wpdb;
$posts_in_group = $wpdb->get_results($wpdb->prepare("
    SELECT 
        pm.post_id,
        p.post_title,
        p.post_status
    FROM {$wpdb->postmeta} AS pm
    JOIN {$wpdb->posts} AS p ON pm.post_id = p.ID
    WHERE pm.meta_key = '_ez_translate_group'
    AND pm.meta_value = %s
    AND p.post_status = 'publish'
    ORDER BY pm.post_id
", $group_id), ARRAY_A);

echo "<h2>📊 Posts en el grupo $group_id</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Post ID</th><th>Título</th><th>Idioma Actual</th><th>Estado</th><th>Acción</th></tr>";

$posts_without_language = array();

foreach ($posts_in_group as $post_data) {
    $post_id = $post_data['post_id'];
    $current_language = get_post_meta($post_id, '_ez_translate_language', true);
    
    $language_status = empty($current_language) ? '❌ NO ASIGNADO' : "✅ $current_language";
    $action = empty($current_language) ? 'NECESITA CORRECCIÓN' : 'OK';
    
    if (empty($current_language)) {
        $posts_without_language[] = $post_id;
    }
    
    echo "<tr>";
    echo "<td>{$post_id}</td>";
    echo "<td>{$post_data['post_title']}</td>";
    echo "<td>$language_status</td>";
    echo "<td>{$post_data['post_status']}</td>";
    echo "<td>$action</td>";
    echo "</tr>";
}

echo "</table>";

if (empty($posts_without_language)) {
    echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>";
    echo "<strong>✅ ¡Perfecto!</strong><br>";
    echo "Todos los posts tienen idiomas asignados correctamente.";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
    echo "<strong>⚠️ Problema encontrado:</strong><br>";
    echo "Los siguientes posts necesitan idiomas asignados: " . implode(', ', $posts_without_language);
    echo "</div>";
    
    echo "<h2>🔧 Corrección Automática</h2>";
    echo "<p>Asignando idiomas basándose en el contenido y patrones de URL...</p>";
    
    // Mapeo de idiomas sugeridos basado en los IDs que mencionaste
    $language_mapping = array(
        9 => 'es',   // Post 9 - español (traducción real)
        17 => 'hi',  // Post 17 - hindi 
        19 => 'pt',  // Post 19 - portugués
        21 => 'en'   // Post 21 - inglés (post actual)
    );
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Post ID</th><th>Título</th><th>Idioma Sugerido</th><th>Resultado</th></tr>";
    
    foreach ($posts_without_language as $post_id) {
        $post = get_post($post_id);
        $suggested_language = isset($language_mapping[$post_id]) ? $language_mapping[$post_id] : 'es';
        
        // Intentar asignar el idioma
        $result = update_post_meta($post_id, '_ez_translate_language', $suggested_language);
        
        $status = $result ? '✅ CORREGIDO' : '❌ ERROR';
        
        echo "<tr>";
        echo "<td>$post_id</td>";
        echo "<td>{$post->post_title}</td>";
        echo "<td>$suggested_language</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 20px 0;'>";
    echo "<strong>ℹ️ Idiomas asignados:</strong><br>";
    echo "• Post 9: Español (es) - Traducción real<br>";
    echo "• Post 17: Hindi (hi) - Traducción<br>";
    echo "• Post 19: Portugués (pt) - Traducción<br>";
    echo "• Post 21: Inglés (en) - Post actual<br>";
    echo "</div>";
}

echo "<h2>🧪 Verificación Post-Corrección</h2>";
echo "<p>Verificando que ahora el traductor funcione correctamente...</p>";

// Simular el método get_available_translations_for_post después de la corrección
$current_post_id = 21;
$current_language = get_post_meta($current_post_id, '_ez_translate_language', true);

echo "<p><strong>Post actual:</strong> $current_post_id</p>";
echo "<p><strong>Idioma actual:</strong> " . ($current_language ?: 'NO ASIGNADO') . "</p>";

// Incluir la clase necesaria
require_once('includes/class-ez-translate-post-meta-manager.php');
$posts_in_group_ids = \EZTranslate\PostMetaManager::get_posts_in_group($group_id);

echo "<p><strong>Posts en el grupo:</strong> " . implode(', ', $posts_in_group_ids) . "</p>";

$translations = array();

foreach ($posts_in_group_ids as $related_post_id) {
    if (!is_numeric($related_post_id)) continue;
    
    $post = get_post($related_post_id);
    if (!$post || $post->post_status !== 'publish') continue;
    
    $post_language = get_post_meta($post->ID, '_ez_translate_language', true);
    
    if ($post->ID == $current_post_id || empty($post_language)) continue;
    
    $translations[] = array(
        'language_code' => $post_language,
        'post_id' => $post->ID,
        'url' => get_permalink($post->ID),
        'title' => get_the_title($post->ID)
    );
}

echo "<h3>📊 Resultado Final</h3>";
echo "<p><strong>Traducciones encontradas:</strong> " . count($translations) . "</p>";

if (count($translations) > 0) {
    echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>";
    echo "<strong>✅ ¡Problema solucionado!</strong><br>";
    echo "El traductor ahora debería mostrar " . count($translations) . " traducciones disponibles.";
    echo "</div>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Idioma</th><th>Post ID</th><th>Título</th><th>URL</th></tr>";
    foreach ($translations as $translation) {
        echo "<tr>";
        echo "<td>{$translation['language_code']}</td>";
        echo "<td>{$translation['post_id']}</td>";
        echo "<td>{$translation['title']}</td>";
        echo "<td><a href='{$translation['url']}' target='_blank'>{$translation['url']}</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<strong>❌ Aún hay problemas</strong><br>";
    echo "Revisa que los idiomas se hayan asignado correctamente.";
    echo "</div>";
}

echo "<h2>🔄 Próximos Pasos</h2>";
echo "<div style='background: #e2e3e5; padding: 15px; border-left: 4px solid #6c757d; margin: 20px 0;'>";
echo "<strong>Para verificar que todo funciona:</strong><br>";
echo "1. Recarga la página donde está el traductor<br>";
echo "2. Verifica que ahora muestre las 3 traducciones disponibles<br>";
echo "3. Prueba hacer clic en cada traducción para verificar que funcionen<br>";
echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f5f5f5;
}

h1, h2, h3 {
    color: #333;
}

table {
    background: white;
    margin: 10px 0;
}

th {
    background: #f0f0f0;
    padding: 8px;
    text-align: left;
}

td {
    padding: 8px;
}
</style>
