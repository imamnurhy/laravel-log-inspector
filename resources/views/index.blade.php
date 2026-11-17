@extends('log-inspector::layout')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <!-- Sidebar / File Selector -->
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm">
            <div class="card-header fw-bold bg-light">
                📁 Log Files ({{ count($files) }})
            </div>
            <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                @forelse($files as $f)
                    <a href="{{ route('log-inspector.index', ['file' => $f['name']]) }}" 
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedFile === $f['name'] ? 'active text-white' : '' }}">
                        <div class="text-truncate" style="max-width: 180px;">
                            {{ $f['name'] }}
                        </div>
                        <span class="badge {{ $f['is_active'] ? 'bg-success' : 'bg-secondary' }}">
                            {{ round($f['size'] / 1024, 1) }} KB
                        </span>
                    </a>
                @empty
                    <div class="p-3 text-muted text-center">No log files found.</div>
                @endforelse
            </div>
        </div>

        @if(!empty($stats) && !empty($stats['top_exceptions']))
        <div class="card shadow-sm mt-3">
            <div class="card-header fw-bold bg-light">
                💥 Frequent Exceptions
            </div>
            <div class="card-body p-2">
                @foreach($stats['top_exceptions'] as $ex)
                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                        <a href="{{ route('log-inspector.index', ['file' => $selectedFile, 'exception_hash' => $ex['hash']]) }}" class="text-danger small font-monospace text-decoration-none">
                            #{{ $ex['hash'] }}
                        </a>
                        <span class="badge bg-danger">{{ $ex['count'] }}x</span>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <div class="col-md-9">
        @if(session('status'))
            <div class="alert alert-success py-2 small">
                {{ session('status') }}
            </div>
        @endif

        @if(!empty($selectedFile))
        <!-- File Action Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-white rounded border shadow-sm">
            <div>
                <span class="fw-bold">{{ $selectedFile }}</span>
                @php
                    $selectedMeta = collect($files)->firstWhere('name', $selectedFile);
                @endphp
                @if($selectedMeta && $selectedMeta['is_active'])
                    <span class="badge bg-success ms-2">Active File</span>
                @else
                    <span class="badge bg-secondary ms-2">Inactive</span>
                @endif
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('log-inspector.download', ['file' => $selectedFile]) }}" class="btn btn-sm btn-outline-primary">
                    ⬇️ Download Masked
                </a>
                <a href="{{ route('log-inspector.download', ['file' => $selectedFile, 'raw' => 1]) }}" class="btn btn-sm btn-outline-danger">
                    ⬇️ Download Raw
                </a>
                @if($selectedMeta && ! $selectedMeta['is_active'])
                    <form method="POST" action="{{ route('log-inspector.destroy') }}" onsubmit="return confirm('Are you sure you want to delete this log file? This action is permanent.');" style="display: inline;">
                        @csrf
                        <input type="hidden" name="file" value="{{ $selectedFile }}">
                        <button type="submit" class="btn btn-sm btn-danger">
                            🗑️ Delete
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @endif

        @if(!empty($stats))
        <!-- Overview Stats Cards -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-2">
                    <div class="text-muted small">Total Logs</div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['total']) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-2 border-danger">
                    <div class="text-danger small fw-bold">Errors</div>
                    <div class="fs-4 fw-bold text-danger">{{ number_format($stats['levels']['ERROR'] ?? 0) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-2 border-warning">
                    <div class="text-warning small fw-bold">Warnings</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($stats['levels']['WARNING'] ?? 0) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-2">
                    <div class="text-muted small">Critical / Alert</div>
                    <div class="fs-4 fw-bold text-danger">
                        {{ number_format(($stats['levels']['CRITICAL'] ?? 0) + ($stats['levels']['ALERT'] ?? 0)) }}
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Filter Bar -->
        <div class="card shadow-sm mb-3">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('log-inspector.index') }}" class="row g-2">
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
        </div>

        @if($entries && $entries->isPartial)
            <div class="alert alert-warning py-2 small d-flex align-items-center">
                ⚠️ {{ $entries->partialNotice }}
            </div>
        @endif

        <!-- Log Table -->
        <div class="log-table-container shadow-sm">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 170px;">Timestamp</th>
                        <th style="width: 100px;">Level</th>
                        <th style="width: 120px;">Channel</th>
                        <th>Message</th>
                        <th style="width: 80px;" class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @if($entries)
                        @forelse($entries as $entry)
                            <tr class="log-entry-row">
                                <td class="font-monospace small text-muted">
                                    {{ $entry->formattedTimestamp() }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ $entry->level->badgeClass() }}">
                                        {{ $entry->level->value }}
                                    </span>
                                </td>
                                <td class="small font-monospace">{{ $entry->channel }}</td>
                                <td>
                                    <div class="log-message-preview">
                                        {{-- Strict escaping: {{ }} is safe against XSS --}}
                                        {{ \Illuminate\Support\Str::limit($entry->message, 180) }}
                                    </div>
                                    @if($entry->hasException())
                                        <div class="text-danger small font-monospace mt-1">
                                            💥 {{ $entry->exceptionClass }}: {{ $entry->exceptionLocation() }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('log-inspector.show', ['file' => $selectedFile, 'offset' => $entry->byteOffset, 'length' => $entry->byteLength]) }}" 
                                       class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    No log entries match the criteria.
                                </td>
                            </tr>
                        @endforelse
                    @else
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Select a log file to inspect.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($entries && $entries->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="small text-muted">
                Showing {{ count($entries) }} of {{ number_format($entries->total) }} entries (Page {{ $entries->currentPage }} / {{ $entries->lastPage() }})
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
