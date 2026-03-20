<?php

declare(strict_types=1);

namespace RTfirst\PageGraph\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use RTfirst\PageGraph\Service\PageGraphDataProvider;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\AdditionalJavaScriptInterface;
use TYPO3\CMS\Dashboard\Widgets\EventDataInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

class PageGraphWidget implements WidgetInterface, RequestAwareWidgetInterface, EventDataInterface, AdditionalJavaScriptInterface, AdditionalCssInterface
{
    private ?ServerRequestInterface $request = null;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly BackendViewFactory $backendViewFactory,
        private readonly PageGraphDataProvider $dataProvider,
        private readonly array $options = [],
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        if ($this->request === null) {
            return '';
        }
        $view = $this->backendViewFactory->create($this->request, ['rtfirst/page-graph']);
        $view->assignMultiple([
            'configuration' => $this->configuration,
        ]);
        return $view->render('Widget/PageGraphWidget');
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        if ($this->request === null) {
            return [];
        }
        $includeContent = (bool) ($this->options['includeContent'] ?? true);
        return [
            'graphData' => $this->dataProvider->getGraphData(
                $this->request,
                $includeContent,
            ),
        ];
    }

    /**
     * @return string[]
     */
    public function getJsFiles(): array
    {
        return [
            'EXT:page_graph/Resources/Public/JavaScript/Vendor/force-graph.min.js',
            'EXT:page_graph/Resources/Public/JavaScript/PageGraphWidget.js',
        ];
    }

    /**
     * @return string[]
     */
    public function getCssFiles(): array
    {
        return [
            'EXT:page_graph/Resources/Public/Css/PageGraphWidget.css',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
