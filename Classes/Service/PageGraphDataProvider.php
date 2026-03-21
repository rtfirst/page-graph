<?php

declare(strict_types=1);

namespace RTfirst\PageGraph\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class PageGraphDataProvider
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly UriBuilder $uriBuilder,
    ) {}

    /**
     * @return array{nodes: list<array<string, mixed>>, links: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function getGraphData(ServerRequestInterface $request, bool $includeContent = true): array
    {
        $pages = $this->fetchPages();
        $nodes = [];
        $links = [];

        // Build pid→uid index and compute depth per page
        $pidToChildren = [];
        $pageByUid = [];
        $pageUids = [];
        foreach ($pages as $page) {
            $uid = (int) $page['uid'];
            $pid = (int) $page['pid'];
            $pageUids[] = $uid;
            $pageByUid[$uid] = $page;
            $pidToChildren[$pid][] = $uid;
        }

        $depthMap = [];
        $queue = [];
        // Siteroot pages or pages with pid=0 start at depth 0
        foreach ($pages as $page) {
            if ((int) $page['pid'] === 0 || (bool) $page['is_siteroot']) {
                $depthMap[(int) $page['uid']] = 0;
                $queue[] = (int) $page['uid'];
            }
        }
        while ($queue !== []) {
            $current = array_shift($queue);
            $childUids = $pidToChildren[$current] ?? [];
            foreach ($childUids as $childUid) {
                if (!isset($depthMap[$childUid])) {
                    $depthMap[$childUid] = $depthMap[$current] + 1;
                    $queue[] = $childUid;
                }
            }
        }

        $pageUidMap = array_flip($pageUids);
        foreach ($pages as $page) {
            $uid = (int) $page['uid'];
            $pid = (int) $page['pid'];
            $nodes[] = [
                'id' => 'p-' . $uid,
                'uid' => $uid,
                'label' => $page['title'],
                'type' => 'page',
                'doktype' => (int) $page['doktype'],
                'group' => $this->getPageGroup((int) $page['doktype']),
                'hidden' => (bool) $page['hidden'],
                'isSiteroot' => (bool) $page['is_siteroot'],
                'depth' => $depthMap[$uid] ?? 0,
                'parentId' => isset($pageUidMap[$pid]) ? 'p-' . $pid : null,
                'editUrl' => $this->buildEditUrl('pages', $uid),
            ];
            if (isset($pageUidMap[$pid])) {
                $links[] = [
                    'source' => 'p-' . $pid,
                    'target' => 'p-' . $uid,
                    'type' => 'tree',
                ];
            }
        }

        $totalContent = 0;
        if ($includeContent && $pageUids !== []) {
            $contentElements = $this->fetchContentElements($pageUids);
            $totalContent = count($contentElements);
            foreach ($contentElements as $ce) {
                $nodes[] = [
                    'id' => 'c-' . $ce['uid'],
                    'uid' => (int) $ce['uid'],
                    'label' => $ce['header'] ?: ('(' . $ce['CType'] . ')'),
                    'type' => 'content',
                    'ctype' => $ce['CType'],
                    'colPos' => (int) $ce['colPos'],
                    'group' => $this->getContentGroup($ce['CType']),
                    'hidden' => (bool) $ce['hidden'],
                    'pageId' => (int) $ce['pid'],
                    'editUrl' => $this->buildEditUrl('tt_content', (int) $ce['uid']),
                ];
                $links[] = [
                    'source' => 'p-' . $ce['pid'],
                    'target' => 'c-' . $ce['uid'],
                    'type' => 'content',
                ];
            }
        }

        // Internal page-to-page links (typolinks from content elements)
        $internalLinks = $this->fetchInternalLinks($pageUids);
        foreach ($internalLinks as $il) {
            $links[] = [
                'source' => 'p-' . $il['source_page'],
                'target' => 'p-' . $il['target_page'],
                'type' => 'reference',
            ];
        }

        // Navigation links: sibling pages share a menu (same pid = same navigation level)
        $navLinks = $this->buildNavigationLinks($pages, $pageUidMap);
        foreach ($navLinks as $nl) {
            $links[] = $nl;
        }

        return [
            'nodes' => $nodes,
            'links' => $links,
            'meta' => [
                'totalPages' => count($pageUids),
                'totalContent' => $totalContent,
                'totalReferences' => count($internalLinks) + count($navLinks),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPages(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return [];
        }
        $permissionClause = $backendUser->getPagePermsClause(Permission::PAGE_SHOW);

        return $queryBuilder
            ->select('uid', 'pid', 'title', 'doktype', 'hidden', 'is_siteroot')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->neq('doktype', $queryBuilder->createNamedParameter(255, Connection::PARAM_INT)),
            )
            ->andWhere(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->andWhere($permissionClause)
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param list<int> $pageUids
     * @return list<array<string, mixed>>
     */
    private function fetchContentElements(array $pageUids): array
    {
        // $pageUids are already filtered by backend user permissions in fetchPages(),
        // so content elements are implicitly restricted to accessible pages.
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

        return $queryBuilder
            ->select('uid', 'pid', 'header', 'CType', 'colPos', 'hidden')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in('pid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY)),
            )
            ->andWhere(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param list<int> $pageUids
     * @return list<array{source_page: int, target_page: int}>
     */
    private function fetchInternalLinks(array $pageUids): array
    {
        if ($pageUids === []) {
            return [];
        }

        $pageUidSet = array_flip($pageUids);

        // Step 1: Get all typolink references pointing to pages
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');
        $refs = $queryBuilder
            ->select('tablename', 'recuid', 'ref_uid')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq('ref_table', $queryBuilder->createNamedParameter('pages')),
                $queryBuilder->expr()->in('softref_key', $queryBuilder->createNamedParameter(['typolink', 'typolink_tag'], Connection::PARAM_STR_ARRAY)),
                $queryBuilder->expr()->eq('workspace', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('ref_uid', $queryBuilder->createNamedParameter($pageUids, Connection::PARAM_INT_ARRAY)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        // Step 2: Group by source table to resolve pid (= source page)
        $byTable = [];
        foreach ($refs as $ref) {
            $byTable[$ref['tablename']][] = $ref;
        }

        $seen = [];
        $result = [];

        foreach ($byTable as $tableName => $tableRefs) {
            $recUids = array_values(array_unique(array_map(fn(array $r): int => (int) $r['recuid'], $tableRefs)));

            // Resolve pid for each record in this table
            $pidMap = $this->resolveSourcePages($tableName, $recUids);

            foreach ($tableRefs as $ref) {
                $sourcePage = $pidMap[(int) $ref['recuid']] ?? null;
                $targetPage = (int) $ref['ref_uid'];

                if ($sourcePage === null || $sourcePage === $targetPage) {
                    continue;
                }
                if (!isset($pageUidSet[$sourcePage], $pageUidSet[$targetPage])) {
                    continue;
                }
                $key = $sourcePage . '->' . $targetPage;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = ['source_page' => $sourcePage, 'target_page' => $targetPage];
            }
        }

        return $result;
    }

    /**
     * Resolve the page (pid) a record belongs to.
     * For tt_content: pid is the page directly.
     * For IRRE children (e.g. tx_bootstrappackage_*): walk up via foreign field to tt_content.
     *
     * @param list<int> $recUids
     * @return array<int, int> recuid => page uid
     */
    private function resolveSourcePages(string $tableName, array $recUids): array
    {
        if ($recUids === []) {
            return [];
        }

        // Only allow known safe tables to prevent arbitrary table access via sys_refindex
        if ($tableName !== 'tt_content' && !str_starts_with($tableName, 'tx_')) {
            return [];
        }

        $connection = $this->connectionPool->getConnectionForTable($tableName);

        // Check if table exists
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        $rows = $queryBuilder
            ->select('uid', 'pid')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($recUids, Connection::PARAM_INT_ARRAY)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $pidMap = [];
        foreach ($rows as $row) {
            $pidMap[(int) $row['uid']] = (int) $row['pid'];
        }

        if ($tableName === 'tt_content') {
            return $pidMap;
        }

        // For IRRE children, pid is the page but we need to verify through tt_content parent.
        // Most Bootstrap Package IRRE tables store the tt_content parent uid in a field
        // matching the table prefix (e.g. `tt_content` column). Try to resolve via that.
        $columns = $schemaManager->listTableColumns($tableName);
        if (isset($columns['tt_content'])) {
            $qb2 = $this->connectionPool->getQueryBuilderForTable($tableName);
            $qb2->getRestrictions()->removeAll()->add(new DeletedRestriction());
            $parentRows = $qb2
                ->select('uid', 'tt_content')
                ->from($tableName)
                ->where(
                    $qb2->expr()->in('uid', $qb2->createNamedParameter($recUids, Connection::PARAM_INT_ARRAY)),
                )
                ->executeQuery()
                ->fetchAllAssociative();

            $ttContentUids = [];
            $irrelMap = [];
            foreach ($parentRows as $row) {
                $ttContentUid = (int) $row['tt_content'];
                if ($ttContentUid > 0) {
                    $ttContentUids[] = $ttContentUid;
                    $irrelMap[(int) $row['uid']] = $ttContentUid;
                }
            }

            if ($ttContentUids !== []) {
                $ceQb = $this->connectionPool->getQueryBuilderForTable('tt_content');
                $ceQb->getRestrictions()->removeAll()->add(new DeletedRestriction());
                $ceRows = $ceQb
                    ->select('uid', 'pid')
                    ->from('tt_content')
                    ->where(
                        $ceQb->expr()->in('uid', $ceQb->createNamedParameter(array_unique($ttContentUids), Connection::PARAM_INT_ARRAY)),
                    )
                    ->executeQuery()
                    ->fetchAllAssociative();

                $cePidMap = [];
                foreach ($ceRows as $ceRow) {
                    $cePidMap[(int) $ceRow['uid']] = (int) $ceRow['pid'];
                }

                foreach ($irrelMap as $recUid => $ttContentUid) {
                    if (isset($cePidMap[$ttContentUid])) {
                        $pidMap[$recUid] = $cePidMap[$ttContentUid];
                    }
                }
            }
        }

        return $pidMap;
    }

    /**
     * Build navigation links: pages with the same parent appear in the same menu.
     * Creates links from the parent to each visible child (implicit menu structure).
     * Also links siblings to each other since they co-appear in navigation.
     *
     * @param list<array<string, mixed>> $pages
     * @param array<int, int> $pageUidMap
     * @return list<array{source: string, target: string, type: string}>
     */
    private function buildNavigationLinks(array $pages, array $pageUidMap): array
    {
        // Group pages by pid (navigation groups)
        $siblingGroups = [];
        foreach ($pages as $page) {
            $pid = (int) $page['pid'];
            if (!isset($pageUidMap[$pid])) {
                continue; // Parent not in our set
            }
            if ((bool) $page['hidden'] || (int) $page['doktype'] === 199) {
                continue; // Hidden pages and spacers don't appear in navigation
            }
            $siblingGroups[$pid][] = (int) $page['uid'];
        }

        $links = [];
        $seen = [];
        foreach ($siblingGroups as $children) {
            // Siblings link to each other (same navigation menu level)
            for ($i = 0, $count = count($children); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = min($children[$i], $children[$j]);
                    $b = max($children[$i], $children[$j]);
                    $key = $a . '<>' . $b;
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $links[] = [
                            'source' => 'p-' . $a,
                            'target' => 'p-' . $b,
                            'type' => 'navigation',
                        ];
                    }
                }
            }
        }

        return $links;
    }

    private function buildEditUrl(string $table, int $uid): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [$table => [$uid => 'edit']],
            'returnUrl' => (string) $this->uriBuilder->buildUriFromRoute('dashboard'),
        ]);
    }

    private function getPageGroup(int $doktype): string
    {
        return match ($doktype) {
            1 => 'standard',
            3 => 'link',
            4 => 'shortcut',
            199 => 'spacer',
            254 => 'folder',
            default => 'other',
        };
    }

    private function getContentGroup(string $ctype): string
    {
        return match (true) {
            in_array($ctype, ['text', 'textpic', 'textmedia'], true) => 'text',
            in_array($ctype, ['image', 'uploads'], true) => 'media',
            $ctype === 'header' => 'header',
            $ctype === 'list' => 'plugin',
            in_array($ctype, ['html', 'div'], true) => 'special',
            $ctype === 'shortcut' => 'reference',
            default => 'other',
        };
    }
}
