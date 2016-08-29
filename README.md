runcommand/profile
==================

Quickly identify what's slow with WordPress.

[![CircleCI](https://circleci.com/gh/runcommand/profile/tree/master.svg?style=svg&circle-token=d916e588bf7c8ac469a3bd01930cf9eed886debe)](https://circleci.com/gh/runcommand/profile/tree/master)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing)

## Using

~~~
wp profile [--url=<url>] [--stage=<stage>] [--hook=<hook>] [--fields=<fields>] [--format=<format>]
~~~

`wp profile` monitors key performance indicators of the WordPress
execution process to help you quickly identify where the slowness is
coming from. Because you can install and run it on any server that
supports WP-CLI, in 15 seconds or less, `wp profile` compliments Xdebug
and New Relic by pointing you in the right direction for further
debugging. And, because it's a WP-CLI command, using `wp profile` means
you don't have to install a plugin and deal with the painful dashboard
of a slow WordPress site.

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

**OPTIONS**

	[--url=<url>]
		Execute a request against a specified URL. Defaults to the home URL.

	[--stage=<stage>]
		Drill down into a specific stage.
		---
		options:
		  - bootstrap
		  - main_query
		  - template
		---

	[--hook=<hook>]
		Drill down into a specific hook.

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

Installing this package requires WP-CLI v0.23.0 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install this package with `wp package install runcommand/profile`.

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/runcommand/profile/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/runcommand/profile/issues/new) with the following:

1. What you were doing (e.g. "When I run `wp post list`").
2. What you saw (e.g. "I see a fatal about a class being undefined.").
3. What you expected to see (e.g. "I expected to see the list of posts.")

Include as much detail as you can, and clear steps to reproduce if possible.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/runcommand/profile/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, please follow our guidelines for creating a pull request to make sure it's a pleasant experience:

1. Create a feature branch for each contribution.
2. Submit your pull request early for feedback.
3. Include functional tests with your changes. [Read the WP-CLI documentation](https://wp-cli.org/docs/pull-requests/#functional-tests) for an introduction.
4. Follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
