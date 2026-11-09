# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - 2026-11-09

### Added
- `StorableAttributesInterface` (`@api`) turns every `\Stringable` in an element's attributes, at any depth, into its text before the element is stored
- `CachedBlockHtmlCodecInterface` and `BlockHtmlCacheLoaderInterface` carry the new cache flow and are not `@api`
- `AbstractHeadElement::getTextAttributes()` (protected) gives a subclass its attributes as text values, for a tag built by the secure renderer

### Fixed
- A block served from the block HTML cache always brings back the head elements it added. They are stored in the block's own cache entry, written with the HTML under the block cache lock. Before, they lived in a second entry written after the HTML, so a request between the two writes, an evicted or failed second entry, or an element added in `_afterToHtml()` on a cache hit lost them, and the full page cache then kept the page without the block's stylesheet or script
- A block whose `_toHtml()` throws leaves no state behind
- A cacheable block without a layout name (created through the block factory), and a content block with a cache lifetime, keep their head elements on a cache hit too
- An element whose data cannot be stored (invalid UTF-8, an element the serializer cannot handle) is left out of the block's entry with a warning; the block is still served from the cache with the rest. A block whose `_toHtml()` returns `false` or `null` is cached as an empty string
- An element key made of digits (`'123'`) comes back on a cache hit
- Text at the end of a block's HTML that only looks like this module's comment (in an entry written by 3.x or while the module was disabled, or in content) is never read as head elements: the comment carries an HMAC of its payload made with the installation's crypt key
- A script without content whose `src` is relative, protocol-relative or has an upper-case scheme is written as text instead of being dropped: the Content Security Policy processor throws on such a script
- Element factories accept data the object manager used to convert: a numeric-string `sort_order` (as layout arguments pass it), scalar or `\Stringable` content; before, these raised a `TypeError`. An attribute name made of digits no longer makes the element fail to render
- Attribute names passed to an element's constructor are stored in lower case, as through the setters
- A generated key never fails on invalid UTF-8 in an element's data
- A `\Stringable` inside an array attribute survives the block cache as its text
- Two serialization strategies with the same priority: the one registered later wins, as in 3.x
- A cache hit of a cacheable block no longer costs a second cache read
- A remote stylesheet (`rel="stylesheet"`, or `rel="preload"` with `as="style"`, with an absolute lower-case `http://` or `https://` `href`) renders through the secure renderer, so its host reaches the `style-src` directive of the Content Security Policy
- One element that fails to render (an inline script with an array attribute, an attribute value with no text form) is logged with its key and left out; before, it aborted the whole head render and the page shipped with no head elements at all
- The head placeholder is always removed: with nothing when there is nothing to inject or rendering failed. 2.3.5 had brought back the early return that 2.3.3 removed
- PHP 8.5: the block cache path no longer calls `setAccessible()`, which is deprecated there and which Magento's error handler turns into an exception; on PHP 8.5 no head element of a cached block was saved or restored
- A cacheable block that re-adds or replaces an element another block added first now stores it in its own cache entry; before, the element was missing on a page where that block was a cache hit and the other block absent
- Nothing is stored or restored while the `block_html` cache type is disabled, or for a block whose cache lifetime is null
- A cached element whose class has no registered factory (a DI preference on an element class, a subclass) is recreated through its element type instead of being dropped; an element that cannot be recreated is logged
- A restored element keeps its factory's default sort order (style 10) instead of 100
- The serialization strategy with the higher priority wins for a class or a type, as the strategy contract states; the lower one used to win
- Script and style attributes follow the same rule as link and meta: `false` and `null` leave the attribute out, `true` renders the boolean form (`async=""`). Before, `'async' => false` rendered `async=""`, which turns async on
- Element data no longer passes through the object manager's argument array, which in developer mode read a nested `instance` key as a class to build and an `argument` key as a configuration reference; both modes now render the same
- The same tag added two ways (`addExternalScript($src)` and `addScript(['src' => $src])`, `addStylesheet()` and `addLink()`, `SRC` and `src`) gets one key and renders once
- `getRenderedElements()` returns the render order
- Attribute names are stored in lower case, so `SRC` and `src` are one attribute and the content security processor finds the URL
- Array and `\Stringable` attribute values survive the block cache: arrays come back as they were, a `\Stringable` as its text, also on an element whose class has no strategy of its own
- A response that is not an HTTP response is passed through untouched instead of raising a `TypeError`

### Changed
- **BREAKING**: The element constructors take only the renderers the element uses; `AbstractHeadElement` takes none:
  - `AbstractHeadElement(array $attributes = [], int $sortOrder = 100)`
  - `MetaElement(AttributeRendererInterface $attributeRenderer, array $attributes = [], int $sortOrder = 100)`
  - `ScriptElement(AttributeRendererInterface $attributeRenderer, SecureHtmlRenderer $secureHtmlRenderer, array $attributes = [], ?string $content = null, int $sortOrder = 100)`
  - `StyleElement(SecureHtmlRenderer $secureHtmlRenderer, array $attributes = [], ?string $content = null, int $sortOrder = 10)`
  - `LinkElement(AttributeRendererInterface $attributeRenderer, SecureHtmlRenderer $secureHtmlRenderer, array $attributes = [], int $sortOrder = 100)`
