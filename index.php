<?php
/**
 * index.php
 * Página principal del Sistema de Votación de Comidas Típicas de Vallegrande.
 * Carga los datos del JSON inicial con PHP para una experiencia fluida sin parpadeos.
 * Implementa control de sesiones de usuario nativo para registrar y votar de forma personalizada.
 */

// Iniciar la sesión nativa de PHP
session_start();

require_once __DIR__ . '/db_helper.php';

// Obtener la IP real de la red local del servidor para el código QR
$server_ip = get_local_ip();


// Obtener las comidas y calcular votos totales
$foods = get_foods();
$total_votes = 0;
foreach ($foods as $food) {
    $total_votes += (int)($food['votes'] ?? 0);
}

// Determinar estado de la sesión actual
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$username = $_SESSION['username'] ?? null;

// Detectar si la cuenta activa es de administración (no puede votar)
$is_admin = $is_logged_in && $username &&
    (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0);

// Comprobar de forma aislada en el servidor si este usuario específico ya votó
$has_voted = false;
$voted_food_id = null;
if ($is_logged_in && $username && !$is_admin) {
    $users = get_users();
    foreach ($users as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            if (isset($user['voted_for']) && !empty($user['voted_for'])) {
                $has_voted = true;
                $voted_food_id = $user['voted_for'];
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sabores de Vallegrande | Votación de Comidas Típicas</title>
    
    <!-- Metaetiquetas SEO -->
    <meta name="description" content="Participa en la votación oficial de la gastronomía de Vallegrande, Bolivia. Crea tu cuenta, inicia sesión y elige tu plato favorito entre las delicias tradicionales.">
    <meta name="keywords" content="Vallegrande, Comidas Tipicas, Asadito Colorado, Bolivia, Gastronomia, Votacion, Crear Cuenta">
    <meta name="author" content="Sabores de Vallegrande">
    
    <!-- Favicon (Emoji de comida para mayor compatibilidad) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🍖</text></svg>">
    
    <!-- Iconos FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Librería QR (genera el código QR localmente en el navegador, sin internet) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    
    <!-- Estilos del Proyecto -->
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">

</head>
<body>

    <!-- Barra de Sesión de Usuario / Navegación -->
    <div class="session-bar session-bar--active" id="session-bar">
        <div class="session-container">
            <a href="resultados.php" class="session-nav-btn" title="Ver resultados de votación en tiempo real">
                <i class="fas fa-chart-bar"></i> Resultados
            </a>
            <?php if ($is_logged_in): ?>
                <span class="session-divider">|</span>
                <span class="session-user"><i class="fas fa-user-circle"></i> 👤 <?= htmlspecialchars($username) ?></span>
                <button class="session-logout-btn" id="session-logout-btn">Salir</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="app-container">
        
        <!-- Cabecera de la Aplicación -->
        <header>
            <span class="hero-badge">
                <i class="fas fa-star animate-pulse"></i> Tradición Gastronómica de Bolivia
            </span>
            <h1>Sabores de Vallegrande</h1>
            <p class="hero-subtitle">
                Descubre los manjares ancestrales de la hermosa provincia de Vallegrande. Explora sus sabores tradicionales, conoce su historia y vota por tu delicia favorita.
            </p>
        </header>

        <!-- Layout Principal (Optimizado a una columna) -->
        <main class="main-layout main-layout--single-column <?= $is_logged_in ? '' : 'auth-view' ?> <?= $has_voted ? 'already-voted' : '' ?>" id="main-layout">
            
            <!-- SECCIÓN IZQUIERDA: Tarjetas de Comida o Formulario de Login/Registro -->
            <section class="foods-section" id="main-content-section">
                
                <?php if ($is_logged_in): ?>
                    <?php if (!$is_admin): ?>
                        <!-- VISTA: Votación de Comida (Si ya inició sesión y no es admin) -->
                        <h2 class="section-title">
                            <i class="fas fa-utensils"></i> Platos Tradicionales
                        </h2>
                        
                        <!-- Contenedor para Notificaciones de Votación -->
                        <div id="notice-container"></div>
                        
                        <div class="foods-grid">
                            <?php if (empty($foods)): ?>
                                <div class="vote-notice error">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>No se pudieron cargar las comidas típicas. Por favor, verifica la base de datos JSON.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($foods as $food): ?>
                                    <article class="food-card">
                                        <div class="food-img-wrapper">
                                            <img 
                                                src="<?= htmlspecialchars($food['image']) ?>" 
                                                alt="<?= htmlspecialchars($food['name']) ?>" 
                                                class="food-img"
                                                loading="lazy"
                                            >
                                        </div>
                                        <div class="food-card-body">
                                            <div class="food-title-row">
                                                <h3 class="food-name"><?= htmlspecialchars($food['name']) ?></h3>
                                            </div>
                                            <p class="food-description"><?= htmlspecialchars($food['description']) ?></p>
                                            
                                            <button 
                                                 class="vote-button <?= ($has_voted && $voted_food_id === $food['id']) ? 'voted-choice' : '' ?>" 
                                                 data-food-id="<?= htmlspecialchars($food['id']) ?>"
                                                 aria-label="Votar por <?= htmlspecialchars($food['name']) ?>"
                                                 <?= $has_voted ? 'disabled' : '' ?>
                                             >
                                                 <?php if ($has_voted): ?>
                                                     <?php if ($voted_food_id === $food['id']): ?>
                                                         <i class="fas fa-check-circle"></i> Elegiste este plato
                                                     <?php else: ?>
                                                         <i class="fas fa-heart"></i> Voto ya realizado
                                                     <?php endif; ?>
                                                 <?php else: ?>
                                                     <i class="fas fa-heart"></i> Votar por este plato
                                                 <?php endif; ?>
                                             </button>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- PANEL DE AUDITORÍA Y CONTROL (Solo visible para admin y superadmin) -->
                    <?php 
                    $is_admin = $is_logged_in && ($username && (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0));
                    if ($is_admin): 
                        $all_users = get_users();
                    ?>
                        <div class="admin-dashboard-container" id="admin-dashboard-container">
                            <h2 class="section-title admin-title">
                                <i class="fas fa-shield-halved animate-pulse"></i> Panel de Auditoría y Control (Administración)
                            </h2>
                            
                            <!-- Barra de búsqueda en la tabla de logs -->
                            <div class="admin-search-wrapper">
                                <div class="input-icon-wrapper">
                                    <input type="text" id="admin-search-input" class="form-input search-input" placeholder="Buscar usuario o plato votado...">
                                    <i class="fas fa-magnifying-glass"></i>
                                </div>
                            </div>

                            <div class="admin-table-wrapper">
                                <table class="admin-table" id="admin-audit-table">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Fecha Registro</th>
                                            <th>Estado Votación</th>
                                            <th>Plato Elegido</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="admin-audit-tbody">
                                        <?php if (empty($all_users)): ?>
                                            <tr>
                                                <td colspan="5" class="no-records">No hay usuarios registrados en el sistema.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($all_users as $u): 
                                                $user_voted = isset($u['voted_for']) && !empty($u['voted_for']);
                                                $food_name = 'Ninguno (Aún sin votar)';
                                                $food_color = '#7f8c8d';
                                                
                                                if ($user_voted) {
                                                    // Buscar detalles de la comida
                                                    foreach ($foods as $f) {
                                                        if ($f['id'] === $u['voted_for']) {
                                                            $food_name = $f['name'];
                                                            $food_color = $f['color'];
                                                            break;
                                                        }
                                                    }
                                                }
                                            ?>
                                                <tr data-username="<?= htmlspecialchars($u['username']) ?>" data-voted-food="<?= htmlspecialchars($food_name) ?>">
                                                    <td class="col-user">👤 <?= htmlspecialchars($u['username']) ?></td>
                                                    <td class="col-date"><?= htmlspecialchars($u['created_at'] ?? 'N/A') ?></td>
                                                    <td class="col-status">
                                                        <?php if ($user_voted): ?>
                                                            <span class="badge-status success"><i class="fas fa-check-circle"></i> Votó</span>
                                                        <?php else: ?>
                                                            <span class="badge-status pending"><i class="fas fa-clock"></i> Pendiente</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="col-food">
                                                        <span class="badge-food" style="border-left: 4px solid <?= $food_color ?>; background-color: <?= $food_color ?>15;">
                                                            <?= htmlspecialchars($food_name) ?>
                                                        </span>
                                                    </td>
                                                    <td class="col-actions">
                                                        <?php if (strcasecmp($u['username'], 'admin') !== 0 && strcasecmp($u['username'], 'superadmin') !== 0): ?>
                                                            <button class="delete-user-btn" data-username="<?= htmlspecialchars($u['username']) ?>" title="Eliminar usuario y su voto">
                                                                <i class="fas fa-trash-can"></i> Eliminar
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge-system"><i class="fas fa-shield-halved"></i> Sistema</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <!-- QR de invitación visible solo para administradores -->
                        <div class="qr-invite-card" style="margin-top: 2rem;">
                            <div class="qr-invite-header">
                                <i class="fas fa-qrcode"></i>
                                <span>Código QR de Registro para Participantes</span>
                            </div>
                            <div class="qr-invite-body">
                                <div class="qr-code-wrapper">
                                    <div id="qr-code-container-admin"></div>
                                </div>
                                <p class="qr-invite-text">
                                    Comparte este código QR con los participantes para que puedan registrarse e ingresar desde su celular directamente a votar.
                                </p>
                            </div>
                        </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- VISTA: Iniciar Sesión / Registro (Si NO ha iniciado sesión) -->
                    <h2 class="section-title" style="justify-content: center; text-align: center;">
                        <i class="fas fa-lock"></i> Únete a la Votación Vallegrandina
                    </h2>
                    
                    <div class="auth-wrapper">
                        <div class="auth-card">
                            <!-- Pestañas del Formulario -->
                            <div class="auth-tabs">
                                <button class="auth-tab-btn active" id="tab-login-btn">Iniciar Sesión</button>
                                <button class="auth-tab-btn" id="tab-register-btn">Registrarse</button>
                            </div>
                            
                            <!-- Cuerpo de los Formularios -->
                            <div class="auth-body">
                                <!-- Alerta de errores dinámica -->
                                <div class="login-error-alert" id="auth-error-alert">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span id="auth-error-message">Error de validación</span>
                                </div>
                                
                                <!-- FORMULARIO 1: INICIAR SESIÓN -->
                                <form id="auth-login-form" class="auth-form active" autocomplete="off">
                                    <div class="form-group">
                                        <label for="login-username" class="form-label">Nombre de Usuario</label>
                                        <div class="input-icon-wrapper">
                                            <input type="text" id="login-username" class="form-input" placeholder="Ingresa tu usuario" required>
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="login-password" class="form-label">Contraseña</label>
                                        <div class="input-icon-wrapper">
                                            <input type="password" id="login-password" class="form-input" placeholder="Ingresa tu contraseña" required>
                                            <i class="fas fa-lock"></i>
                                            <i class="fas fa-eye toggle-password" data-target="login-password" title="Mostrar contraseña"></i>
                                        </div>
                                    </div>
                                    <button type="submit" class="login-submit-btn">
                                        <i class="fas fa-sign-in-alt"></i> Entrar y Votar
                                    </button>
                                </form>

                                <!-- FORMULARIO 2: CREAR CUENTA (REGISTRO) -->
                                <form id="auth-register-form" class="auth-form" autocomplete="off">
                                    <div class="form-group">
                                        <label for="reg-username" class="form-label">Nombre de Usuario</label>
                                        <div class="input-icon-wrapper">
                                            <input type="text" id="reg-username" class="form-input" placeholder="Crea tu usuario (mínimo 3 letras)" required>
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="reg-password" class="form-label">Contraseña</label>
                                        <div class="input-icon-wrapper">
                                            <input type="password" id="reg-password" class="form-input" placeholder="Crea tu contraseña (mínimo 4 letras)" required>
                                            <i class="fas fa-lock"></i>
                                            <i class="fas fa-eye toggle-password" data-target="reg-password" title="Mostrar contraseña"></i>
                                        </div>
                                    </div>
                                    <button type="submit" class="login-submit-btn" style="background: linear-gradient(135deg, var(--color-accent) 0%, #d4ac0d 100%); color: #000000;">
                                        <i class="fas fa-user-check"></i> Crear Cuenta y Entrar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- QR de invitación visible en la vista de login para invitar participantes -->
                    <div class="qr-invite-card" style="margin-top: 2rem;">
                        <div class="qr-invite-header">
                            <i class="fas fa-qrcode"></i>
                            <span>¡Escanea y Únete a la Votación!</span>
                        </div>
                        <div class="qr-invite-body">
                            <div class="qr-code-wrapper">
                                <div id="qr-code-container-login"></div>
                            </div>
                            <p class="qr-invite-text">
                                Escanea este código QR con la cámara de tu teléfono para registrarte al instante y votar por tu plato vallegrandino favorito.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>



        </main>

        <!-- Pie de Página -->
        <footer>
            <p>© <?= date('Y') ?> | Creado con ❤️ para exaltar la cultura de <span class="footer-accent">Vallegrande, Bolivia</span></p>
            <p style="font-size: 0.78rem; opacity: 0.6;">HTML5 • CSS3 Moderno • JavaScript ES6 • PHP 8 (Registro de Usuarios Dinámico)</p>
        </footer>

    </div>


    <!-- Pasar la IP real de la red local física a JavaScript para construir el QR dinámico -->
    <script>
        window.APP_SERVER_IP = "<?= htmlspecialchars($server_ip) ?>";
    </script>

    <!-- Script de Lógica Interactiva -->
    <script src="js/app.js?v=<?= time() ?>"></script>
</body>
</html>
