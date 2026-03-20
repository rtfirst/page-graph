..  _usage:

=====
Usage
=====

Adding the Widget
=================

1. Navigate to the **Dashboard** module in the TYPO3 backend
2. Click **Add widget**
3. Select **Page Graph** from the content group
4. The widget displays your page tree as an interactive graph

Interactions
============

Hover
-----

Move your mouse over a node to highlight it and its directly connected
neighbors. All other nodes are dimmed.

Click
-----

Click a node to open the info panel showing:

-  Record ID, type, and group
-  CType and ColPos for content elements
-  Doktype for pages
-  Hidden status
-  List of connected nodes (clickable)
-  Pencil edit link to open the TYPO3 record editor

Search
------

Type in the search field to filter nodes in real-time. Matching nodes
receive a golden highlight ring while non-matching nodes are dimmed.

Toggle Content Elements
-----------------------

Use the "Content Elements" checkbox to show or hide content element
nodes and their links.

Navigation
----------

-  **Scroll** to zoom in and out
-  **Drag** the background to pan
-  **Drag** a node to reposition it

Dark Mode
=========

The widget automatically detects the current theme:

-  TYPO3 13+: Reads ``data-bs-theme`` attribute
-  TYPO3 12: Falls back to OS preference via ``prefers-color-scheme``
