# SMS Gateway SaaS System

A comprehensive SMS Gateway SaaS (Software as a Service) platform built with PHP and MySQL. This system provides multi-user SMS management with Android device integration, MSG91 API support, custom gateway routing, bulk messaging campaigns, and enterprise-level features for sending SMS at scale.

## 🚀 Features

### Core Messaging Features
- **Single SMS**: Send individual SMS messages via dashboard
- **Bulk Messaging**: Upload contacts via CSV and create campaigns
- **Scheduled Sending**: Schedule messages for future delivery
- **Multiple Routing**: Auto failover between Device → MSG91 → Custom Gateway
- **Message Queue**: Asynchronous processing with automatic retry logic
- **Campaign Management**: Create, track, and manage bulk SMS campaigns
- **Message History**: Complete audit trail with filtering and status tracking

### Device Management
- **Android Device Integration**: Support for multiple Android devices
- **Device Status Monitoring**: Real-time online/offline status tracking
- **Load Balancing**: Automatic distribution of messages across devices
- **Device Assignments**: Admin control over which users can access which devices
- **Daily Limits**: Configurable message limits per device per day
- **SIM Slot Control**: Support for dual-SIM Android devices with preference control
- **Priority Routing**: Device priority levels for message distribution

### Admin Features
- **Dashboard Analytics**: Real-time statistics and overview
- **User Management**: Create, edit, and manage user accounts
- **Device Management**: Configure and monitor all devices
- **API Management**: Create and manage API keys for integrations
- **Custom Gateways**: Configure custom SMS gateway endpoints
- **System Settings**: Control application-wide configuration
- **Activity Logs**: Comprehensive audit trail of all system actions
- **Message Monitoring**: Real-time visibility into message queue and status

### API & Integration
- **RESTful API**: Clean REST API for external integrations
- **Multiple Phone Formats**: Support for various phone number formats
- **Flexible Routing**: Choose routing method (auto, device, msg91, custom)
- **Bulk API Requests**: Send multiple messages in one API call
- **Rate Limiting**: Per-API-key and per-user rate limiting
- **Error Handling**: Detailed error messages and response codes
- **Scheduling Support**: Schedule messages via API with future timestamps

### Security & Compliance
- **User Authentication**: Secure login with session management
- **API Key Authentication**: Bearer token authentication for API requests
- **Password Hashing**: bcrypt for secure password storage
- **SQL Injection Prevention**: Prepared statements for all database queries
- **Input Validation**: Comprehensive validation and sanitization
- **Rate Limiting**: Configurable per-minute and daily limits
- **Blacklist Management**: Block specific phone numbers from receiving messages
- **Activity Logging**: Track all user and system actions

## 🛠️ Installation

### Prerequisites
- PHP 7.4+ with MySQLi extension
- MySQL 5.7+ or MariaDB 10.0+
- Apache/Nginx web server with .htaccess support (recommended)
- Command-line access for cron job setup

### Step 1: Configure Database Connection
Edit `config.php` with your database credentials:
```php
$host = "localhost";
$db = "sms_gateway";
$user = "root";
$pass = "your_password";
```

### Step 2: Run Installation Script
Access the installation script via browser:
```
http://your-domain.com/install.php
```
This will:
- Create all necessary database tables
- Set up default system settings
- Create the default admin user

### Step 3: Configure Admin Panel
1. Navigate to: `http://your-domain.com/dashboard/login.php`
2. Login with default credentials:
   - Username: `admin`
   - Password: `admin123`
3. **Important**: Change the admin password immediately

### Step 4: Setup Cron Job for Message Processing
The worker script processes queued messages. Set up a cron job to run every minute:

```bash
* * * * * cd /path/to/sms && php dashboard/worker.php
```

For shared hosting, use:
```bash
* * * * * /usr/bin/php /home/username/public_html/sms/dashboard/worker.php
```

### Step 5: Configure MSG91 (Optional)
To enable MSG91 as a delivery route:
1. Log in to admin panel
2. Navigate to Settings
3. Enter your MSG91 credentials:
   - Auth Key
   - Sender ID
   - Route
   - Template ID (for DLT compliance)

## 📁 Project Structure

