#!/bin/zsh
# Runs due queued jobs, like cron does on a server. In local development a launchd
# agent calls this every minute (see docs/04-data-import.md, "Running jobs locally").
cd "$(dirname "$0")/.." || exit 1
PHP=${PHP:-/opt/homebrew/bin/php}

# The large queue (nightly SEC import, ~45 min) runs in the background so it doesn't hold up
# quick jobs; a worker started while it is still running finds the job locked and exits.
$PHP vendor/bin/sake tasks:ProcessJobQueueTask --queue=large &
$PHP vendor/bin/sake tasks:ProcessJobQueueTask --queue=queued
wait
