# 🏗️ Arquitectura del Sistema - EZ Translate

## 📋 Visión General

EZ Translate es un plugin WordPress que implementa un sistema multilingüe robusto siguiendo las mejores prácticas de desarrollo y los estándares de WordPress. La arquitectura está diseñada para ser modular, escalable y mantenible.

## 🗂️ Estructura de Archivos y Responsabilidades

### Archivo Principal: `ez-translate.php`
**Propósito**: Punto de entrada del plugin y orquestador principal
**Responsabilidades**:
- Definición de constantes del plugin
- Implementación del patrón Singleton para control de instancia única
- Gestión de hooks de activación/desactivación
- Autoloader PSR-4 para clases del namespace `EZTranslate\`
- Verificaciones de compatibilidad (WordPress 5.8+, PHP 7.4+)
- Inicialización de componentes core
- Configuración de internacionalización

**Características Técnicas**:
- Patrón Singleton para evitar múltiples instancias
- Autoloader que convierte `EZTranslate\ClassName` a `includes/class-ez-translate-classname.php`
- Logging integrado en todas las operaciones críticas
- Manejo de errores con deactivación automática si no se cumplen requisitos

### Sistema de Logging: `includes/class-ez-translate-logger.php`
**Propósito**: Sistema centralizado de logging y debugging
**Responsabilidades**:
- Logging con múltiples niveles (error, warning, info, debug)
- Formateo consistente de mensajes con timestamp y contexto
- Integración con WordPress error_log()
- Notificaciones admin para errores críticos
- Logging especializado para operaciones de BD, API y validaciones

**Características Técnicas**:
- Namespace `EZTranslate\Logger` para organización
- Configuración automática de nivel de log basada en `WP_DEBUG`
- Métodos estáticos para facilidad de uso desde cualquier parte del código
- Contexto JSON para debugging avanzado
- Integración con sistema de notificaciones admin de WordPress

### Sistema Administrativo: `includes/class-ez-translate-admin.php`
**Propósito**: Gestión completa de la interfaz administrativa del plugin
**Responsabilidades**:
- Registro y gestión del menú administrativo principal
- Renderizado de páginas administrativas con interfaz WordPress nativa
- Gestión de formularios para operaciones CRUD de idiomas
- Verificación de capabilities y seguridad de acceso
- Enqueue de assets específicos para páginas del plugin
- Logging de actividad administrativa y accesos

**Características Técnicas**:
- Namespace `EZTranslate\Admin` para organización modular
- Menú top-level con icono `dashicons-translation` en posición 21
- Verificación doble de capabilities `manage_options`
- Interfaz responsive usando clases CSS nativas de WordPress
- Logging contextual de accesos y operaciones administrativas
- Estructura preparada para expansión con submenues adicionales
- Selector de idiomas comunes con 70+ opciones predefinidas
- Modal de edición con JavaScript para experiencia de usuario mejorada
- Validación de formularios en tiempo real
- Generación automática de slugs URL-amigables

### Sistema de Gestión de Idiomas: `includes/class-ez-translate-language-manager.php`
**Propósito**: Gestión completa de operaciones CRUD para idiomas
**Responsabilidades**:
- Operaciones de creación, lectura, actualización y eliminación de idiomas
- Validación robusta de datos de idiomas
- Sanitización de seguridad para todos los inputs
- Gestión de caché para optimización de rendimiento
- Prevención de duplicados y verificación de integridad
- Logging especializado para operaciones de base de datos

**Características Técnicas**:
- Namespace `EZTranslate\LanguageManager` con métodos estáticos
- Almacenamiento en `wp_options` con clave `ez_translate_languages`
- Sistema de caché con transients de WordPress (1 hora de expiración)
- Validación con expresiones regulares para códigos y slugs
- Sanitización usando funciones nativas de WordPress
- Manejo de errores con `WP_Error` para consistencia
- Métodos especializados para idiomas habilitados
- Integración completa con sistema de logging

**Estructura de Datos de Idiomas**:
- `code`: Código ISO 639-1 (2-5 caracteres alfanuméricos, único)
- `name`: Nombre en inglés (obligatorio)
- `slug`: Slug URL-amigable (único, generado automáticamente)
- `native_name`: Nombre en idioma nativo (opcional)
- `flag`: Emoji de bandera del país (opcional)
- `rtl`: Dirección de texto derecha-izquierda (boolean, default false)
- `enabled`: Estado activo del idioma (boolean, default true)

### Script de Desinstalación: `uninstall.php`
**Propósito**: Limpieza completa al eliminar el plugin
**Responsabilidades**:
- Eliminación de opciones del plugin (`ez_translate_*`)
- Limpieza de post meta relacionados
- Eliminación de transients
- Logging del proceso de limpieza

**Características Técnicas**:
- Verificación de seguridad con `WP_UNINSTALL_PLUGIN`
- Uso directo de `$wpdb` para operaciones de limpieza masiva
- Eliminación selectiva por prefijos para evitar conflictos

## 🏛️ Patrones Arquitectónicos Implementados

### 1. Singleton Pattern
**Ubicación**: Clase principal `EZTranslate`
**Justificación**: Garantiza una sola instancia del plugin y proporciona punto de acceso global
**Implementación**: Método estático `get_instance()` con instancia privada

### 2. Autoloading PSR-4
**Ubicación**: Método `autoload()` en clase principal
**Justificación**: Carga automática de clases sin require manual
**Convención**: `EZTranslate\ClassName` → `includes/class-ez-translate-classname.php`

### 3. Static Factory Pattern
**Ubicación**: Clases `Logger` y `LanguageManager`
**Justificación**: Acceso simple a funcionalidad sin instanciación
**Implementación**:
- Logger: Métodos estáticos `error()`, `warning()`, `info()`, `debug()`
- LanguageManager: Métodos estáticos `add_language()`, `get_languages()`, etc.

### 4. Data Access Object (DAO) Pattern
**Ubicación**: Clase `LanguageManager`
**Justificación**: Abstrae el acceso a datos de idiomas del resto del sistema
**Implementación**: Métodos especializados para operaciones CRUD con validación integrada

## 🔧 Convenciones de Desarrollo

### Nomenclatura de Archivos
- **Clases**: `class-ez-translate-{nombre}.php`
- **Funciones**: `functions-ez-translate-{categoria}.php`
- **Templates**: `template-ez-translate-{nombre}.php`

### Namespace y Clases
- **Namespace principal**: `EZTranslate\`
- **Subnamespaces**: `EZTranslate\Admin\`, `EZTranslate\Frontend\`, etc.
- **Convención de clases**: PascalCase (`Logger`, `LanguageManager`)

### Logging y Debugging
- **Prefijo**: `[EZ-Translate]`
- **Formato**: `[EZ-Translate] [TIMESTAMP] LEVEL: Message | Context: {json}`
- **Niveles**: error (1), warning (2), info (3), debug (4)

## 🛡️ Seguridad Implementada

### Prevención de Acceso Directo
- Verificación `!defined('ABSPATH')` en todos los archivos PHP
- Archivos `index.php` en todos los directorios con contenido "Silence is golden"

### Verificaciones de Capabilities
- Uso de `manage_options` para acceso administrativo
- Verificaciones en hooks de activación/desactivación

### Sanitización y Validación
- Preparado para sanitización de inputs en futuras implementaciones
- Estructura para validación de datos antes de almacenamiento

## 📊 Almacenamiento de Datos

### WordPress Options API
- **Clave principal**: `ez_translate_languages`
- **Formato**: Array JSON con configuración de idiomas
- **Estructura**: Array de objetos con campos code, name, slug, native_name, flag, rtl, enabled
- **Transients**: Cache con prefijo `ez_translate_` (expiración 1 hora)
- **Validación**: Códigos únicos, slugs únicos, formatos ISO 639-1

### Post Meta (Futuro)
- **Prefijo**: `_ez_translate_`
- **Campos planificados**:
  - `_ez_translate_language`: Código de idioma
  - `_ez_translate_group`: ID de grupo de traducción
  - `_ez_translate_is_landing`: Boolean para landing pages
  - `_ez_translate_seo_title`: Título SEO específico
  - `_ez_translate_seo_description`: Descripción SEO específica

## 🔄 Flujo de Inicialización

1. **Carga del archivo principal** (`ez-translate.php`)
2. **Definición de constantes** (versión, rutas, text domain)
3. **Instanciación Singleton** de la clase principal
4. **Registro de hooks** (activación, desactivación, init)
5. **Configuración del autoloader** para clases futuras
6. **Carga de dependencias** (Logger)
7. **Inicialización en hook `plugins_loaded`**
8. **Verificación de requisitos** (WordPress/PHP versions)
9. **Carga de text domain** para internacionalización
10. **Inicialización de componentes core**:
    - **Detección de contexto admin** (`is_admin()`)
    - **Carga de clase Admin** si está en área administrativa
    - **Instanciación de EZTranslate\Admin**
    - **Registro de hooks administrativos** (admin_menu, admin_enqueue_scripts)

## 🎯 Principios de Diseño

### Modularidad
- Cada componente tiene responsabilidades específicas
- Bajo acoplamiento entre módulos
- Alta cohesión dentro de cada módulo

### Extensibilidad
- Estructura preparada para nuevos componentes
- Hooks de WordPress para integración
- Namespace organizado para crecimiento

### Mantenibilidad
- Código autodocumentado con comentarios PHPDoc
- Logging comprensivo para debugging
- Convenciones consistentes en todo el proyecto

### Performance
- Autoloading para carga bajo demanda
- Uso de transients para cache
- Mínima sobrecarga en frontend

## 🚀 Preparación para Futuras Fases

La arquitectura actual está preparada para:
- **✅ Admin Interface**: Implementado con menú principal y página de gestión
- **REST API**: Namespace y logging preparados para endpoints
- **Gutenberg Integration**: Directorio `src/gutenberg` creado y listo
- **Frontend Optimization**: Hooks y estructura listos para SEO
- **Testing**: Estructura modular facilita unit testing
- **Database Operations**: Logging y estructura preparados para CRUD de idiomas

### Estado Actual de Componentes

**✅ Completados**:
- Sistema de logging centralizado
- Interfaz administrativa completa con gestión de idiomas
- Autoloader PSR-4 funcional
- Estructura de seguridad implementada
- Sistema de base de datos para idiomas (CRUD completo)
- Selector de idiomas comunes (70+ opciones)
- Validación y sanitización robusta
- Sistema de caché optimizado
- Suite de pruebas comprensiva

**🔄 En Preparación**:
- REST API endpoints para Gutenberg
- Integración con editor Gutenberg
- Sistema de metadatos de página
- Optimizaciones SEO frontend

**📊 Métricas de Implementación**:
- **Archivos de código**: 16 archivos
- **Clases implementadas**: 4 clases principales
- **Líneas de código**: ~1,850 líneas
- **Cobertura de tests**: 9 tests automatizados
- **Idiomas soportados**: 70+ idiomas con códigos ISO
- **Operaciones CRUD**: 100% implementadas y probadas

Esta base sólida permite el desarrollo incremental siguiendo el plan establecido, manteniendo la calidad del código y la facilidad de mantenimiento. El sistema de gestión de idiomas está completamente funcional y listo para la implementación de metadatos de página y integración con Gutenberg en las siguientes fases.