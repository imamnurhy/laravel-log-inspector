@extends('log-inspector::layout')

@section('title', 'Dashboard')

@section('content')
<div class="row g-3">
    <!-- Sidebar / File Selector (Fixed 280px / 3 cols) -->
    <div class="col-lg-3 col-md-4">
        <div class="card li-card mb-3">
            <div class="card-header bg-white py-2 px-3 fw-bold small text-uppercase text-muted border-bottom d-flex justify-content-between align-items-center">
                <span>📁 Log Files</span>
                <span class="badge bg-light text-dark border">{{ count($files) }}</span>
            </div>
            <div class="list-group list-group-flush li-file-list">
                @forelse($files as $f)
                    <a href="{{ route('log-inspector.index', ['file' => $f['name']]) }}" 
                       class="list-group-item list-group-item-action li-file-item {{ $selectedFile === $f['name'] ? 'active' : '' }}">
                        <div class="text-truncate me-2" title="{{ $f['name'] }}">
                            {{ $f['name'] }}
                        </div>
                        <span class="badge li-file-size {{ $f['is_active'] ? 'bg-success text-white' : 'bg-light text-secondary border' }}">
                            {{ round($f['size'] / 1024, 1) }} KB
                        </span>
                    </a>
                @empty
                    <div class="p-3 text-muted text-center small">No log files found.</div>
                @endforelse
            </div>
        </div>

        @if(!empty($stats) && !empty($stats['top_exceptions']))
        <div class="card li-card">
            <div class="card-header bg-white py-2 px-3 fw-bold small text-uppercase text-danger border-bottom">
                💥 Frequent Exceptions
            </div>
            <div class="card-body p-2">
                @foreach($stats['top_exceptions'] as $ex)
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                        <a href="{{ route('log-inspector.index', ['file' => $selectedFile, 'exception_hash' => $ex['hash']]) }}" 
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

    <!-- Main Content Area (9 cols) -->
    <div class="col-lg-9 col-md-8">
        @if(session('status'))
            <div class="alert alert-success py-2 px-3 small mb-3">
                {{ session('status') }}
            </div>
        @endif

        @if(!empty($selectedFile))
        <!-- File Action Toolbar -->
        <div class="li-card p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold fs-6">{{ $selectedFile }}</span>
                @php
                    $selectedMeta = collect($files)->firstWhere('name', $selectedFile);
                @endphp
                @if($selectedMeta && $selectedMeta['is_active'])
                    <span class="badge bg-success">Active File</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
                <span class="text-muted small ms-2">
                    Size: {{ round(($selectedMeta['size'] ?? 0) / 1024, 1) }} KB
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                @if(config('log-inspector.access.mode') === 'gate')
                    <a href="{{ route('log-inspector.download', ['file' => $selectedFile]) }}" class="btn btn-sm btn-outline-primary">
                        ⬇️ Download Masked
                    </a>
                    <a href="{{ route('log-inspector.download', ['file' => $selectedFile, 'raw' => 1]) }}" class="btn btn-sm btn-outline-danger">
                        ⬇️ Download Raw
                    </a>
                    @if(config('log-inspector.web_delete_enabled', false) && $selectedMeta && ! $selectedMeta['is_active'])
                        <form method="POST" action="{{ route('log-inspector.destroy') }}" onsubmit="return confirm('Hapus file log ini secara permanen?');" class="d-inline m-0">
                            @csrf
                            <input type="hidden" name="file" value="{{ $selectedFile }}">
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

        @if(!empty($stats))
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
                    <div class="li-stat-value text-danger">{{ number_format($stats['levels']['ERROR'] ?? 0) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="li-stat-card" style="border-left: 3px solid #d97706;">
                    <div class="li-stat-title text-warning">Warnings</div>
                    <div class="li-stat-value text-warning">{{ number_format($stats['levels']['WARNING'] ?? 0) }}</div>
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

        <!-- Filter Bar -->
        <div class="li-card p-3 mb-3">
            <form method="GET" action="{{ route('log-inspector.index') }}" class="row g-2 align-items-center">
                <input type="hidden" name="file" value="{{ $selectedFile }}">

                <div class="col-md-3">
                    <select name="level" class="form-select form-select-sm">
                        <option value="">All Levels</option>
                        @foreach(['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'] as $lvl)
                            <option value="{{ $lvl }}" {{ ($filters['level'] ?? '') === $lvl ? 'selected' : '' }}>
                                {{ $lvl }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search literal message or context..." 
                           value="{{ $filters['search'] ?? '' }}">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
                    <a href="{{ route('log-inspector.index', ['file' => $selectedFile]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        @if($entries && $entries->isPartial)
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
                <tbody>
                    @if($entries)
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
                                        {{-- Strict escaping safe against XSS --}}
                                        {{ \Illuminate\Support\Str::limit($entry->message, 240) }}
                                    </div>
                                    @if($entry->hasException())
                                        <div class="li-exception-highlight">
                                            💥 {{ $entry->exceptionClass }}: {{ $entry->exceptionLocation() }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('log-inspector.show', ['file' => $selectedFile, 'offset' => $entry->byteOffset, 'length' => $entry->byteLength]) }}" 
                                       class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 0.775rem;">
                                        View
                                    </a>
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

        <!-- Pagination Bar -->
        @if($entries && $entries->hasPages())
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <div class="small text-muted">
                Showing {{ count($entries) }} of {{ number_format($entries->total) }} entries (Page {{ $entries->currentPage }} of {{ $entries->lastPage() }})
            </div>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item {{ $entries->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ route('log-inspector.index', array_merge($filters, ['file' => $selectedFile, 'page' => $entries->currentPage - 1])) }}">
                        Previous
                    </a>
                </li>
                <li class="page-item active">
                    <span class="page-link">{{ $entries->currentPage }}</span>
                </li>
                <li class="page-item {{ $entries->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ route('log-inspector.index', array_merge($filters, ['file' => $selectedFile, 'page' => $entries->currentPage + 1])) }}">
                        Next
                    </a>
                </li>
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
