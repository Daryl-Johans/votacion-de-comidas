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
if ($is_logged_in && $username && !$is_admin) {
    $users = get_users();
    foreach ($users as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            if (isset($user['voted_for']) && !empty($user['voted_for'])) {
                $has_voted = true;
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
    
    <!-- Estilos del Proyecto -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Librería QR (genera el código QR localmente en el navegador, sin internet) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>

    <!-- Barra de Sesión de Usuario -->
    <div class="session-bar<?= $is_logged_in ? ' session-bar--active' : '' ?>" id="session-bar"><?php if ($is_logged_in): ?><div class="session-container">
                <span class="session-user"><i class="fas fa-user-circle"></i> 👤 <?= htmlspecialchars($username) ?></span>
                <button class="session-logout-btn" id="session-logout-btn">Salir</button>
            </div><?php endif; ?></div>

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

        <!-- Layout Principal -->
        <main class="main-layout <?= $is_logged_in ? '' : 'auth-view' ?> <?= $has_voted ? 'already-voted' : '' ?>" id="main-layout">
            
            <!-- SECCIÓN IZQUIERDA: Tarjetas de Comida o Formulario de Login/Registro -->
            <section class="foods-section" id="main-content-section">
                
                <?php if ($is_logged_in): ?>
                    <!-- VISTA: Votación de Comida (Si ya inició sesión) -->
                    <h2 class="section-title">
                        <i class="fas fa-utensils"></i> Platos Tradicionales
                    </h2>
                    
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
                                        
                                        <?php if ($is_admin): ?>
                                            <div class="admin-read-only-badge">
                                                <i class="fas fa-shield-alt"></i> Modo Administrador — Solo lectura
                                            </div>
                                        <?php else: ?>
                                            <button 
                                                class="vote-button" 
                                                data-food-id="<?= htmlspecialchars($food['id']) ?>"
                                                aria-label="Votar por <?= htmlspecialchars($food['name']) ?>"
                                                <?= $has_voted ? 'disabled' : '' ?>
                                            >
                                                <?php if ($has_voted): ?>
                                                    <i class="fas fa-check-circle"></i> Votación Completa
                                                <?php else: ?>
                                                    <i class="fas fa-heart"></i> Votar por este plato
                                                <?php endif; ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

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
                <?php endif; ?>
            </section>

            <!-- SECCIÓN DERECHA: Resultados en Tiempo Real -->
            <section class="results-section">
                <div class="results-header">
                    <h2 class="results-title"><i class="fas fa-chart-simple"></i> Resultados</h2>
                    <div class="total-votes-badge">
                        Total: <span id="total-votes-count"><?= $total_votes ?></span> votos
                    </div>
                </div>

                <!-- Contenedor para Notificaciones de Votación -->
                <div id="notice-container">
                    <?php if ($has_voted): ?>
                        <div class="vote-notice success">
                            <i class="fas fa-check-circle"></i>
                            <span>Ya has emitido tu voto en esta sesión. Abajo puedes ver las estadísticas actualizadas en tiempo real.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                // Encontrar plato líder, margen de ventaja y empates del lado del servidor
                $leader_name = 'Ninguno';
                $leader_votes = 0;
                $leader_color = '#7f8c8d';
                $is_tie = false;
                $second_votes = 0;

                foreach ($foods as $food) {
                    $votes = (int)$food['votes'];
                    if ($votes > $leader_votes) {
                        $second_votes = $leader_votes;
                        $leader_votes = $votes;
                        $leader_name = $food['name'];
                        $leader_color = $food['color'];
                        $is_tie = false;
                    } elseif ($votes === $leader_votes && $votes > 0) {
                        $is_tie = true;
                    } elseif ($votes > $second_votes) {
                        $second_votes = $votes;
                    }
                }

                $leader_percentage = $total_votes > 0 ? round(($leader_votes / $total_votes) * 100) : 0;
                $margin = $leader_votes - $second_votes;
                ?>

                <!-- Tarjetas de KPIs en Vivo -->
                <div class="kpi-grid">
                    <!-- Tarjeta 1: Plato Líder -->
                    <div class="kpi-card leader-kpi" id="kpi-leader-card" style="border-left: 4px solid <?= $leader_color ?>;">
                        <div class="kpi-icon" style="color: <?= $leader_color ?>;"><i class="fas fa-crown"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-title">Plato Líder</span>
                            <span class="kpi-value" id="kpi-leader-name"><?= $is_tie ? 'Empate Técnico' : htmlspecialchars($leader_name) ?></span>
                            <span class="kpi-subtitle" id="kpi-leader-stats"><?= $total_votes > 0 ? $leader_percentage . '% de los votos' : 'Sin votos aún' ?></span>
                        </div>
                    </div>

                    <!-- Tarjeta 2: Votos Totales -->
                    <div class="kpi-card total-kpi">
                        <div class="kpi-icon"><i class="fas fa-vote-yea"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-title">Votos Totales</span>
                            <span class="kpi-value" id="kpi-total-value"><?= $total_votes ?></span>
                            <span class="kpi-subtitle">Participación activa</span>
                        </div>
                    </div>

                    <!-- Tarjeta 3: Margen / Tendencia -->
                    <div class="kpi-card trend-kpi">
                        <div class="kpi-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="kpi-content">
                            <span class="kpi-title">Margen de Ventaja</span>
                            <span class="kpi-value" id="kpi-trend-value"><?= $is_tie ? 'Empatados' : ($total_votes > 0 ? '+' . $margin . ($margin == 1 ? ' voto' : ' votos') : 'N/A') ?></span>
                            <span class="kpi-subtitle" id="kpi-trend-stats"><?= $is_tie ? 'Competencia reñida' : ($total_votes > 0 ? 'Sobre el 2do puesto' : 'Esperando votos') ?></span>
                        </div>
                    </div>
                </div>

                <div class="results-chart-container">
                    <?php foreach ($foods as $food): 
                        // Calcular porcentaje del lado del servidor para carga inicial óptima
                        $percentage = $total_votes > 0 ? round(($food['votes'] / $total_votes) * 100) : 0;
                    ?>
                        <div class="chart-bar-item" id="bar-item-<?= htmlspecialchars($food['id']) ?>">
                            <div class="bar-info">
                                <span class="bar-label">
                                    <span 
                                        class="bar-indicator" 
                                        style="color: <?= htmlspecialchars($food['color']) ?>; background-color: <?= htmlspecialchars($food['color']) ?>"
                                    ></span>
                                    <?= htmlspecialchars($food['name']) ?>
                                </span>
                                <span class="bar-stats">
                                    <span class="bar-percentage"><?= $percentage ?>%</span>
                                    <span class="bar-votes-count">(<?= $food['votes'] ?> <?= $food['votes'] == 1 ? 'voto' : 'votos' ?>)</span>
                                </span>
                            </div>
                            <div class="bar-track">
                                <div 
                                    class="bar-fill" 
                                    data-percentage="<?= $percentage ?>"
                                    style="background: linear-gradient(90deg, <?= htmlspecialchars($food['color']) ?> 0%, <?= htmlspecialchars($food['color']) ?>b3 100%); width: 0%;"
                                ></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Tarjeta de Invitación y Código QR para registro móvil -->
                <div class="qr-invite-card">
                    <div class="qr-invite-header">
                        <i class="fas fa-qrcode"></i>
                        <span>¡Comparte y Vota!</span>
                    </div>
                    <div class="qr-invite-body">
                        <div class="qr-code-wrapper">
                            <div id="qr-code-container"></div>
                        </div>
                        <p class="qr-invite-text">
                            Escanea este código QR con la cámara de tu celular para ingresar al instante, registrar tu cuenta y elegir tu plato vallegrandino favorito.
                        </p>
                    </div>
                </div>

                <!-- Botón de reinicio exclusivo para el administrador en producción -->
                <?php if ($is_logged_in && (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0)): ?>
                    <button id="reset-vote-btn" class="reset-vote-btn" title="Reiniciar base de datos de votos y usuarios (Admin/Superadmin)">
                        <i class="fas fa-rotate-left"></i> Reiniciar Votación (Admin)
                    </button>
                <?php endif; ?>
            </section>

        </main>

        <!-- Pie de Página -->
        <footer>
            <p>© <?= date('Y') ?> | Creado con ❤️ para exaltar la cultura de <span class="footer-accent">Vallegrande, Bolivia</span></p>
            <p style="font-size: 0.78rem; opacity: 0.6;">HTML5 • CSS3 Moderno • JavaScript ES6 • PHP 8 (Registro de Usuarios Dinámico)</p>
        </footer>

    </div>

    <!-- Script de Lógica Interactiva -->
    <script src="js/app.js"></script>
</body>
</html>
