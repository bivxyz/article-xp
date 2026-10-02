# SEOblox compatibility tests

Use Node.js 24.18 or newer:

```sh
npm ci
npm test
PHP_VERSION=7.4 WP_VERSION=6.2 npm run test:php
WP_VERSION=latest npm run test:php
```

The Node suite checks builder placement and the block editor's registrations and
meta edits. The PHP suite uses WordPress Playground's PHP runtime and a real,
disposable WordPress installation backed by SQLite in memory. No existing site,
local database, or WooGEO plugin is loaded or changed. Network access is required
to download WordPress and the pinned SQLite integration plugin.

The runner installs SEOblox fresh, then activates and renders the original
Article XP 1.0.5 code from commit `78cb57008777057eb0464b26648ee736fe6e695e`.
It deactivates that plugin and loads SEOblox without reactivation to test upgrade
initialization. It compares settings and normalized output and checks editor REST
load/save/reload, both generations of blocks and shortcodes, all seven deprecated
filters, visibility/placement, schema hooks, and commerce exclusion. Git history
must include that baseline commit (CI checks out full history).

Tests and npm dependencies are development-only. To produce a WordPress upload
ZIP from a committed tree, package only the entrypoint, readme, includes, and assets:

```sh
git archive --format=zip --prefix=seoblox/ --output=seoblox-2.0.0.zip HEAD seoblox.php readme.txt includes assets
```

The archive contains no tests, npm dependencies, or WooGEO code.
