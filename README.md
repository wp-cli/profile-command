runcommand/profile
==================

Quickly identify what's slow with WordPress.

[![CircleCI](https://circleci.com/gh/runcommand/profile/tree/master.svg?style=svg&circle-token=d916e588bf7c8ac469a3bd01930cf9eed886debe)](https://circleci.com/gh/runcommand/profile/tree/master)

Quick links: [Overview](#overview) | [Using](#using) | [Installing](#installing) | [Support](#support)

## Overview

`wp profile` monitors key performance indicators of the WordPress execution process to help you quickly identify points of slowness.

Save hours diagnosing slow WordPress sites with `wp profile`. Because you can easily run it on any server that supports WP-CLI, `wp profile` compliments Xdebug and New Relic by pointing you in the right direction for further debugging. And, because it's a WP-CLI command, using `wp profile` means you don't have to install a plugin and deal with the painful dashboard of a slow WordPress site.

First, run `wp profile stage` to see metrics for each stage of the WordPress load process:

```
$ wp profile stage
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
| stage      | time    | query_time | query_count | cache_ratio | cache_hits | cache_misses | hook_time | hook_count | request_time | request_count |
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
| bootstrap  | 0.7597s | 0.0052s    | 14          | 93.21%      | 357        | 26           | 0.3328s   | 2717       | 0s           | 0             |
| main_query | 0.0131s | 0.0004s    | 3           | 94.29%      | 33         | 2            | 0.0065s   | 78         | 0s           | 0             |
| template   | 0.7041s | 0.0192s    | 147         | 92.16%      | 2350       | 200          | 0.6982s   | 6130       | 0s           | 0             |
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
| total (3)  | 1.477s  | 0.0248s    | 164         | 93.22%      | 2740       | 228          | 1.0375s   | 8925       | 0s           | 0             |
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
```

Then, use `wp profile stage bootstrap` to dive into higher fidelity of a particular stage. Include the `--spotlight` flag to filter out the zero-ish results.

```
$ wp profile stage bootstrap --spotlight
+--------------------------+----------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| hook                     | callback_count | time    | query_time | query_count | cache_ratio | cache_hits | cache_misses | request_time | request_count |
+--------------------------+----------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| muplugins_loaded:before  |                | 0.1644s | 0.0017s    | 1           | 40%         | 2          | 3            | 0s           | 0             |
| muplugins_loaded         | 2              | 0.0005s | 0s         | 0           | 50%         | 1          | 1            | 0s           | 0             |
| plugins_loaded:before    |                | 0.1771s | 0.0008s    | 6           | 77.63%      | 59         | 17           | 0s           | 0             |
| plugins_loaded           | 14             | 0.0887s | 0s         | 0           | 100%        | 104        | 0            | 0s           | 0             |
| after_setup_theme:before |                | 0.043s  | 0s         | 0           | 100%        | 26         | 0            | 0s           | 0             |
| init                     | 82             | 0.1569s | 0.0018s    | 7           | 96.88%      | 155        | 5            | 0s           | 0             |
| wp_loaded:after          |                | 0.027s  | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
+--------------------------+----------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| total (7)                | 98             | 0.6575s | 0.0043s    | 14          | 77.42%      | 347        | 26           | 0s           | 0             |
+--------------------------+----------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
```

Lastly, when you've found a specific hook you'd like to assess, use `wp profile hook <hook>`. Include the `--fields=<fields>` argument to focus on certain fields.

```
$ wp profile hook plugins_loaded --fields=callback,location,time
+------------------------------------------------------------+-----------------------------------------------------------------+---------+
| callback                                                   | location                                                        | time    |
+------------------------------------------------------------+-----------------------------------------------------------------+---------+
| wp_maybe_load_widgets()                                    | wp-includes/functions.php:3501                                  | 0.0051s |
| wp_maybe_load_embeds()                                     | wp-includes/embed.php:162                                       | 0.0004s |
| VaultPress_Hotfixes->protect_jetpack_402_from_oembed_xss() | vaultpress/class.vaultpress-hotfixes.php:124                    | 0s      |
| _wp_customize_include()                                    | wp-includes/theme.php:2052                                      | 0s      |
| EasyRecipePlus->pluginsLoaded()                            | easyrecipeplus/lib/EasyRecipePlus.php:125                       | 0.0015s |
| Gamajo\GenesisHeaderNav\genesis_header_nav_i18n()          | genesis-header-nav/genesis-header-nav.php:61                    | 0.0008s |
| DS_Public_Post_Preview::init()                             | public-post-preview/public-post-preview.php:52                  | 0.0001s |
| wpseo_load_textdomain()                                    | wordpress-seo-premium/wp-seo-main.php:222                       | 0.0006s |
| load_yoast_notifications()                                 | wordpress-seo-premium/wp-seo-main.php:381                       | 0.0018s |
| wpseo_init()                                               | wordpress-seo-premium/wp-seo-main.php:240                       | 0.0313s |
| wpseo_premium_init()                                       | wordpress-seo-premium/wp-seo-premium.php:79                     | 0.002s  |
| wpseo_frontend_init()                                      | wordpress-seo-premium/wp-seo-main.php:274                       | 0.0007s |
| Black_Studio_TinyMCE_Plugin->load_compatibility()          | black-studio-tinymce-widget/black-studio-tinymce-widget.php:206 | 0.002s  |
| Jetpack::load_modules()                                    | jetpack/class.jetpack.php:1672                                  | 0.0549s |
+------------------------------------------------------------+-----------------------------------------------------------------+---------+
| total (14)                                                 |                                                                 | 0.1012s |
+------------------------------------------------------------+-----------------------------------------------------------------+---------+
```

Et voila! You've identified some of the sources of slowness.

## Using

This package implements the following commands:

### wp profile stage

Profile each stage of the WordPress load process (bootstrap, main_query, template).

~~~
wp profile stage [<stage>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>]
~~~

**OPTIONS**

	[<stage>]
		Drill down into a specific stage.

	[--all]
		Expand upon all stages.

	[--spotlight]
		Filter out logs with zero-ish values from the set.

	[--url=<url>]
		Execute a request against a specified URL. Defaults to the home URL.

	[--fields=<fields>]
		Limit the output to specific fields. Default is all fields.

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - json
		  - yaml
		  - csv
		---



### wp profile hook

Profile key metrics for WordPress hooks (actions and filters).

~~~
wp profile hook [<hook>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>]
~~~

In order to profile callbacks on a specific hook, the action or filter
will need to execute during the course of the request.

**OPTIONS**

	[<hook>]
		Drill into key metrics of callbacks on a specific WordPress hook.

	[--all]
		Profile callbacks for all WordPress hooks.

	[--spotlight]
		Filter out logs with zero-ish values from the set.

	[--url=<url>]
		Execute a request against a specified URL. Defaults to the home URL.

	[--fields=<fields>]
		Display one or more fields.

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - json
		  - yaml
		  - csv
		---



### wp profile eval

Profile arbitrary code execution.

~~~
wp profile eval <php-code> [--fields=<fields>] [--format=<format>]
~~~

Code execution happens after WordPress has loaded entirely, which means
you can use any utilities defined in WordPress, active plugins, or the
current theme.

**OPTIONS**

	<php-code>
		The code to execute, as a string.

	[--fields=<fields>]
		Display one or more fields.

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - json
		  - yaml
		  - csv
		---



### wp profile eval-file

Profile execution of an arbitrary file.

~~~
wp profile eval-file <file> [--fields=<fields>] [--format=<format>]
~~~

File execution happens after WordPress has loaded entirely, which means
you can use any utilities defined in WordPress, active plugins, or the
current theme.

**OPTIONS**

	<file>
		The path to the PHP file to execute and profile.

	[--fields=<fields>]
		Display one or more fields.

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - json
		  - yaml
		  - csv
		---

## Installing

`wp profile` is available to [runcommand gold and silver subscribers](https://runcommand.io/pricing/), or you can purchase a single-seat updates and support subscription for [$129 per year](https://runcommand.memberful.com/checkout?plan=16079).

Once you've signed up, you can install `wp profile` with:

```
$ wp package install profile.zip
```

If you have a Github developer seat, you can also run:

```
$ wp package install git@github.com:runcommand/profile.git
```

See documentation for [alternative installation instructions](https://runcommand.io/to/require-file-wp-cli-yml/).

## Support

Support is available to paying [runcommand](https://runcommand.io/) customers.

Have access to [Sparks](https://github.com/runcommand/sparks/), the runcommand issue tracker? Feel free to [open a new issue](https://github.com/runcommand/sparks/issues/new).

Think you’ve found a bug? Before you create a new issue, you should [search existing issues](https://github.com/runcommand/sparks/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version. Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/runcommand/sparks/issues/new) with description of what you were doing, what you saw, and what you expected to see.

Want to contribute a new feature? Please first [open a new issue](https://github.com/runcommand/sparks/issues/new) to discuss whether the feature is a good fit for the project. Once you've decided to work on a pull request, please include [functional tests](https://wp-cli.org/docs/pull-requests/#functional-tests) and follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

Don't have access to Sparks? You can also email [support@runcommand.io](mailto:support@runcommand.io) with general questions, bug reports, and feature suggestions.


