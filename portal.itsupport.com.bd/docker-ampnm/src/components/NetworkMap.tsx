import { useState, useEffect, useCallback, useRef } from 'react';
import { Edge } from 'reactflow';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { importMap, MapData, NetworkDevice } from '@/services/networkDeviceService';
import { useNetworkMapData } from '@/hooks/useNetworkMapData';
import { useNetworkMapInteractions } from '@/hooks/useNetworkMapInteractions';
import { MapControls } from './MapControls';
import { NetworkMapCanvas } from './NetworkMapCanvas';
import { Button } from '@/components/ui/button';

// Declare SoundManager globally
declare global {
  interface Window {
    SoundManager: {
      play: (soundName: string) => void;
    };
  }
}

const NetworkMap = ({ devices, onMapUpdate }: { devices: NetworkDevice[]; onMapUpdate: (mapId?: string) => void }) => {
  const importInputRef = useRef<HTMLInputElement>(null);
  const mapWrapperRef = useRef<HTMLDivElement>(null);

  const [liveRefreshEnabled, setLiveRefreshEnabled] = useState(false);
  const [isFullScreen, setIsFullScreen] = useState(false);

  // Get user role from global scope
  const userRole = (window as any).userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  // Use custom hook for map data and selection
  const { maps, currentMapId, handleMapChange, fetchMaps } = useNetworkMapData({ onMapUpdate });

  // Use custom hook for ReactFlow interactions
  const {
    nodes,
    edges,
    onNodesChange,
    onEdgesChange,
    onConnect,
    nodeTypes,
    onNodeDragStop,
    onEdgeClick,
    styledEdges,
    handleDelete,
    handleSaveEdge,
    isEdgeEditorOpen,
    setIsEdgeEditorOpen,
    editingEdge,
    setEditingEdge,
  } = useNetworkMapInteractions({ currentMapId, devices, isAdmin, onMapUpdate });

  // Ref to store previous devices for status comparison
  const prevDevicesRef = useRef<NetworkDevice[]>([]);

  // Implement polling for live status updates for all users
  useEffect(() => {
    let pollingInterval: NodeJS.Timeout | undefined;
    if (liveRefreshEnabled && currentMapId) {
      pollingInterval = setInterval(() => {
        onMapUpdate(currentMapId);
      }, 15000); // Poll every 15 seconds
    }

    return () => {
      if (pollingInterval) clearInterval(pollingInterval);
    };
  }, [liveRefreshEnabled, currentMapId, onMapUpdate]);

  // Effect to detect status changes and play sounds
  useEffect(() => {
    const prevDevicesMap = new Map(prevDevicesRef.current.map(d => [d.id, d.status]));

    devices.forEach(currentDevice => {
      const prevStatus = prevDevicesMap.get(currentDevice.id);
      const newStatus = currentDevice.status;

      if (prevStatus && newStatus && prevStatus !== newStatus) {
        if (window.SoundManager) {
          if (newStatus === 'online' && (prevStatus === 'offline' || prevStatus === 'critical' || prevStatus === 'warning')) {
            window.SoundManager.play('online');
          } else if (newStatus === 'warning') {
            window.SoundManager.play('warning');
          } else if (newStatus === 'critical') {
            window.SoundManager.play('critical');
          } else if (newStatus === 'offline') {
            window.SoundManager.play('offline');
          }
        }
      }
    });

    prevDevicesRef.current = devices;
  }, [devices]);

  const handleExport = async () => {
    if (!isAdmin) {
      showError('You do not have permission to export maps.');
      return;
    }
    if (!currentMapId) {
      showError('No map selected to export.');
      return;
    }
    const exportData: MapData = {
      devices: devices.map(({ user_id, status, last_ping, last_ping_result, ...rest }) => ({
        ...rest,
        ip_address: rest.ip_address || null,
        position_x: rest.position_x || null,
        position_y: rest.position_y || null,
        ping_interval: rest.ping_interval || null,
        icon_size: rest.icon_size || null,
        name_text_size: rest.name_text_size || null,
        check_port: rest.check_port || null,
        description: rest.description || null,
        warning_latency_threshold: rest.warning_latency_threshold || null,
        warning_packetloss_threshold: rest.warning_packetloss_threshold || null,
        critical_latency_threshold: rest.critical_latency_threshold || null,
        critical_packetloss_threshold: rest.critical_packetloss_threshold || null,
        show_live_ping: rest.show_live_ping || false,
        map_id: rest.map_id || null,
      })),
      edges: edges.map(({ id, source, target, data }) => ({
        source,
        target,
        connection_type: data.connection_type || 'cat5',
      })),
    };
    const jsonString = JSON.stringify(exportData, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'network-map.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showSuccess('Map exported successfully!');
  };

  const handleImportClick = () => {
    if (!isAdmin) {
      showError('You do not have permission to import maps.');
      return;
    }
    if (!currentMapId) {
      showError('Please select a map to import into.');
      return;
    }
    importInputRef.current?.click();
  };

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    if (!isAdmin) {
      showError('You do not have permission to import maps.');
      return;
    }
    const file = event.target.files?.[0];
    if (!file) return;
    if (!window.confirm('Are you sure you want to import this map? This will overwrite your current map.')) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      const toastId = showLoading('Importing map...');
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        if (!mapData.devices || !mapData.edges) throw new Error('Invalid map file format.');
        await importMap(currentMapId!, mapData); // Pass currentMapId
        dismissToast(toastId);
        showSuccess('Map imported successfully!');
        onMapUpdate(currentMapId); // Refresh the map data
      } catch (error: any) {
        dismissToast(toastId);
        console.error('Failed to import map:', error);
        showError(error.message || 'Failed to import map.');
      } finally {
        if (importInputRef.current) importInputRef.current.value = '';
      }
    };
    reader.readAsText(file);
  };

  const handleShareMap = async () => {
    if (!currentMapId) {
      showError('No map selected to share.');
      return;
    }

    const shareUrl = `http://192.168.20.5:2266/public_map.php?map_id=${currentMapId}`;

    try {
      await navigator.clipboard.writeText(shareUrl);
      showSuccess('Share link copied to clipboard!');
    } catch (err) {
      console.error('Failed to copy share link:', err);
      showError('Failed to copy share link. Please copy manually: ' + shareUrl);
    }
  };

  const handleToggleFullscreen = () => {
    if (!mapWrapperRef.current) return;

    if (!document.fullscreenElement) {
      mapWrapperRef.current.requestFullscreen().catch((err) => {
        showError(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
      });
    } else {
      document.exitFullscreen();
    }
  };

  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullScreen(!!document.fullscreenElement);
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  const currentMap = maps.find(m => m.id === currentMapId);
  const backgroundStyle = currentMap?.background_image_url
    ? { backgroundImage: `url(${currentMap.background_image_url})`, backgroundSize: 'cover', backgroundPosition: 'center' }
    : currentMap?.background_color
    ? { backgroundColor: currentMap.background_color }
    : { backgroundColor: '#1e293b' }; // Default if nothing set

  return (
    <div className="space-y-4">
      <MapControls
        maps={maps}
        currentMapId={currentMapId}
        handleMapChange={handleMapChange}
        onRefresh={() => onMapUpdate(currentMapId)}
        liveRefreshEnabled={liveRefreshEnabled}
        setLiveRefreshEnabled={setLiveRefreshEnabled}
        onExport={handleExport}
        onImportClick={handleImportClick}
        onFileChange={handleFileChange}
        onShareMap={handleShareMap}
        onToggleFullscreen={handleToggleFullscreen}
        isFullScreen={isFullScreen}
        isAdmin={isAdmin}
      />

      {maps.length === 0 && (
        <div className="absolute inset-0 flex flex-col items-center justify-center text-white text-center p-4">
          <h2 className="text-2xl font-bold mb-2">No Network Maps Available</h2>
          <p className="text-lg mb-4">
            {isAdmin ? 'Create a new map to start visualizing your network.' : 'Please ask an administrator to create a map.'}
          </p>
          {isAdmin && (
            <Button onClick={() => console.log('Create First Map')} className="mt-4">Create First Map</Button>
          )}
        </div>
      )}
      {currentMapId && maps.length > 0 && (
        <NetworkMapCanvas
          nodes={nodes}
          edges={styledEdges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          nodeTypes={nodeTypes}
          onNodeDragStop={onNodeDragStop}
          onEdgeClick={onEdgeClick}
          isAdmin={isAdmin}
          backgroundStyle={backgroundStyle}
        />
      )}
      {isEdgeEditorOpen && (
        <EdgeEditorDialog
          isOpen={isEdgeEditorOpen}
          onClose={() => setIsEdgeEditorOpen(false)}
          onSave={handleSaveEdge}
          edge={editingEdge}
        />
      )}
    </div>
  );
};

export default NetworkMap;