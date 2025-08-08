# 🎨 FOSSBilling Modern Theme - Installation Guide

This guide will walk you through the complete installation and setup process for the FOSSBilling Modern theme with comprehensive emoji integration, PWA features, and user-controlled theme switching.

## ✨ What's New in This Installation
- 🎉 **100+ Contextual Emojis** - Modern visual interface throughout
- 🌙 **User Theme Toggle** - Dark/light mode switcher with emoji indicators
- 📱 **Emoji-Based PWA Icons** - Beautiful app icons with 💳, ⚙️, and 👤 emojis
- 🔐 **Enhanced Security** - XSS protection and secure emoji rendering
- 🏠 **Improved UI** - Dashboard, news, orders, and auth pages with emoji enhancements
- 🔗 **Fixed Navigation** - Logo links stay on same tab (no more unwanted new tabs)

## 📋 Prerequisites

### System Requirements
- **FOSSBilling**: Version 0.6.0 or higher
- **PHP**: 8.1 or higher
- **Node.js**: 16.x or higher (for building assets)
- **NPM**: 7.x or higher
- **Web Server**: Apache, Nginx, or similar with mod_rewrite/URL rewriting

### Required PHP Extensions
- GD or Imagick (for image processing)
- Intl (for internationalization)
- JSON (for configuration)
- Mbstring (for text processing)

## 🚀 Installation Methods

### Method 1: Direct Installation (Recommended)

1. **Download the Theme**
   ```bash
   # Navigate to your FOSSBilling themes directory
   cd /path/to/fossbilling/src/themes/
   
   # Clone or copy the fossbilling-modern theme
   # (Replace with actual download method)
   ```

2. **Install Dependencies**
   ```bash
   cd fossbilling-modern
   npm install
   ```

3. **Build Assets**
   ```bash
   # For production
   npm run build
   
   # For development (with watch mode)
   npm run dev
   
   # Generate PWA icons (emoji-based with 💳, ⚙️, 👤 designs)
   node scripts/generate-pwa-icons-simple.js
   
   # Preview generated emoji icons
   # Open assets/icons/*.html files in browser to see icon designs
   ```

### Method 2: Development Installation

1. **Clone for Development**
   ```bash
   cd /path/to/fossbilling/src/themes/
   git clone [repository-url] fossbilling-modern
   cd fossbilling-modern
   ```

2. **Install Development Dependencies**
   ```bash
   npm install --include=dev
   ```

3. **Start Development Server**
   ```bash
   # Start with file watching
   npm run watch
   
   # Or use development server
   npm run dev-server
   ```

## ⚙️ Configuration

### 1. Theme Activation

1. **Access Admin Panel**
   - Log into your FOSSBilling admin panel
   - Navigate to **System** → **Settings** → **General**

2. **Select Theme**
   - Find the **Theme** dropdown
   - Select **FOSSBilling Modern**
   - Click **Save**

3. **Verify Installation**
   - Visit your FOSSBilling frontend
   - Confirm the modern theme is active
   - Check browser developer tools for any console errors

### 2. Theme Settings Configuration

1. **Access Theme Settings**
   - Go to **System** → **Themes** 
   - Click on **FOSSBilling Modern**
   - Click **Settings** button

2. **Configure Options**
   ```json
   {
     "primary_color": "#005fc5",
     "enable_dark_mode": true,
     "enable_pwa": true,
     "enable_user_theme_toggle": true,
     "cache_version": "1.0.0",
     "security_headers": true,
     "custom_css": "",
     "analytics_code": ""
   }
   ```

