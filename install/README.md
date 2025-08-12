# RoutePilot Pro Installation Guide

This guide will help you install RoutePilot Pro on your server.

## Prerequisites

Before running the installation script, ensure you have:

- **Ubuntu/Debian** or **CentOS/RHEL** server
- **PHP 8.2+** with required extensions
- **MySQL/MariaDB** server
- **Apache** web server
- **Composer** (PHP package manager)
- **Node.js** and **npm**
- **Git** (for cloning the repository)

## Quick Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-repo/routepilot-pro.git
   cd routepilot-pro
   ```

2. **Run the installation script:**
   ```bash
   chmod +x install/install_dependencies.sh
   ./install/install_dependencies.sh
   ```

3. **Follow the prompts** to configure your database and domain.

## Manual Installation Steps

If you prefer to install manually or the script fails:

### 1. Install Dependencies

```bash
# PHP dependencies
composer install --optimize-autoloader

# Node.js dependencies
npm install
npm run build
```

### 2. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Update database settings in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=routepilot_pro
DB_USERNAME=routepilot_user
DB_PASSWORD=your_password

# Update APP_URL for HTTPS
APP_URL=https://yourdomain.com
```

### 3. Database Setup

```bash
# Create database and user
sudo mysql -e "CREATE DATABASE routepilot_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'routepilot_user'@'localhost' IDENTIFIED BY 'your_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON routepilot_pro.* TO 'routepilot_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Run migrations
php artisan migrate --force

# Seed database
php artisan db:seed --class=DatabaseSeeder
```

### 4. File Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data storage bootstrap/cache vendor public

# Set proper permissions
sudo chmod -R 775 storage
sudo chmod -R 755 bootstrap/cache

# Create storage link
php artisan storage:link

# Create photo directories
mkdir -p storage/app/public/reports/photos
mkdir -p storage/app/public/locations/photos
mkdir -p storage/app/public/profile-photos
```

### 5. Apache Configuration

Copy the Apache configuration:

```bash
sudo cp install/routepilot.pro.conf /etc/apache2/sites-available/
sudo a2ensite routepilot.pro.conf
sudo a2enmod rewrite headers expires deflate ssl
sudo systemctl restart apache2
```

### 6. SSL Certificate (Recommended)

Install Let's Encrypt SSL certificate:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com
```

## Default Credentials

After installation, you can log in with:

- **Email:** admin@routepilot.pro
- **Password:** password

**Important:** Change the default password after first login!

## Troubleshooting

### Photos Not Loading

If profile pictures or report photos don't display:

```bash
# Check storage permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/

# Verify storage link exists
ls -la public/storage

# Check if photo directories exist
ls -la storage/app/public/reports/photos/
ls -la storage/app/public/locations/photos/
ls -la storage/app/public/profile-photos/
```

### Database Connection Issues

If you get database connection errors:

```bash
# Test MySQL connection
mysql -u routepilot_user -p routepilot_pro

# Check .env file
cat .env | grep DB_

# Clear config cache
php artisan config:clear
```

### Apache Errors

Check Apache error logs:

```bash
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/log/apache2/routepilot_error.log
```

### Permission Issues

If you encounter permission errors:

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/routepilot.pro

# Fix permissions
sudo chmod -R 755 /var/www/routepilot.pro
sudo chmod -R 775 /var/www/routepilot.pro/storage
```

## Post-Installation

1. **Update admin password** after first login
2. **Configure email settings** in `.env` for notifications
3. **Set up backup** for your database and files
4. **Configure monitoring** for your server
5. **Set up SSL certificate** if not done during installation

## Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review Apache and Laravel logs
3. Verify all prerequisites are met
4. Ensure proper file permissions

## Security Notes

- Always use HTTPS in production
- Keep your Laravel application updated
- Regularly backup your database
- Monitor server logs for security issues
- Use strong passwords for all accounts


