/**
 * app.js
 * Lógica interactiva con soporte de Login y Registro dinámico integrado en la página de inicio.
 * Conecta con api.php en el servidor backend PHP.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Referencias de UI de Votación
    const mainLayout = document.querySelector('.main-layout');
    const voteButtons = document.querySelectorAll('.vote-button');
    const totalVotesElement = document.getElementById('total-votes-count');
    const noticeContainer = document.getElementById('notice-container');
    const resetVoteBtn = document.getElementById('reset-vote-btn');
    const sessionBar = document.getElementById('session-bar');
    
    // Referencias del Formulario de Autenticación Integrado (si no está logueado)
    const tabLoginBtn = document.getElementById('tab-login-btn');
    const tabRegisterBtn = document.getElementById('tab-register-btn');
    const authLoginForm = document.getElementById('auth-login-form');
    const authRegisterForm = document.getElementById('auth-register-form');
    const authErrorAlert = document.getElementById('auth-error-alert');
    const authErrorMessage = document.getElementById('auth-error-message');

    // Iniciar Sondeo en Tiempo Real (Polling) cada 3 segundos para el panel de resultados
    const pollInterval = setInterval(fetchLatestResults, 3000);

    // Generar Código QR de Registro e Invitación con librería local (sin depender de internet)
    const qrContainer = document.getElementById('qr-code-container');
    if (qrContainer && typeof QRCode !== 'undefined') {
        const currentUrl = window.location.href;
        qrContainer.innerHTML = ''; // Limpiar por si acaso
        new QRCode(qrContainer, {
            text: currentUrl,
            width: 160,
            height: 160,
            colorDark: '#1e130c',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    // 1. Configurar eventos de Pestañas del Formulario Integrado (si existen)
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

    // 2. Procesar envío de Login Integrado
    if (authLoginForm) {
        authLoginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('login-username').value.trim();
            const password = document.getElementById('login-password').value.trim();
            
            hideAuthError();

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        username: username,
                        password: password
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Recargar la página: PHP detectará la sesión activa e inyectará la vista de votación directamente.
                    window.location.reload();
                } else {
                    showAuthError(result.message || 'Error de autenticación.');
                }
            } catch (error) {
                console.error('Error de login:', error);
                showAuthError('Error de red al conectar con el servidor.');
            }
        });
    }

    // 3. Procesar envío de Registro Integrado
    if (authRegisterForm) {
        authRegisterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('reg-username').value.trim();
            const password = document.getElementById('reg-password').value.trim();
            
            hideAuthError();

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'register',
                        username: username,
                        password: password
                    })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Recargar la página: El registro exitoso inicia sesión automáticamente.
                    window.location.reload();
                } else {
                    showAuthError(result.message || 'No se pudo crear el usuario.');
                }
            } catch (error) {
                console.error('Error de registro:', error);
                showAuthError('Error de red al registrar usuario.');
            }
        });
    }

    // 4. Configurar eventos para los botones de votación (si se muestran en el DOM)
    if (voteButtons && voteButtons.length > 0) {
        // Inicializar barras de progreso
        setTimeout(animateProgressBars, 150);

        voteButtons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                const foodId = button.getAttribute('data-food-id');
                
                // Deshabilitar temporalmente para evitar doble clic accidental en tránsito
                disableVoteButtons();
                const originalBtnContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';

                let voteSuccess = false;

                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            action: 'vote',
                            food_id: foodId 
                        })
                    });

                    const result = await response.json();

                    if (response.ok && result.success) {
                        updateResultsUI(result.data);
                        setAlreadyVotedState(true);
                        voteSuccess = true;
                    } else {
                        showNotice(result.message || 'No se pudo registrar el voto.', 'error');
                    }
                } catch (error) {
                    console.error('Error al votar:', error);
                    showNotice('Error al conectar con el servidor.', 'error');
                } finally {
                    // Solo reactivar los botones si el voto NO se completó con éxito para permitir reintentos
                    if (!voteSuccess) {
                        enableVoteButtons(originalBtnContent);
                    }
                }
            });
        });
    }

    // 5. Configurar evento de Logout (Cerrar Sesión)
    const logoutBtn = document.getElementById('session-logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'logout' })
                });

                if (response.ok) {
                    window.location.reload();
                }
            } catch (e) {
                console.error('Error al cerrar sesión:', e);
            }
        });
    }

    // 6. Botón para reiniciar simulación de voto (limpia local y servidor)
    if (resetVoteBtn) {
        resetVoteBtn.addEventListener('click', async () => {
            const confirmReset = confirm("¿Estás absolutamente seguro de reiniciar todas las votaciones y eliminar las cuentas de usuarios comunes? Esta acción no se puede deshacer.");
            if (!confirmReset) return;

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_votes' })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    alert("¡Base de datos y estadísticas reiniciadas con éxito!");
                    window.location.reload();
                } else {
                    alert(result.message || "Error al reiniciar la base de datos en el servidor.");
                }
            } catch (error) {
                console.error("Error al reiniciar base de datos:", error);
                alert("Error de conexión al intentar reiniciar.");
            }
        });
    }

    // 7. Control de visibilidad de contraseñas (Toggle Password)
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            if (passwordInput) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    btn.classList.remove('fa-eye');
                    btn.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    btn.classList.remove('fa-eye-slash');
                    btn.classList.add('fa-eye');
                }
            }
        });
    });

    // 8. Barra de búsqueda interactiva en tiempo real para auditoría
    const searchInput = document.getElementById('admin-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            applyAdminSearchFilter();
        });
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

    // 9. Delegación de eventos para el borrado granular de usuarios
    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (!deleteBtn) return;

        e.preventDefault();
        const usernameToDelete = deleteBtn.getAttribute('data-username');
        if (!usernameToDelete) return;

        const confirmDelete = confirm(`¿Estás absolutamente seguro de eliminar al usuario "${usernameToDelete}" y anular su voto? Esta acción no se puede deshacer.`);
        if (!confirmDelete) return;

        // Mostrar spinner en caliente
        const originalContent = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_user',
                    username_to_delete: usernameToDelete
                })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showNotice(result.message || 'Usuario eliminado con éxito.', 'success');
                // Recalcular estadísticas y KPIs
                updateResultsUI(result.data);
                // Actualizar logs en caliente
                if (result.admin_data && result.admin_data.users) {
                    updateAdminAuditUI(result.admin_data.users, result.data);
                }
            } else {
                alert(result.message || 'No se pudo eliminar el usuario.');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalContent;
            }
        } catch (error) {
            console.error('Error al eliminar usuario:', error);
            alert('Error de conexión con el servidor.');
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalContent;
        }
    });

    /**
     * Realiza un sondeo periódico del backend para actualizaciones en tiempo real.
     */
    async function fetchLatestResults() {
        try {
            const response = await fetch('api.php');
            const result = await response.json();

            if (response.ok && result.success) {
                updateResultsUI(result.data);
                
                // Si la respuesta contiene datos de auditoría administrativa, actualizarlos en tiempo real
                if (result.admin_data && result.admin_data.users) {
                    updateAdminAuditUI(result.admin_data.users, result.data);
                }
            }
        } catch (error) {
            console.warn('Sondeo en tiempo real pausado temporariamente.');
        }
    }

    /**
     * Actualiza dinámicamente la tabla de auditoría de administración con los nuevos usuarios y votos en tiempo real.
     */
    function updateAdminAuditUI(users, foods) {
        const tbody = document.getElementById('admin-audit-tbody');
        if (!tbody) return;

        if (users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="no-records">No hay usuarios registrados en el sistema.</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = users.map(u => {
            const userVoted = u.voted_for !== null && u.voted_for !== '';
            let foodName = 'Ninguno (Aún sin votar)';
            let foodColor = '#7f8c8d';

            if (userVoted) {
                const food = foods.find(f => f.id === u.voted_for);
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
                    <td class="col-date">${u.created_at}</td>
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

        // Mantener aplicado el filtro de búsqueda
        applyAdminSearchFilter();
    }

    /**
     * Visualización de errores de autenticación.
     */
    function showAuthError(msg) {
        if (authErrorMessage && authErrorAlert) {
            authErrorMessage.textContent = msg;
            authErrorAlert.style.display = 'flex';
        }
    }

    function hideAuthError() {
        if (authErrorAlert) {
            authErrorAlert.style.display = 'none';
        }
    }

    function animateProgressBars() {
        const barFills = document.querySelectorAll('.bar-fill');
        barFills.forEach(fill => {
            const percentage = fill.getAttribute('data-percentage');
            fill.style.width = `${percentage}%`;
        });
    }

    function disableVoteButtons() {
        if (voteButtons) {
            voteButtons.forEach(btn => btn.disabled = true);
        }
    }

    function enableVoteButtons(originalContent) {
        if (voteButtons) {
            voteButtons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            });
        }
    }

    function setAlreadyVotedState(justVoted = false) {
        if (mainLayout) mainLayout.classList.add('already-voted');
        disableVoteButtons();
        
        if (voteButtons) {
            voteButtons.forEach(btn => {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Votación Completa';
            });
        }

        if (justVoted) {
            showNotice('¡Gracias! Tu voto ha sido registrado con éxito.', 'success');
        } else {
            showNotice('Ya has emitido tu voto en esta sesión. Abajo puedes ver las estadísticas actualizadas en tiempo real.', 'info');
        }
    }

    function updateResultsUI(foodsList) {
        if (!foodsList) return;
        
        let totalVotes = 0;
        foodsList.forEach(f => totalVotes += parseInt(f.votes));
        
        totalVotesElement.textContent = totalVotes;

        // --- CÁLCULO DE KPIS DINÁMICOS ---
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

        // --- ACTUALIZAR BARRAS DE PROGRESO ---
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

        if (noticeContainer) {
            noticeContainer.innerHTML = `
                <div class="vote-notice ${customClass}">
                    <i class="fas ${iconClass}"></i>
                    <span>${message}</span>
                </div>
            `;
        }
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
});
