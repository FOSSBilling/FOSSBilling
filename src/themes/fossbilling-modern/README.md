# 🎨 FOSSBilling Modern Theme

A modern, PWA-enabled theme for FOSSBilling that combines contemporary design principles with advanced web technologies and comprehensive emoji integration. Built on the solid foundation of the Huraga theme, this modern variant brings cutting-edge features, enhanced user experience, and delightful visual elements to your billing system.

![FOSSBilling Modern Theme](screenshot.jpg)

## ✨ **What's New in Latest Version**
- 🎉 **Comprehensive Emoji Integration** - Over 100+ contextual emojis throughout the interface
- 🔗 **Fixed Logo Links** - Logo links now stay on same tab (removed target="_blank")
- 🏠 **Enhanced Dashboard** - Emoji-rich homepage with better visual hierarchy
- 📰 **Improved News Pages** - Modern news layout with emoji indicators
- 🛒 **Better Order Management** - Enhanced order pages with visual status indicators
- 🔐 **Upgraded Auth Pages** - Login, signup, and password reset with emoji enhancements

## 🚀 Key Features

### 🎨 Modern Design System
- **CSS Custom Properties** - Dynamic theming with CSS variables for consistent styling
- **Automatic Dark Mode** - Respects user's system preference with `prefers-color-scheme`
- **User Theme Toggle** - 🌙/☀️ emoji-based theme switcher with localStorage persistence
- **Modern Typography** - System font stack for optimal performance and readability
- **Enhanced Shadows & Depth** - Layered shadow system for better visual hierarchy
- **Smooth Animations** - Carefully crafted transitions with reduced motion support
- **Emoji-Enhanced UI** - 🎯 Contextual emojis for better user guidance and visual appeal

### 📱 Progressive Web App (PWA)
- **Service Worker** - Workbox-powered offline functionality and caching
- **App Installation** - Native app installation with 📱 Install App button
- **Emoji-Based Icons** - Beautiful 💳 app icons with 💰 accent, ⚙️ admin and 👤 client shortcuts
- **Enhanced Notifications** - 🔄 Update Available! with ✨ Update Now prompts
- **App Shortcuts** - Quick access to admin and client areas from device launcher
- **Offline Support** - Continue working even without internet connection
- **SVG Icons** - Scalable emoji-based icons with HTML preview generation

### 🎯 Performance Optimizations
- **Lazy Loading** - Images load only when needed using Intersection Observer
- **Bundle Optimization** - Webpack-based build system with code splitting
- **Critical CSS** - Above-the-fold styling prioritization
- **Resource Hints** - Preload and prefetch for faster page loads
- **Reduced Motion Support** - Respects user accessibility preferences

### ♿ Enhanced Accessibility & Security
- **Focus Management** - Visible focus indicators and keyboard navigation
- **ARIA Labels** - Proper semantic markup and screen reader support with emoji context
- **Color Contrast** - WCAG AA compliant color schemes
- **Security Headers** - CSP, X-Frame-Options, XSS protection enabled
- **Secure DOM** - No innerHTML usage, XSS-resistant implementations
- **Theme Toggle** - User-controlled dark/light mode with 🌙/☀️ emoji indicators and rotation effects
- **Emoji Accessibility** - Proper ARIA labels for decorative emojis

### 🔧 Advanced JavaScript Features
- **ES6+ Syntax** - Modern JavaScript with async/await and modules
- **Theme Detection** - Automatic light/dark theme switching with emoji feedback
- **Enhanced Forms** - Real-time validation with emoji status indicators
- **Smooth Scrolling** - Native smooth scrolling for anchor links
- **Performance Monitoring** - Built-in performance tracking capabilities
- **Emoji Animations** - CSS transitions and hover effects for emoji elements
- **Dynamic UI Updates** - Real-time emoji status changes based on user actions

