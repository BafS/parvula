# ![PARVULA](http://i.imgur.com/igAQPza.png)

> **An extremely simple & flexible CMS generated from flat files**

![screenshot](http://i.imgur.com/gsbzwgl.png)
> Parvula with the default galaxy theme

### Requirements

* PHP 5.4+
* [Composer](http://getcomposer.org/)
* URL Rewriting (recommended)

### Installation with Composer

1. Download and extract the zip
2. Run `composer install`
3. That's it !

## Usage

### Edit pages

All pages are located in `data/pages/`. You can also create sub directories if you want to organise your pages.

The basic configuration file is `data/config/site.yaml`.
There is 2 themes bundle by default: "simple" and "galaxy".

### Pages

* Pages must have a title to be listed
* Pages beginning with `_` are *hidden* pages, not listed but accessible
* You can order pages with the *index* field

### Administration

![parvula administration](http://i.imgur.com/WtDfVXu.png)

You can edit pages online at **yoursite.com/admin** (admin url can be edited in `plugins/Admin/config.php`).

Don't forget to *chmod 755* `data/pages` and *change the default password* in `plugins/Admin/config.php` !
