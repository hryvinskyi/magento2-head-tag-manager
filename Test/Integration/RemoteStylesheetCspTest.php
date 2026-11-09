<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\HeadTagManager\Test\Integration;

use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * A remote stylesheet's or script's host reaches the content security policy the storefront sends, and a local one
 * stays in the head
 *
 * The head is rendered through the container's head tag manager, and the policies are read from the shared dynamic
 * collector the content security processor of the secure renderer writes to.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class RemoteStylesheetCspTest extends TestCase
{
    private const REMOTE = 'https://fonts.example.com/a.css';
    private const RELATIVE = '/css/local.css';
    private const UPPER_CASE_SCHEME = 'HTTPS://upper.example.com/b.css';
    private const REMOTE_SCRIPT = 'https://scripts.example.com/a.js';
    private const RELATIVE_SCRIPT = '/media/js/local.js';
    private const PROTOCOL_RELATIVE_SCRIPT = '//cdn.example.com/x.js';

    protected function setUp(): void
    {
        $moduleManager = Bootstrap::getObjectManager()->get(ModuleManager::class);
        self::assertInstanceOf(ModuleManager::class, $moduleManager);
        if (!$moduleManager->isEnabled('Magento_Csp')) {
            self::markTestSkipped('Magento_Csp is not enabled, so no processor adds hosts to a policy.');
        }
    }

    /**
     * The remote stylesheet's host is added to `style-src`, and the container's secure renderer closes its tag with
     * ` />`; a relative stylesheet and one whose scheme is written in upper case are written as text, stay in the
     * head and add no host
     */
    public function testOnlyTheRemoteStylesheetAddsItsHostToTheStyleDirective(): void
    {
        $collector = Bootstrap::getObjectManager()->get(DynamicCollector::class);
        self::assertInstanceOf(DynamicCollector::class, $collector);
        $hostsBefore = $this->styleHosts($collector);

        $manager = Bootstrap::getObjectManager()->get(HeadTagManagerInterface::class);
        self::assertInstanceOf(HeadTagManagerInterface::class, $manager);
        $manager->addStylesheet(self::REMOTE, [], 'remote_css');
        $manager->addStylesheet(self::RELATIVE, [], 'relative_css');
        $manager->addStylesheet(self::UPPER_CASE_SCHEME, [], 'upper_case_css');
        $head = $manager->render();

        self::assertStringContainsString('<link href="' . self::REMOTE . '" rel="stylesheet" />', $head);
        self::assertStringContainsString('<link href="' . self::RELATIVE . '" rel="stylesheet">', $head);
        self::assertStringContainsString('<link href="' . self::UPPER_CASE_SCHEME . '" rel="stylesheet">', $head);
        self::assertSame(
            ['https://fonts.example.com'],
            array_values(array_diff($this->styleHosts($collector), $hostsBefore))
        );
        self::assertNotContains('https://upper.example.com', $this->styleHosts($collector));
    }

    /**
     * The remote external script's host is added to `script-src`; a relative and a protocol-relative one, which the
     * processor reads no host from and would refuse, are written as text and stay in the head
     */
    public function testOnlyTheRemoteExternalScriptAddsItsHostToTheScriptDirective(): void
    {
        $collector = Bootstrap::getObjectManager()->get(DynamicCollector::class);
        self::assertInstanceOf(DynamicCollector::class, $collector);
        $hostsBefore = $this->hosts($collector, 'script-src');

        $manager = Bootstrap::getObjectManager()->get(HeadTagManagerInterface::class);
        self::assertInstanceOf(HeadTagManagerInterface::class, $manager);
        $manager->addExternalScript(self::REMOTE_SCRIPT, ['defer' => true], 'remote_js');
        $manager->addExternalScript(self::RELATIVE_SCRIPT, ['defer' => true], 'relative_js');
        $manager->addExternalScript(self::PROTOCOL_RELATIVE_SCRIPT, [], 'protocol_relative_js');
        $rendered = $manager->getRenderedElements();

        self::assertSame(['remote_js', 'relative_js', 'protocol_relative_js'], array_keys($rendered));
        self::assertStringContainsString('src="' . self::REMOTE_SCRIPT . '"', $rendered['remote_js']);
        self::assertSame('<script src="' . self::RELATIVE_SCRIPT . '" defer></script>', $rendered['relative_js']);
        self::assertSame(
            '<script src="' . self::PROTOCOL_RELATIVE_SCRIPT . '"></script>',
            $rendered['protocol_relative_js']
        );
        self::assertSame(
            ['https://scripts.example.com'],
            array_values(array_diff($this->hosts($collector, 'script-src'), $hostsBefore))
        );
    }

    /**
     * Get the hosts the page's `style-src` directive has collected so far
     *
     * @param DynamicCollector $collector the collector the content security processor writes to
     * @return list<string>
     */
    private function styleHosts(DynamicCollector $collector): array
    {
        return $this->hosts($collector, 'style-src');
    }

    /**
     * Get the hosts one fetch directive of the page has collected so far
     *
     * @param DynamicCollector $collector the collector the content security processor writes to
     * @param string $directive the directive, such as `script-src`
     * @return list<string>
     */
    private function hosts(DynamicCollector $collector, string $directive): array
    {
        $hosts = [];
        foreach ($collector->collect() as $policy) {
            if ($policy instanceof FetchPolicy && $policy->getId() === $directive) {
                array_push($hosts, ...array_values($policy->getHostSources()));
            }
        }

        return $hosts;
    }
}
