# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2025-09-14
### Removed
- **Removing**: Page-level caching support has been removed because it consumed excessive cache space. Using block-level caching is a more efficient approach.

### Updated
- **Updated**: Unit and Integration tests to reflect the removal of page-level caching.
- **Documentation**: Updated README and other documentation to remove references to page-level caching.

## [2.1.2] - 2025-07-30
### Added
- **Refactoring**: Improved `Plugin/Framework/App/Response/Http.php` to prevent trying to render head elements in non-HTML responses

## [2.1.1] - 2025-07-25
### Added
- **Improved caching**: Enhanced block-level caching key generation

## [2.1.0] - 2025-07-25
### Added
- **Block-Level Caching System**: Implemented comprehensive block caching detection and tracking
  - New `BlockCacheDetectorInterface` for detecting block cache status
  - New `HeadElementTrackerInterface` for tracking head elements per block
  - New `BlockHeadElementCacheInterface` for block-specific head element caching
- **Event-Driven Architecture**: Added observers for block HTML rendering lifecycle
  - `BlockHtmlBeforeObserver` - Handles head element tracking before block rendering
  - `BlockHtmlAfterObserver` - Manages head element persistence after block rendering
  - Event observers for `view_block_abstract_to_html_before` and `view_block_abstract_to_html_after`
- **Enhanced Dependency Injection**: Extended DI configuration for new block caching interfaces
- **Improved Cache Strategy**: Better handling of head elements in cached block contexts

### Changed
- Enhanced block-level cache detection and element tracking capabilities
- Improved integration with Magento's block rendering lifecycle

## [2.0.3] - 2025-07-23
### Added
- Moved placeholder elements to require.js block
- Added new two containers `before_head_additional` and `after_head_additional` for head elements, `before_head_additional` will be rendered before the require.js block, and `after_head_additional` will be rendered after the require.js block

## [2.0.2] - 2025-06-26
### Added
- Added new cache type 'head_tag_manager' to support cleaning and flushing from Magento cache management

### Fixed
- Fixed duplicating of each head element

## [2.0.1] - 2025-06-26
### Fixed
- Resolved site-wide element caching by generating a unique cache key for each cacheable page

## [2.0.0] - 2025-06-25

### Added
- **Cache-Aware Architecture**: Implemented `HeadElementCacheStrategyInterface` to solve empty renders on cached pages
- **SOLID Compliance**: Complete refactoring using Strategy and Factory Registry patterns
- **Serialization System**: New `HeadElementSerializerInterface` with pluggable strategies
- **Factory Registry**: Dynamic factory lookup via `HeadElementFactoryRegistryInterface`
- **Strategy Registry**: Extensible serialization strategies via `SerializationStrategyRegistryInterface`
- **Comprehensive Test Suite**: Unit tests, integration tests, and strategy validation tests
- **Element Factories**: Wrapper factories for improved dependency injection
- **Cache Integration**: Automatic element persistence and loading

### Changed
- **BREAKING**: `HeadTagManager` constructor now requires `HeadElementCacheStrategyInterface`
- **BREAKING**: Eliminated hardcoded `instanceof` checks in favor of strategy pattern
- **BREAKING**: Replaced `match` statements with factory registry pattern
- **BREAKING**: Element serialization now uses strategy-based approach
- **Improved**: Better separation of concerns with dedicated interfaces
- **Enhanced**: More extensible architecture for custom element types

### Removed
- **BREAKING**: Direct cache dependencies from `HeadTagManager`
- **BREAKING**: Hardcoded element type checks and factory selection
- **BREAKING**: Static serialization logic

### Fixed
- Empty head element renders on cached page loads
- SOLID principle violations in element handling
- Tight coupling between cache logic and element management


## [1.0.0] - 2025-01-01

### Added
- Initial release of HeadTagManager module
- Basic head element management (Meta, Link, Script, Style)
- CSP (Content Security Policy) support
- ViewModel integration
- Basic element rendering and management

### Features
- Meta tag management (`addMetaName`, `addMetaProperty`, `addCharset`)
- Stylesheet management (`addStylesheet`)
- Script management (`addExternalScript`, `addInlineScript`)
- Style management (`addInlineStyle`)
- Link element management
- Element rendering and output

[2.0.1]: https://github.com/hryvinskyi/magento2-head-tag-manager/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/hryvinskyi/magento2-head-tag-manager/compare/1.0.0...2.0.0
[1.0.0]: https://github.com/hryvinskyi/magento2-head-tag-manager/releases/tag/1.0.0