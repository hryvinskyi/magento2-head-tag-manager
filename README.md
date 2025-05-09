# Hryvinskyi_HeadTagManager

A Magento 2 module for managing HTML head tags. This module allows you to dynamically add, modify, and render various HTML head elements like meta tags, stylesheets, scripts, and other elements.

## Overview

The HeadTagManager module provides a flexible API for managing HTML head elements in Magento 2 applications. It allows you to:

- Add preconnect and prefetch links
- Add link elements (stylesheets, canonical, etc.)
- Add inline and external scripts
- Add inline styles
- Supporting Magento CSP (Content Security Policy)

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

```php
<?php
/** @var \Hryvinskyi\HeadTagManager\ViewModel\HeadTagManagerViewModel $headTagManagerViewModel */
$headTagManagerViewModel = $block->getData('head_tag_manager_view_model');
$headManager = $headTagManagerViewModel->getManager();


// Add stylesheets (CSP compatible)
$headManager->addStylesheet($block->getViewFileUrl('css/some-custom.css'));

// Add external scripts (CSP compatible)
$headManager->addExternalScript($block->getViewFileUrl('js/some-custom.js'));

// Add inline script (CSP compatible)
$headManager->addInlineScript('console.log("Hello, world!");');

// Add inline styles (CSP compatible)
$headManager->addStyle('body { background-color: #f0f0f0; }');
```

## API Reference

### Main Interfaces

- `HeadTagManagerInterface` - Main service for managing head elements
- `HeadElementInterface` - Interface for all head elements

### Head Element Types

- `MetaElement` - HTML meta tags
- `LinkElement` - HTML link tags (stylesheets, favicons, etc.)
- `ScriptElement` - External and inline scripts
- `StyleElement` - Inline CSS styles

### Common Methods

- `addMetaName(string $name, string $content, ?string $key = null)` - Add meta tag with name attribute
- `addMetaProperty(string $property, string $content, ?string $key = null)` - Add meta tag with property attribute
- `addCharset(string $charset = 'UTF-8')` - Add charset meta tag
- `addStylesheet(string $href, array $attributes = [], ?string $key = null)` - Add stylesheet link
- `addInlineStyle(string $content, array $attributes = [], ?string $key = null)` - Add inline style
- `addExternalScript(string $src, array $attributes = [], ?string $key = null)` - Add external script
- `addInlineScript(string $content, array $attributes = [], ?string $key = null)` - Add inline script

## Integration

The module automatically injects head elements at the `<!-- {{HRYVINSKYI:PLACEHOLDER:HEAD_ADDITIONAL}} -->` placeholder in your HTML output.

## License

Copyright © 2025 Volodymyr Hryvinskyi. All rights reserved.