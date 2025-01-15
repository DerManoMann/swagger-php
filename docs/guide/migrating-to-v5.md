# Migrating to v5

## Overview

v5 is mostly a cleanup release with updated dependencies. The main changes are:

* Minimum required PHP version is now 7.4
* The legacy `TokenAnalyser` and the `--legacy` CLI option have been removed
* Defaults now prefer attributes over annotations
* PHP parsing now uses `nicic/php-parser`
* Removal of deprecated features
  * empty/unused `ProcessorInterface`
  * `Context::clone()` and `Context::detect()`

For most installations upgrading should not require any changes.