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

        $output = $view->render('pages/health.php', [
            'pageTitle' => 'Health Check',
            'environment' => 'testing',
            'baseUrl' => 'http://example.test',
            'sessionId' => 'abc123',
            'activePath' => '/health',
            'currentUser' => null,
        ]);

        $this->assertStringContainsString('<title>Health Check</title>', $output);
        $this->assertStringContainsString('Health Check', $output);
        $this->assertStringContainsString('testing', $output);
    }
}
