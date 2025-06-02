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
- Sanitización robusta usando funciones nativas de WordPress + función `sanitize_boolean()` personalizada
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

### Sistema de Metadatos de Página: `includes/class-ez-translate-post-meta-manager.php`
**Propósito**: Gestión completa de metadatos multilingües para páginas y posts
**Responsabilidades**:
- Operaciones CRUD para metadatos multilingües en `wp_postmeta`
- Generación automática de UUIDs para grupos de traducción
- Validación de integridad de datos y formatos
- Gestión de landing pages con validación de unicidad por idioma
- Consultas optimizadas de base de datos para relaciones multilingües
- Hooks de WordPress para procesamiento automático de metadatos

**Características Técnicas**:
- Namespace `EZTranslate\PostMetaManager` con métodos estáticos
- Almacenamiento en `wp_postmeta` con prefijo `_ez_translate_`
- Hooks automáticos en `save_post` y `before_delete_post`
- Generación de Group IDs con formato "tg_" + 16 caracteres alfanuméricos
- Validación de códigos de idioma contra base de datos de idiomas
- Consultas preparadas de `$wpdb` para seguridad y rendimiento
- Logging comprensivo de todas las operaciones de metadatos

**Estructura de Metadatos Multilingües**:
- `_ez_translate_language`: Código de idioma (validado contra idiomas existentes)
- `_ez_translate_group`: ID de grupo de traducción (formato UUID)
- `_ez_translate_is_landing`: Boolean para páginas landing (único por idioma)
- `_ez_translate_seo_title`: Título SEO específico para landing pages
- `_ez_translate_seo_description`: Descripción SEO para landing pages

**Funciones Helper Avanzadas**:
- `set_post_language()`: Asignar idioma con validación
- `set_post_group()`: Asignar/generar grupo de traducción
- `set_post_landing_status()`: Marcar como landing page con validación de unicidad
- `get_posts_by_language()`: Consultar páginas por idioma
- `get_posts_in_group()`: Consultar páginas en grupo de traducción
- `get_landing_page_for_language()`: Encontrar landing page específica

### Sistema REST API: `includes/class-ez-translate-rest-api.php`
**Propósito**: API REST completa para comunicación con Gutenberg y aplicaciones externas
**Responsabilidades**:
- Endpoints públicos para lectura de idiomas (sin autenticación)
- Endpoints administrativos para gestión completa de idiomas
- Endpoints para metadatos de posts con validación de permisos
- Validación completa de entrada con esquemas de datos
- Sanitización robusta de todos los inputs
- Logging comprensivo de operaciones de API

**Características Técnicas**:
- Namespace `EZTranslate\RestAPI` con registro automático de rutas
- Base URL: `/wp-json/ez-translate/v1/`
- Endpoints públicos: `GET /languages` (acceso sin autenticación)
- Endpoints administrativos: `POST/PUT/DELETE /languages` (requiere `manage_options`)
- Endpoints de metadatos: `GET/POST /posts/{id}/meta` (validación por post)
- Esquemas de validación para todos los endpoints
- Manejo de errores con códigos HTTP apropiados
- Integración completa con sistema de logging

**Endpoints Implementados**:
- `GET /languages`: Obtener todos los idiomas habilitados
- `POST /languages`: Crear nuevo idioma (admin)
- `PUT /languages/{code}`: Actualizar idioma existente (admin)
- `DELETE /languages/{code}`: Eliminar idioma (admin)
- `GET /post-meta/{id}`: Obtener metadatos multilingües de post
- `POST /post-meta/{id}`: Actualizar metadatos multilingües de post
- `POST /create-translation/{id}`: **Crear traducción de página** (nuevo en Step 3.2)

**Funcionalidad de Creación de Traducciones** (Step 3.2):
- Duplicación completa de páginas con contenido, título y excerpt
- Copia automática de featured images y custom fields
- Gestión automática de grupos de traducción con UUIDs
- Prevención de traducciones duplicadas para el mismo idioma
- Validación de idiomas destino contra base de datos
- Verificación de permisos de edición del post original
- Creación como borrador para permitir edición
- Redirección automática al editor de la nueva traducción
- Logging comprensivo de todas las operaciones

### Sistema Gutenberg Integration: `includes/class-ez-translate-gutenberg.php`
**Propósito**: Integración completa con el editor de bloques Gutenberg
**Responsabilidades**:
- Registro de meta fields para exposición en REST API
- Enqueue inteligente de assets solo en páginas de Gutenberg
- Detección automática de páginas del editor de bloques
- Callbacks de autorización para meta fields
- Localización de scripts con datos de configuración
- Gestión de dependencias de WordPress

**Características Técnicas**:
- Namespace `EZTranslate\Gutenberg` con hooks específicos de Gutenberg
- Detección automática de contexto Gutenberg (`get_current_screen()`)
- Registro de meta fields con `show_in_rest` para API exposure
- Callbacks de autorización personalizados para cada meta field
- Enqueue condicional de assets (solo en páginas relevantes)
- Localización con datos de configuración WordPress
- Gestión automática de dependencias (`wp-element`, `wp-components`, etc.)

**Meta Fields Registrados**:
- `_ez_translate_language`: Código de idioma con validación
- `_ez_translate_group`: ID de grupo de traducción
- `_ez_translate_is_landing`: Boolean para landing pages
- `_ez_translate_seo_title`: Título SEO específico
- `_ez_translate_seo_description`: Descripción SEO específica

### Sidebar de Gutenberg: `assets/js/gutenberg-sidebar.js`
**Propósito**: Interfaz de usuario completa para gestión de traducciones en Gutenberg
**Responsabilidades**:
- Componente React completo usando WordPress components
- Implementación del flujo correcto de traducción
- Integración con WordPress data store
- Comunicación con REST API
- Manejo de estados de UI (carga, error, éxito)

