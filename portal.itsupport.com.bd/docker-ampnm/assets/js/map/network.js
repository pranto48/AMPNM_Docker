window.MapApp = window.MapApp || {};

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
            // Disable manipulation on this PHP-rendered map, as editing is now handled by the React component
            manipulation: { 
                enabled: false,
                addEdge: (edgeData, callback) => { callback(null); } // Prevent adding edges
            } 
        };
        MapApp.state.network = new vis.Network(container, data, options);
        
        // Event Handlers
        // Disable dragEnd and doubleClick for editing on this PHP-rendered map
        MapApp.state.network.on("dragEnd", async (params) => { 
            // No action for dragging on this map
        });
        MapApp.state.network.on("doubleClick", (params) => { 
            // No action for double click on this map
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