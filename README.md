# Adobe Commerce / Magento 2 Head Tag Manager Module

[![Latest Stable Version](https://poser.pugx.org/hryvinskyi/magento2-head-tag-manager/v/stable)](https://packagist.org/packages/hryvinskyi/magento2-head-tag-manager)
[![Total Downloads](https://poser.pugx.org/hryvinskyi/magento2-head-tag-manager/downloads)](https://packagist.org/packages/hryvinskyi/magento2-head-tag-manager)
[![License](https://poser.pugx.org/hryvinskyi/magento2-head-tag-manager/license)](https://packagist.org/packages/hryvinskyi/magento2-head-tag-manager)

This module provides a robust solution for managing HTML head elements in Adobe Commerce / Magento 2 applications.
This module allows you to dynamically add, modify, and render various HTML head elements like meta tags, stylesheets, scripts, and other elements.

### Features:
- **CSP Compatible**: Full support for Magento Content Security Policy
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

// Add stylesheets (CSP compatible)
$headManager->addStylesheet($block->getViewFileUrl('css/some-custom.css'));

// Add external scripts (CSP compatible)
$headManager->addExternalScript($block->getViewFileUrl('js/some-custom.js'));

// Add inline script (CSP compatible)
$headManager->addInlineScript('console.log("Hello, world!");');

// Add inline styles (CSP compatible)
$headManager->addStyle('body { background-color: #f0f0f0; }');
```

## Architecture

### Cache Strategy

This module is not recommended for use on cached blocks, but this module provides a cache-aware architecture to handle head elements on cached blocks, and correctly render them.

### Common Methods

- `addMetaName(string $name, string $content, ?string $key = null)` - Add meta tag with name attribute
- `addMetaProperty(string $property, string $content, ?string $key = null)` - Add meta tag with property attribute
- `addCharset(string $charset = 'UTF-8')` - Add charset meta tag
- `addStylesheet(string $href, array $attributes = [], ?string $key = null)` - Add stylesheet link
- `addInlineStyle(string $content, array $attributes = [], ?string $key = null)` - Add inline style
- `addExternalScript(string $src, array $attributes = [], ?string $key = null)` - Add external script
- `addInlineScript(string $content, array $attributes = [], ?string $key = null)` - Add inline script

## Advanced Usage

### Custom Elements

To add support for a new head element type:

1. **Create Element Class**: Implement `HeadElementInterface`
2. **Create Factory**: Extend `AbstractHeadElementFactory`
3. **Create Strategy**: Implement `HeadElementSerializationStrategyInterface`
4. **Register via DI**: Add to `di.xml` configuration

### Example Custom Element

```php
class CustomElement implements HeadElementInterface
{
    private $attributes;
    private $content;

    public function __construct(array $attributes, string $content)
    {
        $this->attributes = $attributes;
        $this->content = $content;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * Render the head element as HTML
     * @return string The HTML representation of the element
     */
    public function render(): string
    {
        $attributes = '';
        foreach ($this->attributes as $key => $value) {
            $attributes .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars($value));
        }
        return sprintf('<custom-element%s>%s</custom-element>', $attributes, htmlspecialchars($this->content));
    }
}
```

### Custom Factory

```php
class CustomElementFactory extends extends AbstractHeadElementFactory
{
    /**
     * @inheritDoc
     */
    public function create(array $data = []): HeadElementInterface
    {
        return new CustomElement(
            $data['attributes'] ?? [],
            $data['content'] ?? ''
        );
    }

    /**
     * @inheritDoc
     */
    public function getElementType(): string
    {
        return 'custom';
    }

    /**
     * @inheritDoc
     */
    public function getElementClassName(): string
    {
        return CustomElement::class;
    }
}
```

### Custom Serialization Strategy
```php
class CustomElementSerializationStrategy implements HeadElementSerializationStrategyInterface
{
    public function serialize(HeadElementInterface $element, string $key): array
    {
        return [
            'type' => get_class($element),
            'short_type' => $element->getElementType(),
            'attributes' => $element->getAttributes(),
            'content' => $element->getContent()
        ];
    }

    public function getElementType(): string
    {
        return 'custom';
    }
}
```

### Custom Factory Registration
To register your custom element and factory, add the following to your `di.xml`:

```xml
<type name="Hryvinskyi\HeadTagManager\Factory\HeadElementFactoryRegistry">
    <arguments>
        <argument name="factories" xsi:type="array">
            <item name="custom" xsi:type="object">Vendor\Module\Factory\CustomElementFactory</item>
        </argument>
    </arguments>
</type>
<type name="Hryvinskyi\HeadTagManager\Strategy\SerializationStrategyRegistry">
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

### Element Serialization

Custom serialization strategies allow for element-specific handling:

```php
class CustomElementStrategy implements HeadElementSerializationStrategyInterface
{
    public function serialize(HeadElementInterface $element, string $key): array
    {
        return [
            'type' => get_class($element),
            'short_type' => $this->getElementType(),
            'attributes' => $element->getAttributes(),
            'content' => $this->extractContent($element)
        ];
    }
}
```

## Integration

The module automatically injects head elements at the `<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->` placeholder in your HTML output.

## Version History

See [CHANGELOG.md](CHANGELOG.md) for detailed version history and migration notes.

## Testing

The module includes comprehensive test coverage:

- **Unit Tests**: All core classes and interfaces
- **Integration Tests**: End-to-end functionality
- **Cache Tests**: Cache-aware functionality

Run tests with:
```bash
vendor/bin/phpunit vendor/hryvinskyi/magento2-head-tag-manager/Test/Unit
```

## Contributing

Contributions are welcome! Please ensure:
- All tests pass
- New features include tests
- Code follows SOLID principles
- Documentation is updated

## License

Copyright © 2025 Volodymyr Hryvinskyi. All rights reserved.