import { useState, useCallback, useMemo } from 'react';
import {
  useNodesState,
  useEdgesState,
  Node,
  Edge,
  Connection,
  NodeDragHandler,
  OnEdgesChange,
  applyEdgeChanges,
} from 'reactflow';
import { useNavigate } from 'react-router-dom';
import { showSuccess, showError } from '@/utils/toast';
import {
  updateDevice,
  deleteDevice,
  NetworkDevice,
  getEdges,
  addEdgeToDB,
  deleteEdgeFromDB,
  updateEdgeInDB,
} from '@/services/networkDeviceService';
import DeviceNode from '@/components/DeviceNode';

interface UseNetworkMapInteractionsProps {
  currentMapId?: string;
  devices: NetworkDevice[];
  isAdmin: boolean;
  onMapUpdate: (mapId?: string) => void;
}

export const useNetworkMapInteractions = ({
  currentMapId,
  devices,
  isAdmin,
  onMapUpdate,
}: UseNetworkMapInteractionsProps) => {
  const navigate = useNavigate();
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [isEdgeEditorOpen, setIsEdgeEditorOpen] = useState(false);
  const [editingEdge, setEditingEdge] = useState<Edge | undefined>(undefined);

  // Callback to handle device status changes (e.g., from pinging)
  const handleStatusChange = useCallback(
    async (nodeId: string, status: 'online' | 'offline') => {
      if (!isAdmin) {
        showError('You do not have permission to change device status.');
        return;
      }
      setNodes((nds) =>
        nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status } } : node))
      );
      try {
        const device = devices.find((d) => d.id === nodeId);
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
          nds.map((node) => (node.id === nodeId ? { ...node, data: { ...node.data, status: devices.find((d) => d.id === nodeId)?.status || 'unknown' } } : node))
        );
      }
    },
    [setNodes, devices, isAdmin]
  );

  // Map NetworkDevice data to ReactFlow Node format
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
    [handleStatusChange, navigate, isAdmin, devices]
  );

  // Update nodes when devices prop changes
  useMemo(() => {
    setNodes(devices.map(mapDeviceToNode));
  }, [devices, mapDeviceToNode, setNodes]);

  // Load edges when currentMapId changes
  useMemo(() => {
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
            source: edge.source_id,
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

  // Styled edges for ReactFlow rendering
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
        labelStyle: { fill: 'white', fontWeight: 'bold' },
      };
    });
  }, [nodes, edges]);

  // Handle new connections
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
        data: { connection_type: 'cat5' },
      };
      setEdges((eds) => applyEdgeChanges([{ type: 'add', item: newEdge }], eds));

      try {
        await addEdgeToDB({ source: params.source!, target: params.target!, map_id: currentMapId });
        showSuccess('Connection saved.');
      } catch (error) {
        console.error('Failed to save connection:', error);
        showError('Failed to save connection.');
        setEdges((eds) => eds.filter((e) => e.id !== newEdge.id));
      }
    },
    [setEdges, isAdmin, currentMapId]
  );

  // Handle device deletion
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
        onMapUpdate(currentMapId); // Refresh map data
      } catch (error) {
        console.error('Failed to delete device:', error);
        showError('Failed to delete device.');
        setNodes(originalNodes);
      }
    }
  };

  // Handle node drag stop (save new position)
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

  // Handle edge changes (e.g., deletion)
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

  // Handle edge click (open editor)
  const onEdgeClick = useCallback((_event: React.MouseEvent, edge: Edge) => {
    if (!isAdmin) {
      showError('You do not have permission to edit connections.');
      return;
    }
    setEditingEdge(edge);
    setIsEdgeEditorOpen(true);
  }, [isAdmin]);

  // Handle saving changes from edge editor
  const handleSaveEdge = async (edgeId: string, connectionType: string) => {
    if (!isAdmin) {
      showError('You do not have permission to save connection changes.');
      return;
    }
    const originalEdges = edges;
    setEdges((eds) => eds.map((e) => (e.id === edgeId ? { ...e, data: { connection_type } } : e)));

    try {
      await updateEdgeInDB(edgeId, { connection_type });
      showSuccess('Connection updated.');
    } catch (error) {
      console.error('Failed to update connection:', error);
      showError('Failed to update connection.');
      setEdges(originalEdges);
    }
  };

  const nodeTypes = useMemo(() => ({ device: DeviceNode }), []);

  return {
    nodes,
    edges,
    onNodesChange,
    onEdgesChange: onEdgesChangeHandler,
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
  };
};