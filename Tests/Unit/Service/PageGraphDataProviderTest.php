<?php

declare(strict_types=1);

namespace RTfirst\PageGraph\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use RTfirst\PageGraph\Service\PageGraphDataProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\CMS\Core\Http\Uri;

final class PageGraphDataProviderTest extends TestCase
{
    private ConnectionPool $connectionPoolMock;
    private UriBuilder $uriBuilderMock;
    private ServerRequestInterface $requestMock;

    protected function setUp(): void
    {
        $this->connectionPoolMock = $this->createMock(ConnectionPool::class);
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        $this->uriBuilderMock->method('buildUriFromRoute')->willReturn(new Uri('/typo3/edit'));
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
    }

    private function createSubject(): PageGraphDataProvider
    {
        return new PageGraphDataProvider(
            $this->connectionPoolMock,
            $this->uriBuilderMock,
        );
    }

    /**
     * Creates a QueryBuilder mock that returns the given rows from fetchAllAssociative().
     *
     * @param list<array<string, mixed>> $rows
     */
    private function createQueryBuilderMock(array $rows = []): QueryBuilder
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('neq')->willReturn('1=1');
        $expressionBuilder->method('eq')->willReturn('1=1');
        $expressionBuilder->method('in')->willReturn('1=1');

        $restrictionContainer = $this->createMock(DefaultRestrictionContainer::class);
        $restrictionContainer->method('removeAll')->willReturnSelf();
        $restrictionContainer->method('add')->willReturnSelf();

        $statement = $this->createMock(\Doctrine\DBAL\Result::class);
        $statement->method('fetchAllAssociative')->willReturn($rows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictionContainer);
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturn('?');
        $queryBuilder->method('executeQuery')->willReturn($statement);

