window.MapApp = window.MapApp || {};

MapApp.utils = {
    buildNodeTitle: (deviceData) => {
        let title = `${deviceData.name}<br>${deviceData.ip || 'No IP'}<br>Status: ${deviceData.status}`;
        if (deviceData.status === 'offline' && deviceData.last_ping_output) {
            const lines = deviceData.last_ping_output.split('\n');
            let reason = 'No response';
            for (const line of lines) {
                if (line.toLowerCase().includes('unreachable') || line.toLowerCase().includes('timed out') || line.toLowerCase().includes('could not find host')) {
                    reason = line.trim();
                    break;
                }
            }
            const sanitizedReason = reason.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            title += `<br><small style="color: #fca5a5; font-family: monospace;">${sanitizedReason}</small>`;
        }
        return title;
    },

    // JavaScript equivalent of generateFaSvgDataUrl for client-side rendering
    generateFaSvgDataUrlJs: (iconCode, size, color) => {
        const fontFamily = 'Font Awesome 6 Free';
        const fontWeight = '900'; // Solid icons
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
                <text x="50%" y="50%" style="font-family: '${fontFamily}'; font-weight: ${fontWeight}; font-size: ${size}px; fill: ${color}; text-anchor: middle; dominant-baseline: central;">${iconCode}</text>
            </svg>
        `;
        return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
    }
};