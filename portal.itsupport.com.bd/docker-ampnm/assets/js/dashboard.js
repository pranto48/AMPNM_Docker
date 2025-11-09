function initDashboard() {
    const API_URL = 'api.php';
    const dashboardLoader = document.getElementById('dashboardLoader');
    const dashboardWidgets = document.getElementById('dashboard-widgets');

    const statusChartCanvas = document.getElementById('statusChart');
    const totalDevicesText = document.getElementById('totalDevicesText');
    const onlineCountEl = document.getElementById('onlineCount');
    const warningCountEl = document.getElementById('warningCount');
    const criticalCountEl = document.getElementById('criticalCount');
    const offlineCountEl = document.getElementById('offlineCount');
    const recentActivityListEl = document.getElementById('recentActivityList');
    const noRecentActivityMessage = document.getElementById('noRecentActivityMessage');
    const refreshAllDevicesBtn = document.getElementById('refreshAllDevicesBtn');
    let statusChart = null;

    const pingForm = document.getElementById('pingForm');
    const pingHostInput = document.getElementById('pingHostInput');
    const pingButton = document.getElementById('pingButton');
    const pingResultContainer = document.getElementById('pingResultContainer');
    const pingResultPre = document.getElementById('pingResultPre');

    const api = {
        get: (action, params = {}) => fetch(`${API_URL}?action=${action}&${new URLSearchParams(params)}`).then(res => res.json()),
        post: (action, body) => fetch(`${API_URL}?action=${action}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(res => res.json())
    };

    const statusColorMap = {
        online: 'text-green-400',
        warning: 'text-yellow-400',
        critical: 'text-red-400',
        offline: 'text-slate-400',
        unknown: 'text-slate-500'
    };

    const loadDashboardData = async () => {
        dashboardLoader.classList.remove('hidden');
        dashboardWidgets.classList.add('hidden');

        try {
            const data = await api.get('get_dashboard_data');
            
            totalDevicesText.querySelector('span:first-child').textContent = data.stats.total;
            onlineCountEl.textContent = data.stats.online;
            warningCountEl.textContent = data.stats.warning;
            criticalCountEl.textContent = data.stats.critical;
            offlineCountEl.textContent = data.stats.offline;

            if (statusChart) {
                statusChart.destroy();
            }
            const chartData = {
                labels: ['Online', 'Warning', 'Critical', 'Offline'],
                datasets: [{
                    data: [data.stats.online, data.stats.warning, data.stats.critical, data.stats.offline],
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b'],
                    borderColor: '#1e293b',
                    borderWidth: 4,
                }]
            };
            statusChart = new Chart(statusChartCanvas, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    cutout: '75%',
                    plugins: { legend: { display: false }, tooltip: { enabled: true } }
                }
            });

            // Render recent activity
            if (data.recent_activity && data.recent_activity.length > 0) {
                recentActivityListEl.innerHTML = data.recent_activity.map(activity => `
                    <div class="border border-slate-700 rounded-lg p-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-white">${activity.device_name} <span class="text-sm text-slate-500 font-mono">(${activity.device_ip || 'N/A'})</span></div>
                            <div class="text-sm ${statusColorMap[activity.status] || statusColorMap.unknown}">${activity.status.charAt(0).toUpperCase() + activity.status.slice(1)}: ${activity.details}</div>
                        </div>
                        <div class="text-xs text-slate-500">${new Date(activity.created_at).toLocaleTimeString()}</div>
                    </div>
                `).join('');
                noRecentActivityMessage.classList.add('hidden');
            } else {
                recentActivityListEl.innerHTML = '';
                noRecentActivityMessage.classList.remove('hidden');
            }

        } catch (error) {
            console.error("Failed to load dashboard data:", error);
        } finally {
            dashboardLoader.classList.add('hidden');
            dashboardWidgets.classList.remove('hidden');
        }
    };

    loadDashboardData(); // Initial load

    // Set up auto-refresh for dashboard data every 30 seconds
    setInterval(loadDashboardData, 30000);

    pingForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const host = pingHostInput.value.trim();
        if (!host) return;

        pingButton.disabled = true;
        pingButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Pinging...';
        pingResultContainer.classList.remove('hidden');
        pingResultPre.textContent = `Pinging ${host}...`;

        try {
            const result = await api.post('manual_ping', { host });
            pingResultPre.textContent = result.output || `Error: ${result.error || 'Unknown error'}`;
        } catch (error) {
            pingResultPre.textContent = `Failed to perform ping. Check API connection.`;
        } finally {
            pingButton.disabled = false;
            pingButton.innerHTML = '<i class="fas fa-bolt mr-2"></i>Ping';
        }
    });

    refreshAllDevicesBtn.addEventListener('click', async () => {
        refreshAllDevicesBtn.disabled = true;
        refreshAllDevicesBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
        window.notyf.info('Starting global device status check...');

        try {
            const result = await api.post('check_all_devices_globally');
            if (result.success) {
                window.notyf.success(`${result.message} ${result.status_changes} status changes detected.`);
                await loadDashboardData(); // Reload dashboard data after refresh
            } else {
                throw new Error(result.error || 'Unknown error during bulk check.');
            }
        } catch (error) {
            console.error('Bulk check failed:', error);
            window.notyf.error('Global device check failed.');
        } finally {
            refreshAllDevicesBtn.disabled = false;
            refreshAllDevicesBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Refresh All Devices';
        }
    });
}