        return $queryBuilder;
    }

    /**
     * Sets up $GLOBALS['BE_USER'] with a mock that returns a permission clause.
     */
    private function setUpBackendUser(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('getPagePermsClause')->willReturn('1=1');
        $GLOBALS['BE_USER'] = $backendUser;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
    }

    #[Test]
    public function getGraphDataReturnsEmptyWhenNoBackendUser(): void
    {
        unset($GLOBALS['BE_USER']);

        // fetchPages() needs a QueryBuilder even though it returns [] early
        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturn($this->createQueryBuilderMock([]));

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock);

        self::assertSame([], $result['nodes']);
        self::assertSame([], $result['links']);
        self::assertSame(0, $result['meta']['totalPages']);
    }

    #[Test]
    public function getGraphDataReturnsPageNodes(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
            ['uid' => 2, 'pid' => 1, 'title' => 'About', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
            ['uid' => 3, 'pid' => 1, 'title' => 'Contact', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        // Content elements query returns empty, internal links query returns empty
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) use ($pagesQb, $emptyQb): QueryBuilder {
                return $table === 'pages' ? $pagesQb : $emptyQb;
            });

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock);

        self::assertSame(3, $result['meta']['totalPages']);
        self::assertCount(3, array_filter($result['nodes'], fn(array $n): bool => $n['type'] === 'page'));

        // Verify node structure
        $rootNode = $result['nodes'][0];
        self::assertSame('p-1', $rootNode['id']);
        self::assertSame(1, $rootNode['uid']);
        self::assertSame('Root', $rootNode['label']);
        self::assertSame('page', $rootNode['type']);
        self::assertSame('standard', $rootNode['group']);
        self::assertTrue($rootNode['isSiteroot']);
    }

    #[Test]
    public function getGraphDataBuildsCorrectTreeLinks(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
            ['uid' => 2, 'pid' => 1, 'title' => 'Child A', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
            ['uid' => 3, 'pid' => 1, 'title' => 'Child B', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => $table === 'pages' ? $pagesQb : $emptyQb);

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock);

        $treeLinks = array_values(array_filter($result['links'], fn(array $l): bool => $l['type'] === 'tree'));

        // Root(1) → Child A(2) and Root(1) → Child B(3)
        self::assertCount(2, $treeLinks);
        self::assertSame('p-1', $treeLinks[0]['source']);
        self::assertSame('p-2', $treeLinks[0]['target']);
        self::assertSame('p-1', $treeLinks[1]['source']);
        self::assertSame('p-3', $treeLinks[1]['target']);
    }

    #[Test]
    public function getGraphDataCalculatesDepth(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
            ['uid' => 2, 'pid' => 1, 'title' => 'Level 1', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
            ['uid' => 3, 'pid' => 2, 'title' => 'Level 2', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => $table === 'pages' ? $pagesQb : $emptyQb);

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock);

        $depthByLabel = [];
        foreach ($result['nodes'] as $node) {
            $depthByLabel[$node['label']] = $node['depth'];
        }

        self::assertSame(0, $depthByLabel['Root']);
        self::assertSame(1, $depthByLabel['Level 1']);
        self::assertSame(2, $depthByLabel['Level 2']);
    }

    #[Test]
    public function getGraphDataExcludesContentWhenDisabled(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => $table === 'pages' ? $pagesQb : $emptyQb);

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock, false);

        $contentNodes = array_filter($result['nodes'], fn(array $n): bool => $n['type'] === 'content');
        self::assertCount(0, $contentNodes);
        self::assertSame(0, $result['meta']['totalContent']);
    }

    #[Test]
    public function getGraphDataIncludesContentElements(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
        ];

        $contentElements = [
            ['uid' => 10, 'pid' => 1, 'header' => 'Welcome', 'CType' => 'text', 'colPos' => 0, 'hidden' => 0],
            ['uid' => 11, 'pid' => 1, 'header' => '', 'CType' => 'image', 'colPos' => 0, 'hidden' => 0],
        ];

        $callCount = 0;
        $pagesQb = $this->createQueryBuilderMock($pages);
        $contentQb = $this->createQueryBuilderMock($contentElements);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) use ($pagesQb, $contentQb, $emptyQb, &$callCount): QueryBuilder {
                $callCount++;
                if ($table === 'pages') {
                    return $pagesQb;
                }
                if ($table === 'tt_content' && $callCount === 2) {
                    return $contentQb;
                }
                return $emptyQb;
            });

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock, true);

        $contentNodes = array_values(array_filter($result['nodes'], fn(array $n): bool => $n['type'] === 'content'));
        self::assertCount(2, $contentNodes);
        self::assertSame(2, $result['meta']['totalContent']);

        // First content element with header
        self::assertSame('c-10', $contentNodes[0]['id']);
        self::assertSame('Welcome', $contentNodes[0]['label']);
        self::assertSame('text', $contentNodes[0]['group']);

        // Second content element without header falls back to CType
        self::assertSame('c-11', $contentNodes[1]['id']);
        self::assertSame('(image)', $contentNodes[1]['label']);
        self::assertSame('media', $contentNodes[1]['group']);

        // Content links: page → content element
        $contentLinks = array_values(array_filter($result['links'], fn(array $l): bool => $l['type'] === 'content'));
        self::assertCount(2, $contentLinks);
        self::assertSame('p-1', $contentLinks[0]['source']);
        self::assertSame('c-10', $contentLinks[0]['target']);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function doktypeGroupProvider(): array
    {
        return [
            'standard page' => [1, 'standard'],
            'external link' => [3, 'link'],
            'shortcut' => [4, 'shortcut'],
            'spacer' => [199, 'spacer'],
            'folder' => [254, 'folder'],
            'unknown doktype' => [99, 'other'],
        ];
    }

    #[Test]
    #[DataProvider('doktypeGroupProvider')]
    public function getPageGroupMapsDoktypes(int $doktype, string $expectedGroup): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Test', 'doktype' => $doktype, 'hidden' => 0, 'is_siteroot' => 1],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => $table === 'pages' ? $pagesQb : $emptyQb);

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock, false);

        self::assertSame($expectedGroup, $result['nodes'][0]['group']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ctypeGroupProvider(): array
    {
        return [
            'text' => ['text', 'text'],
            'textpic' => ['textpic', 'text'],
            'textmedia' => ['textmedia', 'text'],
            'image' => ['image', 'media'],
            'uploads' => ['uploads', 'media'],
            'header' => ['header', 'header'],
            'list (plugin)' => ['list', 'plugin'],
            'html (special)' => ['html', 'special'],
            'div (special)' => ['div', 'special'],
            'shortcut (reference)' => ['shortcut', 'reference'],
            'unknown ctype' => ['my_custom_ce', 'other'],
        ];
    }

    #[Test]
    #[DataProvider('ctypeGroupProvider')]
    public function getContentGroupMapsCTypes(string $ctype, string $expectedGroup): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
        ];

        $contentElements = [
            ['uid' => 10, 'pid' => 1, 'header' => 'Test', 'CType' => $ctype, 'colPos' => 0, 'hidden' => 0],
        ];

        $callCount = 0;
        $pagesQb = $this->createQueryBuilderMock($pages);
        $contentQb = $this->createQueryBuilderMock($contentElements);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) use ($pagesQb, $contentQb, $emptyQb, &$callCount): QueryBuilder {
                $callCount++;
                if ($table === 'pages') {
                    return $pagesQb;
                }
                if ($table === 'tt_content' && $callCount === 2) {
                    return $contentQb;
                }
                return $emptyQb;
            });

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock, true);

        $contentNodes = array_values(array_filter($result['nodes'], fn(array $n): bool => $n['type'] === 'content'));
        self::assertCount(1, $contentNodes);
        self::assertSame($expectedGroup, $contentNodes[0]['group']);
    }

    #[Test]
    public function buildNavigationLinksCreatesSiblingLinks(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
            ['uid' => 2, 'pid' => 1, 'title' => 'A', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
            ['uid' => 3, 'pid' => 1, 'title' => 'B', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
            ['uid' => 4, 'pid' => 1, 'title' => 'C', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => $table === 'pages' ? $pagesQb : $emptyQb);

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock, false);

        $navLinks = array_values(array_filter($result['links'], fn(array $l): bool => $l['type'] === 'navigation'));

        // 3 siblings → 3 navigation links: A↔B, A↔C, B↔C (using min/max ordering)
        self::assertCount(3, $navLinks);
        self::assertSame('p-2', $navLinks[0]['source']);
        self::assertSame('p-3', $navLinks[0]['target']);
        self::assertSame('p-2', $navLinks[1]['source']);
        self::assertSame('p-4', $navLinks[1]['target']);
        self::assertSame('p-3', $navLinks[2]['source']);
        self::assertSame('p-4', $navLinks[2]['target']);
    }

    #[Test]
    public function buildNavigationLinksSkipsHiddenPages(): void
    {
        $this->setUpBackendUser();

        $pages = [
            ['uid' => 1, 'pid' => 0, 'title' => 'Root', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 1],
            ['uid' => 2, 'pid' => 1, 'title' => 'Visible', 'doktype' => 1, 'hidden' => 0, 'is_siteroot' => 0],
            ['uid' => 3, 'pid' => 1, 'title' => 'Hidden', 'doktype' => 1, 'hidden' => 1, 'is_siteroot' => 0],
            ['uid' => 4, 'pid' => 1, 'title' => 'Spacer', 'doktype' => 199, 'hidden' => 0, 'is_siteroot' => 0],
        ];

        $pagesQb = $this->createQueryBuilderMock($pages);
        $emptyQb = $this->createQueryBuilderMock([]);

        $this->connectionPoolMock->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => $table === 'pages' ? $pagesQb : $emptyQb);

        $subject = $this->createSubject();
        $result = $subject->getGraphData($this->requestMock, false);

        $navLinks = array_filter($result['links'], fn(array $l): bool => $l['type'] === 'navigation');

        // Only 1 visible page with doktype != 199 → no sibling pairs → no navigation links
        self::assertCount(0, $navLinks);
    }
}
