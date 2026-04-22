# SMS Gateway SaaS System

A comprehensive SMS Gateway SaaS (Software as a Service) system built with PHP, MySQL, Bootstrap 5, and Dasher admin template. This system provides multi-user SMS management with device integration, MSG91 API support, custom API endpoints, bulk messaging, campaign management, and enterprise-level features.

## 🚀 Features

### Core Features
- **Admin System**: Complete admin dashboard with system overview and user management
- **Device Management**: Android device integration with automatic load balancing
- **Multiple Routes**: Device → MSG91 → Custom API with automatic failover
- **Bulk Messaging**: CSV upload with contact preview and campaign management
- **Message Queuing**: Asynchronous processing with retry logic
- **Scheduling**: Send messages at specific times
- **Real-time Monitoring**: Device status, message delivery tracking

### Security & Compliance
- **API Key Authentication**: Secure API access with rate limiting
- **Input Validation**: Comprehensive validation and sanitization
- **Spam Protection**: Built-in spam filters and blacklist management
- **Rate Limiting**: Configurable API and user rate limits
- **Session Management**: Secure user sessions with timeout

### Admin Panel Features
- **Dashboard Analytics**: Real-time statistics and charts
- **User Management**: CRUD operations with device assignment
- **Device Monitoring**: Status tracking and configuration
- **API Management**: MSG91 and custom API configuration
- **Message History**: Complete audit trail with filtering
- **System Settings**: Configurable system parameters
- **Activity Logs**: Comprehensive logging system

### API Features
- **RESTful API**: Clean REST API for external integrations
- **Webhook Support**: Delivery status notifications
- **Auto-generated Keys**: Unique API keys for each user
- **Rate Limiting**: Per-user and global rate limits
- **Documentation**: Complete API documentation

## 🛠️ Installation

### Prerequisites
- PHP 7.4+ with MySQL extension
- MySQL 5.7+ or MariaDB 10.0+
- Apache/Nginx web server
- Composer (optional, for dependency management)
- Cron jobs for message processing

### Step 1: Database Setup
1. Create a new MySQL database
2. Run the `install.php` script to create all tables and initial data:
   ```bash
   # Access via web browser
   http://your-domain.com/install.php
   ```

### Step 2: Configuration
1. Edit `config.php` with your database credentials
2. Configure system settings in the admin panel
3. Set up cron job for message processing:
   ```bash
   # Run every minute
   * * * * * php /path/to/dashboard/worker.php
   ```

### Step 3: Admin Setup
1. Access the admin panel: `http://your-domain.com/dashboard/admin.php`
2. Default admin credentials:
   - Username: `admin`
   - Password: `admin123`

### Step 4: API Configuration
1. Configure MSG91 API credentials in admin settings
2. Set up custom API endpoints if needed
3. Generate API keys for users

## 📁 Project Structure

```
sms-gateway/
├── config.php                 # Database configuration
├── install.php               # Database setup script
├── functions.php             # Core system functions
├── dashboard/                # User dashboard
│   ├── index.php            # User dashboard
│   ├── send.php             # Send SMS interface
│   ├── messages.php         # Message history
│   ├── upload.php           # Bulk upload & campaigns
│   ├── settings.php         # User settings
│   ├── login.php            # Login page
│   ├── logout.php           # Logout script
│   ├── auth_check.php       # Authentication middleware
│   ├── admin.php            # Admin dashboard
│   ├── admin_users.php      # User management
│   ├── admin_devices.php    # Device management
│   ├── admin_apis.php       # API management
│   ├── admin_messages.php   # Message management
│   ├── admin_settings.php   # System settings
│   ├── admin_logs.php       # Activity logs
│   └── worker.php           # Message queue processor
├── api/                     # API endpoints
│   ├── send_sms.php         # Send SMS API
│   └── device_ping.php      # Device ping endpoint
├── dasher-1.0.0/           # Admin template
└── assets/                 # Static assets
```

## 🗄️ Database Schema

### Core Tables
- `users` - User accounts and authentication
- `devices` - Android devices for SMS sending
- `user_devices` - User-device assignments
- `message_queue` - SMS message queue
- `campaigns` - Bulk messaging campaigns
- `campaign_contacts` - Campaign contact lists
- `apis` - Custom API configurations
- `settings` - System configuration
- `logs` - Activity and error logs
- `blacklist` - Blocked phone numbers

