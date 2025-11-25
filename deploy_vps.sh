#!/bin/bash
# EyeLearn Deployment Script for Ubuntu VPS

echo "🚀 Starting EyeLearn Deployment..."

# Update system
sudo apt update && sudo apt upgrade -y

# Install LAMP Stack
sudo apt install apache2 mysql-server php php-mysql php-cli php-curl php-gd php-mbstring php-xml unzip -y

# Enable Apache modules
sudo a2enmod rewrite ssl

# Configure Apache
sudo tee /etc/apache2/sites-available/eyellearn.conf > /dev/null <<EOF
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/html/capstone
    
    <Directory /var/www/html/capstone>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/eyellearn_error.log
    CustomLog \${APACHE_LOG_DIR}/eyellearn_access.log combined
</VirtualHost>
EOF

# Enable site
sudo a2ensite eyellearn.conf
sudo a2dissite 000-default.conf

# Create database
sudo mysql <<EOF
CREATE DATABASE elearn_db;
CREATE USER 'eyellearn_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON elearn_db.* TO 'eyellearn_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Set permissions
sudo chown -R www-data:www-data /var/www/html/capstone
sudo chmod -R 755 /var/www/html/capstone

# Restart Apache
sudo systemctl restart apache2

echo "✅ EyeLearn deployed successfully!"
echo "🔧 Don't forget to:"
echo "   1. Upload your files to /var/www/html/capstone"
echo "   2. Import your database"
echo "   3. Update config.php with new credentials"
echo "   4. Configure SSL certificate"
