php-cackle-sync
===============

PHP Sync Cackle Comments

The pure PHP  example for getting comments from Cackle to local server.

How it works:

1. Custom cron execute script every 60 seconds when page load

2. Only new comments for each cron task

3. Parent comments in local db have the local comment's ID

4. All comment from cackle will saved to your database