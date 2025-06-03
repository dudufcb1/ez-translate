<?php
/**
 * Test page to verify admin interface elements
 */

// Simulate WordPress environment
define('ABSPATH', 'e:/xampp/htdocs/plugins/');
define('WP_DEBUG', true);

// Mock WordPress functions
function _e($text, $domain = 'default') { echo $text; }
function __($text, $domain = 'default') { return $text; }
function esc_attr_e($text, $domain = 'default') { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }

echo "<!DOCTYPE html>
<html>
<head>
    <title>EZ Translate Admin Interface Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-table { width: 100%; border-collapse: collapse; }
        .form-table th { text-align: left; padding: 10px; background: #f1f1f1; width: 200px; }
        .form-table td { padding: 10px; }
        .regular-text { width: 300px; padding: 5px; }
        .large-text { width: 400px; padding: 5px; }
        .description { font-style: italic; color: #666; margin-top: 5px; }
        .card { border: 1px solid #ccc; padding: 20px; margin: 20px 0; background: #fff; }
        h2 { color: #333; }
        h3 { color: #555; }
    </style>
</head>
<body>";

echo "<h1>EZ Translate - Test de Interfaz de Administración</h1>";

echo "<div class='card'>";
echo "<h2>Agregar Nuevo Idioma</h2>";
echo "<p>Esta es la interfaz que deberías ver al agregar un nuevo idioma:</p>";

echo "<table class='form-table'>";

// Language Code
echo "<tr>";
echo "<th scope='row'><label for='language_code'>Código de Idioma *</label></th>";
echo "<td>";
echo "<input type='text' id='language_code' name='code' class='regular-text' placeholder='ej: en, es, fr' required>";
echo "<p class='description'>Código ISO 639-1 (2-5 caracteres)</p>";
echo "</td>";
echo "</tr>";

// Language Name
echo "<tr>";
echo "<th scope='row'><label for='language_name'>Nombre del Idioma *</label></th>";
echo "<td>";
echo "<input type='text' id='language_name' name='name' class='regular-text' placeholder='ej: English, Español, Français' required>";
echo "<p class='description'>Nombre del idioma en español</p>";
echo "</td>";
echo "</tr>";

// Site Name - THIS IS THE IMPORTANT FIELD
echo "<tr style='background-color: #ffffcc; border: 2px solid #ffcc00;'>";
echo "<th scope='row'><label for='language_site_name'>Site Name</label></th>";
echo "<td>";
echo "<input type='text' id='language_site_name' name='site_name' class='regular-text' placeholder='ej: WordPress Specialist, Especialista en WordPress'>";
echo "<p class='description'><strong>Nombre corto del sitio para este idioma (usado en títulos de página). Ejemplo: \"WordPress Specialist\" para inglés.</strong></p>";
echo "</td>";
echo "</tr>";

// Site Title
echo "<tr>";
echo "<th scope='row'><label for='language_site_title'>Site Title</label></th>";
echo "<td>";
echo "<input type='text' id='language_site_title' name='site_title' class='regular-text' placeholder='ej: Mi Sitio Web - Versión en Inglés'>";
echo "<p class='description'>Título completo del sitio para este idioma (usado en landing pages y metadatos SEO)</p>";
echo "</td>";
echo "</tr>";

// Site Description
echo "<tr>";
echo "<th scope='row'><label for='language_site_description'>Site Description</label></th>";
echo "<td>";
echo "<textarea id='language_site_description' name='site_description' class='large-text' rows='3' placeholder='Breve descripción de tu sitio web en este idioma...'></textarea>";
echo "<p class='description'>Descripción del sitio para este idioma (usado en landing pages y metadatos SEO)</p>";
echo "</td>";
echo "</tr>";

echo "</table>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>¿Dónde encontrar esta interfaz?</h2>";
echo "<ol>";
echo "<li>Ve al <strong>Panel de WordPress</strong></li>";
echo "<li>En el menú lateral, busca <strong>EZ Translate</strong></li>";
echo "<li>Haz clic en <strong>Languages</strong></li>";
echo "<li>En la sección <strong>\"Add New Language\"</strong>, deberías ver el campo <strong>\"Site Name\"</strong> resaltado arriba</li>";
echo "<li>Para editar un idioma existente, haz clic en el botón <strong>\"Edit\"</strong> junto al idioma en la tabla</li>";
echo "</ol>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>Explicación del Campo Site Name</h2>";
echo "<ul>";
echo "<li><strong>Site Name</strong>: Nombre corto que aparece en el título de la página (ej: \"WordPress Specialist\")</li>";
echo "<li><strong>Site Title</strong>: Título completo para landing pages (ej: \"WordPress Specialist - Professional Services\")</li>";
echo "<li><strong>Site Description</strong>: Descripción para metadatos (ej: \"Expert WordPress development...\")</li>";
echo "</ul>";
echo "<p><strong>Ejemplo de uso:</strong></p>";
echo "<p>Si configuras Site Name = \"WordPress Specialist\" para inglés, entonces las páginas en inglés mostrarán:</p>";
echo "<code>&lt;title&gt;About Us - WordPress Specialist&lt;/title&gt;</code>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>¿No ves el campo Site Name?</h2>";
echo "<p>Si no ves el campo \"Site Name\" en tu interfaz de administración, puede ser que:</p>";
echo "<ol>";
echo "<li><strong>Necesites actualizar el plugin</strong> - Asegúrate de tener la versión más reciente</li>";
echo "<li><strong>Haya un problema de caché</strong> - Intenta refrescar la página (Ctrl+F5)</li>";
echo "<li><strong>Falte algún archivo</strong> - Verifica que todos los archivos del plugin estén presentes</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>
