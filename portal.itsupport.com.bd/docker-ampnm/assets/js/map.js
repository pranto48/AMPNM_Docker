window.MapApp = window.MapApp || {};

MapApp.ui = {
    // DOM Elements
    els: {},

    // Cache DOM elements
    cacheElements: () => {
        // Use a helper to safely get elements
        const getEl = (id) => document.getElementById(id);

        MapApp.ui.els = {
            mapWrapper: getEl('network-map-wrapper'),
            mapSelector: getEl('mapSelector'),
            newMapBtn: getEl('newMapBtn'),
            renameMapBtn: getEl('renameMapBtn'),
            deleteMapBtn: getEl('deleteMapBtn'),
            mapContainer: getEl('map-container'),
            noMapsContainer: getEl('no-maps'),
            createFirstMapBtn: getEl('createFirstMapBtn'),
            currentMapName: getEl('currentMapName'),
            scanNetworkBtn: getEl('scanNetworkBtn'),
            refreshStatusBtn: getEl('refreshStatusBtn'),
            liveRefreshToggle: getEl('liveRefreshToggle'),
            addEdgeBtn: getEl('addEdgeBtn'),
            fullscreenBtn: getEl('fullscreenBtn'),
            exportBtn: getEl('exportBtn'),
            importBtn: getEl('importBtn'),
            importFile: getEl('importFile'),
            edgeModal: getEl('edgeModal'),
            edgeForm: getEl('edgeForm'),
            cancelEdgeBtn: getEl('cancelEdgeBtn'),
            scanModal: getEl('scanModal'),
            closeScanModal: getEl('closeScanModal'),
            scanForm: getEl('scanForm'),
            scanLoader: getEl('scanLoader'),
            scanResults: getEl('scanResults'),
            scanInitialMessage: getEl('scanInitialMessage'),
            mapSettingsBtn: getEl('mapSettingsBtn'),
            mapSettingsModal: getEl('mapSettingsModal'),
            mapSettingsForm: getEl('mapSettingsForm'),
            cancelMapSettingsBtn: getEl('cancelMapSettingsBtn'),
            resetMapBgBtn: getEl('resetMapBgBtn'),
            mapBgUpload: getEl('mapBgUpload'),
            placeDeviceBtn: getEl('placeDeviceBtn'),
            placeDeviceModal: getEl('placeDeviceModal'),
            closePlaceDeviceModal: getEl('closePlaceDeviceModal'),
            placeDeviceList: getEl('placeDeviceList'),
            placeDeviceLoader: getEl('placeDeviceLoader'),
            shareMapBtn: getEl('shareMapBtn'),
            // NEW Public View Elements
            publicViewToggle: getEl('publicViewToggle'),
            publicViewLinkContainer: getEl('publicViewLinkContainer'),
            publicViewLink: getEl('publicViewLink'),
            copyPublicLinkBtn: getEl('copyPublicLinkBtn'),
            // Custom Modals
            confirmModal: getEl('confirmModal'),
            confirmModalTitle: getEl('confirmModalTitle'),
            confirmModalMessage: getEl('confirmModalMessage'),
            confirmModalConfirmBtn: getEl('confirmModalConfirmBtn'),
            inputModal: getEl('inputModal'),
            inputModalTitle: getEl('inputModalTitle'),
            inputModalMessage: getEl('inputModalMessage'),
            inputModalField: getEl('inputModalField'),
            inputModalConfirmBtn: getEl('inputModalConfirmBtn'),
            // Add Device button on map page
            addDeviceBtn: getEl('addDeviceBtn'),
            // Map Settings specific elements
            mapBgColor: getEl('mapBgColor'),
            mapBgColorHex: getEl('mapBgColorHex'),
            mapBgImageUrl: getEl('mapBgImageUrl'),
            clearMapBgImageUrlBtn: getEl('clearMapBgImageUrlBtn'),
            mapBgUploadLoader: getEl('mapBgUploadLoader'),
            saveMapSettingsBtn: getEl('saveMapSettingsBtn'),
        };
    },

    populateLegend: () => {
        const legendContainer = MapApp.ui.els.mapWrapper ? MapApp.ui.els.mapWrapper.querySelector('#status-legend') : null;
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
        if (MapApp.ui.els.edgeId) MapApp.ui.els.edgeId.value = edge.id;
        if (MapApp.ui.els.connectionType) MapApp.ui.els.connectionType.value = edge.connection_type || 'cat5';
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
            if (!MapApp.ui.els.confirmModal) {
                console.error("Confirm modal elements not found.");
                resolve(false);
                return;
            }
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
            if (!MapApp.ui.els.inputModal) {
                console.error("Input modal elements not found.");
                resolve(null);
                return;
            }
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

MapApp.network = {
    initializeMap: () => {
        console.log('MapApp.network.initializeMap called. Current window.userRole:', window.userRole); // Debug log
        const container = document.getElementById('network-map');
        const contextMenu = document.getElementById('context-menu');
        MapApp.ui.populateLegend();
        const data = { nodes: MapApp.state.nodes, edges: MapApp.state.edges };
        const options = { 
            physics: false, 
            interaction: { hover: true }, 
            edges: { smooth: true, width: 2, font: { color: '#ffffff', size: 12, align: 'top', strokeWidth: 0 } }, 
            manipulation: { 
                enabled: true, // Enable manipulation by default, but control via buttons
                addNode: false, // We don't add nodes via manipulation UI
                deleteNode: (nodeData, callback) => {
                    if (window.userRole !== 'admin') { // Corrected condition
                        window.notyf.error('You do not have permission to delete devices.');
                        callback(null);
                        return;
                    }
                    MapApp.ui.showConfirm("Delete Device", "Are you sure you want to delete this device? This action cannot be undone.").then(async (confirmed) => {
                        if (confirmed) {
                            try {
                                await MapApp.api.post('delete_device', { id: nodeData.id });
                                window.notyf.success('Device deleted successfully.');
                                callback(nodeData); // Let vis.js remove the node
                            } catch (error) {
                                console.error('Failed to delete device:', error);
                                window.notyf.error(error.message || 'Failed to delete device.');
                                callback(null); // Do not delete the node
                            }
                        } else {
                            callback(null); // Do not delete the node
                        }
                    });
                },
                addEdge: async (edgeData, callback) => {
                    if (window.userRole !== 'admin') { // Corrected condition
                        window.notyf.error('You do not have permission to create connections.');
                        callback(null);
                        return;
                    }
                    if (!MapApp.state.currentMapId) {
                        window.notyf.error('No map selected. Cannot create connection.');
                        callback(null);
                        return;
                    }
                    try {
                        const newEdge = await MapApp.api.post('create_edge', { 
                            source_id: edgeData.from, 
                            target_id: edgeData.to, 
                            map_id: MapApp.state.currentMapId, 
                            connection_type: edgeData.type || 'cat5' // Default type
                        });
                        window.notyf.success('Connection saved.');
                        // Update edgeData with the ID from the database
                        edgeData.id = newEdge.id;
                        edgeData.connection_type = newEdge.connection_type; // This is important!
                        edgeData.label = newEdge.connection_type; // Set label for vis.js
                        callback(edgeData); // Let vis.js add the edge to the dataset
                        MapApp.state.network.disableEditMode(); // Exit add edge mode
                        
                        // Automatically open edit modal for the new edge
                        MapApp.ui.openEdgeModal(newEdge.id);

                    } catch (error) {
                        console.error('Failed to save connection:', error);
                        window.notyf.error(error.message || 'Failed to save connection.');
                        callback(null); // Do not add the edge
                    }
                },
                editEdge: {
                    editWithoutDrag: (edgeData, callback) => {
                        if (window.userRole !== 'admin') { // Corrected condition
                            window.notyf.error('You do not have permission to edit connections.');
                            callback(null);
                            return;
                        }
                        // Open custom modal for editing edge
                        MapApp.ui.openEdgeModal(edgeData.id);
                        // The modal will handle saving and calling callback(edgeData) or callback(null)
                        // For now, just call callback(null) to prevent default vis.js edit popup
                        callback(null); 
                    }
                },
                deleteEdge: (edgeData, callback) => {
                    if (window.userRole !== 'admin') { // Corrected condition
                        window.notyf.error('You do not have permission to delete connections.');
                        callback(null);
                        return;
                    }
                    MapApp.ui.showConfirm("Delete Connection", "Are you sure you want to delete this connection? This action cannot be undone.").then(async (confirmed) => {
                        if (confirmed) {
                            try {
                                await MapApp.api.post('delete_edge', { id: edgeData.id });
                                window.notyf.success('Connection deleted successfully.');
                                callback(edgeData); // Let vis.js remove the edge
                            } catch (error) {
                                console.error('Failed to delete connection:', error);
                                window.notyf.error(error.message || 'Failed to delete connection.');
                                callback(null); // Do not delete the edge
                            }
                        } else {
                            callback(null); // Do not delete the edge
                        }
                    });
                }
            } 
        };
        MapApp.state.network = new vis.Network(container, data, options);
        
        // Event Handlers
        // Disable dragEnd and doubleClick for editing on this PHP-rendered map, as manipulation handles it
        MapApp.state.network.on("dragEnd", async (params) => { 
            if (window.userRole !== 'admin') return; // Corrected condition
            if (params.nodes.length > 0) {
                const nodeId = params.nodes[0];
                const newPosition = MapApp.state.network.getPositions([nodeId])[nodeId];
                try {
                    await MapApp.api.post('update_device', { id: nodeId, updates: { x: newPosition.x, y: newPosition.y } });
                    window.notyf.success('Device position saved.');
                } catch (error) {
                    console.error('Failed to save device position:', error);
                    window.notyf.error(error.message || 'Failed to save device position.');
                }
            }
        });
        MapApp.state.network.on("doubleClick", (params) => { 
            if (window.userRole !== 'admin') return; // Corrected condition
            const nodeId = MapApp.state.network.getNodeAt(params.pointer.DOM);
            if (nodeId) {
                MapApp.ui.openDeviceModal(nodeId);
            }
        });

        const closeContextMenu = () => { contextMenu.style.display = 'none'; };
        MapApp.state.network.on("oncontext", (params) => {
            console.log('Context menu triggered. Node ID:', MapApp.state.network.getNodeAt(params.pointer.DOM), 'Edge ID:', MapApp.state.network.getEdgeAt(params.pointer.DOM), 'User Role:', window.userRole); // Debug log
            params.event.preventDefault();
            const nodeId = MapApp.state.network.getNodeAt(params.pointer.DOM);
            const edgeId = MapApp.state.network.getEdgeAt(params.pointer.DOM);

            let menuItems = ``;
            if (window.userRole === 'admin') {
                if (nodeId) {
                    menuItems += `<div class="context-menu-item" data-action="edit-device" data-id="${nodeId}"><i class="fas fa-edit fa-fw mr-2"></i>Edit Device</div>`;
                    menuItems += `<div class="context-menu-item" data-action="copy-device" data-id="${nodeId}"><i class="fas fa-copy fa-fw mr-2"></i>Copy Device</div>`;
                    menuItems += `<div class="context-menu-item text-red-400" data-action="delete-device" data-id="${nodeId}"><i class="fas fa-trash fa-fw mr-2"></i>Delete Device</div>`;
                } else if (edgeId) {
                    menuItems += `<div class="context-menu-item" data-action="edit-edge" data-id="${edgeId}"><i class="fas fa-edit fa-fw mr-2"></i>Edit Connection</div>`;
                    menuItems += `<div class="context-menu-item text-red-400" data-action="delete-edge" data-id="${edgeId}"><i class="fas fa-trash fa-fw mr-2"></i>Delete Connection</div>`;
                }
            } else {
                menuItems += `<div class="context-menu-item text-slate-500">No actions available</div>`;
            }
            
            contextMenu.innerHTML = menuItems;
            contextMenu.style.left = `${params.pointer.DOM.x}px`;
            contextMenu.style.top = `${params.pointer.DOM.y}px`;
            contextMenu.style.display = 'block';
            document.addEventListener('click', closeContextMenu, { once: true });
        });
        contextMenu.addEventListener('click', async (e) => {
            const target = e.target.closest('.context-menu-item');
            if (target) {
                const { action, id } = target.dataset;
                closeContextMenu();

                if (action === 'edit-device') {
                    MapApp.ui.openDeviceModal(id);
                } else if (action === 'copy-device') {
                    MapApp.mapManager.copyDevice(id);
                } else if (action === 'delete-device') {
                    const confirmed = await MapApp.ui.showConfirm("Delete Device", "Are you sure you want to delete this device? This action cannot be undone.");
                    if (confirmed) {
                        try {
                            await MapApp.api.post('delete_device', { id: id });
                            MapApp.state.nodes.remove({ id: id });
                            window.notyf.success('Device deleted successfully.');
                        } catch (error) {
                            console.error('Failed to delete device:', error);
                            window.notyf.error(error.message || 'Failed to delete device.');
                        }
                    }
                } else if (action === 'edit-edge') {
                    MapApp.ui.openEdgeModal(id);
                } else if (action === 'delete-edge') {
                    const confirmed = await MapApp.ui.showConfirm("Delete Connection", "Are you sure you want to delete this connection? This action cannot be undone.");
                    if (confirmed) {
                        try {
                            await MapApp.api.post('delete_edge', { id: id });
                            MapApp.state.edges.remove({ id: id });
                            window.notyf.success('Connection deleted successfully.');
                        } catch (error) {
                            console.error('Failed to delete connection:', error);
                            window.notyf.error(error.message || 'Failed to delete connection.');
                        }
                    }
                }
            }
        });
    }
};