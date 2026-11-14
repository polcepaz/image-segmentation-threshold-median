<?php
/**
 * Trabajo Encargado 1 (TE1) — Instalador por línea de comandos.
 *
 * Uso:
 *   php install.php
 *
 * Realiza:
 *   1. Verificación de requisitos (PHP, extensiones, Python opcional).
 *   2. Creación de la base de datos (schema + seed) vía cliente mysql.
 *   3. Importación de las cinco imágenes del dataset inicial.
 */

if (PHP_SAPI !== 'cli') {
    exit("Este instalador debe ejecutarse desde la línea de comandos.\n");
}

require __DIR__ . '/config/bootstrap.php';

use App\Core\Database;
use App\Models\Image;

function out(string $msg): void { echo $msg . "\n"; }
function ok(string $msg): void { echo "  [OK] " . $msg . "\n"; }
function warn(string $msg): void { echo "  [!!] " . $msg . "\n"; }
function fail(string $msg): void { echo "  [ERROR] " . $msg . "\n"; }

out('=================================================');
out('  Trabajo Encargado 1 (TE1) — Instalador');
out('=================================================');

/* ---------------------------------------------------------------
 * 1. Requisitos
 * --------------------------------------------------------------- */
out("\n1) Verificando requisitos...");
$phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
$phpOk ? ok('PHP ' . PHP_VERSION) : fail('Se requiere PHP >= 8.2 (actual: ' . PHP_VERSION . ')');

foreach (['pdo_mysql', 'gd', 'mbstring', 'json', 'fileinfo'] as $ext) {
    extension_loaded($ext) ? ok('Extensión ' . $ext) : fail('Falta la extensión ' . $ext);
}

if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0775, true);
    @mkdir(STORAGE_ORIGINALS, 0775, true);
    @mkdir(STORAGE_PROCESSED, 0775, true);
    @mkdir(STORAGE_REPORTS, 0775, true);
    @mkdir(STORAGE_LOGS, 0775, true);
}
is_writable(STORAGE_PATH) ? ok('Storage escribible') : fail('Storage no escribible: ' . STORAGE_PATH);

/* ---------------------------------------------------------------
 * 2. Base de datos
 * --------------------------------------------------------------- */
out("\n2) Configurando base de datos (MySQL)...");

$mysqlBin = findMysql();
if ($mysqlBin === null) {
    warn('No se encontró el cliente mysql. Se intentará vía PDO.');
}

$schemaFile = __DIR__ . '/database/schema.sql';
$seedFile = __DIR__ . '/database/seed.sql';

if ($mysqlBin !== null) {
    $pass = DB_PASS !== '' ? ' -p' . escapeshellarg(DB_PASS) : '';
    $base = sprintf(
        '%s -h %s -P %s -u %s%s',
        escapeshellarg($mysqlBin),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_PORT),
        escapeshellarg(DB_USER),
        $pass
    );

    exec($base . ' < ' . escapeshellarg($schemaFile) . ' 2>&1', $schemaOut, $schemaCode);
    if ($schemaCode !== 0) {
        fail('Error ejecutando schema.sql:');
        foreach ($schemaOut as $line) { echo "    " . $line . "\n"; }
        exit(1);
    }
    ok('schema.sql ejecutado');

    exec($base . ' ' . escapeshellarg(DB_NAME) . ' < ' . escapeshellarg($seedFile) . ' 2>&1', $seedOut, $seedCode);
    if ($seedCode !== 0) {
        fail('Error ejecutando seed.sql:');
        foreach ($seedOut as $line) { echo "    " . $line . "\n"; }
        exit(1);
    }
    ok('seed.sql ejecutado');
} else {
    try {
        runSqlFile($schemaFile);
        ok('schema.sql ejecutado (PDO)');
        runSqlFile($seedFile);
        ok('seed.sql ejecutado (PDO)');
    } catch (\Throwable $e) {
        fail('Error ejecutando SQL: ' . $e->getMessage());
        exit(1);
    }
}

/* ---------------------------------------------------------------
 * 3. Importar dataset inicial
 * --------------------------------------------------------------- */
out("\n3) Importando dataset inicial...");