```
sms/
├── config.php                      # Database configuration
├── index.php                       # Redirect to dashboard
├── install.php                     # Installation & setup script
├── README.md                       # This file
│
├── api/
│   ├── common.php                 # Common API utilities & authentication
│   ├── send_sms.php               # Send SMS API endpoint
│   └── device_ping.php            # Device heartbeat endpoint
│
├── dashboard/
│   ├── functions.php              # Core business logic & database functions
│   ├── auth_check.php             # Authentication middleware
│   │
│   ├── index.php                  # User dashboard (main)
│   ├── send.php                   # Send single SMS page
│   ├── messages.php               # Message history page
│   ├── upload.php                 # Bulk upload & campaign page
│   ├── settings.php               # User account settings
│   ├── apis.php                   # User API key management
│   │
│   ├── login.php                  # User login page
│   ├── logout.php                 # Logout handler
│   │
│   ├── admin.php                  # Admin dashboard
│   ├── admin_users.php            # User management
│   ├── admin_devices.php          # Device management
│   ├── admin_apis.php             # API key management
│   ├── admin_gateways.php         # Custom gateway configuration
│   ├── admin_messages.php         # Message queue monitoring
│   ├── admin_settings.php         # System settings
│   ├── logs.php                   # Activity logs
│   │
│   ├── worker.php                 # Message queue processor (cron job)
│   ├── test.php                   # Testing page
│   │
│   ├── partials/
│   │   ├── header.php            # Page header & navigation
│   │   └── footer.php            # Page footer
│   │
│   └── assets/
│       ├── css/
│       │   ├── theme.css         # Main stylesheet
│       │   └── scss/             # SCSS source files
│       ├── js/
│       │   ├── main.js           # Main JavaScript
│       │   └── vendors/          # Third-party JS libraries
│       └── images/               # UI images and icons
│
└── storage/
    ├── uploads/                  # Campaign CSV files
    └── tmp/                      # Temporary files
```

## 🗄️ Database Schema

### Users & Authentication
- `users` - User accounts with roles (admin/user), status, and settings
- `activity_logs` - Audit trail of all system actions

### SMS Delivery
- `message_queue` - All SMS messages with status and metadata
- `message_attempts` - Detailed record of each delivery attempt
- `campaigns` - Bulk SMS campaigns
- `campaign_contacts` - Contacts for each campaign

### Device & Gateway Management
- `devices` - Android devices with status and limits
- `user_devices` - Mapping of users to accessible devices
- `apis` - API keys for external integrations
- `custom_gateways` - Third-party gateway configurations

### System
- `settings` - Application configuration key-value store
- `blacklist` - Blocked phone numbers

## 🔧 Configuration

### System Settings (Admin Panel)
Configure these in Admin → Settings:

**Messaging:**
- `sms_delay_seconds` - Delay between messages sent from device (default: 5)
- `retry_limit` - Maximum retry attempts for failed messages (default: 3)
- `retry_delay_seconds` - Wait time before retrying (default: 60)
- `allow_unicode` - Allow Unicode SMS content (default: true)
- `default_signature` - Signature appended to all messages

**Rate Limiting:**
- `rate_limit_per_minute` - Messages per minute for dashboard users (default: 30)
- `api_rate_limit_per_minute` - API requests per minute (default: 60)
- `daily_limit_per_user` - Max messages per user per day (default: 1000)

**Device Management:**
- `sim_slot_preference` - Global SIM slot (auto/sim1/sim2)
- `device_offline_after_minutes` - Timeout for offline status (default: 2)
- `device_disconnect_after_minutes` - Timeout for disconnected status (default: 10)

**Quiet Hours:**
- `quiet_hours_enabled` - Prevent sending during specific times
- `quiet_hours_start` - Start time (e.g., 22:00)
- `quiet_hours_end` - End time (e.g., 06:00)

**MSG91 Configuration:**
- `msg91_enabled` - Enable MSG91 route
- `msg91_auth_key` - Your MSG91 authentication key
- `msg91_sender_id` - Your sender ID
- `msg91_api_mode` - Legacy or Flow mode
- `msg91_dlt_template_id` - DLT template for compliance

### File Permissions
Ensure proper permissions for storage directories:
```bash
chmod 755 storage/
chmod 755 storage/uploads/
chmod 755 storage/tmp/
```

## 📡 API Usage

### Authentication
All API requests require the `X-API-Key` header:
```
X-API-Key: your-api-key-here
```

### Send SMS Endpoint
**URL:** `POST /api/send_sms.php`

**Request Parameters:**
```json
{
  "phone": "+1234567890",           // Single phone number (string)
  "phones": ["+1234567890"],        // Multiple phones (array)
  "message": "Hello World",         // SMS text (required)
  "route": "auto",                  // auto|device|msg91|custom_api (optional)
  "schedule": "2026-04-25 10:30:00" // Future delivery time (optional)
}
```

**Success Response (200):**
```json
{
  "success": true,
  "queued_ids": [1234, 1235],
  "queued_count": 2,
  "errors": []
}
```

**Error Response (400/401/429):**
```json
{
  "success": false,
  "error": "Error message describing the issue"
}
```

### Device Ping Endpoint
**URL:** `POST /api/device_ping.php`

Used by Android devices to update status:
```json
{
  "device_id": "DEVICE123",
  "api_key": "device-api-key",
  "battery": 85,
  "network": "4G"
}
```

## 📊 Dashboard Features

