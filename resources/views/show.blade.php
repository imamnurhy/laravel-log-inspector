@extends('log-inspector::layout')

@section('title', 'Log Entry Details')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-md-12">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="{{ route('log-inspector.index', ['file' => $file]) }}" class="btn btn-sm btn-outline-secondary">
                ← Kembali ke Daftar Log
            </a>
            <button class="btn btn-sm btn-dark" data-copy-target="raw-log-content">
                📋 Salin Log Mentah
            </button>
        </div>

        <div class="card li-card mb-4">
            <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center border-bottom gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="li-level-badge {{ $entry->level->badgeClass() }}">{{ $entry->level->value }}</span>
                    <span class="li-log-timestamp small">{{ $entry->formattedTimestamp() }}</span>
                    <span class="badge bg-light text-secondary border small">{{ $entry->channel }}</span>
                    <span class="badge bg-dark small">{{ $entry->environment }}</span>
                </div>
                <div class="small text-muted font-monospace">
                    Offset: {{ $entry->byteOffset }} | Byte: {{ $entry->byteLength }}
                </div>
            </div>

            <div class="card-body p-4">
                <h6 class="fw-bold mb-2 text-secondary text-uppercase small" style="letter-spacing: 0.05em;">Pesan Log</h6>
                <div class="li-code-light mb-4">
                    {{-- Strict escaping prevents XSS --}}
                    {{ $entry->message }}
                </div>

                @if($entry->hasException())
                    <h6 class="fw-bold text-danger text-uppercase small mb-2" style="letter-spacing: 0.05em;">Detail Exception</h6>
                    <div class="p-3 bg-light border border-danger rounded mb-4" style="background-color: #fff5f5 !important;">
                        <div class="fw-bold text-danger fs-6">{{ $entry->exceptionClass }}</div>
                        <div class="small font-monospace text-dark mt-1">{{ $entry->exceptionLocation() }}</div>
                        <div class="small text-muted mt-2">
                            Hash Signature: <span class="badge bg-danger">#{{ $entry->exceptionHash() }}</span>
                        </div>
                    </div>

                    @if(!empty($entry->stackTrace))
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-secondary text-uppercase small mb-0" style="letter-spacing: 0.05em;">
                                Stack Trace ({{ count($entry->stackTrace) }} frames)
                            </h6>
                            <button class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 0.725rem;" data-copy-target="stacktrace-content">
                                Salin Trace
                            </button>
                        </div>
                        <pre class="li-code-dark mb-4" id="stacktrace-content">{{ implode("\n", $entry->stackTrace) }}</pre>
                    @endif
                @endif

                @if(!empty($entry->context))
                    <h6 class="fw-bold text-secondary text-uppercase small mb-2" style="letter-spacing: 0.05em;">Context JSON</h6>
                    <pre class="li-code-light mb-4">{{ json_encode($entry->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif

                @if(!empty($entry->extra))
                    <h6 class="fw-bold text-secondary text-uppercase small mb-2" style="letter-spacing: 0.05em;">Extra Metadata</h6>
                    <pre class="li-code-light mb-4">{{ json_encode($entry->extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif

                <div id="raw-log-content" style="display: none;">{{ $entry->raw ?? $entry->message }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
