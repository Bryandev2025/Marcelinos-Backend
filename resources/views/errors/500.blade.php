<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Something went wrong</title>
    <style>
        :root {
            --bg: #f6f2ea;
            --panel: rgba(255, 255, 255, 0.88);
            --panel-border: rgba(15, 61, 54, 0.12);
            --text: #17322d;
            --muted: #5f6f6b;
            --accent: #0f3d36;
            --accent-2: #c6a15b;
            --shadow: 0 24px 80px rgba(15, 61, 54, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(198, 161, 91, 0.18), transparent 28%),
                radial-gradient(circle at bottom right, rgba(15, 61, 54, 0.14), transparent 30%),
                linear-gradient(135deg, #f8f5ef 0%, #f3eee3 45%, #ece6d8 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(900px, 100%);
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            overflow: hidden;
            border: 1px solid var(--panel-border);
            border-radius: 28px;
            background: var(--panel);
            backdrop-filter: blur(12px);
            box-shadow: var(--shadow);
        }

        .hero {
            padding: 44px 42px;
            background:
                linear-gradient(160deg, rgba(15, 61, 54, 0.98), rgba(15, 61, 54, 0.9)),
                radial-gradient(circle at top right, rgba(198, 161, 91, 0.28), transparent 35%);
            color: #fff;
            position: relative;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto -60px -90px auto;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
        }

        .kicker {
            margin: 0 0 18px;
            font-size: 12px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.74);
        }

        .code {
            margin: 0;
            font-size: clamp(64px, 10vw, 112px);
            line-height: 0.92;
            font-weight: 800;
            letter-spacing: -0.06em;
        }

        .title {
            margin: 18px 0 12px;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.05;
            letter-spacing: -0.04em;
        }

        .description {
            margin: 0;
            max-width: 34rem;
            font-size: 15px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.84);
        }

        .status {
            margin-top: 24px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-size: 13px;
            color: rgba(255, 255, 255, 0.92);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--accent-2);
            box-shadow: 0 0 0 6px rgba(198, 161, 91, 0.18);
        }

        .actions {
            padding: 44px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
        }

        .panel-copy {
            margin: 0;
            font-size: 14px;
            line-height: 1.75;
            color: var(--muted);
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 6px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 160ms ease, box-shadow 160ms ease, opacity 160ms ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button.primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 12px 28px rgba(15, 61, 54, 0.22);
        }

        .button.secondary {
            background: rgba(15, 61, 54, 0.06);
            color: var(--accent);
            border: 1px solid rgba(15, 61, 54, 0.12);
        }

        .note {
            margin: 0;
            font-size: 12px;
            line-height: 1.6;
            color: #6b7b77;
        }

        .footer {
            grid-column: 1 / -1;
            padding: 18px 36px 26px;
            border-top: 1px solid rgba(15, 61, 54, 0.08);
            color: #6b7b77;
            font-size: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
        }

        .footer strong {
            color: var(--accent);
        }

        @media (max-width: 820px) {
            .card {
                grid-template-columns: 1fr;
            }

            .hero,
            .actions,
            .footer {
                padding-left: 24px;
                padding-right: 24px;
            }

            .hero {
                padding-top: 38px;
                padding-bottom: 34px;
            }
        }
    </style>
</head>
<body>
    <main class="card" role="main" aria-labelledby="error-title">
        <section class="hero">
            <p class="kicker">Marcelino's Resort Hotel</p>
            <p class="code">500</p>
            <h1 class="title" id="error-title">We hit a problem</h1>
            <p class="description">
                Something went wrong while loading this page.
                Please try again in a moment, or return to the dashboard.
            </p>
            <div class="status" aria-label="System status">
                <span class="status-dot" aria-hidden="true"></span>
                The team has been notified automatically.
            </div>
        </section>

        <section class="actions">
            <p class="panel-copy">
                If you were working in the admin area, your changes are still safe in the database.
                You can go back to the dashboard or return to the home page and continue from there.
            </p>

            <div class="button-row">
                <a class="button primary" href="{{ url('/admin') }}">Go to Admin Dashboard</a>
                <a class="button secondary" href="{{ url('/') }}">Go to Home Page</a>
            </div>

            <p class="note">
                If this keeps happening, check the application logs or contact the development team.
            </p>
        </section>

        <footer class="footer">
            <span><strong>Need help?</strong> Refresh and try again after a few seconds.</span>
            <span>Official server error page for Marcelino's Resort Hotel</span>
        </footer>
    </main>
</body>
</html>
