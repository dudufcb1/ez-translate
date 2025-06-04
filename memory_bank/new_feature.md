# 🆕 Nueva Funcionalidad: Landing Pages en Lista de Páginas

## 🔄 Workflow de Desarrollo de Features

### **INSTRUCCIONES PARA NUEVAS FEATURES:**

1. **📋 PLANIFICACIÓN**:
   - Crear/actualizar `new_feature.md` con especificaciones detalladas
   - Definir objetivos, implementación técnica y pasos

2. **🔄 DESARROLLO** (usar este archivo como mini progress):
   - Documentar cada paso completado en la sección "PROGRESO" abajo
   - Mantener registro de archivos modificados
   - Anotar problemas encontrados y soluciones

3. **✅ FINALIZACIÓN**:
   - Una vez completada la feature, dejar solo UNA línea en Acá:
     `✅ [FECHA] Feature: [NOMBRE] - Completada`
   - Limpiar todo el progreso detallado de este archivo
   - Actualizar `architecture.md` con la nueva funcionalidad

4. **🔄 INTEGRACIÓN**:
   - Integrar documentación en `architecture.md`
   - Actualizar `progress.md` si es necesario
   - Mantener este archivo limpio para próxima feature

---

## 📊 MINI PROGRESS - FEATURE ACTUAL

**🎯 FEATURE**: Landing Pages en Lista de Páginas del Admin
**📅 INICIO**: [FECHA DE INICIO]
**📋 ESPECIFICACIÓN**: Detallada abajo

### PROGRESO:
- [ ] Paso 1: Agregar columna "Landing Page" en lista de páginas
- [ ] Paso 2: Implementar contenido de columna (LP-{CÓDIGO})
- [ ] Paso 3: Crear tabla adicional de Landing Pages
- [ ] Paso 4: Implementar métodos helper (get_all_landing_pages)
- [ ] Paso 5: Renderizar tabla con estilos
- [ ] Paso 6: Testing y verificación
- [ ] Paso 7: Documentación en architecture.md

### ARCHIVOS MODIFICADOS:
- [ ] `includes/class-ez-translate-admin.php` (métodos principales)

### NOTAS DE DESARROLLO:
*(Agregar aquí problemas encontrados, soluciones, etc.)*

---

## 📋 Descripción de la Funcionalidad

Agregar una nueva columna "Landing Page" en la lista de páginas de WordPress (`wp-admin/edit.php?post_type=page`) que muestre:

1. **Columna "Landing Page"**:
   - **Vacío**: Si la página no es una Landing Page
   - **"LP-{CÓDIGO}"**: Si es Landing Page, mostrar código de idioma (ej: "LP-ES", "LP-EN")

2. **Tabla adicional de Landing Pages**:
   - Debajo de la tabla principal de páginas
   - Mostrar solo las Landing Pages existentes
   - Información relevante de cada Landing Page

---

## 🎯 Objetivos

- **Visibilidad mejorada**: Identificar rápidamente qué páginas son Landing Pages
- **Gestión centralizada**: Ver todas las Landing Pages en un solo lugar
- **Información clara**: Mostrar código de idioma asociado
- **Integración nativa**: Usar hooks estándar de WordPress

---

## 🔧 Implementación Técnica

### **Paso 1: Agregar Columna "Landing Page"**

**Archivo**: `includes/class-ez-translate-admin.php`

**Hooks necesarios**:
```php
// Agregar columna
add_filter('manage_pages_columns', array($this, 'add_landing_page_column'));

// Mostrar contenido de la columna
add_action('manage_pages_custom_column', array($this, 'show_landing_page_column_content'), 10, 2);

// Hacer la columna ordenable (opcional)
add_filter('manage_edit-page_sortable_columns', array($this, 'make_landing_page_column_sortable'));
```

**Método para agregar columna**:
```php
/**
 * Add Landing Page column to pages list
 *
 * @param array $columns Existing columns
 * @return array Modified columns
 */
public function add_landing_page_column($columns) {
    // Insertar después de la columna 'title'
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['ez_translate_landing'] = __('Landing Page', 'ez-translate');
        }
    }
    return $new_columns;
}
```

**Método para mostrar contenido**:
```php
/**
 * Show Landing Page column content
 *
 * @param string $column_name Column name
 * @param int    $post_id     Post ID
 */
public function show_landing_page_column_content($column_name, $post_id) {
    if ($column_name === 'ez_translate_landing') {
        // Verificar si es landing page
        $is_landing = get_post_meta($post_id, '_ez_translate_is_landing', true);
        
        if ($is_landing) {
            // Obtener código de idioma
            $language_code = get_post_meta($post_id, '_ez_translate_language', true);
            
            if ($language_code) {
                echo '<strong style="color: #0073aa;">LP-' . strtoupper(esc_html($language_code)) . '</strong>';
            } else {
                echo '<strong style="color: #d63638;">LP-?</strong>';
            }
        }
        // Si no es landing page, no mostrar nada (columna vacía)
    }
}
```

### **Paso 2: Tabla de Landing Pages**

**Hook necesario**:
```php
// Agregar tabla después de la lista principal
add_action('admin_footer-edit.php', array($this, 'add_landing_pages_table'));
```

**Método para mostrar tabla**:
```php
/**
 * Add Landing Pages table below main pages list
 */
public function add_landing_pages_table() {
    global $typenow;
    
    // Solo en la página de edición de páginas
    if ($typenow !== 'page') {
        return;
    }
    
    // Obtener todas las landing pages
    $landing_pages = $this->get_all_landing_pages();
    
    if (empty($landing_pages)) {
        return;
    }
    
    // Mostrar tabla
    $this->render_landing_pages_table($landing_pages);
}
```

