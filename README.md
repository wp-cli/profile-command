wp-cli/profile-command
======================

Quickly identify what's slow with WordPress.

[![Build Status](https://travis-ci.org/wp-cli/profile-command.svg?branch=master)](https://travis-ci.org/wp-cli/profile-command)

Quick links: [Overview](#overview) | [Using](#using) | [Installing](#installing) | [Contributing](#contributing)

## Overview

`wp profile` monitors key performance indicators of the WordPress execution process to help you quickly identify points of slowness.

Save hours diagnosing slow WordPress sites. Because you can easily run it on any server that supports WP-CLI, `wp profile` compliments Xdebug and New Relic by pointing you in the right direction for further debugging. Because runs on the command line, using `wp profile` means you don't have to install a plugin and deal with the painful dashboard of a slow WordPress site. And, because it's a WP-CLI command, `wp profile` makes it easy to perfom hard tasks (e.g. [profiling a WP REST API response](https://runcommand.io/to/profile-wp-rest-api/)).

[Identify why WordPress is slow in just a few steps](https://runcommand.io/to/identify-wordpress-slowness/) with `wp profile`.

## Using

This package implements the following commands:

### wp profile stage

Profile each stage of the WordPress load process (bootstrap, main_query, template).

~~~
wp profile stage [<stage>] [--all] [--spotlight] [--url=<url>] [--fields=<fields>] [--format=<format>]
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

```
# `wp profile stage` gives an overview of each stage.
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

# Then, dive into hooks for each stage with `wp profile stage <stage>`
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
```

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
wp profile eval <php-code> [--hook[=<hook>]] [--fields=<fields>] [--format=<format>]
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



### wp profile eval-file

Profile execution of an arbitrary file.

~~~
wp profile eval-file <file> [--hook[=<hook>]] [--fields=<fields>] [--format=<format>]
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

## Installing

Installing this package requires WP-CLI's latest stable release. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with:

    wp package install git@github.com:wp-cli/profile-command.git

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

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience.


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
