// import { supabase } from '@/integrations/supabase/client'; // Removed Supabase import

const API_BASE_URL = 'http://localhost:2266/api.php'; // Your local PHP API endpoint

export interface NetworkDevice {
  id?: string;
  user_id?: string; // This will be handled by PHP session
  name: string;
  ip_address: string;
  position_x: number;
  position_y: number;
  icon: string;
  status?: 'online' | 'offline' | 'unknown';
  ping_interval?: number;
  icon_size?: number;
  name_text_size?: number;
  last_ping?: string | null;
  last_ping_result?: boolean | null;
}

export interface MapData {
  devices: Omit<NetworkDevice, 'user_id' | 'status'>[];
  edges: { source: string; target: string; connection_type: string }[];
}

export interface NetworkMapDetails {
  id: string;
  name: string;
  background_color: string | null;
  background_image_url: string | null;
  share_id: string | null;
  is_public: boolean;
}

const fetchApi = async (action: string, method: 'GET' | 'POST' = 'GET', body?: any) => {
  const url = `${API_BASE_URL}?action=${action}`;
  const options: RequestInit = {
    method,
    headers: {
      'Content-Type': 'application/json',
    },
  };
  if (body) {
    options.body = JSON.stringify(body);
  }

  const response = await fetch(url, options);
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
    throw new Error(errorData.error || `API request failed with status ${response.status}`);
  }
  return response.json();
};

export const getMaps = async (): Promise<NetworkMapDetails[]> => {
  // User ID is handled by PHP session on the server side
  const data = await fetchApi('get_maps');
  return data;
};

export const getDevices = async (mapId?: string, shareId?: string) => {
  const params = new URLSearchParams();
  if (mapId) params.append('map_id', mapId);
  if (shareId) params.append('share_id', shareId);

  const data = await fetchApi(`get_devices&${params.toString()}`);
  return data;
};

export const addDevice = async (device: Omit<NetworkDevice, 'user_id'>) => {
  const data = await fetchApi('create_device', 'POST', device);
  return data;
};

export const updateDevice = async (id: string, updates: Partial<NetworkDevice>) => {
  const data = await fetchApi('update_device', 'POST', { id, updates });
  return data;
};

export const updateDeviceStatusByIp = async (ip_address: string, status: 'online' | 'offline') => {
  // This action needs to be implemented in your PHP API
  // For now, we'll simulate it or call a generic update if available
  console.warn('updateDeviceStatusByIp is not fully implemented in PHP API yet. Simulating update.');
  // You might need a specific API endpoint for this or handle it via a generic device update
  const data = await fetchApi('update_device_status_by_ip', 'POST', { ip_address, status });
  return data;
};

export const deleteDevice = async (id: string) => {
  await fetchApi('delete_device', 'POST', { id });
};

export const getEdges = async (mapId?: string, shareId?: string) => {
  const params = new URLSearchParams();
  if (mapId) params.append('map_id', mapId);
  if (shareId) params.append('share_id', shareId);

  const data = await fetchApi(`get_edges&${params.toString()}`);
  return data;
};

export const addEdgeToDB = async (edge: { source: string; target: string; map_id: string }) => {
  const data = await fetchApi('create_edge', 'POST', edge);
  return data;
};

export const updateEdgeInDB = async (id: string, updates: { connection_type: string }) => {
  const data = await fetchApi('update_edge', 'POST', { id, updates });
  return data;
};

export const deleteEdgeFromDB = async (edgeId: string) => {
  await fetchApi('delete_edge', 'POST', { id: edgeId });
};

export const importMap = async (mapData: MapData, mapId: string) => {
  await fetchApi('import_map', 'POST', { map_id: mapId, devices: mapData.devices, edges: mapData.edges });
};

// New functions for map sharing (now using local PHP API)
export const getMapDetailsByShareId = async (shareId: string): Promise<NetworkMapDetails | null> => {
  const data = await fetchApi(`get_map_by_share_id&share_id=${shareId}`);
  return data;
};

export const generateMapShareLink = async (mapId: string): Promise<string> => {
  const data = await fetchApi('generate_share_link', 'POST', { map_id: mapId });
  return data.share_id; // PHP API should return { success: true, share_id: '...' }
};

export const disableMapShareLink = async (mapId: string) => {
  await fetchApi('disable_share_link', 'POST', { map_id: mapId });
};

// Real-time subscription is not directly supported by PHP/MySQL without additional tech (e.g., WebSockets server)
// We'll remove this for now and rely on periodic refreshes or manual updates.
export const subscribeToDeviceChanges = (callback: (payload: any) => void) => {
  console.warn('Real-time device change subscription is not available with PHP/MySQL backend.');
  // Return a dummy object that can be unsubscribed
  return {
    unsubscribe: () => console.log('Dummy unsubscribe for device changes.'),
    channel: {
      on: () => ({ subscribe: () => {} }) // Mock the on and subscribe methods
    }
  };
};