## 🔧 Configuration

### System Settings
Configure these in the admin panel:

- **SMS Delay**: Delay between messages (seconds)
- **SIM Slot**: Which SIM card to use (0 or 1)
- **Retry Limit**: Maximum retry attempts
- **Rate Limit**: Messages per minute per user
- **Daily Limit**: Maximum messages per user per day
- **Night Sending**: Allow/disallow night time sending
- **Spam Protection**: Enable/disable spam filters

### MSG91 Configuration
- API Key
- Sender ID
- Route type
- DLT settings

## 📡 API Usage

### Send SMS
```bash
curl -X POST http://your-domain.com/api/send_sms.php \
  -H "X-API-Key: your-api-key" \
  -d "phone=+1234567890&message=Hello World"
```

### Response
```json
{
  "success": true,
  "message_id": 123,
  "status": "queued",
  "phone": "+1234567890",
  "route": "auto",
  "remaining_daily": 999
}
```

### Device Ping
```bash
curl -X POST http://your-domain.com/api/device_ping.php \
  -H "X-API-Key: device-api-key" \
  -d "device_id=DEVICE123"
```

## 📊 Dashboard Features

### User Dashboard
- Send single SMS messages
- View message history with status
- Bulk upload contacts via CSV
- Create and manage campaigns
- Real-time statistics
- API key management

### Admin Dashboard
- System overview with charts
- User management (create/edit/delete)
- Device monitoring and assignment
- Message queue monitoring
- API configuration
- System settings management
- Activity log viewing

## 🔄 Message Flow

1. **User sends SMS** via dashboard or API
2. **Message queued** in database with pending status
3. **Worker processes** queue every minute
4. **Route selection**:
   - Auto: Device → MSG91 → Custom API
   - Device: Android device only
   - MSG91: MSG91 API only
   - API: Custom API only
5. **Delivery attempt** with retry logic
6. **Status update** and logging

## 📱 Device Integration

### Android App Requirements
- Internet permission
- SMS permission
- Phone state permission
- Background service capability

### Device API
Devices should ping the server every 30 seconds:
```json
{
  "device_id": "DEVICE123",
  "api_key": "device-key",
  "battery": 85,
  "network": "4G"
}
```

## 🔒 Security Features

- **Password Hashing**: bcrypt for secure password storage
- **Prepared Statements**: SQL injection prevention
- **Input Sanitization**: XSS and injection protection
- **Rate Limiting**: API and user rate limiting
- **Session Security**: Secure session management
- **API Key Authentication**: Bearer token authentication
- **Blacklist Management**: Phone number blocking

## 📈 Monitoring & Logging

### Activity Logs
- User actions (login, send SMS, etc.)
- API requests and responses
- Device status changes
- System errors and warnings

### Message Tracking
- Delivery status (queued, sent, failed)
- Route used for each message
- Retry attempts and reasons
- Delivery timestamps

## 🚀 Deployment

### Production Setup
1. Use HTTPS (SSL certificate)
2. Set up proper file permissions
3. Configure PHP error logging
4. Set up database backups
5. Configure firewall rules
6. Set up monitoring (optional)

### Performance Optimization
- Database indexing on frequently queried columns
- Message queue processing optimization
- Caching for frequently accessed data
- CDN for static assets

## 🐛 Troubleshooting

### Common Issues
1. **Messages not sending**: Check device status and API keys
2. **API errors**: Verify API key and rate limits
3. **Database errors**: Check database connection and permissions
4. **Worker not running**: Verify cron job configuration

### Debug Mode
Enable debug logging in `config.php`:
```php
define('DEBUG', true);
```

## 📝 API Documentation

### Authentication
All API requests require an `X-API-Key` header with a valid API key.

### Endpoints
- `POST /api/send_sms.php` - Send SMS message
- `POST /api/device_ping.php` - Device status update

### Error Codes
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (invalid API key)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Check the troubleshooting section
- Review the API documentation
- Create an issue on GitHub

## 🔄 Updates

### Version 1.0.0
- Initial release with core SMS gateway functionality
- Multi-user system with admin panel
- Device integration and load balancing
- MSG91 API support
- Bulk messaging and campaigns
- RESTful API for external integrations
- Comprehensive logging and monitoring

---

**Note**: This system is designed for high-volume SMS sending with enterprise-level features. Ensure compliance with local telecommunications regulations before deployment.