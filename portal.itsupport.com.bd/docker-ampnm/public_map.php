<?php
// This file serves as the entry point for the React-based public map.
// It loads the React application which then fetches map data via API.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Network Map</title>
    <script type="module" crossorigin src="/assets/index-C_y_11_L.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-B_y_11_L.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Basic reset and body styling for the React app */
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
                'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue',
                sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        #root {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <div id="root"></div>
    <script>
        // This script will redirect to the React Router path for the public map
        // It ensures the React app handles the routing correctly.
        const mapId = new URLSearchParams(window.location.search).get('map_id');
        if (mapId) {
            window.history.replaceState(null, '', `/public-map/${mapId}`);
        } else {
            window.history.replaceState(null, '', '/'); // Redirect to home if no mapId
        }
    </script>
</body>
</html>