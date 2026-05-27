<?php
/**
 * db_helper.php
 * Helper de acceso a datos para el sistema de votación de comidas de Vallegrande.
 * Calcula los votos dinámicamente basándose en la elección de cada usuario en data/users.json.
 * Cada usuario real registrado puede votar por un único plato (1 voto por usuario).
 */

define('VOTES_FILE', __DIR__ . '/data/votes.json');
define('USERS_FILE', __DIR__ . '/data/users.json');

/**
 * Obtiene la lista de usuarios registrados de forma segura.
 * 
 * @return array Lista de usuarios.
 */
function get_users() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }

    $fp = fopen(USERS_FILE, 'r');
    if (!$fp) {
        return [];
    }

    $users = [];
    if (flock($fp, LOCK_SH)) {
        $filesize = filesize(USERS_FILE);
        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $users = json_decode($content, true);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return is_array($users) ? $users : [];
}

/**
 * Obtiene la lista de comidas con sus conteos calculados DINÁMICAMENTE.
 * Suma las elecciones (voted_for) individuales de los usuarios registrados.
 * 
 * @return array Lista de comidas con votos actualizados.
 */
function get_foods() {
    if (!file_exists(VOTES_FILE)) {
        return [];
    }

    $fp = fopen(VOTES_FILE, 'r');
    if (!$fp) {
        return [];
    }

    $foods = [];
    if (flock($fp, LOCK_SH)) {
        $filesize = filesize(VOTES_FILE);
        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $foods = json_decode($content, true);
        }
        flock($fp, LOCK_UN);
    } else {
        $content = file_get_contents(VOTES_FILE);
        $foods = json_decode($content, true);
    }
    fclose($fp);

    if (!is_array($foods)) {
        return [];
    }

    // 1. Leer los usuarios registrados
    $users = get_users();

    // 2. Contabilizar los votos (1 por usuario registrado)
    $vote_counts = [];
    foreach ($users as $user) {
        if (isset($user['voted_for']) && !empty($user['voted_for'])) {
            $food_id = $user['voted_for'];
            if (is_array($food_id)) {
                if (count($food_id) > 0) {
                    $food_id = end($food_id);
                } else {
                    continue;
                }
            }
            if (is_string($food_id) && !empty($food_id)) {
                $vote_counts[$food_id] = isset($vote_counts[$food_id]) ? $vote_counts[$food_id] + 1 : 1;
            }
        }
    }

    // 3. Inyectar los votos reales dinámicamente en cada comida
    foreach ($foods as &$food) {
        $food['votes'] = isset($vote_counts[$food['id']]) ? $vote_counts[$food['id']] : 0;
    }

    return $foods;
}

/**
 * Registra un único voto asociándolo al usuario autenticado (1 voto máximo).
 * 
 * @param string $food_id ID del plato a votar.
 * @param string $username Nombre de usuario que realiza el voto.
 * @return array|bool Retorna la lista actualizada de comidas o false si falla o ya votó.
 */
