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
import { PlusCircle, Upload, Download, Share2, Link, XCircle } from 'lucide-react';
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
  generateMapShareLink,
  disableMapShareLink,
  NetworkMapDetails,
} from '@/services/networkDeviceService';
import { DeviceEditorDialog } from './DeviceEditorDialog';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import DeviceNode from './DeviceNode';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { supabase } from '@/integrations/supabase/client';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface NetworkMapProps {
  devices: NetworkDevice[];
  onMapUpdate: () => void;
  currentMapId: string;
  mapDetails: NetworkMapDetails | null;
  isReadOnly?: boolean;
}

const NetworkMap = ({ devices, onMapUpdate, currentMapId, mapDetails, isReadOnly = false }: NetworkMapProps) => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Partial<NetworkDevice> | undefined>(undefined);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);
  const [isShareDialogOpen, setIsShareDialogOpen] = useState(false);
  const [shareLink, setShareLink] = useState<string>('');
  const importInputRef = useRef<HTMLInputElement>(null);

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      if (isReadOnly) return;
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
            last_ping_result: status === 'online'
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
    [setNodes, devices, isReadOnly]
  );

  const mapDeviceToNode = useCallback(
    (device: NetworkDevice): Node => ({
      id: device.id!,
      type: 'device',
      position: { x: device.position_x, y: device.position_y },
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
        onEdit: (id: string) => handleEdit(id),
        onDelete: (id: string) => handleDelete(id),
        onStatusChange: handleStatusChange,
      },
      draggable: !isReadOnly,
      selectable: !isReadOnly,
    }),
    [handleStatusChange, handleEdit, handleDelete, isReadOnly]
  );

  // Update nodes when devices change
  useEffect(() => {
    setNodes(devices.map(mapDeviceToNode));
  }, [devices, mapDeviceToNode, setNodes]);

  // Load edges and subscribe to edge changes
  useEffect(() => {
    const loadEdges = async () => {
      try {
        const edgesData = await getEdges(currentMapId, mapDetails?.share_id);
        setEdges(
          edgesData.map((edge: any) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            data: { connection_type: edge.connection_type || 'cat5' },
          }))
        );
      } catch (error) {
        console.error('Failed to load network edges:', error);
        showError('Failed to load network connections.');
      }
    };
    loadEdges();

    if (!isReadOnly) {
      // Subscribe to edge changes only if not read-only
      const handleEdgeInsert = (payload: any) => {
        const newEdge = { 
          id: payload.new.id, 
          source: payload.new.source_id, 
          target: payload.new.target_id, 
          data: { connection_type: payload.new.connection_type } 
        };
        setEdges((eds) => applyEdgeChanges([{ type: 'add', item: newEdge }], eds));
      };
      
      const handleEdgeUpdate = (payload: any) => {
        setEdges((eds) => 
          eds.map(e => e.id === payload.new.id ? { ...e, data: { connection_type: payload.new.connection_type } } : e)
        );
      };
      
      const handleEdgeDelete = (payload: any) => {
        setEdges((eds) => eds.filter((e) => e.id !== payload.old.id));
      };

      const edgeChannel = supabase.channel('network-map-edge-changes');
      edgeChannel
        .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'network_edges' }, handleEdgeInsert)
        .on('postgres_changes', { event: 'UPDATE', schema: 'public', table: 'network_edges' }, handleEdgeUpdate)
        .on('postgres_changes', { event: 'DELETE', schema: 'public', table: 'network_edges' }, handleEdgeDelete)
        .subscribe();

      return () => {
        supabase.removeChannel(edgeChannel);
      };
    }
  }, [setEdges, currentMapId, mapDetails?.share_id, isReadOnly]);

  // Style edges based on connection type and device status
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
        style.stroke = '#ef4444'; // Red for offline
      } else {
        switch (type) {
          case 'fiber': 
            style.stroke = '#f97316'; // Orange
            break;
          case 'wifi': 
            style.stroke = '#38bdf8'; // Sky blue
            style.strokeDasharray = '5, 5';
            break;
          case 'radio': 
            style.stroke = '#84cc16'; // Lime green
            style.strokeDasharray = '2, 7';
            break;
          case 'cat5': 
          default: 
            style.stroke = '#a78bfa'; // Violet
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
      if (isReadOnly) return;
      // Optimistically add edge to UI
      const newEdge = { 
        id: `reactflow__edge-${params.source}${params.target}`, 
        source: params.source!, 
        target: params.target!, 
        data: { connection_type: 'cat5' } 
      };
      setEdges((eds) => applyEdgeChanges([{ type: 'add', item: newEdge }], eds));
      
      try {
        // Save to database
        await addEdgeToDB({ source: params.source!, target: params.target!, map_id: currentMapId });
        showSuccess('Connection saved.');
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
        // Revert UI update on failure
        setEdges((eds) => eds.filter(e => e.id !== newEdge.id));
      }
    },
    [setEdges, currentMapId, isReadOnly]
  );

  const handleAddDevice = () => {
    if (isReadOnly) return;
    setEditingDevice(undefined);
    setIsEditorOpen(true);
  };

  const handleEdit = (deviceId: string) => {
    if (isReadOnly) return;
    const nodeToEdit = nodes.find((n) => n.id === deviceId);
    if (nodeToEdit) {
      setEditingDevice({ id: nodeToEdit.id, ...nodeToEdit.data });
      setIsEditorOpen(true);
    }
  };

  const handleDelete = async (deviceId: string) => {
    if (isReadOnly) return;
    if (window.confirm('Are you sure you want to delete this device?')) {
      // Optimistically remove from UI
      const originalNodes = nodes;
      setNodes((nds) => nds.filter((node) => node.id !== deviceId));
      
      try {
        // Delete from database
        await deleteDevice(deviceId);
        showSuccess('Device deleted successfully.');
      } catch (error) {
        console.error('Failed to delete device:', error);
        showError('Failed to delete device.');
        // Revert UI update on failure
        setNodes(originalNodes);
      }
    }
  };

  const handleSaveDevice = async (deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id'>) => {
    if (isReadOnly) return;
    try {
      if (editingDevice?.id) {
        // Update existing device
        await updateDevice(editingDevice.id, deviceData);
        showSuccess('Device updated successfully.');
      } else {
        // Add new device
        await addDevice({ ...deviceData, map_id: currentMapId, position_x: 100, position_y: 100, status: 'unknown' });
        showSuccess('Device added successfully.');
      }
      setIsEditorOpen(false);
      onMapUpdate(); // Refresh map to show new/updated device
    } catch (error) {
      console.error('Failed to save device:', error);
      showError('Failed to save device.');
    }
  };

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      if (isReadOnly) return;
      try {
        await updateDevice(node.id, { position_x: node.position.x, position_y: node.position.y });
      } catch (error) {
        console.error('Failed to save device position:', error);
        showError('Failed to save device position.');
      }
    },
    [isReadOnly]
  );

  const onEdgesChangeHandler: OnEdgesChange = useCallback(
    (changes) => {
      if (isReadOnly) return;
      onEdgesChange(changes);
      changes.forEach(async (change) => {
        if (change.type === 'remove') {
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
    [onEdgesChange, isReadOnly]
  );

  const onEdgeClick = (_event: React.MouseEvent, edge: Edge) => {
    if (isReadOnly) return;
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  };

  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
    if (isReadOnly) return;
    // Optimistically update UI
    const originalEdges = edges;
    setEdges((eds) => eds.map(e => e.id === edgeId ? { ...e, data: { connection_type } } : e));
    
    try {
      // Update in database
      await updateEdgeInDB(edgeId, { connection_type });
      showSuccess('Connection updated.');
    } catch (error) {
      console.error('Failed to update connection:', error);
      showError('Failed to update connection.');
      // Revert UI update on failure
      setEdges(originalEdges);
    }
  };

  const handleExport = async () => {
    if (isReadOnly) return;
    const exportData: MapData = {
      devices: devices.map(({ user_id, status, last_ping, last_ping_result, ...rest }) => rest),
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
    if (isReadOnly) return;
    importInputRef.current?.click();
  };

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    if (isReadOnly) return;
    const file = event.target.files?.[0];
    if (!file) return;
    if (!window.confirm('Are you sure you want to import this map? This will overwrite your current map.')) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      const toastId = showLoading('Importing map...');
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        if (!mapData.devices || !mapData.edges) throw new Error('Invalid map file format.');
        await importMap(mapData, currentMapId); // Pass currentMapId
        dismissToast(toastId);
        showSuccess('Map imported successfully!');
        onMapUpdate(); // Refresh the map data
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

  const handleGenerateShareLink = async () => {
    if (isReadOnly) return;
    if (!currentMapId) {
      showError('Please select a map to share.');
      return;
    }
    const toastId = showLoading('Generating share link...');
    try {
      const newShareId = await generateMapShareLink(currentMapId);
      const fullShareLink = `${window.location.origin}/shared-map/${newShareId}`;
      setShareLink(fullShareLink);
      setIsShareDialogOpen(true);
      dismissToast(toastId);
      showSuccess('Share link generated!');
      onMapUpdate(); // Refresh map details to show share_id
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Failed to generate share link:', error);
      showError(error.message || 'Failed to generate share link.');
    }
  };

  const handleDisableShareLink = async () => {
    if (isReadOnly) return;
    if (!currentMapId) {
      showError('No map selected.');
      return;
    }
    if (!window.confirm('Are you sure you want to disable the share link for this map? It will no longer be publicly accessible.')) {
      return;
    }
    const toastId = showLoading('Disabling share link...');
    try {
      await disableMapShareLink(currentMapId);
      setShareLink('');
      setIsShareDialogOpen(false);
      dismissToast(toastId);
      showSuccess('Share link disabled successfully.');
      onMapUpdate(); // Refresh map details to remove share_id
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Failed to disable share link:', error);
      showError(error.message || 'Failed to disable share link.');
    }
  };

  const handleCopyShareLink = () => {
    navigator.clipboard.writeText(shareLink);
    showSuccess('Share link copied to clipboard!');
  };

  const backgroundStyle: React.CSSProperties = useMemo(() => {
    if (mapDetails?.background_image_url) {
      return {
        backgroundImage: `url(${mapDetails.background_image_url})`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
      };
    }
    if (mapDetails?.background_color) {
      return {
        backgroundColor: mapDetails.background_color,
      };
    }
    return {
      backgroundColor: '#1e293b', // Default dark background
    };
  }, [mapDetails]);

  return (
    <div style={{ height: '70vh', width: '100%', ...backgroundStyle }} className="relative border rounded-lg">
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
        nodesDraggable={!isReadOnly}
        nodesConnectable={!isReadOnly}
        elementsSelectable={!isReadOnly}
        panOnDrag={!isReadOnly}
        zoomOnScroll={true}
        zoomOnPinch={true}
        zoomOnDoubleClick={!isReadOnly}
      >
        <Controls showInteractive={false} />
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
      {!isReadOnly && (
        <div className="absolute top-4 left-4 flex flex-wrap gap-2">
          <Button onClick={handleAddDevice} size="sm">
            <PlusCircle className="h-4 w-4 mr-2" />Add Device
          </Button>
          <Button onClick={handleExport} variant="outline" size="sm">
            <Download className="h-4 w-4 mr-2" />Export
          </Button>
          <Button onClick={handleImportClick} variant="outline" size="sm">
            <Upload className="h-4 w-4 mr-2" />Import
          </Button>
          <input 
            type="file" 
            ref={importInputRef} 
            onChange={handleFileChange} 
            accept="application/json" 
            className="hidden" 
          />
          {mapDetails?.is_public && mapDetails.share_id ? (
            <Button onClick={() => { setShareLink(`${window.location.origin}/shared-map/${mapDetails.share_id}`); setIsShareDialogOpen(true); }} variant="secondary" size="sm">
              <Link className="h-4 w-4 mr-2" />View Share Link
            </Button>
          ) : (
            <Button onClick={handleGenerateShareLink} variant="secondary" size="sm">
              <Share2 className="h-4 w-4 mr-2" />Share Map
            </Button>
          )}
        </div>
      )}
      {isEditorOpen && (
        <DeviceEditorDialog 
          isOpen={isEditorOpen} 
          onClose={() => setIsEditorOpen(false)} 
          onSave={handleSaveDevice} 
          device={editingDevice} 
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

      {/* Share Link Dialog */}
      <Dialog open={isShareDialogOpen} onOpenChange={setIsShareDialogOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Share Map</DialogTitle>
            <DialogDescription>
              Anyone with this link can view your map.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-3 items-center gap-4">
              <Label htmlFor="share-link" className="text-right">
                Link
              </Label>
              <Input
                id="share-link"
                value={shareLink}
                readOnly
                className="col-span-2"
              />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={handleCopyShareLink}>
              Copy Link
            </Button>
            <Button type="button" variant="destructive" onClick={handleDisableShareLink}>
              <XCircle className="h-4 w-4 mr-2" />Disable Link
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default NetworkMap;