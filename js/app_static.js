/**
 * app_static.js
 * Lógica interactiva estática con soporte de Login, Registro y Votación Relacional.
 * Los votos se calculan dinámicamente contando los votos individuales de los usuarios reales.
 */

// Datos de semilla iniciales (votos limpios en 0 por defecto)
const INITIAL_FOODS = [
  {
    "id": "asadito",
    "name": "Asadito Colorado",
    "description": "El rey de la gastronomía vallegrandina. Carne de cerdo adobada con urucú (achiote), ajo y pimienta, frita artesanalmente en su propia manteca hasta lograr un dorado crocante y jugoso.",
    "image": "images/asadito_colorado.jpg",
    "votes": 0,
    "color": "#e74c3c"
  },
  {
    "id": "bistec",
    "name": "Bistec Vallegrandino",
    "description": "Un jugoso filete de res a la plancha, sazonado con las especias ancestrales de Vallegrande: ajo, comino y orégano, acompañado de papas doradas y ensalada fresca de la tierra vallegrandina.",
    "image": "images/bistec_vallegrandino.png",
    "votes": 0,
    "color": "#c0392b"
  },
  {
    "id": "huminta",
    "name": "Huminta en Olla",
    "description": "Delicioso pastel hecho con choclo fresco (maíz tierno) molido a mano, mezclado con abundante queso criollo derretido, manteca y un toque de albahaca, cocinado lentamente al vapor.",
    "image": "images/huminta.jpg",
    "votes": 0,
    "color": "#f1c40f"
  },
  {
    "id": "asado_chancho",
    "name": "Asado de Chancho al Horno",
    "description": "Tierno cerdo horneado a fuego lento durante horas, marinado en chicha vallegrandina genuina y hierbas aromáticas, con un cuero crujiente insuperable.",
    "image": "images/asado_chancho.png",
    "votes": 0,
    "color": "#d35400"
  }
];

