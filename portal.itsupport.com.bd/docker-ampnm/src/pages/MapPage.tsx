import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import NetworkMap from '@/components/NetworkMap';
import { NetworkDevice, getDevices } from '@/services/networkDeviceService';
import { showError } from '@/utils/toast';
import { Skeleton } from '@/components/ui/skeleton';

const MapPage = () => {
  const [devices, setDevices] = useState<NetworkDevice[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchParams, setSearchParams] = useSearchParams();

  const currentMapIdFromUrl = searchParams.get('map_id') || undefined;

  const fetchDevicesForMap = useCallback(async (mapId?: string) => {
    setIsLoading(true);
    try {
      const dbDevices = await getDevices(mapId);
      const mappedDevices: NetworkDevice[] = dbDevices.map(d => ({
        id: d.id,
        name: d.name,
        ip_address: d.ip,
        position_x: d.x,
        position_y: d.y,
        icon: d.type,
        status: d.status || 'unknown',
        ping_interval: d.ping_interval,
        icon_size: d.icon_size,
        name_text_size: d.name_text_size,
        last_ping: d.last_seen,
        last_ping_result: d.status === 'online',
        check_port: d.check_port,
        description: d.description,
        warning_latency_threshold: d.warning_latency_threshold,
        warning_packetloss_threshold: d.warning_packetloss_threshold,
        critical_latency_threshold: d.critical_latency_threshold,
        critical_packetloss_threshold: d.critical_packetloss_threshold,
        show_live_ping: d.show_live_ping,
        map_id: d.map_id,
      }));
      setDevices(mappedDevices);
    } catch (error) {
      showError("Failed to load devices for map.");
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Callback to update the current map ID, typically from NetworkMap component
  const handleMapUpdate = useCallback((mapId?: string) => {
    if (mapId) {
      setSearchParams({ map_id: mapId });
      fetchDevicesForMap(mapId);
    } else {
      setSearchParams({}); // Clear map_id if no map is selected
      setDevices([]);
    }
  }, [fetchDevicesForMap, setSearchParams]);

  // Initial load based on URL or default
  useEffect(() => {
    fetchDevicesForMap(currentMapIdFromUrl);
  }, [currentMapIdFromUrl, fetchDevicesForMap]);

  if (isLoading) {
    return (
      <div className="container mx-auto p-4">
        <Skeleton className="h-10 w-full mb-4" />
        <Skeleton className="h-[70vh] w-full rounded-lg" />
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <NetworkMap devices={devices} onMapUpdate={handleMapUpdate} />
    </div>
  );
};

export default MapPage;