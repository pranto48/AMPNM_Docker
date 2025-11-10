<?php
require_once 'includes/auth_check.php';
include 'header.php';

// Get user role from session for conditional rendering
$user_role = $_SESSION['user_role'] ?? 'viewer';
$is_admin = ($user_role === 'admin');
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <div id="map-selection" class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-3xl font-bold text-white">Network Map</h1>
                <div class="flex gap-4">
                    <select id="mapSelector" class="bg-slate-900 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500"></select>
                    <?php if ($is_admin): ?>
                        <button id="newMapBtn" class="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700"><i class="fas fa-plus mr-2"></i>New Map</button>
                        <button id="renameMapBtn" class="px-4 py-2 bg-yellow-600/80 text-white rounded-lg hover:bg-yellow-700"><i class="fas fa-edit mr-2"></i>Rename Map</button>
                        <button id="deleteMapBtn" class="px-4 py-2 bg-red-600/80 text-white rounded-lg hover:bg-red-700"><i class="fas fa-trash mr-2"></i>Delete Map</button>
                    <?php endif; ?>
                    <button id="shareMapBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-share-alt mr-2"></i>Share Map</button>
                </div>
            </div>
        </div>

        <div id="map-container" class="hidden">
            <div id="map-controls" class="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
                <div class="flex items-center justify-between">
                    <h2 id="currentMapName" class="text-xl font-semibold text-white"></h2>
                    <div class="flex items-center gap-2">
                        <button id="scanNetworkBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Scan Network" <?= $is_admin ? '' : 'disabled' ?>><i class="fas fa-search"></i></button>
                        <button id="refreshStatusBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Refresh Device Statuses"><i class="fas fa-sync-alt"></i></button>
                        
                        <div class="flex items-center space-x-2 pl-2 ml-2 border-l border-slate-700">
                            <label for="liveRefreshToggle" class="text-sm text-slate-400 select-none cursor-pointer">Live Status</label>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="liveRefreshToggle" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-600 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                            </label>
                        </div>

                        <div class="pl-2 ml-2 border-l border-slate-700 flex items-center gap-2">
                            <?php if ($is_admin): ?>
                                <button id="placeDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Place Existing Device"><i class="fas fa-download"></i></button>
                                <a href="create-device.php" id="addDeviceBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add New Device"><i class="fas fa-plus"></i></a>
                                <button id="addEdgeBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Add Connection"><i class="fas fa-project-diagram"></i></button>
                                <button id="exportBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Export Map"><i class="fas fa-file-export"></i></button>
                                <button id="importBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Import Map"><i class="fas fa-file-import"></i></button>
                                <input type="file" id="importFile" class="hidden" accept=".json">
                                <button id="mapSettingsBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Map Settings"><i class="fas fa-cog"></i></button>
                            <?php endif; ?>
                            <button id="fullscreenBtn" class="px-3 py-2 bg-slate-700 text-slate-300 rounded-lg hover:bg-slate-600" title="Toggle Fullscreen"><i class="fas fa-expand"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="network-map-wrapper">
                <div id="network-map"></div>
                <div id="context-menu" class="context-menu"></div>
                <div id="status-legend">
                    <!-- Legend items are now generated by map.js -->
                </div>
            </div>
        </div>
        
        <div id="no-maps" class="text-center py-16 bg-slate-800 border border-slate-700 rounded-lg hidden">
            <i class="fas fa-map-signs text-slate-600 text-5xl mb-4"></i>
            <h2 class="text-2xl font-bold text-white mb-2">No Network Maps Found</h2>
            <p class="text-slate-400 mb-6">Create a map to start visualizing your network.</p>
            <?php if ($is_admin): ?>
                <button id="createFirstMapBtn" class="px-6 py-3 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 text-lg">Create Your First Map</button>
            <?php else: ?>
                <p class="text-red-400 text-sm mt-2">Only admin users can create new maps.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- The React NetworkMap component now handles its own modals/dialogs -->
    <!-- Old modals (edgeModal, scanModal, mapSettingsModal, placeDeviceModal) are removed -->
</main>

<?php include 'footer.php'; ?>