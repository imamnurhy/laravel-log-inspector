@extends('log-inspector::layout')

@section('title', 'Log Entry Details')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="{{ route('log-inspector.index', ['file' => $file]) }}" class="btn btn-sm btn-outline-secondary">
                ← Back to Log Entries
            </a>
            <button class="btn btn-sm btn-secondary" data-copy-target="raw-log-content">
                📋 Copy Raw Entry
            </button>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $entry->level->badgeClass() }}">{{ $entry->level->value }}</span>
                    <span class="font-monospace text-muted small">{{ $entry->formattedTimestamp() }}</span>
                    <span class="badge bg-secondary small">{{ $entry->channel }}</span>
                    <span class="badge bg-dark small">{{ $entry->environment }}</span>
                </div>
                <div class="small text-muted font-monospace">
                    Offset: {{ $entry->byteOffset }} | Len: {{ $entry->byteLength }}
                </div>
            </div>
            <div class="card-body">
                <h6 class="fw-bold mb-2">Message</h6>
                <div class="p-3 bg-light border rounded font-monospace small mb-3 text-break">
                    {{-- Strict escaping prevents XSS --}}
                    {{ $entry->message }}
                </div>

                @if($entry->hasException())
                    <h6 class="fw-bold text-danger mb-2">Exception Inspector</h6>
                    <div class="p-3 bg-light border border-danger rounded mb-3">
                        <div class="fw-bold text-danger">{{ $entry->exceptionClass }}</div>
                        <div class="small font-monospace mb-1">{{ $entry->exceptionLocation() }}</div>
                        <div class="small text-muted">Hash: <span class="badge bg-danger">#{{ $entry->exceptionHash() }}</span></div>
                    </div>

                    @if(!empty($entry->stackTrace))
                        <h6 class="fw-bold mb-2">Stack Trace ({{ count($entry->stackTrace) }} frames)</h6>
                        <pre class="log-stacktrace-box">{{ implode("\n", $entry->stackTrace) }}</pre>
                    @endif
                @endif

                @if(!empty($entry->context))
                    <h6 class="fw-bold mb-2">Context JSON</h6>
                    <pre class="log-context-box">{{ json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif

                @if(!empty($entry->extra))
                    <h6 class="fw-bold mb-2">Extra</h6>
                    <pre class="log-context-box">{{ json_encode($entry->extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif

                <div id="raw-log-content" style="display: none;">{{ $entry->raw ?? $entry->message }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
