<?php
/**
 * Diagnostic script to check admin interface
 */

echo "<h1>EZ Translate - Diagnóstico de Interfaz de Administración</h1>";

// Check if admin file exists
$admin_file = __DIR__ . '/includes/class-ez-translate-admin.php';
echo "<h2>1. Verificación de Archivos</h2>";
if (file_exists($admin_file)) {
    echo "✅ Archivo de administración encontrado: " . $admin_file . "<br>";
    
    // Check file size
    $file_size = filesize($admin_file);
    echo "📁 Tamaño del archivo: " . number_format($file_size) . " bytes<br>";
    
    // Check if file contains Site Name field
    $content = file_get_contents($admin_file);
    if (strpos($content, 'language_site_name') !== false) {
        echo "✅ Campo 'Site Name' encontrado en el código<br>";
    } else {
        echo "❌ Campo 'Site Name' NO encontrado en el código<br>";
    }
    
    if (strpos($content, 'Site Name') !== false) {
        echo "✅ Texto 'Site Name' encontrado en el código<br>";
    } else {
        echo "❌ Texto 'Site Name' NO encontrado en el código<br>";
    }
    
} else {
    echo "❌ Archivo de administración NO encontrado: " . $admin_file . "<br>";
}

// Check main plugin file
$main_file = __DIR__ . '/ez-translate.php';
echo "<h2>2. Verificación del Archivo Principal</h2>";
if (file_exists($main_file)) {
    echo "✅ Archivo principal encontrado: " . $main_file . "<br>";
    
    $content = file_get_contents($main_file);
    if (strpos($content, 'class-ez-translate-admin.php') !== false) {
        echo "✅ Referencia al archivo de administración encontrada<br>";
    } else {
        echo "❌ Referencia al archivo de administración NO encontrada<br>";
    }
} else {
    echo "❌ Archivo principal NO encontrado: " . $main_file . "<br>";
}

// Check version
echo "<h2>3. Información de Versión</h2>";
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    if (preg_match('/Version:\s*([0-9.]+)/', $content, $matches)) {
        echo "📋 Versión del plugin: " . $matches[1] . "<br>";
    }
    
    if (preg_match('/define\s*\(\s*[\'"]EZ_TRANSLATE_VERSION[\'"],\s*[\'"]([^\'\"]+)[\'"]/', $content, $matches)) {
        echo "📋 Versión definida en código: " . $matches[1] . "<br>";
    }
}

// Check specific lines in admin file
echo "<h2>4. Verificación Específica del Campo Site Name</h2>";
if (file_exists($admin_file)) {
    $lines = file($admin_file);
    $found_lines = array();
    
    foreach ($lines as $line_num => $line) {
        if (stripos($line, 'site_name') !== false || stripos($line, 'Site Name') !== false) {
            $found_lines[] = "Línea " . ($line_num + 1) . ": " . trim($line);
        }
    }
    
    if (!empty($found_lines)) {
        echo "✅ Líneas que contienen 'Site Name' encontradas:<br>";
        foreach ($found_lines as $found_line) {
            echo "• " . htmlspecialchars($found_line) . "<br>";
        }
    } else {
        echo "❌ No se encontraron líneas con 'Site Name'<br>";
    }
}

// Check directory structure
echo "<h2>5. Estructura de Directorios</h2>";
$dirs_to_check = array(
    'includes',
    'assets',
    'tests'
);

foreach ($dirs_to_check as $dir) {
    $dir_path = __DIR__ . '/' . $dir;
    if (is_dir($dir_path)) {
        echo "✅ Directorio encontrado: " . $dir . "<br>";
        
        // List files in includes directory
        if ($dir === 'includes') {
            $files = scandir($dir_path);
            echo "📁 Archivos en includes:<br>";
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "  • " . $file . "<br>";
                }
            }
        }
    } else {
        echo "❌ Directorio NO encontrado: " . $dir . "<br>";
    }
}

echo "<h2>6. Instrucciones</h2>";
echo "<p>Si todos los archivos están presentes pero no ves el campo 'Site Name' en WordPress:</p>";
echo "<ol>";
echo "<li>Verifica que el plugin esté <strong>activado</strong> en WordPress</li>";
echo "<li>Ve a <strong>EZ Translate > Languages</strong> en el panel de administración</li>";
echo "<li>Busca la sección <strong>'Add New Language'</strong></li>";
echo "<li>El campo 'Site Name' debería estar entre 'Status' y 'Site Title'</li>";
echo "<li>Si no lo ves, intenta <strong>desactivar y reactivar</strong> el plugin</li>";
echo "</ol>";

echo "<h2>7. Captura de Pantalla</h2>";
echo "<p>Si sigues sin ver el campo, por favor:</p>";
echo "<ol>";
echo "<li>Ve a <strong>EZ Translate > Languages</strong> en WordPress</li>";
echo "<li>Toma una captura de pantalla de la sección 'Add New Language'</li>";
echo "<li>Compártela para poder ayudarte mejor</li>";
echo "</ol>";
?>
