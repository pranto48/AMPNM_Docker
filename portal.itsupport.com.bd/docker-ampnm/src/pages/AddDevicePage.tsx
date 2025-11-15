import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { DeviceForm } from '@/components/DeviceForm';
import { addDevice, NetworkDevice, NetworkEdge, addEdgeToDB, getDevices, getMaps, Map } from '@/services/networkDeviceService';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { Skeleton } from '@/components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const AddDevicePage = () => {
  const navigate = useNavigate();
  const [allDevices, setAllDevices] = useState<NetworkDevice[]>([]);
  const [maps, setMaps] = useState<Map[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<string | undefined>(undefined);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchInitialData = async () => {
      setIsLoading(true);
      try {
        const fetchedMaps = await getMaps();
        setMaps(fetchedMaps);
        if (fetchedMaps.length > 0 && !selectedMapId) {
          setSelectedMapId(fetchedMaps[0].id); // Auto-select first map if available
        }
      } catch (error) {
        console.error('Failed to fetch maps for AddDevicePage:', error);
        showError('Failed to load maps.');
      } finally {
        setIsLoading(false);
      }
    };
    fetchInitialData();
  }, []);

  useEffect(() => {
    const fetchDevicesForMap = async () => {
      if (selectedMapId) {
        try {
          const fetchedDevices = await getDevices(selectedMapId); // Fetch devices for the selected map
          setAllDevices(fetchedDevices);
        } catch (error) {
          console.error('Failed to fetch devices for selected map:', error);
          showError('Failed to load devices for the selected map.');
        }
      } else {
        setAllDevices([]); // Clear devices if no map is selected
      }
    };
    fetchDevicesForMap();
  }, [selectedMapId]); // Re-fetch devices when selectedMapId changes

  const handleSubmit = async (
    deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id'>,
    connections: (Omit<NetworkEdge, 'source_id' | 'map_id'> & { target_device_id: string })[]
  ) => {
    if (!selectedMapId) {
      showError('Please select a map before adding a device.');
      return;
    }

    const toastId = showLoading('Adding device and connections...');
    try {
      // Default position for new devices, can be adjusted on map later
      const newDevice = await addDevice({ 
        ...deviceData, 
        position_x: 100, 
        position_y: 100, 
        status: 'unknown',
        map_id: selectedMapId, // Assign to the selected map
      });

      if (newDevice.id) {
        // Add connections
        const connectionPromises = connections.map(conn => 
          addEdgeToDB({ 
            source: newDevice.id!, 
            target: conn.target_device_id, 
            map_id: selectedMapId, 
            connection_type: conn.connection_type 
          })
        );
        await Promise.all(connectionPromises);
      }

      dismissToast(toastId);
      showSuccess('Device and connections added successfully!');
      navigate('/'); // Navigate back to the dashboard or map
    } catch (error) {
      dismissToast(toastId);
      console.error('Failed to add device:', error);
      showError('Failed to add device or connections.');
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
        <h1 className="text-3xl font-bold">Add New Device</h1>
      </div>
      <div className="flex items-center gap-2 mb-4">
        <h2 className="text-xl font-bold">Assign to Map:</h2>
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
      {selectedMapId ? (
        <DeviceForm 
          onSubmit={handleSubmit} 
          allDevices={allDevices} 
          selectedMapId={selectedMapId} 
        />
      ) : (
        <p className="text-center text-muted-foreground py-8">
          Please create a map first via the PHP map page if no maps are listed here, then select one.
        </p>
      )}
    </div>
  );
};

export default AddDevicePage;