### **Paso 3: Obtener Landing Pages**

**Método helper**:
```php
/**
 * Get all landing pages
 *
 * @return array Array of landing page data
 */
private function get_all_landing_pages() {
    $args = array(
        'post_type' => 'page',
        'post_status' => array('publish', 'draft', 'private'),
        'meta_query' => array(
            array(
                'key' => '_ez_translate_is_landing',
                'value' => '1',
                'compare' => '='
            )
        ),
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    $query = new WP_Query($args);
    $landing_pages = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            $landing_pages[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'language' => get_post_meta($post_id, '_ez_translate_language', true),
                'seo_title' => get_post_meta($post_id, '_ez_translate_seo_title', true),
                'status' => get_post_status(),
                'edit_url' => get_edit_post_link($post_id),
                'view_url' => get_permalink($post_id),
                'last_modified' => get_the_modified_date('Y-m-d H:i:s')
            );
        }
        wp_reset_postdata();
    }
    
    return $landing_pages;
}
```

### **Paso 4: Renderizar Tabla de Landing Pages**

**Método de renderizado**:
```php
/**
 * Render Landing Pages table
 *
 * @param array $landing_pages Array of landing page data
 */
private function render_landing_pages_table($landing_pages) {
    ?>
    <div class="wrap" style="margin-top: 30px;">
        <h2><?php _e('Landing Pages', 'ez-translate'); ?></h2>
        <p class="description">
            <?php _e('All pages configured as Landing Pages for different languages.', 'ez-translate'); ?>
        </p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 40%;"><?php _e('Title', 'ez-translate'); ?></th>
                    <th scope="col" style="width: 15%;"><?php _e('Language', 'ez-translate'); ?></th>
                    <th scope="col" style="width: 15%;"><?php _e('Status', 'ez-translate'); ?></th>
                    <th scope="col" style="width: 20%;"><?php _e('Last Modified', 'ez-translate'); ?></th>
                    <th scope="col" style="width: 10%;"><?php _e('Actions', 'ez-translate'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($landing_pages as $page): ?>
                <tr>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url($page['edit_url']); ?>">
                                <?php echo esc_html($page['title']); ?>
                            </a>
                        </strong>
                        <?php if (!empty($page['seo_title'])): ?>
                            <br><small style="color: #666;">
                                SEO: <?php echo esc_html($page['seo_title']); ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="ez-translate-language-badge" style="background: #0073aa; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                            <?php echo strtoupper(esc_html($page['language'] ?: 'N/A')); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $status_colors = array(
                            'publish' => '#00a32a',
                            'draft' => '#d63638',
                            'private' => '#dba617'
                        );
                        $status_color = $status_colors[$page['status']] ?? '#666';
                        ?>
                        <span style="color: <?php echo $status_color; ?>; font-weight: 600;">
                            <?php echo ucfirst(esc_html($page['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo esc_html(date('M j, Y \a\t g:i A', strtotime($page['last_modified']))); ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($page['edit_url']); ?>" class="button button-small">
                            <?php _e('Edit', 'ez-translate'); ?>
                        </a>
                        <?php if ($page['status'] === 'publish'): ?>
                            <a href="<?php echo esc_url($page['view_url']); ?>" class="button button-small" target="_blank">
                                <?php _e('View', 'ez-translate'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 15px;">
            <a href="<?php echo admin_url('admin.php?page=ez-translate'); ?>" class="button button-primary">
                <?php _e('Manage Languages', 'ez-translate'); ?>
            </a>
        </p>
    </div>
    
    <style>
    .ez-translate-language-badge {
        display: inline-block;
        font-weight: 600;
        text-transform: uppercase;
    }
    </style>
    <?php
}
```

---

## 🚀 Pasos de Implementación

### **1. Modificar Admin Class**
- Agregar los hooks en el constructor de `Admin`
- Implementar los métodos descritos arriba

### **2. Testing**
- Crear algunas Landing Pages de prueba
- Verificar que aparezca la columna
- Verificar que aparezca la tabla adicional

### **3. Estilos (Opcional)**
- Agregar CSS personalizado para mejorar la apariencia
- Iconos para diferentes estados

### **4. Funcionalidades Adicionales (Futuro)**
- Filtros por idioma
- Búsqueda en Landing Pages
- Acciones en lote
- Ordenamiento por columnas

---

## 📝 Notas de Implementación

### **Consideraciones**:
- **Performance**: La consulta de Landing Pages se ejecuta solo en `edit.php?post_type=page`
- **Compatibilidad**: Usar hooks estándar de WordPress
- **Responsive**: La tabla debe verse bien en diferentes tamaños de pantalla
- **Accesibilidad**: Usar etiquetas semánticas apropiadas

### **Hooks Utilizados**:
- `manage_pages_columns` - Agregar columna
- `manage_pages_custom_column` - Contenido de columna
- `admin_footer-edit.php` - Tabla adicional

### **Metadatos Utilizados**:
- `_ez_translate_is_landing` - Identificar Landing Page
- `_ez_translate_language` - Código de idioma
- `_ez_translate_seo_title` - Título SEO (opcional)

---

## 🎯 Resultado Esperado

1. **Lista de páginas** con nueva columna "Landing Page"
2. **Identificación visual** clara de Landing Pages
3. **Tabla dedicada** para gestión de Landing Pages
4. **Integración nativa** con WordPress admin
5. **Información completa** de cada Landing Page

Esta funcionalidad mejorará significativamente la experiencia de gestión de Landing Pages multiidioma en WordPress.
