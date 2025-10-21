#!/bin/bash

# SSL Certificate Manager for Laranode
# This script handles SSL certificate generation and management using Let's Encrypt

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
WEBROOT_PATH="/var/www/html"
CERTBOT_PATH="/usr/bin/certbot"
APACHE_SITES_PATH="/etc/apache2/sites-available"
APACHE_ENABLED_PATH="/etc/apache2/sites-enabled"
SSL_CERTS_PATH="/etc/letsencrypt/live"

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if certbot is installed
check_certbot() {
    if ! command -v certbot &> /dev/null; then
        print_error "Certbot is not installed. Please install it first:"
        echo "sudo apt update && sudo apt install certbot python3-certbot-apache"
        exit 1
    fi
}

# Function to check if domain is accessible
check_domain_accessibility() {
    local domain=$1
    print_status "Checking if domain $domain is accessible..."
    
    if ! curl -s --connect-timeout 10 "http://$domain" > /dev/null; then
        print_error "Domain $domain is not accessible. Please ensure:"
        echo "1. Domain DNS points to this server"
        echo "2. Apache virtual host is configured and enabled"
        echo "3. Domain is accessible via HTTP"
        exit 1
    fi
    
    print_status "Domain $domain is accessible"
}

# Function to generate SSL certificate
generate_ssl_certificate() {
    local domain=$1
    local email=$2
    local document_root=$3
    local webroot_path
    
    # Prefer provided document root; fallback to default WEBROOT_PATH
    if [ -n "$document_root" ]; then
        webroot_path="$document_root"
    else
        webroot_path="$WEBROOT_PATH"
    fi
    
    print_status "Generating SSL certificate for $domain..."
    
    # Check if certificate already exists
    if [ -d "$SSL_CERTS_PATH/$domain" ]; then
        print_warning "SSL certificate for $domain already exists"
        return 0
    fi
    
    # Generate certificate using certbot
    if certbot certonly \
        --webroot \
        --webroot-path="$webroot_path" \
        --email "$email" \
        --agree-tos \
        --no-eff-email \
        --domains "$domain" \
        --non-interactive; then
        print_status "SSL certificate generated successfully for $domain"
        return 0
    else
        print_error "Failed to generate SSL certificate for $domain"
        return 1
    fi
}

# Function to create SSL-enabled Apache virtual host
create_ssl_vhost() {
    local domain=$1
    local document_root=$2

    print_status "Creating SSL-enabled virtual host for $domain..."

    local non_ssl_vhost="$APACHE_SITES_PATH/$domain.conf"
    local vhost_file="$APACHE_SITES_PATH/$domain-ssl.conf"

    if [[ ! -f "$non_ssl_vhost" ]]; then
        print_error "Non-SSL vhost file not found: $non_ssl_vhost"
        return 1
    fi

    # Extract everything between <VirtualHost> and </VirtualHost>
    local inner_content
    inner_content=$(awk '
        /<VirtualHost/{flag=1; next}
        /<\/VirtualHost>/{flag=0}
        flag
    ' "$non_ssl_vhost")

    {
        echo "<VirtualHost *:443>"
        echo "    ServerName $domain"
        echo "    SSLEngine on"
        echo "    SSLCertificateFile $SSL_CERTS_PATH/$domain/fullchain.pem"
        echo "    SSLCertificateKeyFile $SSL_CERTS_PATH/$domain/privkey.pem"
        echo
        echo "$inner_content" | sed 's/^/    /'
        echo "</VirtualHost>"
        echo
        echo "# Redirect HTTP to HTTPS"
        echo "<VirtualHost *:80>"
        echo "    ServerName $domain"
        echo "    Redirect permanent / https://$domain/"
        echo "</VirtualHost>"
    } > "$vhost_file"

    # Enable the SSL site
    a2ensite "$domain-ssl.conf"

    # Test Apache configuration
    if apache2ctl configtest; then
        systemctl reload apache2
        print_status "SSL virtual host created and enabled for $domain"
        return 0
    else
        print_error "Apache configuration test failed"
        return 1
    fi
}


# Function to remove SSL certificate
remove_ssl_certificate() {
    local domain=$1
    
    print_status "Removing SSL certificate for $domain..."
    
    # Disable SSL site
    if [ -f "$APACHE_SITES_PATH/$domain-ssl.conf" ]; then
        a2dissite "$domain-ssl.conf"
        rm -f "$APACHE_SITES_PATH/$domain-ssl.conf"
    fi
    
    # Remove certificate files
    if [ -d "$SSL_CERTS_PATH/$domain" ]; then
        certbot delete --cert-name "$domain" --non-interactive
        print_status "SSL certificate removed for $domain"
    else
        print_warning "No SSL certificate found for $domain"
    fi
    
    # Reload Apache
    systemctl reload apache2
    print_status "SSL configuration removed for $domain"
}

# Function to check SSL certificate status
check_ssl_status() {
    local domain=$1
    
    if [ -d "$SSL_CERTS_PATH/$domain" ]; then
        # Check if certificate is valid and not expired
        local cert_file="$SSL_CERTS_PATH/$domain/fullchain.pem"
        if [ -f "$cert_file" ]; then
            local expiry_date=$(openssl x509 -in "$cert_file" -noout -enddate | cut -d= -f2)
            local expiry_timestamp=$(date -d "$expiry_date" +%s)
            local current_timestamp=$(date +%s)
            
            if [ $expiry_timestamp -gt $current_timestamp ]; then
                echo "active"
                return 0
            else
                echo "expired"
                return 1
            fi
        fi
    fi
    
    echo "inactive"
    return 1
}

# Function to renew SSL certificates
renew_ssl_certificates() {
    print_status "Renewing SSL certificates..."
    
    if certbot renew --quiet; then
        systemctl reload apache2
        print_status "SSL certificates renewed successfully"
        return 0
    else
        print_error "Failed to renew SSL certificates"
        return 1
    fi
}

# Main script logic
case "$1" in
    "generate")
        if [ $# -lt 3 ]; then
            echo "Usage: $0 generate <domain> <email> [document_root]"
            exit 1
        fi
        
        domain=$2
        email=$3
        document_root=$4
        
        check_certbot
        check_domain_accessibility "$domain"
        generate_ssl_certificate "$domain" "$email" "$document_root"
        create_ssl_vhost "$domain" "$document_root"
        ;;
        
    "remove")
        if [ $# -ne 2 ]; then
            echo "Usage: $0 remove <domain>"
            exit 1
        fi
        
        domain=$2
        remove_ssl_certificate "$domain"
        ;;
        
    "status")
        if [ $# -ne 2 ]; then
            echo "Usage: $0 status <domain>"
            exit 1
        fi
        
        domain=$2
        status=$(check_ssl_status "$domain")
        echo "$status"
        ;;
        
    "renew")
        renew_ssl_certificates
        ;;
        
    *)
        echo "Usage: $0 {generate|remove|status|renew}"
        echo ""
        echo "Commands:"
        echo "  generate <domain> <email> [document_root]  - Generate SSL certificate for domain"
        echo "  remove <domain>                           - Remove SSL certificate for domain"
        echo "  status <domain>                           - Check SSL certificate status"
        echo "  renew                                     - Renew all SSL certificates"
        exit 1
        ;;
esac