**Características Técnicas**:
- Componente React usando `wp.element.createElement`
- Integración con `wp.data` para acceso a post metadata
- Comunicación con API usando `wp.apiFetch`
- Manejo de estados con React hooks (`useState`, `useEffect`)
- Componentes WordPress nativos (`PanelBody`, `SelectControl`, `ToggleControl`)
- Localización completa con `wp.i18n`

**Flujo de Traducción Implementado** (Step 3.2 - Funcional):
1. **Detección Automática**: Idioma original detectado desde configuración WordPress
2. **Idioma Original Fijo**: Mostrado como solo lectura, no modificable
3. **Selector de Destino**: Dropdown con idiomas disponibles (excluye original)
4. **Botón de Creación**: "Create Translation Page" para duplicar páginas
5. **Llamada API Real**: Integración con endpoint `/create-translation/{id}`
6. **Manejo de Respuestas**: Success/error handling con mensajes específicos
7. **Redirección Automática**: Al editor de la nueva traducción creada
8. **Preservación**: Página original mantiene su idioma intacto
9. **Grupos Automáticos**: Translation Group IDs ocultos del usuario

**Características de UX Implementadas**:
- Confirmación de usuario antes de redirección
- Mensajes de error específicos (traducción existente, idioma inválido)
- Estados de loading durante creación de traducción
- Reset automático de selección tras operación
- Integración completa con WordPress data store

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

## 🎯 Decisiones de Diseño Críticas

### Flujo de Traducción Correcto
**Decisión**: Implementar flujo de creación de páginas en lugar de modificación de idioma
**Justificación**:
- Preserva la integridad de la página original
- Evita confusión del usuario sobre qué página está editando
- Permite workflow claro: Original → Seleccionar Destino → Crear Traducción
- Mantiene relaciones claras entre páginas originales y traducciones

**Implementación**:
- Idioma original detectado automáticamente y mostrado como solo lectura
- Selector de idioma destino excluye idioma original
- Botón explícito "Create Translation Page" para duplicación
- Translation Group IDs completamente ocultos del usuario

### Ocultación de Detalles Técnicos
**Decisión**: Ocultar Translation Group IDs de la interfaz de usuario
**Justificación**:
- Los UUIDs son detalles de implementación técnica
- No aportan valor al usuario final
- Pueden causar confusión o errores de manipulación
- Simplifica la interfaz y mejora la experiencia de usuario

**Implementación**:
- Generación automática de Group IDs en background
- Manejo interno de relaciones entre páginas
- UI enfocada en acciones del usuario, no en datos técnicos

### Arquitectura REST API Híbrida
**Decisión**: Endpoints públicos para lectura, administrativos para escritura
**Justificación**:
- Gutenberg necesita acceso sin autenticación para mostrar idiomas
- Operaciones de escritura requieren permisos administrativos
- Separación clara de responsabilidades de seguridad
- Flexibilidad para futuras integraciones frontend

**Implementación**:
- `GET /languages`: Público, sin autenticación
- `POST/PUT/DELETE /languages`: Requiere `manage_options`
- Validación de permisos por endpoint específico
- Logging diferenciado por tipo de operación

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
- Sistema de metadatos multilingües completo
- Generación automática de UUIDs para grupos de traducción
- Hooks de WordPress para procesamiento automático
- Consultas optimizadas de base de datos
- **REST API completa** con endpoints públicos y administrativos
- **Integración Gutenberg completa** con sidebar funcional
- **Flujo de traducción correcto** implementado en UI
- **Meta fields registration** para exposición en REST API
- **Asset management** para JavaScript y CSS de Gutenberg

**✅ Completados en Step 3.2**:
- **Funcionalidad de duplicación de páginas** completa y funcional
- **Endpoint REST API** `/create-translation/{id}` implementado
- **Integración Gutenberg real** con API calls funcionales
- **Sistema de redirección** automática al editor de traducción
- **Manejo completo de errores** y validaciones

**🔄 En Preparación**:
- Designación de Landing Pages (Step 4.1)
- Optimizaciones SEO frontend
- Selector de idiomas frontend
- Herramientas administrativas avanzadas

**📊 Métricas de Implementación** (Actualizado Step 3.2):
- **Archivos de código**: 29 archivos
- **Clases implementadas**: 7 clases principales
- **Líneas de código**: ~5,000 líneas
- **Cobertura de tests**: 40 tests automatizados (9 Language Manager + 16 Post Meta Manager + 8 Gutenberg Integration + 7 Translation Creation) - ✅ 40/40 PASANDO
- **Idiomas soportados**: 70+ idiomas con códigos ISO
- **Operaciones CRUD**: 100% implementadas y probadas (idiomas + metadatos + REST API + creación de traducciones)
- **Metadatos multilingües**: 5 campos implementados con validación completa
- **Grupos de traducción**: Sistema UUID automático implementado y oculto del usuario
- **REST API**: 7 endpoints implementados bajo `/wp-json/ez-translate/v1/` (incluyendo creación de traducciones)
- **Gutenberg Integration**: Sidebar completo con flujo de traducción funcional y creación real de páginas
- **Assets**: JavaScript y CSS para Gutenberg con gestión de dependencias
- **Creación de Traducciones**: Sistema completo de duplicación inteligente con redirección automática

Esta base sólida permite el desarrollo incremental siguiendo el plan establecido, manteniendo la calidad del código y la facilidad de mantenimiento. El sistema de gestión de idiomas y metadatos multilingües está completamente funcional y listo para la integración con Gutenberg y optimizaciones SEO en las siguientes fases. La arquitectura modular facilita la expansión con nuevas funcionalidades mientras mantiene la estabilidad y rendimiento del sistema.