<?php
/**
 * db_helper.php
 * Helper de acceso a datos - Sistema de Votación Sabores de Vallegrande.
 * Usa MySQL (PDO) como backend de datos.
 * Compatible con InfinityFree (sql210.infinityfree.com) y XAMPP local.
 */

// Cargar credenciales de BD
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', ''); // Fallback XAMPP local (root sin contraseña)
}

// ══════════════════════════════════════════════════════════════
//  CONFIGURACIÓN DE CONEXIÓN
//  Detecta automáticamente si estamos en local (XAMPP) o en producción
// ══════════════════════════════════════════════════════════════

function get_db_config(): array {
    $is_local = (
        $_SERVER['HTTP_HOST'] === 'localhost' ||
        str_starts_with($_SERVER['HTTP_HOST'], '127.') ||
        str_starts_with($_SERVER['HTTP_HOST'], '192.168.')
    );

    if ($is_local) {
        // Configuración XAMPP local
        return [
            'host'     => 'localhost',
            'dbname'   => 'votacion_comidas',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ];
    } else {
        // Configuración InfinityFree (producción)
        return [
            'host'     => 'sql210.infinityfree.com',
            'dbname'   => 'if0_42133638_votacion',
            'username' => 'if0_42133638',
            'password' => DB_PASSWORD, // Definida en config.php
            'charset'  => 'utf8mb4',
        ];
    }
}

/**
 * Devuelve una conexión PDO activa.
 */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = get_db_config();
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";

    try {
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $pdo;
}

// ══════════════════════════════════════════════════════════════
//  FUNCIONES PRINCIPALES
// ══════════════════════════════════════════════════════════════

/**
 * Obtiene la lista de comidas con sus conteos de votos calculados dinámicamente.
 */
function get_foods(): array {
    $pdo = get_pdo();

    // Obtener todas las comidas
    $foods = $pdo->query("SELECT * FROM foods ORDER BY id")->fetchAll();

    // Calcular votos por comida desde la tabla de usuarios
    $counts = $pdo->query(
        "SELECT voted_for, COUNT(*) as total
         FROM users
         WHERE voted_for IS NOT NULL AND voted_for != ''
         GROUP BY voted_for"
    )->fetchAll();

    $vote_map = [];
    foreach ($counts as $row) {
        $vote_map[$row['voted_for']] = (int)$row['total'];
    }

    foreach ($foods as &$food) {
        $food['votes'] = $vote_map[$food['id']] ?? 0;
    }

    return $foods;
}

/**
 * Obtiene la lista completa de usuarios registrados.
 */
function get_users(): array {
    $pdo = get_pdo();
    return $pdo->query("SELECT * FROM users ORDER BY created_at")->fetchAll();
}

/**
 * Registra el voto de un usuario autenticado (1 voto máximo por usuario).
 *
 * @return array|string|false  Lista actualizada de comidas, 'already_voted', o false en error.
 */
function vote_food(string $food_id, string $username) {
    $username = trim($username);
    $food_id  = trim($food_id);

    if (empty($username) || empty($food_id)) return false;

    $pdo = get_pdo();

    // Validar que el food_id exista en el catálogo de comidas registradas
    $stmtFood = $pdo->prepare("SELECT id FROM foods WHERE id = ?");
    $stmtFood->execute([$food_id]);
    if (!$stmtFood->fetch()) {
        return false; // Plato inexistente: rechazar voto
    }

    // Verificar si ya votó
    $stmt = $pdo->prepare("SELECT voted_for FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) return false;
    if (!empty($user['voted_for'])) return 'already_voted';

    // Registrar el voto
    $stmt = $pdo->prepare(
        "UPDATE users SET voted_for = ?, voted_at = NOW() WHERE LOWER(username) = LOWER(?)"
    );
    $stmt->execute([$food_id, $username]);

    return $stmt->rowCount() > 0 ? get_foods() : false;
}

/**
 * Registra un nuevo usuario con contraseña encriptada BCRYPT.
 *
 * @return string 'success' o mensaje de error.
 */
function register_user(string $username, string $password): string {
    $username = trim($username);
    $password = trim($password);

    if (empty($username) || empty($password)) {
        return 'Usuario y contraseña no pueden estar vacíos.';
    }
    if (strlen($username) < 3) {
        return 'El usuario debe tener al menos 3 caracteres.';
    }
    if (strlen($password) < 4) {
        return 'La contraseña debe tener al menos 4 caracteres.';
    }

    $pdo = get_pdo();

    // Verificar si el usuario ya existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return 'El nombre de usuario ya está registrado.';
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password, voted_for, created_at)
         VALUES (?, ?, NULL, NOW())"
    );
    $stmt->execute([$username, $hash]);

    return $stmt->rowCount() > 0 ? 'success' : 'Error al registrar el usuario.';
}

/**
 * Autentica un usuario verificando su contraseña cifrada.
 */
function authenticate_user(string $username, string $password): bool {
    $username = trim($username);
    $password = trim($password);

    if (empty($username) || empty($password)) return false;

    $pdo = get_pdo();
    $stmt = $pdo->prepare("SELECT password FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    return $user && password_verify($password, $user['password']);
}

/**
 * Elimina un usuario de la base de datos.
 */
function delete_user_from_db(string $username): bool {
    $username = trim($username);
    if (empty($username)) return false;

    $pdo = get_pdo();
    $stmt = $pdo->prepare("DELETE FROM users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute([$username]);

    return $stmt->rowCount() > 0;
}

/**
 * Reinicia todas las votaciones: elimina usuarios no-admin y limpia votos de admins.
 */
function reset_all_data(): array|false {
    $pdo = get_pdo();

    try {
        $pdo->beginTransaction();

        // Eliminar usuarios que no son admin/superadmin
        $pdo->exec(
            "DELETE FROM users WHERE LOWER(username) NOT IN ('admin', 'superadmin')"
        );

        // Limpiar votos de admin/superadmin
        $pdo->exec(
            "UPDATE users SET voted_for = NULL, voted_at = NULL
             WHERE LOWER(username) IN ('admin', 'superadmin')"
        );

        $pdo->commit();
        return get_foods();
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Obtiene la IP local del servidor (para generación de QR en demo local).
 */
function get_local_ip(): string {
    $ip = '127.0.0.1';

    if (function_exists('socket_create')) {
        try {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                if (@socket_connect($sock, '8.8.8.8', 80)) {
                    if (@socket_getsockname($sock, $local_ip, $port)) {
                        if ($local_ip && $local_ip !== '127.0.0.1' && $local_ip !== '0.0.0.0') {
                            $ip = $local_ip;
                        }
                    }
                }
                @socket_close($sock);
            }
        } catch (Exception $e) {}
    }

    if ($ip === '127.0.0.1') {
        $hostname_ip = gethostbyname(gethostname());
        if ($hostname_ip && $hostname_ip !== '127.0.0.1' && filter_var($hostname_ip, FILTER_VALIDATE_IP)) {
            $ip = $hostname_ip;
        }
    }

    return $ip;
}
