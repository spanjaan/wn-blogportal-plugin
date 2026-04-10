# BlogPortal

[![Winter CMS](https://img.shields.io/badge/Winter-CMS-brightgreen.svg)](https://wintercms.com)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A powerful Winter CMS plugin that extends Winter.Blog with comments, tags, archives, view tracking, social sharing, and author features.

## Features

### 💬 Comments System
- Hierarchical nested comments with replies
- User authentication (Backend, Frontend, Guest)
- Comment moderation (Approve, Reject, Spam)
- Like/Dislike functionality
- Author favorites - Pin important comments
- Markdown support
- Captcha & Honeypot spam protection
- Comment modes: Open, Restricted, Private, Closed

### 🏷️ Tag Management
- Custom tags with slugs
- Promoted tags support
- Tag color customization
- Filter posts by tags

### 📅 Archive System
- Archive links by year/month
- Date-based post filtering

### 📊 Statistics & Analytics
- View counter & unique visitors
- Comment statistics
- Post popularity tracking

### 🔗 Social Sharing
- Facebook, Twitter, LinkedIn, WhatsApp
- Share count tracking

### 👤 Author Features
- Custom display names
- Author slug & archives
- About me section

## Installation

```bash
composer require spanjaan/wn-blogportal-plugin
php artisan migrate
```

## Components

### Posts Components

| Component | Description |
|-----------|-------------|
| `blogportalPostsByTag` | Display posts filtered by tag |
| `blogportalPostsByAuthor` | Display posts by author |
| `blogportalPostsByDate` | Display posts by date |
| `blogportalPostsByCommentCount` | Posts sorted by comments |
| `blogportalPopularPosts` | Most viewed posts |

### Comments Components

| Component | Description |
|-----------|-------------|
| `blogportalCommentSection` | Full comments with form |
| `blogportalCommentList` | Comments list only |

### UI Components

| Component | Description |
|-----------|-------------|
| `blogportalTags` | List of tags |
| `blogportalArchiveLinks` | Archive links |
| `blogportalShareButtons` | Social share buttons |

## Usage Examples

### Comments Section
```twig
[blogportalCommentSection]
postSlug = "{{ :slug }}"
commentsPerPage = 10
sortOrder = "created_at desc"
formPosition = "above"
==
```

### Posts by Tag
```twig
[blogportalPostsByTag]
tagFilter = "{{ :slug }}"
postsPerPage = 10
==
```

### Posts by Author
```twig
[blogportalPostsByAuthor]
authorFilter = "{{ :slug }}"
postsPerPage = 10
==
```

### Posts by Date
```twig
[blogportalPostsByDate]
dateFilter = "{{ :date }}"
postsPerPage = 10
==
```

### Popular Posts
```twig
[blogportalPopularPosts]
postsPerPage = 5
==
```

### Tags List
```twig
[blogportalTags]
hideEmpty = 1
==
```

### Archive Links
```twig
[blogportalArchiveLinks]
archivePage = "blog/date"
==
```

### Share Buttons
```twig
[blogportalShareButtons]
postSlug = "{{ :slug }}"
==
```

## Settings

Access via **Settings > BlogPortal** in backend:

- Guest comments enable/disable
- Comment moderation options
- Like/Dislike settings
- Markdown & Captcha
- TOS requirement
- Comment modes per post

## Backend Features

### Menu Items
- **Tags** - Manage blog tags
- **Comments** - Manage with moderation
- **Share Counts** - Social statistics

### Post Fields
- Comments visibility
- Comment mode (Open/Restricted/Private/Closed)

### User Fields
- Display name
- Author slug
- About me

## Requirements

- Winter CMS 1.2+
- Winter.Blog plugin
