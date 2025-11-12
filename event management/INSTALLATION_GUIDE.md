# Installation Guide - Event Management System Enhancements

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (for dependency management)

## Step 1: Install Dependencies

### Using Composer (Recommended)

1. Navigate to your project directory:
```bash
cd "C:\xampp\htdocs\event management"
```

2. Initialize Composer if not already done:
```bash
composer init
```

3. Install required packages:
```bash
composer require phpmailer/phpmailer
composer require tecnickcom/tcpdf
```

### Manual Installation (Alternative)

If Composer is not available, download and extract these libraries manually:

1. **PHPMailer**: Download from https://github.com/PHPMailer/PHPMailer
   - Extract to `includes/PHPMailer/`

2. **TCPDF**: Download from https://github.com/tecnickcom/TCPDF
   - Extract to `tcpdf/`

## Step 2: Database Setup

The system will automatically create necessary tables and columns. Ensure your database connection is properly configured in all PHP files:

```php
$conn = new mysqli('localhost', 'root', '', 'event_management');
```

## Step 3: Email Configuration

1. Open `includes/email_config.php`
2. Update the email settings:

```php
$this->mail->Username = 'your-email@gmail.com';
$this->mail->Password = 'your-app-password'; // Use App Password for Gmail
```

### Gmail Setup (if using Gmail)

1. Enable 2-Factor Authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate password for "Mail"
   - Use this password in the configuration

## Step 4: File Permissions

Ensure the following directories have write permissions:
- `tcpdf/` (for PDF generation)
- `includes/` (for email configuration)

## Step 5: Testing

1. **Search Functionality**: Visit `search.php` and test the search features
2. **Email Notifications**: Create and delete/deactivate an event to test emails
3. **PDF Generation**: Download participant lists from event management
4. **Venue Reports**: Check `venue_reports.php` for venue statistics
5. **Feedback System**: Submit feedback through `feedback_system.php`
6. **Enhanced Tickets**: View tickets with the new design

## Troubleshooting

### Common Issues

1. **Email not sending**:
   - Check SMTP credentials
   - Verify firewall settings
   - Ensure App Password is used for Gmail

2. **PDF generation fails**:
   - Check file permissions
   - Verify TCPDF installation
   - Ensure PHP has necessary extensions

3. **Database errors**:
   - Verify database connection
   - Check table creation permissions
   - Ensure MySQL version compatibility

### Error Logs

Check PHP error logs for detailed error messages:
- XAMPP: `C:\xampp\php\logs\php_error_log`
- WAMP: `C:\wamp\logs\php_error.log`

## Security Considerations

1. **Email Configuration**: Never commit email credentials to version control
2. **Database**: Use strong passwords and limit database user permissions
3. **File Permissions**: Set appropriate file permissions (644 for files, 755 for directories)
4. **HTTPS**: Use HTTPS in production environments

## Performance Optimization

1. **Database Indexing**: Add indexes on frequently queried columns
2. **Caching**: Implement caching for frequently accessed data
3. **Image Optimization**: Optimize any uploaded images
4. **CDN**: Use CDN for static assets in production

## Backup Recommendations

Before implementing enhancements:
1. Backup your database
2. Backup existing files
3. Test in a development environment first

## Support

For technical support:
1. Check the error logs
2. Verify all dependencies are installed
3. Test individual components
4. Refer to the ENHANCEMENTS_README.md for detailed feature documentation

---

**Note**: This installation guide assumes you're using XAMPP on Windows. Adjust paths and commands for other environments accordingly.
