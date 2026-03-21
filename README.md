# TYPO3 Extension: page_graph

[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 13](https://img.shields.io/badge/TYPO3-13-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 14](https://img.shields.io/badge/TYPO3-14-orange.svg)](https://get.typo3.org/version/14)
[![CI](https://github.com/rtfirst/page-graph/actions/workflows/ci.yaml/badge.svg)](https://github.com/rtfirst/page-graph/actions/workflows/ci.yaml)
[![Latest Stable Version](https://img.shields.io/packagist/v/rtfirst/page-graph)](https://packagist.org/packages/rtfirst/page-graph)
[![Total Downloads](https://img.shields.io/packagist/dt/rtfirst/page-graph)](https://packagist.org/packages/rtfirst/page-graph)
[![License](https://img.shields.io/packagist/l/rtfirst/page-graph)](https://github.com/rtfirst/page-graph/blob/main/LICENSE)

A TYPO3 dashboard widget that visualizes the page tree and content elements as an interactive force-directed graph.

![Page Graph Widget](Documentation/Images/page-graph.webp)

## Features

- Interactive force-directed graph of the TYPO3 page tree
- Content element nodes with toggle visibility
- Internal links view (typolinks + navigation structure)
- Hover highlighting with connected node emphasis
- Click nodes to view details and edit records in the TYPO3 backend
- Real-time search filtering
- Fullscreen mode
- Light and dark mode support (TYPO3 12/13/14)
- Depth-based branch coloring
- German and English translations

## Installation

### Composer

```bash
composer require rtfirst/page-graph
```

Then activate the extension:

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

## Usage

1. Go to the TYPO3 backend Dashboard module
2. Click "Add widget"
3. Select "Page Graph" from the content group
4. The widget displays your page tree as an interactive graph

### Interactions

- **Hover** over a node to highlight it and its connections
- **Click** a node to open the info panel with details and an edit link
- **Search** to filter and highlight matching nodes
- **Toggle** "Content Elements" to show/hide content element nodes
- **Toggle** "Internal Links" to visualize typolinks and navigation links
- **Fullscreen** for a larger view
- **Scroll** to zoom, **drag** to pan

## Requirements

- TYPO3 12.4 - 14.x
- PHP 8.1 - 8.4
- TYPO3 Dashboard extension

## License

GPL-2.0-or-later
