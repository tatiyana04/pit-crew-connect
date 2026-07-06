<?php
/*
 * PitCrew Connect ALB health check endpoint.
 *
 * This file is intentionally independent from the database and sessions.
 * The Application Load Balancer target group can use this path:
 *   /health.php
 * Expected HTTP response:
 *   200 OK
 */
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo "OK - PitCrew Connect is healthy";
?>
