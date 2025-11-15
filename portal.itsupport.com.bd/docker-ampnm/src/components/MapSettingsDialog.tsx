import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Map, updateMap, uploadMapBackground } from '@/services/networkDeviceService';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';
import { Copy, Loader2, RefreshCcw } from 'lucide-react';

interface MapSettingsDialogProps {
  isOpen: boolean;
  onClose: () => void;
  currentMap: Map | undefined;
  onMapUpdated: () => void; // Callback to refresh maps list in parent
}

export const MapSettingsDialog = ({ isOpen, onClose, currentMap, onMapUpdated }: MapSettingsDialogProps) => {
  const [backgroundColor, setBackgroundColor] = useState(currentMap?.background_color || '#1e293b');
  const [backgroundImageUrl, setBackgroundImageUrl] = useState(currentMap?.background_image_url || '');
  const [publicViewEnabled, setPublicViewEnabled] = useState(currentMap?.public_view_enabled || false);
  const [isUploading, setIsUploading] = useState(false);
  const [publicLink, setPublicLink] = useState('');

  useEffect(() => {
    if (currentMap) {
      setBackgroundColor(currentMap.background_color || '#1e293b');
      setBackgroundImageUrl(currentMap.background_image_url || '');
      setPublicViewEnabled(currentMap.public_view_enabled);
      if (currentMap.public_view_enabled) {
        // Hardcoded IP and port as per user request
        setPublicLink(`http://192.168.20.5:2266/public_map.php?map_id=${currentMap.id}`);
      } else {
        setPublicLink('');
      }
    }
  }, [currentMap]);

  const handleSave = async () => {
    if (!currentMap?.id) return;

    const toastId = showLoading('Saving map settings...');
    try {
      await updateMap(currentMap.id, {
        background_color: backgroundColor,
        background_image_url: backgroundImageUrl,
        public_view_enabled: publicViewEnabled,
      });
      dismissToast(toastId);
      showSuccess('Map settings saved successfully!');
      onMapUpdated(); // Refresh maps in parent
      onClose();
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Failed to save map settings:', error);
      showError(error.message || 'Failed to save map settings.');
    }
  };

  const handleResetBackground = async () => {
    if (!currentMap?.id) return;

    if (!window.confirm('Are you sure you want to reset the map background and disable public view?')) return;

    const toastId = showLoading('Resetting map settings...');
    try {
      await updateMap(currentMap.id, {
        background_color: null,
        background_image_url: null,
        public_view_enabled: false,
      });
      dismissToast(toastId);
      showSuccess('Map background and public view reset to default!');
      onMapUpdated(); // Refresh maps in parent
      onClose();
    } catch (error: any) {
      dismissToast(toastId);
      console.error('Failed to reset map settings:', error);
      showError(error.message || 'Failed to reset map settings.');
    }
  };

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    if (!currentMap?.id) return;
    const file = event.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    const toastId = showLoading('Uploading background image...');
    try {
      const result = await uploadMapBackground(currentMap.id, file);
      if (result.success) {
        setBackgroundImageUrl(result.url);
        showSuccess('Image uploaded. Click Save to apply.');
      } else {
        throw new Error(result.error || 'Unknown upload error.');
      }
    } catch (error: any) {
      console.error('Failed to upload map background:', error);
      showError(error.message || 'Failed to upload image.');
    } finally {
      dismissToast(toastId);
      setIsUploading(false);
      if (event.target) event.target.value = ''; // Clear file input
    }
  };

  const handleCopyPublicLink = async () => {
    if (publicLink) {
      try {
        await navigator.clipboard.writeText(publicLink);
        showSuccess('Public link copied to clipboard!');
      } catch (err) {
        console.error('Failed to copy public link:', err);
        showError('Failed to copy public link. Please copy manually.');
      }
    }
  };

  if (!currentMap) return null;

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Map Settings: {currentMap.name}</DialogTitle>
          <DialogDescription>
            Customize the appearance and sharing options for this map.
          </DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 py-4">
          <div className="space-y-2">
            <Label htmlFor="mapBgColor">Background Color</Label>
            <div className="flex items-center gap-2">
              <Input
                id="mapBgColor"
                type="color"
                value={backgroundColor}
                onChange={(e) => setBackgroundColor(e.target.value)}
                className="h-10 w-14 p-1 cursor-pointer"
              />
              <Input
                type="text"
                value={backgroundColor}
                onChange={(e) => setBackgroundColor(e.target.value)}
                placeholder="#1e293b"
                className="flex-1"
              />
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="mapBgImageUrl">Background Image URL</Label>
            <Input
              id="mapBgImageUrl"
              value={backgroundImageUrl}
              onChange={(e) => setBackgroundImageUrl(e.target.value)}
              placeholder="Leave blank for no image"
            />
          </div>
          <div className="text-center text-muted-foreground text-sm">OR</div>
          <div className="space-y-2">
            <Label htmlFor="mapBgUpload">Upload Background Image</Label>
            <Input
              id="mapBgUpload"
              type="file"
              accept="image/*"
              onChange={handleFileUpload}
              disabled={isUploading}
              className="file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary/20 file:text-primary"
            />
            {isUploading && (
              <div className="flex items-center text-sm text-muted-foreground">
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Uploading...
              </div>
            )}
          </div>

          <div className="border-t pt-4 mt-4 space-y-3">
            <h3 className="text-lg font-semibold">Public View Settings</h3>
            <div className="flex items-center space-x-2">
              <Checkbox
                id="publicViewToggle"
                checked={publicViewEnabled}
                onCheckedChange={(checked) => setPublicViewEnabled(checked as boolean)}
              />
              <Label htmlFor="publicViewToggle" className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                Enable Public View
              </Label>
            </div>
            <p className="text-sm text-muted-foreground">
              Allow anyone with the link to view this map without logging in.
            </p>
            {publicViewEnabled && currentMap.id && (
              <div className="space-y-2">
                <Label>Public Link:</Label>
                <div className="flex items-center gap-2">
                  <Input value={publicLink} readOnly className="flex-grow" />
                  <Button type="button" size="sm" onClick={handleCopyPublicLink}>
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            )}
          </div>
        </div>
        <DialogFooter className="flex justify-between items-center">
          <Button type="button" variant="outline" onClick={handleResetBackground}>
            <RefreshCcw className="h-4 w-4 mr-2" />Reset to Default
          </Button>
          <div className="flex gap-2">
            <Button type="button" variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" onClick={handleSave}>
              Save Changes
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};