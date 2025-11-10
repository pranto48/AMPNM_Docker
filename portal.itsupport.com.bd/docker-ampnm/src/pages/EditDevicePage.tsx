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

const EditDevicePage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [device, setDevice] = useState<NetworkDevice | undefined>(undefined);
  const [isLoading, setIsLoading] = useState(true);
  const userRole = (window as any).userRole || 'viewer'; // Get user role from global scope

  useEffect(() => {
    if (userRole !== 'admin') {
      showError('You do not have permission to edit devices.');
      navigate('/'); // Redirect to dashboard
      return;
    }

    const fetchDevice = async () => {
      if (!id) {
        showError('Device ID is missing.');
        navigate('/devices'); // Navigate to devices list
        return;
      }
      try {
        // Fetch all devices and find the one with the matching ID
        const devices = await getDevices();
        const foundDevice = devices.find((d: NetworkDevice) => d.id === id);
        if (foundDevice) {
          setDevice(foundDevice);
        } else {
          showError('Device not found.');
          navigate('/devices'); // Navigate to devices list
        }
      } catch (error) {
        console.error('Failed to fetch device:', error);
        showError('Failed to load device data.');
        navigate('/devices'); // Navigate to devices list
      } finally {
        setIsLoading(false);
      }
    };
    fetchDevice();
  }, [id, navigate, userRole]);

  if (userRole !== 'admin') {
    return null; // Render nothing if not authorized
  }

  const handleSubmit = async (deviceData: Omit<NetworkDevice, 'id' | 'user_id'>) => { // Adjusted type
    if (!id) return;
    try {
      await updateDevice(id, { 
        ...deviceData,
        map_id: deviceData.map_id || null, // Ensure map_id is passed, or null if unassigned
      });
      showSuccess('Device updated successfully!');
      navigate('/devices'); // Navigate back to the devices list
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
          <Link to="/devices">
            <ArrowLeft className="h-5 w-5" />
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">Edit Device: {device?.name}</h1>
      </div>
      {device && <DeviceForm initialData={device} onSubmit={handleSubmit} isEditing />}
    </div>
  );
};

export default EditDevicePage;