@extends('log-inspector::layout')

@section('title', 'Dashboard')

@section('content')
    <div class="row g-3">
        <!-- Sidebar / File Selector -->
        <div class="col-lg-3 col-md-4">
            <div class="card li-card mb-3">
                <div
                    class="card-header bg-white py-2 px-3 fw-bold small text-uppercase text-muted border-bottom d-flex justify-content-between align-items-center">
                    <span>📁 Log Files</span>
                    <span class="badge bg-light text-dark border">{{ count($files) }}</span>
                </div>

                <!-- Instant Search Input for Log Files -->
                <div class="p-2 border-bottom bg-light">
                    <input type="text" id="logFileSearchInput" class="form-control form-control-sm"
                        placeholder="Cari file log (mis. 2026-11)..." autocomplete="off">
                </div>

                <div class="list-group list-group-flush li-file-list">
                    @forelse($files as $f)
                        <a href="{{ route('log-inspector.index', ['file' => $f['identifier']]) }}"
                            class="list-group-item list-group-item-action li-file-item {{ $selectedFile === $f['name'] ? 'active' : '' }}"
                            data-filename="{{ $f['name'] }}">
                            <div class="text-truncate me-2" title="{{ $f['name'] }}">
                                {{ $f['name'] }}
                            </div>
                            <span
                                class="badge li-file-size {{ $f['is_active'] ? 'bg-success text-white' : 'bg-light text-secondary border' }}">
                                {{ round($f['size'] / 1024, 1) }} KB
                            </span>
                        </a>
                    @empty
                        <div class="p-3 text-muted text-center small">No log files found.</div>
                    @endforelse
                    <div id="liNoFileMatches" class="p-3 text-muted text-center small" style="display: none;">
                        Tidak ada file yang cocok.
                    </div>
                </div>
            </div>

            @if (!empty($stats) && !empty($stats['top_exceptions']))
                <div class="card li-card">
                    <div class="card-header bg-white py-2 px-3 fw-bold small text-uppercase text-danger border-bottom">
                        💥 Frequent Exceptions
                    </div>
                    <div class="card-body p-2">
                        @foreach ($stats['top_exceptions'] as $ex)
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                <a href="{{ route('log-inspector.index', ['file' => \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile), 'exception_hash' => $ex['hash']]) }}"
                                    class="text-danger small font-monospace text-decoration-none text-truncate me-2">
                                    #{{ $ex['hash'] }}
                                </a>
                                <span class="badge bg-danger rounded-pill">{{ $ex['count'] }}x</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Main Content Area -->
        <div class="col-lg-9 col-md-8">
            @if (session('status'))
                <div class="alert alert-success py-2 px-3 small mb-3">
                    {{ session('status') }}
                </div>
            @endif

            @if (!empty($selectedFile))
                <!-- File Action Toolbar -->
                <div class="li-card p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold fs-6">{{ $selectedFile }}</span>
                        @php
                            $selectedMeta = collect($files)->firstWhere('name', $selectedFile);
                        @endphp
                        @if ($selectedMeta && $selectedMeta['is_active'])
                            <span class="badge bg-success">Active File</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                        <span class="text-muted small ms-2">
                            Size: {{ round(($selectedMeta['size'] ?? 0) / 1024, 1) }} KB
                        </span>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        @if (config('log-inspector.access.mode') === 'gate')
                            <a href="{{ route('log-inspector.download', ['file' => \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile)]) }}"
                                class="btn btn-sm btn-outline-primary">
                                ⬇️ Download Masked
                            </a>
                            <a href="{{ route('log-inspector.download', ['file' => \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile), 'raw' => 1]) }}"
                                class="btn btn-sm btn-outline-danger">
                                ⬇️ Download Raw
                            </a>
                            @if (config('log-inspector.web_delete_enabled', false) && $selectedMeta && !$selectedMeta['is_active'])
                                <form method="POST" action="{{ route('log-inspector.destroy') }}"
                                    onsubmit="return confirm('Hapus file log ini secara permanen?');" class="d-inline m-0">
                                    @csrf
                                    <input type="hidden" name="file" value="{{ \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile) }}">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        🗑️ Delete
                                    </button>
                                </form>
                            @endif
                        @else
                            <span class="badge bg-light text-muted border px-2 py-1">🔒 Masked Read-Only</span>
                        @endif
                    </div>
                </div>
            @endif

            @if (!empty($stats))
                <!-- Overview Stats Grid -->
                <div class="row g-2 mb-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="li-stat-card">
                            <div class="li-stat-title">Total Logs</div>
                            <div class="li-stat-value text-dark">{{ number_format($stats['total']) }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="li-stat-card" style="border-left: 3px solid #dc2626;">
                            <div class="li-stat-title text-danger">Errors</div>
                            <div class="li-stat-value text-danger">{{ number_format($stats['levels']['ERROR'] ?? 0) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="li-stat-card" style="border-left: 3px solid #d97706;">
                            <div class="li-stat-title text-warning">Warnings</div>
                            <div class="li-stat-value text-warning">{{ number_format($stats['levels']['WARNING'] ?? 0) }}
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="li-stat-card" style="border-left: 3px solid #991b1b;">
                            <div class="li-stat-title text-danger">Critical / Alert</div>
                            <div class="li-stat-value text-danger">
                                {{ number_format(($stats['levels']['CRITICAL'] ?? 0) + ($stats['levels']['ALERT'] ?? 0)) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filter Bar with Pagination per_page & Auto Refresh in 1 compact row -->
            <div class="li-card p-3 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <form method="GET" action="{{ route('log-inspector.index') }}" class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
                        <input type="hidden" name="file" value="{{ \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile) }}">

                        <select name="level" class="form-select form-select-sm" style="width: auto; min-width: 120px;">
                            <option value="">All Levels</option>
                            @foreach (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'] as $lvl)
                                <option value="{{ $lvl }}"
                                    {{ ($filters['level'] ?? '') === $lvl ? 'selected' : '' }}>
                                    {{ $lvl }}
                                </option>
                            @endforeach
                        </select>

                        <input type="text" name="search" class="form-control form-control-sm flex-grow-1" style="min-width: 180px;"
                            placeholder="Search literal message or context..." value="{{ $filters['search'] ?? '' }}">

                        <select name="per_page" class="form-select form-select-sm" style="width: auto;" title="Jumlah entri per halaman"
                            onchange="this.form.submit()">
                            @foreach ([10, 25, 50, 100, 200] as $size)
                                <option value="{{ $size }}"
                                    {{ ((int) ($filters['per_page'] ?? config('log-inspector.limits.per_page', 10))) === $size ? 'selected' : '' }}>
                                    {{ $size }} / hal
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ route('log-inspector.index', ['file' => \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile)]) }}"
                            class="btn btn-sm btn-outline-secondary">Reset</a>
                    </form>

                    @if (!empty($selectedFile) && isset($selectedMeta) && $selectedMeta['is_active'] && !str_ends_with(strtolower($selectedFile), '.gz'))
                        <div class="d-flex align-items-center gap-2 ps-2 border-start">
                            <div class="form-check form-switch mb-0 d-flex align-items-center gap-2">
                                <input class="form-check-input mt-0" type="checkbox" role="switch" id="autoRefreshToggle"
                                    data-tail-url="{{ route('log-inspector.tail', ['file' => \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile)]) }}"
                                    data-tail-offset="{{ $selectedMeta['size'] ?? 0 }}">
                                <label class="form-check-label small fw-semibold text-secondary text-nowrap" for="autoRefreshToggle" style="cursor: pointer;">
                                    Auto Refresh
                                </label>
                            </div>
                            <select id="autoRefreshInterval" class="form-select form-select-sm" style="width: auto;" title="Interval Auto Refresh">
                                <option value="3000">3 detik</option>
                                <option value="5000" selected>5 detik</option>
                                <option value="10000">10 detik</option>
                                <option value="30000">30 detik</option>
                            </select>
                        </div>
                    @endif
                </div>
            </div>

            @if ($entries && $entries->isPartial)
                <div class="alert alert-warning py-2 px-3 small d-flex align-items-center mb-3">
                    ⚠️ {{ $entries->partialNotice }}
                </div>
            @endif

            <!-- Modern Clean Log Table -->
            <div class="li-table-wrap mb-3">
                <table class="li-table">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Timestamp</th>
                            <th style="width: 90px;">Level</th>
                            <th style="width: 110px;">Channel</th>
                            <th>Message</th>
                            <th style="width: 80px;" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody">
                        @if ($entries)
                            @forelse($entries as $entry)
                                <tr>
                                    <td>
                                        <span class="li-log-timestamp">{{ $entry->formattedTimestamp() }}</span>
                                    </td>
                                    <td>
                                        <span class="li-level-badge {{ $entry->level->badgeClass() }}">
                                            {{ $entry->level->value }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="li-log-channel">{{ $entry->channel }}</span>
                                    </td>
                                    <td>
                                        <div class="li-log-msg">
                                            {{ \Illuminate\Support\Str::limit(trim($entry->message), 240) }}</div>
                                        @if ($entry->hasException())
                                            <div class="li-exception-highlight">
                                                💥 {{ $entry->exceptionClass }}: {{ $entry->exceptionLocation() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-secondary py-1 px-2 js-view-log"
                                            style="font-size: 0.775rem;"
                                            data-detail-url="{{ route('log-inspector.show', ['file' => \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile), 'offset' => $entry->byteOffset, 'length' => $entry->byteLength]) }}">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted small">
                                        No log entries match the criteria.
                                    </td>
                                </tr>
                            @endforelse
                        @else
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted small">
                                    Select a log file to inspect.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <!-- Full Multi-Page Pagination Bar (Always rendered when entries exist) -->
            @if ($entries)
                <div
                    class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 p-2 bg-white rounded border">
                    <div class="small text-muted">
                        Menampilkan <b>{{ count($entries) }}</b> dari <b>{{ number_format($entries->total) }}</b> entri
                        (Halaman {{ $entries->currentPage }} dari {{ max(1, $entries->lastPage()) }})
                    </div>

                    @php
                        $current = $entries->currentPage;
                        $last = max(1, $entries->lastPage());
                        $start = max(1, $current - 2);
                        $end = min($last, $current + 2);
                    @endphp

                    <ul class="pagination pagination-sm mb-0">
                        @php
                            $encodedSelected = \Explorin\LogInspector\Services\LogInspector::encodeFile($selectedFile);
                        @endphp

                        {{-- First Page --}}
                        <li class="page-item {{ $entries->onFirstPage() ? 'disabled' : '' }}">
                            <a class="page-link"
                                href="{{ route('log-inspector.index', array_merge($filters, ['file' => $encodedSelected, 'page' => 1])) }}"
                                title="Halaman Pertama">
                                ««
                            </a>
                        </li>

                        {{-- Previous Page --}}
                        <li class="page-item {{ $entries->onFirstPage() ? 'disabled' : '' }}">
                            <a class="page-link"
                                href="{{ route('log-inspector.index', array_merge($filters, ['file' => $encodedSelected, 'page' => max(1, $current - 1)])) }}">
                                ‹ Prev
                            </a>
                        </li>

                        {{-- Ellipsis Start --}}
                        @if ($start > 1)
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        @endif

                        {{-- Page Numbers (Window of +/- 2) --}}
                        @for ($p = $start; $p <= $end; $p++)
                            <li class="page-item {{ $p === $current ? 'active' : '' }}">
                                <a class="page-link"
                                    href="{{ route('log-inspector.index', array_merge($filters, ['file' => $encodedSelected, 'page' => $p])) }}">
                                    {{ $p }}
                                </a>
                            </li>
                        @endfor

                        {{-- Ellipsis End --}}
                        @if ($end < $last)
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        @endif

                        {{-- Next Page --}}
                        <li class="page-item {{ $entries->hasMorePages() ? '' : 'disabled' }}">
                            <a class="page-link"
                                href="{{ route('log-inspector.index', array_merge($filters, ['file' => $encodedSelected, 'page' => min($last, $current + 1)])) }}">
                                Next ›
                            </a>
                        </li>

                        {{-- Last Page --}}
                        <li class="page-item {{ $current === $last ? 'disabled' : '' }}">
                            <a class="page-link"
                                href="{{ route('log-inspector.index', array_merge($filters, ['file' => $encodedSelected, 'page' => $last])) }}"
                                title="Halaman Terakhir">
                                »»
                            </a>
                        </li>
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <!-- Log Detail Modal Popup -->
    <div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header py-3 px-4 bg-light border-bottom">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span id="modalLevelBadge" class="li-level-badge"></span>
                        <span id="modalTimestamp" class="li-log-timestamp small"></span>
                        <span id="modalChannel" class="badge bg-light text-secondary border small"></span>
                        <span id="modalEnvironment" class="badge bg-dark small"></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <span id="modalOffsets" class="small text-muted font-monospace me-2"></span>
                        <button type="button" class="btn btn-sm btn-outline-dark" data-copy-target="modalRawContent">
                            📋 Salin Log
                        </button>
                        <button type="button" class="btn-close ms-2" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>

                <div class="modal-body p-4">
                    <!-- Loading Spinner -->
                    <div id="modalLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="text-muted small mt-2">Memuat data log...</div>
                    </div>

                    <!-- Actual Content (Hidden until loaded) -->
                    <div id="modalContent" style="display: none;">
                        <h6 class="fw-bold mb-2 text-secondary text-uppercase small" style="letter-spacing: 0.05em;">Pesan
                            Log</h6>
                        <div class="li-code-light mb-4" id="modalMessage"></div>

                        <!-- Exception Inspector Box -->
                        <div id="modalExceptionSection" style="display: none;">
                            <h6 class="fw-bold text-danger text-uppercase small mb-2" style="letter-spacing: 0.05em;">
                                Detail Exception</h6>
                            <div class="p-3 bg-light border border-danger rounded mb-4"
                                style="background-color: #fff5f5 !important;">
                                <div class="fw-bold text-danger fs-6" id="modalExClass"></div>
                                <div class="small font-monospace text-dark mt-1" id="modalExLocation"></div>
                                <div class="small text-muted mt-2">
                                    Hash Signature: <span class="badge bg-danger" id="modalExHash"></span>
                                </div>
                            </div>

                            <div id="modalTraceSection" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold text-secondary text-uppercase small mb-0"
                                        style="letter-spacing: 0.05em;">
                                        Stack Trace (<span id="modalTraceCount">0</span> frames)
                                    </h6>
                                    <button class="btn btn-xs btn-outline-secondary py-0 px-2"
                                        style="font-size: 0.725rem;" data-copy-target="modalStackTrace">
                                        Salin Trace
                                    </button>
                                </div>
                                <pre class="li-code-dark mb-4" id="modalStackTrace"></pre>
                            </div>
                        </div>

                        <!-- Context JSON Box -->
                        <div id="modalContextSection" style="display: none;">
                            <h6 class="fw-bold text-secondary text-uppercase small mb-2" style="letter-spacing: 0.05em;">
                                Context JSON</h6>
                            <pre class="li-code-light mb-4" id="modalContext"></pre>
                        </div>

                        <!-- Extra Box -->
                        <div id="modalExtraSection" style="display: none;">
                            <h6 class="fw-bold text-secondary text-uppercase small mb-2" style="letter-spacing: 0.05em;">
                                Extra Metadata</h6>
                            <pre class="li-code-light mb-4" id="modalExtra"></pre>
                        </div>

                        <div id="modalRawContent" style="display: none;"></div>
                    </div>
                </div>

                <div class="modal-footer py-2 px-4 bg-light border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endsection
