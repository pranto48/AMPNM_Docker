-- Enable the 'uuid-ossp' extension for UUID generation if not already enabled
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
-- Enable the 'hstore' extension for key-value pair storage, used in import_network_map
CREATE EXTENSION IF NOT EXISTS hstore;

-- Table: public.maps
-- This table stores information about each network map, including sharing details.
CREATE TABLE public.maps (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    name text NOT NULL,
    type text DEFAULT 'lan'::text NOT NULL,
    description text,
    background_color text,
    background_image_url text,
    is_default boolean DEFAULT false,
    share_id uuid UNIQUE, -- Unique ID for public sharing
    is_public boolean DEFAULT false, -- Flag to indicate if the map is publicly shareable
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT maps_pkey PRIMARY KEY (id),
    CONSTRAINT maps_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users (id) ON DELETE CASCADE
);

-- Enable Row Level Security (RLS) for the maps table
ALTER TABLE public.maps ENABLE ROW LEVEL SECURITY;

-- RLS Policies for maps table
CREATE POLICY "Enable read access for all users" ON public.maps FOR SELECT USING (true);
CREATE POLICY "Enable insert for authenticated users only" ON public.maps FOR INSERT WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Enable update for users based on user_id" ON public.maps FOR UPDATE USING (auth.uid() = user_id);
CREATE POLICY "Enable delete for users based on user_id" ON public.maps FOR DELETE USING (auth.uid() = user_id);

-- Table: public.network_devices
-- This table stores individual network devices.
CREATE TABLE public.network_devices (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    map_id uuid,
    name text NOT NULL,
    ip_address text,
    position_x numeric,
    position_y numeric,
    icon text DEFAULT 'server'::text NOT NULL,
    status text DEFAULT 'unknown'::text NOT NULL,
    ping_interval integer,
    icon_size integer DEFAULT 50,
    name_text_size integer DEFAULT 14,
    last_ping timestamp with time zone,
    last_ping_result boolean,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    updated_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT network_devices_pkey PRIMARY KEY (id),
    CONSTRAINT network_devices_map_id_fkey FOREIGN KEY (map_id) REFERENCES public.maps (id) ON DELETE CASCADE,
    CONSTRAINT network_devices_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users (id) ON DELETE CASCADE
);

-- Enable Row Level Security (RLS) for the network_devices table
ALTER TABLE public.network_devices ENABLE ROW LEVEL SECURITY;

-- RLS Policies for network_devices table
-- Public maps can be viewed by anyone, so devices on public maps should be readable.
CREATE POLICY "Enable read access for all users" ON public.network_devices FOR SELECT USING (
    EXISTS (SELECT 1 FROM public.maps WHERE public.maps.id = network_devices.map_id AND public.maps.is_public = TRUE)
    OR auth.uid() = user_id
);
CREATE POLICY "Enable insert for authenticated users only" ON public.network_devices FOR INSERT WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Enable update for users based on user_id" ON public.network_devices FOR UPDATE USING (auth.uid() = user_id);
CREATE POLICY "Enable delete for users based on user_id" ON public.network_devices FOR DELETE USING (auth.uid() = user_id);

-- Table: public.network_edges
-- This table stores connections between network devices.
CREATE TABLE public.network_edges (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    user_id uuid NOT NULL,
    map_id uuid NOT NULL,
    source_id uuid NOT NULL,
    target_id uuid NOT NULL,
    connection_type text DEFAULT 'cat5'::text NOT NULL,
    created_at timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT network_edges_pkey PRIMARY KEY (id),
    CONSTRAINT network_edges_map_id_fkey FOREIGN KEY (map_id) REFERENCES public.maps (id) ON DELETE CASCADE,
    CONSTRAINT network_edges_source_id_fkey FOREIGN KEY (source_id) REFERENCES public.network_devices (id) ON DELETE CASCADE,
    CONSTRAINT network_edges_target_id_fkey FOREIGN KEY (target_id) REFERENCES public.network_devices (id) ON DELETE CASCADE,
    CONSTRAINT network_edges_user_id_fkey FOREIGN KEY (user_id) REFERENCES auth.users (id) ON DELETE CASCADE
);

-- Enable Row Level Security (RLS) for the network_edges table
ALTER TABLE public.network_edges ENABLE ROW LEVEL SECURITY;

-- RLS Policies for network_edges table
-- Public maps can be viewed by anyone, so edges on public maps should be readable.
CREATE POLICY "Enable read access for all users" ON public.network_edges FOR SELECT USING (
    EXISTS (SELECT 1 FROM public.maps WHERE public.maps.id = network_edges.map_id AND public.maps.is_public = TRUE)
    OR auth.uid() = user_id
);
CREATE POLICY "Enable insert for authenticated users only" ON public.network_edges FOR INSERT WITH CHECK (auth.uid() = user_id);
CREATE POLICY "Enable update for users based on user_id" ON public.network_edges FOR UPDATE USING (auth.uid() = user_id);
CREATE POLICY "Enable delete for users based on user_id" ON public.network_edges FOR DELETE USING (auth.uid() = user_id);

