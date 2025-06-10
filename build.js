const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const readline = require('readline');

// Directorios de origen
const assetsDir = path.join(__dirname, 'assets');
const srcDir = path.join(__dirname, 'src');
const cssDir = path.join(assetsDir, 'css');
const jsDir = path.join(assetsDir, 'js');

// Directorio de salida
const outDir = path.join(__dirname, 'dist');
const buildDir = path.join(outDir, 'ez-translate');

// Crear directorios necesarios
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
if (!fs.existsSync(buildDir)) fs.mkdirSync(buildDir, { recursive: true });

// Función para crear interfaz de readline
function createReadlineInterface() {
  return readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });
}

// Función para hacer preguntas al usuario
function askQuestion(question) {
  return new Promise((resolve) => {
    const rl = createReadlineInterface();
    rl.question(question, (answer) => {
      rl.close();
      resolve(answer.trim());
    });
  });
}

// Función para obtener la versión actual del plugin
function getCurrentVersion() {
  const pluginFile = path.join(__dirname, 'ez-translate.php');
  if (!fs.existsSync(pluginFile)) {
    throw new Error('No se encontró el archivo ez-translate.php');
  }

  const content = fs.readFileSync(pluginFile, 'utf8');
  const versionMatch = content.match(/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/);

  if (!versionMatch) {
    throw new Error('No se pudo encontrar la versión en ez-translate.php');
  }

  return versionMatch[1];
}

// Función para incrementar versión
function incrementVersion(version, type = 'patch') {
  const parts = version.split('.').map(Number);

  switch (type) {
    case 'major':
      parts[0]++;
      parts[1] = 0;
      parts[2] = 0;
      break;
    case 'minor':
      parts[1]++;
      parts[2] = 0;
      break;
    case 'patch':
    default:
      parts[2]++;
      break;
  }

  return parts.join('.');
}

// Función para actualizar la versión en el archivo del plugin
function updatePluginVersion(newVersion) {
  const pluginFile = path.join(__dirname, 'ez-translate.php');
  let content = fs.readFileSync(pluginFile, 'utf8');

  // Actualizar header del plugin
  content = content.replace(
    /Version:\s*[0-9]+\.[0-9]+\.[0-9]+/,
    `Version: ${newVersion}`
  );

  // Actualizar constante PHP
  content = content.replace(
    /define\('EZ_TRANSLATE_VERSION',\s*'[0-9]+\.[0-9]+\.[0-9]+'\);/,
    `define('EZ_TRANSLATE_VERSION', '${newVersion}');`
  );

  fs.writeFileSync(pluginFile, content, 'utf8');
  console.log(`✓ Versión actualizada a ${newVersion} en ez-translate.php`);
}

// Función para minificar un archivo con esbuild
async function minifyFile(inputPath, outputPath) {
  const ext = path.extname(inputPath);

  // Crear directorio de salida si no existe
  const outputDir = path.dirname(outputPath);
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  try {
    const buildOptions = {
      entryPoints: [inputPath],
      outfile: outputPath,
      minify: true,
      bundle: false,
      sourcemap: false,
      logLevel: 'silent',
    };

    // Configurar loader según la extensión
    if (ext === '.css') {
      buildOptions.loader = { '.css': 'css' };
    } else if (ext === '.js') {
      buildOptions.loader = { '.js': 'jsx' }; // Usar jsx loader para manejar JSX
      buildOptions.target = 'es2015';
      buildOptions.jsx = 'preserve'; // Preservar JSX para WordPress
    }

    await esbuild.build(buildOptions);

    // Mostrar estadísticas de compresión
    const originalSize = fs.statSync(inputPath).size;
    const minifiedSize = fs.statSync(outputPath).size;
    const savings = ((originalSize - minifiedSize) / originalSize * 100).toFixed(1);

    console.log(`✓ Minificado: ${path.relative(__dirname, inputPath)} -> ${path.relative(__dirname, outputPath)} (${savings}% reducción)`);
  } catch (err) {
    console.error(`✗ Error minificando ${inputPath}:`, err.message);
  }
}

// Minificar todos los archivos CSS/JS dentro de un directorio
async function minifyDir(inputDir, outputDir) {
  if (!fs.existsSync(inputDir)) {
    console.log(`⚠ Directorio no encontrado: ${inputDir}`);
    return;
  }

  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  const files = fs.readdirSync(inputDir).filter(f => /\.(css|js)$/.test(f));

  if (files.length === 0) {
    console.log(`⚠ No se encontraron archivos CSS/JS en: ${inputDir}`);
    return;
  }

  console.log(`📁 Procesando directorio: ${path.relative(__dirname, inputDir)}`);

  for (const file of files) {
    const inputPath = path.join(inputDir, file);
    const outputPath = path.join(outputDir, file);
    await minifyFile(inputPath, outputPath);
  }
}

// Copiar archivos que no necesitan minificación
function copyFile(src, dest) {
  const destDir = path.dirname(dest);
  if (!fs.existsSync(destDir)) {
    fs.mkdirSync(destDir, { recursive: true });
  }
  fs.copyFileSync(src, dest);
}

