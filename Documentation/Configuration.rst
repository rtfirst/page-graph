..  _configuration:

=============
Configuration
=============

The Page Graph widget works out of the box without any configuration.

Widget Options
==============

The widget is registered with the following defaults in ``Services.yaml``:

-  **includeContent**: ``true`` — show content element nodes by default
-  **refreshAvailable**: ``true`` — allow dashboard refresh
-  **height**: ``large`` — large widget height
-  **width**: ``large`` — large widget width

These can be customized by overriding the widget service definition in your
own extension's ``Services.yaml``.
