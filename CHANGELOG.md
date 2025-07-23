# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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