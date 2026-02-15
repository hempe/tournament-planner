# Iframe Mode

The Tournament Planner supports running in **iframe mode** for embedding into other websites or applications.

## Features

When running in iframe mode, the application automatically adapts its UI:

- **No navigation bar** - Main navigation is hidden, controls moved to calendar header
- **Auto language detection** - Automatically detects and sets browser language
- **Smaller fonts** - Optimized for embedded viewing
- **Reduced spacing** - Tighter margins and padding
- **Responsive design** - Works on mobile and desktop
- **Light theme only** - Forces light theme for seamless integration
- **No admin rights** - Admin features are disabled in iframe mode
- **Hard edges** - No rounded corners for clean integration

## Usage

### Basic Iframe Mode

Add the `?iframe=1` query parameter to any URL:

```html
<iframe src="https://your-domain.com/?iframe=1" width="100%" height="600"></iframe>
```

### Compact Mode

For even more compact UI, add `?compact=1`:

```html
<iframe src="https://your-domain.com/?iframe=1&compact=1" width="100%" height="600"></iframe>
```

### Demo Page

A demo page is included at `/iframe-demo.html` that shows the application embedded in an iframe, matching the exact layout of the Golf El Faro website:

```bash
# Start the development server
./run.sh

# Open in browser
http://localhost:5000/iframe-demo.html
```

## Detection

The application detects iframe mode in three ways:

1. **URL Parameter**: `?iframe=1` in the query string
2. **JavaScript Detection**: Automatically detects if `window.self !== window.top`
3. **Session Storage**: Persists the iframe mode setting across page loads
4. **Browser Language**: Automatically detects browser language (de, en, es) on first load

## CSS Styling

Iframe mode applies custom styles from `/styles/iframe.css`:

### Data Attributes

The `<html>` element receives data attributes:
- `data-iframe="true"` - Indicates iframe mode is active
- `data-compact="true"` - Indicates compact mode is active (optional)

### CSS Selectors

Use these selectors to style iframe mode:

```css
/* Apply to all iframe mode */
[data-iframe="true"] .your-element {
    /* styles */
}

/* Apply only to compact mode */
[data-iframe="true"][data-compact="true"] .your-element {
    /* styles */
}

/* Hide in iframe mode */
[data-iframe="true"] .iframe-hide {
    display: none;
}

/* Show only in iframe mode */
.iframe-only {
    display: none;
}

[data-iframe="true"] .iframe-only {
    display: block;
}
```

## PHP Helpers

Check iframe mode in PHP:

```php
if (is_iframe_mode()) {
    // Running in iframe mode
}

if (is_compact_mode()) {
    // Running in compact iframe mode
}
```

## Styling Customization

Default iframe styles include:

- Reduced navigation bar height (45px → 40px in compact)
- Smaller title font size (1.2rem)
- Compact buttons (smaller padding)
- Reduced card margins
- Smaller table fonts (0.9rem)
- Tighter form element spacing

To customize, override styles in your own CSS:

```css
[data-iframe="true"] .navtop {
    background: your-custom-color;
    min-height: your-custom-height;
}
```

## JavaScript API

The iframe detection script provides:

```javascript
// Check if in iframe
const isIframe = window.self !== window.top;

// Get iframe mode from URL
const urlParams = new URLSearchParams(window.location.search);
const iframeMode = urlParams.get('iframe') === '1';
```

## Communication with Parent

To send messages from the iframe to the parent page:

```javascript
// In iframe
window.parent.postMessage({ type: 'event', data: 'value' }, '*');
```

To receive messages in the parent page:

```javascript
// In parent page
window.addEventListener('message', function(event) {
    if (event.data.type === 'event') {
        console.log('Received:', event.data);
    }
});
```

## Security Considerations

1. **X-Frame-Options**: Ensure your server allows framing if needed
2. **Content-Security-Policy**: Configure CSP frame-ancestors appropriately
3. **postMessage Origin**: Always validate message origins in production

```php
// Example: Allow framing from specific domains
header('X-Frame-Options: ALLOW-FROM https://trusted-domain.com');
```

## Examples

### Embed in WordPress

```html
<!-- WordPress shortcode or HTML block -->
<iframe
    src="https://your-tournament-planner.com/?iframe=1"
    width="100%"
    height="800"
    frameborder="0"
    style="border: none; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
></iframe>
```

### Embed in React

```jsx
function TournamentPlanner() {
    return (
        <iframe
            src="https://your-tournament-planner.com/?iframe=1&compact=1"
            width="100%"
            height="600"
            frameBorder="0"
            title="Tournament Planner"
        />
    );
}
```

### Embed in Vue

```vue
<template>
    <iframe
        src="https://your-tournament-planner.com/?iframe=1"
        width="100%"
        height="600"
        frameborder="0"
        title="Tournament Planner"
    />
</template>
```

## Browser Compatibility

Iframe mode is supported in all modern browsers:
- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

## Troubleshooting

### Iframe not loading

1. Check browser console for CSP errors
2. Verify X-Frame-Options header allows framing
3. Ensure HTTPS is used if parent page uses HTTPS

### Styles not applying

1. Clear browser cache
2. Check that `iframe.css` is loaded (Network tab)
3. Verify `data-iframe="true"` attribute is set on `<html>`

### Communication issues

1. Check postMessage origin validation
2. Ensure both pages use compatible protocols (both HTTP or both HTTPS)
3. Verify iframe has correct `src` URL

## Performance

Iframe mode is optimized for performance:
- CSS is loaded only once
- JavaScript detection runs immediately to prevent flash
- SessionStorage caches mode settings
- No additional HTTP requests

## Future Enhancements

Potential improvements:
- [ ] Theme synchronization with parent page
- [ ] Automatic height adjustment based on content
- [ ] Sandboxed mode with restricted permissions
- [ ] API for programmatic control from parent
- [ ] Dark/light mode message passing
