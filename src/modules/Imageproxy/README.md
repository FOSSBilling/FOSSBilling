# Image Proxy Module

Prevents IP address and user-agent leakage by proxying remote images in support tickets through your FOSSBilling server.

## The Problem

When support tickets contain remote images like `<img src="https://evil.example/tracker.php">`, viewing the ticket causes your browser to directly contact that external server, exposing your IP address and browser information.

## The Solution

This module automatically rewrites remote image URLs to proxy through your FOSSBilling server, so external sites only see your server's IP, not individual staff or client IPs.

## Installation

1. Activate the module via **Extensions** in admin panel
2. Event hooks register automatically - no manual setup needed

## Configuration

Access settings at: **Extensions → Image Proxy → Settings** (or `/admin/extension/settings/imageproxy`)

**Configurable options:**
- Maximum image size (1-50 MB, default: 5 MB)
- Connection timeout (1-30 seconds, default: 5)
- Maximum duration (1-60 seconds, default: 10)

## How It Works

### Automatic Processing

The module hooks into 6 Support ticket events and automatically processes images when:
- Clients create or reply to tickets
- Admins create or reply to tickets
- Guests reply to public tickets

**Note**: This only applies to **new** tickets and replies. To retroactively apply image proxy to existing tickets, see the [Migration](#migration) section below.

### URL Rewriting

**Original:**
```markdown
![Screenshot](https://example.com/image.png)
```

**Automatically becomes:**
```markdown
![Screenshot](http://localhost:8280/imageproxy/image?u=aHR0cHM6Ly9leGFtcGxlLmNvbS9pbWFnZS5wbmc)
```

### Access Control

- ✅ Logged-in clients can view images in their own tickets
- ✅ Logged-in admins can view images in all tickets
- ❌ Guests cannot access proxied images

## Security Features

- **Authentication required**: Must be logged in to view proxied images
- **Size limit**: Configurable maximum (default 5 MB)
- **Timeout protection**: Prevents slow external servers from hanging requests
- **Content validation**: Only allows image/* MIME types
- **Protocol restriction**: Only http:// and https:// URLs
- **Browser cache**: 5-minute cache to reduce repeated requests

## Supported Formats

- HTML `<img>` tags
- Markdown `![alt](url)` syntax

## Migration

### Migrating Existing Tickets

Since the module only processes new tickets and replies automatically, you can retroactively apply image proxy to existing ticket messages.

#### Via Admin Panel

1. Navigate to **Extensions → Image Proxy → Settings**
2. Scroll to the "Migrate Existing Tickets" section
3. Click the **"Migrate Existing Tickets"** button
4. Confirm the operation
5. Wait for the page to reload with a success message

This will scan all existing ticket messages (both regular and public tickets) and rewrite remote image URLs to use the proxy.

#### Via Console Command

For large installations or to run as a scheduled task:

```bash
docker exec fossbilling-app php /var/www/html/console.php imageproxy:migrate-existing
```

**Output example:**
```
Imageproxy: Migrate Existing Tickets
=====================================

 Scanning all ticket messages for remote images...

 [OK] Migration completed!

 -------------------- -------
  Metric               Count
 -------------------- -------
  Messages Processed   150
  Messages with Images 23
  Messages Updated     23
 -------------------- -------
```

### Reverting to Original URLs

If you need to temporarily disable the module or revert all proxified URLs back to their originals:

#### Via Admin Panel

1. Navigate to **Extensions → Image Proxy → Settings**
2. Scroll to the "Migrate Existing Tickets" section
3. Click the **"Revert Proxified URLs"** button
4. Confirm the operation

#### Via Console Command

```bash
docker exec fossbilling-app php /var/www/html/console.php imageproxy:revert
```

### Safety Features

- **Idempotent**: Migration can be run multiple times safely. Already proxified URLs won't be re-proxified.
- **Double-Proxification Prevention**: The module automatically detects and skips already-proxified URLs to prevent nested proxy URLs.
- **Non-destructive**: Only updates messages containing remote images.
- **Reversible**: The `revert` command decodes proxified URLs back to originals.
- **Automatic on Uninstall**: When you uninstall the module, all proxified URLs are automatically reverted to prevent broken images.

## Testing

1. Create a new support ticket with:
   ```markdown
   ![Test](https://picsum.photos/200/300)
   ```

2. View the ticket and check browser DevTools (F12 → Network tab)

3. Verify the image loads from `/imageproxy/image?u=...` not from `picsum.photos`

## Technical Details

- **Symfony HTTP Client**: Used for external requests (per FOSSBilling guidelines)
- **Symfony Response**: Used for serving images
- **Event-driven**: Hooks into Support module events
- **Browser-like headers**: Bypasses most CDN restrictions
- **Base64url encoding**: Safe URL parameter encoding

## Troubleshooting

**Images not loading?**
- Hard refresh your browser (Ctrl+Shift+R or Cmd+Shift+R)
- Create a NEW ticket after activating the module
- Check that external URLs are accessible from your server

**Still seeing direct image loads?**
- For existing tickets, use the [Migration feature](#migration) to apply image proxy retroactively
- Check that module is activated in Extensions

**Fetch errors?**
- Some CDNs may block server requests
- Check your server can make outbound HTTPS requests
- Increase timeout limits in settings if needed

## License

Apache License 2.0 - Copyright 2022-2025 FOSSBilling Community

## Credits

Created to address [GitHub Issue #3003](https://github.com/FOSSBilling/FOSSBilling/issues/3003)

