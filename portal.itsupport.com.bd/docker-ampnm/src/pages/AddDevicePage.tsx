import { useNavigate } from 'react-router-dom';
import { DeviceForm } from '@/components/DeviceForm';
import { addDevice, NetworkDevice } from '@/services/networkDeviceService';
import { showSuccess, showError } from '@/utils/toast';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { getMaps } from '@/services/mapService';
import { useEffect, useState } from 'react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';

const AddDevicePage = () => {
  const navigate = useNavigate();
  const [maps, setMaps] = useState<any[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<string | undefined>(undefined);

  useEffect(() => {
    const fetchMaps = async () => {
      try {
        const fetchedMaps = await getMaps();
        setMaps(fetchedMaps);
        if (fetchedMaps.length > 0) {
          setSelectedMapId(fetchedMaps[0].id); // Auto-select the first map
        }
      } catch (error) {
        showError("Failed to load maps for device assignment.");
      }
    };
    fetchMaps();
  }, []);

  const handleSubmit = async (deviceData: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y' | 'user_id' | 'map_id'>) => {
    if (!selectedMapId) {
      showError("Please select a map to add the device to.");
      return;
    }
    try {
      // Default position for new devices, can be adjusted on map later
      await addDevice({ ...deviceData, position_x: 100, position_y: 100, status: 'unknown', map_id: selectedMapId });
      showSuccess('Device added successfully!');
      navigate('/'); // Navigate back to the dashboard or map
    } catch (error) {
      console.error('Failed to add device:', error);
      showError('Failed to add device.');
    }
  };

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
      <Card className="w-full max-w-2xl mx-auto">
        <CardHeader>
          <CardTitle>Add New Device</CardTitle>
          <CardDescription>Add a new device to your network map.</CardDescription>
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
          <DeviceForm onSubmit={handleSubmit} />
        </CardContent>
      </Card>
    </div>
  );
};

export default AddDevicePage;