# LinkPilot

AI-powered internal link suggestions and orphaned content rescue for WordPress.

## Features

### Post Analyzer Panel (Gutenberg Sidebar)
- Accessible from the Gutenberg editor sidebar
- Press "Analyze" to scan your content
- Extracts keywords from your content (single words and phrases)
- Shows 5 suggested internal links based on content relevance
- Uses WordPress database to find overlapping keywords
- Ranks candidates by relevance score
- Automatically excludes:
  - The current post
  - Draft posts
  - Non-indexed posts (supports Yoast SEO, Rank Math, All in One SEO, SEOPress)

### Orphaned Content Finder (Admin Page)
- Accessible from the WordPress admin menu
- Finds posts and pages with zero incoming internal links
- Helps identify content that may need better internal linking
- Filter by post type (posts, pages, custom post types)
- Direct links to edit or view orphaned content

## Installation

1. Download or clone this repository
2. Upload the `linkpilot` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

## Development

### Requirements
- Node.js 18+
- npm 9+

### Build

```bash
npm install
npm run build
```

### Development Mode

```bash
npm run start
```

## Usage

### Using the Post Analyzer
1. Edit any post or page in the Gutenberg editor
2. Click the LinkPilot icon in the top toolbar, or find it in the "More Menu" (three dots)
3. Click "Analyze Content" to scan your content
4. View extracted keywords and suggested internal links
5. Click "Copy Link" to copy a link to your clipboard, then paste it into your content

### Using the Orphaned Content Finder
1. Go to **LinkPilot** in the WordPress admin menu
2. Select the post types you want to scan
3. Click "Scan for Orphaned Content"
4. Review the list of posts with no incoming internal links
5. Click "Edit" to add internal links to these posts

## License

MIT License - see [LICENSE](LICENSE) file for details.
