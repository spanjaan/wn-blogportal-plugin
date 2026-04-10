# Changelog

All notable changes to this plugin will be documented in this file.

## [2.0.0] - 2024-04-10

### ⚠️ Breaking Changes
- Minimum PHP version is now 8.0+ (was 7.4)
- Requires Winter CMS 1.2+

### 🚀 New Features
- Added reusable post card partials for all post components
- Added settings caching for better performance
- Added database indexes for all major tables

### 🔧 Improvements
- Refactored CommentSection query to reduce database cloning
- Replaced `$_SERVER` with Laravel `Request` class for security
- Added time interval constants in Comment model
- Updated component templates to use Tailwind CSS only
- Improved dark mode support in UI components
- Added title property to post components for customization

### 🐛 Bug Fixes
- Fixed duplicate category display issue
- Fixed video play button on post cards
- Fixed deprecated `getRenderContentAttribute` method removed

### 📦 Database Migrations
- v1.0.8: Added indexes to comments table
- v1.0.9: Added indexes to sharecounts, visitors, and tags tables

---

## [1.0.7] - 2024-XX-XX
- Minor typo fixes

## [1.0.6] - 2024-XX-XX
- Added share counts table

## [1.0.5] - 2024-XX-XX
- Added backend user author features

## [1.0.4] - 2024-XX-XX
- Added comments system

## [1.0.3] - 2024-XX-XX
- Added view tracking

## [1.0.2] - 2024-XX-XX
- Added visitors table

## [1.0.1] - 2024-XX-XX
- Added tags table

## [1.0.0] - 2024-XX-XX
- Initial release