<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // General Settings
        Setting::setValue('site_title', 'RoutePilot Pro', 'string', 'general', 'Site title');
        Setting::setValue('site_tagline', 'Professional Pool Service Management', 'string', 'general', 'Site tagline');
        Setting::setValue('company_name', 'RoutePilot Pro', 'string', 'general', 'Company name');
        Setting::setValue('company_email', 'admin@routepilot.pro', 'string', 'general', 'Company email');
        Setting::setValue('company_phone', '', 'string', 'general', 'Company phone');
        Setting::setValue('company_address', '', 'string', 'general', 'Company address');
        Setting::setValue('background_image', '', 'string', 'general', 'Site background image');
        Setting::setValue('background_enabled', '0', 'boolean', 'general', 'Enable background image');
        Setting::setValue('background_fixed', '0', 'boolean', 'general', 'Background image fixed (parallax effect)');

        // Database Settings
        Setting::setValue('backup_enabled', '0', 'boolean', 'database', 'Enable automatic backups');
        Setting::setValue('backup_frequency', 'daily', 'string', 'database', 'Backup frequency');
        Setting::setValue('backup_retention_days', '30', 'integer', 'database', 'Backup retention days');
        Setting::setValue('backup_notification_email', '', 'string', 'database', 'Backup notification email');

        // Mail Settings
        Setting::setValue('mail_driver', 'smtp', 'string', 'mail', 'Mail driver');
        Setting::setValue('mail_host', 'smtp.mailtrap.io', 'string', 'mail', 'Mail host');
        Setting::setValue('mail_port', '2525', 'integer', 'mail', 'Mail port');
        Setting::setValue('mail_username', '', 'string', 'mail', 'Mail username');
        Setting::setValue('mail_password', '', 'string', 'mail', 'Mail password');
        Setting::setValue('mail_encryption', 'tls', 'string', 'mail', 'Mail encryption');
        Setting::setValue('mail_from_address', 'noreply@routepilot.pro', 'string', 'mail', 'Mail from address');
        Setting::setValue('mail_from_name', 'RoutePilot Pro', 'string', 'mail', 'Mail from name');

        // Security Settings
        Setting::setValue('login_throttle_attempts', '5', 'integer', 'security', 'Login throttle attempts');
        Setting::setValue('login_throttle_minutes', '15', 'integer', 'security', 'Login throttle minutes');
        Setting::setValue('max_file_upload_size', '10', 'integer', 'security', 'Max file upload size (MB)');
        Setting::setValue('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt', 'string', 'security', 'Allowed file types');
        Setting::setValue('session_lifetime', '120', 'integer', 'security', 'Session lifetime (minutes)');
        Setting::setValue('password_min_length', '8', 'integer', 'security', 'Password minimum length');
        Setting::setValue('require_password_complexity', '0', 'boolean', 'security', 'Require password complexity');
    }
}
