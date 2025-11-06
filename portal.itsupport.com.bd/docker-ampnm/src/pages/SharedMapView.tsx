import React from 'react';
import ReactDOM from 'react-dom/client';
import NetworkMap from '@/components/NetworkMap';
import { NetworkDevice, NetworkMapDetails } from '@/services/networkDeviceService';
import { Toaster as Sonner } from "@/components/ui/sonner"; // Import Sonner for toasts

interface SharedMapViewProps {
  mapDetails: NetworkMapDetails | null;
  devices: NetworkDevice[];
  edges: any[]; // Adjust type if you have a specific Edge interface
  error: string | null;
}

const SharedMapView: React.FC<SharedMapViewProps> = ({ mapDetails, devices, edges, error }) => {
  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <div className="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-8 w-full max-w-md text-center">
          <i className="fas fa-exclamation-triangle text-red-500 text-6xl mb-4"></i>
          <h1 className="text-2xl font-bold text-white mb-2">Error Loading Map</h1>
          <p className="text-slate-400 mb-4">{error}</p>
          <a href="index.php" className="px-6 py-3 bg-cyan-600 text-white font-semibold rounded-lg hover:bg-cyan-700">Go to Dashboard</a>
        </div>
      </div>
    );
  }

  if (!mapDetails) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 p-4">
        <div className="bg-slate-800 border border-slate-700 rounded-lg shadow-xl p-8 w-full max-w-md text-center">
          <i className="fas fa-spinner fa-spin text-cyan-400 text-6xl mb-4"></i>
          <h1 className="text-2xl font-bold text-white mb-2">Loading Map...</h1>
          <p className="text-slate-400 mb-4">Please wait while the map data is loaded.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <i className="fas fa-globe text-cyan-400 text-2xl"></i>
          <h1 className="text-3xl font-bold">{mapDetails.name} (Shared View)</h1>
        </div>
      </div>
      <NetworkMap
        devices={devices}
        onMapUpdate={() => {}} // Read-only, no updates
        currentMapId={mapDetails.id}
        mapDetails={mapDetails}
        isReadOnly={true}
        isMapDetailsLoading={false}
      />
      <Sonner /> {/* Add Sonner for toasts in the shared view */}
    </div>
  );
};

// This function will be called by the PHP page to render the React component
(window as any).renderSharedMapView = (props: SharedMapViewProps) => {
  const container = document.getElementById('network-map-container');
  if (container) {
    ReactDOM.createRoot(container).render(
      <React.StrictMode>
        <SharedMapView {...props} />
      </React.StrictMode>
    );
  }
};

export default SharedMapView;