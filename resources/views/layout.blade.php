<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Log Inspector') - Explorin</title>
    <!-- Local Bundled Assets (No External CDN, CSP Safe) -->
    <link rel="stylesheet" href="{{ asset('vendor/log-inspector/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/log-inspector/css/log-inspector.css') }}">
</head>
<body class="log-inspector-body">
    <nav class="navbar navbar-dark bg-dark px-3 py-2 mb-3">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand fw-bold text-white text-decoration-none" href="{{ route('log-inspector.index') }}">
                🪵 Explorin Log Inspector
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary text-uppercase">{{ app()->environment() }}</span>
                @if(config('log-inspector.access.mode') === 'link' || request()->attributes->get('log_inspector_mode') === 'link')
                    <span class="badge bg-info text-dark">Temporary Link Session (Read-Only)</span>
                    <form method="POST" action="{{ route('log-inspector.logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size: 0.75rem;">
                            🚪 Akhiri Sesi
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </nav>

    <main class="container-fluid mb-5">
        @yield('content')
    </main>

    <script src="{{ asset('vendor/log-inspector/js/log-inspector.js') }}"></script>
</body>
</html>
