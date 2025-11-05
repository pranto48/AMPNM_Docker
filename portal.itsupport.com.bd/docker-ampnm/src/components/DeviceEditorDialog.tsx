import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { NetworkDevice } from '@/services/networkDeviceService';

const deviceSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  ip_address: z.string().min(1, 'IP address is required'),
  icon: z.string().min(1, 'Icon is required'),
  ping_interval: z.coerce.number().int().positive().optional().nullable(),
  icon_size: z.coerce.number().int().min(20).max(100).optional().nullable(),
  name_text_size: z.coerce.number().int().min(8).max(24).optional().nullable(),
});

interface DeviceEditorDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (device: Omit<NetworkDevice, 'id' | 'position_x' | 'position_y'>) => void;
  device?: Partial<NetworkDevice>;
}

const icons = ['server', 'router', 'printer', 'laptop', 'wifi', 'database'];

export const DeviceEditorDialog = ({ isOpen, onClose, onSave, device }: DeviceEditorDialogProps) => {
  const form = useForm<z.infer<typeof deviceSchema>>({
    resolver: zodResolver(deviceSchema),
    defaultValues: {
      name: device?.name || '',
      ip_address: device?.ip_address || '',
      icon: device?.icon || 'server',
      ping_interval: device?.ping_interval || undefined,
      icon_size: device?.icon_size || 50,
      name_text_size: device?.name_text_size || 14,
    },
  });

  const handleSubmit = (values: z.infer<typeof deviceSchema>) => {
    onSave(values);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>{device?.id ? 'Edit Device' : 'Add Device'}</DialogTitle>
          <DialogDescription>
            {device?.id ? 'Update the details for your network device.' : 'Add a new device to your network map.'}
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Service Name</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., Main Router" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="ip_address"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>IP Address</FormLabel>
                  <FormControl>
                    <Input placeholder="e.g., 192.168.1.1" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="icon"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Icon</FormLabel>
                  <Select onValueChange={field.onChange} defaultValue={field.value}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select an icon" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {icons.map((icon) => (
                        <SelectItem key={icon} value={icon} className="capitalize">
                          {icon}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="ping_interval"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Ping Interval (seconds)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      placeholder="e.g., 60 (leave blank for no auto ping)"
                      {...field}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="icon_size"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Icon Size (20-100px)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      placeholder="e.g., 50"
                      {...field}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="name_text_size"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Name Text Size (8-24px)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      placeholder="e.g., 14"
                      {...field}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value === '' ? null : +event.target.value)}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={onClose}>
                Cancel
              </Button>
              <Button type="submit">Save</Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
};