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
import { PlusCircle, Upload, Download, Wifi, WifiOff } from 'lucide-react';
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
  subscribeToDeviceChanges
} from '@/services/networkDeviceService';
import { DeviceEditorDialog } from './DeviceEditorDialog';
import { EdgeEditorDialog } from './EdgeEditorDialog';
import DeviceNode from './DeviceNode';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { supabase } from '@/integrations/supabase/client';

const NetworkMap = ({ devices, onMapUpdate }: { devices: NetworkDevice[]; onMapUpdate: () => void }) => {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Partial<NetworkDevice> | undefined>(undefined);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);
  const importInputRef = useRef<HTMLInputElement>(null);

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
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
    [setNodes, devices]
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
    }),
    [handleStatusChange]
  );

  // Update nodes when devices change
  useEffect(() => {
    setNodes(devices.map(mapDeviceToNode));
  }, [devices, mapDeviceToNode, setNodes]);

  // Load edges and subscribe to edge changes
  useEffect(() => {
    const loadEdges = async () => {
      try {
        const edgesData = await getEdges();
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

    // Subscribe to edge changes
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
  }, [setEdges]);

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
        await addEdgeToDB({ source: params.source!, target: params.target! });
        showSuccess('Connection saved.');
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
        // Revert UI update on failure
        setEdges((eds) => eds.filter(e => e.id !== newEdge.id));
      }
    },
    [setEdges]
  );

  const handleAddDevice = () => {
    setEditingDevice(undefined);
    setIsEditorOpen(true);
  };

  const handleEdit = (deviceId: string) => {
    const nodeToEdit = nodes.find((n) => n.id === deviceId);
    if (nodeToEdit) {
      setEditingDevice({ id: nodeToEdit.id, ...nodeToEdit.data });
      setIsEditorOpen(true);
    }
  };

  const handleDelete = async (deviceId: string) => {
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
    try {
      if (editingDevice?.id) {
        // Update existing device
        await updateDevice(editingDevice.id, deviceData);
        showSuccess('Device updated successfully.');
      } else {
        // Add new device
        await addDevice({ ...deviceData, position_x: 100, position_y: 100, status: 'unknown' });
        showSuccess('Device added successfully.');
      }
      setIsEditorOpen(false);
    } catch (error) {
      console.error('Failed to save device:', error);
      showError('Failed to save device.');
    }
  };

  const onNodeDragStop: NodeDragHandler = useCallback(
    async (_event, node) => {
      try {
        await updateDevice(node.id, { position_x: node.position.x, position_y: node.position.y });
      } catch (error) {
        console.error('Failed to save device position:', error);
        showError('Failed to save device position.');
      }
    },
    []
  );

  const onEdgesChangeHandler: OnEdgesChange = useCallback(
    (changes) => {
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
    [onEdgesChange]
  );

  const onEdgeClick = (_event: React.MouseEvent, edge: Edge) => {
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  };

  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
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

  const handleImportClick = () => importInputRef.current?.click();

  const handleFileChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!window.confirm('Are you sure you want to import this map? This will overwrite your current map.')) return;

    const reader = new FileReader();
    reader.onload = async (e) => {
      const toastId = showLoading('Importing map...');
      try {
        const mapData = JSON.parse(e.target?.result as string) as MapData;
        if (!mapData.devices || !mapData.edges) throw new Error('Invalid map file format.');
        await importMap(mapData);
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

  return (
    <div style={{ height: '70vh', width: '100%' }} className="relative border rounded-lg bg-gray-900">
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
      </div>
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
    </div>
  );
};

export default NetworkMap;