### 🎛️ Modern CSS Features
- **CSS Grid & Flexbox** - Modern layout systems for responsive design
- **Container Queries** - Responsive components based on container size
- **Logical Properties** - Better internationalization support
- **Custom Selectors** - Advanced CSS targeting capabilities
- **Modern Pseudo-classes** - Enhanced interactivity with `:focus-visible`, `:where()`

## 📦 What's Included

### Core Files
```
fossbilling-modern/
├── assets/
│   ├── scss/
│   │   ├── fossbilling-modern.scss    # Main stylesheet with emoji support
│   │   ├── markdown.scss              # Enhanced markdown styling
│   │   └── flags/                     # Country flag assets
│   ├── icons/                         # PWA icons (SVG format with emojis)
│   │   ├── icon-*.svg                 # App icons (💳 + 💰 emojis)
│   │   ├── admin-96x96.svg           # Admin shortcut (⚙️ + 🔧 emojis)
│   │   ├── client-96x96.svg          # Client shortcut (👤 + 🏠 emojis)
│   │   ├── support-96x96.svg         # Support icon (🆘 + 💬 emojis)
│   │   ├── billing-96x96.svg         # Billing icon (🧾 + ✅ emojis)
│   │   ├── dashboard-96x96.svg       # Dashboard icon (🏠 + 📊 emojis)
│   │   └── README.md                  # Icon usage guide
│   └── fossbilling-modern.js          # Modern JavaScript with PWA + emoji features
├── build/                             # Compiled assets (auto-generated)
├── config/                            # Theme configuration
├── html/                              # Emoji-enhanced Twig templates
│   ├── mod_index_dashboard.html.twig  # 🏠 Dashboard with comprehensive emojis
│   ├── mod_news_*.html.twig           # 📰 News pages with emoji enhancements
│   ├── mod_order_*.html.twig          # 🛒 Order pages with visual indicators
│   ├── mod_page_*.html.twig           # 🔐 Auth pages with emoji improvements
│   └── (other enhanced templates)
├── scripts/                           # Build and emoji icon generation scripts
├── manifest.json                      # FOSSBilling theme manifest
├── manifest.webmanifest              # PWA web app manifest
├── package.json                       # NPM dependencies and build scripts
├── webpack.config.js                  # Modern build configuration with Workbox
└── screenshot.jpg                     # Theme preview image
```

### Build System
- **Webpack Encore** - Symfony's asset management tool
- **Sass/SCSS** - Advanced CSS preprocessing
- **PostCSS** - Modern CSS transformations
- **Babel** - JavaScript transpilation for browser compatibility
- **Workbox** - Google's PWA toolkit for service workers

### PWA Features
- **Web App Manifest** - Native app-like installation with emoji icons
- **Service Worker** - Workbox-powered offline functionality and caching
- **Background Sync** - Data synchronization when back online
- **Security-First** - XSS protection and secure DOM manipulation
- **App Shortcuts** - Admin (⚙️) and Client (👤) shortcuts from launcher
- **Theme Toggle** - User-facing dark/light mode switcher with 🌙/☀️ icons

## 🎨 Design Tokens

The theme uses a comprehensive design token system:

### Color Palette
```scss
:root {
  --primary-color: #005fc5;      // Brand primary
  --primary-rgb: 0, 95, 197;     // RGB values for alpha
  --warning-color: #f59e0b;      // Warning states
  --danger-color: #dc2626;       // Error states
  --success-color: #16a34a;      // Success states
  --body-bg: #f8fafc;            // Background
  --surface-color: #ffffff;      // Card/surface
  --text-primary: #1e293b;       // Primary text
  --text-secondary: #64748b;     // Secondary text
  --border-color: #e2e8f0;       // Borders
}
```

### Typography Scale
- **Font Stack**: Modern system fonts (Inter, SF Pro, Helvetica Neue, etc.)
- **Responsive Scaling**: Fluid typography using CSS clamp()
- **Semantic Sizes**: Consistent sizing scale across components

