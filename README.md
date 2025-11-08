wp-cli/profile-command
======================

Quickly identify what's slow with WordPress.

[![Testing](https://github.com/wp-cli/profile-command/actions/workflows/testing.yml/badge.svg)](https://github.com/wp-cli/profile-command/actions/workflows/testing.yml)

Quick links: [Overview](#overview) | [Using](#using) | [Installing](#installing) | [Contributing](#contributing)

## Overview

`wp profile` monitors key performance indicators of the WordPress execution process to help you quickly identify points of slowness.

Save hours diagnosing slow WordPress sites. Because you can easily run it on any server that supports WP-CLI, `wp profile` compliments Xdebug and New Relic by pointing you in the right direction for further debugging. Because it runs on the command line, using `wp profile` means you don't have to install a plugin and deal with the painful dashboard of a slow WordPress site. And, because it's a WP-CLI command, `wp profile` makes it easy to perform hard tasks (e.g. [profiling a WP REST API response](https://danielbachhuber.com/tip/profile-wp-rest-api/)).

[Identify why WordPress is slow in just a few steps](https://danielbachhuber.com/tip/identify-wordpress-slowness/) with `wp profile`.

## Using

This package implements the following commands:

### wp profile stage

Profile each stage of the WordPress load process (bootstrap, main_query, template).

~~~
wp profile stage [<stage>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
~~~

When WordPress handles a request from a browser, it’s essentially
executing as one long PHP script. `wp profile stage` breaks the script
into three stages:

* **bootstrap** is where WordPress is setting itself up, loading plugins
and the main theme, and firing the `init` hook.
* **main_query** is how WordPress transforms the request (e.g. `/2016/10/21/moms-birthday/`)
into the primary WP_Query.
* **template** is where WordPress determines which theme template to
render based on the main query, and renders it.

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

	[--order=<order>]
		Ascending or Descending order.
		---
		default: ASC
		options:
		  - ASC
		  - DESC
		---

	[--orderby=<fields>]
		Set orderby which field.

**EXAMPLES**

    # See an overview for each stage of the load process.
    $ wp profile stage --fields=stage,time,cache_ratio
    +------------+---------+-------------+
    | stage      | time    | cache_ratio |
    +------------+---------+-------------+
    | bootstrap  | 0.7994s | 93.21%      |
    | main_query | 0.0123s | 94.29%      |
    | template   | 0.792s  | 91.23%      |
    +------------+---------+-------------+
    | total (3)  | 1.6037s | 92.91%      |
    +------------+---------+-------------+

    # Dive into hook performance for a given stage.
    $ wp profile stage bootstrap --fields=hook,time,cache_ratio --spotlight
    +--------------------------+---------+-------------+
    | hook                     | time    | cache_ratio |
    +--------------------------+---------+-------------+
    | muplugins_loaded:before  | 0.2335s | 40%         |
    | muplugins_loaded         | 0.0007s | 50%         |
    | plugins_loaded:before    | 0.2792s | 77.63%      |
    | plugins_loaded           | 0.1502s | 100%        |
    | after_setup_theme:before | 0.068s  | 100%        |
    | init                     | 0.2643s | 96.88%      |
    | wp_loaded:after          | 0.0377s |             |
    +--------------------------+---------+-------------+
    | total (7)                | 1.0335s | 77.42%      |
    +--------------------------+---------+-------------+



### wp profile hook

Profile key metrics for WordPress hooks (actions and filters).

~~~
wp profile hook [<hook>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
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

	[--order=<order>]
		Ascending or Descending order.
		---
		default: ASC
		options:
		  - ASC
		  - DESC
		---

	[--orderby=<fields>]
		Set orderby which field.

**EXAMPLES**

    # Profile a hook.
    $ wp profile hook template_redirect --fields=callback,cache_hits,cache_misses
    +--------------------------------+------------+--------------+
    | callback                       | cache_hits | cache_misses |
    +--------------------------------+------------+--------------+
    | _wp_admin_bar_init()           | 0          | 0            |
    | wp_old_slug_redirect()         | 0          | 0            |
    | redirect_canonical()           | 5          | 0            |
    | WP_Sitemaps->render_sitemaps() | 0          | 0            |
    | rest_output_link_header()      | 3          | 0            |
    | wp_shortlink_header()          | 0          | 0            |
    | wp_redirect_admin_locations()  | 0          | 0            |
    +--------------------------------+------------+--------------+
    | total (7)                      | 8          | 0            |
    +--------------------------------+------------+--------------+



### wp profile queries

Profile database queries and their execution time.

~~~
wp profile queries [--url=<url>] [--hook=<hook>] [--callback=<callback>] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
~~~

Displays all database queries executed during a WordPress request,
along with their execution time and caller information. You can filter
queries to only show those executed during a specific hook or by a
specific callback.

**OPTIONS**

	[--url=<url>]
		Execute a request against a specified URL. Defaults to the home URL.

	[--hook=<hook>]
		Filter queries to only show those executed during a specific hook.

	[--callback=<callback>]
		Filter queries to only show those executed by a specific callback.

	[--fields=<fields>]
		Limit the output to specific fields.

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

	[--order=<order>]
		Ascending or Descending order.
		---
		default: ASC
		options:
		  - ASC
		  - DESC
		---

	[--orderby=<fields>]
		Set orderby which field.

**EXAMPLES**

    # Show all queries with their execution time
    $ wp profile queries --fields=query,time
    +--------------------------------------+---------+
    | query                                | time    |
    +--------------------------------------+---------+
    | SELECT option_value FROM wp_options  | 0.0001s |
    | SELECT * FROM wp_posts WHERE ID = 1  | 0.0003s |
    +--------------------------------------+---------+
    | total (2)                            | 0.0004s |
    +--------------------------------------+---------+

    # Show queries executed during the 'init' hook
    $ wp profile queries --hook=init --fields=query,time,callback
    +--------------------------------------+---------+------------------+
    | query                                | time    | callback         |
    +--------------------------------------+---------+------------------+
    | SELECT * FROM wp_users               | 0.0002s | my_init_func()   |
    +--------------------------------------+---------+------------------+
    | total (1)                            | 0.0002s |                  |
    +--------------------------------------+---------+------------------+

    # Show queries executed by a specific callback
    $ wp profile queries --callback='WP_Query->get_posts()' --fields=query,time
    +--------------------------------------+---------+
    | query                                | time    |
    +--------------------------------------+---------+
    | SELECT * FROM wp_posts               | 0.0004s |
    +--------------------------------------+---------+
    | total (1)                            | 0.0004s |
    +--------------------------------------+---------+



### wp profile eval

Profile arbitrary code execution.

~~~
wp profile eval <php-code> [--hook[=<hook>]] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
~~~

Code execution happens after WordPress has loaded entirely, which means
you can use any utilities defined in WordPress, active plugins, or the
current theme.

**OPTIONS**

	<php-code>
		The code to execute, as a string.

	[--hook[=<hook>]]
		Focus on key metrics for all hooks, or callbacks on a specific hook.

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

	[--order=<order>]
		Ascending or Descending order.
		---
		default: ASC
		options:
		  - ASC
		  - DESC
		---

	[--orderby=<fields>]
		Set orderby which field.

**EXAMPLES**

    # Profile a function that makes one HTTP request.
    $ wp profile eval 'wp_remote_get( "https://www.apple.com/" );' --fields=time,cache_ratio,request_count
    +---------+-------------+---------------+
    | time    | cache_ratio | request_count |
    +---------+-------------+---------------+
    | 0.1009s | 100%        | 1             |
    +---------+-------------+---------------+



### wp profile eval-file

Profile execution of an arbitrary file.

~~~
wp profile eval-file <file> [--hook[=<hook>]] [--fields=<fields>] [--format=<format>] [--order=<order>] [--orderby=<fields>]
~~~

File execution happens after WordPress has loaded entirely, which means
you can use any utilities defined in WordPress, active plugins, or the
current theme.

**OPTIONS**

	<file>
		The path to the PHP file to execute and profile.

	[--hook[=<hook>]]
		Focus on key metrics for all hooks, or callbacks on a specific hook.

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

	[--order=<order>]
		Ascending or Descending order.
		---
		default: ASC
		options:
		  - ASC
		  - DESC
		---

	[--orderby=<fields>]
		Set orderby which field.

**EXAMPLES**

    # Profile from a file `request.php` containing `<?php wp_remote_get( "https://www.apple.com/" );`.
    $ wp profile eval-file request.php --fields=time,cache_ratio,request_count
    +---------+-------------+---------------+
    | time    | cache_ratio | request_count |
    +---------+-------------+---------------+
    | 0.1009s | 100%        | 1             |
    +---------+-------------+---------------+

## Installing

Installing this package requires WP-CLI v2.12 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install wp-cli/profile-command:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install wp-cli/profile-command:dev-main
```

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/profile-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/profile-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/profile-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