### User Dashboard
- **Overview**: Quick stats on messages sent/failed and device status
- **Send SMS**: Single message interface with recipient and routing options
- **Message History**: Filter and view all sent messages with status
- **Bulk Upload**: Upload CSV files and create campaigns
- **Campaigns**: View campaign status, contacts, and results
- **API Keys**: Generate and manage API keys for integrations
- **Settings**: Update account information

### Admin Dashboard
- **System Overview**: Total users, devices, messages, and API keys
- **Today's Stats**: Messages sent/failed for the current day
- **Quick Actions**: Common admin tasks
- **User Management**: Create/edit/delete users with role assignment
- **Device Management**: Configure devices and device assignments
- **API Management**: Create/revoke API keys and set expiration
- **Custom Gateways**: Configure third-party SMS gateway endpoints
- **Message Queue**: Monitor queued, processing, and failed messages
- **System Settings**: Configure all application parameters
- **Activity Logs**: View audit trail with filters by user/action/date

## 🔄 Message Flow

1. **User initiates**: SMS sent via dashboard, bulk upload, or API
2. **Validation**: Phone number, message, and rate limit validation
3. **Queuing**: Message stored in `message_queue` table with `pending` status
4. **Worker processing**: Cron job reads queued messages
5. **Route selection**: Based on preference and availability:
   - Device: Android device in user's access list
   - MSG91: MSG91 API (if enabled)
   - Custom Gateway: Configured third-party endpoint
   - Auto: Try each in order until success
6. **Delivery attempt**: Message sent via chosen route
7. **Retry logic**: Failed messages retry based on `retry_limit`
8. **Final status**: `sent`, `failed`, or moved to retry queue
9. **Logging**: All actions logged to `activity_logs` table

## 📱 Android Device Integration

### Device Registration
1. Admin provides device with API key
2. Device submits unique device ID and API key
3. Admin approves device in Device Management
4. Device appears in user's device list

### Device Heartbeat
Devices should ping the server every 30 seconds with current status:
```
POST /api/device_ping.php
X-API-Key: [device-api-key]

{
  "device_id": "DEVICE123",
  "battery": 85,
  "network": "4G",
  "sim_available": ["sim1", "sim2"]
}
```

### Device Status Indicators
- **Online**: Pinged within last 2 minutes (configurable)
- **Offline**: Not pinged for 2-10 minutes
- **Disconnected**: No ping for 10+ minutes

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] Change default admin password
- [ ] Configure database backups
- [ ] Set up HTTPS/SSL certificate
- [ ] Configure file permissions (755 for dirs, 644 for files)
- [ ] Set up cron job for worker.php
- [ ] Test message delivery with test.php

### Production Configuration
- [ ] Set error reporting appropriately
- [ ] Configure logs to rotate
- [ ] Set up firewall rules
- [ ] Restrict admin panel access by IP (optional)
- [ ] Configure MSG91 credentials if using
- [ ] Test API endpoints thoroughly
- [ ] Set up monitoring/alerts

### Performance Optimization
- [ ] Configure MySQL connection pooling
- [ ] Add database indexes (already included in schema)
- [ ] Monitor message queue size
- [ ] Adjust cron batch size if needed
- [ ] Use CDN for static assets (if high traffic)

## 🐛 Troubleshooting

### Messages Not Sending
1. Check device status: Admin → Devices
2. Verify device is online and has capacity
3. Check message queue: Admin → Messages
4. Verify cron job is running: `ps aux | grep worker.php`
5. Check error logs in activity log

### API Errors
- `400`: Invalid parameters - check request format
- `401`: Invalid API key - regenerate key
- `422`: Invalid data - check phone number and message
- `429`: Rate limit exceeded - wait or increase limit

### Cron Job Not Running
1. Verify cron syntax: `crontab -l`
2. Check PHP path: `which php`
3. Test manually: `php /path/to/worker.php`
4. Check server logs: `/var/log/cron` or hosting control panel
5. Verify file permissions

### Database Connection Issues
- Check credentials in `config.php`
- Verify MySQL service is running
- Check firewall rules for port 3306
- Verify user has database access: `SHOW GRANTS FOR 'user'@'host';`

## 📝 Support & Maintenance

### Regular Maintenance
- Monitor message queue size (should stay under 10,000)
- Clean up old activity logs monthly
- Review device status and remove disconnected devices
- Update SMS gateways and routing as needed
- Monitor rate limits and adjust as needed

### Common Tasks
- **Reset device counter**: Automatic daily at IST midnight
- **Archive old messages**: Manual via admin panel (future feature)
- **Export message history**: Via admin panel (future feature)
- **Rotate API keys**: Generate new key and revoke old one

## 📄 License

This project is proprietary software. All rights reserved.

## 🆘 Support

For issues and feature requests:
1. Check the troubleshooting section above
2. Review system logs: Admin → Logs
3. Test with the test page: `dashboard/test.php`
4. Contact system administrator

---

**Last Updated:** April 2026  
**Version:** 1.0.0