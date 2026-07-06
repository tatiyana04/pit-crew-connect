<?php
/*
 * PitCrew Connect Day 6 deployment status page.
 *
 * Use this page after configuring the Application Load Balancer and Auto Scaling Group
 * to confirm that the website is being served from an EC2 web server created from the AMI.
 * This page does not connect to the database, so it is safe to use for infrastructure checks.
 */
http_response_code(200);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$hostname = gethostname() ?: 'Unknown host';
$serverAddress = $_SERVER['SERVER_ADDR'] ?? 'Unknown server address';
$requestHost = $_SERVER['HTTP_HOST'] ?? 'Unknown request host';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/deployment-status.php';
$forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not forwarded';
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http');
$time = date('Y-m-d H:i:s T');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PitCrew Connect Deployment Status</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, Arial, sans-serif;
            background: #07111f;
            color: #0f172a;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top right, rgba(249, 115, 22, 0.26), transparent 32%),
                linear-gradient(135deg, #020617 0%, #0f172a 55%, #1e293b 100%);
        }

        .status-card {
            width: min(860px, 100%);
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }

        .status-hero {
            padding: 30px;
            background: linear-gradient(135deg, #f97316, #facc15);
            color: #111827;
        }

        .status-hero span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.58);
            font-size: 13px;
            font-weight: 800;
        }

        h1 {
            margin: 14px 0 8px;
            font-size: clamp(30px, 5vw, 46px);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        p {
            margin: 0;
            color: #334155;
            line-height: 1.6;
        }

        .status-body {
            padding: 28px 30px 32px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 22px;
        }

        .status-item {
            padding: 16px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #f8fafc;
        }

        .status-item span {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .status-item strong {
            display: block;
            color: #0f172a;
            overflow-wrap: anywhere;
            font-size: 15px;
        }

        .ok-line {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 22px;
            padding: 14px 16px;
            border-radius: 18px;
            background: #dcfce7;
            color: #166534;
            font-weight: 800;
        }

        .ok-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.14);
        }

        @media (max-width: 680px) {
            .status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="status-card">
        <section class="status-hero">
            <span>Day 6 AWS Deployment Check</span>
            <h1>PitCrew Connect is running.</h1>
            <p>This page helps confirm that the AMI, Load Balancer, Launch Template, and Auto Scaling deployment are serving the website correctly.</p>
        </section>

        <section class="status-body">
            <div class="ok-line"><span class="ok-dot"></span>HTTP 200 OK - Web server is responding</div>

            <div class="status-grid">
                <div class="status-item">
                    <span>EC2 hostname</span>
                    <strong><?= h($hostname) ?></strong>
                </div>
                <div class="status-item">
                    <span>Server address</span>
                    <strong><?= h($serverAddress) ?></strong>
                </div>
                <div class="status-item">
                    <span>Request host</span>
                    <strong><?= h($requestHost) ?></strong>
                </div>
                <div class="status-item">
                    <span>Request URI</span>
                    <strong><?= h($requestUri) ?></strong>
                </div>
                <div class="status-item">
                    <span>Forwarded client</span>
                    <strong><?= h($forwardedFor) ?></strong>
                </div>
                <div class="status-item">
                    <span>Protocol</span>
                    <strong><?= h($forwardedProto) ?></strong>
                </div>
                <div class="status-item">
                    <span>Server time</span>
                    <strong><?= h($time) ?></strong>
                </div>
                <div class="status-item">
                    <span>Health check path</span>
                    <strong>/health.php</strong>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
