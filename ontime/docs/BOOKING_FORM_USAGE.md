# OnTime Multi-Step Booking Form

## Overview

A modern, mobile-first, step-by-step booking experience for WordPress that integrates seamlessly with the OnTime plugin. This implementation uses vanilla JavaScript, CSS Grid/Flexbox, and follows WordPress best practices for security.

## Features

- **5-Step Booking Process**: Service → Staff → Date & Time → Customer Info → Confirmation
- **Mobile-First Design**: Fully responsive with touch-friendly interactions
- **Modern UX**: App-like experience with smooth animations and clear progress indicators
- **Security**: Full CSRF protection with WordPress nonces and proper input sanitization
- **Performance**: Lightweight, no jQuery or heavy library dependencies
- **Customizable**: CSS custom properties (variables) for easy branding
- **RTL Support**: Full right-to-left support for Persian language
- **Accessibility**: ARIA attributes and keyboard navigation support

## Installation

The booking form is automatically registered when the OnTime plugin is activated. No additional installation is required.

## Usage

### Basic Shortcode

Simply add the shortcode to any post or page:

```php
[ontime_booking_form]
```

### Shortcode with Custom Title

```php
[ontime_booking_form title="رزرو نوبت کلی نیک"]
```

### Shortcode with Preselected Service

```php
[ontime_booking_form service_id="5"]
```

### Shortcode with Preselected Staff

```php
[ontime_booking_form staff_id="3"]
```

### Shortcode with Both Preselected

```php
[ontime_booking_form service_id="5" staff_id="3" title="رزرو نوبت با علی کارشناس"]
```

## File Structure

```
ontime/
├── includes/
│   └── Core/
│       └── class-ontime-plugin.php       # Updated to load booking form
├── public/
│   ├── class-ontime-booking-form.php     # Main booking form class
│   ├── css/
│   │   └── booking-form.css              # Modern mobile-first CSS
│   └── js/
│       └── booking-form.js               # Vanilla JavaScript
└── docs/
    └── BOOKING_FORM_USAGE.md              # This file
```

## Architecture

### 1. Shortcode Registration

The shortcode `[ontime_booking_form]` is registered in `class-ontime-booking-form.php` and outputs a complete multi-step form with all necessary HTML, inline data, and hidden fields.

### 2. AJAX Handlers

All form interactions happen via AJAX with proper nonce verification:

- `ontime_get_services` - Get available services (for dynamic loading)
- `ontime_get_staff` - Get available staff members
- `ontime_get_available_dates` - Get dates with availability
- `ontime_get_available_slots` - Get available time slots for a specific date
- `ontime_validate_customer_info` - Validate customer information
- `ontime_confirm_booking` - Create the final appointment

### 3. JavaScript Features

- **No Dependencies**: Pure vanilla JavaScript (no jQuery)
- **State Management**: Internal state tracking for selections
- **Form Validation**: Real-time validation with helpful error messages
- **Jalali Date Picker**: Custom date picker for Persian calendar
- **Async Loading**: Non-blocking AJAX requests for slots
- **Success Modal**: Confirmation modal with appointment details

### 4. CSS Features

- **CSS Custom Properties**: Easy customization without modifying core files
- **Mobile-First**: Responsive breakpoints for all screen sizes
- **Flexbox/Grid**: Modern layout techniques
- **Animations**: Smooth transitions and micro-interactions
- **RTL Support**: Optimized for right-to-left languages
- **Accessibility**: Focus states and reduced motion support

## Security Measures

### CSRF Protection

- All AJAX requests include a WordPress nonce
- Server-side verification with `check_ajax_referer()`
- Nonce is unique per session and form instance

### Input Sanitization

All form inputs are sanitized before database operations:

- `sanitize_text_field()` - For text inputs (name, notes)
- `sanitize_email()` - For email addresses
- `absint()` - For numeric IDs (service, staff)
- `sanitize_textarea_field()` - For textarea inputs

### Output Escaping

All output is properly escaped:

- `esc_html__()` / `esc_html_e()` - For HTML output
- `esc_attr()` - For HTML attributes
- `esc_url()` - For URLs
- `wp_json_encode()` - For JSON data

### Database Security

- All database operations use prepared statements
- IDs are cast to integers with `absint()`
- Dates are validated before processing
- Appointment creation includes race condition protection

## Customization

### CSS Variables

