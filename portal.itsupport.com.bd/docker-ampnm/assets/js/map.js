window.MapApp = window.MapApp || {};

MapApp.ui = {
    // DOM Elements
    els: {},

    // Cache DOM elements
    cacheElements: () => {
        MapApp.ui.els = {
            mapWrapper: document.getElementById('network-map-wrapper'),
            mapSelector: document.getElementById('mapSelector'),
            newMapBtn: document.getElementById('newMapBtn'),
            renameMapBtn: document.getElementById('renameMapBtn'),
            deleteMapBtn: document.getElementById('deleteMapBtn'),
            mapContainer: document.getElementById('map-container'),
            noMapsContainer: document.getElementById('no-maps'),
            createFirstMapBtn: document.getElementById('createFirstMapBtn'),
            currentMapName: document.getElementById('currentMapName'),
            scanNetworkBtn: document.getElementById('scanNetworkBtn'),
            refreshStatusBtn: document.getElementById('refreshStatusBtn'),
            liveRefreshToggle: document.getElementById('liveRefreshToggle'),
            addEdgeBtn: document.getElementById('addEdgeBtn'),
            fullscreenBtn: document.getElementById('fullscreenBtn'),
            exportBtn: document.getElementById('exportBtn'),
            importBtn: document.getElementById('importBtn'),
            importFile: document.getElementById('importFile'),
            edgeModal: document.getElementById('edgeModal'),
            edgeForm: document.getElementById('edgeForm'),
            cancelEdgeBtn: document.getElementById('cancelEdgeBtn'),
            scanModal: document.getElementById('scanModal'),
            closeScanModal: document.getElementById('closeScanModal'),
            scanForm: document.getElementById('scanForm'),
            scanLoader: document.getElementById('scanLoader'),
            scanResults: document.getElementById('scanResults'),
            scanInitialMessage: document.getElementById('scanInitialMessage'),
            mapSettingsBtn: document.getElementById('mapSettingsBtn'),
            mapSettingsModal: document.getElementById('mapSettingsModal'),
            mapSettingsForm: document.getElementById('mapSettingsForm'),
            cancelMapSettingsBtn: document.getElementById('cancelMapSettingsBtn'),
            resetMapBgBtn: document.getElementById('resetMapBgBtn'),
            mapBgUpload: document.getElementById('mapBgUpload'),
            placeDeviceBtn: document.getElementById('placeDeviceBtn'),
            placeDeviceModal: document.getElementById('placeDeviceModal'),
            closePlaceDeviceModal: document.getElementById('closePlaceDeviceModal'),
            placeDeviceList: document.getElementById('placeDeviceList'),
            placeDeviceLoader: document.getElementById('placeDeviceLoader'),
            shareMapBtn: document.getElementById('shareMapBtn'),
            // NEW Public View Elements
            publicViewToggle: document.getElementById('publicViewToggle'),
            publicViewLinkContainer: document.getElementById('publicViewLinkContainer'),
            publicViewLink: document.getElementById('publicViewLink'),
            copyPublicLinkBtn: document.getElementById('copyPublicLinkBtn'),
            // Custom Modals
            confirmModal: document.getElementById('confirmModal'),
            confirmModalTitle: document.getElementById('confirmModalTitle'),
            confirmModalMessage: document.getElementById('confirmModalMessage'),
            confirmModalConfirmBtn: document.getElementById('confirmModalConfirmBtn'),
            inputModal: document.getElementById('inputModal'),
            inputModalTitle: document.getElementById('inputModalTitle'),
            inputModalMessage: document.getElementById('inputModalMessage'),
            inputModalField: document.getElementById('inputModalField'),
            inputModalConfirmBtn: document.getElementById('inputModalConfirmBtn'),
        };
    },

    populateLegend: () => {
        const legendContainer = document.getElementById('status-legend');
        if (!legendContainer) return;
        const statusOrder = ['online', 'warning', 'critical', 'offline', 'unknown'];
        legendContainer.innerHTML = statusOrder.map(status => {
            const color = MapApp.config.statusColorMap[status];
            const label = status.charAt(0).toUpperCase() + status.slice(1);
            return `<div class="legend-item"><div class="legend-dot" style="background-color: ${color};"></div><span>${label}</span></div>`;
        }).join('');
    },

    openDeviceModal: (deviceId) => {
        if (window.userRole !== 'admin') {
            window.notyf.error('You do not have permission to edit devices.');
            return;
        }
        // Redirect to the PHP edit-device page, passing the current map_id
        window.location.href = `edit-device.php?id=${deviceId}&map_id=${MapApp.state.currentMapId}`;
    },

    openEdgeModal: (edgeId) => {
        if (window.userRole !== 'admin') {
            window.notyf.error('You do not have permission to edit connections.');
            return;
        }
        const edge = MapApp.state.edges.get(edgeId);
        document.getElementById('edgeId').value = edge.id;
        document.getElementById('connectionType').value = edge.connection_type || 'cat5';
        openModal('edgeModal');
    },

    updateAndAnimateEdges: () => {
        MapApp.state.tick++;
        const animatedDashes = [4 - (MapApp.state.tick % 12), 8, MapApp.state.tick % 12];
        const updates = [];
        const allEdges = MapApp.state.edges.get();
        if (MapApp.state.nodes.length > 0 && allEdges.length > 0) {
            const deviceStatusMap = new Map(MapApp.state.nodes.get({ fields: ['id', 'deviceData'] }).map(d => [d.id, d.deviceData.status]));
            allEdges.forEach(edge => {
                const sourceStatus = deviceStatusMap.get(edge.from);
                const targetStatus = deviceStatusMap.get(edge.to);
                const isOffline = sourceStatus === 'offline' || targetStatus === 'offline';
                const isActive = sourceStatus === 'online' && targetStatus === 'online';
                const color = isOffline ? MapApp.config.statusColorMap.offline : (MapApp.config.edgeColorMap[edge.connection_type] || MapApp.config.edgeColorMap.cat5);
                let dashes = false;
                if (isActive) { dashes = animatedDashes; } 
                else if (edge.connection_type === 'wifi' || edge.connection_type === 'radio') { dashes = [5, 5]; }
                updates.push({ id: edge.id, color, dashes });
            });
        }
        if (updates.length > 0) MapApp.state.edges.update(updates);
        MapApp.state.animationFrameId = requestAnimationFrame(MapApp.ui.updateAndAnimateEdges);
    },

    // Generic Confirmation Modal
    showConfirm: (title, message, confirmText = 'Confirm') => {
        return new Promise((resolve) => {
            MapApp.ui.els.confirmModalTitle.textContent = title;
            MapApp.ui.els.confirmModalMessage.textContent = message;
            MapApp.ui.els.confirmModalConfirmBtn.textContent = confirmText;
            openModal('confirmModal');

            const onConfirm = () => {
                closeModal('confirmModal');
                MapApp.ui.els.confirmModalConfirmBtn.removeEventListener('click', onConfirm);
                MapApp.ui.els.confirmModalCancelBtn.removeEventListener('click', onCancel);
                resolve(true);
            };
            const onCancel = () => {
                closeModal('confirmModal');
                MapApp.ui.els.confirmModalConfirmBtn.removeEventListener('click', onConfirm);
                MapApp.ui.els.confirmModalCancelBtn.removeEventListener('click', onCancel);
                resolve(false);
            };

            MapApp.ui.els.confirmModalConfirmBtn.addEventListener('click', onConfirm);
            MapApp.ui.els.confirmModalCancelBtn.addEventListener('click', onCancel);
        });
    },

    // Generic Input Modal
    showInput: (title, message, defaultValue = '', confirmText = 'Submit') => {
        return new Promise((resolve) => {
            MapApp.ui.els.inputModalTitle.textContent = title;
            MapApp.ui.els.inputModalMessage.textContent = message;
            MapApp.ui.els.inputModalField.value = defaultValue;
            MapApp.ui.els.inputModalConfirmBtn.textContent = confirmText;
            openModal('inputModal');

            const onConfirm = () => {
                const value = MapApp.ui.els.inputModalField.value;
                closeModal('inputModal');
                MapApp.ui.els.inputModalConfirmBtn.removeEventListener('click', onConfirm);
                MapApp.ui.els.inputModalCancelBtn.removeEventListener('click', onCancel);
                resolve(value);
            };
            const onCancel = () => {
                closeModal('inputModal');
                MapApp.ui.els.inputModalConfirmBtn.removeEventListener('click', onConfirm);
                MapApp.ui.els.inputModalCancelBtn.removeEventListener('click', onCancel);
                resolve(null);
            };

            MapApp.ui.els.inputModalConfirmBtn.addEventListener('click', onConfirm);
            MapApp.ui.els.inputModalCancelBtn.addEventListener('click', onCancel);
        });
    }
};