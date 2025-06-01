# Plan de Implementación: EZ Translate - Sistema Multilingüe WordPress

## Especificaciones Técnicas del Plugin

### Identidad del Plugin
- **Nombre**: EZ Translate
- **Slug**: ez-translate
- **Text Domain**: ez-translate
- **Versión Inicial**: 1.0.0
- **Archivo Principal**: ez-translate.php
- **Capabilities Requeridas**: manage_options

### Requisitos Técnicos
- **PHP Mínimo**: 7.4+
- **WordPress Mínimo**: 5.8+
- **Ubicación Menú**: Top-level menu (después de "Pages")
- **Icono Menú**: dashicons-translation

### Estructura de Datos

#### Idiomas (wp_options: ez_translate_languages)
```json
{
  "code": "es",              // ISO 639-1 (obligatorio, 2-5 chars)
  "name": "Español",         // Nombre legible (obligatorio)
  "slug": "spanish",         // URL slug único (obligatorio)
  "native_name": "Español",  // Nombre nativo (opcional)
  "flag": "🇪🇸",             // Emoji bandera (opcional)
  "rtl": false,              // Dirección texto (opcional, default false)
  "enabled": true            // Estado activo (obligatorio, default true)
}
```

#### Grupos de Traducción
- **Formato ID**: "tg_" + 16 caracteres alfanuméricos (UUID)
- **Ejemplo**: "tg_12a3b4c5d6e7f8g9"

### REST API Endpoints (Namespace: /wp-json/ez-translate/v1/)
- `GET /languages` - Listar idiomas
- `POST /languages` - Crear idioma
- `PUT /languages/{code}` - Editar idioma
- `DELETE /languages/{code}` - Eliminar idioma
- `GET /post-meta/{post_id}` - Obtener metadatos multilingües
- `POST /post-meta/{post_id}` - Guardar metadatos multilingües

### Logging y Debugging
- **Sistema**: WordPress nativo error_log()
- **Prefix**: "[EZ-Translate]"
- **Desarrollo**: Logs detallados de todas las operaciones
- **Producción**: Solo errores críticos y operaciones importantes

## Estructura del Plan

Este plan está diseñado para desarrollo incremental con validación continua. Cada paso incluye implementación específica, debugging estratégico y validación del usuario antes de proceder al siguiente.

## 🏗️ FASE 1: Fundación del Plugin

### Paso 1.1: Estructura Base del Plugin
**Implementar:**
- Crear archivo principal `ez-translate.php` con headers WordPress estándar
- Implementar hooks básicos de activación/desactivación
- Crear estructura de carpetas completa:
  ```
  ez-translate/
  ├── admin/              # Páginas administrativas
  ├── includes/           # Clases PHP core
  ├── assets/            # CSS/JS compilados
  │   ├── css/
  │   └── js/
  ├── src/               # Fuentes para build (React/SCSS)
  │   ├── gutenberg/
  │   └── admin/
  ├── languages/         # Archivos de traducción
  ├── ez-translate.php   # Archivo principal
  ├── uninstall.php      # Limpieza al desinstalar
  └── README.md          # Documentación
  ```
