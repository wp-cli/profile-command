runcommand/profile
==================

Quickly identify what's slow with WordPress.

[![runcommand premium](https://runcommand.io/wp-content/themes/runcommand-theme/bin/shields/runcommand-premium.svg)](https://runcommand.io/pricing/) [![CircleCI](https://circleci.com/gh/runcommand/profile/tree/master.svg?style=svg&circle-token=d916e588bf7c8ac469a3bd01930cf9eed886debe)](https://circleci.com/gh/runcommand/profile/tree/master)

Quick links: [Overview](#overview) | [Using](#using) | [Installing](#installing) | [Support](#support)

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


