import { supabase } from '@/integrations/supabase/client';

export interface NetworkDevice {
  id?: string;
  user_id?: string;
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

export const getDevices = async () => {
  const { data, error } = await supabase.from('network_devices').select('*').order('created_at', { ascending: true });
  if (error) throw new Error(error.message);
  return data;
};

export const addDevice = async (device: Omit<NetworkDevice, 'user_id'>) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');
  
  const deviceWithUser = { ...device, user_id: user.id };
  const { data, error } = await supabase.from('network_devices').insert(deviceWithUser).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateDevice = async (id: string, updates: Partial<NetworkDevice>) => {
  const { data, error } = await supabase.from('network_devices').update(updates).eq('id', id).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateDeviceStatusByIp = async (ip_address: string, status: 'online' | 'offline') => {
  const { data, error } = await supabase
    .from('network_devices')
    .update({ 
      status, 
      last_ping: new Date().toISOString(),
      last_ping_result: status === 'online'
    })
    .eq('ip_address', ip_address)
    .select()
    .single();

  if (error) {
    console.error('Error updating device status:', error);
    throw new Error(error.message);
  }
  
  return data;
};

export const deleteDevice = async (id: string) => {
  const { error } = await supabase.from('network_devices').delete().eq('id', id);
  if (error) throw new Error(error.message);
};

export const getEdges = async () => {
  const { data, error } = await supabase.from('network_edges').select('id, source:source_id, target:target_id, connection_type');
  if (error) throw new Error(error.message);
  return data;
};

export const addEdgeToDB = async (edge: { source: string; target: string }) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');

  const { data, error } = await supabase.from('network_edges').insert({ source_id: edge.source, target_id: edge.target, user_id: user.id, connection_type: 'cat5' }).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const updateEdgeInDB = async (id: string, updates: { connection_type: string }) => {
  const { data, error } = await supabase.from('network_edges').update(updates).eq('id', id).select().single();
  if (error) throw new Error(error.message);
  return data;
};

export const deleteEdgeFromDB = async (edgeId: string) => {
  const { error } = await supabase.from('network_edges').delete().eq('id', edgeId);
  if (error) throw new Error(error.message);
};

export const importMap = async (mapData: MapData) => {
  const { error } = await supabase.rpc('import_network_map', {
    devices_data: mapData.devices,
    edges_data: mapData.edges,
  });
  if (error) throw new Error(`Import failed: ${error.message}`);
};

// Real-time subscription for device changes
export const subscribeToDeviceChanges = (callback: (payload: any) => void) => {
  const channel = supabase
    .channel('network-devices-changes')
    .on(
      'postgres_changes',
      { event: '*', schema: 'public', table: 'network_devices' },
      callback
    )
    .subscribe();

  return channel;
};