try {
    $dataset = [
        ['file' => '01_1_original.png', 'name' => 'OCT original', 'modality' => 'OCT', 'desc' => 'Imagen OCT del dataset inicial.'],
        ['file' => 'P2_IMG0218.png',   'name' => 'Corte TC P2', 'modality' => 'TC',  'desc' => 'Corte de TC de tórax (P2).'],
        ['file' => 'P6_IMG0175.png',   'name' => 'Corte TC P6', 'modality' => 'TC',  'desc' => 'Corte de TC de tórax (P6).'],
        ['file' => 'P7_IMG0183.png',   'name' => 'Corte TC P7', 'modality' => 'TC',  'desc' => 'Corte de TC de tórax (P7).'],
        ['file' => 'P10_IMG0106.png',  'name' => 'Corte TC P10', 'modality' => 'TC', 'desc' => 'Corte de TC de tórax (P10).'],
    ];

    $sourceDir = __DIR__ . '/doc';
    $imageModel = new Image();
    $imported = 0;
    $skipped = 0;

    foreach ($dataset as $d) {
        $src = $sourceDir . '/' . $d['file'];
        if (!is_file($src)) {
            warn('No se encontró ' . $d['file'] . ' (omitida)');
            continue;
        }

        // Evitar duplicados por nombre.
        $existing = Database::fetch("SELECT id FROM images WHERE name = ?", [$d['name']]);
        if ($existing) {
            $skipped++;
            continue;
        }

        $dir = STORAGE_ORIGINALS;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'seed_' . date('YmdHis') . '_' . $d['file'];
        $dest = $dir . '/' . $filename;
        copy($src, $dest);

        $size = filesize($dest);
        $info = getimagesize($dest);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($dest);

        $id = $imageModel->create([
            'name'          => $d['name'],
            'description'   => $d['desc'],
            'modality'      => $d['modality'],
            'filename'      => $filename,
            'original_path' => $dest,
            'mime_type'     => $mime,
            'file_size'     => (int) $size,
            'width'         => (int) ($info[0] ?? 0),
            'height'        => (int) ($info[1] ?? 0),
        ]);

        Database::insert('image_metadata', [
            'image_id'    => $id,
            'color_space' => 'RGB',
            'bits_per_sample' => (int) ($info['bits'] ?? 8),
        ]);

        ok('Importada "' . $d['name'] . '" (id ' . $id . ')');
        $imported++;
    }

    out("    Importadas: {$imported}, omitidas (existentes): {$skipped}");
} catch (\Throwable $e) {
    fail('Error importando dataset: ' . $e->getMessage());
}

/* ---------------------------------------------------------------
 * 4. Verificación del motor Python (opcional)
 * --------------------------------------------------------------- */
out("\n4) Motor de procesamiento...");
$python = findPython();
if ($python !== null) {
    ok('Python detectado: ' . $python);
    // Comprobar OpenCV.
    exec(escapeshellarg($python) . ' -c "import cv2" 2>&1', $cvOut, $cvCode);
    if ($cvCode === 0) {
        ok('OpenCV disponible (se usará Python/OpenCV).');
    } else {
        warn('OpenCV no instalado. Se usará el motor GD (fallback).');
        warn('Instale dependencias: pip install -r python/requirements.txt');
    }
} else {
    warn('Python no encontrado. Se usará el motor GD (fallback).');
}

out("\n=================================================");
out('  Instalación completada.');
out('  Acceda desde su navegador a: http://localhost/web14/');
out('=================================================');

/* ---------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------- */
function findMysql(): ?string
{
    foreach ([
        'C:\\xampp\\mysql\\bin\\mysql.exe',
        'mysql',
    ] as $cand) {
        if (str_contains($cand, '\\') || str_contains($cand, '/')) {
            if (is_file($cand)) {
                return $cand;
            }
        } else {
            $where = trim((string) shell_exec('where ' . $cand . ' 2>NUL'));
            if ($where !== '') {
                return preg_split('/\r\n|\r|\n/', $where)[0];
            }
        }
    }
    return null;
}

function findPython(): ?string
{
    if (PYTHON_BIN !== '') {
        return is_file(PYTHON_BIN) ? PYTHON_BIN : null;
    }
    foreach (['python', 'python3', 'py'] as $cand) {
        $where = trim((string) shell_exec('where ' . $cand . ' 2>NUL'));
        if ($where !== '') {
            return preg_split('/\r\n|\r|\n/', $where)[0];
        }
    }
    return null;
}

function runSqlFile(string $file): void
{
    $sql = file_get_contents($file);
    // Crear la base de datos antes de conectar a ella.
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    // Eliminar sentencias USE / CREATE DATABASE para ejecución secuencial.
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
    $sql = preg_replace('/USE\s+\w+;/i', '', $sql);

    foreach (splitStatements($sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }
}

function splitStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($quote !== null) {
            $buffer .= $c;
            if ($c === $quote) {
                $quote = null;
            }
        } elseif ($c === "'" || $c === '"') {
            $quote = $c;
            $buffer .= $c;
        } elseif ($c === ';') {
            $statements[] = $buffer;
            $buffer = '';
        } else {
            $buffer .= $c;
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }
    return $statements;
}
