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
            // Add Device button on map page
            addDeviceBtn: document.getElementById('addDeviceBtn'),
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
        MapApp.ui.els.edgeId.value = edge.id;
        MapApp.ui.els.connectionType.value = edge.connection_type || 'cat5';
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

function initMap() {
    MapApp.ui.cacheElements();

    const { els } = MapApp.ui;
    const { api } = MapApp;
    const { state } = MapApp;
    const { mapManager } = MapApp;
    const { deviceManager } = MapApp;

    window.cleanup = () => {
        if (state.animationFrameId) {
            cancelAnimationFrame(state.animationFrameId);
            state.animationFrameId = null;
        }
        Object.values(state.pingIntervals).forEach(clearInterval);
        state.pingIntervals = {};
        if (state.globalRefreshIntervalId) {
            clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
        }
        if (state.network) {
            state.network.destroy();
            state.network = null;
        }
        window.cleanup = null;
    };

    // Event Listeners Setup
    if (window.userRole === 'admin') {
        els.edgeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = els.edgeId.value;
            const connection_type = els.connectionType.value;
            try {
                await api.post('update_edge', { id, connection_type });
                closeModal('edgeModal');
                state.edges.update({ id, connection_type, label: connection_type });
                window.notyf.success('Connection updated.');
            } catch (error) {
                console.error("Failed to update connection:", error);
                window.notyf.error(error.message || "An error occurred while updating connection.");
            }
        });
    } else {
        if (els.edgeForm) {
            els.edgeForm.querySelectorAll('select, button').forEach(el => el.disabled = true);
            els.edgeForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to edit connections.</p>');
        }
    }

    if (window.userRole === 'admin') {
        els.scanForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const subnet = els.subnetInput.value;
            if (!subnet) return;
            els.scanInitialMessage.classList.add('hidden');
            els.scanResults.innerHTML = '';
            els.scanLoader.classList.remove('hidden');
            try {
                const result = await api.post('scan_network', { subnet });
                if (result.devices && result.devices.length > 0) {
                    els.scanResults.innerHTML = result.devices.map(device => `
                        <div class="flex items-center justify-between p-2 border-b border-slate-700">
                            <div>
                                <div class="font-mono text-white">${device.ip}</div>
                                <div class="text-sm text-slate-400">${device.hostname || 'N/A'}</div>
                            </div>
                            <button class="add-scanned-device-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-ip="${device.ip}" data-name="${device.hostname || device.ip}">Add</button>
                        </div>
                    `).join('');
                } else {
                    els.scanResults.innerHTML = '<p class="text-center text-slate-500 py-4">No devices found.</p>';
                }
            } catch (error) {
                els.scanResults.innerHTML = '<p class="text-center text-red-400 py-4">Scan failed. Ensure nmap is installed.</p>';
            } finally {
                els.scanLoader.classList.add('hidden');
            }
        });

        els.scanResults.addEventListener('click', async (e) => {
            const targetButton = e.target.closest('.add-scanned-device-btn');
            if (targetButton) {
                const { ip, name } = targetButton.dataset;
                
                // Create device directly
                const toastId = window.notyf.open({ type: 'info', message: `Adding device "${name}"...`, duration: 0 });
                try {
                    const createdDevice = await api.post('create_device', {
                        name: name,
                        ip: ip,
                        type: 'server', // Default type
                        map_id: state.currentMapId,
                        x: 100, // Default position
                        y: 100,
                    });
                    window.notyf.dismiss(toastId);
                    window.notyf.success(`Device "${name}" added to map.`);
                    
                    // Add to map visually
                    const baseNode = {
                        id: createdDevice.id, label: createdDevice.name, title: MapApp.utils.buildNodeTitle(createdDevice),
                        x: createdDevice.x, y: createdDevice.y,
                        font: { color: 'white', size: parseInt(createdDevice.name_text_size) || 14, multi: true },
                        deviceData: createdDevice
                    };
                    let visNode;
                    if (createdDevice.icon_url) {
                        visNode = { ...baseNode, shape: 'image', image: createdDevice.icon_url, size: (parseInt(createdDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
                    } else if (createdDevice.type === 'box') {
                        visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
                    } else {
                        visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[createdDevice.type] || MapApp.config.iconMap.other, size: parseInt(createdDevice.icon_size) || 50, color: MapApp.config.statusColorMap[createdDevice.status] || MapApp.config.statusColorMap.unknown } };
                    }
                    state.nodes.add(visNode);

                    targetButton.textContent = 'Added';
                    targetButton.disabled = true;
                    targetButton.classList.remove('bg-cyan-600/50', 'hover:bg-cyan-600/80', 'text-cyan-300');
                    targetButton.classList.add('bg-green-600/50', 'text-green-300');

                } catch (err) {
                    window.notyf.dismiss(toastId);
                    console.error('Failed to add scanned device:', err);
                    window.notyf.error('Failed to add scanned device: ' + err.message);
                }
            }
        });
    } else {
        if (els.scanForm) {
            els.scanForm.querySelectorAll('input, button').forEach(el => el.disabled = true);
            els.scanForm.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to scan the network.</p>');
        }
    }

    els.refreshStatusBtn.addEventListener('click', async () => {
        els.refreshStatusBtn.disabled = true;
        await deviceManager.performBulkRefresh();
        if (!els.liveRefreshToggle.checked) els.refreshStatusBtn.disabled = false;
    });

    els.liveRefreshToggle.addEventListener('change', (e) => {
        if (e.target.checked) {
            window.notyf.info(`Live status enabled. Updating every ${MapApp.config.REFRESH_INTERVAL_SECONDS} seconds.`);
            els.refreshStatusBtn.disabled = true;
            deviceManager.performBulkRefresh();
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            if (state.globalRefreshIntervalId) clearInterval(state.globalRefreshIntervalId);
            state.globalRefreshIntervalId = null;
            els.refreshStatusBtn.disabled = false;
            window.notyf.info('Live status disabled.');
        }
    });

    if (window.userRole === 'admin') {
        els.exportBtn.addEventListener('click', async () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected to export.');
                return;
            }
            const confirmed = await MapApp.ui.showConfirm("Export Map", "Are you sure you want to export the current map's devices and connections?", "Export");
            if (!confirmed) return;

            const mapName = els.mapSelector.options[els.mapSelector.selectedIndex].text;
            const devices = state.nodes.get({ fields: ['id', 'deviceData'] }).map(node => ({
                id: node.id,
                ...node.deviceData
            }));
            const edges = state.edges.get({ fields: ['from', 'to', 'connection_type'] }).map(edge => ({
                source_id: edge.from,
                target_id: edge.to,
                connection_type: edge.connection_type
            }));
            const exportData = { devices, edges };
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", `${mapName.replace(/\s+/g, '_')}_export.json`);
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
            window.notyf.success('Map exported successfully.');
        });

        els.importBtn.addEventListener('click', () => els.importFile.click());
        els.importFile.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            if (!state.currentMapId) {
                window.notyf.error('No map selected. Cannot import map data.');
                els.importFile.value = '';
                return;
            }

            const confirmed = await MapApp.ui.showConfirm("Import Map", "Are you sure you want to import this map? This will OVERWRITE all existing devices and connections on the current map.", "Import");
            if (!confirmed) {
                els.importFile.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = async (event) => {
                const toastId = window.notyf.open({ type: 'info', message: 'Importing map...', duration: 0 });
                try {
                    const data = JSON.parse(event.target.result);
                    if (!data.devices || !data.edges) throw new Error('Invalid map file format.');
                    
                    await api.post('import_map', { map_id: state.currentMapId, ...data });
                    window.notyf.dismiss(toastId);
                    window.notyf.success('Map imported successfully!');
                    await mapManager.switchMap(state.currentMapId); // Reload map to show imported data
                } catch (err) {
                    window.notyf.dismiss(toastId);
                    window.notyf.error('Failed to import map: ' + err.message);
                } finally {
                    els.importFile.value = '';
                }
            };
            reader.readAsText(file);
        });
    } else {
        if (els.exportBtn) els.exportBtn.disabled = true;
        if (els.importBtn) els.importBtn.disabled = true;
    }

    els.fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) els.mapWrapper.requestFullscreen();
        else document.exitFullscreen();
    });
    document.addEventListener('fullscreenchange', () => {
        const icon = els.fullscreenBtn.querySelector('i');
        icon.classList.toggle('fa-expand', !document.fullscreenElement);
        icon.classList.toggle('fa-compress', !!document.fullscreenElement);
    });

    if (window.userRole === 'admin') {
        els.newMapBtn.addEventListener('click', mapManager.createMap);
        els.createFirstMapBtn.addEventListener('click', mapManager.createMap);
        els.renameMapBtn.addEventListener('click', async () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected to rename.');
                return;
            }
            const selectedOption = els.mapSelector.options[els.mapSelector.selectedIndex];
            const currentName = selectedOption.text;
            const newName = await MapApp.ui.showInput("Rename Map", "Enter a new name for the map:", currentName);
        
            if (newName === null) { // User clicked cancel
                window.notyf.info("Map renaming cancelled.");
                return;
            }
            const trimmedName = newName.trim();
            if (trimmedName === '') {
                window.notyf.error("Map name cannot be empty.");
                return;
            }
            if (trimmedName === currentName) {
                window.notyf.info("Map name is the same.");
                return;
            }
            
            try {
                await api.post('update_map', { id: state.currentMapId, updates: { name: trimmedName } });
                selectedOption.text = trimmedName;
                els.currentMapName.textContent = trimmedName;
                window.notyf.success('Map renamed successfully.');
            } catch (error) {
                console.error("Failed to rename map:", error);
                window.notyf.error(error.message || "Could not rename map.");
            }
        });
        els.deleteMapBtn.addEventListener('click', async () => {
            if (!state.currentMapId) {
                window.notyf.error('No map selected to delete.');
                return;
            }
            const mapName = els.mapSelector.options[els.mapSelector.selectedIndex].text;
            const confirmed = await MapApp.ui.showConfirm("Delete Map", `Are you sure you want to delete map "${mapName}"? This action cannot be undone and will delete all devices and connections on this map.`, "Delete");
            if (confirmed) {
                try {
                    await api.post('delete_map', { id: state.currentMapId });
                    const firstMapId = await mapManager.loadMaps();
                    await mapManager.switchMap(firstMapId);
                    window.notyf.success('Map deleted.');
                } catch (error) {
                    console.error("Failed to delete map:", error);
                    window.notyf.error(error.message || "Could not delete map.");
                }
            }
        });
    } else {
        if (els.newMapBtn) els.newMapBtn.disabled = true;
        if (els.createFirstMapBtn) els.createFirstMapBtn.disabled = true;
        if (els.renameMapBtn) els.renameMapBtn.disabled = true;
        if (els.deleteMapBtn) els.deleteMapBtn.disabled = true;
        const mapSelectionControls = document.querySelector('#map-selection .flex.gap-4');
        if (mapSelectionControls) {
            mapSelectionControls.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage maps.</p>');
        }
    }

    els.mapSelector.addEventListener('change', (e) => mapManager.switchMap(e.target.value));
    
    if (window.userRole === 'admin') {
        els.addEdgeBtn.addEventListener('click', () => {
            state.network.addEdgeMode();
            window.notyf.info('Click on a node to start a connection.');
        });
    } else {
        if (els.addEdgeBtn) els.addEdgeBtn.disabled = true;
    }

    els.cancelEdgeBtn.addEventListener('click', () => closeModal('edgeModal'));
    els.scanNetworkBtn.addEventListener('click', () => openModal('scanModal'));
    els.closeScanModal.addEventListener('click', () => closeModal('scanModal'));

    if (window.userRole === 'admin') {
        els.placeDeviceBtn.addEventListener('click', async () => {
            openModal('placeDeviceModal');
            els.placeDeviceLoader.classList.remove('hidden');
            els.placeDeviceList.innerHTML = '';
            try {
                const unmappedDevices = await api.get('get_devices', { unmapped: true });
                if (unmappedDevices.devices && unmappedDevices.devices.length > 0) {
                    els.placeDeviceList.innerHTML = unmappedDevices.devices.map(device => `
                        <div class="flex items-center justify-between p-2 border-b border-slate-700 hover:bg-slate-700/50" data-device-id="${device.id}">
                            <div>
                                <div class="font-medium text-white">${device.name}</div>
                                <div class="text-sm text-slate-400 font-mono">${device.ip || 'No IP'}</div>
                            </div>
                            <button class="place-device-item-btn px-3 py-1 bg-cyan-600/50 text-cyan-300 rounded-lg hover:bg-cyan-600/80 text-sm" data-id="${device.id}">
                                Place
                            </button>
                        </div>
                    `).join('');
                } else {
                    els.placeDeviceList.innerHTML = '<p class="text-center text-slate-500 py-4">No unassigned devices found.</p>';
                }
            } catch (error) {
                console.error('Failed to load unmapped devices:', error);
                window.notyf.error('Could not load unassigned devices.');
                els.placeDeviceList.innerHTML = '<p class="text-center text-red-400 py-4">Could not load devices.</p>';
            } finally {
                els.placeDeviceLoader.classList.add('hidden');
            }
        });
        els.closePlaceDeviceModal.addEventListener('click', () => closeModal('placeDeviceModal'));
        els.placeDeviceList.addEventListener('click', async (e) => {
            const targetButton = e.target.closest('.place-device-item-btn');
            if (targetButton) {
                const deviceId = targetButton.dataset.id;
                targetButton.disabled = true;
                targetButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const viewPosition = state.network.getViewPosition();
                const canvasPosition = state.network.canvas.DOMtoCanvas(viewPosition);

                try {
                    const updatedDevice = await api.post('update_device', {
                        id: deviceId,
                        updates: { map_id: state.currentMapId, x: canvasPosition.x, y: canvasPosition.y }
                    });

                    const baseNode = {
                        id: updatedDevice.id, label: updatedDevice.name, title: MapApp.utils.buildNodeTitle(updatedDevice),
                        x: updatedDevice.x, y: updatedDevice.y,
                        font: { color: 'white', size: parseInt(updatedDevice.name_text_size) || 14, multi: true },
                        deviceData: updatedDevice
                    };
                    let visNode;
                    if (updatedDevice.icon_url) {
                        visNode = { ...baseNode, shape: 'image', image: updatedDevice.icon_url, size: (parseInt(updatedDevice.icon_size) || 50) / 2, color: { border: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown, background: 'transparent' }, borderWidth: 3 };
                    } else if (updatedDevice.type === 'box') {
                        visNode = { ...baseNode, shape: 'box', color: { background: 'rgba(49, 65, 85, 0.5)', border: '#475569' }, margin: 20, level: -1 };
                    } else {
                        visNode = { ...baseNode, shape: 'icon', icon: { face: "'Font Awesome 6 Free'", weight: "900", code: MapApp.config.iconMap[updatedDevice.type] || MapApp.config.iconMap.other, size: parseInt(updatedDevice.icon_size) || 50, color: MapApp.config.statusColorMap[updatedDevice.status] || MapApp.config.statusColorMap.unknown } };
                    }
                    state.nodes.add(visNode);
                    
                    window.notyf.success(`Device "${updatedDevice.name}" placed on map.`);
                    
                    // Remove the device from the list in the modal
                    const deviceRow = targetButton.closest('[data-device-id]');
                    if (deviceRow) {
                        deviceRow.remove();
                    }
                    // Check if the list is now empty
                    if (els.placeDeviceList.children.length === 0) {
                        els.placeDeviceList.innerHTML = '<p class="text-center text-slate-500 py-4">No unassigned devices found.</p>';
                    }
                } catch (error) {
                    console.error('Failed to place device:', error);
                    window.notyf.error('Failed to place device.');
                }
            }
        });
    } else {
        if (els.placeDeviceBtn) els.placeDeviceBtn.disabled = true;
    }

    if (window.userRole === 'admin') {
        els.mapSettingsBtn.addEventListener('click', () => {
            const currentMap = state.maps.find(m => m.id == state.currentMapId);
            if (currentMap) {
                els.mapBgColor.value = currentMap.background_color || '#1e293b';
                els.mapBgColorHex.value = currentMap.background_color || '#1e293b';
                els.mapBgImageUrl.value = currentMap.background_image_url || '';
                els.publicViewToggle.checked = currentMap.public_view_enabled;
                mapManager.updatePublicViewLink(currentMap.id, currentMap.public_view_enabled);
                openModal('mapSettingsModal');
            }
        });
        els.cancelMapSettingsBtn.addEventListener('click', () => closeModal('mapSettingsModal'));
        els.mapBgColor.addEventListener('input', (e) => {
            els.mapBgColorHex.value = e.target.value;
        });
        els.mapBgColorHex.addEventListener('input', (e) => {
            els.mapBgColor.value = e.target.value;
        });

        els.publicViewToggle.addEventListener('change', () => {
            mapManager.updatePublicViewLink(state.currentMapId, els.publicViewToggle.checked);
        });

        els.copyPublicLinkBtn.addEventListener('click', async () => {
            const publicLink = els.publicViewLink.value;
            if (publicLink) {
                try {
                    await navigator.clipboard.writeText(publicLink);
                    window.notyf.success('Public link copied to clipboard!');
                } catch (err) {
                    console.error('Failed to copy public link:', err);
                    window.notyf.error('Failed to copy public link. Please copy manually.');
                }
            }
        });

        els.mapSettingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const updates = {
                background_color: els.mapBgColorHex.value,
                background_image_url: els.mapBgImageUrl.value,
                public_view_enabled: els.publicViewToggle.checked
            };
            try {
                await api.post('update_map', { id: state.currentMapId, updates });
                await mapManager.loadMaps();
                await mapManager.switchMap(state.currentMapId);
                closeModal('mapSettingsModal');
                window.notyf.success('Map settings saved.');
            } catch (error) {
                console.error("Failed to save map settings:", error);
                window.notyf.error(error.message || "Could not save map settings.");
            }
        });
        els.resetMapBgBtn.addEventListener('click', async () => {
            const confirmed = await MapApp.ui.showConfirm("Reset Map Background", "Are you sure you want to reset the map background and disable public view? This action cannot be undone.", "Reset");
            if (!confirmed) return;

            try {
                const updates = { background_color: null, background_image_url: null, public_view_enabled: false };
                await api.post('update_map', { id: state.currentMapId, updates });
                await mapManager.loadMaps();
                await mapManager.switchMap(state.currentMapId);
                closeModal('mapSettingsModal');
                window.notyf.success('Map background and public view reset to default.');
            } catch (error) {
                console.error("Failed to reset map background:", error);
                window.notyf.error(error.message || "Could not reset map background.");
            }
        });
        els.mapBgUpload.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            const loader = els.mapBgUploadLoader;
            loader.classList.remove('hidden');
            const formData = new FormData();
            formData.append('map_id', state.currentMapId);
            formData.append('backgroundFile', file);
            try {
                const res = await fetch(`${MapApp.config.API_URL}?action=upload_map_background`, { method: 'POST', body: formData });
                const result = await res.json();
                if (result.success) {
                    els.mapBgImageUrl.value = result.url;
                    window.notyf.success('Image uploaded. Click Save to apply.');
                } else { throw new Error(result.error); }
            } catch (error) {
                window.notyf.error('Upload failed: ' + error.message);
            } finally {
                loader.classList.add('hidden');
                e.target.value = '';
            }
        });
    } else {
        if (els.mapSettingsBtn) els.mapSettingsBtn.disabled = true;
    }

    els.shareMapBtn.addEventListener('click', async () => {
        if (!state.currentMapId) {
            window.notyf.error('No map selected to share.');
            return;
        }
        const shareUrl = `http://192.168.20.5:2266/public_map.php?map_id=${state.currentMapId}`;
        try {
            await navigator.clipboard.writeText(shareUrl);
            window.notyf.success('Share link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy share link:', err);
            window.notyf.error('Failed to copy share link. Please copy manually: ' + shareUrl);
        }
    });

    (async () => {
        if (window.userRole === 'viewer') {
            els.liveRefreshToggle.checked = true;
            els.liveRefreshToggle.disabled = true;
            els.refreshStatusBtn.disabled = true;
            deviceManager.performBulkRefresh();
            state.globalRefreshIntervalId = setInterval(deviceManager.performBulkRefresh, MapApp.config.REFRESH_INTERVAL_SECONDS * 1000);
        } else {
            els.liveRefreshToggle.checked = false;
            els.liveRefreshToggle.disabled = false;
        }

        const urlParams = new URLSearchParams(window.location.search);
        const mapToLoad = urlParams.get('map_id');
        
        const firstMapId = await mapManager.loadMaps();
        const initialMapId = mapToLoad || firstMapId;
        
        if (initialMapId) {
            els.mapSelector.value = initialMapId;
            await mapManager.switchMap(initialMapId);
            // Update the addDeviceBtn href with the current map_id
            if (els.addDeviceBtn && window.userRole === 'admin') {
                els.addDeviceBtn.href = `create-device.php?map_id=${initialMapId}`;
            }
            const deviceToEdit = urlParams.get('edit_device_id');
            if (deviceToEdit && state.nodes.get(deviceToEdit)) {
                window.notyf.info('To edit a device, click the "Edit" option from its context menu.');
                const newUrl = window.location.pathname + `?map_id=${initialMapId}`;
                history.replaceState(null, '', newUrl);
            }
        }

        if (window.userRole === 'viewer') {
            els.newMapBtn.disabled = true;
            els.renameMapBtn.disabled = true;
            els.deleteMapBtn.disabled = true;
            els.placeDeviceBtn.disabled = true;
            if (els.addDeviceBtn) els.addDeviceBtn.style.display = 'none';
            els.addEdgeBtn.disabled = true;
            els.exportBtn.disabled = true;
            els.importBtn.disabled = true;
            els.mapSettingsBtn.disabled = true;
            els.scanNetworkBtn.disabled = true;
            if (els.createFirstMapBtn) els.createFirstMapBtn.disabled = true;
            
            const mapSelectionControls = document.querySelector('#map-selection .flex.gap-4');
            if (mapSelectionControls) {
                mapSelectionControls.insertAdjacentHTML('afterend', '<p class="text-red-400 text-sm mt-2">You do not have permission to manage maps or devices.</p>');
            }
        }
    })();
}