bandeau-ENT
===========

Web widget prolonging uportal/ENT into apps

!! this is what Université Paris Pantheon-Sorbonne is using in production. It has not yet been used elsewhere and would need moving custom paris1 stuff somewhere else !!

Configuration
-------------

* create ```config.inc.php``` using ```config.sample.inc.php```.
* create ```config-menu.inc.php``` using ```config-menu-sample.inc.php```
* add the following to applications:

```html
<script> window.bandeau_ENT = { current: 'xxx' } </script>
<script async src="https://bandeau-ENT.univ.fr/bandeau-ENT-loader.js"></script>
```

* to enable links in uportal to go out of uportal, you can use bandeau-ENT ```redirect.php``` on iframes:

```xsl
<xsl:attribute name="href">
  <xsl:choose>
    <xsl:when test="@portletName='IFrame'">
       https://bandeau-ENT.univ.fr/redirect.php?id=<xsl:value-of select="@fname"/>
    </xsl:when>
  <xsl:otherwise>
```

### intégration dans une application via reverse proxy apache

```apache
RequestHeader unset  Accept-Encoding

FilterDeclare replace
FilterProvider replace SUBSTITUTE Content-Type $text/html
FilterChain replace

Substitute "s|</head>| <script type=\"text/javascript\">window.bandeau_ENT = { current: \"xxx\"}; </script><script async src=\"https://bandeau-ENT.univ.fr/bandeau-ENT-loader.js\"></script> </head>|"
```

### window.bandeau_ENT options

* current, currentAppIds
* no_titlebar
* hide_menu
* account_links

* logout: used to find the logout button. bandeau's logout will trigger a click on app's logout button
* login
* is_logged

* ping_to_increase_session_timeout
* quirks
* div_id, div_is_uid


Implementation tips
-------------------

### ```bandeau-ENT-loader.js```

Small javascript loader calling ```bandeau-ENT-js.php``` .
If a cached bandeau is found in ```localStorage```, it will use it.

### ```bandeau-ENT-js.php```

PHP code generating code that will display the bandeau.

It tries to authenticate the user and then computes its "layout".

It bundles various files to reduce the number of requests: ```bandeau-ENT-static.js```, ```bandeau-ENT.css```, ```bandeau-ENT-desktop.css```

### ```bandeau-ENT-static.js```

Code that displays the bandeau using various data computed/bundled by ```bandeau-ENT-js.php```

A neat feature is "background" update: the localStorage version is used, but if it is old, an updated version is requested from server and the bandeau is updated if there is a change.

### ```detectReload.php```

Small helper script to detect if the user wants to reload the page.

When detected, code in ```bandeau-ENT-static.js``` will trigger an update of the bandeau (it will first display the localStorage version, but will asap try to get updated version)

### ```redirect.php```

Small script used to redirect to an application using its code/fname.
For uportal 3, it is useful since it handles the computing of the activeTab (would not be needed on uportal 4)
