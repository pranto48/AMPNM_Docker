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
import { PlusCircle, Upload, Download, Share2, MapPin, Edit, Trash2, Settings, Plus, RefreshCw } from 'lucide-react';
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
  getMaps,
  Map,
  createMap,
  updateMap,
  deleteMap,
} from '@/services/networkDeviceService';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import { MapSettingsDialog } from './MapSettingsDialog'; // Import the new dialog
import DeviceNode from './DeviceNode';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { useNavigate } from 'react-router-dom';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';

// Declare SoundManager globally
declare global {
  interface Window {
    SoundManager: {
      play: (soundName: string) => void;
    };
    userRole: string; // Declare userRole
  }
}

interface NetworkMapProps {
  devices: NetworkDevice[];
  onMapUpdate: (mapId?: string) => void; // Modified to accept mapId
  selectedMapId: string | undefined; // Now optional
  setSelectedMapId: (mapId: string | undefined) => void; // New prop to update parent
}

const NetworkMap = ({ devices, onMapUpdate, selectedMapId, setSelectedMapId }: NetworkMapProps) => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);
  const [maps, setMaps] = useState<Map[]>([]);
  const [isMapSettingsOpen, setIsMapSettingsOpen] = useState(false);
  const [isDeleteConfirmOpen, setIsDeleteConfirmOpen] = useState(false);
  const importInputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  // Get user role from global scope
  const userRole = window.userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const currentMap = useMemo(() => {
    return maps.find((map) => map.id === selectedMapId);
  }, [maps, selectedMapId]);

  const fetchMaps = useCallback(async () => {
    try {
      const fetchedMaps = await getMaps();
      setMaps(fetchedMaps);
      if (fetchedMaps.length > 0 && !selectedMapId) {
        setSelectedMapId(fetchedMaps[0].id);
      } else if (fetchedMaps.length === 0) {
        setSelectedMapId(undefined);
      }
    } catch (error) {
      console.error('Failed to fetch maps:', error);
      showError('Failed to load maps.');
    }
  }, [selectedMapId, setSelectedMapId]);

  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  // Trigger parent's onMapUpdate when selectedMapId changes
  useEffect(() => {
    onMapUpdate(selectedMapId);
  }, [selectedMapId, onMapUpdate]);

  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      if (!isAdmin) {
        return;
      }
      setNodes((nds) =>
        nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status } } : node))
      );
      try {
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
        last_ping_result: device.status === 'online',
        check_port: device.check_port,
        description: device.description,
        warning_latency_threshold: device.warning_latency_threshold,
        warning_packetloss_threshold: device.warning_packetloss_threshold,
        critical_latency_threshold: device.critical_latency_threshold,
        critical_packetloss_threshold: device.critical_packetloss_threshold,
        show_live_ping: device.show_live_ping,
        map_id: device.map_id,
        onEdit: (id: string) => navigate(`/edit-device/${id}`),
        onDelete: handleDelete,
        onStatusChange: handleStatusChange,
      },
    }),
    [handleStatusChange, navigate, isAdmin]
  );

  useEffect(() => {
    setNodes(devices.map(mapDeviceToNode));
  }, [devices, mapDeviceToNode, setNodes]);

  const loadEdges = useCallback(async () => {
    if (!selectedMapId) {
      setEdges([]);
      return;
    }
    try {
      const edgesData = await getEdges(selectedMapId);
      setEdges(
        edgesData.map((edge: any) => ({
          id: edge.id,
          source: edge.source_id,
          target: edge.target_id,
          data: { connection_type: edge.connection_type || 'cat5' },
        }))
      );
    } catch (error) {
      console.error('Failed to load network edges:', error);
      showError('Failed to load network connections.');
    }
  }, [setEdges, selectedMapId]);

  useEffect(() => {
    loadEdges();
  }, [loadEdges]);

  useEffect(() => {
    const pollingInterval = setInterval(() => {
      onMapUpdate(selectedMapId);
      loadEdges();
    }, 15000);

    return () => clearInterval(pollingInterval);
  }, [onMapUpdate, loadEdges, selectedMapId]);

  const prevDevicesRef = useRef<NetworkDevice[]>([]);

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
        showError('You do not have permission to create connections.');
        return;
      }
      if (!selectedMapId) {
        showError('No map selected. Cannot create connection.');
        return;
      }
      
      try {
        await addEdgeToDB({ source: params.source!, target: params.target!, map_id: selectedMapId, connection_type: 'cat5' });
        showSuccess('Connection saved.');
        loadEdges();
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
      }
    },
    [isAdmin, selectedMapId, loadEdges]
  );

  const handleDelete = async (deviceId: string) => {
    if (!isAdmin) {
      showError('You do not have permission to delete devices.');
      return;
    }
    if (window.confirm('Are you sure you want to delete this device?')) {
      try {
        await deleteDevice(deviceId);
        showSuccess('Device deleted successfully.');
        onMapUpdate(selectedMapId);
        loadEdges();
      } catch (error) {
        console.error('Failed to delete device:', error);
        showError('Failed to delete device.');
      }
    }
  };

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      if (!isAdmin) {
        showError('You do not have permission to drag nodes.');
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
            loadEdges();
          } catch (error) {
            console.error('Failed to delete connection:', error);
            showError('Failed to delete connection.');
          }
        }
      });
    },
    [onEdgesChange, isAdmin, loadEdges]
  );

  const onEdgeClick = useCallback((_event: React.MouseEvent, edge: Edge) => {
    if (!isAdmin) {
      showError('You do not have permission to edit connections.');
      return;
    }
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  }, [isAdmin]);

  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
    if (!isAdmin) {
      showError('You do not have permission to save connection changes.');
      return;
    }
    
    try {
      await updateEdgeInDB(edgeId, { connection_type: connectionType });
      showSuccess('Connection updated.');
      loadEdges();
    } catch (error) {
      console.error('Failed to update connection:', error);
      showError('Failed to update connection.');
    }
  };

  const handleExport = async () => {
    if (!isAdmin) {
      showError('You do not have permission to export maps.');
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
    if (!selectedMapId) {
      showError('No map selected. Cannot import map data.');
      if (importInputRef.current) importInputRef.current.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = async (e) => {
      const toastId = showLoading('Importing map...');
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        if (!mapData.devices || !mapData.edges) throw new Error('Invalid map file format.');
        
        await importMap(mapData, selectedMapId);
        dismissToast(toastId);
        showSuccess('Map imported successfully!');
        onMapUpdate(selectedMapId);
        loadEdges();
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
    if (!selectedMapId) {
      showError('No map selected to share.');
      return;
    }

    const shareUrl = `http://192.168.20.5:2266/public_map.php?map_id=${selectedMapId}`;

    try {
      await navigator.clipboard.writeText(shareUrl);
      showSuccess('Share link copied to clipboard!');
    } catch (err) {
      console.error('Failed to copy share link:', err);
      showError('Failed to copy share link. Please copy manually: ' + shareUrl);
    }
  };

  const handleCreateMap = async () => {
    if (!isAdmin) {
      showError('You do not have permission to create maps.');
      return;
    }
    const mapName = prompt('Enter a name for the new map:');
    if (mapName && mapName.trim() !== '') {
      const toastId = showLoading('Creating map...');
      try {
        const newMap = await createMap(mapName.trim());
        dismissToast(toastId);
        showSuccess(`Map "${newMap.name}" created!`);
        await fetchMaps(); // Refresh maps list
        setSelectedMapId(newMap.id); // Select the new map
      } catch (error: any) {
        dismissToast(toastId);
        console.error('Failed to create map:', error);
        showError(error.message || 'Failed to create map.');
      }
    } else if (mapName !== null) {
      showError('Map name cannot be empty.');
    }
  };

  const handleRenameMap = async () => {
    if (!isAdmin || !selectedMapId || !currentMap) {
      showError('You do not have permission or no map is selected.');
      return;
    }
    const newName = prompt('Enter a new name for the map:', currentMap.name);
    if (newName && newName.trim() !== '' && newName !== currentMap.name) {
      const toastId = showLoading('Renaming map...');
      try {
        await updateMap(selectedMapId, { name: newName.trim() });
        dismissToast(toastId);
        showSuccess(`Map renamed to "${newName}"!`);
        await fetchMaps(); // Refresh maps list
      } catch (error: any) {
        dismissToast(toastId);
        console.error('Failed to rename map:', error);
        showError(error.message || 'Failed to rename map.');
      }
    } else if (newName !== null && newName.trim() === '') {
      showError('Map name cannot be empty.');
    }
  };

  const handleDeleteMap = async () => {
    if (!isAdmin || !selectedMapId) {
      showError('You do not have permission or no map is selected.');
      return;
    }
    setIsDeleteConfirmOpen(true);
  };

  const confirmDeleteMap = async () => {
    if (!selectedMapId) return;
    const toastId = showLoading('Deleting map...');
    try {
      await deleteMap(selectedMapId);
      dismissToast(toastId);
      showSuccess('Map deleted successfully!');
      setIsDeleteConfirmOpen(false);
      await fetchMaps(); // Refresh maps list
      // setSelectedMapId will be updated by fetchMaps if there are other maps
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Failed to delete map:', error);
      showError(error.message || 'Failed to delete map.');
    }
  };

  const handleOpenMapSettings = () => {
    if (!isAdmin || !selectedMapId) {
      showError('You do not have permission or no map is selected.');
      return;
    }
    setIsMapSettingsOpen(true);
  };

  const handleMapSettingsUpdated = useCallback(() => {
    fetchMaps(); // Re-fetch maps to get updated settings
  }, [fetchMaps]);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="text-xl font-bold">Current Map:</h2>
        <Select value={selectedMapId || ''} onValueChange={setSelectedMapId} disabled={maps.length === 0}>
          <SelectTrigger className="w-[200px]">
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

        {isAdmin && (
          <>
            <Button onClick={handleCreateMap} size="sm" variant="outline">
              <Plus className="h-4 w-4 mr-2" />New Map
            </Button>
            <Button onClick={handleRenameMap} size="sm" variant="outline" disabled={!selectedMapId}>
              <Edit className="h-4 w-4 mr-2" />Rename
            </Button>
            <Button onClick={handleDeleteMap} size="sm" variant="destructive" disabled={!selectedMapId}>
              <Trash2 className="h-4 w-4 mr-2" />Delete
            </Button>
            <Button onClick={handleOpenMapSettings} size="sm" variant="outline" disabled={!selectedMapId}>
              <Settings className="h-4 w-4 mr-2" />Settings
            </Button>
          </>
        )}
      </div>

      {selectedMapId ? (
        <div 
          style={{ 
            height: '70vh', 
            width: '100%', 
            backgroundImage: currentMap?.background_image_url ? `url(${currentMap.background_image_url})` : 'none',
            backgroundColor: currentMap?.background_color || '#1e293b',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
          }} 
          className="relative border rounded-lg"
        >
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
          <div className="absolute top-4 left-4 flex flex-wrap gap-2">
            {isAdmin && (
              <Button onClick={() => navigate('/add-device')} size="sm">
                <PlusCircle className="h-4 w-4 mr-2" />Add Device
              </Button>
            )}
            {isAdmin && (
              <Button onClick={handleExport} variant="outline" size="sm">
                <Download className="h-4 w-4 mr-2" />Export
              </Button>
            )}
            {isAdmin && (
              <Button onClick={handleImportClick} variant="outline" size="sm">
                <Upload className="h-4 w-4 mr-2" />Import
              </Button>
            )}
            <input 
              type="file" 
              ref={importInputRef} 
              onChange={handleFileChange} 
              accept="application/json" 
              className="hidden" 
            />
            <Button onClick={handleShareMap} variant="outline" size="sm">
              <Share2 className="h-4 w-4 mr-2" />Share Map
            </Button>
          </div>
          {isEdgeEditorOpen && isAdmin && editingEdge && (
            <EdgeEditorDialog 
              isOpen={isEdgeEditorOpen} 
              onClose={() => setIsEdgeEditorOpen(false)} 
              onSave={handleSaveEdge} 
              edge={editingEdge} 
            />
          )}
          {isMapSettingsOpen && isAdmin && currentMap && (
            <MapSettingsDialog
              isOpen={isMapSettingsOpen}
              onClose={() => setIsMapSettingsOpen(false)}
              currentMap={currentMap}
              onMapUpdated={handleMapSettingsUpdated}
            />
          )}
        </div>
      ) : (
        <div className="text-center py-16 border rounded-lg bg-gray-800">
          <MapPin className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
          <p className="text-muted-foreground">
            No map selected. {isAdmin ? 'Create a new map or select an existing one.' : 'Please ask an admin to create a map.'}
          </p>
          {isAdmin && (
            <Button onClick={handleCreateMap} className="mt-4">
              <Plus className="h-4 w-4 mr-2" />Create First Map
            </Button>
          )}
        </div>
      )}

      <AlertDialog open={isDeleteConfirmOpen} onOpenChange={setIsDeleteConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. This will permanently delete the "{currentMap?.name}" map and all associated devices and connections.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDeleteMap} className="bg-red-600 hover:bg-red-700">Delete</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
};

export default NetworkMap;