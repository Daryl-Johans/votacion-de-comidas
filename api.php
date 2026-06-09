<?php
/**
 * api.php
 * Endpoint de API REST para el sistema de votación.
 * Soporta control de sesiones, autenticación (login/logout) y votación segura.
 * Controla de forma estricta que cada cuenta de usuario registrada solo emita 1 voto máximo.
 */

// Iniciar sesión para mantener estado de autenticación
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db_helper.php';

// Manejo de peticiones preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

// OBTENER ESTADO ACTUAL Y DE SESIÓN (GET)
if ($method === 'GET') {
    $foods = get_foods();
    
    $has_voted = false;
    $voted_for = null;
    $username = $_SESSION['username'] ?? null;
    
    // Si la sesión está activa, comprobar en la base de datos si ya tiene un voto registrado
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $username) {
        $users = get_users();
        foreach ($users as $user) {
            if (strcasecmp($user['username'], $username) === 0) {
                if (isset($user['voted_for']) && !empty($user['voted_for'])) {
                    $has_voted = true;
                    $voted_for = $user['voted_for'];
                }
                break;
            }
        }
    }

    // Inyectar el listado de usuarios para auditoría exclusiva de administración
    $users_list = null;
    $is_admin = $username && (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0);
    if ($is_admin) {
        $all_users = get_users();
        $users_list = [];
        foreach ($all_users as $u) {
            // Excluir la contraseña por motivos elementales de privacidad/seguridad
            $users_list[] = [
                'username' => htmlspecialchars($u['username']),
                'voted_for' => $u['voted_for'] ?? null,
                'created_at' => $u['created_at'] ?? 'N/A'
            ];
        }
    }
    
    // Devolver el estado de sesión y de votos actual, junto con la IP local real para automatizar el QR en la demo
    echo json_encode([
        'success' => true,
        'server_ip' => get_local_ip(),
        'data' => $foods,
        'user' => [
            'logged_in' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true,
            'username' => $username,
            'has_voted' => $has_voted,
            'voted_for' => $voted_for,
            'is_admin' => $is_admin
        ],
        'admin_data' => [
            'users' => $users_list
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// PROCESAR PETICIONES (POST)
if ($method === 'POST') {
    // Obtener datos del cuerpo de la petición (JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Si no es JSON, intentar leer desde $_POST
    if (!$input) {
        $input = $_POST;
    }

    $action = isset($input['action']) ? trim($input['action']) : 'vote';

    // ACCIÓN: INICIAR SESIÓN (LOGIN)
    if ($action === 'login') {
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Usuario y contraseña son requeridos.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (authenticate_user($username, $password)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;

            echo json_encode([
                'success' => true,
                'message' => '¡Sesión iniciada con éxito!',
                'username' => $username
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ACCIÓN: REGISTRARSE (REGISTER)
    if ($action === 'register') {
        $username = isset($input['username']) ? trim($input['username']) : '';
        $password = isset($input['password']) ? trim($input['password']) : '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Usuario y contraseña son requeridos.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $reg_result = register_user($username, $password);

        if ($reg_result === 'success') {
            // Autenticación automática al registrarse con éxito
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;

            echo json_encode([
                'success' => true,
                'message' => '¡Usuario registrado e inicio de sesión completado!',
                'username' => $username
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $reg_result
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ACCIÓN: CERRAR SESIÓN (LOGOUT)
    if ($action === 'logout') {
        session_unset();
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada con éxito.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ACCIÓN POR DEFECTO: REGISTRAR VOTO
    if ($action === 'vote') {
        // Verificar si el usuario está autenticado
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Acceso denegado. Debes iniciar sesión para votar.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $food_id = isset($input['food_id']) ? trim($input['food_id']) : '';

        if (empty($food_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID de comida no especificado.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Registrar el voto asociándolo al usuario autenticado
        $username = $_SESSION['username'];

        // Bloquear el voto para cuentas de administración
        if (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Las cuentas de administración no pueden emitir votos.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $updated_foods = vote_food($food_id, $username);

        // Controlar si ya había votado
        if ($updated_foods === 'already_voted') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Ya has emitido tu voto anteriormente.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($updated_foods !== false) {
            echo json_encode([
                'success' => true,
                'message' => '¡Voto registrado con éxito!',
                'data' => $updated_foods
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar el voto en el servidor.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ACCIÓN: ELIMINAR USUARIO (DELETE_USER) - EXCLUSIVO ADMIN/SUPERADMIN
    if ($action === 'delete_user') {
        $username = $_SESSION['username'] ?? '';
        $is_admin = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && 
                    (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0);

        if (!$is_admin) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Acceso denegado. No tienes permisos para eliminar usuarios.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $user_to_delete = isset($input['username_to_delete']) ? trim($input['username_to_delete']) : '';

        if (empty($user_to_delete)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Nombre de usuario a eliminar no especificado.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Impedir que se eliminen cuentas administrativas por defecto
        if (strcasecmp($user_to_delete, 'admin') === 0 || strcasecmp($user_to_delete, 'superadmin') === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No se pueden eliminar las cuentas administrativas por defecto.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $delete_success = delete_user_from_db($user_to_delete);

        if ($delete_success) {
            $updated_foods = get_foods();
            $all_users = get_users();
            $users_list = [];
            foreach ($all_users as $u) {
                $users_list[] = [
                    'username' => htmlspecialchars($u['username']),
                    'voted_for' => $u['voted_for'] ?? null,
                    'created_at' => $u['created_at'] ?? 'N/A'
                ];
            }

            echo json_encode([
                'success' => true,
                'message' => "Usuario '$user_to_delete' eliminado con éxito.",
                'data' => $updated_foods,
                'admin_data' => [
                    'users' => $users_list
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'El usuario no existe o no pudo ser eliminado.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // ACCIÓN: REINICIAR VOTACIONES (RESET_VOTES) - EXCLUSIVO ADMIN/SUPERADMIN
    if ($action === 'reset_votes') {
        $username = $_SESSION['username'] ?? '';
        $is_admin = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && 
                    (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0);

        if (!$is_admin) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Acceso denegado. No tienes permisos para reiniciar la votación.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cleaned_foods = reset_all_data();

        if ($cleaned_foods !== false) {
            echo json_encode([
                'success' => true,
                'message' => '¡Base de datos y estadísticas reiniciadas con éxito!',
                'data' => $cleaned_foods
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al reiniciar la base de datos en el servidor.'
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

// Método no permitido
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Método no permitido.'
], JSON_UNESCAPED_UNICODE);
