..  _installation:

============
Installation
============

The recommended way to install this extension is via Composer.

Composer
========

..  code-block:: bash
    :caption: Install via Composer

    composer require rtfirst/page-graph

Then activate and clear caches:

..  code-block:: bash
    :caption: Activate the extension

    vendor/bin/typo3 extension:setup
    vendor/bin/typo3 cache:flush

Requirements
============

-  TYPO3 12.4, 13.x, or 14.x
-  PHP 8.1 or higher
-  TYPO3 Dashboard system extension (``typo3/cms-dashboard``)