-- Function: public.generate_map_share_link
-- Generates a unique share_id for a map and makes it public.
CREATE OR REPLACE FUNCTION public.generate_map_share_link(p_map_id uuid)
 RETURNS uuid
 LANGUAGE plpgsql
 SECURITY DEFINER
AS $function$
DECLARE
    v_share_id uuid;
BEGIN
    -- Check if the map belongs to the current authenticated user
    IF NOT EXISTS (SELECT 1 FROM public.maps WHERE id = p_map_id AND user_id = auth.uid()) THEN
        RAISE EXCEPTION 'Map not found or access denied.';
    END IF;

    -- Generate a new UUID for the share_id
    v_share_id := gen_random_uuid();

    -- Update the map with the new share_id and set it to public
    UPDATE public.maps
    SET
        share_id = v_share_id,
        is_public = TRUE,
        updated_at = now()
    WHERE id = p_map_id AND user_id = auth.uid();

    RETURN v_share_id;
END;
$function$;

-- Function: public.disable_map_share_link
-- Disables a map's public share link by setting share_id to NULL and is_public to FALSE.
CREATE OR REPLACE FUNCTION public.disable_map_share_link(p_map_id uuid)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
AS $function$
BEGIN
    -- Check if the map belongs to the current authenticated user
    IF NOT EXISTS (SELECT 1 FROM public.maps WHERE id = p_map_id AND user_id = auth.uid()) THEN
        RAISE EXCEPTION 'Map not found or access denied.';
    END IF;

    -- Set share_id to NULL and is_public to FALSE
    UPDATE public.maps
    SET
        share_id = NULL,
        is_public = FALSE,
        updated_at = now()
    WHERE id = p_map_id AND user_id = auth.uid();
END;
$function$;

-- Function: public.import_network_map
-- Imports devices and edges into a specified map, replacing existing data.
CREATE OR REPLACE FUNCTION public.import_network_map(p_map_id uuid, devices_data jsonb, edges_data jsonb)
 RETURNS void
 LANGUAGE plpgsql
 SECURITY DEFINER
AS $function$
DECLARE
    device_item jsonb;
    edge_item jsonb;
    old_device_id uuid;
    new_device_id uuid;
    device_id_map hstore; -- Use hstore for mapping old_id to new_id
BEGIN
    -- Check if the map belongs to the current authenticated user
    IF NOT EXISTS (SELECT 1 FROM public.maps WHERE id = p_map_id AND user_id = auth.uid()) THEN
        RAISE EXCEPTION 'Map not found or access denied.';
    END IF;

    -- Initialize hstore
    device_id_map := ''::hstore;

    -- Delete existing devices and edges for this map
    DELETE FROM public.network_edges WHERE map_id = p_map_id;
    DELETE FROM public.network_devices WHERE map_id = p_map_id;

    -- Insert new devices and build ID map
    FOR device_item IN SELECT * FROM jsonb_array_elements(devices_data)
    LOOP
        old_device_id := (device_item->>'id')::uuid;
        INSERT INTO public.network_devices (
            user_id, map_id, name, ip_address, position_x, position_y, icon,
            ping_interval, icon_size, name_text_size, last_ping, last_ping_result
        ) VALUES (
            auth.uid(),
            p_map_id,
            device_item->>'name',
            device_item->>'ip_address',
            (device_item->>'position_x')::numeric,
            (device_item->>'position_y')::numeric,
            device_item->>'icon',
            (device_item->>'ping_interval')::integer,
            (device_item->>'icon_size')::integer,
            (device_item->>'name_text_size')::integer,
            (device_item->>'last_ping')::timestamp with time zone,
            (device_item->>'last_ping_result')::boolean
        ) RETURNING id INTO new_device_id;

        device_id_map := device_id_map || hstore(old_device_id::text, new_device_id::text);
    END LOOP;

    -- Insert new edges, mapping old device IDs to new ones
    FOR edge_item IN SELECT * FROM jsonb_array_elements(edges_data)
    LOOP
        INSERT INTO public.network_edges (
            user_id, map_id, source_id, target_id, connection_type
        ) VALUES (
            auth.uid(),
            p_map_id,
            (device_id_map->(edge_item->>'source'))::uuid,
            (device_id_map->(edge_item->>'target'))::uuid,
            edge_item->>'connection_type'
        );
    END LOOP;
END;
$function$;