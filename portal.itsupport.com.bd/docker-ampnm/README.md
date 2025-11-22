# Local Network Monitor - PHP/MySQL Version

## Requirements
- XAMPP installed on your computer
- PHP 7.4 or higher
- MySQL/MariaDB

## Installation Steps

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Place Files in htdocs**
   - Create a new folder in `C:\xampp\htdocs\network-monitor\`
   - Copy all files to this folder:
     - `index.php` (main dashboard)
     - `devices.php` (device management)
     - `history.php` (ping history with filtering)
     - `api.php` (AJAX API endpoints)
     - `export.php` (CSV export functionality)
     - `config.php` (configuration file)
     - `database_setup.php` (database setup script)
     - `README.md` (this file)

3. **Setup Database**
   - Open your browser and go to: `http://localhost/network-monitor/database_setup.php`
   - This will automatically create the database and tables

4. **Access Application**
   - Open your browser and go to: `http://localhost/network-monitor/`
   - The network monitor application will load

## Features
- Ping any host or IP address
- View ping history stored in MySQL database
- Monitor local network devices
- Real-time network status monitoring
- Device management (add, remove, check status)
- Historical data with filtering and pagination
- Export data to CSV
- Responsive design with Tailwind CSS
- AJAX-powered interface for smooth interactions

## Usage
1. **Dashboard**: Main overview of network status and recent activity
2. **Device Management**: Add/remove devices and check their status
3. **Ping History**: View historical ping results with filtering and export options
4. **Real-time Updates**: AJAX-powered updates without page refresh

## Security Notes
- This is designed for local network use only
- The database uses default XAMPP credentials (root with no password)
- For production use, change database credentials and add authentication

## Troubleshooting
1. If ping doesn't work, ensure PHP can execute shell commands
2. Check that Apache and MySQL are running in XAMPP
3. Verify database connection in config.php if needed
4. Make sure your firewall allows ping requests