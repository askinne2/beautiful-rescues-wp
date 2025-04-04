# Beautiful Rescues WordPress Plugin

A WordPress plugin for managing rescue donations and displaying Cloudinary image galleries.

## Features

- **Donation Management**
  - Create and manage rescue donations
  - Review and verify donation submissions
  - Email notifications for new donations
  - Status tracking (pending, verified, rejected)

- **Cloudinary Integration**
  - Seamless image upload and management
  - Organized image galleries by categories
  - Responsive image display
  - Watermark support
  - Lazy loading for better performance

- **Gallery Features**
  - Grid layout with responsive design
  - Image selection for donations
  - Zoom preview functionality
  - Sorting options (random, newest, oldest, name)
  - Pagination via "Load More" option

- **Donation Verification**
  - Secure file upload for verification documents
  - Support for images and PDFs
  - Form validation and error handling
  - Admin review interface

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Cloudinary account with API access
- Composer for dependency management

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/beautiful-rescues-wp.git
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Upload the plugin to your WordPress plugins directory:
   ```bash
   cp -r beautiful-rescues-wp /path/to/wordpress/wp-content/plugins/
   ```

4. Activate the plugin through the WordPress admin interface.

5. Configure the plugin settings:
   - Go to Settings > Beautiful Rescues
   - Enter your Cloudinary credentials
   - Configure email settings
   - Set up allowed file types and sizes

## Usage

### Gallery Shortcode
```
[beautiful_rescues_gallery 
    style="default"
    columns="5"
    gutter="1.5rem"
    max_width="1200px"
    category=""
    sort="newest"
    per_page="40"
    tablet_breakpoint="768px"
    mobile_breakpoint="480px"
    tablet_columns="2"
    mobile_columns="2"
]
```

#### Parameters

1. `style` (default: "default") - The gallery style to use
2. `columns` (default: "5") - Number of columns in the gallery grid
3. `gutter` (default: "1.5rem") - Spacing between gallery items
4. `max_width` (default: "1200px") - Maximum width of the gallery container
5. `category` (default: "") - Filter images by category (e.g., "Black", "Calico", etc.)
6. `sort` (default: "newest") - Sort order for images (options: "random", "newest", "oldest", "name")
7. `per_page` (default: "40") - Number of images to load per page
8. `tablet_breakpoint` (default: "768px") - Breakpoint for tablet view
9. `mobile_breakpoint` (default: "480px") - Breakpoint for mobile view
10. `tablet_columns` (default: "2") - Number of columns on tablet devices
11. `mobile_columns` (default: "2") - Number of columns on mobile devices

#### Examples

Show a gallery of black cats with 4 columns on desktop and 2 on mobile:
```
[beautiful_rescues_gallery 
    category="Black"
    columns="4"
    mobile_columns="2"
]
```

Show a random selection of all cats with 3 columns:
```
[beautiful_rescues_gallery 
    sort="random"
    columns="3"
]
```

The gallery includes features like:
- Image selection
- Zoom functionality
- Load more button
- Responsive layout
- Sorting options
- Category filtering
- Watermarking (if enabled in settings)

### Donation Review Shortcode
```
[beautiful_rescues_donation_review]
```

## Development

### Building Assets
```bash
# Install Node dependencies
npm install

# Build assets
npm run build

# Watch for changes
npm run watch
```

### Testing
```bash
# Run PHPUnit tests
composer test

# Run JavaScript tests
npm test
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL-2.0+ License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please open an issue in the GitHub repository or contact the plugin maintainers.

## Credits

- Built by [21adsmedia](https://21adsmedia.com)
- Cloudinary integration powered by the Cloudinary PHP SDK
- Icons and UI elements from various open-source libraries 

