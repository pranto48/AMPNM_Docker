import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Save, Bell, Mail, Trash2, Edit, BellOff } from 'lucide-react';
import { showSuccess, showError, showLoading, dismissToast } from '@/utils/toast';

interface SmtpSettings {
  host: string;
  port: number;
  username: string;
  password?: string; // Masked password from API
  encryption: 'none' | 'ssl' | 'tls';
  from_email: string;
  from_name?: string;
}

interface DeviceOption {
  id: string;
  name: string;
  ip: string;
  map_name?: string;
}

interface Subscription {
  id: string;
  device_id: string;
  recipient_email: string;
  notify_on_online: boolean;
  notify_on_offline: boolean;
  notify_on_warning: boolean;
  notify_on_critical: boolean;
}

const EmailNotificationsPage = () => {
  const [smtpSettings, setSmtpSettings] = useState<SmtpSettings>({
    host: '', port: 587, username: '', password: '********', encryption: 'tls', from_email: '', from_name: ''
  });
  const [devices, setDevices] = useState<DeviceOption[]>([]);
  const [selectedDeviceId, setSelectedDeviceId] = useState<string | undefined>(undefined);
  const [subscriptions, setSubscriptions] = useState<Subscription[]>([]);
  const [isSmtpSaving, setIsSmtpSaving] = useState(false);
  const [isSubscriptionSaving, setIsSubscriptionSaving] = useState(false);
  const [editingSubscription, setEditingSubscription] = useState<Subscription | null>(null);

  const userRole = (window as any).userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const fetchSmtpSettings = useCallback(async () => {
    try {
      const response = await fetch('api.php?action=get_smtp_settings');
      const data = await response.json();
      if (response.ok && data) {
        setSmtpSettings(prev => ({ ...prev, ...data }));
      } else {
        showError(data.error || 'Failed to load SMTP settings.');
      }
    } catch (error) {
      console.error('Failed to load SMTP settings:', error);
      showError('Failed to load SMTP settings.');
    }
  }, []);

  const saveSmtpSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isAdmin) {
      showError('You do not have permission to save SMTP settings.');
      return;
    }
    setIsSmtpSaving(true);
    const toastId = showLoading('Saving SMTP settings...');
    try {
      const response = await fetch('api.php?action=save_smtp_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(smtpSettings),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        showSuccess(data.message);
        fetchSmtpSettings(); // Re-fetch to get masked password
      } else {
        showError(data.error || 'Failed to save SMTP settings.');
      }
    } catch (error) {
      console.error('Failed to save SMTP settings:', error);
      showError('Failed to save SMTP settings.');
    } finally {
      dismissToast(toastId);
      setIsSmtpSaving(false);
    }
  };

  const fetchDevicesForSubscriptions = useCallback(async () => {
    try {
      const response = await fetch('api.php?action=get_all_devices_for_subscriptions');
      const data = await response.json();
      if (response.ok && data) {
        setDevices(data);
      } else {
        showError(data.error || 'Failed to load devices for subscriptions.');
      }
    } catch (error) {
      console.error('Failed to load devices for subscriptions:', error);
      showError('Failed to load devices for subscriptions.');
    }
  }, []);

  const fetchDeviceSubscriptions = useCallback(async (deviceId: string) => {
    try {
      const response = await fetch(`api.php?action=get_device_subscriptions&device_id=${deviceId}`);
      const data = await response.json();
      if (response.ok && data) {
        setSubscriptions(data);
      } else {
        showError(data.error || 'Failed to load subscriptions.');
      }
    } catch (error) {
      console.error('Failed to load subscriptions:', error);
      showError('Failed to load subscriptions.');
    }
  }, []);

  const saveSubscription = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isAdmin) {
      showError('You do not have permission to save subscriptions.');
      return;
    }
    if (!selectedDeviceId) {
      showError('Please select a device.');
      return;
    }

    setIsSubscriptionSaving(true);
    const toastId = showLoading('Saving subscription...');

    const formData = new FormData(e.target as HTMLFormElement);
    const newSubscription: Partial<Subscription> = {
      device_id: selectedDeviceId,
      recipient_email: formData.get('recipient_email') as string,
      notify_on_online: formData.has('notify_on_online'),
      notify_on_offline: formData.has('notify_on_offline'),
      notify_on_warning: formData.has('notify_on_warning'),
      notify_on_critical: formData.has('notify_on_critical'),
    };

    if (editingSubscription) {
      newSubscription.id = editingSubscription.id;
    }

    try {
      const response = await fetch('api.php?action=save_device_subscription', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newSubscription),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        showSuccess(data.message);
        setEditingSubscription(null);
        (e.target as HTMLFormElement).reset();
        fetchDeviceSubscriptions(selectedDeviceId);
      } else {
        showError(data.error || 'Failed to save subscription.');
      }
    } catch (error) {
      console.error('Failed to save subscription:', error);
      showError('Failed to save subscription.');
    } finally {
      dismissToast(toastId);
      setIsSubscriptionSaving(false);
    }
  };

  const deleteSubscription = async (id: string) => {
    if (!isAdmin) {
      showError('You do not have permission to delete subscriptions.');
      return;
    }
    if (!window.confirm('Are you sure you want to delete this subscription?')) return;

    const toastId = showLoading('Deleting subscription...');
    try {
      const response = await fetch('api.php?action=delete_device_subscription', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
      });
      const data = await response.json();
      if (response.ok && data.success) {
        showSuccess(data.message);
        fetchDeviceSubscriptions(selectedDeviceId!);
      } else {
        showError(data.error || 'Failed to delete subscription.');
      }
    } catch (error) {
      console.error('Failed to delete subscription:', error);
      showError('Failed to delete subscription.');
    } finally {
      dismissToast(toastId);
    }
  };

  useEffect(() => {
    fetchSmtpSettings();
    fetchDevicesForSubscriptions();
  }, [fetchSmtpSettings, fetchDevicesForSubscriptions]);

  useEffect(() => {
    if (selectedDeviceId) {
      fetchDeviceSubscriptions(selectedDeviceId);
    } else {
      setSubscriptions([]);
    }
  }, [selectedDeviceId, fetchDeviceSubscriptions]);

  if (!isAdmin) {
    return (
      <div className="container mx-auto p-4 text-center text-red-400">
        <h1 className="text-3xl font-bold mb-4">Access Denied</h1>
        <p>You do not have permission to manage email notifications.</p>
        <Link to="/" className="text-blue-400 hover:underline mt-4 block">Go to Dashboard</Link>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-3xl font-bold text-white mb-6">Email Notifications</h1>

      {/* SMTP Settings Card */}
      <Card className="mb-8">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5" />
            SMTP Server Settings
          </CardTitle>
          <CardDescription>Configure your SMTP server for sending email notifications.</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={saveSmtpSettings} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="smtpHost">SMTP Host</Label>
                <Input id="smtpHost" value={smtpSettings.host} onChange={e => setSmtpSettings(prev => ({ ...prev, host: e.target.value }))} required />
              </div>
              <div>
                <Label htmlFor="smtpPort">Port</Label>
                <Input id="smtpPort" type="number" value={smtpSettings.port} onChange={e => setSmtpSettings(prev => ({ ...prev, port: parseInt(e.target.value) }))} required />
              </div>
              <div>
                <Label htmlFor="smtpUsername">Username</Label>
                <Input id="smtpUsername" value={smtpSettings.username} onChange={e => setSmtpSettings(prev => ({ ...prev, username: e.target.value }))} required />
              </div>
              <div>
                <Label htmlFor="smtpPassword">Password</Label>
                <Input id="smtpPassword" type="password" value={smtpSettings.password} onChange={e => setSmtpSettings(prev => ({ ...prev, password: e.target.value }))} placeholder="Leave blank to keep current" />
              </div>
              <div>
                <Label htmlFor="smtpEncryption">Encryption</Label>
                <Select value={smtpSettings.encryption} onValueChange={value => setSmtpSettings(prev => ({ ...prev, encryption: value as 'none' | 'ssl' | 'tls' }))}>
                  <SelectTrigger id="smtpEncryption">
                    <SelectValue placeholder="Select encryption" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None</SelectItem>
                    <SelectItem value="ssl">SSL</SelectItem>
                    <SelectItem value="tls">TLS</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="smtpFromEmail">From Email Address</Label>
                <Input id="smtpFromEmail" type="email" value={smtpSettings.from_email} onChange={e => setSmtpSettings(prev => ({ ...prev, from_email: e.target.value }))} required />
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="smtpFromName">From Name (Optional)</Label>
                <Input id="smtpFromName" value={smtpSettings.from_name} onChange={e => setSmtpSettings(prev => ({ ...prev, from_name: e.target.value }))} />
              </div>
            </div>
            <div className="flex justify-end">
              <Button type="submit" disabled={isSmtpSaving}>
                <Save className={`h-4 w-4 mr-2 ${isSmtpSaving ? 'animate-spin' : ''}`} />
                {isSmtpSaving ? 'Saving...' : 'Save Settings'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>

      {/* Device Subscriptions Card */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Bell className="h-5 w-5" />
            Device Email Subscriptions
          </CardTitle>
          <CardDescription>Manage who receives email notifications for device status changes.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-4">
            <Label htmlFor="deviceSelect">Select Device</Label>
            <Select value={selectedDeviceId} onValueChange={setSelectedDeviceId}>
              <SelectTrigger id="deviceSelect">
                <SelectValue placeholder="-- Select a device --" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">-- Select a device --</SelectItem>
                {devices.map(device => (
                  <SelectItem key={device.id} value={device.id}>
                    {device.name} ({device.ip || 'No IP'}) {device.map_name ? `[${device.map_name}]` : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {selectedDeviceId && (
            <Card className="p-4 mt-4">
              <CardHeader>
                <CardTitle className="text-lg">
                  {editingSubscription ? 'Edit Subscription' : 'Add New Subscription'} for <span className="text-primary">{devices.find(d => d.id === selectedDeviceId)?.name}</span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <form onSubmit={saveSubscription} className="space-y-3">
                  <Input type="hidden" name="id" value={editingSubscription?.id || ''} />
                  <Input type="hidden" name="device_id" value={selectedDeviceId} />
                  <div>
                    <Label htmlFor="recipientEmail">Recipient Email</Label>
                    <Input id="recipientEmail" name="recipient_email" type="email" required defaultValue={editingSubscription?.recipient_email || ''} />
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <Label className="flex items-center">
                      <Checkbox name="notify_on_online" defaultChecked={editingSubscription?.notify_on_online ?? true} />
                      <span className="ml-2">Notify on Online</span>
                    </Label>
                    <Label className="flex items-center">
                      <Checkbox name="notify_on_offline" defaultChecked={editingSubscription?.notify_on_offline ?? true} />
                      <span className="ml-2">Notify on Offline</span>
                    </Label>
                    <Label className="flex items-center">
                      <Checkbox name="notify_on_warning" defaultChecked={editingSubscription?.notify_on_warning ?? false} />
                      <span className="ml-2">Notify on Warning</span>
                    </Label>
                    <Label className="flex items-center">
                      <Checkbox name="notify_on_critical" defaultChecked={editingSubscription?.notify_on_critical ?? false} />
                      <span className="ml-2">Notify on Critical</span>
                    </Label>
                  </div>
                  <div className="flex justify-end gap-2">
                    {editingSubscription && (
                      <Button type="button" variant="outline" onClick={() => setEditingSubscription(null)}>Cancel Edit</Button>
                    )}
                    <Button type="submit" disabled={isSubscriptionSaving}>
                      <Save className={`h-4 w-4 mr-2 ${isSubscriptionSaving ? 'animate-spin' : ''}`} />
                      {isSubscriptionSaving ? 'Saving...' : (editingSubscription ? 'Update Subscription' : 'Add Subscription')}
                    </Button>
                  </div>
                </form>
              </CardContent>
            </Card>
          )}

          <div className="mt-6">
            <h3 className="text-lg font-semibold text-white mb-3">Active Subscriptions</h3>
            {subscriptions.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <BellOff className="h-12 w-12 mx-auto mb-4" />
                <p>No subscriptions for this device yet.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Recipient</TableHead>
                      <TableHead>Triggers</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {subscriptions.map(sub => (
                      <TableRow key={sub.id}>
                        <TableCell className="font-medium">{sub.recipient_email}</TableCell>
                        <TableCell>
                          {sub.notify_on_online && <Badge variant="secondary" className="mr-1 mb-1">Online</Badge>}
                          {sub.notify_on_offline && <Badge variant="destructive" className="mr-1 mb-1">Offline</Badge>}
                          {sub.notify_on_warning && <Badge variant="outline" className="mr-1 mb-1">Warning</Badge>}
                          {sub.notify_on_critical && <Badge variant="destructive" className="mr-1 mb-1">Critical</Badge>}
                        </TableCell>
                        <TableCell className="text-right">
                          <Button variant="ghost" size="icon" onClick={() => setEditingSubscription(sub)} title="Edit Subscription">
                            <Edit className="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onClick={() => deleteSubscription(sub.id)} title="Delete Subscription">
                            <Trash2 className="h-4 w-4 text-red-500" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default EmailNotificationsPage;