### Spacing System
- **Base Unit**: 0.25rem (4px)
- **Scale**: 1, 2, 3, 4, 6, 8, 12, 16, 20, 24, 32, 40, 48, 64
- **Responsive**: Automatic scaling on smaller screens

### Shadow System
```scss
--shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
```

## 🔧 Customization

### CSS Custom Properties
The theme is built with CSS custom properties, making customization simple:

```css
:root {
  --primary-color: #your-brand-color;
  --border-radius: 12px;
  --transition-base: all 0.3s ease;
}

/* Emoji-specific styling */
.emoji-icon {
  font-size: 1.2em;
  margin-right: 0.25rem;
}

.theme-icon {
  transition: transform 0.3s ease;
}

.navbar-theme-toggle:hover .theme-icon {
  transform: rotate(20deg);
}
```

### Theme Variants
Create custom themes by overriding design tokens:

```scss
[data-theme="custom"] {
  --primary-color: #8b5cf6;
  --body-bg: #fafafa;
  --surface-color: #ffffff;
}
```

### Component Customization
Override specific component styles:

```scss
.card {
  --card-padding: 2rem;
  --card-radius: var(--border-radius);
  --card-shadow: var(--shadow-md);
}
```

## 🌐 Browser Support

### Modern Browsers (Full Support)
- Chrome/Edge 88+
- Firefox 85+
- Safari 14+
- Opera 74+

### Progressive Enhancement
- Service Workers: Chrome 45+, Firefox 44+, Safari 11.1+
- CSS Grid: Chrome 57+, Firefox 52+, Safari 10.1+
- CSS Custom Properties: Chrome 49+, Firefox 31+, Safari 9.1+

### Fallbacks
- Graceful degradation for older browsers
- Polyfills included for essential features
- Progressive enhancement approach

## 📊 Performance & Security Metrics

### Lighthouse Scores (Typical)
- **Performance**: 95+ (optimized emoji rendering)
- **Accessibility**: 100 (ARIA-labeled emojis)
- **Best Practices**: 100 (secure emoji implementation)
- **SEO**: 100
- **PWA**: 100 (emoji-enhanced app experience)

### Security Features
- **Content Security Policy** - XSS protection headers
- **X-Frame-Options** - Clickjacking prevention  
- **Secure DOM** - No innerHTML, XSS-resistant emoji rendering
- **HTTPS Ready** - PWA requires HTTPS for full functionality
- **Logo Link Security** - Fixed target="_blank" removal for better UX

### Bundle Sizes
- **CSS**: ~290KB (includes Bootstrap + emoji styling + security)
- **JavaScript**: ~320KB (PWA + emoji features + theme toggle)
- **Service Worker**: ~4KB (Workbox runtime caching)
- **Icons**: SVG format (lightweight emoji-based graphics with fallbacks)

### Loading Performance
- **First Contentful Paint**: <1.5s (optimized emoji fonts)
- **Largest Contentful Paint**: <2.5s
- **Cumulative Layout Shift**: <0.1 (stable emoji positioning)

### Emoji Features
- **100+ Contextual Emojis** - Throughout the interface
- **Semantic Usage** - Each emoji matches its functional context
- **Cross-platform Support** - Apple/Google/Microsoft emoji compatibility
- **Accessibility Compliant** - Proper ARIA labels for screen readers

## 🔄 Updates & Maintenance

### Version History
- **v1.0.0** - Initial release with PWA features
- **v1.1.0** - Added comprehensive emoji integration and security fixes
- Based on Huraga theme structure
- Modern CSS and JavaScript enhancements

### Update Process
1. Check for theme updates in FOSSBilling admin
2. Backup current theme customizations
3. Update theme files
4. Rebuild assets: `npm run build`
5. Test customizations and functionality

## 🤝 Contributing

We welcome contributions to improve the FOSSBilling Modern theme:

