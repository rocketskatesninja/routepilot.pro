#!/bin/bash

# RoutePilot Pro Cron Job Setup Script
# This script sets up the cron job for automatic database backups

set -e

echo "⏰ Setting up cron job for RoutePilot Pro automatic backups..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Please run this script from the Laravel project root directory"
    exit 1
fi

# Get the current directory
PROJECT_DIR=$(pwd)
echo "📁 Project directory: $PROJECT_DIR"

# Define the cron job
CRON_JOB="* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1"

echo "🔧 Cron job to be added:"
echo "   $CRON_JOB"
echo ""

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "php artisan schedule:run"; then
    echo "✅ Cron job already exists:"
    crontab -l | grep "php artisan schedule:run"
    echo ""
    echo "To remove existing cron job, run:"
    echo "   crontab -e"
    echo "   (then delete the line with 'php artisan schedule:run')"
else
    # Add cron job
    echo "📝 Adding cron job..."
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Cron job added successfully!"
fi

echo ""
echo "🔍 Current cron jobs:"
crontab -l 2>/dev/null || echo "No cron jobs found"

echo ""
echo "🧪 Testing backup command..."
if php artisan backup:run --help > /dev/null 2>&1; then
    echo "✅ Backup command is working"
else
    echo "❌ Backup command test failed"
fi

echo ""
echo "📋 Next steps:"
echo "   1. Go to Admin > Settings > Database"
echo "   2. Enable 'Enable Automatic Backups'"
echo "   3. Choose your backup frequency (daily/weekly/monthly)"
echo "   4. Set retention period and notification email"
echo "   5. Save the configuration"
echo ""
echo "🔧 Useful commands:"
echo "   - Check cron jobs: crontab -l"
echo "   - Edit cron jobs: crontab -e"
echo "   - Test backup manually: php artisan backup:run --force"
echo "   - View cron logs: sudo tail -f /var/log/syslog | grep CRON"
echo "   - Check Laravel logs: tail -f storage/logs/laravel.log"
echo ""
echo "⚠️  Important notes:"
echo "   - The cron job runs every minute to check if scheduled tasks should run"
echo "   - Backups will only run at the scheduled time (2 AM by default)"
echo "   - You can use the 'Reset Cron Job' button in the admin panel to force immediate backup"
echo "   - Make sure your server timezone is correct for accurate scheduling"
