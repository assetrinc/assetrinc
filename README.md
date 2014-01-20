# assetrinc

An unusually simple way to include assets


## Example

### `./assets/main.js.assetrinc`
    js moment/moment-with-langs.js
    js foundation/js/vendor/jquery.js
    js foundation/js/foundation.min.js
    js angular/angular{,-route}.js
    coffee lidsys/app-models.coffee
    js lidsys/football.js
    js lidsys/nav.js
    js lidsys/app.js

### `./assets/main.css.assetrinc`
    css foundation/css/foundation.css
    css lidsys/app.css
    
### `./assetrinc.json`
    {
        "bundles": {
            "main.js": {
                "src": "main.js.assetrinc",
                "dest": "main.js"
            },
            "main.css": {}
        }
    }