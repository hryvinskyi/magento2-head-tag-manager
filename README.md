# Adobe Commerce / Magento 2 Head Tag Manager Module

[![Latest Stable Version](https://poser.pugx.org/hryvinskyi/magento2-head-tag-manager/v/stable)](https://packagist.org/packages/hryvinskyi/magento2-head-tag-manager)
[![Total Downloads](https://poser.pugx.org/hryvinskyi/magento2-head-tag-manager/downloads)](https://packagist.org/packages/hryvinskyi/magento2-head-tag-manager)
[![License](https://poser.pugx.org/hryvinskyi/magento2-head-tag-manager/license)](https://packagist.org/packages/hryvinskyi/magento2-head-tag-manager)

This module provides a robust solution for managing HTML head elements in Adobe Commerce / Magento 2 applications.
This module allows you to dynamically add, modify, and render various HTML head elements like meta tags, stylesheets, scripts, and other elements.

### Features:
- **CSP Compatible**: scripts and inline styles render through Magento's secure renderer, which adds the nonce or hash and whitelists a script's remote host. So does a remote stylesheet, which puts its host into `style-src`; see [Content Security Policy](#content-security-policy)
- **Performance Optimized**: Efficient element management and rendering

### Key Features

- Add preconnect and prefetch links
- Add link elements (stylesheets, canonical, etc.)
- Add inline and external scripts with CSP support
- Add inline styles with CSP support

## Installation

### Composer Installation

```bash
composer require hryvinskyi/magento2-head-tag-manager
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Manual Installation

1. Create directory `app/code/Hryvinskyi/HeadTagManager`
2. Download and extract module contents to this directory
3. Enable the module:
4. 
```bash
bin/magento module:enable Hryvinskyi_HeadTagManager
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Usage

### Via ViewModel

```xml
<block name="some.block" template="....">
    <arguments>
        <argument name="head_tag_manager_view_model" xsi:type="object">Hryvinskyi\HeadTagManager\ViewModel\HeadTagManagerViewModel</argument>
    </arguments>
</block>
```

```PHP
<?php
/** @var \Hryvinskyi\HeadTagManager\ViewModel\HeadTagManagerViewModel $headTagManagerViewModel */
$headTagManagerViewModel = $block->getData('head_tag_manager_view_model');
$headManager = $headTagManagerViewModel->getManager();

// Add preconnect link
$headTagManagerViewModel->getManager()->addLink([
    'rel' => 'preload',
    'href' => 'https://example.com/image.jpg',
    'as' => 'image',
]);

// Add prefetch link
$headTagManagerViewModel->getManager()->addLink([
    'rel' => 'prefetch',
    'href' => '/landing-page',
]);

// Add DNS prefetch link
$headTagManagerViewModel->getManager()->addLink([
    'rel' => 'dns-prefetch',
    'href' => 'https://fonts.googleapis.com/',
]);

// Add stylesheets (the host of a remote one reaches the CSP's style-src)
$headManager->addStylesheet($block->getViewFileUrl('css/some-custom.css'));

// Add external scripts (CSP compatible)
$headManager->addExternalScript($block->getViewFileUrl('js/some-custom.js'));

// Add inline script (CSP compatible)
$headManager->addInlineScript('console.log("Hello, world!");');

// Add inline styles (CSP compatible)
$headManager->addInlineStyle('body { background-color: #f0f0f0; }');
```

## Architecture

### Cache Strategy

A block's head elements are stored inside the block's own block HTML cache entry, so they come back with its HTML when the block is served from the cache and its code does not run.

When a cacheable block renders, the elements it adds (nested blocks included) are appended to its HTML as one comment: `<!--hryvinskyi-head-elements:1:`, a 128-bit HMAC of the payload made with the installation's crypt key, `:`, the base64 of their JSON, and `-->`. The JSON names each element's type (`short_type`), and its class only when that type would not find the same factory. The comment is written even when the block added no elements, and an element whose data cannot be stored is left out of it with a warning. Magento's own save action stores the entry under its cache lock, with the block's key, tags and lifetime. There is no second entry that could go missing, be evicted on its own, or be written after another request has read the HTML.

On a hit the comment is cut off the HTML and the elements go back into the manager. Elements that `_afterToHtml()` adds are added on a hit and on a miss alike, and are never stored.

An entry without a valid comment (written by 3.x, while the module was disabled, or ending with text that only looks like the comment) counts as a miss. The block renders again under the lock and the entry is rewritten, so such an entry is never served without its elements and upgrading needs no flush.

A `before` plugin on `Magento\Framework\Cache\LockGuardedCacheLoader::lockedLoadData()` does this. It is declared in the global `etc/di.xml` and runs in every area, so a block rendered outside the storefront (in an e-mail sent by cron, for instance) strips the comment too. The same loader serves the configuration cache, so the plugin touches only a load whose lock name is a block cache key: it starts with one of the prefixes in the plugin's `blockCacheKeyPrefixes` argument, which lists the framework's `BLOCK_` and `CUSTOM_BLOCK_` and the CMS module's `CMS_BLOCK` prefixes. A module whose block class defines its own `CACHE_KEY_PREFIX` adds it as an item of that argument in its `di.xml`. A block loads through it when the `block_html` cache type is enabled and the block's cache lifetime is not null; while the cache type is disabled nothing is stored or restored. A lifetime of `0` or `false` also loads through it: the comment is built and Magento then saves nothing, so such a block is better given a null lifetime.

If the module is removed or disabled without `bin/magento module:disable`, which cleans the caches, clean the `block_html` cache yourself. Otherwise the comment keeps printing in cached block output until the entries expire. It is an HTML comment, so the page shows nothing.

An element that fails to render is logged with its key and left out; the rest of the head still renders.

Attribute values: `true` renders the boolean form, `false` and `null` leave the attribute out, numbers and `Stringable` objects render as text. Arrays render on meta and on a link or an external script written as text (data and class forms); an inline script or a style refuses them. A remote link or script with an array attribute is written as text, so its host has to be in a `csp_whitelist.xml`.

### Content Security Policy

Inline scripts, styles and a script whose `src` points at a remote host render through Magento's secure renderer; a script with no content and a relative, protocol-relative or upper-case-scheme `src` is written as text, because the policy's processor would throw on it. So does a link that loads a remote stylesheet render through the secure renderer: its `rel` has the token `stylesheet`, or `preload` with `as="style"`, and its `href` starts with a lower-case `http://` or `https://` and a host. When Magento's Content Security Policy module is enabled, its processor adds that host to `style-src`. The tag comes out as the renderer writes it, for example `<link href="https://fonts.example.com/a.css" rel="stylesheet" />`, and a `true` attribute prints as `crossorigin=""`.

Every other link is written as text and adds nothing to the policy: preloads of fonts and scripts, icons, preconnect and prefetch links, a relative or protocol-relative `href`, an upper-case scheme (`HTTPS://…`), an empty host, and any link with an array attribute. A host such a link needs has to be in a `csp_whitelist.xml`. A relative `href` or an upper-case scheme stays a text link on purpose: the policy's processor throws on it, which would drop the stylesheet from the page.

### Common Methods

- `addMetaName(string $name, string $content, ?string $key = null)` - Add meta tag with name attribute
- `addMetaProperty(string $property, string $content, ?string $key = null)` - Add meta tag with property attribute
- `addCharset(string $charset = 'UTF-8')` - Add charset meta tag
- `addStylesheet(string $href, array $attributes = [], ?string $key = null)` - Add stylesheet link
- `addInlineStyle(string $content, array $attributes = [], ?string $key = null)` - Add inline style
- `addExternalScript(string $src, array $attributes = [], ?string $key = null)` - Add external script
- `addInlineScript(string $content, array $attributes = [], ?string $key = null)` - Add inline script

### Element Sort Order

You can control the rendering order of head elements using the `sort_order` parameter.
Elements are sorted in ascending order (lower values render first). The default sort order is 10 for inline styles and 100 for every other element.

```php
// Critical scripts should load first (sort_order = 1)
$headManager->createElement('script', [
    'attributes' => ['src' => $block->getViewFileUrl('js/critical.js')],
    'sort_order' => 1
]);

// Viewport meta tag with high priority (sort_order = 5)
$headManager->createElement('meta', [
    'attributes' => ['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1'],
    'sort_order' => 5
]);

// Regular stylesheet uses default sort_order (100)
$headManager->addStylesheet($block->getViewFileUrl('css/styles.css'));

// Deferred scripts load later (sort_order = 200)
$headManager->createElement('script', [
    'attributes' => ['src' => $block->getViewFileUrl('js/analytics.js'), 'defer' => true],
    'sort_order' => 200
]);
```

Elements with the same sort order maintain their insertion order (stable sort).

## Advanced Usage

### Custom Elements

To add support for a new head element type:

1. **Create Element Class**: extend `AbstractHeadElement`. Its constructor takes `$attributes` and `$sortOrder` and no renderer; take the renderer your element writes its tag with (`AttributeRendererInterface` for a tag written as text, `SecureHtmlRenderer` for a tag that carries a nonce or hash) and pass the rest on
2. **Create Factory**: extend `AbstractHeadElementFactory`; it creates the element through Magento's generated factory with no data, then applies attributes and sort order through the element's setters
3. **Create Strategy**: extend `AbstractSerializationStrategy`, so the element survives the block cache
4. **Register via DI**: add both to `di.xml`

### Example Custom Element

```php
class CustomElement extends AbstractHeadElement
{
    private ?string $content = null;

    public function __construct(
        private readonly AttributeRendererInterface $attributeRenderer,
        array $attributes = [],
        int $sortOrder = 100
    ) {
        parent::__construct($attributes, $sortOrder);
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function render(): string
    {
        return '<custom-element' . $this->attributeRenderer->render($this->getAttributes()) . '>'
            . htmlspecialchars((string) $this->content, ENT_QUOTES)
            . '</custom-element>';
    }
}
```

### Custom Factory

```php
class CustomElementFactory extends AbstractHeadElementFactory
{
    /**
     * @param \Vendor\Module\Model\CustomElementFactory $elementFactory Magento's generated factory for the element
     */
    public function __construct(
        private readonly \Vendor\Module\Model\CustomElementFactory $elementFactory
    ) {
    }

    protected function createElement(array $data): AbstractHeadElement
    {
        $element = $this->elementFactory->create();
        if (isset($data['content'])) {
            $element->setContent($data['content']);
        }

        return $element;
    }

    protected function getDefaultSortOrder(): int
    {
        return 100;
    }

    public function getElementType(): string
    {
        return 'custom';
    }

    public function getElementClassName(): string
    {
        return CustomElement::class;
    }
}
```

### Custom Serialization Strategy

```php
class CustomElementSerializationStrategy extends AbstractSerializationStrategy
{
    public function getElementType(): string
    {
        return 'custom';
    }

    public function getElementClassName(): string
    {
        return CustomElement::class;
    }

    protected function getAdditionalSerializationData(HeadElementInterface $element): array
    {
        return $element instanceof CustomElement ? ['content' => $element->getContent()] : [];
    }
}
```

A strategy with a constructor of its own passes the factory registry and a `StorableAttributesInterface` on to `parent::__construct()`. When several strategies handle the same class, the one with the highest `getPriority()` is used; among equal priorities, the one registered last.

### Custom Factory Registration
To register your custom element and factory, add the following to your `di.xml`:

```xml
<type name="Hryvinskyi\HeadTagManager\Model\Registry\HeadElementFactoryRegistry">
    <arguments>
        <argument name="factories" xsi:type="array">
            <item name="custom" xsi:type="object">Vendor\Module\Factory\CustomElementFactory</item>
        </argument>
    </arguments>
</type>
<type name="Hryvinskyi\HeadTagManager\Model\Serializer\Strategy\SerializationStrategyRegistry">
    <arguments>
        <argument name="strategies" xsi:type="array">
            <item name="custom" xsi:type="object">Vendor\Module\Strategy\CustomElementSerializationStrategy</item>
        </argument>
    </arguments>
</type>
```

### Usage
To use your custom element, simply call the factory from the `HeadTagManager`:

```php
$headTagManager->createElement('custom', [
    'attributes' => ['data-custom' => 'value'],
    'content' => 'Custom Content'
], 'custom_key');
```

## Integration

The module injects head elements at the `<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->` placeholder in your HTML output, and removes the placeholder when there is nothing to inject.

## Upgrading from 3.x

- Nothing needs flushing. The first request after the upgrade renders each cacheable block once, as after a `block_html` flush, and rewrites its entry. The separate `hhe_` entries of 3.x are no longer read and expire with the block cache.
- A deploy whose old processes keep serving against a shared cache (an atomic release switch on a shared Redis) should run `bin/magento cache:clean block_html full_page` after the new release is active. While both versions run they overwrite each other's entries, and pages the old processes render lack the elements of cached blocks. Rolling back to 3.x needs the same clean once 3.x is active. Otherwise no flush is needed.
- A custom element no longer receives renderers from `AbstractHeadElement`: its constructor takes only `$attributes` and `$sortOrder`, and the element takes the renderer it uses itself (see [Custom Elements](#custom-elements)). `getSecureHtmlRenderer()` and `attributesToString()` are gone.
- The tracker, the block head element cache and the block cache detector interfaces and the two block observers are removed; the elements travel with the block's HTML now.
- A subclass that overrides `setAttribute()`, `getAttribute()` or `setAttributes()` takes the wider value types (`string|int|float|bool|\Stringable|array|null`).
- A custom serialization strategy with its own constructor passes a `StorableAttributesInterface` to `parent::__construct()` after the factory registry.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history and migration notes.

## Testing

Unit tests, from the Magento root:
```bash
vendor/bin/phpunit -c vendor/hryvinskyi/magento2-head-tag-manager/phpunit.xml.dist
```
The `-c` matters: the package's bootstrap provides Magento's generated factories when the installation has not been compiled.

Integration tests are in `Test/Integration`. Magento's integration configuration only collects `app/code/*/*/Test/Integration`, so for a Composer install point a copy of `dev/tests/integration/phpunit.xml` at `vendor/hryvinskyi/magento2-head-tag-manager/Test/Integration`.

Static analysis runs at PHPStan level 10 with the package's `phpstan.neon.dist` from the package directory.

## Contributing

Contributions are welcome! Please ensure:
- All tests pass
- New features include tests
- Code follows SOLID principles
- Documentation is updated

## License

Copyright © 2025 Volodymyr Hryvinskyi. All rights reserved.