- **BREAKING**: A block's cache entry in the block HTML cache now ends with a comment, `<!--hryvinskyi-head-elements:1:`, a 128-bit HMAC of the payload, `:`, the base64 of the elements' JSON and `-->`, written even when the block added no elements. The JSON keeps slashes and non-ASCII text unescaped and names an element's class only when its element type would not find the same factory, so a block's one entry is about the size of the HTML entry and the element entry 3.x wrote for it together. An entry without a valid comment, which is every entry 3.x wrote, counts as a miss: the block renders again under the lock and the entry is rewritten, so the upgrade needs no flush
- A block's head elements are stored and restored by a `before` plugin on `Magento\Framework\Cache\LockGuardedCacheLoader::lockedLoadData()`, declared in the global `etc/di.xml` so it also runs outside the storefront. It wraps the loader, collector and saver of a load whose lock name is a block cache key, that is, it starts with one of the prefixes listed in the plugin's `blockCacheKeyPrefixes` argument (the framework's `BLOCK_` and `CUSTOM_BLOCK_`, and the CMS module's `CMS_BLOCK` prefixes; a module whose blocks define another prefix adds it there), and leaves every other load that goes through that loader, the configuration cache among them, untouched. The module no longer observes `view_block_abstract_to_html_before` and `view_block_abstract_to_html_after`
- **BREAKING**: A remote stylesheet is written by the secure renderer, so its tag is `<link href="https://fonts.example.com/a.css" rel="stylesheet" />`; a `true` attribute prints as `crossorigin=""`, not as a bare `crossorigin`. Only a lower-case `http://` or `https://` `href` with a host qualifies: `HTTPS://…`, a relative or protocol-relative URL, other `rel` values, a link with an array attribute, and the preload of a font or script render as text, as before
- **BREAKING**: `HeadTagManagerInterface` declares `getAllElements()` and `clear()`; `HeadTagManager` takes a `SerializerInterface` and a `LoggerInterface`
- `HeadElementSerializer::serialize()` leaves out an element's `type` (its class) when its `short_type` finds the same factory; `unserialize()` reads such an entry through the element type
- A generated key encodes an identity of valid text as it is, and walks it to replace invalid UTF-8 only when the encoder refuses it or it holds an array value
- **BREAKING**: `AbstractSerializationStrategy::__construct()` takes a `StorableAttributesInterface` after the factory registry; a custom strategy with its own constructor passes it on
- Requires `magento/module-cms`, whose block cache key prefixes the plugin lists, in place of `magento/module-store`, which nothing uses any more
- **BREAKING**: element factories call the generated factory without data and apply attributes, content and sort order through the element's setters; a custom factory extending `AbstractHeadElementFactory` implements `createElement()` and `getDefaultSortOrder()` instead of `create()`
- **BREAKING**: Attribute values are typed `string|int|float|bool|\Stringable|array|null` in `setAttribute()`, `getAttribute()` and `setAttributes()`; a subclass that overrides one of them with the 3.x `string` types no longer loads. Arrays render on meta and on a link or an external script written as text (Base's data and class forms), and an inline script or a style refuses them. A remote link or script with an array attribute is written as text, so its host does not reach the policy and has to be whitelisted
- `StyleElement`'s own default sort order is 10, the same as its factory's
- Generated keys are a SHA-256 of a canonical JSON form
- Requires `magento/module-page-cache`, whose result plugin this module orders itself before
- The `Api/` interfaces are marked `@api`; PHPStan level 10 covers the whole module and runs on an uncompiled install
- `magento/module-csp` is suggested, and a development dependency for the integration test of the remote stylesheet

### Removed
- **BREAKING**: `HeadElementTrackerInterface`, `BlockHeadElementCacheInterface` and `BlockCacheDetectorInterface`, with `HeadElementTracker`, `BlockHeadElementCache` and `BlockCacheDetector`, and the two block observers. The separate `hhe_` cache entries are no longer read or written
- **BREAKING**: `AbstractHeadElement::getSecureHtmlRenderer()` and `attributesToString()`. A subclass holds the renderer it needs itself
- **BREAKING**: `HeadElementCacheStrategyInterface`, `HeadElementCacheStrategy`, `NullCacheStrategy` and `Model\Cache\Type`, unused since 2.2.0; `Type` pointed at the `head_tag_manager` cache type removed in 2.3.0

### Upgrading from 3.x
- Nothing to flush. The first request after the upgrade renders each cacheable block once, as after a `block_html` flush, and rewrites its entry; the `hhe_` entries 3.x left behind are never read and expire with the block cache
- A deploy whose old processes keep serving against a shared cache (an atomic release switch on a shared Redis) should run `bin/magento cache:clean block_html full_page` after the new release is active. While both versions run they overwrite each other's entries: 3.x neither reads nor strips the comment and finds no `hhe_` entry, so pages it renders, and the full page cache stores, lack the elements of cached blocks
- Rolling back to 3.x needs `bin/magento cache:clean block_html full_page` once 3.x is active, for the same reason
- A subclass that overrides `setAttribute()`, `getAttribute()` or `setAttributes()` takes the wider value types
- A custom serialization strategy with its own constructor passes a `StorableAttributesInterface` to `parent::__construct()` after the factory registry
- A custom element passes on the renderers it uses and drops the two calls that are gone:
  ```php
  public function __construct(
      private readonly AttributeRendererInterface $attributeRenderer,
      array $attributes = [],
      int $sortOrder = 100
  ) {
      parent::__construct($attributes, $sortOrder);
  }

  public function render(): string
  {
      return '<custom-element' . $this->attributeRenderer->render($this->getAttributes()) . '>';
  }
  ```
- A custom element factory only changes where it extends `AbstractHeadElementFactory`, as described under Changed. The generated factory of an element supplies its renderers
- Code that implemented or called the removed interfaces has nothing to move to; the elements now travel with the block's HTML

## [3.0.0] - 2026-11-07

### Changed
- **BREAKING**: Requires `hryvinskyi/magento2-base` `^3.0`; the module is declared in the `sequence` of `etc/module.xml`
- **BREAKING**: `AbstractHeadElement::__construct()` takes `Hryvinskyi\Base\Api\Html\AttributeRendererInterface` as its second parameter, before `$attributes`. `ScriptElement` and `StyleElement` take and pass it too, and a subclass of any element has to do the same
- **BREAKING**: `LinkElementFactory`, `MetaElementFactory`, `ScriptElementFactory` and `StyleElementFactory` take the element's generated factory in place of `SecureHtmlRenderer`, so an element now comes from the object manager. Their `create()` data and `getElementType()` / `getElementClassName()` are unchanged
- Attributes render through `AttributeRendererInterface` in place of the static `Html::renderTagAttributes()`; the rendered HTML is byte-identical for string and `true` values. A `null` value differs: Base 2 rendered it as a bare attribute, Base 3 leaves it out. In developer mode the object manager also reads an `attributes` array holding a key named `argument` as a configuration reference and empties it (fixed in the next release)
- Elements with a plugin keep working in the block head cache: a cached interceptor's class is read as the class it wraps, which is the one that has a factory
- **BREAKING**: `HeadElementInterface` declares `getAttributes()`, which the serializers already called; an implementation outside this module has to add it
- Declares `magento/module-store`, which the block observer already used
- An attribute value without a text form now raises `Hryvinskyi\Base\Exception\InvalidParamException`

## [2.3.5] - 2026-01-27

### Added
- **Sort Order Support**: Added `sortOrder` property to head elements for explicit control over tag rendering order
  - New `getSortOrder(): int` method in `HeadElementInterface`
  - New `getSortOrder()` and `setSortOrder()` methods in `AbstractHeadElement`
  - Elements are sorted in ascending order (lower values render first)
  - Default sort order is 100 for all element types
  - Stable sort preserves insertion order for elements with equal sort order

### Changed
- Updated all element constructors to accept optional `$sortOrder` parameter
- Updated all element factories to extract `sort_order` from `$data` array
- Updated `HeadTagManager::render()` to sort elements before rendering
- Updated serialization to include `sort_order` in cached data

## [2.3.4] - 2025-10-25

### Added
- Hyvä theme compatibility: the placeholder is added to `head.additional` in the `default_hyva` layout handle

## [2.3.3] - 2025-10-08

### Updated
- **Updated**: Disable showing empty placeholder elements in the head section when no head elements are added

## [2.3.2] - 2025-10-06

### Added
- **Added**: Rendering html attributes like defer, async without value for link and meta tags; script tags render the boolean form as `defer=""`

## [2.3.1] - 2025-10-02

### Fixed
- **Fixed**: Possible cache type (added \Magento\Framework\App\CacheInterface type)

## [2.3.0] - 2025-09-29

### Removed
- **BREAKING**: Removed custom `head_tag_manager` cache type from `etc/cache.xml`
  - No longer need to manage a separate cache type in Magento cache management
  - Head elements now use each block's native cache infrastructure

### Changed
- **BREAKING**: `BlockHeadElementCache` constructor signature changed
  - Removed dependency on cache class

### Fixed
- Cache synchronization issues where head elements could expire before or after block cache
- Memory and storage efficiency by removing duplicate cache layer
- Cache invalidation now properly follows block cache invalidation

### Updated
- Unit tests updated to reflect new constructor and caching behavior

## [2.2.3] - 2025-09-29

### Fixed
- **Cache Synchronization**: Fixed critical cache synchronization issue between block cache and head elements cache
  - Head elements cache now uses module's long cache lifetime (1 year) instead of block's cache lifetime

## [2.2.2] - 2025-09-28

### Updated
- **Updated** using caching type, now we always use block cache instead of default context cache

### Fixed
- **Cache Synchronization**: Fixed critical cache synchronization issue between block cache and head elements cache
  - Head elements cache now uses module's long cache lifetime (1 year) instead of block's cache lifetime

## [2.2.1] - 2025-09-18

### Fixed
- **Fixed**: Fixed rendering when slave redis cache for block enabled

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