- Agregar autoloader para clases PHP con namespace `EZTranslate\`
- Configurar text domain y preparar para internacionalización

**Debug estratégico:**
- Log de activación/desactivación del plugin
- Verificación de carga de archivos principales

**Validación usuario:**
- Activar/desactivar plugin sin errores
- Verificar que aparece en lista de plugins
- Revisar logs de WordPress para confirmar activación limpia

### Paso 1.2: Menú Administrativo Principal
**Implementar:**
- Crear página principal en admin con hook `admin_menu`
- Implementar menú top-level "EZ Translate" con icono `dashicons-translation`
- Posicionar después del menú "Pages" en WordPress admin
- Crear página básica "Idiomas" como página principal del menú
- Verificar capability `manage_options` para acceso
- Agregar estilos básicos usando WordPress admin CSS

**Debug estratégico:**
- Log cuando se carga el menú administrativo
- Verificar permisos de acceso

**Validación usuario:**
- Confirmar que aparece nuevo menú "Idiomas" en admin
- Verificar que la página carga sin errores 404
- Comprobar que solo usuarios con permisos apropiados ven el menú

### Paso 1.3: Sistema de Base de Datos
**Implementar:**
- Crear funciones para gestionar idiomas en `wp_options` con key `ez_translate_languages`
- Implementar CRUD básico para idiomas con estructura completa:
  - code (ISO 639-1, 2-5 chars, obligatorio, único)
  - name (nombre legible, obligatorio)
  - slug (URL slug, obligatorio, único)
  - native_name (nombre nativo, opcional)
  - flag (emoji bandera, opcional)
  - rtl (dirección texto, opcional, default false)
  - enabled (estado activo, obligatorio, default true)
- Validaciones: códigos únicos, slugs únicos, formato de código válido
- Usar formato JSON para almacenar configuración de idiomas

**Debug estratégico:**
- Log de operaciones de base de datos
- Verificar integridad de datos almacenados

**Validación usuario:**
- Agregar un idioma de prueba y verificar que se guarda
- Revisar en base de datos que los datos están en `wp_options`
- Confirmar que los datos persisten tras recargar página

## 🎯 FASE 2: Gestión Básica de Idiomas

### Paso 2.1: Interface de Gestión de Idiomas
**Implementar:**
- Formulario para agregar idiomas (código, nombre, slug)
- Lista de idiomas existentes con opciones editar/eliminar
- Validación básica de formularios

**Debug estratégico:**
- Log de validaciones de formulario
- Tracking de operaciones CRUD exitosas/fallidas

**Validación usuario:**
- Agregar 2-3 idiomas diferentes
- Editar un idioma existente
- Eliminar un idioma
- Verificar que validaciones funcionan (ej: código duplicado)

### Paso 2.2: Metadatos de Página - Estructura
**Implementar:**
- Sistema para guardar metadatos multilingües en `wp_postmeta`:
  - `_ez_translate_language` (código de idioma de la página)
  - `_ez_translate_group` (ID de grupo de traducción formato "tg_xxxxxxxxxxxxxxxx")
  - `_ez_translate_is_landing` (boolean, si es landing page del idioma)
  - `_ez_translate_seo_title` (título SEO específico para landing pages)
  - `_ez_translate_seo_description` (descripción SEO para landing pages)
- Hooks en `save_post` para procesar metadatos
- Funciones helper para leer/escribir metadatos de página
- Generación automática de IDs de grupo de traducción con formato UUID

**Debug estratégico:**
- Log cuando se guardan metadatos por página
- Verificar que metadatos se asocian correctamente con posts

**Validación usuario:**
- Crear una página de prueba
- Verificar en base de datos que se crean entradas en `wp_postmeta`
- Confirmar que metadatos se mantienen al editar página

## 🧩 FASE 3: Integración con Editor

### Paso 3.1: Panel Gutenberg Básico
**Implementar:**
- Crear sidebar plugin para Gutenberg usando React y @wordpress/components
- Selector de idioma para página actual (dropdown con idiomas disponibles)
- Integración con WordPress data store (@wordpress/data)
- Comunicación con REST API endpoints:
  - GET /wp-json/ez-translate/v1/languages (cargar idiomas)
  - GET /wp-json/ez-translate/v1/post-meta/{post_id} (cargar metadatos)
  - POST /wp-json/ez-translate/v1/post-meta/{post_id} (guardar metadatos)
- Usar @wordpress/api-fetch para comunicación con backend

**Debug estratégico:**
- Console.log en JavaScript para verificar carga del componente
- Verificar comunicación con REST API

**Validación usuario:**
- Abrir editor Gutenberg y confirmar que aparece panel lateral
- Cambiar idioma de página y verificar que se guarda
- Revisar consola del navegador para errores JavaScript

### Paso 3.2: Sistema de Grupos de Traducción
**Implementar:**
- Campo grupo de traducción en metadatos
- Lógica para asignar automáticamente grupo a páginas nuevas
- Interface para modificar grupo de traducción

**Debug estratégico:**
- Log de asignación automática de grupos
- Verificar unicidad de identificadores de grupo

**Validación usuario:**
- Crear páginas en diferentes idiomas del mismo contenido
- Verificar que se asignan al mismo grupo de traducción
- Confirmar que grupos se muestran correctamente en interface

## ⚙️ FASE 4: Landing Pages y SEO Básico

### Paso 4.1: Designación de Landing Pages
**Implementar:**
- Checkbox en panel Gutenberg para marcar como landing page
- Validación para solo una landing page por idioma
- Storage en metadatos de página

**Debug estratégico:**
- Log cuando se designa/remueve landing page
- Verificar validación de unicidad

**Validación usuario:**
- Designar una página como landing page de un idioma
- Intentar designar segunda landing page del mismo idioma (debe fallar)
- Verificar que status se mantiene al guardar

### Paso 4.2: Metadatos SEO para Landing Pages
**Implementar:**
- Campos adicionales en Gutenberg para title/description SEO
- Almacenamiento en metadatos de página
- Mostrar campos solo cuando página es landing page

**Debug estratégico:**
- Log de guardado de metadatos SEO
- Verificar que campos solo aparecen en landing pages

**Validación usuario:**
- Configurar metadatos SEO en landing page
- Verificar que campos desaparecen al quitar status de landing page
- Confirmar que datos se guardan correctamente

## 🌐 FASE 5: Frontend y Optimización SEO

### Paso 5.1: Inyección de Metadatos en Frontend
**Implementar:**
- Hook en `wp_head` para inyectar metadatos SEO
- Lógica para detectar landing pages automáticamente
- Override de title/description cuando corresponda

**Debug estratégico:**
- Log de detección de landing pages en frontend
- Verificar que metadatos se inyectan correctamente

**Validación usuario:**
- Ver código fuente de landing page
- Confirmar que title/description aparecen en HTML
- Verificar que páginas normales no se ven afectadas

### Paso 5.2: Implementación de Hreflang
**Implementar:**
- Generación automática de etiquetas hreflang
- Detección de páginas relacionadas por grupo
- Inyección en `wp_head`

**Debug estratégico:**
- Log de generación de relaciones hreflang
- Verificar que solo páginas con traducciones generan etiquetas

**Validación usuario:**
- Ver código fuente de páginas con traducciones
- Confirmar presencia de etiquetas hreflang correctas
- Verificar que URLs apuntan a páginas correctas

## 🔧 FASE 6: Herramientas Administrativas

### Paso 6.1: Mejoras en Listado de Páginas
**Implementar:**
- Columna de idioma en wp-admin/edit.php
- Indicadores visuales para landing pages
- Enlaces rápidos a traducciones

**Debug estratégico:**
- Log de carga de información multilingüe en listados
- Verificar performance de consultas adicionales

**Validación usuario:**
- Ver listado de páginas con nueva columna de idioma
- Confirmar que indicadores aparecen correctamente
- Usar enlaces rápidos para navegar entre traducciones

### Paso 6.2: Vista de Resumen General
**Implementar:**
- Dashboard en página principal del plugin
- Estadísticas de páginas por idioma
- Lista de landing pages configuradas

**Debug estratégico:**
- Log de generación de estadísticas
- Verificar precisión de conteos

**Validación usuario:**
- Revisar dashboard con resumen de configuración
- Verificar que estadísticas son precisas
- Confirmar que landing pages se listan correctamente

## 📋 Metodología de Validación

### Para Cada Paso:
1. **Implementación**: Código específico con debugging integrado
2. **Auto-testing**: Verificar funcionalidad básica antes de entregar
3. **Validación usuario**: Instrucciones específicas de testing
4. **Review conjunto**: Análisis de logs y comportamiento observado
5. **Aprobación**: Confirmación antes de proceder al siguiente paso

### Criterios de Aprobación por Paso:
- ✅ Funcionalidad implementada funciona según especificación
- ✅ No hay errores críticos en logs
- ✅ Performance aceptable
- ✅ Comportamiento esperado confirmado por usuario

## 📝 Reglas de Validación Específicas

### Validación de Idiomas
- **Códigos**: ISO 639-1 preferido, pero permitir 2-5 caracteres alfanuméricos
- **Unicidad**: Códigos y slugs deben ser únicos
- **Nombres**: Pueden repetirse pero mostrar advertencia
- **Slugs**: Compatible con URLs (sin espacios, caracteres especiales)

### Validación de Landing Pages
- **Restricción**: Solo una landing page por idioma
- **Metadatos SEO**: Solo disponibles para landing pages
- **Campos requeridos**: Título y descripción SEO para landing pages

### Logging Específico
- **Formato**: "[EZ-Translate] Operación: Detalles"
- **Desarrollo**: Log detallado de CRUD, validaciones, API calls
- **Producción**: Solo errores críticos, activación/desactivación, operaciones importantes
- **Ubicación**: WordPress error_log() estándar

### REST API Security
- **Autenticación**: WordPress nonce verification
- **Capabilities**: Verificar manage_options para todas las operaciones
- **Sanitización**: Todos los inputs deben ser sanitizados
- **Validación**: Validar estructura de datos antes de guardar
