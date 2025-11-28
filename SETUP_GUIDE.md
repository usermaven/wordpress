# 🚀 Usermaven WordPress Plugin - Setup Guide

## ✅ Current Status
- ✅ Plugin symlinked to Local site: `/home/sheharyar/Local Sites/test/`
- ✅ Development folder: `/home/sheharyar/Desktop/D4/usermaven-Wordpress/wordpress`
- ✅ Any changes you make will be reflected immediately

---

## 📋 Steps to Run the Plugin

### 1. **Start Local by Flywheel**
```bash
# Open Local application
# Or if you have it in your applications
local
```

### 2. **Start the "test" Site**
- Open Local by Flywheel GUI
- Find the "test" site in the left sidebar
- Click the "Start" button (green play icon)
- Wait for the site to start (MySQL + Nginx)

### 3. **Access WordPress Admin**
Once the site is running, you can access:
- **WordPress Site**: http://test.local (or the URL shown in Local)
- **WordPress Admin**: http://test.local/wp-admin

### 4. **Activate the Usermaven Plugin**
```bash
# Option 1: Via WordPress Admin
1. Go to: http://test.local/wp-admin/plugins.php
2. Find "Usermaven" in the plugin list
3. Click "Activate"

# Option 2: Via Command Line (if you have WP-CLI)
cd "/home/sheharyar/Local Sites/test/app/public"
wp plugin activate usermaven
```

### 5. **Install WooCommerce**
```bash
# Via WordPress Admin:
1. Go to: Plugins → Add New
2. Search for "WooCommerce"
3. Click "Install Now" → "Activate"
4. Follow the setup wizard

# Via WP-CLI:
cd "/home/sheharyar/Local Sites/test/app/public"
wp plugin install woocommerce --activate
```

### 6. **Configure Usermaven**
1. Go to: **WordPress Admin → Usermaven → Settings**
2. Enter your Usermaven API Key (get it from https://usermaven.com)
3. Enable "Identify logged-in users in Usermaven"
4. Enable "Auto-capture events" (optional)
5. Click "Save Settings"

---

## 🧪 Testing Your Changes

### Create Test Users with Custom Roles

**Option 1: Add to functions.php (temporary)**
```php
// Add to: /home/sheharyar/Local Sites/test/app/public/wp-content/themes/YOUR_THEME/functions.php

add_action('init', function() {
    // Create custom roles
    add_role('wholesale_customer', 'Wholesale Customer', array(
        'read' => true,
        'edit_posts' => false,
    ));
    
    add_role('vip_member', 'VIP Member', array(
        'read' => true,
        'edit_posts' => false,
    ));
});
```

**Option 2: Use a Plugin**
- Install "User Role Editor" plugin
- Create custom roles via the GUI

**Option 3: Create users manually**
1. Go to: Users → Add New
2. Create user with custom role
3. Test purchases with this user

### Test Scenarios

#### Test 1: Product View Tracking
1. Log in as a custom role user (wholesale_customer)
2. Visit a product page
3. Check Usermaven dashboard for "viewed_product" event
4. Verify user_id and roles are captured

#### Test 2: Add to Cart
1. While logged in, add product to cart
2. Check Usermaven for "added_to_cart" event
3. Verify user context includes all roles

#### Test 3: Complete Purchase
1. Proceed to checkout
2. Complete purchase
3. Check Usermaven for these events:
   - `initiated_checkout`
   - `order_submitted`
   - `order_completed`
4. **Verify user context includes:**
   - `customer_id`
   - `customer_email`
   - `customer_name`
   - `customer_roles` (should show ALL roles)
   - `customer_primary_role`

#### Test 4: Check Dashboard
1. Go to Usermaven dashboard
2. Navigate to Revenue/Sales reports
3. **Verify:**
   - ✅ Sales are showing (not just refunds)
   - ✅ User profiles show correct custom roles
   - ✅ Revenue is attributed to correct users

---

## 🐛 Debugging

### Enable WordPress Debug Logging
Already enabled in your setup! Check logs at:
```bash
tail -f "/home/sheharyar/Local Sites/test/app/public/wp-content/debug.log"
```

### Check if Plugin is Active
```bash
cd "/home/sheharyar/Local Sites/test/app/public"
wp plugin list | grep usermaven
```

### Check Usermaven Events in Browser
1. Open browser DevTools (F12)
2. Go to Network tab
3. Filter by "usermaven" or "t.usermaven.com"
4. Perform actions (view product, add to cart, checkout)
5. Check if events are being sent

### Verify User Roles
```bash
cd "/home/sheharyar/Local Sites/test/app/public"
wp user list --fields=ID,user_login,roles
```

---

## 📂 File Structure

```
/home/sheharyar/Desktop/D4/usermaven-Wordpress/
└── wordpress/                          # Your plugin (symlinked to Local)
    ├── usermaven.php                   # Main plugin file
    ├── includes/
    │   ├── class-usermaven.php         # Core plugin class
    │   ├── class-usermaven-woocommerce.php  # WooCommerce integration (YOUR CHANGES)
    │   ├── class-usermaven-api.php     # API communication
    │   └── ...
    ├── admin/                          # Admin interface
    └── public/                         # Public-facing code

Symlinked to:
/home/sheharyar/Local Sites/test/app/public/wp-content/plugins/usermaven → (your dev folder)
```

---

## 🔧 Quick Commands

### Start Local Site
```bash
# Open Local GUI and click "Start" on the "test" site
```

### View WordPress Logs
```bash
tail -f "/home/sheharyar/Local Sites/test/app/public/wp-content/debug.log"
```

### Check Plugin Status
```bash
cd "/home/sheharyar/Local Sites/test/app/public"
wp plugin list
```

### Activate Plugin
```bash
cd "/home/sheharyar/Local Sites/test/app/public"
wp plugin activate usermaven
```

### Deactivate Plugin
```bash
cd "/home/sheharyar/Local Sites/test/app/public"
wp plugin deactivate usermaven
```

### Clear WordPress Cache
```bash
cd "/home/sheharyar/Local Sites/test/app/public"
wp cache flush
```

---

## ✅ Verification Checklist

Before testing, ensure:
- [ ] Local by Flywheel is running
- [ ] "test" site is started (green indicator in Local)
- [ ] WordPress is accessible at http://test.local
- [ ] Usermaven plugin is activated
- [ ] WooCommerce is installed and activated
- [ ] Usermaven API key is configured
- [ ] "Identify logged-in users" is enabled
- [ ] Test products exist in WooCommerce
- [ ] Custom role users are created

---

## 🎯 What Your Changes Fixed

Your code changes ensure:
- ✅ **All user roles are captured** (not just primary role)
- ✅ **Order events include complete user context**
- ✅ **Custom roles (wholesale_customer, vip_member, etc.) are tracked**
- ✅ **Sales appear in Usermaven dashboard with correct user attribution**
- ✅ **Guest users are handled gracefully**
- ✅ **Backward compatible with existing setups**

---

## 📞 Need Help?

If you encounter issues:
1. Check WordPress debug.log
2. Check browser console for JS errors
3. Verify Usermaven API key is correct
4. Ensure "Identify logged-in users" is enabled
5. Test with a simple user first, then custom roles

---

## 🚀 You're Ready!

Your plugin is now set up and ready to test. Just:
1. **Start Local** → Open Local app and start the "test" site
2. **Access WordPress** → http://test.local/wp-admin
3. **Activate Plugin** → Plugins → Activate Usermaven
4. **Configure** → Usermaven → Settings → Add API key
5. **Test** → Create orders with custom role users

**Good luck! Your changes look great! 🎉**
