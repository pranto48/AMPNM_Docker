import React, { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { PlusCircle, Upload, Download, Share2, RefreshCw, Search, Cog, Expand, Compress } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface MapOption {
  id: string;
  name: string;
}

interface MapControlsProps {
  maps: MapOption[];
  currentMapId?: string;
  handleMapChange: (mapId: string) => void;
  onRefresh: () => void;
  liveRefreshEnabled: boolean;
  setLiveRefreshEnabled: (enabled: boolean) => void;
  onExport: () => void;
  onImportClick: () => void;
  onFileChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
  onShareMap: () => void;
  onToggleFullscreen: () => void;
  isFullScreen: boolean;
  isAdmin: boolean;
}

export const MapControls = ({
  maps,
  currentMapId,
  handleMapChange,
  onRefresh,
  liveRefreshEnabled,
  setLiveRefreshEnabled,
  onExport,
  onImportClick,
  onFileChange,
  onShareMap,
  onToggleFullscreen,
  isFullScreen,
  isAdmin,
}: MapControlsProps) => {
  const importInputRef = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();

  // Expose importInputRef to parent for triggering file dialog
  React.useImperativeHandle(importInputRef, () => importInputRef.current!);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-2">
          <Label htmlFor="map-select" className="text-white">Select Map:</Label>
          <Select value={currentMapId} onValueChange={handleMapChange}>
            <SelectTrigger id="map-select" className="w-[200px]">
              <SelectValue placeholder="Select a map" />
            </SelectTrigger>
            <SelectContent>
              {maps.length === 0 ? (
                <SelectItem value="no-maps" disabled>No maps available</SelectItem>
              ) : (
                maps.map((map) => (
                  <SelectItem key={map.id} value={map.id}>
                    {map.name}
                  </SelectItem>
                ))
              )}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-2">
          {isAdmin && (
            <>
              <Button onClick={() => console.log('New Map')} variant="outline" size="sm">New Map</Button>
              <Button onClick={() => console.log('Rename Map')} variant="outline" size="sm">Rename Map</Button>
              <Button onClick={() => console.log('Delete Map')} variant="destructive" size="sm">Delete Map</Button>
            </>
          )}
          <Button onClick={onShareMap} variant="outline" size="sm">
            <Share2 className="h-4 w-4 mr-2" />Share Map
          </Button>
        </div>
      </div>

      <div className="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-4 mb-6">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold text-white">{maps.find(m => m.id === currentMapId)?.name || 'No Map Selected'}</h2>
          <div className="flex items-center gap-2">
            <Button onClick={() => console.log('Scan Network')} variant="ghost" size="icon" title="Scan Network" disabled={!isAdmin}>
              <Search className="h-5 w-5" />
            </Button>
            <Button onClick={onRefresh} variant="ghost" size="icon" title="Refresh Device Statuses">
              <RefreshCw className="h-5 w-5" />
            </Button>
            
            <div className="flex items-center space-x-2 pl-2 ml-2 border-l border-slate-700">
              <Label htmlFor="live-refresh-toggle" className="text-sm text-slate-400 select-none cursor-pointer">Live Status</Label>
              <Switch
                id="live-refresh-toggle"
                checked={liveRefreshEnabled}
                onCheckedChange={setLiveRefreshEnabled}
              />
            </div>

            <div className="pl-2 ml-2 border-l border-slate-700 flex items-center gap-2">
              {isAdmin && (
                <>
                  <Button onClick={() => console.log('Place Existing Device')} variant="ghost" size="icon" title="Place Existing Device">
                    <PlusCircle className="h-5 w-5" />
                  </Button>
                  <Button onClick={() => navigate('/add-device')} variant="ghost" size="icon" title="Add New Device">
                    <PlusCircle className="h-5 w-5" />
                  </Button>
                  <Button onClick={() => console.log('Add Connection')} variant="ghost" size="icon" title="Add Connection">
                    <PlusCircle className="h-5 w-5" />
                  </Button>
                  <Button onClick={onExport} variant="ghost" size="icon" title="Export Map">
                    <Download className="h-5 w-5" />
                  </Button>
                  <Button onClick={onImportClick} variant="ghost" size="icon" title="Import Map">
                    <Upload className="h-5 w-5" />
                  </Button>
                  <input 
                    type="file" 
                    ref={importInputRef} 
                    onChange={onFileChange} 
                    accept="application/json" 
                    className="hidden" 
                  />
                  <Button onClick={() => console.log('Map Settings')} variant="ghost" size="icon" title="Map Settings">
                    <Cog className="h-5 w-5" />
                  </Button>
                </>
              )}
              <Button onClick={onToggleFullscreen} variant="ghost" size="icon" title="Toggle Fullscreen">
                {isFullScreen ? <Compress className="h-5 w-5" /> : <Expand className="h-5 w-5" />}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};