function vote_food($food_id, $username) {
    $username = trim($username);
    if (empty($username) || empty($food_id)) {
        return false;
    }

    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $fp = fopen(USERS_FILE, 'r+');
    if (!$fp) {
        return false;
    }

    $success = false;
    $already_voted = false;

    if (flock($fp, LOCK_EX)) {
        $filesize = filesize(USERS_FILE);
        $users = [];
        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $users = json_decode($content, true);
        }

        if (is_array($users)) {
            $found = false;
            foreach ($users as &$user) {
                if (strcasecmp($user['username'], $username) === 0) {
                    // Verificar si ya tiene un voto registrado
                    $has_previous_vote = false;
                    if (isset($user['voted_for']) && !empty($user['voted_for'])) {
                        if (is_array($user['voted_for'])) {
                            $has_previous_vote = count($user['voted_for']) > 0;
                        } else {
                            $has_previous_vote = true;
                        }
                    }
                    
                    if ($has_previous_vote) {
                        $already_voted = true;
                        break;
                    }
                    
                    // Registrar el único voto
                    $user['voted_for'] = $food_id;
                    $user['voted_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }

            if ($found && !$already_voted) {
                rewind($fp);
                ftruncate($fp, 0);
                fwrite($fp, json_encode($users, JSON_PRETTY_PRINT));
                fflush($fp);
                $success = true;
            }
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    if ($already_voted) {
        return 'already_voted';
    }

    return $success ? get_foods() : false;
}

/**
 * Registra un nuevo usuario con contraseña encriptada BCRYPT.
 * 
 * @param string $username Nombre de usuario.
 * @param string $password Contraseña en texto plano.
 * @return array|string Retorna 'success' en éxito o un mensaje de error en caso de fallo.
 */
function register_user($username, $password) {
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

    $dir = dirname(USERS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fp = fopen(USERS_FILE, 'c+');
    if (!$fp) {
        return 'Error del sistema al abrir base de datos de usuarios.';
    }

    $error = null;

    if (flock($fp, LOCK_EX)) {
        $filesize = filesize(USERS_FILE);
        $users = [];
        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $users = json_decode($content, true);
        }
        if (!is_array($users)) {
            $users = [];
        }

        // Verificar si el usuario ya existe
        foreach ($users as $user) {
            if (strcasecmp($user['username'], $username) === 0) {
                $error = 'El nombre de usuario ya está registrado.';
                break;
            }
        }

        if (!$error) {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            $users[] = [
                'username' => $username,
                'password' => $hashed_password,
                'voted_for' => null, // Inicia sin voto (puede votar una sola vez)
                'created_at' => date('Y-m-d H:i:s')
            ];

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($users, JSON_PRETTY_PRINT));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
    } else {
        $error = 'El servidor está ocupado. Intenta de nuevo.';
    }

    fclose($fp);
    return $error ? $error : 'success';
}

/**
 * Autentica un usuario verificando su contraseña cifrada en BCRYPT.
 * 
 * @param string $username Nombre de usuario.
 * @param string $password Contraseña en texto plano.
 * @return bool True si es válido, False de lo contrario.
 */
function authenticate_user($username, $password) {
    $username = trim($username);
    $password = trim($password);

    if (empty($username) || empty($password)) {
        return false;
    }

    $users = get_users();
    foreach ($users as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            if (password_verify($password, $user['password'])) {
                return true;
            }
            break;
        }
    }

    return false;
}

/**
 * Restablece la base de datos de usuarios en el servidor.
 * Conserva únicamente las cuentas administrativas principales (admin y superadmin)
 * y les quita el estado de voto para que puedan volver a votar en la demo.
 * 
 * @return array Comidas con votos reiniciados a 0.
 */
function reset_all_data() {
    $fp = fopen(USERS_FILE, 'c+');
    if (!$fp) {
        return false;
    }

    $success = false;
    if (flock($fp, LOCK_EX)) {
        $filesize = filesize(USERS_FILE);
        $users = [];
        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $users = json_decode($content, true);
        }
        if (!is_array($users)) {
            $users = [];
        }

        // Cuentas administrativas por defecto con sus hashes seguros BCRYPT
        $admin_defaults = [
            'superadmin' => [
                'username' => 'superadmin',
                'password' => '$2y$10$G6es7TI4m6uvhyXTJvil8OvvjtsX7SlaZ53jWG49LhXb3VzG09PRC', // superadmin123
                'voted_for' => null,
                'created_at' => date('Y-m-d H:i:s')
            ],
            'admin' => [
                'username' => 'admin',
                'password' => '$2y$10$3/Ax.et8y2HMF5iITEq3Y.257e8qlAlXwxugBZT5EeuahDWMmlzq6', // admin123
                'voted_for' => null,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];

        $cleaned_users = [];
        
        // Conservar las cuentas de admin/superadmin que ya existan, pero limpiar sus votos
        foreach ($users as $user) {
            $uname = strtolower(trim($user['username']));
            if ($uname === 'admin' || $uname === 'superadmin') {
                $user['voted_for'] = null;
                if (isset($user['voted_at'])) unset($user['voted_at']);
                if (isset($user['last_vote_at'])) unset($user['last_vote_at']);
                $cleaned_users[$uname] = $user;
            }
        }

        // Sembrar si no existen en el archivo
        foreach ($admin_defaults as $uname => $def_user) {
            if (!isset($cleaned_users[$uname])) {
                $cleaned_users[$uname] = $def_user;
            }
        }

        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode(array_values($cleaned_users), JSON_PRETTY_PRINT));
        fflush($fp);
        $success = true;
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return $success ? get_foods() : false;
}

/**
 * Elimina un usuario de la base de datos por su nombre de usuario.
 * 
 * @param string $username Nombre de usuario a eliminar.
 * @return bool True si se eliminó con éxito, False de lo contrario.
 */
function delete_user_from_db($username) {
    $username = trim($username);
    if (empty($username)) {
        return false;
    }

    if (!file_exists(USERS_FILE)) {
        return false;
    }

    $fp = fopen(USERS_FILE, 'r+');
    if (!$fp) {
        return false;
    }

    $success = false;
    if (flock($fp, LOCK_EX)) {
        $filesize = filesize(USERS_FILE);
        $users = [];
        if ($filesize > 0) {
            $content = fread($fp, $filesize);
            $users = json_decode($content, true);
        }

        if (is_array($users)) {
            $filtered_users = [];
            $found = false;
            foreach ($users as $user) {
                if (strcasecmp($user['username'], $username) === 0) {
                    $found = true;
                    continue; // omitir este usuario
                }
                $filtered_users[] = $user;
            }

            if ($found) {
                rewind($fp);
                ftruncate($fp, 0);
                fwrite($fp, json_encode($filtered_users, JSON_PRETTY_PRINT));
                fflush($fp);
                $success = true;
            }
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    return $success;
}

