<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\View;

use Fred\Infrastructure\View\ViewRenderer;
use Tests\TestCase;

final class ViewRendererTest extends TestCase
{
    public function testRendersTemplateInsideDefaultLayout(): void
    {
        $view = new ViewRenderer($this->basePath('resources/views'));

        $output = $view->render('pages/home.php', [
            'pageTitle' => 'Welcome',
            'environment' => 'testing',
            'baseUrl' => 'http://example.test',
            'navSections' => [],
            'currentUser' => null,
        ]);

        $this->assertStringContainsString('<title>Welcome</title>', $output);
        $this->assertStringContainsString('Fred forum engine', $output);
        $this->assertStringContainsString('testing', $output);
    }
}
