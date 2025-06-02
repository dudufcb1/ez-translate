# 🧪 Instrucciones de Testing - Paso 4.1: Designación de Landing Pages

## 📋 Resumen de Funcionalidades a Validar

El Paso 4.1 ha implementado la funcionalidad completa de designación de landing pages con las siguientes características:

### ✅ Funcionalidades Implementadas
- **Panel de Landing Page** disponible para todas las páginas con idioma asignado
- **Validación de unicidad** - solo una landing page por idioma
- **Campos SEO específicos** para landing pages (título y descripción)
- **Validación en REST API** con códigos de error específicos
- **Toggle on/off** de estado de landing page
- **Soporte multi-idioma** - cada idioma puede tener su propia landing page

## 🎯 Plan de Testing Manual

### 1. Acceso al Panel de Gutenberg

**Pasos:**
1. Ir a WordPress Admin → Pages → Add New (o editar página existente)
2. Abrir el editor de Gutenberg
3. Buscar el panel "EZ Translate" en la barra lateral derecha
4. Si no está visible, hacer clic en el icono de configuración (engranaje) y buscar "EZ Translate"

**Resultado esperado:** Panel EZ Translate visible con secciones "Translation Settings" y "Landing Page Settings"

### 2. Testing de Visibilidad del Panel Landing Page

**Pasos:**
1. En una página nueva, verificar que el panel "Landing Page Settings" NO aparece
2. En el panel "Translation Settings", seleccionar un idioma destino
3. Hacer clic en "Create Translation Page" para crear una traducción
4. En la nueva página de traducción, verificar que aparece el panel "Landing Page Settings"
5. Alternativamente, asignar manualmente un idioma a una página existente

**Resultado esperado:** Panel "Landing Page Settings" solo aparece cuando la página tiene un idioma asignado

### 3. Testing de Designación Básica de Landing Page

**Pasos:**
1. En una página con idioma asignado (ej: español)
2. Activar el toggle "Landing Page" en el panel "Landing Page Settings"
3. Guardar la página
4. Verificar que el toggle permanece activado después de recargar

**Resultado esperado:** Página marcada exitosamente como landing page

### 4. Testing de Campos SEO para Landing Pages

**Pasos:**
1. Con una página marcada como landing page
2. Verificar que aparecen los campos "SEO Title" y "SEO Description"
3. Llenar ambos campos con contenido de prueba:
   - SEO Title: "Página Principal en Español - Mi Sitio Web"
   - SEO Description: "Esta es la página principal de nuestro sitio web en español con información importante."
4. Guardar la página
5. Recargar y verificar que los valores se mantienen

**Resultado esperado:** Campos SEO visibles solo para landing pages y valores guardados correctamente

### 5. Testing de Validación: Una Landing Page por Idioma

**Pasos:**
1. Crear/editar una segunda página en el mismo idioma (ej: español)
2. Intentar marcarla como landing page activando el toggle
3. Observar el comportamiento y mensajes de error

**Resultado esperado:** Error mostrado indicando que ya existe una landing page para ese idioma

### 6. Testing de Múltiples Idiomas

**Pasos:**
1. Crear páginas en diferentes idiomas (inglés, español, francés)
2. Marcar cada una como landing page de su respectivo idioma
3. Verificar que todas pueden ser landing pages simultáneamente

**Resultado esperado:** Cada idioma puede tener su propia landing page sin conflictos

### 7. Testing de Toggle Off

**Pasos:**
1. Con una página marcada como landing page
2. Desactivar el toggle "Landing Page"
3. Verificar que los campos SEO desaparecen
4. Guardar la página
5. Verificar que ahora otra página del mismo idioma puede ser marcada como landing page

**Resultado esperado:** Landing page status removido correctamente y campos SEO limpiados

### 8. Testing de REST API (Avanzado)

**Pasos:**
1. Abrir las herramientas de desarrollador del navegador (F12)
2. Ir a la pestaña "Network"
3. Intentar marcar una página como landing page cuando ya existe otra
4. Observar las llamadas a la API y respuestas de error

**Resultado esperado:** Código de error 409 con mensaje "landing_page_exists"

## 🧪 Testing Automatizado

Para ejecutar las pruebas automatizadas:

**Pasos:**
1. Ir a: `tu-sitio.com/wp-admin/admin.php?page=ez-translate&run_ez_translate_landing_tests=1`
2. O hacer clic en "Run Landing Page Tests" en la página de administración
3. Verificar que todas las pruebas pasan

**Resultado esperado:** Todas las pruebas deben pasar (7/7)

## 📊 Criterios de Validación

### ✅ Criterios de Éxito
- [ ] Panel "Landing Page Settings" aparece solo para páginas con idioma
- [ ] Toggle de landing page funciona correctamente
- [ ] Campos SEO aparecen solo para landing pages
- [ ] Validación previene múltiples landing pages por idioma
- [ ] Mensajes de error claros y específicos
- [ ] Múltiples idiomas pueden tener sus propias landing pages
- [ ] Toggle off limpia campos SEO automáticamente
- [ ] REST API retorna códigos de error apropiados
- [ ] Pruebas automatizadas pasan completamente
- [ ] No hay errores en logs de WordPress

### 🚨 Señales de Problemas
- Panel no aparece o aparece incorrectamente
- Toggle no se guarda o no funciona
- Múltiples landing pages permitidas para el mismo idioma
- Campos SEO no aparecen o no se guardan
- Errores JavaScript en consola del navegador
- Errores 500 en llamadas a la API
- Pruebas automatizadas que fallan

## 📝 Casos de Uso Específicos

### Caso 1: Sitio Multilingüe Básico
1. Página principal en inglés (landing page)
2. Página principal en español (landing page)
3. Páginas secundarias en ambos idiomas (no landing pages)

### Caso 2: Cambio de Landing Page
1. Página A marcada como landing page en español
2. Desmarcar página A
3. Marcar página B como nueva landing page en español

### Caso 3: Validación de Errores
1. Intentar marcar múltiples páginas como landing page del mismo idioma
2. Verificar mensajes de error apropiados

## 🎯 Próximo Paso

Una vez validado exitosamente el Paso 4.1, procederemos con:
**Paso 4.2**: Metadatos SEO para Landing Pages - Funcionalidad avanzada de SEO

## 📋 Checklist de Validación

- [ ] Panel Gutenberg funcional ✓
- [ ] Validación de unicidad ✓
- [ ] Campos SEO ✓
- [ ] Toggle on/off ✓
- [ ] Múltiples idiomas ✓
- [ ] REST API validation ✓
- [ ] Tests automatizados: 7/7 ✓
- [ ] Sin errores en logs ✓
- [ ] Documentación actualizada ✓
