# Automatic Database Backups

RoutePilot Pro includes an automatic database backup system that can create backups on a scheduled basis (daily, weekly, or monthly) without manual intervention.

## How It Works

### 1. Console Command
- **Command**: `php artisan backup:run`
- **Purpose**: Creates a database backup using mysqldump
- **Options**: 
  - `--force`: Force backup regardless of schedule
  - `--help`: Show help information

### 2. Laravel Scheduler
- **Provider**: `ScheduleServiceProvider`
- **Location**: `app/Providers/ScheduleServiceProvider.php`
- **Schedule**: 
  - **Daily**: Runs at 2:00 AM every day
  - **Weekly**: Runs at 2:00 AM every Sunday
  - **Monthly**: Runs at 2:00 AM on the 1st of each month

### 3. Cron Job
- **Frequency**: Every minute
- **Command**: `php artisan schedule:run`
- **Purpose**: Checks if scheduled tasks should run

## Setup Instructions

### Automatic Setup (Recommended)
Run the installation script which includes cron job setup:
```bash
./install/install_dependencies.sh
```

### Manual Setup
1. **Set up cron job**:
   ```bash
   ./install/setup_cron.sh
   ```
   
   Or manually add to crontab:
   ```bash
   crontab -e
   # Add this line:
   * * * * * cd /path/to/routepilot.pro && php artisan schedule:run >> /dev/null 2>&1
   ```

2. **Verify cron job**:
   ```bash
   crontab -l
   ```

3. **Test the backup command**:
   ```bash
   php artisan backup:run --force
   ```

## Configuration

### Admin Panel Settings
1. Go to **Admin > Settings > Database**
2. Enable **"Enable Automatic Backups"**
3. Choose **Backup Frequency**:
   - Daily
   - Weekly  
   - Monthly
4. Set **Retention Period** (days)
5. Configure **Notification Email** (optional)
6. Click **"Save Backup Configuration"**

### Advanced Settings
- **Backup Time**: Defaults to 2:00 AM (configured in `ScheduleServiceProvider`)
- **Backup Location**: `storage/app/backups/`
- **File Format**: `backup_YYYY-MM-DD_HH-MM-SS.sql`
- **Retention**: Automatic cleanup of old backups based on retention setting

## Monitoring and Management

### Check Backup Status
- **Admin Panel**: Database tab shows backup statistics
- **File System**: Check `storage/app/backups/` directory
- **Logs**: Check `storage/logs/laravel.log` for backup events

### Manual Operations
- **Create Backup**: Use "Create Backup" button in admin panel
- **Reset Schedule**: Use "Reset Cron Job" button to force immediate backup
- **Download Backup**: Click download icon in backup list
- **Delete Backup**: Click delete icon in backup list

### Reset Cron Job
The "Reset Cron Job" button:
1. Clears last backup timestamps
2. Forces immediate backup on next schedule check
3. Useful for troubleshooting or changing backup frequency

## Troubleshooting

### Common Issues

#### 1. "Backup directory is not writable"
**Solution**: Fix directory permissions
```bash
sudo chown -R www-data:www-data storage/app/backups/
sudo chmod -R 775 storage/app/backups/
```

#### 2. Cron job not running
**Check**: Verify cron job exists
```bash
crontab -l
```

**Solution**: Re-add cron job
```bash
./install/setup_cron.sh
```

#### 3. Scheduler not working
**Check**: Test scheduler manually
```bash
php artisan schedule:run
```

**Solution**: Ensure `ScheduleServiceProvider` is registered in `bootstrap/app.php`

#### 4. Database connection fails
**Check**: Verify database credentials in `.env`
**Solution**: Use "Test Connection" button in admin panel

### Log Analysis

#### Cron Logs
```bash
sudo tail -f /var/log/syslog | grep CRON
```

#### Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

#### Backup Command Output
```bash
php artisan backup:run --force -v
```

## Security Considerations

### File Permissions
- Backup files are stored in `storage/app/backups/`
- Ensure proper file permissions (755 for directories, 644 for files)
- Consider moving backups to external storage for production

### Database Credentials
- Backup command uses database credentials from `.env`
- Ensure database user has sufficient privileges for mysqldump
- Consider using dedicated backup user with limited permissions

### Notification Emails
- Backup notifications are sent via configured mail settings
- Ensure mail configuration is secure and working

## Performance Impact

### Backup Process
- Uses `mysqldump` with `--single-transaction` for consistency
- Minimal impact on database performance
- Runs in background to avoid blocking

### Storage
- Monitor backup directory size
- Implement external storage for large databases
- Consider compression for older backups

## Best Practices

### 1. Testing
- Test backup command manually before enabling automatic backups
- Verify backup files can be restored
- Test notification emails

### 2. Monitoring
- Set up log monitoring for backup failures
- Monitor backup file sizes and creation times
- Check retention policy compliance

### 3. Maintenance
- Regularly verify cron job is running
- Clean up old backup files manually if needed
- Update backup strategy as database grows

### 4. Disaster Recovery
- Store backups in multiple locations
- Test restore procedures regularly
- Document recovery procedures

## Support

For issues with the automatic backup system:
1. Check this documentation
2. Review Laravel logs
3. Test commands manually
4. Verify system configuration
5. Contact system administrator if needed