document.addEventListener('DOMContentLoaded', () => {
    // 1. Cargar base de datos de comidas
    let db = JSON.parse(localStorage.getItem('vallegrande_db'));
    if (!db || db.length !== 4) {
        db = INITIAL_FOODS;
        localStorage.setItem('vallegrande_db', JSON.stringify(db));
    }

    // Inicializar base de datos de usuarios simulados
    let usersDb = JSON.parse(localStorage.getItem('vallegrande_users'));
    if (!usersDb || usersDb.length === 0) {
        usersDb = [
            { "username": "superadmin", "password": "superadmin123", "voted_for": null, "created_at": "2026-05-27 12:00:00" },
            { "username": "admin", "password": "admin123", "voted_for": null, "created_at": "2026-05-27 12:05:00" }
        ];
        localStorage.setItem('vallegrande_users', JSON.stringify(usersDb));
    } else {
        // Asegurarse de que al menos admin y superadmin estén en el array para que puedan loguearse
        const hasAdmin = usersDb.some(u => u.username.toLowerCase() === 'admin');
        const hasSuperadmin = usersDb.some(u => u.username.toLowerCase() === 'superadmin');
        let modifications = false;
        if (!hasSuperadmin) {
            usersDb.push({ "username": "superadmin", "password": "superadmin123", "voted_for": null, "created_at": "2026-05-27 12:00:00" });
            modifications = true;
        }
        if (!hasAdmin) {
            usersDb.push({ "username": "admin", "password": "admin123", "voted_for": null, "created_at": "2026-05-27 12:05:00" });
            modifications = true;
        }
        if (modifications) {
            localStorage.setItem('vallegrande_users', JSON.stringify(usersDb));
        }
    }

    // Estado local de sesión
    let currentUser = {
        loggedIn: sessionStorage.getItem('static_logged_in') === '1',
        username: sessionStorage.getItem('static_username') || null
    };

    // Referencias del DOM
    const mainContentSection = document.getElementById('main-content-section');
    const resultsChartContainer = document.getElementById('results-chart-container');
    const totalVotesElement = document.getElementById('total-votes-count');
    const noticeContainer = document.getElementById('notice-container');
    const resetVoteBtn = document.getElementById('reset-vote-btn');
    const mainLayout = document.getElementById('main-layout');
    const sessionBar = document.getElementById('session-bar');

    // Generar Código QR de Registro e Invitación Dinámico con autodetección inteligente de IP de red local
    const qrImage = document.getElementById('qr-code-image');
    if (qrImage) {
        let currentUrl = window.location.href;

        // Función para renderizar el código QR de manera limpia
        const updateQrCode = (url) => {
            qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1e130c&bgcolor=ffffff&qzone=1&data=${encodeURIComponent(url)}`;
        };

        // Renderizado inicial con la URL local de la PC
        updateQrCode(currentUrl);

        // Si la URL es localhost, 127.0.0.1 o ::1, intentar autodetectar la IP física real mediante el backend
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname === '[::1]') {
            fetch('api.php')
                .then(response => response.json())
                .then(result => {
                    if (result && result.success && result.server_ip) {
                        try {
                            const urlObj = new URL(window.location.href);
                            urlObj.hostname = result.server_ip;
                            currentUrl = urlObj.toString();
                            
                            // Actualizar QR automáticamente con la IP real detectada en la red física
                            updateQrCode(currentUrl);
                            
                            // Mostrar mensaje de éxito discreto en español
                            const inviteText = document.querySelector('.qr-invite-text');
                            if (inviteText) {
                                inviteText.innerHTML = `✅ <strong>Acceso Móvil Automático Activo:</strong> Tu servidor de red local física está en <code>${result.server_ip}</code>. Escanea este código QR con tu celular Poco X7 para acceder directamente.`;
                            }
                        } catch (e) {
                            console.warn("Error al parsear URL con la IP autodetectada:", e);
                        }
                    }
                })
                .catch(err => {
                    console.log("No se pudo detectar la IP de red local de forma automática en modo estático (API offline). Usando fallback local.");
                    try {
                        // IP de red local física detectada en el sistema
                        const LOCAL_NETWORK_IP_FALLBACK = '10.171.205.158';
                        const urlObj = new URL(window.location.href);
                        urlObj.hostname = LOCAL_NETWORK_IP_FALLBACK;
                        currentUrl = urlObj.toString();
                        
                        // Forzar actualización del código QR con la IP local
                        updateQrCode(currentUrl);
                        
                        const inviteText = document.querySelector('.qr-invite-text');
                        if (inviteText) {
                            inviteText.innerHTML = `✅ <strong>Acceso Móvil Activo (Red Local):</strong> Tu servidor está en <code>${LOCAL_NETWORK_IP_FALLBACK}</code>. Escanea este código QR con tu celular para acceder directamente.`;
                        }
                    } catch (e) {
                        console.warn("Error al inyectar fallback de IP local:", e);
                    }
                });
        } else if (window.location.protocol === 'file:') {
            // Caso especial: abriendo index.html directamente como archivo local en el disco
            const inviteText = document.querySelector('.qr-invite-text');
            if (inviteText && !document.getElementById('change-ip-link')) {
                const originalText = inviteText.innerHTML;
                inviteText.innerHTML = `⚠️ <strong>Servidor local inactivo:</strong> Estás abriendo el archivo de forma estática (<code>file://</code>). Para escanear con tu celular Poco X7, abre el proyecto desde XAMPP en tu navegador (ej. <code>http://localhost/votacion%20de%20comidas/</code>).<br><br>👉 <a href="#" id="change-ip-link" style="color:var(--color-primary);text-decoration:underline; font-weight:bold;">O ingresa la URL de XAMPP de tu computadora manualmente</a><br><br>` + originalText;
                
                document.getElementById('change-ip-link').addEventListener('click', (e) => {
                    e.preventDefault();
                    let sugerencia = `http://192.168.1.15/`;
                    if (window.location.pathname.includes('mis_proyectos')) {
                        const partes = window.location.pathname.split('mis_proyectos');
                        sugerencia = `http://192.168.1.15/mis_proyectos${partes[1]}`;
                    }
                    
                    const userUrl = prompt("Ingresa la URL completa correcta de tu servidor Apache:", sugerencia);
                    if (userUrl && userUrl.trim() !== "") {
                        currentUrl = userUrl.trim();
                        updateQrCode(currentUrl);
                        inviteText.innerHTML = `✅ <strong>QR actualizado correctamente.</strong><br>URL: <code>${currentUrl}</code><br><br>Escanea este código con tu celular Poco X7 para votar.`;
                    }
                });
            }
        }
    }

    // Función auxiliar para determinar de forma relacional si el usuario activo ya votó
    function hasCurrentUserVoted() {
        if (!currentUser.loggedIn || !currentUser.username) {
            return false;
        }
        const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];
        const user = localUsers.find(u => u.username.toLowerCase() === currentUser.username.toLowerCase());
        return user && user.voted_for ? true : false;
    }

    // Función auxiliar para detectar si la cuenta activa es de administración
    function isAdminUser() {
        if (!currentUser.loggedIn || !currentUser.username) return false;
        const name = currentUser.username.toLowerCase();
        return name === 'admin' || name === 'superadmin';
    }

    // 2. Renderizar componentes iniciales
    renderLayout();
    renderChartContainer(db);
    updateResultsUI(db);
    renderSessionBar();
    
    // Animar las barras en carga inicial
    setTimeout(animateProgressBars, 150);

    // Comprobar estado de voto previo de forma aislada para el usuario actual
    if (hasCurrentUserVoted()) {
        setAlreadyVotedState();
    }

    // 3. Sondeo en tiempo real local (Polling) cada 3 segundos
    // Lee constantemente localStorage para reflejar votos de otras pestañas al instante
    setInterval(() => {
        updateResultsUI(db);
    }, 3000);

    // 4. Botón de reinicio completo (limpia todo y borra base de datos)
    if (resetVoteBtn) {
        resetVoteBtn.addEventListener('click', () => {
            localStorage.clear();
            sessionStorage.clear();
            window.location.reload();
        });
    }

    // 5. Usar delegación de eventos para alternar visibilidad de contraseña (Toggle Password)
    document.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('toggle-password')) {
            const targetId = e.target.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            if (passwordInput) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    e.target.classList.remove('fa-eye');
                    e.target.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    e.target.classList.remove('fa-eye-slash');
                    e.target.classList.add('fa-eye');
                }
            }
        }
    });

    /**
     * Dibuja la pantalla según el estado de la sesión (Login o Platos)
     */
    function renderLayout() {
        if (currentUser.loggedIn) {
            mainLayout.classList.remove('auth-view');
            
            const isAdmin = currentUser.username && 
                            (currentUser.username.toLowerCase() === 'admin' || 
                             currentUser.username.toLowerCase() === 'superadmin');
            
            mainContentSection.innerHTML = `
                <h2 class="section-title">
                    <i class="fas fa-utensils"></i> Platos Tradicionales
                </h2>
                <div class="foods-grid" id="foods-grid-container"></div>
                ${isAdmin ? `
                    <div class="admin-dashboard-container" id="admin-dashboard-container">
                        <h2 class="section-title admin-title">
                            <i class="fas fa-shield-halved animate-pulse"></i> Panel de Auditoría y Control (Administración)
                        </h2>
                        
                        <!-- Barra de búsqueda -->
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
                                    <!-- Dinámico por JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : ''}
            `;
            
            renderFoodCards(db);
            setupVoteButtons();

            if (isAdmin) {
                renderAdminAuditUI();
                setupAdminEvents();
            }

            if (hasCurrentUserVoted()) {
                setAlreadyVotedState();
            }
        } else {
            mainLayout.classList.add('auth-view');
            
            mainContentSection.innerHTML = `
                <h2 class="section-title section-title--centered">
                    <i class="fas fa-lock"></i> Únete a la Votación Vallegrandina
                </h2>
                
                <div class="auth-wrapper">
                    <div class="auth-card">
                        <!-- Pestañas -->
                        <div class="auth-tabs">
                            <button class="auth-tab-btn active" id="tab-login-btn">Iniciar Sesión</button>
                            <button class="auth-tab-btn" id="tab-register-btn">Registrarse</button>
                        </div>
                        
                        <!-- Cuerpo -->
                        <div class="auth-body">
                            <div class="login-error-alert" id="auth-error-alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="auth-error-message">Error de validación</span>
                            </div>
                            
                            <!-- Formulario de Login -->
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

                            <!-- Formulario de Registro -->
                            <form id="auth-register-form" class="auth-form" autocomplete="off">
                                <div class="form-group">
                                    <label for="reg-username" class="form-label">Nombre de Usuario</label>
                                    <div class="input-icon-wrapper">
                                        <input type="text" id="reg-username" class="form-input" placeholder="Elige tu usuario (mínimo 3 letras)" required>
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="reg-password" class="form-label">Contraseña</label>
                                    <div class="input-icon-wrapper">
                                        <input type="password" id="reg-password" class="form-input" placeholder="Elige tu contraseña (mínimo 4 letras)" required>
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
            `;
            
            setupAuthEvents();
        }
    }

    /**
     * Vincula los listeners de eventos para el Login / Registro integrado.
     */
    function setupAuthEvents() {
        const tabLoginBtn = document.getElementById('tab-login-btn');
        const tabRegisterBtn = document.getElementById('tab-register-btn');
        const authLoginForm = document.getElementById('auth-login-form');
        const authRegisterForm = document.getElementById('auth-register-form');

        if (tabLoginBtn && tabRegisterBtn) {
            tabLoginBtn.addEventListener('click', () => {
                tabLoginBtn.classList.add('active');
                tabRegisterBtn.classList.remove('active');
                authLoginForm.classList.add('active');
                authRegisterForm.classList.remove('active');
                hideAuthError();
            });

            tabRegisterBtn.addEventListener('click', () => {
                tabRegisterBtn.classList.add('active');
                tabLoginBtn.classList.remove('active');
                authRegisterForm.classList.add('active');
                authLoginForm.classList.remove('active');
                hideAuthError();
            });
        }

        // Envío de Login
        if (authLoginForm) {
            authLoginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const username = document.getElementById('login-username').value.trim();
                const password = document.getElementById('login-password').value.trim();
                
                hideAuthError();

                const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];
                const matchedUser = localUsers.find(u => u.username.toLowerCase() === username.toLowerCase());

                if (matchedUser && matchedUser.password === password) {
                    currentUser.loggedIn = true;
                    currentUser.username = matchedUser.username;
                    
                    sessionStorage.setItem('static_logged_in', '1');
                    sessionStorage.setItem('static_username', matchedUser.username);

                    renderSessionBar();
                    renderLayout();
                    
                    if (hasCurrentUserVoted()) {
                        setAlreadyVotedState();
                        showNotice(`¡Bienvenido de nuevo, <strong>${matchedUser.username}</strong>! Ya has registrado tu voto.`, 'info');
                    } else {
                        showNotice(`¡Bienvenido, <strong>${matchedUser.username}</strong>! Ya puedes votar por tu plato favorito.`, 'success');
                    }
                } else {
                    showAuthError('Usuario o contraseña incorrectos. Regístrate si aún no tienes cuenta.');
                }
            });
        }

        // Envío de Registro
        if (authRegisterForm) {
            authRegisterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const username = document.getElementById('reg-username').value.trim();
                const password = document.getElementById('reg-password').value.trim();
                
                hideAuthError();

                if (username.length < 3) {
                    showAuthError('El usuario debe tener al menos 3 caracteres.');
                    return;
                }

                if (password.length < 4) {
                    showAuthError('La contraseña debe tener al menos 4 caracteres.');
                    return;
                }

                const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];
                const userExists = localUsers.some(u => u.username.toLowerCase() === username.toLowerCase());

                if (userExists) {
                    showAuthError('El nombre de usuario ya está registrado.');
                    return;
                }

                // Guardar usuario nuevo con su voto vacío
                localUsers.push({ username, password, voted_for: null });
                localStorage.setItem('vallegrande_users', JSON.stringify(localUsers));

                currentUser.loggedIn = true;
                currentUser.username = username;
                
                sessionStorage.setItem('static_logged_in', '1');
                sessionStorage.setItem('static_username', username);

                renderSessionBar();
                renderLayout();
                showNotice(`¡Cuenta creada con éxito! Bienvenido/a, <strong>${username}</strong>. Ya puedes votar por tu plato preferido.`, 'success');
            });
        }
    }

    function showAuthError(msg) {
        const authErrorAlert = document.getElementById('auth-error-alert');
        const authErrorMessage = document.getElementById('auth-error-message');
        if (authErrorAlert && authErrorMessage) {
            authErrorMessage.textContent = msg;
            authErrorAlert.style.display = 'flex';
        }
    }

    function hideAuthError() {
        const authErrorAlert = document.getElementById('auth-error-alert');
        if (authErrorAlert) {
            authErrorAlert.style.display = 'none';
        }
    }

    /**
     * Dibuja dinámicamente las tarjetas de comidas.
     */
    function renderFoodCards(foodsList) {
        const foodsGridContainer = document.getElementById('foods-grid-container');
        if (!foodsGridContainer) return;

        foodsGridContainer.innerHTML = foodsList.map(food => {
            const voteControl = isAdminUser()
                ? `<div class="admin-read-only-badge"><i class="fas fa-shield-alt"></i> Modo Administrador — Solo lectura</div>`
                : `<button
                        class="vote-button"
                        data-food-id="${food.id}"
                        aria-label="Votar por ${food.name}"
                    >
                        <i class="fas fa-heart"></i> Votar por este plato
                    </button>`;

            return `
            <article class="food-card">
                <div class="food-img-wrapper">
                    <img 
                        src="${food.image}" 
                        alt="${food.name}" 
                        class="food-img"
                        loading="lazy"
                    >
                </div>
                <div class="food-card-body">
                    <div class="food-title-row">
                        <h3 class="food-name">${food.name}</h3>
                    </div>
                    <p class="food-description">${food.description}</p>
                    ${voteControl}
                </div>
            </article>`;
        }).join('');
    }

    /**
     * Asocia los disparadores de votación vinculándolos al perfil de usuario actual.
     * Registra un único voto y deshabilita los botones.
     */
    function setupVoteButtons() {
        // Los administradores no pueden votar: se omite el registro de eventos
        if (isAdminUser()) return;

        const voteButtons = document.querySelectorAll('.vote-button');
        voteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const foodId = button.getAttribute('data-food-id');

                // Si ya votó, abortar (protección de interfaz)
                if (hasCurrentUserVoted()) {
                    setAlreadyVotedState();
                    return;
                }

                // 1. Obtener usuarios registrados y guardar el voto único en la cuenta activa
                const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];
                const updatedUsers = localUsers.map(u => {
                    if (u.username.toLowerCase() === currentUser.username.toLowerCase()) {
                        return { ...u, voted_for: foodId };
                    }
                    return u;
                });
                
                // 2. Persistir base de datos de usuarios locales
                localStorage.setItem('vallegrande_users', JSON.stringify(updatedUsers));

                // 3. Recalcular estadísticas dinámicamente basándose exclusivamente en usuarios registrados
                updateResultsUI(db);
                
                // 4. Inhabilitar la UI en caliente de inmediato
                setAlreadyVotedState(true);
            });
        });
    }

    /**
     * Dibuja la estructura base de los gráficos de resultados.
     */
    function renderChartContainer(foodsList) {
        resultsChartContainer.innerHTML = foodsList.map(food => `
            <div class="chart-bar-item" id="bar-item-${food.id}">
                <div class="bar-info">
                    <span class="bar-label">
                        <span 
                            class="bar-indicator" 
                            style="color: ${food.color}; background-color: ${food.color}"
                        ></span>
                        ${food.name}
                    </span>
                    <span class="bar-stats">
                        <span class="bar-percentage">0%</span>
                        <span class="bar-votes-count">(0 votos)</span>
                    </span>
                </div>
                <div class="bar-track">
                    <div 
                        class="bar-fill" 
                        data-percentage="0"
                        style="background: linear-gradient(90deg, ${food.color} 0%, ${food.color}b3 100%); width: 0%;"
                    ></div>
                </div>
            </div>
        `).join('');
    }

    /**
     * Calcula los porcentajes y barras de resultados contando dinámicamente
     * ÚNICAMENTE los votos de los usuarios registrados del local storage.
     */
    function updateResultsUI(foodsList) {
        // 1. Obtener usuarios registrados
        const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];
        
        // 2. Contabilizar los votos activos
        const voteCounts = {};
        localUsers.forEach(u => {
            if (u.voted_for) {
                if (Array.isArray(u.voted_for)) {
                    if (u.voted_for.length > 0) {
                        const lastVote = u.voted_for[u.voted_for.length - 1];
                        voteCounts[lastVote] = (voteCounts[lastVote] || 0) + 1;
                    }
                } else {
                    voteCounts[u.voted_for] = (voteCounts[u.voted_for] || 0) + 1;
                }
            }
        });

        // 3. Calcular total de votos reales de usuarios registrados
        let totalVotes = 0;
        foodsList.forEach(food => {
            food.votes = voteCounts[food.id] || 0;
            totalVotes += food.votes;
        });
        
        totalVotesElement.textContent = totalVotes;

        // --- CÁLCULO DE KPIS EN LA DEMO ESTÁTICA ---
        let leaderName = 'Ninguno';
        let leaderVotes = 0;
        let leaderColor = '#7f8c8d';
        let isTie = false;
        let secondVotes = 0;

        foodsList.forEach(food => {
            const votes = parseInt(food.votes) || 0;
            if (votes > leaderVotes) {
                secondVotes = leaderVotes;
                leaderVotes = votes;
                leaderName = food.name;
                leaderColor = food.color;
                isTie = false;
            } else if (votes === leaderVotes && votes > 0) {
                isTie = true;
            } else if (votes > secondVotes) {
                secondVotes = votes;
            }
        });

        const leaderPercentage = totalVotes > 0 ? Math.round((leaderVotes / totalVotes) * 100) : 0;
        const margin = leaderVotes - secondVotes;

        // Función interna para animar la transición con efecto pulse
        function animateKPI(el, value) {
            if (!el) return;
            if (el.textContent !== String(value)) {
                el.textContent = value;
                el.classList.remove('value-pulse');
                void el.offsetWidth; // forzar reflow
                el.classList.add('value-pulse');
            }
        }

        // Referencias a los componentes de KPIs
        const kpiLeaderCard = document.getElementById('kpi-leader-card');
        const kpiLeaderName = document.getElementById('kpi-leader-name');
        const kpiLeaderStats = document.getElementById('kpi-leader-stats');
        const kpiTotalValue = document.getElementById('kpi-total-value');
        const kpiTrendValue = document.getElementById('kpi-trend-value');
        const kpiTrendStats = document.getElementById('kpi-trend-stats');

        if (kpiLeaderName) {
            animateKPI(kpiLeaderName, isTie ? 'Empate Técnico' : leaderName);
        }
        if (kpiLeaderStats) {
            animateKPI(kpiLeaderStats, totalVotes > 0 ? `${leaderPercentage}% de los votos` : 'Sin votos aún');
        }
        if (kpiTotalValue) {
            animateKPI(kpiTotalValue, totalVotes);
        }
        if (kpiTrendValue) {
            const trendText = isTie ? 'Empatados' : (totalVotes > 0 ? `+${margin} ${margin === 1 ? 'voto' : 'votos'}` : 'N/A');
            animateKPI(kpiTrendValue, trendText);
        }
        if (kpiTrendStats) {
            const trendStatsText = isTie ? 'Competencia reñida' : (totalVotes > 0 ? 'Sobre el 2do puesto' : 'Esperando votos');
            animateKPI(kpiTrendStats, trendStatsText);
        }

        // Sintonizar color del líder en la tarjeta principal de métricas
        if (kpiLeaderCard) {
            kpiLeaderCard.style.borderLeft = `4px solid ${leaderColor}`;
            const crownIcon = kpiLeaderCard.querySelector('.kpi-icon');
            if (crownIcon) {
                crownIcon.style.color = leaderColor;
            }
        }

        // 4. Actualizar barras y porcentajes en base al conteo real
        foodsList.forEach(food => {
            const barItem = document.getElementById(`bar-item-${food.id}`);
            if (barItem) {
                const percentage = totalVotes > 0 ? Math.round((food.votes / totalVotes) * 100) : 0;
                
                const percentText = barItem.querySelector('.bar-percentage');
                if (percentText) percentText.textContent = `${percentage}%`;

                const countText = barItem.querySelector('.bar-votes-count');
                if (countText) countText.textContent = `(${food.votes} ${food.votes === 1 ? 'voto' : 'votos'})`;

                const barFill = barItem.querySelector('.bar-fill');
                if (barFill) {
                    barFill.setAttribute('data-percentage', percentage);
                    barFill.style.width = `${percentage}%`;
                }
            }
        });
    }

    function animateProgressBars() {
        const barFills = document.querySelectorAll('.bar-fill');
        barFills.forEach(fill => {
            const percentage = fill.getAttribute('data-percentage');
            fill.style.width = `${percentage}%`;
        });
    }

    /**
     * Dibuja la barra de sesión en la cabecera.
     */
    function renderSessionBar() {
        if (currentUser.loggedIn) {
            sessionBar.classList.add('session-bar--active');
            sessionBar.innerHTML = `
                <div class="session-container">
                    <span class="session-user"><i class="fas fa-user-circle"></i> 👤 ${currentUser.username}</span>
                    <button class="session-logout-btn" id="session-logout-btn">Salir</button>
                </div>
            `;
            document.getElementById('session-logout-btn').addEventListener('click', handleLogout);
        } else {
            sessionBar.classList.remove('session-bar--active');
            sessionBar.innerHTML = '';
        }
    }

    function handleLogout() {
        currentUser.loggedIn = false;
        currentUser.username = null;
        
        sessionStorage.removeItem('static_logged_in');
        sessionStorage.removeItem('static_username');

        renderSessionBar();
        renderLayout();
        showNotice('Sesión cerrada con éxito.', 'info');
    }

    function setAlreadyVotedState(justVoted = false) {
        const voteButtons = document.querySelectorAll('.vote-button');
        voteButtons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Votación Completa';
        });

        if (justVoted) {
            showNotice('¡Gracias! Tu voto ha sido registrado con éxito.', 'success');
        } else {
            showNotice('Ya has emitido tu voto en esta sesión. Abajo puedes ver las estadísticas actualizadas en tiempo real.', 'info');
        }
    }

    function showNotice(message, type = 'info') {
        let iconClass = 'fa-info-circle';
        let customClass = '';

        if (type === 'success') {
            iconClass = 'fa-check-circle';
            customClass = 'success';
        } else if (type === 'error') {
            iconClass = 'fa-exclamation-triangle';
            customClass = 'error';
        }

        noticeContainer.innerHTML = `
            <div class="vote-notice ${customClass}">
                <i class="fas ${iconClass}"></i>
                <span>${message}</span>
            </div>
        `;
    }

    /**
     * Dibuja la tabla de auditoría estática.
     */
    function renderAdminAuditUI() {
        const tbody = document.getElementById('admin-audit-tbody');
        if (!tbody) return;

        const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];

        if (localUsers.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="no-records">No hay usuarios registrados en el sistema.</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = localUsers.map(u => {
            const userVoted = u.voted_for !== null && u.voted_for !== undefined && u.voted_for !== '';
            let foodName = 'Ninguno (Aún sin votar)';
            let foodColor = '#7f8c8d';

            if (userVoted) {
                const food = db.find(f => f.id === u.voted_for);
                if (food) {
                    foodName = food.name;
                    foodColor = food.color;
                }
            }

            const statusBadge = userVoted 
                ? `<span class="badge-status success"><i class="fas fa-check-circle"></i> Votó</span>`
                : `<span class="badge-status pending"><i class="fas fa-clock"></i> Pendiente</span>`;

            const isDefaultAdmin = u.username.toLowerCase() === 'admin' || u.username.toLowerCase() === 'superadmin';
            const actionsCell = isDefaultAdmin 
                ? `<span class="badge-system"><i class="fas fa-shield-halved"></i> Sistema</span>`
                : `<button class="delete-user-btn" data-username="${u.username}" title="Eliminar usuario y su voto"><i class="fas fa-trash-can"></i> Eliminar</button>`;

            return `
                <tr data-username="${u.username}" data-voted-food="${foodName}">
                    <td class="col-user">👤 ${u.username}</td>
                    <td class="col-date">${u.created_at || 'N/A'}</td>
                    <td class="col-status">${statusBadge}</td>
                    <td class="col-food">
                        <span class="badge-food" style="border-left: 4px solid ${foodColor}; background-color: ${foodColor}15;">
                            ${foodName}
                        </span>
                    </td>
                    <td class="col-actions">
                        ${actionsCell}
                    </td>
                </tr>
            `;
        }).join('');

        applyAdminSearchFilter();
    }

    /**
     * Vincula los eventos para el panel de administración estático (búsqueda y borrado)
     */
    function setupAdminEvents() {
        const searchInput = document.getElementById('admin-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', applyAdminSearchFilter);
        }

        // Delegar clic de borrado dentro del panel
        const adminDashboard = document.getElementById('admin-dashboard-container');
        if (adminDashboard) {
            adminDashboard.addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('.delete-user-btn');
                if (!deleteBtn) return;

                e.preventDefault();
                const usernameToDelete = deleteBtn.getAttribute('data-username');
                if (!usernameToDelete) return;

                const confirmDelete = confirm(`¿Estás absolutamente seguro de eliminar al usuario "${usernameToDelete}" en la Demo estática y anular su voto?`);
                if (!confirmDelete) return;

                // 1. Quitar el usuario de localStorage
                const localUsers = JSON.parse(localStorage.getItem('vallegrande_users')) || [];
                const filteredUsers = localUsers.filter(u => u.username.toLowerCase() !== usernameToDelete.toLowerCase());
                localStorage.setItem('vallegrande_users', JSON.stringify(filteredUsers));

                // 2. Re-calcular estadísticas, KPIs y tabla
                updateResultsUI(db);
                renderAdminAuditUI();
                showNotice(`Usuario "${usernameToDelete}" eliminado con éxito (Simulado).`, 'success');
            });
        }
    }

    function applyAdminSearchFilter() {
        const queryInput = document.getElementById('admin-search-input');
        if (!queryInput) return;
        const query = queryInput.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#admin-audit-tbody tr');
        
        rows.forEach(row => {
            if (row.classList.contains('no-records')) return;
            const username = row.getAttribute('data-username') || '';
            const food = row.getAttribute('data-voted-food') || '';
            
            if (username.toLowerCase().includes(query) || food.toLowerCase().includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
});
