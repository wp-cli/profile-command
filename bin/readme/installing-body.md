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
