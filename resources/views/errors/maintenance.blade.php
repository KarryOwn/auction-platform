@php($window = \App\Models\MaintenanceWindow::query()->where('status', \App\Models\MaintenanceWindow::STATUS_ACTIVE)->orderBy('scheduled_end')->first())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Auction Platform') }} | Maintenance</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                font-family: Inter, system-ui, sans-serif;
                color: #f8fafc;
                background:
                    radial-gradient(circle at top, rgba(251, 191, 36, 0.2), transparent 35%),
                    linear-gradient(135deg, #0f172a, #1e293b 55%, #334155);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .panel {
                width: min(640px, 100%);
                background: rgba(15, 23, 42, 0.7);
                border: 1px solid rgba(251, 191, 36, 0.25);
                border-radius: 24px;
                padding: 40px 32px;
                box-shadow: 0 25px 60px rgba(15, 23, 42, 0.45);
                backdrop-filter: blur(14px);
            }
            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border-radius: 999px;
                background: rgba(251, 191, 36, 0.12);
                color: #fcd34d;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .08em;
                text-transform: uppercase;
            }
            h1 {
                margin: 20px 0 12px;
                font-size: clamp(2rem, 4vw, 3.5rem);
                line-height: 1.05;
            }
            p {
                margin: 0;
                color: #cbd5e1;
                line-height: 1.7;
            }
            .grid {
                margin-top: 28px;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 16px;
            }
            .card {
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.06);
                border: 1px solid rgba(148, 163, 184, 0.18);
                padding: 18px;
            }
            .label {
                display: block;
                color: #94a3b8;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .08em;
                margin-bottom: 8px;
            }
            .value {
                font-size: 1.25rem;
                font-weight: 700;
                color: #f8fafc;
            }
            .countdown {
                margin-top: 28px;
                font-size: clamp(1.5rem, 3vw, 2.5rem);
                font-weight: 800;
                color: #fcd34d;
            }
        </style>
    </head>
    <body>
        <section class="panel">
            <span class="eyebrow">Scheduled Maintenance</span>
            <h1>We&apos;ll be right back.</h1>
            <p>{{ $window?->message ?? 'Scheduled maintenance. Back soon.' }}</p>

            <div class="grid">
                <div class="card">
                    <span class="label">Started</span>
                    <span class="value">{{ $window?->scheduled_start?->format('M d, Y H:i') ?? 'In progress' }}</span>
                </div>
                <div class="card">
                    <span class="label">Expected Back</span>
                    <span class="value">{{ $window?->scheduled_end?->format('M d, Y H:i') ?? 'Soon' }}</span>
                </div>
            </div>

            <div class="countdown" id="maintenance-countdown">Counting down...</div>
        </section>

        <script>
            (() => {
                const target = @json($window?->scheduled_end?->toIso8601String());
                const el = document.getElementById('maintenance-countdown');

                if (!target || !el) {
                    if (el) {
                        el.textContent = 'Back as soon as possible';
                    }
                    return;
                }

                const tick = () => {
                    const diff = new Date(target).getTime() - Date.now();
                    if (diff <= 0) {
                        el.textContent = 'Maintenance ending shortly';
                        return;
                    }

                    const hours = Math.floor(diff / 3600000);
                    const minutes = Math.floor((diff % 3600000) / 60000);
                    const seconds = Math.floor((diff % 60000) / 1000);
                    el.textContent = `Estimated return in ${hours}h ${minutes}m ${seconds}s`;
                };

                tick();
                setInterval(tick, 1000);
            })();
        </script>
    </body>
</html>
