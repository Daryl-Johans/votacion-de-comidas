/**
 * app.js
 * Lógica interactiva con soporte de Login y Registro dinámico integrado en la página de inicio.
 * Conecta con api.php en el servidor backend PHP.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Referencias de UI de Votación
    const mainLayout = document.querySelector('.main-layout');
    const voteButtons = document.querySelectorAll('.vote-button');
    const noticeContainer = document.getElementById('notice-container');
    const sessionBar = document.getElementById('session-bar');
    
    // Referencias del Formulario de Autenticación Integrado (si no está logueado)
    const tabLoginBtn = document.getElementById('tab-login-btn');
    const tabRegisterBtn = document.getElementById('tab-register-btn');
    const authLoginForm = document.getElementById('auth-login-form');
    const authRegisterForm = document.getElementById('auth-register-form');
    const authErrorAlert = document.getElementById('auth-error-alert');
    const authErrorMessage = document.getElementById('auth-error-message');

    // Iniciar Sondeo en Tiempo Real (Polling) cada 3 segundos SOLO si está el panel de auditoría
    if (document.getElementById('admin-audit-table')) {
        setInterval(fetchLatestResults, 3000);
    }

    // ── Generación de Códigos QR de Invitación ─────────────────────────────
    // Se inicializan los contenedores QR presentes en esta página:
    //   - #qr-code-container-admin  → Panel de administración (admin/superadmin)
    //   - #qr-code-container-login  → Vista de inicio de sesión (usuarios no autenticados)
    const qrContainerIds = ['qr-code-container-admin', 'qr-code-container-login'];

    /**
     * Genera o regenera el código QR dentro de un contenedor dado.
     * @param {string} containerId - ID del elemento contenedor del QR.
     * @param {string} qrUrl - URL que codificará el QR.
     */
    function renderQR(containerId, qrUrl) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = ''; // Limpiar contenido previo

        // Usar la librería qrcodejs si está disponible (funciona offline)
        if (typeof QRCode !== 'undefined') {
            try {
                new QRCode(container, {
                    text: qrUrl,
                    width: 160,
                    height: 160,
                    colorDark: '#1e130c',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                return;
            } catch (err) {
                console.warn(`QRCode.js falló en #${containerId}, usando fallback de API:`, err);
            }
        }

        // Fallback: imagen de API pública si la librería no está disponible
        const img = document.createElement('img');
        img.src = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1e130c&data=${encodeURIComponent(qrUrl)}`;
        img.alt = 'Código QR de acceso';
        img.style.cssText = 'width:160px;height:160px;border-radius:4px;display:block;';
        container.appendChild(img);
    }

    // Construir la URL base apuntando a index.php con el parámetro de registro
    function buildRegisterUrl(hostname) {
        try {
            const urlObj = new URL(window.location.href);
            if (hostname) urlObj.hostname = hostname;
            // Normalizar la ruta para apuntar siempre a index.php
            urlObj.pathname = urlObj.pathname.replace(/\/[^/]*$/, '/index.php');
            urlObj.searchParams.set('tab', 'register');
            return urlObj.toString();
        } catch (e) {
            return window.location.href;
        }
    }

    // Renderizar con la URL local primero para tener un QR inmediato
    const qrUrlLocal = buildRegisterUrl(null);
    qrContainerIds.forEach(id => renderQR(id, qrUrlLocal));

    // Si está disponible la IP real de la red local (inyectada por PHP), reemplazar la URL del QR
    if (window.APP_SERVER_IP &&
        (window.location.hostname === 'localhost' ||
         window.location.hostname === '127.0.0.1' ||
         window.location.hostname === '[::1]')) {
        const qrUrlNetwork = buildRegisterUrl(window.APP_SERVER_IP);
        qrContainerIds.forEach(id => renderQR(id, qrUrlNetwork));
    }
    // ── Fin de Generación de Códigos QR ───────────────────────────────────

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

        // Activar automáticamente la pestaña de registro si el QR trae ?tab=register
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'register') {
            tabRegisterBtn.click();
        }
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
        voteButtons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                const foodId = button.getAttribute('data-food-id');
                
                // Guardar los contenidos y estados originales de todos los botones de votación
                // para poder revertir de inmediato si el servidor falla (Optimistic UI)
                const originalBtnContents = [];
                voteButtons.forEach(btn => {
                    originalBtnContents.push({
                        button: btn,
                        content: btn.innerHTML
                    });
                });

                // Efecto visual inmediato: Añadir clase de pulsación rápida al botón presionado
                button.classList.add('vote-clicked');
                
                // Aplicar el estado de votación completa en la UI de inmediato (Optimistic UI)
                setAlreadyVotedState(true, foodId);

                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ 
                            action: 'vote',
                            food_id: foodId 
                        })
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        // Si falla la petición, revertimos el estado optimista y mostramos el mensaje de error
                        revertVoteState(originalBtnContents);
                        showNotice(result.message || 'No se pudo registrar el voto.', 'error');
                    } else {
                        // Si tiene éxito, quitar animación y redirigir inmediatamente
                        button.classList.remove('vote-clicked');
                        window.location.href = 'resultados.php';
                    }
                } catch (error) {
                    console.error('Error al votar:', error);
                    // Revertimos ante problemas de red
                    revertVoteState(originalBtnContents);
                    showNotice('Error al conectar con el servidor.', 'error');
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

    // 10. Control para el reinicio completo de votaciones (Administración)
    const resetBtn = document.getElementById('admin-reset-btn');
    if (resetBtn) {
        resetBtn.addEventListener('click', async () => {
            const confirm1 = confirm("⚠️ ¿Estás absolutamente seguro de reiniciar todas las votaciones?\n\nEsta acción eliminará a todos los usuarios registrados (excepto cuentas admin y superadmin) y borrará todos los votos de la base de datos. Esta acción no se puede deshacer.");
            if (!confirm1) return;

            const confirm2 = confirm("🚨 ¿Realmente deseas proceder con el reinicio completo?\n\nLos datos históricos de la votación actual se perderán definitivamente.");
            if (!confirm2) return;

            // Cambiar estado visual del botón
            const originalContent = resetBtn.innerHTML;
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reiniciando...';

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset_votes' })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showNotice(result.message || '¡Base de datos y estadísticas reiniciadas con éxito!', 'success');
                    
                    // Recargar la página tras un breve momento para ver los gráficos limpios
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert(result.message || 'Ocurrió un error al reiniciar la base de datos.');
                    resetBtn.disabled = false;
                    resetBtn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error('Error al reiniciar votaciones:', error);
                alert('Error de red al conectar con el servidor.');
                resetBtn.disabled = false;
                resetBtn.innerHTML = originalContent;
            }
        });
    }

    /**
     * Realiza un sondeo periódico del backend para actualizaciones en tiempo real.
     */
    async function fetchLatestResults() {
        try {
            const response = await fetch('api.php');
            const result = await response.json();

            if (response.ok && result.success) {
                // Si la respuesta contiene datos de auditoría administrativa, actualizarlos en tiempo real
                if (result.admin_data && result.admin_data.users) {
                    updateAdminAuditUI(result.admin_data.users, result.data);
                }
            }
        } catch (error) {
            console.warn('Sondeo en tiempo real de auditoría pausado temporariamente.');
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
    function revertVoteState(originalBtnContents) {
        if (mainLayout) mainLayout.classList.remove('already-voted');
        
        if (originalBtnContents && originalBtnContents.length > 0) {
            originalBtnContents.forEach(item => {
                item.button.disabled = false;
                item.button.innerHTML = item.content;
                item.button.classList.remove('vote-clicked');
                item.button.classList.remove('voted-choice');
            });
        }
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

    function setAlreadyVotedState(justVoted = false, votedFoodId = null) {
        if (mainLayout) mainLayout.classList.add('already-voted');
        disableVoteButtons();
        
        if (voteButtons) {
            voteButtons.forEach(btn => {
                const currentFoodId = btn.getAttribute('data-food-id');
                if (votedFoodId && currentFoodId === votedFoodId) {
                    btn.classList.add('voted-choice');
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Elegiste este plato';
                } else {
                    btn.innerHTML = '<i class="fas fa-heart"></i> Voto ya realizado';
                }
            });
        }

        if (justVoted) {
            showNotice('¡Gracias! Tu voto ha sido registrado con éxito.', 'success');
        } else {
            showNotice('Ya has emitido tu voto en esta sesión. Puedes ver los resultados globales en la pantalla de estadísticas.', 'info');
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
