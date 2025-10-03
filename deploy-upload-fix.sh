#!/bin/bash

echo "=== Domanesia Deployment Script for Upload Fix ==="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}1. Backing up current files...${NC}"
# Backup existing files if they exist
if [ -f "public_html/.htaccess" ]; then
    cp public_html/.htaccess public_html/.htaccess.backup.$(date +%Y%m%d_%H%M%S)
    echo -e "${GREEN}✅ .htaccess backed up${NC}"
fi

if [ -f "public_html/php.ini" ]; then
    cp public_html/php.ini public_html/php.ini.backup.$(date +%Y%m%d_%H%M%S)
    echo -e "${GREEN}✅ php.ini backed up${NC}"
fi

echo -e "${YELLOW}2. Uploading configuration files...${NC}"
# Copy the generated files to public_html
cp public/.htaccess public_html/.htaccess
cp public/php.ini public_html/php.ini

echo -e "${GREEN}✅ Configuration files uploaded${NC}"

echo -e "${YELLOW}3. Clearing Laravel cache...${NC}"
# Clear Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

echo -e "${GREEN}✅ Laravel cache cleared${NC}"

echo -e "${YELLOW}4. Testing configuration...${NC}"
# Test the configuration
php artisan upload:check-config

echo ""
echo -e "${GREEN}🎉 Deployment completed!${NC}"
echo ""
echo -e "${YELLOW}📋 What was done:${NC}"
echo "   - Updated .htaccess with upload limits (6MB)"
echo "   - Created php.ini with proper settings"
echo "   - Cleared Laravel configuration cache"
echo "   - Applied Domanesia-specific optimizations"
echo ""
echo -e "${YELLOW}🧪 Next steps:${NC}"
echo "   1. Test upload via your API endpoint"
echo "   2. Check server error logs if issues persist"
echo "   3. Contact Domanesia support if needed"
echo ""
echo -e "${RED}⚠️  Important:${NC}"
echo "   - Some changes may take 5-10 minutes to take effect"
echo "   - If upload still fails, check Domanesia control panel for additional limits"
