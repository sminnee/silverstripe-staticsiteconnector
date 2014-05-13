# SilverStripe Static Site Connector

## Authors

* [Sam Minnee](https://github.com/sminnee)
* [Russell Michell](https://github.com/phptek)
* [Mike Parkhill](https://github.com/mparkhill)
* [Stig Lindqvist](https://github.com/stojg)

## Introduction

This module allows you to extract content from another website by crawling and parsing
its DOM structure and transforms it directly into native SilverStripe objects, then
imports those objects into SilverStripe's database as though they had been created
via the CMS.

Although this has the disadvantage of leaving it unable to extract any information
or structure that _isn't_ represented in the site's markup, it means no special access
or reliance on particular back-end systems is required. This makes the module suited
for legacy and experimental site-imports, as well as connections to websites generated
by obscure CMS's.

## How it works

Importing a site is a __2__ or __3__ step process (Depending on user-selection).

 1. Crawl
 2. Import
 3. Rewrite Links (Automatic, if selected in step 2.)
 
A list of URLs are fetched and extracted from the site via [PHPCrawl](http://cuab.de/),
and cached in a text file under the assets directory.

Each cached URL corresponds to a page or asset (css, image, pdf etc) that the module
will attempt to import into native SilverStripe objects e.g. `SiteTree` and `File`.

Page content is imported page-by-page using cUrl, and the desired DOM elements
extracted via configurable CSS selectors via [phpQuery](http://code.google.com/p/phpquery/)
which is leveraged for this purpose.

## Migration

See the included [migration documentation](docs/en/migration.md) for detailed
instruction on migrating a legacy site into SilverStripe using the module.

## Installation

This module requires the [PHP Sempahore](http://php.net/manual/en/book.sem.php)
functions to work. These are installed by default on Debian and some OS/X PHP
distributions, but if you're using Macports you'll need to add the `+ipc` flag
when installing `php5`.

If compiling PHP from source you need to pass three additional flags to PHP's
configure script:

	./configure <usual flags> '--enable-sysvsem' '--enable-sysvshm' '--enable-sysvmsg'

Once that's done, you can use [Composer](http://getcomposer.org) to add the module
to your SilverStripe project:

    #> composer require silverstripe/staticsiteconnector

Please see the included [Migration](docs/en/migration.md) document, that describes
exactly how to configure the tool to perform a site-scrape / migration.

There is also an [example database-dump](docs/en/example.sql) (MySQL/MariaDB only)
provided which you can import into your DB to get you up and running quickly.

License
-------

This code is available under the BSD license, with the exception of the [PHPCrawl](http://cuab.de/)
library, bundled with this module which is GPL version 2.
