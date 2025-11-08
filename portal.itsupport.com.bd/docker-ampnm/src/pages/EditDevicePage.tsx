import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { DeviceForm } from '@/components/DeviceForm';
import { getDevices, updateDevice, NetworkDevice } from '@/services/networkDeviceService';
import { showSuccess, showError } from '@/utils/toast';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { Skeleton } from '@/components/ui/skeleton';
import { getMaps } from '@/services/mapService';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';

const EditDevicePage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [device, setDevice] = useState<NetworkDevice | undefined>(undefined);
  const [isLoading, setIsLoading] = useState(true);
  const [maps, setMaps] = useState<any[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<string | undefined>(undefined);

  useEffect(() => {
    const fetchData = async () => {
      if (!id) {
        showError('Device ID is missing.');
        navigate('/');
        return;
      }
      try {
        const [fetchedDevices, fetchedMaps] = await Promise.all([getDevices(), getMaps()]);
        setMaps(fetchedMaps);

        const foundDevice = fetchedDevices.find((d: NetworkDevice) => d.id === id);
        if (foundDevice) {
          setDevice(foundDevice);
          setSelectedMapId(foundDevice.map_id);
        } else {
          showError('Device not found.');
          navigate('/');
        }
      } catch (error) {
        console.error('Failed to fetch device or maps:', error);
        showError('Failed to load device data or maps.');
        navigate('/');
      } finally {
        setIsLoading(false);
      }
    };
    fetchData();
  }, [id, navigate]);

  const handleSubmit = async (deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id' | 'map_id'>) => {
    if (!id) return;
    if (!selectedMapId) {
      showError("Please select a map to assign the device to.");
      return;
    }
    try {
      await updateDevice(id, { ...deviceData, map_id: selectedMapId });
      showSuccess('Device updated successfully!');
      navigate('/'); // Navigate back to the dashboard or map
    } catch (error) {
      console.error('Failed to update device:', error);
      showError('Failed to update device.');
    }
  };

  if (isLoading) {
    return (
      <div className="container mx-auto p-4">
        <div className="flex items-center gap-4 mb-6">
          <Skeleton className="h-10 w-10 rounded-full" />
          <Skeleton className="h-8 w-64" />
        </div>
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
      <Card className="w-full max-w-2xl mx-auto">
        <CardHeader>
          <CardTitle>Edit Device</CardTitle>
          <CardDescription>Update the details for your network device.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-6">
            <Label htmlFor="map-select" className="block text-sm font-medium text-foreground mb-2">Assign to Map</Label>
            <Select value={selectedMapId || ''} onValueChange={setSelectedMapId}>
              <SelectTrigger id="map-select">
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
          {device && <DeviceForm initialData={device} onSubmit={handleSubmit} isEditing />}
        </CardContent>
      </Card>
    </div>
  );
};

export default EditDevicePage;