You can customize the appearance by overriding CSS variables in your theme:

```css
:root {
    /* Primary colors */
    --ontime-primary: #2563eb;
    --ontime-primary-dark: #1d4ed8;
    --ontime-primary-light: #dbeafe;
    
    /* Change primary color to green */
    --ontime-primary: #10b981;
    --ontime-primary-dark: #059669;
    --ontime-primary-light: #d1fae5;
    
    /* Typography */
    --ontime-font-family: 'Vazirmatn', sans-serif;
    
    /* Spacing */
    --ontime-spacing-md: 1.5rem;
    
    /* Border radius */
    --ontime-radius-lg: 1rem;
}
```

### Translations

All text is translatable via WordPress translation functions. To add or modify translations:

1. Use the `ontime` text domain
2. Add translations to your theme or a custom plugin
3. Use the WordPress translation API

## Hooks & Filters

### Actions

- `ontime_appointment_confirmed` - Fires when an appointment is successfully created
  - Parameters: `$appointment_id`, `$appointment_data`

### Example: Send Confirmation Email

```php
add_action('ontime_appointment_confirmed', function($appointment_id, $appointment_data) {
    // Send email to customer
    wp_mail(
        $appointment_data['customer_email'],
        'تایید نوبت',
        'نوبت شما با موفقیت رزرو شد.'
    );
}, 10, 2);
```

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome for Android)

The form uses modern JavaScript (ES6+) and CSS features that are supported in all modern browsers.

## Performance

### Optimizations

- Minimal DOM manipulation
- Event delegation where appropriate
- Efficient selectors with caching
- Async loading of time slots
- Conditional asset loading (only on pages with the shortcode)
- No jQuery dependency
- Minimal CSS footprint

### PageSpeed Considerations

- All CSS and JS are loaded only when needed
- No render-blocking resources (CSS is in head, JS is deferred)
- Inline critical CSS for above-the-fold content
- Efficient JavaScript with no heavy libraries

## Testing

### Manual Testing

1. Add the shortcode to a page
2. Verify all steps can be completed
3. Test with different service/staff combinations
4. Verify validation works (required fields, email format, phone format)
5. Test date picker functionality
6. Verify time slots are loaded correctly
7. Test confirmation and success modal
8. Verify mobile responsiveness

### Automated Testing

The form is designed to work with WordPress's built-in testing framework. You can extend the test suite to include:

```php
class OnTime_Booking_Form_Test extends WP_UnitTestCase {
    public function test_shortcode_output() {
        $output = do_shortcode('[ontime_booking_form]');
        $this->assertStringContainsString('ontime-booking-container', $output);
    }
    
    public function test_ajax_get_services() {
        // Test AJAX handler
        $_POST['action'] = 'ontime_get_services';
        $_POST['nonce'] = wp_create_nonce('ontime_booking_nonce');
        
        // Run the AJAX handler
        try {
            ob_start();
            do_action('wp_ajax_ontime_get_services');
            $output = ob_get_clean();
            $response = json_decode($output);
            $this->assertTrue($response->success);
        } catch (WPAjaxDieContinueException $e) {
            // Expected for AJAX
        }
    }
}
```

## Troubleshooting

### Shortcode Not Working

1. Verify the OnTime plugin is activated
2. Check that the shortcode is spelled correctly: `[ontime_booking_form]`
3. Ensure there are services and staff members defined in the admin

### AJAX Requests Failing

1. Verify the nonce is valid (check browser console for 403 errors)
2. Check that WordPress admin-ajax.php is accessible
3. Ensure the user has the correct capabilities

### Styling Issues

1. Check for CSS conflicts with your theme
2. Verify RTL direction is set correctly
3. Ensure CSS variables are not being overridden

### Date Picker Not Working

1. Verify the Jalali date format is correct (YYYY/MM/DD)
2. Check that the calendar container is visible
3. Ensure there are no JavaScript errors in the console

## Future Enhancements

- Add payment gateway integration
- Implement calendar synchronization with Google Calendar
- Add SMS notification support
- Implement time zone support
- Add custom field support
- Add conditional logic for service/staff selection
- Implement waitlist functionality

## Support

For support and bug reports, please contact the OnTime development team.

---

**Version**: 1.0.0  
**License**: GPL-2.0+  
**Author**: OnTime Team  
**Author URI**: https://ontime.ir