3. **PWA Configuration**
   - **App Name**: Your billing system name
   - **App Description**: Brief description
   - **Theme Color**: Primary brand color (#005fc5)
   - **Background Color**: App background color
   - **Icons**: Automatic emoji-based icons (💳 app, ⚙️ admin, 👤 client)
   - **User Theme Toggle**: Dark/light mode switcher with 🌙/☀️ indicators
   - **Emoji Integration**: 100+ contextual emojis throughout interface
   - **Security Headers**: XSS protection with secure emoji rendering
   - **Fixed Logo Links**: No more unwanted new tab openings

### 3. Web Server Configuration

#### Apache Configuration
Add to your `.htacache` or virtual host:

```apache
# Enable PWA manifest serving
<Files "manifest.webmanifest">
    Header set Content-Type "application/manifest+json"
</Files>

# Enable service worker caching
<Files "service-worker.js">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
</Files>

# Enable asset caching
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
    Header set Cache-Control "public, max-age=31536000"
</FilesMatch>
```

#### Nginx Configuration
Add to your server block:

```nginx
# PWA manifest
location ~ ^/themes/fossbilling-modern/manifest\.webmanifest$ {
    add_header Content-Type application/manifest+json;
}

# Service worker (no caching)
location ~ ^/themes/fossbilling-modern/build/service-worker\.js$ {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header Pragma "no-cache";
    add_header Expires "0";
}

# Static assets (aggressive caching)
location ~ ^/themes/fossbilling-modern/build/.*\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    add_header Cache-Control "public, max-age=31536000";
}
```

## 🔧 Build Process

### Production Build
```bash
# Clean previous builds
rm -rf build/

# Install dependencies (if not done)
npm install

# Build for production
npm run build

# Verify build output
ls -la build/
```

### Development Build
```bash
# Build with source maps and watch mode
npm run dev

# Or with file watching
npm run watch

# Access with browser sync (if configured)
npm run dev-server
```

### Build Scripts Explained
```json
{
  "scripts": {
    "dev": "encore dev",                    // Development build
    "dev-server": "encore dev-server",     // Dev server with hot reload
    "watch": "encore dev --watch",         // Watch mode for development
    "build": "encore production --progress", // Production build
    "analyze": "webpack-bundle-analyzer build/js --port 8888" // Bundle analysis
  }
}
```

## 🎯 Emoji Features Configuration

### Theme Toggle Setup
The theme includes a user-controlled toggle in the navigation bar:

```twig
{# In layout_default.html.twig #}
<button class="btn btn-outline-secondary btn-sm navbar-theme-toggle ms-2" 
        type="button"
        title="{{ 'Toggle dark/light theme'|trans }}"
        aria-label="{{ 'Toggle dark/light theme'|trans }}">
    <span class="theme-icon">🌙</span>
</button>
```

**Features:**
- 🌙 Moon icon for light mode (switches to dark)
- ☀️ Sun icon for dark mode (switches to light)
- Smooth rotation animation on hover
- localStorage persistence across sessions
- Automatic system preference detection

### Emoji Customization
The theme uses contextual emojis throughout:

```css
/* Adjust emoji sizes globally */
.emoji-icon {
  font-size: 1.1em;
  margin-right: 0.25rem;
}

/* Customize theme toggle animations */
.navbar-theme-toggle:hover .theme-icon {
  transform: rotate(20deg);
}

/* Disable emoji animations if needed */
@media (prefers-reduced-motion: reduce) {
  .theme-icon {
    transition: none;
  }
}
```

**Page-Specific Emojis:**
- 🏠 Dashboard: Profile (👤), Invoices (🧾), Orders (🛒), Support (🎫)
- 📰 News: Articles (📄), timestamps (🕐), reading (👁️)
- 🛒 Orders: Products (📦), status (🟢⏰❌), management (⚙️)
- 🔐 Auth: Login (📧🔑), signup (🚀), profile (👤)

## 🎨 Customization

### 1. Basic Customization

**Override CSS Variables:**
```css
/* In theme settings or custom CSS */
:root {
  --primary-color: #your-brand-color;
  --border-radius: 8px;
  --font-sans: 'Your Custom Font', system-ui;
}
```

**Custom Logo:**
1. Upload logo to `/themes/fossbilling-modern/assets/images/`
2. Update theme settings to reference new logo
3. Rebuild assets: `npm run build`

### 2. Advanced Customization

**Create Custom SCSS:**
```scss
// custom-theme.scss
@import 'fossbilling-modern';

.custom-component {
  background: var(--surface-color);
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-md);
}
```

**Add Custom JavaScript:**
```javascript
// custom-features.js
document.addEventListener('DOMContentLoaded', () => {
  // Your custom functionality
  console.log('Custom theme features loaded');
});
```

### 3. Creating Theme Variants

**Dark Theme Variant:**
```scss
[data-theme="dark"] {
  --body-bg: #0f172a;
  --surface-color: #1e293b;
  --text-primary: #f1f5f9;
  --text-secondary: #94a3b8;
  --border-color: #334155;
}
```

**Brand Color Variants:**
```scss
[data-theme="purple"] {
  --primary-color: #8b5cf6;
  --primary-rgb: 139, 92, 246;
}

[data-theme="green"] {
  --primary-color: #059669;
  --primary-rgb: 5, 150, 105;
}
```

## 📱 PWA Setup

### 1. HTTPS Requirement
PWA features require HTTPS. Set up SSL/TLS:

```bash
# Using Let's Encrypt (example)
certbot --nginx -d yourdomain.com
```

### 2. App Icons Generation
The theme includes an emoji-based icon generator:

```bash
# Generate PWA icons using emoji
node scripts/generate-pwa-icons-simple.js

# This creates:
# - Main app icons with 💳 emoji + 💰 accent
# - Admin shortcut with ⚙️ emoji + 🔧 accent
# - Client shortcut with 👤 emoji + 🏠 accent
# - Support icon with 🆘 emoji + 💬 accent
# - Billing icon with 🧾 emoji + ✅ accent
# - Dashboard icon with 🏠 emoji + 📊 accent
# - All in SVG format with HTML previews
```

**Custom Icons (Optional):**
```bash
# If you prefer PNG icons, install conversion tool
npm install -g pwa-asset-generator

# Convert your logo to PWA icons
pwa-asset-generator logo.png ./assets/icons \
  --manifest ./manifest.webmanifest
```

### 3. Service Worker Configuration
Edit `webpack.config.js` to customize caching:

```javascript
new GenerateSW({
  clientsClaim: true,
  skipWaiting: true,
  runtimeCaching: [
    // Add custom caching rules
    {
      urlPattern: /^https:\/\/your-api-domain\.com/,
      handler: 'NetworkFirst',
      options: {
        cacheName: 'api-cache',
        networkTimeoutSeconds: 3,
      },
    },
  ],
})
```

## 🔍 Troubleshooting

### Common Issues

**1. Assets Not Loading**
```bash
# Check if assets are built
ls -la build/

# Rebuild assets
npm run build

# Check emoji-based PWA icons are generated
ls -la assets/icons/

# Regenerate emoji icons if needed
node scripts/generate-pwa-icons-simple.js

# Preview generated emoji icons in browser
open assets/icons/icon-192x192.html
# Or manually open *.html files to see emoji designs

# Check file permissions
chmod -R 755 build/ assets/
```

**2. Service Worker Issues**
```javascript
// Check in browser console
navigator.serviceWorker.getRegistrations().then(registrations => {
  console.log('Active service workers:', registrations);
});

// Unregister problematic service worker
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(registration => registration.unregister());
});
```

**3. Build Errors**
```bash
# Clear npm cache
npm cache clean --force

# Remove node_modules and reinstall
rm -rf node_modules package-lock.json
npm install

# Check Node.js version
node --version  # Should be 16+
npm --version   # Should be 7+
```

**4. Theme Toggle (🌙/☀️) Not Working**
```bash
# Check if JavaScript is loading
# Open browser console and look for errors

# Verify theme toggle button exists in templates
grep -r "navbar-theme-toggle" html/

# Check if emoji icons are displaying
grep -r "theme-icon" html/

# Test localStorage functionality
# Open DevTools > Console and run:
# localStorage.setItem('fossbilling-theme', 'dark')
# localStorage.getItem('fossbilling-theme')

# Verify theme detection in JavaScript
# Check fossbilling-modern.js for detectTheme() function
```

**5. Permission Issues**
```bash
# Fix ownership
chown -R www-data:www-data /path/to/fossbilling/src/themes/fossbilling-modern

# Fix permissions
find /path/to/fossbilling/src/themes/fossbilling-modern -type d -exec chmod 755 {} \;
find /path/to/fossbilling/src/themes/fossbilling-modern -type f -exec chmod 644 {} \;

# Ensure scripts are executable
chmod +x scripts/*.js
```

### Debugging Tools

**1. Browser Developer Tools**
- Open F12 Developer Tools
- Check Console tab for JavaScript errors
- Check Network tab for failed asset loads
- Check Application tab for Service Worker status

**2. Build Analysis**
```bash
# Analyze bundle size
npm run analyze

# Check webpack stats
npm run build -- --profile --json > stats.json

# Test PWA features
npm run build && python3 -m http.server 8080
# Then visit localhost:8080 and test install prompt
```

**3. Performance Testing**
```bash
# Install Lighthouse CLI
npm install -g lighthouse

# Run Lighthouse audit
lighthouse https://yourdomain.com --view
```

## 🔄 Updates

### Updating the Theme

1. **Backup Current Theme**
   ```bash
   cp -r fossbilling-modern fossbilling-modern-backup
   ```

2. **Download New Version**
   ```bash
   # Replace with new theme files
   # Preserve your customizations in custom files
   ```

3. **Update Dependencies**
   ```bash
   npm install
   npm run build
   
   # Regenerate PWA assets if needed
   node scripts/generate-pwa-icons-simple.js
   ```

4. **Test Functionality**
   - Check frontend appearance with emoji integration
   - Test PWA features (install prompt, offline mode, emoji icons)
   - Test theme toggle (🌙/☀️ switching with rotation animation)
   - Verify security headers in browser DevTools
   - Check accessibility compliance (emoji ARIA labels)
   - Test emoji icons display correctly across different devices
   - Verify logo links stay on same tab (no target="_blank")
   - Check contextual emojis in dashboard, news, and order pages

### Version Management
```bash
# Check current version
cat package.json | grep version

# Update npm packages
npm update

# Check for outdated packages
npm outdated
```

## 📞 Support

### Getting Help

1. **Documentation Issues**
   - Check the README.md for detailed features
   - Review FOSSBilling documentation

2. **Technical Support**
   - FOSSBilling Community Forum
   - GitHub Issues (for bugs)
   - Discord Community Chat

3. **Professional Support**
   - Contact FOSSBilling team
   - Hire certified FOSSBilling developers

### Reporting Issues

When reporting issues, include:
- FOSSBilling version
- PHP version
- Node.js/npm version
- Browser and version (especially for PWA features)
- Console error messages
- Theme toggle functionality status (🌙/☀️ switching)
- Emoji icon rendering status (verify cross-platform display)
- PWA installation status with emoji-based icons
- Security header status (check DevTools Network tab)
- Logo link behavior (should stay on same tab)
- Steps to reproduce

---

**Installation Complete! 🎉**

Your FOSSBilling Modern theme is now ready with all the latest features:

✅ **100+ Contextual Emojis** - Visual enhancement throughout the interface  
✅ **User Theme Toggle** - 🌙/☀️ Dark/light mode switcher in navigation  
✅ **PWA Ready** - Installable app with emoji-based icons (💳⚙️👤)  
✅ **Enhanced Security** - XSS protection with secure emoji rendering  
✅ **Modern UI** - Dashboard, news, orders, and auth pages with emoji enhancements  
✅ **Fixed Navigation** - Logo links stay on same tab for better UX  
✅ **Accessibility** - ARIA-labeled emojis for screen reader support  

Visit your site to experience the modern, emoji-enhanced, PWA-enabled billing system! 🚀

**Quick Test Checklist:**
- [ ] Click the 🌙/☀️ theme toggle to test dark/light switching
- [ ] Try installing as PWA app (look for 📱 install prompt)
- [ ] Navigate through dashboard to see contextual emojis
- [ ] Check that logo links don't open new tabs
- [ ] Test on mobile devices for responsive emoji display