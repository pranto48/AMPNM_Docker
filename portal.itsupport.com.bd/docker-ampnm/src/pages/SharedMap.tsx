import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import NetworkMap from '@/components/NetworkMap';
import { getMapDetailsByShareId, getDevices, NetworkDevice, NetworkMapDetails } from '@/services/networkDeviceService';
import { showError } from '@/utils/toast';
import { Skeleton } from '@/components/ui/skeleton';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Globe } from 'lucide-react';

const SharedMap = () => {
  const { shareId } = useParams<{ shareId: string }>();
  const [mapDetails, setMapDetails] = useState<NetworkMapDetails | null>(null);
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchSharedMapData = async () => {
      if (!shareId) {
        setError('Share ID is missing.');
        setIsLoading(false);
        return;
      }

      setIsLoading(true);
      setError(null);
      try {
        const details = await getMapDetailsByShareId(shareId);
        if (!details) {
          setError('Shared map not found or link is invalid/disabled.');
          return;
        }
        setMapDetails(details);
        
        const sharedDevices = await getDevices(undefined, shareId); // Fetch devices using shareId
        setDevices(sharedDevices as NetworkDevice[]);
      } catch (err: any) {
        console.error('Failed to fetch shared map data:', err);
        setError(err.message || 'Failed to load shared map.');
        showError(err.message || 'Failed to load shared map.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchSharedMapData();
  }, [shareId]);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background p-4">
        <Card className="w-full max-w-3xl">
          <CardHeader>
            <Skeleton className="h-6 w-1/2 mb-2" />
            <Skeleton className="h-4 w-3/4" />
          </CardHeader>
          <CardContent>
            <Skeleton className="h-96 w-full" />
            <div className="flex justify-center mt-4 space-x-2">
              <Skeleton className="h-8 w-24" />
              <Skeleton className="h-8 w-24" />
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background p-4">
        <Card className="w-full max-w-md text-center">
          <CardHeader>
            <CardTitle className="text-red-500">Error Loading Map</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground mb-4">{error}</p>
            <a href="/" className="text-primary hover:underline">Go to Home</a>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (!mapDetails) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background p-4">
        <Card className="w-full max-w-md text-center">
          <CardHeader>
            <CardTitle>Map Not Found</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground mb-4">The shared map could not be found or is no longer public.</p>
            <a href="/" className="text-primary hover:underline">Go to Home</a>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background p-4">
      <div className="container mx-auto">
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <Globe className="h-8 w-8 text-primary" />
            <h1 className="text-3xl font-bold">{mapDetails.name} (Shared View)</h1>
          </div>
        </div>
        <NetworkMap 
          devices={devices} 
          onMapUpdate={() => { /* No update needed in read-only mode */ }} 
          currentMapId={mapDetails.id} // Pass map ID for context, even if read-only
          mapDetails={mapDetails}
          isReadOnly={true} 
        />
      </div>
    </div>
  );
};

export default SharedMap;