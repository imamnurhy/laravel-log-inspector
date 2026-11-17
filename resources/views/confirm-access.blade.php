<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorize Session - Log Inspector</title>
    <link rel="stylesheet" href="{{ route('log-inspector.asset', ['path' => 'css/bootstrap.min.css']) }}">
    <link rel="stylesheet" href="{{ route('log-inspector.asset', ['path' => 'css/log-inspector.css']) }}">
</head>
<body class="log-inspector-body d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
        <div class="card-header bg-dark text-white text-center py-3">
            <h5 class="mb-0 fw-bold">🪵 Log Inspector</h5>
        </div>
        <div class="card-body p-4 text-center">
            <p class="text-muted mb-4 small">
                Anda mengakses link otentikasi sementara. Klik tombol di bawah untuk menukar link dan memulai sesi dashboard Anda.
            </p>

            <form method="POST" action="{{ route('log-inspector.access.exchange', ['token' => $token]) }}">
                @csrf
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    Lanjutkan ke Dashboard →
                </button>
            </form>
        </div>
        <div class="card-footer bg-light text-center py-2 text-muted" style="font-size: 0.75rem;">
            Link ini sekali pakai dan akan hangus setelah Anda melanjutkan.
        </div>
    </div>
</body>
</html>
