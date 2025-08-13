#!/bin/bash

# RoutePilot Pro Installation Script
# This script sets up the complete pool service management system

set -e

echo "🚀 Starting RoutePilot Pro Installation..."

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Please run this script from the Laravel project root directory"
    exit 1
fi

# Check if MySQL/MariaDB is installed
echo "🔍 Checking database server..."
if ! command -v mysql &> /dev/null; then
    echo "❌ Error: MySQL/MariaDB is not installed. Please install it first."
    echo "   On Ubuntu/Debian: sudo apt install mariadb-server"
    echo "   On CentOS/RHEL: sudo yum install mariadb-server"
    exit 1
fi

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --optimize-autoloader

# Install Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

# Build assets
echo "🔨 Building assets..."
npm run build

# Set up environment file
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# Database setup
echo "🗄️ Setting up database..."
read -p "Enter database name (default: routepilot_pro): " DB_NAME
DB_NAME=${DB_NAME:-routepilot_pro}

read -p "Enter database username (default: routepilot_user): " DB_USER
DB_USER=${DB_USER:-routepilot_user}

read -p "Enter database password: " DB_PASSWORD

# Create database and user if they don't exist
echo "🗄️ Creating database and user..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Update .env file with database credentials
echo "📝 Updating .env file..."
sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/DB_HOST=.*/DB_HOST=127.0.0.1/" .env
sed -i "s/DB_PORT=.*/DB_PORT=3306/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env

# Update APP_URL for HTTPS (will be set up later)
read -p "Enter your domain (e.g., routepilot.pro): " DOMAIN
DOMAIN=${DOMAIN:-routepilot.pro}
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env

# Clear configuration cache
echo "🧹 Clearing configuration cache..."
php artisan config:clear

# Run migrations
echo "🔄 Running database migrations..."
php artisan migrate --force

# Create storage links
echo "🔗 Creating storage links..."
php artisan storage:link

# Create required storage directories
echo "📁 Creating storage directories..."
mkdir -p storage/app/public/reports/photos
mkdir -p storage/app/public/locations/photos
mkdir -p storage/app/public/profile-photos

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache vendor public
sudo chmod -R 775 storage
sudo chmod 664 database/database.sqlite 2>/dev/null || true

# Seed the database with sample data
echo "🌱 Seeding database with sample data..."
php artisan db:seed --class=DatabaseSeeder

# Create sample photos for testing
echo "📸 Creating sample photos..."
if [ -f "storage/app/public/profile-photos/lPv3jMXYdmmeJG5BAvBVSOwYWHB9vpcumqTJ5awL.png" ]; then
    # Create sample report photos
    cp storage/app/public/profile-photos/lPv3jMXYdmmeJG5BAvBVSOwYWHB9vpcumqTJ5awL.png storage/app/public/reports/photos/kGc4DWpDviGcRRfSZoYs93mqeJwTeV0vuhhae6zm.png 2>/dev/null || true
    cp storage/app/public/profile-photos/lPv3jMXYdmmeJG5BAvBVSOwYWHB9vpcumqTJ5awL.png storage/app/public/reports/photos/IzQzhHZ2gtGJAMPFQu2sXQe6wxPqCb6mnVpCWRhB.png 2>/dev/null || true
    cp storage/app/public/profile-photos/lPv3jMXYdmmeJG5BAvBVSOwYWHB9vpcumqTJ5awL.png storage/app/public/locations/photos/mmG8cgoBS5bkXMsBsB87QJDTHpTjNd4xc3ssYxEv.png 2>/dev/null || true
    echo "✅ Sample photos created"
fi

# Apache Configuration
echo "🌐 Setting up Apache configuration..."

# Check if Apache config exists
if [ -f "install/routepilot.pro.conf" ]; then
    # Copy Apache config
    sudo cp install/routepilot.pro.conf /etc/apache2/sites-available/
    
    # Enable the site
    sudo a2ensite routepilot.pro.conf
    
    # Enable required Apache modules
    sudo a2enmod rewrite
    sudo a2enmod headers
    sudo a2enmod expires
    sudo a2enmod deflate
    sudo a2enmod ssl
    
    # Restart Apache
    echo "🔄 Restarting Apache..."
    sudo systemctl restart apache2
else
    echo "⚠️  Apache configuration file not found. Please create it manually."
fi

# Clear all caches
echo "🧹 Clearing all caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Set up cron job for automatic backups
echo "⏰ Setting up cron job for automatic backups..."
CRON_JOB="* * * * * cd /var/www/routepilot.pro && php artisan schedule:run >> /dev/null 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "php artisan schedule:run"; then
    echo "✅ Cron job already exists"
else
    # Add cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "✅ Cron job added successfully"
fi

# Test the backup command
echo "🧪 Testing backup command..."
if php artisan backup:run --help > /dev/null 2>&1; then
    echo "✅ Backup command is working"
else
    echo "❌ Backup command test failed"
fi

echo "✅ Installation completed successfully!"
echo "🌐 Your RoutePilot Pro application is ready!"
echo "📝 Apache configuration installed and enabled"
echo "🔗 Site should be accessible at: https://$DOMAIN"
echo "⏰ Automatic backup cron job is configured"
echo ""
echo "🔐 Default admin credentials:"
echo "   Email: admin@routepilot.pro"
echo "   Password: password"
echo ""
echo "📋 Next steps:"
echo "   - Configure your DNS/hosts file to point $DOMAIN to this server"
echo "   - Set up SSL certificate for HTTPS (Let's Encrypt recommended)"
echo "   - Configure mail settings in .env for email functionality"
echo "   - Update admin password after first login"
echo "   - Enable automatic backups in Admin > Settings > Database"
echo ""
echo "🔧 Troubleshooting:"
echo "   - If photos don't load, check storage permissions: sudo chown -R www-data:www-data storage/"
echo "   - If database connection fails, verify MySQL credentials in .env"
echo "   - If Apache errors occur, check logs: sudo tail -f /var/log/apache2/error.log"
echo "   - To check cron job: crontab -l"
echo "   - To test backup manually: php artisan backup:run"
echo "   - To view cron logs: sudo tail -f /var/log/syslog | grep CRON" 