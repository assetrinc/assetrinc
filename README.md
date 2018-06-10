# assetrinc

assetrinc */aˈsentrik/* An unusually simple way to compile and serve web assets

## Installation

The recommended method for installing assetrinc is via Composer.  If you are not familiar with Composer, check out the [Composer documentation](http://getcomposer.org).

Assetrinc's package name is `assetrinc/assetrinc`.

It is highly recommended that you require a specific version of assetrinc in your composer.json for now.  That is, do not include `0.0.*` or `~0.0.6`, but instead include `0.0.6`.  Assetrinc is still undergoing research and development, so backwards incompatible changes are still likely to occur between versions.

## Basic Usage

### Manifest Files

Manifest files are CSS and JS files with special comments that list CSS or JS files to include.

Assetrinc uses [Sprocketeer](http://github.com/zacharyrankin/sprocketeer) to parse manifest files.  Sprocketeer manifest files are similar to Ruby's Sprockets library manifest files, but keep in mind that Sprocketeer simplifies behavior by using named category paths instead of search paths.

### Example Code

```php
<?php

require 'vendor/autoload.php';

use Assetrinc\AssetService;

$asset_service = new AssetService(
    // the category paths to use when loading manifest files
    array(
        'core' => __DIR__ . '/assets',
        'bower' => __DIR__ . '/bower_components',
    ),
    // the base route assets are served from
    '/assets',
    array('debug' => false)
);

// in the controller that serves your /assets/{name} route
header("Content-Type: " . $asset_service->getContentType($name));
echo $asset_service->getContent($name);

// in your templates, generate JS/CSS tags using
echo $asset_service->jsTag("core/application.js");
echo $asset_service->cssTag("core/application.css");
```

## Contributors

Project Leaders

 - [Matt Light](http://github.com/lightster)
 - [Zachary Rankin](http://github.com/zacharyrankin)
