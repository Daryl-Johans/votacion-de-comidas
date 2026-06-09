<?php
/**
 * setup.php
 * Script de instalación - Ejecutar UNA SOLA VEZ en el servidor.
 * Crea las tablas y siembra los datos iniciales en MySQL.
 * Acceder via: https://sistema-votacion.infy.click/setup.php
 * ⚠️ ELIMINAR este archivo del servidor después de ejecutarlo.
 */

require_once __DIR__ . '/config.php';

$host   = 'sql210.infinityfree.com';
$dbname = 'if0_42133638_votacion';
$user   = 'if0_42133638';
$pass   = DB_PASSWORD;

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("<h2 style='color:red'>❌ Error de conexión: " . htmlspecialchars($e->getMessage()) . "</h2>");
}

$log = [];

// ── 1. TABLA: foods ───────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS foods (
        id          VARCHAR(50)   NOT NULL PRIMARY KEY,
        name        VARCHAR(200)  NOT NULL,
        description TEXT          NOT NULL,
        image       VARCHAR(200)  NOT NULL,
        color       VARCHAR(20)   NOT NULL DEFAULT '#e74c3c',
        created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$log[] = "✅ Tabla <strong>foods</strong> lista.";

// ── 2. TABLA: users ───────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id         INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(100)  NOT NULL UNIQUE,
        password   VARCHAR(255)  NOT NULL,
        voted_for  VARCHAR(50)   NULL DEFAULT NULL,
        voted_at   DATETIME      NULL DEFAULT NULL,
        created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$log[] = "✅ Tabla <strong>users</strong> lista.";

// ── 3. SEMBRAR COMIDAS ────────────────────────────────────────
$foods = [
    [
        'id'          => 'asadito',
        'name'        => 'Asadito Colorado',
        'description' => 'El rey de la gastronomía vallegrandina. Carne de cerdo adobada con urucú (achiote), ajo y pimienta, frita artesanalmente en su propia manteca hasta lograr un dorado crocante y jugoso.',
        'image'       => 'images/asadito_colorado.png',
        'color'       => '#e74c3c',
    ],
    [
        'id'          => 'escabeche',
        'name'        => 'Escabeche Vallegrandino',
        'description' => 'Una conserva exquisita de patitas de cerdo, cuero o carne de pollo, cocidos a la perfección y marinados en vinagre de manzana local con cebollas crujientes, zanahorias y especias.',
        'image'       => 'images/escabeche.png',
        'color'       => '#e67e22',
    ],
    [
        'id'          => 'huminta',
        'name'        => 'Huminta en Olla',
        'description' => 'Delicioso pastel hecho con choclo fresco (maíz tierno) molido a mano, mezclado con abundante queso criollo derretido, manteca y un toque de albahaca, cocinado lentamente al vapor.',
        'image'       => 'images/huminta.png',
        'color'       => '#f1c40f',
    ],
    [
        'id'          => 'asado_chancho',
        'name'        => 'Asado de Chancho al Horno',
        'description' => 'Tierno cerdo horneado a fuego lento durante horas, marinado en chicha vallegrandina genuina y hierbas aromáticas, con un cuero crujiente insuperable.',
        'image'       => 'images/asado_chancho.png',
        'color'       => '#d35400',
    ],
];

$stmt = $pdo->prepare("
    INSERT INTO foods (id, name, description, image, color)
    VALUES (:id, :name, :description, :image, :color)
    ON DUPLICATE KEY UPDATE
        name        = VALUES(name),
        description = VALUES(description),
        image       = VALUES(image),
        color       = VALUES(color)
");

foreach ($foods as $food) {
    $stmt->execute($food);
}
$log[] = "✅ " . count($foods) . " comidas sembradas en <strong>foods</strong>.";

// ── 4. SEMBRAR USUARIOS ADMIN ─────────────────────────────────
$admins = [
    [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_BCRYPT),
    ],
    [
        'username' => 'superadmin',
        'password' => password_hash('superadmin123', PASSWORD_BCRYPT),
    ],
];

$stmt = $pdo->prepare("
    INSERT INTO users (username, password, voted_for, created_at)
    VALUES (:username, :password, NULL, '2026-05-27 00:00:00')
    ON DUPLICATE KEY UPDATE password = VALUES(password)
");

foreach ($admins as $admin) {
    $stmt->execute($admin);
}
$log[] = "✅ Usuarios <strong>admin</strong> y <strong>superadmin</strong> creados.";

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Setup - Sabores de Vallegrande</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; background: #0f172a; color: #e2e8f0; }
  h1   { color: #f97316; }
  .ok  { background: #14532d; border-left: 4px solid #22c55e; padding: 10px 16px; margin: 8px 0; border-radius: 4px; }
  .warn{ background: #7c2d12; border-left: 4px solid #f97316; padding: 10px 16px; margin-top: 20px; border-radius: 4px; }
  a    { color: #f97316; }
</style>
</head>
<body>
<h1>🍽️ Setup - Sabores de Vallegrande</h1>
<h3>Resultado de la instalación:</h3>
<?php foreach ($log as $line): ?>
<div class="ok"><?= $line ?></div>
<?php endforeach; ?>
<div class="warn">
  <strong>⚠️ IMPORTANTE:</strong> Elimina este archivo <code>setup.php</code> del servidor por seguridad.<br><br>
  <a href="index.php">➡️ Ir al sitio</a>
</div>
</body>
</html>
