<?php
/**
 * resultados.php
 * Página dedicada a la visualización de los resultados de votación en tiempo real.
 * Carga los datos iniciales con PHP para evitar parpadeos y realiza sondeo (polling) dinámico con JS.
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

// Detectar si la cuenta activa es de administración
$is_admin = $is_logged_in && $username &&
    (strcasecmp($username, 'admin') === 0 || strcasecmp($username, 'superadmin') === 0);

// Comprobar si este usuario ya votó
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
    <title>Resultados en Vivo | Sabores de Vallegrande</title>
    
    <!-- Metaetiquetas SEO -->
    <meta name="description" content="Estadísticas y resultados en vivo de la votación oficial de la gastronomía de Vallegrande, Bolivia. Conoce los platos favoritos en tiempo real.">
    <meta name="keywords" content="Vallegrande, Comidas Tipicas, Resultados, Estadisticas, Asadito Colorado, Bolivia, Gastronomia">
    <meta name="author" content="Sabores de Vallegrande">
    
    <!-- Favicon (Emoji de comida) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📊</text></svg>">
    
    <!-- Iconos FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos del Proyecto -->
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">

    <!-- Librería QR (genera el código QR localmente en el navegador, sin internet) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>

    <!-- Barra de Sesión de Usuario / Navegación -->
    <div class="session-bar session-bar--active" id="session-bar">
        <div class="session-container">
            <a href="index.php" class="session-nav-btn" title="Ir a la pantalla de votación para elegir un plato">
                <i class="fas fa-vote-yea"></i> Votar
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
                <i class="fas fa-chart-line animate-pulse"></i> Estadísticas en Tiempo Real
            </span>
            <h1>Resultados de la Votación</h1>
            <p class="hero-subtitle">
                Monitorea el apoyo del público a las delicias tradicionales de Vallegrande. Los resultados se actualizan automáticamente en vivo.
            </p>
        </header>

        <!-- Layout Principal (Optimizado a una columna para visualización centrada de resultados) -->
        <main class="main-layout main-layout--single-column" id="main-layout">
            
            <!-- SECCIÓN CENTRAL: Resultados en Tiempo Real -->
            <section class="results-section results-section--standalone">
                <div class="results-header">
                    <h2 class="results-title"><i class="fas fa-chart-simple"></i> Estadísticas Actuales</h2>
                    <div class="total-votes-badge">
                        Total: <span id="total-votes-count"><?= $total_votes ?></span> votos
                    </div>
                </div>

                <!-- Contenedor para Notificaciones de Votación -->
                <div id="notice-container">
                    <?php if ($has_voted): ?>
                        <div class="vote-notice success">
                            <i class="fas fa-check-circle"></i>
                            <span>¡Gracias por participar! Tu voto ya ha sido registrado y cuenta en los resultados mostrados abajo.</span>
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
                    $votes = (int)($food['votes'] ?? 0);
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

                <!-- Contenedor del Gráfico de Barras -->
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
                        <span>¡Comparte y Vota desde tu Celular!</span>
                    </div>
                    <div class="qr-invite-body">
                        <div class="qr-code-wrapper">
                            <div id="qr-code-container"></div>
                        </div>
                        <p class="qr-invite-text">
                            Escanea este código QR con la cámara de tu teléfono móvil para registrarte al instante en la red local y elegir tu plato vallegrandino preferido.
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
            <p style="font-size: 0.78rem; opacity: 0.6;">HTML5 • CSS3 Moderno • JavaScript ES6 • PHP 8 (Visualización en Tiempo Real)</p>
        </footer>

    </div>

    <!-- Pasar la IP real de la red local física a JavaScript para construir el QR dinámico -->
    <script>
        window.APP_SERVER_IP = "<?= htmlspecialchars($server_ip) ?>";
    </script>

    <!-- Script de Resultados en Tiempo Real -->
    <script src="js/resultados.js?v=<?= time() ?>"></script>
</body>
</html>
