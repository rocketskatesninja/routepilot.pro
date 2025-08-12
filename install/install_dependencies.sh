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

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

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

# Update .env file with database credentials
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env

# Run migrations
echo "🔄 Running database migrations..."
php artisan migrate --force

# Create storage links
echo "🔗 Creating storage links..."
php artisan storage:link

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
# Use sudo for chown to avoid permission errors
sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || echo "⚠️  Note: Some permission changes may require manual setup"

# Apache Configuration
echo "🌐 Setting up Apache configuration..."

# Copy Apache config
sudo cp install/routepilot.pro.conf /etc/apache2/sites-available/

# Enable the site
sudo a2ensite routepilot.pro.conf

# Enable required Apache modules
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod expires
sudo a2enmod deflate

# PHP Configuration Optimization
echo "⚙️  Optimizing PHP configuration for file uploads..."
sudo mkdir -p /etc/php/8.2/fpm/conf.d /etc/php/8.2/apache2/conf.d

# Create custom PHP configuration for file uploads
echo -e "upload_max_filesize = 25M\npost_max_size = 30M\nmemory_limit = 256M\nmax_execution_time = 300\nmax_input_time = 300" | sudo tee /etc/php/8.2/fpm/conf.d/99-routepilot.ini
echo -e "upload_max_filesize = 25M\npost_max_size = 30M\nmemory_limit = 256M\nmax_execution_time = 300\nmax_input_time = 300" | sudo tee /etc/php/8.2/apache2/conf.d/99-routepilot.ini

# Restart Apache and PHP-FPM
echo "🔄 Restarting Apache and PHP-FPM..."
sudo systemctl restart php8.2-fpm apache2

echo "✅ Installation completed successfully!"
echo "🌐 Your RoutePilot Pro application is ready!"
echo "📝 Apache configuration installed and enabled"
echo "🔗 Site should be accessible at: http://routepilot.pro"
echo "⚙️  PHP configuration optimized for file uploads (25MB limit)"
echo ""
echo "📋 Next steps:"
echo "   - Configure your DNS/hosts file to point routepilot.pro to this server"
echo "   - Set up SSL certificate for HTTPS"
echo "   - Configure mail settings in .env for email functionality" 