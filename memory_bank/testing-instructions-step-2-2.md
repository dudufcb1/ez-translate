# 🧪 Instrucciones de Testing - Paso 2.2: Metadatos de Página - Estructura

## 📋 Resumen de Funcionalidades Implementadas

El Paso 2.2 ha implementado un sistema completo de gestión de metadatos multilingües para páginas con las siguientes características:

### ✅ Funcionalidades Implementadas
- **Sistema de metadatos completo** para páginas multilingües
- **Hooks de WordPress** para procesar metadatos en `save_post`
- **Generación automática de UUIDs** para grupos de traducción
- **Validación robusta** de datos y formatos
- **Funciones helper** para leer/escribir metadatos
- **Sistema de logging** comprensivo para todas las operaciones
- **Suite de pruebas automatizadas** (16 tests)

### 📊 Metadatos Implementados
- `_ez_translate_language`: Código de idioma de la página
- `_ez_translate_group`: ID de grupo de traducción (formato "tg_xxxxxxxxxxxxxxxx")
- `_ez_translate_is_landing`: Boolean para páginas landing
- `_ez_translate_seo_title`: Título SEO específico para landing pages
- `_ez_translate_seo_description`: Descripción SEO para landing pages

## 🎯 Plan de Testing Manual

### 1. Verificar Inicialización del Sistema

**Pasos:**
1. Ir a WordPress Admin → EZ Translate
2. Verificar que la página carga sin errores
3. Revisar logs de WordPress para confirmar inicialización del PostMetaManager
4. Buscar en logs: `[EZ-Translate] Info: Post Meta Manager initialized`

**Resultado esperado:** Sistema inicializado sin errores

### 2. Testing de Generación de Group IDs

**Pasos:**
1. Ir a: `tu-sitio.com/wp-admin/admin.php?page=ez-translate&run_ez_translate_tests=1`
2. Buscar la sección "Post Meta Manager Tests"
3. Verificar test "Generate Group Id"
4. Confirmar que el ID generado tiene formato "tg_" + 16 caracteres alfanuméricos

**Resultado esperado:** ✅ Generate Group Id test pasa

### 3. Testing de Validación de Group IDs

**Pasos:**
1. En la misma página de tests
2. Verificar test "Validate Group Id"
3. Confirmar que la validación funciona correctamente

**Resultado esperado:** ✅ Validate Group Id test pasa

### 4. Testing de Metadatos de Idioma

**Pasos:**
1. Verificar tests "Set Post Language" y "Get Post Language"
2. Confirmar que se puede asignar y recuperar idiomas de páginas

**Resultado esperado:** ✅ Ambos tests pasan

### 5. Testing de Grupos de Traducción

**Pasos:**
1. Verificar tests "Set Post Group" y "Get Post Group"
2. Confirmar que se pueden asignar y recuperar grupos de traducción

**Resultado esperado:** ✅ Ambos tests pasan

### 6. Testing de Landing Pages

**Pasos:**
1. Verificar tests "Set Landing Status" y "Is Landing Page"
2. Confirmar que se puede marcar páginas como landing pages

**Resultado esperado:** ✅ Ambos tests pasan

### 7. Testing de Metadatos SEO

**Pasos:**
1. Verificar tests "Set Seo Title", "Get Seo Title", "Set Seo Description", "Get Seo Description"
2. Confirmar que se pueden guardar y recuperar metadatos SEO

**Resultado esperado:** ✅ Todos los tests SEO pasan

### 8. Testing de Funciones de Consulta

**Pasos:**
1. Verificar tests "Get Landing For Language", "Get Posts In Group", "Get Posts By Language"
2. Confirmar que las consultas de base de datos funcionan correctamente

**Resultado esperado:** ✅ Todos los tests de consulta pasan

### 9. Testing de Metadatos Completos

**Pasos:**
1. Verificar test "Get All Metadata"
2. Confirmar que se pueden recuperar todos los metadatos de una página

**Resultado esperado:** ✅ Get All Metadata test pasa

### 10. Testing Manual con Páginas Reales

**Pasos:**
1. Crear una nueva página en WordPress
2. Verificar en logs que se ejecuta `handle_post_save`
3. Buscar en logs: `[EZ-Translate] Info: Processing post save`
4. Confirmar que no hay errores en el proceso

**Resultado esperado:** Página se guarda sin errores, logs muestran procesamiento

## 🔧 Testing de Base de Datos

### Verificar Estructura de Metadatos

**Pasos:**
1. Acceder a phpMyAdmin o herramienta de BD
2. Ir a tabla `wp_postmeta`
3. Buscar entradas con meta_key que empiecen con `_ez_translate_`
4. Verificar que los valores tienen el formato correcto

**Resultado esperado:** Metadatos guardados correctamente en BD

### Verificar Integridad de Group IDs

**Pasos:**
1. En la tabla `wp_postmeta`
2. Buscar entradas con meta_key = `_ez_translate_group`
3. Verificar que todos los valores tienen formato "tg_" + 16 caracteres

**Resultado esperado:** Todos los group IDs tienen formato válido

## 🧪 Testing Automatizado Completo

### Ejecutar Suite de Pruebas

**Pasos:**
1. Ir a: `tu-sitio.com/wp-admin/admin.php?page=ez-translate&run_ez_translate_tests=1`
2. Verificar que aparecen dos secciones de tests:
   - Language Manager Tests (9 tests)
   - Post Meta Manager Tests (16 tests)
3. Confirmar que todos los tests pasan

**Resultado esperado:** 25/25 tests pasan (9 + 16)

## 📊 Criterios de Validación

### ✅ Criterios de Éxito
- [ ] PostMetaManager se inicializa correctamente
- [ ] Generación de Group IDs funciona (formato tg_xxxxxxxxxxxxxxxx)
- [ ] Validación de Group IDs funciona correctamente
- [ ] Metadatos de idioma se guardan y recuperan
- [ ] Grupos de traducción se asignan correctamente
- [ ] Status de landing page funciona
- [ ] Metadatos SEO se guardan y recuperan
- [ ] Consultas de base de datos funcionan
- [ ] Hooks de save_post se ejecutan sin errores
- [ ] Todos los tests automatizados pasan (16/16)
- [ ] No hay errores en logs de WordPress
- [ ] Metadatos se guardan correctamente en wp_postmeta

### 🚨 Señales de Problemas
- Errores 500 o páginas en blanco
- Tests automatizados que fallan
- Errores en logs de WordPress
- Metadatos que no se guardan en BD
- Group IDs con formato incorrecto
- Hooks que no se ejecutan

## 📝 Reporte de Resultados

Después de completar las pruebas, reportar:

1. **Número de tests que pasan** (esperado: 16/16)
2. **Funcionalidades que funcionan correctamente**
3. **Problemas encontrados** (si los hay)
4. **Estado de los logs** (sin errores críticos)
5. **Confirmación para proceder al siguiente paso**

## 🎯 Próximo Paso

Una vez validado exitosamente el Paso 2.2, procederemos con:
**Paso 3.1**: Panel Gutenberg Básico - Integración con el editor de bloques para gestionar metadatos multilingües

## 📋 Checklist de Validación

- [ ] PostMetaManager inicializado ✓
- [ ] Tests automatizados: 16/16 ✓
- [ ] Generación de Group IDs ✓
- [ ] Metadatos de idioma ✓
- [ ] Grupos de traducción ✓
- [ ] Landing pages ✓
- [ ] Metadatos SEO ✓
- [ ] Consultas de BD ✓
- [ ] Hooks de WordPress ✓
- [ ] Sin errores en logs ✓
- [ ] Datos en wp_postmeta ✓