// Copiar directorio recursivamente
function copyDir(src, dest, excludePatterns = []) {
  if (!fs.existsSync(src)) return;

  if (!fs.existsSync(dest)) {
    fs.mkdirSync(dest, { recursive: true });
  }

  const items = fs.readdirSync(src);

  for (const item of items) {
    const srcPath = path.join(src, item);
    const destPath = path.join(dest, item);

    // Verificar si el archivo debe ser excluido
    const shouldExclude = excludePatterns.some(pattern => {
      if (typeof pattern === 'string') {
        return item === pattern;
      } else if (pattern instanceof RegExp) {
        return pattern.test(item);
      }
      return false;
    });

    if (shouldExclude) continue;

    const stat = fs.statSync(srcPath);

    if (stat.isDirectory()) {
      copyDir(srcPath, destPath, excludePatterns);
    } else {
      copyFile(srcPath, destPath);
    }
  }
}

// Crear ZIP con los archivos del plugin
function createZip(sourceDir, outZipPath) {
  return new Promise((resolve, reject) => {
    const output = fs.createWriteStream(outZipPath);
    const archive = archiver('zip', { zlib: { level: 9 } });

    output.on('close', () => {
      const sizeInMB = (archive.pointer() / 1024 / 1024).toFixed(2);
      console.log(`📦 ZIP creado: ${path.relative(__dirname, outZipPath)} (${sizeInMB} MB)`);
      resolve();
    });

    archive.on('error', err => reject(err));

    archive.pipe(output);
    archive.directory(sourceDir, false);
    archive.finalize();
  });
}

async function build() {
  try {
    console.log('🚀 Iniciando build del plugin ez-translate...\n');

    // Preguntar sobre incremento de versión
    const currentVersion = getCurrentVersion();
    console.log(`📋 Versión actual: ${currentVersion}`);

    const shouldUpdateVersion = await askQuestion('¿Quieres incrementar la versión? (s/N): ');

    if (shouldUpdateVersion.toLowerCase() === 's' || shouldUpdateVersion.toLowerCase() === 'y') {
      console.log('\n📝 Tipos de incremento:');
      console.log('  1. patch (1.0.1 → 1.0.2) - Correcciones de bugs');
      console.log('  2. minor (1.0.1 → 1.1.0) - Nuevas características');
      console.log('  3. major (1.0.1 → 2.0.0) - Cambios importantes\n');

      const versionType = await askQuestion('Selecciona el tipo (1/2/3) [1]: ');

      let incrementType = 'patch';
      switch (versionType) {
        case '2':
          incrementType = 'minor';
          break;
        case '3':
          incrementType = 'major';
          break;
        default:
          incrementType = 'patch';
      }

      const newVersion = incrementVersion(currentVersion, incrementType);
      console.log(`\n🔄 Actualizando versión: ${currentVersion} → ${newVersion}`);

      updatePluginVersion(newVersion);
      console.log('');
    }

    // Limpiar directorio de build anterior
    if (fs.existsSync(buildDir)) {
      fs.rmSync(buildDir, { recursive: true, force: true });
    }
    fs.mkdirSync(buildDir, { recursive: true });

    // 1. Copiar estructura base del plugin
    console.log('📋 Copiando archivos base del plugin...');
    const excludePatterns = [
      '.idea',
      'node_modules',
      'dist',
      'tests',
      'memory_bank',
      '.git',
      '.gitignore',
      'build.js',
      'package.json',
      'package-lock.json',
      /\.bak$/,
      /\.md$/
    ];

    copyDir(__dirname, buildDir, excludePatterns);

    // 2. Minificar archivos CSS y JS de assets/
    console.log('\n🎨 Minificando archivos CSS y JS...');
    const buildAssetsDir = path.join(buildDir, 'assets');

    // Minificar CSS
    await minifyDir(cssDir, path.join(buildAssetsDir, 'css'));

    // Minificar JS
    await minifyDir(jsDir, path.join(buildAssetsDir, 'js'));

    // 3. Procesar archivos de src/ si existen
    if (fs.existsSync(srcDir)) {
      console.log('\n📁 Procesando archivos de src/...');
      const buildSrcDir = path.join(buildDir, 'src');

      // Procesar archivos JS en src/gutenberg/
      const srcGutenbergDir = path.join(srcDir, 'gutenberg');
      if (fs.existsSync(srcGutenbergDir)) {
        await minifyDir(srcGutenbergDir, path.join(buildSrcDir, 'gutenberg'));
      }

      // Procesar otros directorios de src/ si los hay
      const srcAdminDir = path.join(srcDir, 'admin');
      if (fs.existsSync(srcAdminDir)) {
        await minifyDir(srcAdminDir, path.join(buildSrcDir, 'admin'));
      }
    }

    // 4. Crear ZIP final
    console.log('\n📦 Creando archivo ZIP...');
    const zipPath = path.join(outDir, 'ez-translate.zip');
    await createZip(buildDir, zipPath);

    console.log('\n✅ Build completado exitosamente!');
    console.log(`📁 Archivos de build: ${path.relative(__dirname, buildDir)}`);
    console.log(`📦 Archivo ZIP: ${path.relative(__dirname, zipPath)}`);

  } catch (err) {
    console.error('\n❌ Error en build:', err.message);
    process.exit(1);
  }
}

// Ejecutar build
if (require.main === module) {
  build();
}
