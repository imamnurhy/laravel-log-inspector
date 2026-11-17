<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Log Inspector') - Explorin</title>
    <!-- Internal Zero-Publish Assets (Served directly from package with caching) -->
    <link rel="stylesheet" href="{{ route('log-inspector.asset', ['path' => 'css/bootstrap.min.css']) }}">
    <link rel="stylesheet" href="{{ route('log-inspector.asset', ['path' => 'css/log-inspector.css']) }}">
</head>
<body class="log-inspector-body">
    <nav class="navbar navbar-expand-lg navbar-dark li-navbar">
        <div class="container-fluid px-0">
            <a class="navbar-brand li-brand mb-0" href="{{ route('log-inspector.index') }}">
                <span>🪵</span> Explorin Log Inspector
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="li-badge-env text-uppercase">{{ app()->environment() }}</span>
                @if(config('log-inspector.access.mode') === 'link' || request()->attributes->get('log_inspector_mode') === 'link')
                    <span class="badge bg-secondary">Link Session (Read-Only)</span>
                    <form method="POST" action="{{ route('log-inspector.logout') }}" class="d-inline m-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.775rem;">
                            🚪 Akhiri Sesi
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </nav>

    <main class="li-container">
        @yield('content')
    </main>

    <!-- Bootstrap Bundle JS (includes Popper) & Helper (Zero-Publish) -->
    <script src="{{ route('log-inspector.asset', ['path' => 'js/bootstrap.bundle.min.js']) }}"></script>
    <script src="{{ route('log-inspector.asset', ['path' => 'js/log-inspector.js']) }}"></script>
</body>
</html>
