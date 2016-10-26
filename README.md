runcommand/profile
==================

Quickly identify what's slow with WordPress.

[![Buy Now](https://runcommand.io/wp-content/themes/runcommand-theme/bin/shields/buy-now-129.svg)](https://runcommand.memberful.com/checkout?plan=16079)
[![CircleCI](https://circleci.com/gh/runcommand/profile/tree/master.svg?style=svg&circle-token=d916e588bf7c8ac469a3bd01930cf9eed886debe)](https://circleci.com/gh/runcommand/profile/tree/master)

Quick links: [Overview](#overview) | [Using](#using) | [Installing](#installing) | [Support](#support)

## Overview

`wp profile` monitors key performance indicators of the WordPress execution process to help you quickly identify points of slowness.

Save hours diagnosing slow WordPress sites with `wp profile`. Because you can easily run it on any server that supports WP-CLI, `wp profile` compliments Xdebug and New Relic by pointing you in the right direction for further debugging. And, because it's a WP-CLI command, using `wp profile` means you don't have to install a plugin and deal with the painful dashboard of a slow WordPress site.

First, run `wp profile` to see metrics for each stage of the WordPress load process:

```
$ wp profile
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
| stage      | time    | query_time | query_count | cache_ratio | cache_hits | cache_misses | hook_time | hook_count | request_time | request_count |
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
| bootstrap  | 2.0408s | 0.0365s    | 15          | 93.21%      | 412        | 30           | 0.9299s   | 3097       | 0s           | 0             |
| main_query | 0.0123s | 0.0004s    | 3           | 94.29%      | 33         | 2            | 0.0098s   | 79         | 0s           | 0             |
| template   | 0.305s  | 0.0175s    | 179         | 91.02%      | 2636       | 260          | 0.1125s   | 7777       | 0s           | 0             |
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
| total      | 2.3582s | 0.0544s    | 197         | 92.84%      | 3081       | 292          | 1.0522s   | 10953      | 0s           | 0             |
+------------+---------+------------+-------------+-------------+------------+--------------+-----------+------------+--------------+---------------+
```

Then, use `--stage=<stage>` to dive into higher fidelity of a particular stage:

```
$ wp profile --stage=bootstrap
+-------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| hook              | time    | query_time | query_count | cache_ratio | cache_hits | cache_misses | request_time | request_count |
+-------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
|                   | 0.3558s | 0.0014s    | 1           | 25%         | 1          | 3            | 0s           | 0             |
| muplugins_loaded  | 0.0002s | 0s         | 0           | 50%         | 1          | 1            | 0s           | 0             |
|                   | 0.8075s | 0.0007s    | 6           | 73.68%      | 56         | 20           | 0s           | 0             |
| plugins_loaded    | 0.4271s | 0s         | 0           | 100%        | 138        | 0            | 0s           | 0             |
|                   | 0.0046s | 0s         | 0           | 100%        | 6          | 0            | 0s           | 0             |
| setup_theme       | 0s      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
|                   | 0.2401s | 0s         | 0           | 100%        | 26         | 0            | 0s           | 0             |
| after_setup_theme | 0.0007s | 0s         | 0           | 100%        | 4          | 0            | 0s           | 0             |
|                   | 0.0001s | 0s         | 0           | 100%        | 2          | 0            | 0s           | 0             |
| init              | 0.2922s | 0.0016s    | 8           | 96.3%       | 156        | 6            | 0s           | 0             |
|                   | 0.0277s | 0s         | 0           | 100%        | 2          | 0            | 0s           | 0             |
| wp_loaded         | 0.01s   | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
+-------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| total             | 2.166s  | 0.0037s    | 15          | 84.5%       | 392        | 30           | 0s           | 0             |
+-------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
```

Lastly, when you've found a specific hook you'd like to assess, use `--hook=<hook>`:

```
$ wp profile --hook=plugins_loaded
+------------------------------------------------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| callback                                                   | time    | query_time | query_count | cache_ratio | cache_hits | cache_misses | request_time | request_count |
+------------------------------------------------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| wp_maybe_load_widgets()                                    | 0.0309s | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| wp_maybe_load_embeds()                                     | 0.0001s | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| VaultPress_Hotfixes->protect_jetpack_402_from_oembed_xss() | 0s      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| _wp_customize_include()                                    | 0s      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| Debug_Bar_Remote_Requests()                                | 0s      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| EasyRecipePlus->pluginsLoaded()                            | 0.0029s | 0s         | 0           | 100%        | 4          | 0            | 0s           | 0             |
| Gamajo\GenesisHeaderNav\genesis_header_nav_i18n()          | 0.0007s | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| DS_Public_Post_Preview::init()                             | 0s      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| wpseo_load_textdomain()                                    | 0.0006s | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| load_yoast_notifications()                                 | 0.003s  | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| wpseo_init()                                               | 0.101s  | 0s         | 0           | 100%        | 70         | 0            | 0s           | 0             |
| wpseo_frontend_init()                                      | 0.0003s | 0s         | 0           | 100%        | 2          | 0            | 0s           | 0             |
| Black_Studio_TinyMCE_Plugin->load_compatibility()          | 0.0122s | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
| Jetpack::load_modules()                                    | 0.2706s | 0s         | 0           | 100%        | 62         | 0            | 0s           | 0             |
| function(){}                                               | 0s      | 0s         | 0           |             | 0          | 0            | 0s           | 0             |
+------------------------------------------------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
| total                                                      | 0.4226s | 0s         | 0           | 100%        | 138        | 0            | 0s           | 0             |
+------------------------------------------------------------+---------+------------+-------------+-------------+------------+--------------+--------------+---------------+
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

[Get access to `wp profile` for only $129 per year](https://runcommand.memberful.com/checkout?plan=16079). Purchasing an annual subscription locks you into this price for as long as you stay subscribed. Subscriptions include unlimited downloads of the command, plus support and updates for the length of your subscription.

Once you've purchased a subscription, you can use the `wp profile` command with:

```
wp --require=command.php profile
```

Alternatively, you can [require the command so that it's always available to WP-CLI](https://runcommand.io/to/require-file-wp-cli-yml/) when running as the current system user:

1. Extract the package files to `~/.wp-cli/runcommand-profile`
2. Edit (or create) `~/.wp-cli/config.yml` and include the following require statement:

```
require:
  - runcommand-profile/command.php
```

## Support

Support is available to paying [runcommand](https://runcommand.io/) customers.

Have access to [Sparks](https://github.com/runcommand/sparks/), the runcommand issue tracker? Feel free to [open a new issue](https://github.com/runcommand/sparks/issues/new).

Think you’ve found a bug? Before you create a new issue, you should [search existing issues](https://github.com/runcommand/sparks/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version. Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/runcommand/sparks/issues/new) with description of what you were doing, what you saw, and what you expected to see.

Want to contribute a new feature? Please first [open a new issue](https://github.com/runcommand/sparks/issues/new) to discuss whether the feature is a good fit for the project. Once you've decided to work on a pull request, please include [functional tests](https://wp-cli.org/docs/pull-requests/#functional-tests) and follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

Don't have access to Sparks? You can also email [support@runcommand.io](mailto:support@runcommand.io) with general questions, bug reports, and feature suggestions.


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
