import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import ReactFlow, {
  MiniMap,
  Controls,
  Background,
  useNodesState,
  useEdgesState,
  Node,
  Edge,
  Connection,
  NodeDragHandler,
  OnEdgesChange,
  applyNodeChanges,
  applyEdgeChanges,
} from 'reactflow';
import 'reactflow/dist/style.css';
import { Button } from '@/components/ui/button';
import { PlusCircle, Upload, Download, Share2, RefreshCw, Search, Cog, Expand, Compress } from 'lucide-react';
import {
  addDevice,
  updateDevice,
  deleteDevice,
  NetworkDevice,
  getEdges,
  addEdgeToDB,
  deleteEdgeFromDB,
  updateEdgeInDB,
  importMap,
  MapData,
} from '@/services/networkDeviceService';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import DeviceNode from './DeviceNode';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { useNavigate } from 'react-router-dom';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

// Declare SoundManager globally
declare global {
  interface Window {
    SoundManager: {
      play: (soundName: string) => void;
    };
  }
}

interface MapOption {
  id: string;
  name: string;
  background_color?: string;
  background_image_url?: string;
}

const NetworkMap = ({ devices, onMapUpdate }: { devices: NetworkDevice[]; onMapUpdate: (mapId?: string) => void }) => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);
  const importInputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  const [maps, setMaps] = useState<MapOption[]>([]);
  const [currentMapId, setCurrentMapId] = useState<string | undefined>(undefined);
  const [liveRefreshEnabled, setLiveRefreshEnabled] = useState(false);
  const [isFullScreen, setIsFullScreen] = useState(false);
  const mapWrapperRef = useRef<HTMLDivElement>(null);

  // Ref to store previous devices for status comparison
  const prevDevicesRef = useRef<NetworkDevice[]>([]);

  // Get user role from global scope
  const userRole = (window as any).userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const fetchMaps = useCallback(async () => {
    try {
      const response = await fetch('api.php?action=get_maps');
      const mapsData = await response.json();
      if (mapsData && mapsData.length > 0) {
        setMaps(mapsData);
        // If no map is currently selected, or the current map is no longer in the list, select the first one
        if (!currentMapId || !mapsData.some((m: MapOption) => m.id === currentMapId)) {
          setCurrentMapId(mapsData[0].id);
          onMapUpdate(mapsData[0].id); // Trigger parent update with new map ID
        } else {
          onMapUpdate(currentMapId); // Trigger parent update with current map ID
        }
      } else {
        setMaps([]);
        setCurrentMapId(undefined);
        onMapUpdate(undefined); // No map selected
      }
    } catch (error) {
      console.error('Failed to load maps:', error);
      showError('Failed to load network maps.');
    }
  }, [currentMapId, onMapUpdate]);

  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  const handleMapChange = useCallback((mapId: string) => {
    setCurrentMapId(mapId);
    onMapUpdate(mapId); // Notify parent component of map change
  }, [onMapUpdate]);

  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      if (!isAdmin) {
        showError('You do not have permission to change device status.');
        return;
      }
      // Optimistically update UI
      setNodes((nds) =>
        nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status } } : node))
      );
      try {
        // Update in database
        const device = devices.find(d => d.id === nodeId);
        if (device && device.ip_address) {
          await updateDevice(nodeId, { 
            status,
            last_ping: new Date().toISOString(),
          });
        }
      } catch (error) {
        console.error('Failed to update device status in DB:', error);
        showError('Failed to update device status.');
        // Revert UI update on failure
        setNodes((nds) =>
          nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status: device?.status || 'unknown' } } : node))
        );
      }
    },
    [setNodes, devices, isAdmin]
  );

  const mapDeviceToNode = useCallback(
    (device: NetworkDevice): Node => ({
      id: device.id!,
      type: 'device',
      position: { x: device.position_x || 0, y: device.position_y || 0 },
      data: {
        id: device.id,
        name: device.name,
        ip_address: device.ip_address,
        icon: device.icon,
        status: device.status || 'unknown',
        ping_interval: device.ping_interval,
        icon_size: device.icon_size,
        name_text_size: device.name_text_size,
        last_ping: device.last_ping,
        last_ping_result: device.last_ping_result,
        onEdit: (id: string) => {
          if (!isAdmin) {
            showError('You do not have permission to edit devices.');
            return;
          }
          navigate(`/edit-device/${id}`);
        },
        onDelete: (id: string) => {
          if (!isAdmin) {
            showError('You do not have permission to delete devices.');
            return;
          }
          handleDelete(id);
        },
        onStatusChange: handleStatusChange,
      },
    }),
    [handleStatusChange, navigate, isAdmin]
  );

  useEffect(() => {
    setNodes(devices.map(mapDeviceToNode));
  }, [devices, mapDeviceToNode, setNodes]);

  useEffect(() => {
    const loadEdges = async () => {
      if (!currentMapId) {
        setEdges([]);
        return;
      }
      try {
        const edgesData = await getEdges(currentMapId);
        setEdges(
          edgesData.map((edge: any) => ({
            id: edge.id,
            source: edge.source_id, // PHP API returns source_id, target_id
            target: edge.target_id,
            data: { connection_type: edge.connection_type || 'cat5' },
          }))
        );
      } catch (error) {
        console.error('Failed to load network edges:', error);
        showError('Failed to load network connections.');
      }
    };
    loadEdges();
  }, [setEdges, currentMapId]);

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

  const styledEdges = useMemo(() => {
    return edges.map((edge) => {
      const sourceNode = nodes.find((n) => n.id === edge.source);
      const targetNode = nodes.find((n) => n.id === edge.target);
      const isConnectionBroken = 
        sourceNode?.data.status === 'offline' || 
        targetNode?.data.status === 'offline';
      
      const type = edge.data?.connection_type || 'cat5';
      let style: React.CSSProperties = { strokeWidth: 2 };
      
      if (isConnectionBroken) {
        style.stroke = '#ef4444';
      } else {
        switch (type) {
          case 'fiber': 
            style.stroke = '#f97316';
            break;
          case 'wifi': 
            style.stroke = '#38bdf8';
            style.strokeDasharray = '5, 5';
            break;
          case 'radio': 
            style.stroke = '#84cc16';
            style.strokeDasharray = '2, 7';
            break;
          case 'cat5': 
          default: 
            style.stroke = '#a78bfa';
            break;
        }
      }

      return { 
        ...edge, 
        animated: !isConnectionBroken, 
        style, 
        label: type,
        labelStyle: { fill: 'white', fontWeight: 'bold' }
      };
    });
  }, [nodes, edges]);

  const onConnect = useCallback(
    async (params: Connection) => {
      if (!isAdmin) {
        showError('You do not have permission to add connections.');
        return;
      }
      if (!currentMapId) {
        showError('Please select a map first.');
        return;
      }
      const newEdge = { 
        id: `reactflow__edge-${params.source}${params.target}`, 
        source: params.source!, 
        target: params.target!, 
        data: { connection_type: 'cat5' } 
      };
      setEdges((eds) => applyEdgeChanges([{ type: 'add', item: newEdge }], eds));
      
      try {
        await addEdgeToDB({ source: params.source!, target: params.target!, map_id: currentMapId });
        showSuccess('Connection saved.');
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
        setEdges((eds) => eds.filter(e => e.id !== newEdge.id));
      }
    },
    [setEdges, isAdmin, currentMapId]
  );

  const handleDelete = async (deviceId: string) => {
    if (!isAdmin) {
      showError('You do not have permission to delete devices.');
      return;
    }
    if (window.confirm('Are you sure you want to delete this device?')) {
      const originalNodes = nodes;
      setNodes((nds) => nds.filter((node) => node.id !== deviceId));
      
      try {
        await deleteDevice(deviceId);
        showSuccess('Device deleted successfully.');
        onMapUpdate(currentMapId);
      } catch (error) {
        console.error('Failed to delete device:', error);
        showError('Failed to delete device.');
        setNodes(originalNodes);
      }
    }
  };

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      if (!isAdmin) {
        showError('You do not have permission to move devices.');
        return;
      }
      try {
        await updateDevice(node.id, { position_x: node.position.x, position_y: node.position.y });
      } catch (error) {
        console.error('Failed to save device position:', error);
        showError('Failed to save device position.');
      }
    },
    [isAdmin]
  );

  const onEdgesChangeHandler: OnEdgesChange = useCallback(
    (changes) => {
      onEdgesChange(changes);
      changes.forEach(async (change) => {
        if (change.type === 'remove') {
          if (!isAdmin) {
            showError('You do not have permission to delete connections.');
            return;
          }
          try {
            await deleteEdgeFromDB(change.id);
            showSuccess('Connection deleted.');
          } catch (error) {
            console.error('Failed to delete connection:', error);
            showError('Failed to delete connection.');
          }
        }
      });
    },
    [onEdgesChange, isAdmin]
  );

  const onEdgeClick = (_event: React.MouseEvent, edge: Edge) => {
    if (!isAdmin) {
      showError('You do not have permission to edit connections.');
      return;
    }
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  };

  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
    if (!isAdmin) {
      showError('You do not have permission to save connection changes.');
      return;
    }
    const originalEdges = edges;
    setEdges((eds) => eds.map(e => e.id === edgeId ? { ...e, data: { connection_type } } : e));
    
    try {
      await updateEdgeInDB(edgeId, { connection_type });
      showSuccess('Connection updated.');
    } catch (error) {
      console.error('Failed to update connection:', error);
      showError('Failed to update connection.');
      setEdges(originalEdges);
    }
  };

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
        connection_type: data.connection_type || 'cat5' 
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
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Label htmlFor="map-select" className="text-white">Select Map:</Label>
          <Select value={currentMapId} onValueChange={handleMapChange}>
            <SelectTrigger id="map-select" className="w-[200px]">
              <SelectValue placeholder="Select a map" />
            </SelectTrigger>
            <SelectContent>
              {maps.length === 0 ? (
                <SelectItem value="no-maps" disabled>No maps available</SelectItem>
              ) : (
                maps.map((map) => (
                  <SelectItem key={map.id} value={map.id}>
                    {map.name}
                  </SelectItem>
                ))
              )}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-2">
          {isAdmin && (
            <>
              <Button onClick={() => console.log('New Map')} variant="outline" size="sm">New Map</Button>
              <Button onClick={() => console.log('Rename Map')} variant="outline" size="sm">Rename Map</Button>
              <Button onClick={() => console.log('Delete Map')} variant="destructive" size="sm">Delete Map</Button>
            </>
          )}
          <Button onClick={handleShareMap} variant="outline" size="sm">
            <Share2 className="h-4 w-4 mr-2" />Share Map
          </Button>
        </div>
      </div>

      <div className="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold text-white">{currentMap?.name || 'No Map Selected'}</h2>
          <div className="flex items-center gap-2">
            <Button onClick={() => console.log('Scan Network')} variant="ghost" size="icon" title="Scan Network" disabled={!isAdmin}>
              <Search className="h-5 w-5" />
            </Button>
            <Button onClick={() => onMapUpdate(currentMapId)} variant="ghost" size="icon" title="Refresh Device Statuses">
              <RefreshCw className="h-5 w-5" />
            </Button>
            
            <div className="flex items-center space-x-2 pl-2 ml-2 border-l border-slate-700">
              <Label htmlFor="live-refresh-toggle" className="text-sm text-slate-400 select-none cursor-pointer">Live Status</Label>
              <Switch
                id="live-refresh-toggle"
                checked={liveRefreshEnabled}
                onCheckedChange={setLiveRefreshEnabled}
              />
            </div>

            <div className="pl-2 ml-2 border-l border-slate-700 flex items-center gap-2">
              {isAdmin && (
                <>
                  <Button onClick={() => console.log('Place Existing Device')} variant="ghost" size="icon" title="Place Existing Device">
                    <PlusCircle className="h-5 w-5" />
                  </Button>
                  <Button onClick={() => navigate('/add-device')} variant="ghost" size="icon" title="Add New Device">
                    <PlusCircle className="h-5 w-5" />
                  </Button>
                  <Button onClick={() => console.log('Add Connection')} variant="ghost" size="icon" title="Add Connection">
                    <PlusCircle className="h-5 w-5" />
                  </Button>
                  <Button onClick={handleExport} variant="ghost" size="icon" title="Export Map">
                    <Download className="h-5 w-5" />
                  </Button>
                  <Button onClick={handleImportClick} variant="ghost" size="icon" title="Import Map">
                    <Upload className="h-5 w-5" />
                  </Button>
                  <input 
                    type="file" 
                    ref={importInputRef} 
                    onChange={handleFileChange} 
                    accept="application/json" 
                    className="hidden" 
                  />
                  <Button onClick={() => console.log('Map Settings')} variant="ghost" size="icon" title="Map Settings">
                    <Cog className="h-5 w-5" />
                  </Button>
                </>
              )}
              <Button onClick={handleToggleFullscreen} variant="ghost" size="icon" title="Toggle Fullscreen">
                {isFullScreen ? <Compress className="h-5 w-5" /> : <Expand className="h-5 w-5" />}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div ref={mapWrapperRef} style={{ height: '70vh', width: '100%', ...backgroundStyle }} className="relative border rounded-lg bg-gray-900">
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
          <ReactFlow
            nodes={nodes}
            edges={styledEdges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChangeHandler}
            onConnect={onConnect}
            nodeTypes={nodeTypes}
            onNodeDragStop={onNodeDragStop}
            onEdgeClick={onEdgeClick}
            fitView
            fitViewOptions={{ padding: 0.1 }}
            proOptions={{ hideAttribution: true }}
            nodesDraggable={isAdmin}
            nodesConnectable={isAdmin}
            elementsSelectable={isAdmin}
          >
            <Controls />
            <MiniMap 
              nodeColor={(n) => {
                switch (n.data.status) {
                  case 'online': return '#22c55e';
                  case 'offline': return '#ef4444';
                  default: return '#94a3b8';
                }
              }} 
              nodeStrokeWidth={3} 
              maskColor="rgba(15, 23, 42, 0.8)"
            />
            <Background gap={16} size={1} color="#444" />
          </ReactFlow>
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
    </div>
  );
};

export default NetworkMap;