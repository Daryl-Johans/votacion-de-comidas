/**
 * resultados.js
 * Lógica dedicada exclusivamente a la visualización de resultados en tiempo real y código QR.
 * Conecta con api.php en el servidor backend PHP.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Referencias de UI de Resultados
    const totalVotesElement = document.getElementById('total-votes-count');
    const noticeContainer = document.getElementById('notice-container');
    const resetVoteBtn = document.getElementById('reset-vote-btn');
    const sessionBar = document.getElementById('session-bar');

    // Inicializar barras de progreso al cargar
    setTimeout(animateProgressBars, 150);

    // Iniciar Sondeo en Tiempo Real (Polling) cada 3 segundos para el panel de resultados
    const pollInterval = setInterval(fetchLatestResults, 3000);

    // Generar Código QR de Registro e Invitación con redundancia automática y etiqueta manual
    const qrContainer = document.getElementById('qr-code-container');
    if (qrContainer) {
        // Reconstruir dinámicamente la URL del QR reemplazando localhost por la IP de red local física detectada en PHP
        // En la página de resultados, queremos que la URL apunte al registro de index.php (?tab=register)
        let qrUrl = window.location.href;
        try {
            const urlObj = new URL(window.location.href);
            // Reemplazar la ruta para apuntar a index.php
            let path = urlObj.pathname;
            if (path.endsWith('resultados.php')) {
                urlObj.pathname = path.replace('resultados.php', 'index.php');
            }
            
            if (window.APP_SERVER_IP && (urlObj.hostname === 'localhost' || urlObj.hostname === '127.0.0.1' || urlObj.hostname === '[::1]')) {
                urlObj.hostname = window.APP_SERVER_IP;
            }
            // Siempre apuntar al registro para que el QR lleve directo a crear cuenta
            urlObj.searchParams.set('tab', 'register');
            qrUrl = urlObj.toString();
        } catch (err) {
            console.error('Error al generar URL automática para el QR:', err);
        }
        
        qrContainer.innerHTML = ''; // Limpiar
        
        let qrGenerated = false;

        // Intentar usar la librería qrcode.js si se cargó exitosamente
        if (typeof QRCode !== 'undefined') {
            try {
                new QRCode(qrContainer, {
                    text: qrUrl,
                    width: 160,
                    height: 160,
                    colorDark: '#1e130c',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                qrGenerated = true;
            } catch (err) {
                console.warn('Error al iniciar QRCode.js, intentando fallback de API:', err);
            }
        }

        // Si la librería no está definida o falló la renderización, usar API de imagen pública como fallback
        if (!qrGenerated) {
            const img = document.createElement('img');
            img.src = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1e130c&data=${encodeURIComponent(qrUrl)}`;
            img.alt = 'Código QR de acceso';
            img.style.width = '160px';
            img.style.height = '160px';
            img.style.borderRadius = '4px';
            img.style.display = 'block';
            qrContainer.appendChild(img);
        }

        // Badge de URL eliminado — el QR se muestra limpio sin texto de dirección
    }

    // Configurar evento de Logout (Cerrar Sesión)
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

    // Botón para reiniciar simulación de voto (limpia local y servidor) - Exclusivo Admin
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

    /**
     * Realiza un sondeo periódico del backend para actualizaciones en tiempo real.
     */
    async function fetchLatestResults() {
        try {
            const response = await fetch('api.php');
            const result = await response.json();

            if (response.ok && result.success) {
                updateResultsUI(result.data);
            }
        } catch (error) {
            console.warn('Sondeo en tiempo real pausado temporariamente.');
        }
    }

    function animateProgressBars() {
        const barFills = document.querySelectorAll('.bar-fill');
        barFills.forEach(fill => {
            const percentage = fill.getAttribute('data-percentage');
            fill.style.width = `${percentage}%`;
        });
    }

    function updateResultsUI(foodsList) {
        if (!foodsList) return;
        
        let totalVotes = 0;
        foodsList.forEach(f => totalVotes += parseInt(f.votes));
        
        if (totalVotesElement) {
            totalVotesElement.textContent = totalVotes;
        }

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
});
