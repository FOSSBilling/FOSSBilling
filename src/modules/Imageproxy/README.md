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
- Existing tickets need to be replied to or edited for rewriting to occur
- Check that module is activated in Extensions

**Fetch errors?**
- Some CDNs may block server requests
- Check your server can make outbound HTTPS requests
- Increase timeout limits in settings if needed

## License

Apache License 2.0 - Copyright 2022-2025 FOSSBilling Community

## Credits

Created to address [GitHub Issue #3003](https://github.com/FOSSBilling/FOSSBilling/issues/3003)

