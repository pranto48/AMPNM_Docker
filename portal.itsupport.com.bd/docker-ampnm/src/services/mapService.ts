import { supabase } from '@/integrations/supabase/client';

export interface Map {
  id: string;
  name: string;
  type: string;
  background_color?: string;
  background_image_url?: string;
  is_default: boolean;
  created_at: string;
  updated_at: string;
  deviceCount?: number; // For display purposes
}

export interface CreateMapData {
  name: string;
  type: string;
  background_color?: string;
  background_image_url?: string;
}

export const getMaps = async (): Promise<Map[]> => {
  const { data, error } = await supabase
    .from('maps')
    .select('id, name, type, background_color, background_image_url, is_default, created_at, updated_at, deviceCount:network_devices(count)')
    .order('name', { ascending: true });

  if (error) {
    console.error('Error fetching maps:', error);
    throw new Error(error.message);
  }

  // Flatten the deviceCount from the nested object
  return data.map(map => ({
    ...map,
    deviceCount: map.deviceCount ? map.deviceCount[0].count : 0
  }));
};

export const createMap = async (mapData: CreateMapData): Promise<Map> => {
  const { data: { user } } = await supabase.auth.getUser();
  if (!user) throw new Error('User not authenticated');

  const { data, error } = await supabase
    .from('maps')
    .insert({ ...mapData, user_id: user.id })
    .select()
    .single();

  if (error) {
    console.error('Error creating map:', error);
    throw new Error(error.message);
  }
  return data;
};

export const updateMap = async (id: string, updates: Partial<CreateMapData>): Promise<Map> => {
  const { data, error } = await supabase
    .from('maps')
    .update(updates)
    .eq('id', id)
    .select()
    .single();

  if (error) {
    console.error('Error updating map:', error);
    throw new Error(error.message);
  }
  return data;
};

export const deleteMap = async (id: string): Promise<void> => {
  const { error } = await supabase
    .from('maps')
    .delete()
    .eq('id', id);

  if (error) {
    console.error('Error deleting map:', error);
    throw new Error(error.message);
  }
};