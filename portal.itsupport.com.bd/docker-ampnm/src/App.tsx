import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import Index from "./pages/Index";
import NotFound from "./pages/NotFound";
import AddDevicePage from "./pages/AddDevicePage";
import EditDevicePage from "./pages/EditDevicePage";
import DevicesPage from "./pages/DevicesPage"; // New import
import MapPage from "./pages/MapPage"; // New import
import StatusLogsPage from "./pages/StatusLogsPage"; // New import
import EmailNotificationsPage from "./pages/EmailNotificationsPage"; // New import
import UsersPage from "./pages/UsersPage"; // New import

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Index />} />
          <Route path="/add-device" element={<AddDevicePage />} />
          <Route path="/edit-device/:id" element={<EditDevicePage />} />
          <Route path="/devices" element={<DevicesPage />} /> {/* New route */}
          <Route path="/map" element={<MapPage />} /> {/* New route */}
          <Route path="/status-logs" element={<StatusLogsPage />} /> {/* New route */}
          <Route path="/email-notifications" element={<EmailNotificationsPage />} /> {/* New route */}
          <Route path="/users" element={<UsersPage />} /> {/* New route */}
          {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;