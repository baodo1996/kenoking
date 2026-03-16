# KingSlots Deployment Guide — luckyluked.com

## 🚀 Hostinger Deployment Steps

### Step 1: Access Hostinger Control Panel
1. Log in to your Hostinger account
2. Go to **Websites** section
3. Click **Manage** next to luckyluked.com

### Step 2: Upload All Files
1. Click **File Manager** in the left sidebar
2. Navigate to the **public_html** folder
3. Upload every file and folder from this repository:
   - `index.html` → root of public_html
   - `.htaccess` → root of public_html (rename from `.htaccess.txt` if needed)
   - `robots.txt` → root of public_html
   - `sitemap.xml` → root of public_html
   - `config.php` → root of public_html
   - `api/proxy.php` → `api/` subfolder
   - `gold_api/user_balance.php` → `gold_api/` subfolder
   - `gold_api/game_callback.php` → `gold_api/` subfolder
   - `gold_api/money_callback.php` → `gold_api/` subfolder
   - Create an empty writable `data/` folder (chmod 750)

### Step 3: Set Permissions
```
chmod 750 data/
chmod 640 config.php
```

### Step 4: Configure API Credentials (optional — env vars)
The credentials are already set in `config.php`.  
For extra security you can override them via PHP environment variables:
- `AAS_BASE_URL`   — API provider base URL (e.g. `https://api.pplaygame.net`)
- `AAS_AGENT_CODE` — Your agent code
- `AAS_AGENT_TOKEN`— Your agent token
- `AAS_AGENT_SECRET`— Your agent secret (for callback authentication)

Set these in Hostinger → **PHP Config** → **Environment Variables** (if available), or
in an `.env` file outside public_html.

### Step 5: Configure Callback URLs in API Dashboard
Register these callback URLs in the AAS provider's back-office:
- **Balance check:** `https://luckyluked.com/gold_api/user_balance.php`
- **Game callback:** `https://luckyluked.com/gold_api/game_callback.php`
- **Money callback:** `https://luckyluked.com/gold_api/money_callback.php`

### Step 6: Enable SSL Certificate
1. Go to **SSL** section in Hostinger panel
2. Enable SSL for luckyluked.com
3. Wait for SSL activation (usually instant)

### Step 7: Test Your Casino
1. Visit https://luckyluked.com
2. Verify the slots lobby loads and games appear
3. Sign in with a test username and launch a game
4. Check that the balance callbacks work correctly

## 🎯 Your Casino URL: https://luckyluked.com
