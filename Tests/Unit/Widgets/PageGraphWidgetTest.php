<?php

declare(strict_types=1);

namespace RTfirst\PageGraph\Tests\Unit\Widgets;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RTfirst\PageGraph\Service\PageGraphDataProvider;
use RTfirst\PageGraph\Widgets\PageGraphWidget;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;

final class PageGraphWidgetTest extends TestCase
{
    private WidgetConfigurationInterface $configurationMock;
    private BackendViewFactory $backendViewFactoryStub;
    private PageGraphDataProvider $dataProviderMock;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(WidgetConfigurationInterface::class);
        // BackendViewFactory is final — create uninitialized instance via reflection
        // (no test calls renderWidgetContent with a real request, so this is safe)
        $reflection = new \ReflectionClass(BackendViewFactory::class);
        $this->backendViewFactoryStub = $reflection->newInstanceWithoutConstructor();
        $this->dataProviderMock = $this->createMock(PageGraphDataProvider::class);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createSubject(array $options = []): PageGraphWidget
    {
        return new PageGraphWidget(
            $this->configurationMock,
            $this->backendViewFactoryStub,
            $this->dataProviderMock,
            $options,
        );
    }

    #[Test]
    public function getJsFilesReturnsCorrectPaths(): void
    {
        $subject = $this->createSubject();
        $jsFiles = $subject->getJsFiles();

        self::assertCount(2, $jsFiles);
        self::assertSame('EXT:page_graph/Resources/Public/JavaScript/Vendor/force-graph.min.js', $jsFiles[0]);
        self::assertSame('EXT:page_graph/Resources/Public/JavaScript/PageGraphWidget.js', $jsFiles[1]);
    }

    #[Test]
    public function getCssFilesReturnsCorrectPaths(): void
    {
        $subject = $this->createSubject();
        $cssFiles = $subject->getCssFiles();

        self::assertCount(1, $cssFiles);
        self::assertSame('EXT:page_graph/Resources/Public/Css/PageGraphWidget.css', $cssFiles[0]);
    }

    #[Test]
    public function getOptionsReturnsConstructorOptions(): void
    {
        $options = ['includeContent' => false, 'custom' => 'value'];
        $subject = $this->createSubject($options);

        self::assertSame($options, $subject->getOptions());
    }

    #[Test]
    public function renderWidgetContentReturnsEmptyWithoutRequest(): void
    {
        $subject = $this->createSubject();

        self::assertSame('', $subject->renderWidgetContent());
    }

    #[Test]
    public function getEventDataReturnsEmptyWithoutRequest(): void
    {
        $subject = $this->createSubject();

        self::assertSame([], $subject->getEventData());
    }

    #[Test]
    public function getEventDataPassesIncludeContentOption(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $graphData = ['nodes' => [], 'links' => [], 'meta' => ['totalPages' => 0, 'totalContent' => 0, 'totalReferences' => 0]];

        $this->dataProviderMock->expects(self::once())
            ->method('getGraphData')
            ->with($request, false)
            ->willReturn($graphData);

        $subject = $this->createSubject(['includeContent' => false]);
        $subject->setRequest($request);

        $result = $subject->getEventData();

        self::assertSame(['graphData' => $graphData], $result);
    }

    #[Test]
    public function getEventDataDefaultsToIncludeContent(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $graphData = ['nodes' => [], 'links' => [], 'meta' => ['totalPages' => 0, 'totalContent' => 0, 'totalReferences' => 0]];

        $this->dataProviderMock->expects(self::once())
            ->method('getGraphData')
            ->with($request, true)
            ->willReturn($graphData);

        $subject = $this->createSubject();
        $subject->setRequest($request);

        $result = $subject->getEventData();

        self::assertSame(['graphData' => $graphData], $result);
    }
}
