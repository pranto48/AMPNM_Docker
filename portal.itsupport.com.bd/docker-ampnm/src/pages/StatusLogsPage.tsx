import { useState, useEffect, useCallback, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { BarChart, Clock, Server, WifiOff } from 'lucide-react';
import { showError } from '@/utils/toast';
import Chart from 'chart.js/auto';
import 'chartjs-adapter-date-fns'; // Ensure date adapter is imported
import { getDevices } from '@/services/networkDeviceService';

interface MapOption {
  id: string;
  name: string;
}

interface StatusLogData {
  time_group: string;
  warning_count: number;
  critical_count: number;
  offline_count: number;
}

const StatusLogsPage = () => {
  const [maps, setMaps] = useState<MapOption[]>([]);
  const [devices, setDevices] = useState<any[]>([]);
  const [selectedMapId, setSelectedMapId] = useState<string | undefined>(undefined);
  const [selectedDeviceId, setSelectedDeviceId] = useState<string | undefined>(undefined);
  const [selectedPeriod, setSelectedPeriod] = useState<'live' | '24h' | '7d' | '30d'>('24h');
  const [isLoading, setIsLoading] = useState(true);
  const [chartData, setChartData] = useState<StatusLogData[]>([]);

  const chartRef = useRef<HTMLCanvasElement>(null);
  const chartInstance = useRef<Chart | null>(null);
  const liveIntervalRef = useRef<NodeJS.Timeout | null>(null);

  const userRole = (window as any).userRole || 'viewer';
  const isAdmin = userRole === 'admin';

  const fetchMaps = useCallback(async () => {
    try {
      const response = await fetch('api.php?action=get_maps');
      const mapsData = await response.json();
      if (mapsData && mapsData.length > 0) {
        setMaps(mapsData);
        setSelectedMapId(mapsData[0].id);
      } else {
        setMaps([]);
        setSelectedMapId(undefined);
      }
    } catch (error) {
      console.error('Failed to load maps:', error);
      showError('Failed to load network maps.');
    }
  }, []);

  const fetchDevices = useCallback(async (mapId: string) => {
    try {
      const result = await getDevices(mapId);
      setDevices(result);
    } catch (error) {
      console.error('Failed to load devices for map:', error);
      showError('Failed to load devices for filter.');
    }
  }, []);

  const fetchStatusLogs = useCallback(async () => {
    if (!selectedMapId) {
      setChartData([]);
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    if (liveIntervalRef.current) {
      clearInterval(liveIntervalRef.current);
      liveIntervalRef.current = null;
    }

    try {
      const params = new URLSearchParams({
        map_id: selectedMapId,
        period: selectedPeriod,
      });
      if (selectedDeviceId) {
        params.append('device_id', selectedDeviceId);
      }

      const response = await fetch(`api.php?action=get_status_logs&${params.toString()}`);
      const data = await response.json();
      if (response.ok && data) {
        setChartData(data);
      } else {
        showError(data.error || 'Failed to fetch status logs.');
        setChartData([]);
      }
    } catch (error) {
      console.error('Failed to fetch status logs:', error);
      showError('Failed to fetch status logs.');
      setChartData([]);
    } finally {
      setIsLoading(false);
      if (selectedPeriod === 'live' && isAdmin) {
        liveIntervalRef.current = setInterval(fetchStatusLogs, 30000); // Refresh every 30 seconds
      }
    }
  }, [selectedMapId, selectedDeviceId, selectedPeriod, isAdmin]);

  useEffect(() => {
    fetchMaps();
  }, [fetchMaps]);

  useEffect(() => {
    if (selectedMapId) {
      fetchDevices(selectedMapId);
      fetchStatusLogs();
    }
  }, [selectedMapId, fetchDevices, fetchStatusLogs]);

  useEffect(() => {
    fetchStatusLogs();
  }, [selectedDeviceId, selectedPeriod, fetchStatusLogs]);

  useEffect(() => {
    if (chartRef.current) {
      if (chartInstance.current) {
        chartInstance.current.destroy();
      }

      if (chartData.length === 0) {
        return;
      }

      const labels = chartData.map(d => d.time_group);
      const datasets = [
        { label: 'Critical', data: chartData.map(d => d.critical_count), backgroundColor: '#ef4444' },
        { label: 'Warning', data: chartData.map(d => d.warning_count), backgroundColor: '#f59e0b' },
        { label: 'Offline', data: chartData.map(d => d.offline_count), backgroundColor: '#64748b' },
      ];

      chartInstance.current = new Chart(chartRef.current, {
        type: 'bar',
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { labels: { color: '#cbd5e1' } } },
          scales: {
            x: {
              type: 'time',
              time: {
                unit: selectedPeriod === '24h' || selectedPeriod === 'live' ? 'hour' : 'day',
                tooltipFormat: selectedPeriod === 'live' ? 'MMM d, HH:mm' : 'MMM d',
                displayFormats: {
                  hour: 'HH:mm',
                  day: 'MMM d',
                },
              },
              ticks: { color: '#94a3b8' },
              grid: { color: '#334155' },
            },
            y: {
              stacked: true,
              beginAtZero: true,
              ticks: { color: '#94a3b8', stepSize: 1 },
              grid: { color: '#334155' },
            },
          },
        },
      });
    }
  }, [chartData, selectedPeriod]);

  useEffect(() => {
    return () => {
      if (liveIntervalRef.current) {
        clearInterval(liveIntervalRef.current);
      }
      if (chartInstance.current) {
        chartInstance.current.destroy();
      }
    };
  }, []);

  if (!isAdmin) {
    return (
      <div className="container mx-auto p-4 text-center text-red-400">
        <h1 className="text-3xl font-bold mb-4">Access Denied</h1>
        <p>You do not have permission to view status logs.</p>
        <Link to="/" className="text-blue-400 hover:underline mt-4 block">Go to Dashboard</Link>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-3xl font-bold text-white mb-6">Status Event Logs</h1>

      <Card className="mb-8">
        <CardHeader>
          <CardTitle>Filters</CardTitle>
          <CardDescription>Filter status events by map, device, and time period.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
              <Label htmlFor="mapSelector" className="block text-sm font-medium text-muted-foreground mb-1">Map</Label>
              <Select value={selectedMapId} onValueChange={setSelectedMapId}>
                <SelectTrigger id="mapSelector">
                  <SelectValue placeholder="Select a map" />
                </SelectTrigger>
                <SelectContent>
                  {maps.length === 0 ? (
                    <SelectItem value="no-maps" disabled>No maps found</SelectItem>
                  ) : (
                    maps.map(map => (
                      <SelectItem key={map.id} value={map.id}>{map.name}</SelectItem>
                    ))
                  )}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label htmlFor="deviceSelector" className="block text-sm font-medium text-muted-foreground mb-1">Device</Label>
              <Select value={selectedDeviceId} onValueChange={setSelectedDeviceId} disabled={!selectedMapId}>
                <SelectTrigger id="deviceSelector">
                  <SelectValue placeholder="All Devices" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="">All Devices</SelectItem>
                  {devices.map(device => (
                    <SelectItem key={device.id} value={device.id}>{device.name} ({device.ip || 'No IP'})</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="block text-sm font-medium text-muted-foreground mb-1">Time Period</Label>
              <div className="flex rounded-md bg-muted p-1">
                <Button
                  variant={selectedPeriod === 'live' ? 'secondary' : 'ghost'}
                  onClick={() => setSelectedPeriod('live')}
                  className="flex-1"
                  disabled={!isAdmin} // Only admin can view live
                >
                  Live
                </Button>
                <Button
                  variant={selectedPeriod === '24h' ? 'secondary' : 'ghost'}
                  onClick={() => setSelectedPeriod('24h')}
                  className="flex-1"
                >
                  24 Hours
                </Button>
                <Button
                  variant={selectedPeriod === '7d' ? 'secondary' : 'ghost'}
                  onClick={() => setSelectedPeriod('7d')}
                  className="flex-1"
                >
                  7 Days
                </Button>
                <Button
                  variant={selectedPeriod === '30d' ? 'secondary' : 'ghost'}
                  onClick={() => setSelectedPeriod('30d')}
                  className="flex-1"
                >
                  30 Days
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BarChart className="h-5 w-5" />
            Status Events in the Last {selectedPeriod === 'live' ? 'Hour (Live)' : selectedPeriod === '24h' ? '24 Hours' : selectedPeriod === '7d' ? '7 Days' : '30 Days'}
          </CardTitle>
          <CardDescription>Visual representation of device status changes over time.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-16">
              <Clock className="h-12 w-12 mx-auto mb-4 animate-spin text-primary" />
              <p className="text-muted-foreground">Loading status logs...</p>
            </div>
          ) : chartData.length === 0 ? (
            <div className="text-center py-16 text-muted-foreground">
              <WifiOff className="h-12 w-12 mx-auto mb-4" />
              <p>No status event data found for the selected period.</p>
            </div>
          ) : (
            <div className="h-96">
              <canvas ref={chartRef}></canvas>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default StatusLogsPage;