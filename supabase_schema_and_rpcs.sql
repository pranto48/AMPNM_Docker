-- Ensure the device_status_enum type exists
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'device_status_enum') THEN
        CREATE TYPE public.device_status_enum AS ENUM ('online', 'offline', 'unknown');
    END IF;
END $$;

-- Table: public.maps
-- Ensure share_id and is_public columns exist and RLS policies are correct
CREATE TABLE IF NOT EXISTS public.maps (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  name TEXT NOT NULL,
  background_color TEXT,
  background_image_url TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  share_id UUID UNIQUE, -- New column
  is_public BOOLEAN DEFAULT FALSE -- New column
);

ALTER TABLE public.maps ENABLE ROW LEVEL SECURITY;

-- Existing policies (ensure these are present)
DROP POLICY IF EXISTS "Users can view their own maps." ON public.maps;
CREATE POLICY "Users can view their own maps." ON public.maps
  FOR SELECT USING (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can insert their own maps." ON public.maps;
CREATE POLICY "Users can insert their own maps." ON public.maps
  FOR INSERT WITH CHECK (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can update their own maps." ON public.maps;
CREATE POLICY "Users can update their own maps." ON public.maps
  FOR UPDATE USING (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can delete their own maps." ON public.maps;
CREATE POLICY "Users can delete their own maps." ON public.maps
  FOR DELETE USING (auth.uid() = user_id);

-- New policy for public access to shared maps
DROP POLICY IF EXISTS "Public can view shared maps" ON public.maps;
CREATE POLICY "Public can view shared maps" ON public.maps
  FOR SELECT USING (is_public = TRUE AND share_id IS NOT NULL);

-- Table: public.network_devices
-- Ensure map_id column exists and RLS policies are correct for shared maps
CREATE TABLE IF NOT EXISTS public.network_devices (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  map_id UUID REFERENCES public.maps(id) ON DELETE CASCADE NOT NULL, -- Crucial for map association
  name TEXT NOT NULL,
  ip_address TEXT,
  position_x FLOAT NOT NULL,
  position_y FLOAT NOT NULL,
  icon TEXT NOT NULL,
  status public.device_status_enum DEFAULT 'unknown' NOT NULL,
  ping_interval INTEGER,
  icon_size INTEGER,
  name_text_size INTEGER,
  last_ping TIMESTAMPTZ,
  last_ping_result BOOLEAN,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE public.network_devices ENABLE ROW LEVEL SECURITY;

-- Existing policies (ensure these are present)
DROP POLICY IF EXISTS "Users can view their own devices." ON public.network_devices;
CREATE POLICY "Users can view their own devices." ON public.network_devices
  FOR SELECT USING (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can insert their own devices." ON public.network_devices;
CREATE POLICY "Users can insert their own devices." ON public.network_devices
  FOR INSERT WITH CHECK (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can update their own devices." ON public.network_devices;
CREATE POLICY "Users can update their own devices." ON public.network_devices
  FOR UPDATE USING (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can delete their own devices." ON public.network_devices;
CREATE POLICY "Users can delete their own devices." ON public.network_devices
  FOR DELETE USING (auth.uid() = user_id);

-- New policy for public access to devices on shared maps
DROP POLICY IF EXISTS "Public can view devices on shared maps" ON public.network_devices;
CREATE POLICY "Public can view devices on shared maps" ON public.network_devices
  FOR SELECT USING (EXISTS (SELECT 1 FROM public.maps WHERE public.maps.id = network_devices.map_id AND public.maps.is_public = TRUE AND public.maps.share_id IS NOT NULL));

-- Table: public.network_edges
-- Ensure map_id column exists and RLS policies are correct for shared maps
CREATE TABLE IF NOT EXISTS public.network_edges (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
  map_id UUID REFERENCES public.maps(id) ON DELETE CASCADE NOT NULL, -- Crucial for map association
  source_id UUID REFERENCES public.network_devices(id) ON DELETE CASCADE NOT NULL,
  target_id UUID REFERENCES public.network_devices(id) ON DELETE CASCADE NOT NULL,
  connection_type TEXT DEFAULT 'cat5' NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE public.network_edges ENABLE ROW LEVEL SECURITY;

-- Existing policies (ensure these are present)
DROP POLICY IF EXISTS "Users can view their own edges." ON public.network_edges;
CREATE POLICY "Users can view their own edges." ON public.network_edges
  FOR SELECT USING (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can insert their own edges." ON public.network_edges;
CREATE POLICY "Users can insert their own edges." ON public.network_edges
  FOR INSERT WITH CHECK (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can update their own edges." ON public.network_edges
  FOR UPDATE USING (auth.uid() = user_id);

DROP POLICY IF EXISTS "Users can delete their own edges." ON public.network_edges;
CREATE POLICY "Users can delete their own edges." ON public.network_edges
  FOR DELETE USING (auth.uid() = user_id);

-- New policy for public access to edges on shared maps
DROP POLICY IF EXISTS "Public can view edges on shared maps" ON public.network_edges;
CREATE POLICY "Public can view edges on shared maps" ON public.network_edges
  FOR SELECT USING (EXISTS (SELECT 1 FROM public.maps WHERE public.maps.id = network_edges.map_id AND public.maps.is_public = TRUE AND public.maps.share_id IS NOT NULL));


-- RPC Function: generate_map_share_link
CREATE OR REPLACE FUNCTION generate_map_share_link(p_map_id UUID)
RETURNS UUID
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    new_share_id UUID;
    current_user_id UUID;
BEGIN
    SELECT auth.uid() INTO current_user_id;

    IF current_user_id IS NULL THEN
        RAISE EXCEPTION 'User not authenticated.';
    END IF;

    -- Verify map ownership
    PERFORM 1 FROM public.maps WHERE id = p_map_id AND user_id = current_user_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Map with ID % not found or not owned by current user.', p_map_id;
    END IF;

    -- Generate a new UUID for the share link
    new_share_id := gen_random_uuid();

    -- Update the map to set the share_id and make it public
    UPDATE public.maps
    SET
        share_id = new_share_id,
        is_public = TRUE,
        updated_at = NOW()
    WHERE
        id = p_map_id;

    RETURN new_share_id;
END;
$$;

-- Grant usage to authenticated role
GRANT EXECUTE ON FUNCTION generate_map_share_link(UUID) TO authenticated;


-- RPC Function: disable_map_share_link
CREATE OR REPLACE FUNCTION disable_map_share_link(p_map_id UUID)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    current_user_id UUID;
BEGIN
    SELECT auth.uid() INTO current_user_id;

    IF current_user_id IS NULL THEN
        RAISE EXCEPTION 'User not authenticated.';
    END IF;

    -- Verify map ownership
    PERFORM 1 FROM public.maps WHERE id = p_map_id AND user_id = current_user_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Map with ID % not found or not owned by current user.', p_map_id;
    END IF;

    -- Update the map to remove the share_id and make it private
    UPDATE public.maps
    SET
        share_id = NULL,
        is_public = FALSE,
        updated_at = NOW()
    WHERE
        id = p_map_id;
END;
$$;

-- Grant usage to authenticated role
GRANT EXECUTE ON FUNCTION disable_map_share_link(UUID) TO authenticated;


-- RPC Function: import_network_map
CREATE OR REPLACE FUNCTION import_network_map(
    p_map_id UUID,
    devices_data JSONB,
    edges_data JSONB
)
RETURNS VOID
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    device_record JSONB;
    edge_record JSONB;
    old_device_id TEXT;
    new_device_id UUID;
    device_id_map JSONB DEFAULT '{}'::JSONB;
    current_user_id UUID;
BEGIN
    -- Get the current user ID from the session
    SELECT auth.uid() INTO current_user_id;

    IF current_user_id IS NULL THEN
        RAISE EXCEPTION 'User not authenticated.';
    END IF;

    -- Verify map ownership
    PERFORM 1 FROM public.maps WHERE id = p_map_id AND user_id = current_user_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Map with ID % not found or not owned by current user.', p_map_id;
    END IF;

    -- Delete existing devices and edges for this map
    DELETE FROM public.network_edges WHERE map_id = p_map_id;
    DELETE FROM public.network_devices WHERE map_id = p_map_id;

    -- Insert new devices and build a mapping from old_id to new_id
    FOR device_record IN SELECT * FROM jsonb_array_elements(devices_data)
    LOOP
        old_device_id := device_record->>'id';
        INSERT INTO public.network_devices (
            user_id, map_id, name, ip_address, position_x, position_y, icon,
            ping_interval, icon_size, name_text_size, last_ping, last_ping_result, status
        ) VALUES (
            current_user_id,
            p_map_id,
            device_record->>'name',
            device_record->>'ip_address',
            (device_record->>'position_x')::FLOAT,
            (device_record->>'position_y')::FLOAT,
            device_record->>'icon',
            (device_record->>'ping_interval')::INTEGER,
            (device_record->>'icon_size')::INTEGER,
            (device_record->>'name_text_size')::INTEGER,
            (device_record->>'last_ping')::TIMESTAMPTZ,
            (device_record->>'last_ping_result')::BOOLEAN,
            (device_record->>'status')::public.device_status_enum -- Assuming device_status_enum exists
        )
        RETURNING id INTO new_device_id;

        device_id_map := jsonb_set(device_id_map, ARRAY[old_device_id], to_jsonb(new_device_id));
    END LOOP;

    -- Insert new edges using the new device IDs
    FOR edge_record IN SELECT * FROM jsonb_array_elements(edges_data)
    LOOP
        INSERT INTO public.network_edges (
            user_id, map_id, source_id, target_id, connection_type
        ) VALUES (
            current_user_id,
            p_map_id,
            (device_id_map->>(edge_record->>'source'))::UUID,
            (device_id_map->>(edge_record->>'target'))::UUID,
            edge_record->>'connection_type'
        );
    END LOOP;
END;
$$;

-- Grant usage to authenticated role
GRANT EXECUTE ON FUNCTION import_network_map(UUID, JSONB, JSONB) TO authenticated;