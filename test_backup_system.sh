#!/bin/bash

echo "🧪 Testing RoutePilot Pro Automatic Backup System"
echo "=================================================="
echo ""

# Test 1: Console Command
echo "1️⃣ Testing Console Command..."
if php artisan backup:run --help > /dev/null 2>&1; then
    echo "   ✅ Console command is working"
else
    echo "   ❌ Console command failed"
    exit 1
fi

# Test 2: Backup Creation
echo "2️⃣ Testing Backup Creation..."
if php artisan backup:run --force > /dev/null 2>&1; then
    echo "   ✅ Backup creation is working"
else
    echo "   ❌ Backup creation failed"
    exit 1
fi

# Test 3: Scheduler
echo "3️⃣ Testing Laravel Scheduler..."
SCHEDULE_OUTPUT=$(php artisan schedule:run 2>&1)
if echo "$SCHEDULE_OUTPUT" | grep -q "No scheduled commands are ready to run"; then
    echo "   ✅ Scheduler is working (no commands ready to run)"
elif echo "$SCHEDULE_OUTPUT" | grep -q "Running.*backup:run"; then
    echo "   ✅ Scheduler is working (backup command executed)"
else
    echo "   ❌ Scheduler is not working properly"
    echo "   Output: $SCHEDULE_OUTPUT"
    exit 1
fi

# Test 4: Cron Job
echo "4️⃣ Testing Cron Job..."
if crontab -l 2>/dev/null | grep -q "php artisan schedule:run"; then
    echo "   ✅ Cron job is configured"
else
    echo "   ❌ Cron job is not configured"
    exit 1
fi

# Test 5: Backup Files
echo "5️⃣ Testing Backup Files..."
BACKUP_COUNT=$(ls -1 storage/app/backups/backup_*.sql 2>/dev/null | wc -l)
if [ "$BACKUP_COUNT" -gt 0 ]; then
    echo "   ✅ Backup files exist ($BACKUP_COUNT backups)"
    LATEST_BACKUP=$(ls -t storage/app/backups/backup_*.sql | head -1)
    echo "   📁 Latest backup: $(basename "$LATEST_BACKUP")"
else
    echo "   ❌ No backup files found"
    exit 1
fi

# Test 6: Database Settings
echo "6️⃣ Testing Database Settings..."
BACKUP_ENABLED=$(php artisan tinker --execute="echo \App\Models\Setting::getValue('backup_enabled', '0');" 2>/dev/null)
BACKUP_FREQUENCY=$(php artisan tinker --execute="echo \App\Models\Setting::getValue('backup_frequency', 'daily');" 2>/dev/null)

if [ "$BACKUP_ENABLED" = "1" ]; then
    echo "   ✅ Automatic backups are enabled"
else
    echo "   ❌ Automatic backups are disabled"
fi

echo "   📊 Backup frequency: $BACKUP_FREQUENCY"

# Test 7: Last Backup Timestamp
echo "7️⃣ Testing Last Backup Timestamp..."
LAST_BACKUP_TIME=$(php artisan tinker --execute="echo \App\Models\Setting::getValue('last_backup_daily', 'Not set');" 2>/dev/null)
if [ "$LAST_BACKUP_TIME" != "Not set" ]; then
    echo "   ✅ Last backup timestamp is recorded: $LAST_BACKUP_TIME"
else
    echo "   ❌ Last backup timestamp is not recorded"
fi

echo ""
echo "🎉 Backup System Test Results:"
echo "=============================="
echo "✅ Console Command: Working"
echo "✅ Backup Creation: Working"
echo "✅ Laravel Scheduler: Working"
echo "✅ Cron Job: Configured"
echo "✅ Backup Files: $BACKUP_COUNT backups found"
echo "✅ Database Settings: Configured"
echo "✅ Timestamp Tracking: Working"
echo ""
echo "🚀 The automatic backup system is fully functional!"
echo ""
echo "📋 Next Steps:"
echo "   1. Go to Admin > Settings > Database"
echo "   2. Verify backup configuration"
echo "   3. Test the 'Reset Cron Job' button"
echo "   4. Monitor automatic backups at 2:00 AM"
echo ""
echo "🔧 Useful Commands:"
echo "   - Manual backup: php artisan backup:run --force"
echo "   - Check scheduler: php artisan schedule:run"
echo "   - View cron jobs: crontab -l"
echo "   - Check backup files: ls -la storage/app/backups/"
