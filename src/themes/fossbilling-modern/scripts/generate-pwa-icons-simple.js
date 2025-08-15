#!/usr/bin/env node

/**
 * Simple PWA Icon Generator
 * Creates basic colored icons with text for PWA
 */

const fs = require('fs');
const path = require('path');

// Icon sizes needed for PWA
const iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Create assets directory if it doesn't exist
const iconsDir = path.join(__dirname, '../assets/icons');
const screenshotsDir = path.join(__dirname, '../assets/screenshots');

if (!fs.existsSync(iconsDir)) {
  fs.mkdirSync(iconsDir, { recursive: true });
}

if (!fs.existsSync(screenshotsDir)) {
  fs.mkdirSync(screenshotsDir, { recursive: true });
}

/**
 * Generate a simple SVG icon with enhanced emoji styling
 */
function generateSVGIcon(size, emoji, bgColor = '#005fc5', accentEmoji = '') {
  const hasAccent = accentEmoji && size >= 128; // Only show accent on larger icons
  
  return `<svg width="${size}" height="${size}" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:${bgColor};stop-opacity:1" />
      <stop offset="100%" style="stop-color:#0048a0;stop-opacity:1" />
    </linearGradient>
    <filter id="glow">
      <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
      <feMerge> 
        <feMergeNode in="coloredBlur"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  </defs>
  <rect width="${size}" height="${size}" fill="url(#grad)" rx="${size * 0.1}" />
  <circle cx="${size/2}" cy="${size/2}" r="${size * 0.4}" fill="rgba(255,255,255,0.1)" />
  <circle cx="${size/2}" cy="${size/2}" r="${size * 0.25}" fill="rgba(255,255,255,0.05)" />
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" 
        font-size="${size * 0.45}" font-family="Apple Color Emoji, Segoe UI Emoji, system-ui"
        filter="url(#glow)">
    ${emoji}
  </text>
  ${hasAccent ? `<text x="75%" y="25%" dominant-baseline="middle" text-anchor="middle" 
        font-size="${size * 0.2}" font-family="Apple Color Emoji, Segoe UI Emoji, system-ui">
    ${accentEmoji}
  </text>` : ''}
</svg>`;
}

/**
 * Convert SVG to base64 data URL
 */
function svgToDataURL(svg) {
  const base64 = Buffer.from(svg).toString('base64');
  return `data:image/svg+xml;base64,${base64}`;
}

/**
 * Create a placeholder PNG using SVG with enhanced emoji variations
 * Note: This creates an SVG file that browsers will render as an image
 */
function createPlaceholderIcon(size, emoji, filename, accentEmoji = '', bgColor = '#005fc5') {
  const svg = generateSVGIcon(size, emoji, bgColor, accentEmoji);
  // Save as .svg but it will work as icon source
  const svgFilename = filename.replace('.png', '.svg');
  fs.writeFileSync(svgFilename, svg);
  
  // Also create a simple HTML file that can be converted to PNG manually if needed
  const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>PWA Icon ${size}x${size}</title>
  <style>
    body { 
      margin: 0; 
      padding: 20px; 
      background: #f0f0f0;
      font-family: system-ui, sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .icon { 
      width: ${size}px; 
      height: ${size}px; 
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .info {
      margin-top: 20px;
      text-align: center;
      color: #666;
    }
  </style>
</head>
<body>
  <div class="info">
    <h3>FOSSBilling PWA Icon</h3>
    <p>Size: ${size}×${size}px | Emoji: ${emoji}${accentEmoji ? ' + ' + accentEmoji : ''}</p>
  </div>
  ${svg}
  <div class="info">
    <p><small>Screenshot this area to create PNG version</small></p>
  </div>
  <script>
    // This page can be screenshot to create a PNG
    console.log('📸 Screenshot the icon at ${size}x${size} to create the PNG version');
    console.log('🎨 Icon features: ${emoji}${accentEmoji ? ' with ' + accentEmoji + ' accent' : ''}, gradient background');
  </script>
</body>
</html>`;
  
  const htmlFilename = filename.replace('.png', '.html');
  fs.writeFileSync(htmlFilename, html);
  
  console.log(`✅ Generated: ${svgFilename} (and ${path.basename(htmlFilename)} for manual conversion)`);
}

/**
 * Main function to generate all icons
 */
function generateAllIcons() {
  console.log('🎨 Generating PWA icons (SVG format)...\n');
  console.log('Note: These are SVG icons that will work in modern browsers.');
  console.log('For PNG conversion, you can use online tools or screenshot the HTML files.\n');

  // Generate main app icons with credit card emoji and billing accent
  iconSizes.forEach(size => {
    const filename = path.join(iconsDir, `icon-${size}x${size}.png`);
    createPlaceholderIcon(size, '💳', filename, '💰', '#005fc5');
  });

  // Generate shortcut icons with enhanced emojis
  console.log('\n🎯 Generating shortcut icons...\n');
  
  // Admin icon with gear emoji and settings accent
  createPlaceholderIcon(96, '⚙️', path.join(iconsDir, 'admin-96x96.png'), '🔧', '#dc3545');
  
  // Client icon with user emoji and account accent
  createPlaceholderIcon(96, '👤', path.join(iconsDir, 'client-96x96.png'), '🏠', '#198754');

  // Generate additional themed icons
  console.log('\n✨ Generating bonus emoji icons...\n');
  
  // Support icon
  createPlaceholderIcon(96, '🆘', path.join(iconsDir, 'support-96x96.png'), '💬', '#fd7e14');
  
  // Invoice/billing icon
  createPlaceholderIcon(96, '🧾', path.join(iconsDir, 'billing-96x96.png'), '✅', '#6f42c1');
  
  // Dashboard icon
  createPlaceholderIcon(96, '🏠', path.join(iconsDir, 'dashboard-96x96.png'), '📊', '#0dcaf0');

  console.log('\n📝 Icon generation complete!');
  console.log('\n📸 Note about screenshots:');
  console.log('The theme is configured to use your existing screenshot.jpg');
  console.log('If you need specific screenshot sizes, you can:');
  console.log('1. Use your existing screenshot.jpg');
  console.log('2. Create desktop.png (1280x720) and mobile.png (390x844) from it');
  
  // Create a simple README for the icons
  const readme = `# PWA Icons

## Generated Icons

The following SVG icons have been generated for your PWA:

### Main App Icons
- icon-72x72.svg - Small icon
- icon-96x96.svg - Medium icon
- icon-128x128.svg - Large icon
- icon-144x144.svg - Extra large icon
- icon-152x152.svg - iPad icon
- icon-192x192.svg - Chrome/Android icon
- icon-384x384.svg - High resolution icon
- icon-512x512.svg - Splash screen icon

### Shortcut Icons
- admin-96x96.svg - Admin area shortcut (⚙️)
- client-96x96.svg - Client area shortcut (👤)

## Converting to PNG

If you need PNG versions, you can:
1. Open the .html files in a browser and take screenshots
2. Use an online SVG to PNG converter
3. Use ImageMagick: \`convert icon-512x512.svg icon-512x512.png\`

## Using the Icons

The manifest.webmanifest is already configured to use these icons.
Modern browsers support SVG icons in PWA manifests.
`;

  fs.writeFileSync(path.join(iconsDir, 'README.md'), readme);
  console.log('\n✅ Created README.md in assets/icons directory');

  console.log('\n✨ All PWA assets prepared successfully!');
}

// Run the generator
generateAllIcons();