import { useState, useEffect, useCallback } from 'react';
import { getMaps, MapOption } from '@/services/networkDeviceService';
import { showError } from '@/utils/toast';

interface UseNetworkMapDataProps {
  initialMapId?: string;
  onMapUpdate: (mapId?: string) => void;
}

export const useNetworkMapData = ({ initialMapId, onMapUpdate }: UseNetworkMapDataProps) => {
  const [maps, setMaps] = useState<MapOption[]>([]);
  const [currentMapId, setCurrentMapId] = useState<string | undefined>(initialMapId);

  const fetchMaps = useCallback(async () => {
    try {
      const mapsData = await getMaps();
      if (mapsData && mapsData.length > 0) {
        setMaps(mapsData);
        // If no map is currently selected, or the current map is no longer in the list, select the first one
        if (!currentMapId || !mapsData.some((m: MapOption) => m.id === currentMapId)) {
          setCurrentMapId(mapsData[0].id);
          onMapUpdate(mapsData[0].id);
        } else {
          onMapUpdate(currentMapId);
        }
      } else {
        setMaps([]);
        setCurrentMapId(undefined);
        onMapUpdate(undefined);
      }
    } catch (error) {
      console.error('Failed to load maps:', error);
      showError('Failed to load network maps.');
    }
  }, [currentMapId, onMapUpdate]);

  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  const handleMapChange = useCallback((mapId: string) => {
    setCurrentMapId(mapId);
    onMapUpdate(mapId);
  }, [onMapUpdate]);

  return { maps, currentMapId, handleMapChange, fetchMaps };
};