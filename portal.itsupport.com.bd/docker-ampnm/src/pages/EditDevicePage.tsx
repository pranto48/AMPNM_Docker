import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { DeviceForm } from '@/components/DeviceForm';
import { getDevices, updateDevice, NetworkDevice, getEdges, NetworkEdge, addEdgeToDB, updateEdgeInDB, deleteEdgeFromDB, getMaps, Map } from '@/services/networkDeviceService';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { Skeleton } from '@/components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const EditDevicePage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [device, setDevice] = useState<NetworkDevice | undefined>(undefined);
  const [initialConnections, setInitialConnections] = useState<NetworkEdge[]>([]);
  const [allDevices, setAllDevices] = useState<NetworkDevice[]>([]);
  const [maps, setMaps] = useState<Map[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<string | undefined>(undefined);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchDeviceData = async () => {
      if (!id) {
        showError('Device ID is missing.');
        navigate('/');
        return;
      }
      setIsLoading(true);
      try {
        const [fetchedDevices, fetchedMaps] = await Promise.all([
          getDevices(), // Fetch all devices for connection dropdown
          getMaps(), // Fetch all maps for selection
        ]);
        setAllDevices(fetchedDevices);
        setMaps(fetchedMaps);

        const foundDevice = fetchedDevices.find((d: NetworkDevice) => d.id === id);
        if (foundDevice) {
          setDevice(foundDevice);
          setSelectedMapId(foundDevice.map_id); // Set selected map to device's map
          const deviceEdges = await getEdges(foundDevice.map_id); // Fetch edges for the device's map
          const connections = deviceEdges.filter(
            (edge) => edge.source_id === id
          );
          setInitialConnections(connections);
        } else {
          showError('Device not found.');
          navigate('/');
        }
      } catch (error) {
        console.error('Failed to fetch device data for EditDevicePage:', error);
        showError('Failed to load device data.');
        navigate('/');
      } finally {
        setIsLoading(false);
      }
    };
    fetchDeviceData();
  }, [id, navigate]);

  const handleSubmit = async (
    deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id'>,
    connections: (Omit<NetworkEdge, 'source_id' | 'map_id'> & { target_device_id: string })[]
  ) => {
    if (!id || !selectedMapId) {
      showError('Device ID or selected map is missing.');
      return;
    }

    const toastId = showLoading('Updating device and connections...');
    try {
      // Update device details
      await updateDevice(id, { ...deviceData, map_id: selectedMapId });

      // Handle connections: compare initialConnections with new connections
      const existingConnectionIds = new Set(initialConnections.map(conn => conn.id));
      const newConnectionTargetIds = new Set(connections.map(conn => conn.target_device_id));

      const connectionPromises: Promise<any>[] = [];

      // Delete removed connections
      initialConnections.forEach(oldConn => {
        if (!newConnectionTargetIds.has(oldConn.target_id)) {
          connectionPromises.push(deleteEdgeFromDB(oldConn.id!));
        }
      });

      // Add new or update existing connections
      connections.forEach(newConn => {
        const existingEdge = initialConnections.find(
          oldConn => oldConn.target_id === newConn.target_device_id
        );

        if (existingEdge) {
          // Update existing connection if type changed
          if (existingEdge.connection_type !== newConn.connection_type) {
            connectionPromises.push(
              updateEdgeInDB(existingEdge.id!, { connection_type: newConn.connection_type })
            );
          }
        } else {
          // Add new connection
          connectionPromises.push(
            addEdgeToDB({
              source: id,
              target: newConn.target_device_id,
              map_id: selectedMapId,
              connection_type: newConn.connection_type,
            })
          );
        }
      });

      await Promise.all(connectionPromises);

      dismissToast(toastId);
      showSuccess('Device and connections updated successfully!');
      navigate('/'); // Navigate back to the dashboard or map
    } catch (error) {
      dismissToast(toastId);
      console.error('Failed to update device:', error);
      showError('Failed to update device or connections.');
    }
  };

  if (isLoading) {
    return (
      <div className="container mx-auto p-4">
        <div className="flex items-center gap-4 mb-6">
          <Skeleton className="h-10 w-10 rounded-full" />
          <Skeleton className="h-8 w-64" />
        </div>
        <Skeleton className="h-12 w-full mb-6" /> {/* Map selector skeleton */}
        <Card className="w-full max-w-2xl mx-auto">
          <CardHeader>
            <Skeleton className="h-6 w-48 mb-2" />
            <Skeleton className="h-4 w-3/4" />
          </CardHeader>
          <CardContent className="space-y-6">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <div className="grid grid-cols-2 gap-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-6 w-full" />
            <div className="flex justify-end gap-2">
              <Skeleton className="h-10 w-24" />
              <Skeleton className="h-10 w-24" />
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <div className="flex items-center gap-4 mb-6">
        <Button variant="ghost" size="icon" asChild>
          <Link to="/">
            <ArrowLeft className="h-5 w-5" />
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">Edit Device: {device?.name}</h1>
      </div>
      <div className="flex items-center gap-2 mb-4">
        <h2 className="text-xl font-bold">Assigned to Map:</h2>
        <Select value={selectedMapId} onValueChange={setSelectedMapId}>
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
      </div>
      {device && selectedMapId ? (
        <DeviceForm
          initialData={device}
          initialConnections={initialConnections}
          onSubmit={handleSubmit}
          isEditing
          allDevices={allDevices}
          currentDeviceId={id}
          selectedMapId={selectedMapId}
        />
      ) : (
        <p className="text-center text-muted-foreground py-8">
          Device data not found or map not selected.
        </p>
      )}
    </div>
  );
};

export default EditDevicePage;