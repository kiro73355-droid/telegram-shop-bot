<?php
// Entry point so Railpack detects the PHP provider.
// The Telegram bot webhook is served by webhook.php.
http_response_code(200);
echo 'Telegram shop bot is running.';
