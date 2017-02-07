#!/bin/bash

set -ex

BEHAT_TAGS=$(php utils/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --strict