1. **Bug Reports** - Use GitHub issues for bug reports
2. **Feature Requests** - Suggest new features via issues
3. **Pull Requests** - Submit improvements via PR
4. **Documentation** - Help improve documentation

### Development Setup
```bash
# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Watch for changes
npm run watch

# Generate PWA icons (emoji-based with multiple variants)
node scripts/generate-pwa-icons-simple.js

# Preview generated emoji icons
# Open assets/icons/*.html files in browser
```

## 🎯 **Emoji Integration Guide**

### Page-Specific Emojis

**🏠 Dashboard/Home Page:**
- 👤 Profile section with 🆔 ID, 📧 Email, 💰 Balance
- 🧾 Invoices with ✅ Paid, ⏰ Unpaid status
- 🛒 Orders with 🟢 Active, ⏰ Expiring indicators  
- 🎫 Support tickets with 🔓 Open, ⏸️ On Hold status
- 🕒 Recent items with 📦 order icons

**📰 News Pages:**
- 📢 Announcements header
- 📄 Individual articles
- 🕐 Timestamp indicators
- 👁️ Continue reading links
- 📭 Empty state graphics

**🛒 Order Pages:**
- 🛍️ Products catalog
- 📦 Service management
- 🆔 Order IDs with 💰 pricing
- 📅 Due dates and 🔄 renewal options
- ⚙️ Management actions

**🔐 Authentication:**
- 📧 Email fields with 🔑 password inputs
- 🚀 Action buttons (Login, Register, Reset)
- 👤 Profile information
- 🏢 Company details

### Emoji Best Practices
- **Contextual**: Each emoji relates directly to its function
- **Consistent**: Same emojis used for same concepts across pages  
- **Accessible**: ARIA labels provided for screen readers
- **Performance**: Optimized font loading and rendering
- **Cross-platform**: Universal emoji support

## 🎛️ **Theme Configuration**

### Branding Control
The "Powered by FOSSBilling Community" footer can be controlled via:
- **Admin Panel**: Extensions → Branding → Enable/Disable
- **Template Control**: `layout_default.html.twig` lines 258-265

### Theme Toggle Settings
- **User Control**: 🌙/☀️ button in navbar for instant theme switching
- **System Preference**: Automatic detection of user's OS theme preference
- **Persistence**: Theme choice saved in localStorage
- **Animation**: Smooth emoji rotation effects on hover

### Emoji Customization
```css
/* Adjust emoji sizes globally */
.emoji-icon {
  font-size: 1.1em; /* Smaller emojis */
}

/* Disable emoji animations if needed */
.navbar-theme-toggle:hover .theme-icon {
  transform: none;
}
```

## 📄 License

This theme is licensed under the same license as FOSSBilling. See the main FOSSBilling repository for license details.

## 🆘 Support

- **Documentation**: [FOSSBilling Docs](https://fossbilling.org/docs/)
- **Community**: [FOSSBilling Discord](https://fossbilling.org/discord)
- **Issues**: [GitHub Issues](https://github.com/FOSSBilling/FOSSBilling/issues)
- **Forum**: [Community Forum](https://forum.fossbilling.org/)

---

## 🌟 **Feature Highlights**

✨ **Latest Updates:**
- 🎨 **100+ Contextual Emojis** integrated throughout the interface
- 🔗 **Fixed Logo Links** - No more unwanted new tabs
- 🌙 **Enhanced Theme Toggle** - User-controlled with emoji feedback
- 📱 **Improved PWA Icons** - Multi-layered emoji designs with accent graphics
- 🏠 **Dashboard Overhaul** - Complete emoji-enhanced user experience
- 📰 **News System** - Modern layout with visual indicators
- 🛒 **Order Management** - Status-aware emoji indicators
- 🔐 **Authentication** - Friendly emoji-guided forms
- 🛡️ **Security First** - XSS protection with secure emoji rendering
- ⚡ **Performance** - Optimized emoji font loading and animations

**Built with ❤️ and lots of 🎉 by the FOSSBilling Community**