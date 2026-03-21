..  _developer:

=========
Developer
=========

Architecture
============

The extension consists of two main PHP classes and a JavaScript module:

PageGraphDataProvider
---------------------

``RTfirst\PageGraph\Service\PageGraphDataProvider`` queries the ``pages``
and ``tt_content`` tables and builds a graph data structure with nodes
and links.

-  Respects backend user page permissions
-  Excludes deleted records and sys_folder pages (doktype 255)
-  Generates backend edit URLs for each record

PageGraphWidget
---------------

``RTfirst\PageGraph\Widgets\PageGraphWidget`` implements five dashboard
widget interfaces for cross-version compatibility:

-  ``WidgetInterface`` — base rendering
-  ``RequestAwareWidgetInterface`` — access to the PSR-7 request
-  ``EventDataInterface`` — passes graph data to JavaScript
-  ``AdditionalJavaScriptInterface`` — loads force-graph library
-  ``AdditionalCssInterface`` — loads widget styles

JavaScript
----------

``PageGraphWidget.js`` listens for the ``widgetContentRendered`` custom
event dispatched by the TYPO3 dashboard and initializes the force-graph
canvas.

Graph Data Structure
====================

The ``getGraphData()`` method returns:

.. code-block:: json
    :caption: Graph data structure

    {
        "nodes": [
            {"id": "p-3", "uid": 3, "label": "Home", "type": "page", ...},
            {"id": "c-42", "uid": 42, "label": "Welcome", "type": "content", ...}
        ],
        "links": [
            {"source": "p-3", "target": "p-5", "type": "tree"},
            {"source": "p-3", "target": "c-42", "type": "content"}
        ],
        "meta": {"totalPages": 25, "totalContent": 87}
    }

Unit Tests
==========

The extension ships with PHPUnit unit tests located in ``Tests/Unit/``.

Run tests locally
-----------------

..  code-block:: bash
    :caption: Run from the DDEV project root

    ddev exec "cd packages/page_graph && ../../vendor/bin/phpunit \
        --bootstrap ../../vendor/autoload.php Tests/Unit/"

Test structure
--------------

-  ``Tests/Unit/Service/PageGraphDataProviderTest.php`` — Tests the data
   provider including page/content node generation, depth calculation,
   doktype and CType group mapping, tree links, and navigation links.
-  ``Tests/Unit/Widgets/PageGraphWidgetTest.php`` — Tests the widget's
   JS/CSS file paths, options handling, request guards, and event data
   delegation.

Updating Vendor Libraries
=========================

The bundled ``force-graph`` library can be updated with the provided
shell script. No npm or Node.js installation is required for end users
— the minified file is always committed to Git.

..  code-block:: bash
    :caption: Update to the latest version

    cd packages/page_graph
    bash Build/update-vendor.sh

..  code-block:: bash
    :caption: Update to a specific version

    bash Build/update-vendor.sh 1.52.0

The script:

-  Fetches the minified build from `unpkg.com <https://unpkg.com/>`__
-  Prepends a version comment to the first line
-  Updates the version reference in ``Resources/Public/JavaScript/Vendor/LICENSE.md``

After updating, verify the widget still works in the TYPO3 dashboard
and commit the changed files.

Cross-Version Compatibility
===========================

The extension supports TYPO3 12.4, 13.x, and 14.x. It avoids
``JavaScriptInterface`` (not available in v12) and uses
``BackendViewFactory`` instead of deprecated ``StandaloneView``.
