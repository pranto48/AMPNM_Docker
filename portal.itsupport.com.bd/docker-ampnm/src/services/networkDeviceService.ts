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

export interface NetworkMapDetails {
  id: string;
  name: string;
  background_color: string | null;
  background_image_url: string | null;
  share_id: string | null;
  is_public: boolean;
}

export const getMaps = async (): Promise<NetworkMapDetails[]> => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');

  const { data, error } = await supabase
    .from('maps')
    .select('id, name, background_color, background_image_url, share_id, is_public')
    .eq('user_id', user.id)
    .order('name', { ascending: true });

  if (error) throw new Error(error.message);
  return data;
};

export const getDevices = async (mapId?: string, shareId?: string) => {
  let query = supabase.from('network_devices').select('*').order('created_at', { ascending: true });

  if (mapId) {
    query = query.eq('map_id', mapId);
  } else if (shareId) {
    // For shared maps, we need to join with maps table to ensure it's public
    query = query
      .in('map_id', supabase.from('maps').select('id').eq('share_id', shareId).eq('is_public', true))
  }
  
  const { data, error } = await query;
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

export const getEdges = async (mapId?: string, shareId?: string) => {
  let query = supabase.from('network_edges').select('id, source:source_id, target:target_id, connection_type');

  if (mapId) {
    query = query.eq('map_id', mapId);
  } else if (shareId) {
    // For shared maps, we need to join with maps table to ensure it's public
    query = query
      .in('map_id', supabase.from('maps').select('id').eq('share_id', shareId).eq('is_public', true))
  }

  const { data, error } = await query;
  if (error) throw new Error(error.message);
  return data;
};

export const addEdgeToDB = async (edge: { source: string; target: string; map_id: string }) => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');

  const { data, error } = await supabase.from('network_edges').insert({ source_id: edge.source, target_id: edge.target, user_id: user.id, map_id: edge.map_id, connection_type: 'cat5' }).select().single();
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

export const importMap = async (mapData: MapData, mapId: string) => {
  const { error } = await supabase.rpc('import_network_map', {
    p_map_id: mapId, // Pass mapId to the RPC function
    devices_data: mapData.devices,
    edges_data: mapData.edges,
  });
  if (error) throw new Error(`Import failed: ${error.message}`);
};

// New functions for map sharing
export const getMapDetailsByShareId = async (shareId: string): Promise<NetworkMapDetails | null> => {
  const { data, error } = await supabase
    .from('maps')
    .select('id, name, background_color, background_image_url, share_id, is_public')
    .eq('share_id', shareId)
    .eq('is_public', true)
    .single();

  if (error && error.code !== 'PGRST116') { // PGRST116 means no rows found
    throw new Error(error.message);
  }
  return data;
};

export const generateMapShareLink = async (mapId: string): Promise<string> => {
  const { data, error } = await supabase.rpc('generate_map_share_link', { p_map_id: mapId });
  if (error) throw new Error(error.message);
  return data; // This should be the share_id
};

export const disableMapShareLink = async (mapId: string) => {
  const { error } = await supabase.rpc('disable_map_share_link', { p_map_id: mapId });
  if (error) throw new Error(error.message);
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