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
**Ubicación**: Clase `Logger`
**Justificación**: Acceso simple a funcionalidad de logging sin instanciación
**Implementación**: Métodos estáticos `error()`, `warning()`, `info()`, `debug()`

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
- **Transients**: Cache con prefijo `ez_translate_`

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
10. **Inicialización de componentes core** (expandible)

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
- **Admin Interface**: Estructura de directorios lista
- **REST API**: Namespace y logging preparados
- **Gutenberg Integration**: Directorio `src/gutenberg` creado
- **Frontend Optimization**: Hooks y estructura listos
- **Testing**: Estructura modular facilita unit testing

Esta base sólida permite el desarrollo incremental siguiendo el plan establecido, manteniendo la calidad del código y la facilidad de mantenimiento.