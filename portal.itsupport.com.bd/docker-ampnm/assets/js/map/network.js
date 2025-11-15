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
                menuItems += `<div class="context-menu-item text-yellow-400"><i class="fas fa-info-circle fa-fw mr-2"></i>Use Dashboard Map for Editing</div>`;
                menuItems += `<div class="context-menu-item" data-action="go-to-dashboard-map"><i class="fas fa-arrow-right fa-fw mr-2"></i>Go to Dashboard Map</div>`;
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
                const { action } = target.dataset;
                closeContextMenu();

                if (action === 'go-to-dashboard-map') {
                    window.location.href = 'index.php#map'; // Redirect to the dashboard map tab
                }
            }
        });
    }
};