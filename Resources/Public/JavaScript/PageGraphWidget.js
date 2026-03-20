(function () {
    'use strict';

    // Color palettes for light and dark modes
    var COLORS = {
        light: {
            bg: '#f8f9fa',
            text: '#333333',
            pageNode: '#7c5cbf',
            siteroot: '#e05050',
            linkTree: 'rgba(100,100,100,0.4)',
            linkContent: 'rgba(100,100,200,0.25)',
            linkReference: 'rgba(220,120,50,0.5)',
            linkNavigation: 'rgba(80,160,80,0.35)',
            accent: '#6c4db0',
            searchHighlight: '#e8a838',
            panelBg: '#ffffff',
            panelBorder: '#dee2e6',
            dimmed: 'rgba(150,150,150,0.15)'
        },
        dark: {
            bg: '#1e1e1e',
            text: '#dcddde',
            pageNode: '#a88bfa',
            siteroot: '#ff6b6b',
            linkTree: 'rgba(220,221,222,0.3)',
            linkContent: 'rgba(168,139,250,0.2)',
            linkReference: 'rgba(255,160,80,0.45)',
            linkNavigation: 'rgba(100,200,100,0.3)',
            accent: '#a88bfa',
            searchHighlight: '#e8a838',
            panelBg: '#2b2b2b',
            panelBorder: '#3e3e3e',
            dimmed: 'rgba(150,150,150,0.08)'
        }
    };

    // Depth-based hues for page branches (each top-level branch gets its own hue)
    var BRANCH_HUES = [262, 210, 150, 30, 340, 180, 60, 300, 120, 240, 0, 90];

    // Content element group colors
    var CONTENT_COLORS = {
        light: {
            text: '#4a90d9', media: '#d94a7a', header: '#d9a84a',
            plugin: '#4ad9a8', special: '#d94a4a', reference: '#8f5cbf', other: '#999999'
        },
        dark: {
            text: '#6aadff', media: '#ff6a9a', header: '#ffc46a',
            plugin: '#6affca', special: '#ff6a6a', reference: '#b58bfa', other: '#888888'
        }
    };

    function isDarkMode() {
        var theme = document.documentElement.getAttribute('data-bs-theme');
        if (theme === 'dark') return true;
        if (theme === 'light') return false;
        var cs = getComputedStyle(document.documentElement).colorScheme;
        if (cs && cs.indexOf('dark') !== -1) return true;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function getColors() {
        return isDarkMode() ? COLORS.dark : COLORS.light;
    }

    function getContentColors() {
        return isDarkMode() ? CONTENT_COLORS.dark : CONTENT_COLORS.light;
    }

    // Compute branch index for each node (which top-level subtree does it belong to?)
    var branchIndexCache = {};
    function computeBranchIndices(nodes) {
        branchIndexCache = {};
        // Find siteroot
        var siteroot = null;
        nodes.forEach(function (n) { if (n.isSiteroot) siteroot = n; });
        if (!siteroot) return;
        branchIndexCache[siteroot.id] = -1; // siteroot itself

        // Find direct children of siteroot (top-level branches)
        var branchIdx = 0;
        nodes.forEach(function (n) {
            if (n.parentId === siteroot.id) {
                branchIndexCache[n.id] = branchIdx;
                branchIdx++;
            }
        });

        // Propagate branch index down the tree
        var changed = true;
        while (changed) {
            changed = false;
            nodes.forEach(function (n) {
                if (branchIndexCache[n.id] === undefined && n.parentId && branchIndexCache[n.parentId] !== undefined) {
                    branchIndexCache[n.id] = branchIndexCache[n.parentId];
                    changed = true;
                }
            });
        }
    }

    function getNodeColor(node) {
        if (node.type === 'content') {
            var cc = getContentColors();
            return cc[node.group] || cc.other;
        }
        var dark = isDarkMode();
        var bi = branchIndexCache[node.id];
        if (bi === undefined || bi === -1) return null; // siteroot handled separately
        var hue = BRANCH_HUES[bi % BRANCH_HUES.length];
        var sat = dark ? 55 : 50;
        // Deeper nodes get lighter (light mode) or darker (dark mode)
        var depth = node.depth || 0;
        var lightness;
        if (dark) {
            lightness = Math.max(35, 65 - depth * 8);
        } else {
            lightness = Math.min(75, 45 + depth * 8);
        }
        return 'hsl(' + hue + ',' + sat + '%,' + lightness + '%)';
    }

    /** Create a text detail row using safe DOM methods */
    function createDetailRow(label, value) {
        var div = document.createElement('div');
        var strong = document.createElement('strong');
        strong.textContent = label + ': ';
        div.appendChild(strong);
        div.appendChild(document.createTextNode(String(value)));
        return div;
    }

    function initGraph(container, graphData) {
        var widget = container.closest('.page-graph-widget');
        if (!widget) return;

        var searchInput = widget.querySelector('.page-graph-search');
        var toggleContent = widget.querySelector('.page-graph-toggle-content');
        var toggleReferences = widget.querySelector('.page-graph-toggle-references');
        var infoPanel = widget.querySelector('.page-graph-info-panel');
        if (!searchInput || !toggleContent || !toggleReferences || !infoPanel) return;
        var infoTitle = widget.querySelector('.page-graph-info-title');
        var infoEdit = widget.querySelector('.page-graph-info-edit');
        var infoClose = widget.querySelector('.page-graph-info-close');
        var infoDetails = widget.querySelector('.page-graph-info-details');
        var connectionsList = widget.querySelector('.page-graph-info-connections-list');
        var statsEl = widget.querySelector('.page-graph-stats');

        // i18n labels from data attributes
        var labels = {
            id: widget.dataset.labelId || 'ID',
            type: widget.dataset.labelType || 'Type',
            group: widget.dataset.labelGroup || 'Group',
            hidden: widget.dataset.labelHidden || 'Hidden',
            page: widget.dataset.labelPage || 'page',
            content: widget.dataset.labelContent || 'content',
            pages: widget.dataset.labelPages || 'Pages',
            contentElements: widget.dataset.labelContentElements || 'Content Elements',
            connected: widget.dataset.labelConnected || 'Connected:'
        };

        // Deep-copy original data so force-graph mutations don't corrupt our source
        var rawNodes = JSON.parse(JSON.stringify(graphData.nodes));
        var rawLinks = JSON.parse(JSON.stringify(graphData.links));
        var hoveredNode = null;
        var selectedNode = null;
        var searchTerm = '';
        var showContent = toggleContent.checked;
        var showReferences = toggleReferences.checked;

        // Build neighbor maps
        var neighbors = {};
        function rebuildNeighbors(links) {
            neighbors = {};
            links.forEach(function (l) {
                var sid = typeof l.source === 'object' ? l.source.id : l.source;
                var tid = typeof l.target === 'object' ? l.target.id : l.target;
                if (!neighbors[sid]) neighbors[sid] = new Set();
                if (!neighbors[tid]) neighbors[tid] = new Set();
                neighbors[sid].add(tid);
                neighbors[tid].add(sid);
            });
        }

        function getFilteredData() {
            // Always produce fresh copies to avoid force-graph mutation issues
            var nodes;
            if (showContent && !showReferences) {
                nodes = rawNodes.slice();
            } else {
                // In reference mode, only show pages
                nodes = rawNodes.filter(function (n) { return n.type === 'page'; });
            }
            var nodeIds = new Set(nodes.map(function (n) { return n.id; }));
            var filteredLinks = rawLinks.filter(function (l) {
                if (!nodeIds.has(l.source) || !nodeIds.has(l.target)) return false;
                if (showReferences) {
                    // Show tree structure + reference + navigation links
                    return l.type === 'tree' || l.type === 'reference' || l.type === 'navigation';
                }
                // Normal mode: only tree and content links
                return l.type === 'tree' || l.type === 'content';
            });
            return {
                nodes: JSON.parse(JSON.stringify(nodes)),
                links: JSON.parse(JSON.stringify(filteredLinks))
            };
        }

        function isNeighbor(nodeId, targetId) {
            return neighbors[nodeId] && neighbors[nodeId].has(targetId);
        }

        function matchesSearch(node) {
            if (!searchTerm) return true;
            return node.label.toLowerCase().indexOf(searchTerm) !== -1 ||
                   node.id.toLowerCase().indexOf(searchTerm) !== -1 ||
                   (node.ctype && node.ctype.toLowerCase().indexOf(searchTerm) !== -1) ||
                   (node.group && node.group.toLowerCase().indexOf(searchTerm) !== -1);
        }

        function updateStats(data) {
            var pages = data.nodes.filter(function (n) { return n.type === 'page'; }).length;
            var content = data.nodes.filter(function (n) { return n.type === 'content'; }).length;
            var refs = data.links.filter(function (l) { return l.type === 'reference'; }).length;
            var parts = [pages + ' ' + labels.pages];
            if (showContent) parts.push(content + ' ' + labels.contentElements);
            if (showReferences && refs > 0) parts.push(refs + ' Links');
            statsEl.textContent = parts.join(', ');
        }

        function getLinkCount(nodeId, links) {
            var count = 0;
            links.forEach(function (l) {
                var sid = typeof l.source === 'object' ? l.source.id : l.source;
                var tid = typeof l.target === 'object' ? l.target.id : l.target;
                if (sid === nodeId || tid === nodeId) count++;
            });
            return count;
        }

        computeBranchIndices(rawNodes);
        var data = getFilteredData();
        rebuildNeighbors(data.links);
        updateStats(data);

        var nodeCount = data.nodes.length;

        var graph = ForceGraph()(container)
            .graphData(data)
            .nodeId('id')
            .nodeLabel('')
            .linkSource('source')
            .linkTarget('target')
            .backgroundColor('transparent')
            .linkColor(function (link) {
                var colors = getColors();
                if (link.type === 'reference') return colors.linkReference;
                if (link.type === 'navigation') return colors.linkNavigation;
                if (link.type === 'content') return colors.linkContent;
                return colors.linkTree;
            })
            .linkWidth(function (link) {
                if (link.type === 'reference') return 1.5;
                if (link.type === 'navigation') return 0.8;
                return link.type === 'content' ? 0.5 : 1;
            })
            .linkLineDash(function (link) {
                if (link.type === 'reference') return [4, 3];
                if (link.type === 'navigation') return [2, 2];
                return [];
            })
            .linkDirectionalParticles(0)
            .d3AlphaDecay(0.015)
            .d3VelocityDecay(0.3)
            .warmupTicks(150)
            .cooldownTicks(400);

        // Stronger repulsion so nodes don't overlap
        var chargeForce = graph.d3Force('charge');
        if (chargeForce && chargeForce.strength) {
            chargeForce.strength(Math.min(-150, -300 - nodeCount * 3));
        }
        // Longer links to spread the tree out
        var linkForce = graph.d3Force('link');
        if (linkForce && linkForce.distance) {
            linkForce.distance(function (link) {
                // Content links shorter, tree links longer
                var t = typeof link.type === 'string' ? link.type : 'tree';
                return t === 'content' ? 40 : Math.max(60, 80 + nodeCount);
            });
        }

        graph
            .nodeCanvasObject(function (node, ctx, globalScale) {
                var colors = getColors();
                var nodeColor = getNodeColor(node);
                var links = graph.graphData().links;
                var size = 4 + Math.sqrt(getLinkCount(node.id, links)) * 2;
                var isHovered = hoveredNode && (hoveredNode.id === node.id || isNeighbor(hoveredNode.id, node.id));
                var isSelected = selectedNode && selectedNode.id === node.id;
                var isSearchMatch = searchTerm && matchesSearch(node);
                var isDimmedByHover = hoveredNode && hoveredNode.id !== node.id && !isNeighbor(hoveredNode.id, node.id);
                var isDimmedBySearch = searchTerm && !matchesSearch(node);
                var isDimmed = isDimmedByHover || isDimmedBySearch;

                ctx.save();

                // Siteroot gets distinct color and larger size
                if (node.isSiteroot) {
                    nodeColor = colors.siteroot;
                    size = size + 4;
                }

                // Dimmed nodes: desaturated, muted fill at full opacity (no bleed-through)
                var dark = isDarkMode();
                var fillColor;
                if (isDimmed) {
                    fillColor = dark ? '#3a3a3a' : '#d0d0d0';
                } else if (node.hidden) {
                    fillColor = '#999999';
                } else {
                    fillColor = nodeColor;
                }

                // Draw node circle
                ctx.beginPath();
                ctx.arc(node.x, node.y, size, 0, 2 * Math.PI);
                ctx.fillStyle = fillColor;
                ctx.fill();

                // Siteroot ring
                if (node.isSiteroot) {
                    ctx.strokeStyle = colors.siteroot;
                    ctx.lineWidth = 2.5;
                    ctx.stroke();
                }

                // Search highlight ring
                if (isSearchMatch) {
                    ctx.strokeStyle = colors.searchHighlight;
                    ctx.lineWidth = 2.5;
                    ctx.stroke();
                }

                // Selected ring
                if (isSelected) {
                    ctx.strokeStyle = colors.accent;
                    ctx.lineWidth = 2;
                    ctx.stroke();
                }

                // Label - hide for dimmed nodes, show for active/zoomed
                var showLabel = !isDimmed && (globalScale > 1.2 || isHovered || isSelected || isSearchMatch);
                if (showLabel) {
                    var label = node.label;
                    var fontSize = Math.max(10 / globalScale, 3);
                    ctx.font = (isSelected ? 'bold ' : '') + fontSize + 'px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'top';
                    ctx.fillStyle = dark ? '#dcddde' : '#1a1a1a';
                    ctx.fillText(label, node.x, node.y + size + 2);
                }

                ctx.restore();
            })
            .nodePointerAreaPaint(function (node, color, ctx) {
                var links = graph.graphData().links;
                var size = 4 + Math.sqrt(getLinkCount(node.id, links)) * 2;
                ctx.beginPath();
                ctx.arc(node.x, node.y, size + 2, 0, 2 * Math.PI);
                ctx.fillStyle = color;
                ctx.fill();
            })
            .onNodeHover(function (node) {
                hoveredNode = node || null;
                container.style.cursor = node ? 'pointer' : 'default';
            })
            .onNodeClick(function (node) {
                if (!node) return;
                selectedNode = node;
                showInfoPanel(node);
                graph.centerAt(node.x, node.y, 400);
            })
            .onBackgroundClick(function () {
                selectedNode = null;
                hoveredNode = null;
                hideInfoPanel();
                graph.nodeColor(graph.nodeColor());
            });

        function showInfoPanel(node) {
            infoTitle.textContent = node.label;
            infoEdit.href = node.editUrl || '#';
            infoEdit.style.display = node.editUrl ? '' : 'none';

            // Build details using safe DOM methods
            while (infoDetails.firstChild) {
                infoDetails.removeChild(infoDetails.firstChild);
            }
            infoDetails.appendChild(createDetailRow(labels.id, node.uid));
            infoDetails.appendChild(createDetailRow(labels.type, node.type === 'page' ? labels.page : labels.content));
            infoDetails.appendChild(createDetailRow(labels.group, node.group));
            if (node.type === 'content') {
                infoDetails.appendChild(createDetailRow('CType', node.ctype || '-'));
                infoDetails.appendChild(createDetailRow('ColPos', node.colPos));
            }
            if (node.type === 'page') {
                infoDetails.appendChild(createDetailRow('Doktype', node.doktype));
            }
            if (node.hidden) {
                var hiddenDiv = document.createElement('div');
                hiddenDiv.style.color = '#d9534f';
                var hiddenStrong = document.createElement('strong');
                hiddenStrong.textContent = labels.hidden;
                hiddenDiv.appendChild(hiddenStrong);
                infoDetails.appendChild(hiddenDiv);
            }

            // Connected nodes using safe DOM methods
            while (connectionsList.firstChild) {
                connectionsList.removeChild(connectionsList.firstChild);
            }
            var connectedIds = neighbors[node.id] || new Set();
            var currentNodes = graph.graphData().nodes;
            connectedIds.forEach(function (cid) {
                var cnode = currentNodes.find(function (n) { return n.id === cid; });
                if (!cnode) return;
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.textContent = cnode.label + ' (' + (cnode.type === 'page' ? labels.page : labels.content) + ')';
                a.href = '#';
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    selectedNode = cnode;
                    showInfoPanel(cnode);
                    graph.centerAt(cnode.x, cnode.y, 400);
                });
                li.appendChild(a);
                connectionsList.appendChild(li);
            });

            infoPanel.style.display = '';
        }

        function hideInfoPanel() {
            infoPanel.style.display = 'none';
        }

        infoClose.addEventListener('click', function () {
            selectedNode = null;
            hideInfoPanel();
        });

        searchInput.addEventListener('input', function () {
            searchTerm = this.value.toLowerCase().trim();
            // Force canvas redraw without reheating simulation
            graph.nodeColor(graph.nodeColor());
        });

        function refreshGraph() {
            var newData = getFilteredData();
            rebuildNeighbors(newData.links);
            updateStats(newData);
            graph.graphData(newData);
            selectedNode = null;
            hideInfoPanel();
            setTimeout(centerOnSiteroot, 300);
        }

        toggleContent.addEventListener('change', function () {
            showContent = this.checked;
            if (showContent && showReferences) {
                showReferences = false;
                toggleReferences.checked = false;
            }
            refreshGraph();
        });

        toggleReferences.addEventListener('change', function () {
            showReferences = this.checked;
            if (showReferences && showContent) {
                showContent = false;
                toggleContent.checked = false;
            }
            refreshGraph();
        });

        // Fullscreen toggle
        var fullscreenBtn = widget.querySelector('.page-graph-fullscreen');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function () {
                var isFs = widget.classList.toggle('is-fullscreen');
                fullscreenBtn.textContent = isFs ? '\u00d7' : '\u26F6';
                // Trigger resize after transition
                setTimeout(function () {
                    var rect = container.getBoundingClientRect();
                    graph.width(rect.width).height(rect.height);
                    centerOnSiteroot();
                }, 50);
            });
            // Escape key exits fullscreen
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && widget.classList.contains('is-fullscreen')) {
                    widget.classList.remove('is-fullscreen');
                    fullscreenBtn.textContent = '\u26F6';
                    setTimeout(function () {
                        var rect = container.getBoundingClientRect();
                        graph.width(rect.width).height(rect.height);
                    }, 50);
                }
            });
        }

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    var rect = entries[i].contentRect;
                    graph.width(rect.width).height(rect.height);
                }
            });
            ro.observe(container);
        }

        // Center on siteroot node with reasonable zoom
        function centerOnSiteroot() {
            var siterootNode = graph.graphData().nodes.find(function (n) {
                return n.isSiteroot;
            });
            if (siterootNode && siterootNode.x !== undefined) {
                graph.centerAt(siterootNode.x, siterootNode.y, 400);
                graph.zoom(3, 400);
            } else {
                graph.zoomToFit(400, 40);
            }
        }

        // Reset view button
        var resetBtn = widget.querySelector('.page-graph-reset-view');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                selectedNode = null;
                hideInfoPanel();
                centerOnSiteroot();
            });
        }

        setTimeout(centerOnSiteroot, 600);
    }

    // Listen for the widgetContentRendered event (dispatched by TYPO3 dashboard)
    document.addEventListener('widgetContentRendered', function (e) {
        if (!e.detail || !e.detail.graphData) return;

        var widget;
        if (e.target && e.target.querySelector) {
            widget = e.target.querySelector('.page-graph-container');
        }
        if (!widget) {
            widget = document.querySelector('.page-graph-container');
        }
        if (!widget) return;

        // Prevent double initialization
        if (widget.dataset.initialized) return;
        widget.dataset.initialized = 'true';

        initGraph(widget, e.detail.graphData);
    });
})();
