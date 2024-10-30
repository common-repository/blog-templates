=== Blog Templates ===
Contributors: gregsurname
Tags: wpmu
Requires at least: 2.8.0
Tested up to: 2.9.2
Stable tag: 2.5.2

A WPMU plugin that sets up a template system that lets you have a template appied to new blogs.

== Description ==

This WPMU plugin sets up a template system for new blogs. When a new blog is created it can have a template applied to it. These templates allow for these blog options to be set:

* Blog options.(Privacy, Theme, Moderation, etc)
* Extra posts to be created.
* Extra pages to be created.
* Extra links to be created.
* Extra page categories to be created.

A default template can be created if all you are after is a single set of new blog defaults.

== Installation ==

To install the plugin:

1. Upload `gb-blog-templates.php` to the `/wp-content/mu-plugins/` directory
1. Go to Site Admin -> Blog Templates to configure any templates required.

If a template called 'default' is created then it will be applied to all new blogs unless another template has been selected.

The plugin adds the following interfaces/hooks:

1. An administration interface is added to the Site Admin menu.
1. A template selection field is inserted into the signup page.
1. New blogs created through the Site Admin->Blogs menu will have the template named 'default' applied.

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

