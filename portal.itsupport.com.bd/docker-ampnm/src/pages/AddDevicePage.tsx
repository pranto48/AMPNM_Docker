import { useNavigate } from 'react-router-dom';
import { DeviceForm } from '@/components/DeviceForm';
import { addDevice, NetworkDevice } from '@/services/networkDeviceService';
import { showSuccess, showError } from '@/utils/toast';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { useEffect } from 'react';

const AddDevicePage = () => {
  const navigate = useNavigate();
  const userRole = (window as any).userRole || 'viewer'; // Get user role from global scope

  useEffect(() => {
    if (userRole !== 'admin') {
      showError('You do not have permission to add devices.');
      navigate('/'); // Redirect to dashboard
    }
  }, [userRole, navigate]);

  if (userRole !== 'admin') {
    return null; // Render nothing if not authorized
  }

  const handleSubmit = async (deviceData: Omit<NetworkDevice, 'id' | 'user_id'>) => { // Adjusted type
    try {
      // Default position for new devices, can be adjusted on map later
      await addDevice({ 
        ...deviceData, 
        position_x: 100, 
        position_y: 100, 
        status: 'unknown',
        map_id: deviceData.map_id || null, // Ensure map_id is passed, or null if unassigned
      });
      showSuccess('Device added successfully!');
      navigate('/devices'); // Navigate back to the devices list
    } catch (error) {
      console.error('Failed to add device:', error);
      showError('Failed to add device.');
    }
  };

  return (
    <div className="container mx-auto p-4">
      <div className="flex items-center gap-4 mb-6">
        <Button variant="ghost" size="icon" asChild>
          <Link to="/devices">
            <ArrowLeft className="h-5 w-5" />
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">Add New Device</h1>
      </div>
      <DeviceForm onSubmit={handleSubmit} />
    </div>
  );
};

export default AddDevicePage;