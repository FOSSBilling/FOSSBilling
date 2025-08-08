#!/usr/bin/env node

/**
 * PWA Icon Generator using Emoji
 * Creates all required PWA icons with a billing emoji (💳)
 */

const { createCanvas } = require('canvas');
const fs = require('fs');
const path = require('path');

// Icon sizes needed for PWA
const iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
const shortcutIconSize = 96;

// Emoji and colors
const EMOJI = '💳'; // Credit card emoji for billing
const BG_COLOR = '#005fc5'; // Primary brand color
const EMOJI_SIZE_RATIO = 0.65; // Emoji takes 65% of icon space

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
 * Generate an icon with emoji
 */
function generateIcon(size, emoji, filename) {
  const canvas = createCanvas(size, size);
  const ctx = canvas.getContext('2d');

  // Background with gradient
  const gradient = ctx.createLinearGradient(0, 0, size, size);
  gradient.addColorStop(0, BG_COLOR);
  gradient.addColorStop(1, '#0048a0'); // Darker shade
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, size, size);

  // Add subtle circle background for emoji
  ctx.fillStyle = 'rgba(255, 255, 255, 0.15)';
  ctx.beginPath();
  ctx.arc(size / 2, size / 2, size * 0.4, 0, Math.PI * 2);
  ctx.fill();

  // Draw emoji
  ctx.font = `${Math.floor(size * EMOJI_SIZE_RATIO)}px "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", "Android Emoji", sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(emoji, size / 2, size / 2);

  // Save the icon
  const buffer = canvas.toBuffer('image/png');
  fs.writeFileSync(filename, buffer);
  console.log(`✅ Generated: ${filename}`);
}

/**
 * Generate shortcut icons with different emojis
 */
function generateShortcutIcons() {
  // Admin icon with gear emoji
  generateIcon(
    shortcutIconSize,
    '⚙️',
    path.join(iconsDir, 'admin-96x96.png')
  );

  // Client icon with user emoji
  generateIcon(
    shortcutIconSize,
    '👤',
    path.join(iconsDir, 'client-96x96.png')
  );
}

/**
 * Generate mockup screenshots
 */
function generateScreenshots() {
  // Desktop screenshot (1280x720)
  const desktopCanvas = createCanvas(1280, 720);
  const desktopCtx = desktopCanvas.getContext('2d');
  
  // Background
  desktopCtx.fillStyle = '#f8fafc';
  desktopCtx.fillRect(0, 0, 1280, 720);
  
  // Header bar
  desktopCtx.fillStyle = BG_COLOR;
  desktopCtx.fillRect(0, 0, 1280, 80);
  
  // Logo area
  desktopCtx.fillStyle = 'white';
  desktopCtx.font = '32px sans-serif';
  desktopCtx.fillText('💳 FOSSBilling Modern', 50, 50);
  
  // Content area mockup
  desktopCtx.fillStyle = 'white';
  desktopCtx.fillRect(50, 120, 1180, 550);
  
  // Save desktop screenshot
  const desktopBuffer = desktopCanvas.toBuffer('image/png');
  fs.writeFileSync(path.join(screenshotsDir, 'desktop.png'), desktopBuffer);
  console.log(`✅ Generated: desktop screenshot`);
  
  // Mobile screenshot (390x844 - iPhone 12 size)
  const mobileCanvas = createCanvas(390, 844);
  const mobileCtx = mobileCanvas.getContext('2d');
  
  // Background
  mobileCtx.fillStyle = '#f8fafc';
  mobileCtx.fillRect(0, 0, 390, 844);
  
  // Header bar
  mobileCtx.fillStyle = BG_COLOR;
  mobileCtx.fillRect(0, 0, 390, 80);
  
  // Logo area
  mobileCtx.fillStyle = 'white';
  mobileCtx.font = '24px sans-serif';
  mobileCtx.fillText('💳 FOSSBilling', 20, 50);
  
  // Content area mockup
  mobileCtx.fillStyle = 'white';
  mobileCtx.fillRect(20, 100, 350, 724);
  
  // Save mobile screenshot
  const mobileBuffer = mobileCanvas.toBuffer('image/png');
  fs.writeFileSync(path.join(screenshotsDir, 'mobile.png'), mobileBuffer);
  console.log(`✅ Generated: mobile screenshot`);
}

/**
 * Main function to generate all icons
 */
function generateAllIcons() {
  console.log('🎨 Generating PWA icons with emoji...\n');

  // Generate main app icons
  iconSizes.forEach(size => {
    const filename = path.join(iconsDir, `icon-${size}x${size}.png`);
    generateIcon(size, EMOJI, filename);
  });

  console.log('\n🎯 Generating shortcut icons...\n');
  generateShortcutIcons();

  console.log('\n📸 Generating screenshots...\n');
  generateScreenshots();

  console.log('\n✨ All PWA assets generated successfully!');
}

// Run the generator
generateAllIcons();