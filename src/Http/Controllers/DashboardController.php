<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Controllers;

use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Services\LogInspector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class DashboardController
{
    public function __construct(
        private readonly LogInspector $logInspector,
        private readonly PathGuardInterface $pathGuard,
    ) {
    }

    /**
     * Display the main Log Inspector Dashboard.
     */
    public function index(Request $request): View
    {
        $this->authorizeIfGateMode('view');

        $files = $this->logInspector->files();
        $fileParam = $request->query('file');
        $selectedFile = $fileParam !== null ? LogInspector::decodeFile((string) $fileParam) : null;

        if (empty($selectedFile) && ! empty($files)) {
            $selectedFile = $files[0]['name'];
        }

        $entries = null;
        $stats = null;

        if (! empty($selectedFile)) {
            $allowedDirs = (array) config('log-inspector.paths', []);
            $this->pathGuard->validate($selectedFile, $allowedDirs);

            $query = $this->logInspector->query()->file($selectedFile);

            if ($request->filled('level')) {
                $query->level((string) $request->query('level'));
            }

            if ($request->filled('search')) {
                $query->search((string) $request->query('search'));
            }

            if ($request->filled('exception_hash')) {
                $query->exceptionHash((string) $request->query('exception_hash'));
            }

            $perPage = min(
                (int) $request->query('per_page', config('log-inspector.limits.per_page', 10)),
                (int) config('log-inspector.limits.max_per_page', 200)
            );
            $page = max(1, (int) $request->query('page', 1));

            $entries = $query->paginate($perPage, $page);
            $stats = $this->logInspector->statistics($selectedFile);
        }

        $filters = $request->only(['level', 'search', 'exception_hash', 'per_page']);
        if (! isset($filters['per_page'])) {
            $filters['per_page'] = $perPage ?? (int) config('log-inspector.limits.per_page', 10);
        }

        return view('log-inspector::index', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'entries' => $entries,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * Show detail of an individual log entry or exception.
     * Supports both HTML view and JSON responses for modal popups.
     */
    public function show(Request $request): View|JsonResponse
    {
        $this->authorizeIfGateMode('view');

        $fileParam = (string) $request->query('file');
        $file = LogInspector::decodeFile($fileParam);
        $offset = (int) $request->query('offset', 0);
        $length = (int) $request->query('length', 0);

        $allowedDirs = (array) config('log-inspector.paths', []);
        $validatedFile = $this->pathGuard->validate($file, $allowedDirs);

        $handle = fopen($validatedFile, 'rb');
        if ($handle === false) {
            abort(404, 'Cannot open log file.');
        }

        try {
            fseek($handle, $offset, SEEK_SET);
            $rawText = fread($handle, max(1, $length));
        } finally {
            fclose($handle);
        }

        if ($rawText === false) {
            abort(404, 'Unable to read requested log entry.');
        }

        $parser = app(\Explorin\LogInspector\Contracts\LogParserInterface::class);
        $entry = $parser->parse($rawText, $offset, $length);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'id' => $entry->id,
                'timestamp' => $entry->formattedTimestamp(),
                'environment' => $entry->environment,
                'level' => $entry->level->value,
                'badge_class' => $entry->level->badgeClass(),
                'channel' => $entry->channel,
                'message' => $entry->message,
                'context' => $entry->context,
                'extra' => $entry->extra,
                'has_exception' => $entry->hasException(),
                'exception_class' => $entry->exceptionClass,
                'exception_location' => $entry->exceptionLocation(),
                'exception_hash' => $entry->exceptionHash(),
                'stack_trace' => $entry->stackTrace,
                'raw' => $entry->raw ?? $entry->message,
                'byte_offset' => $entry->byteOffset,
                'byte_length' => $entry->byteLength,
            ]);
        }

        return view('log-inspector::show', [
            'entry' => $entry,
            'file' => $file,
        ]);
    }

    /**
     * Poll for new log entries appended after the given byte offset (Live Tail).
     */
    public function tail(Request $request): JsonResponse
    {
        $this->authorizeIfGateMode('view');

        $fileParam = (string) $request->query('file');
        $file = LogInspector::decodeFile($fileParam);
        $offset = max(0, (int) $request->query('offset', 0));

        $allowedDirs = (array) config('log-inspector.paths', []);
        $validatedFile = $this->pathGuard->validate($file, $allowedDirs);

        if (str_ends_with(strtolower($validatedFile), '.gz')) {
            return response()->json(['entries' => [], 'offset' => $offset]);
        }

        clearstatcache(true, $validatedFile);
        $currentSize = (int) filesize($validatedFile);

        // File was rotated or truncated
        if ($currentSize < $offset) {
            return response()->json([
                'entries' => [],
                'offset' => $currentSize,
                'reset' => true,
            ]);
        }

        // No new bytes
        if ($currentSize === $offset) {
            return response()->json([
                'entries' => [],
                'offset' => $currentSize,
            ]);
        }

        // Cap delta read per poll to 512 KB to keep polling fast and memory-safe
        $maxDelta = 524288;
        $readLength = min($currentSize - $offset, $maxDelta);

        $handle = fopen($validatedFile, 'rb');
        if ($handle === false) {
            abort(404, 'Cannot open log file.');
        }

        $entries = [];
        try {
            fseek($handle, $offset, SEEK_SET);
            $parser = app(\Explorin\LogInspector\Contracts\LogParserInterface::class);

            $currentEntryOffset = $offset;
            $currentEntryText = '';
            $streamOffset = $offset;
            $bytesRead = 0;

            while (! feof($handle) && $bytesRead < $readLength) {
                $line = fgets($handle, 65536);
                if ($line === false) {
                    break;
                }

                $lineLength = strlen($line);
                $bytesRead += $lineLength;

                if ($parser->isEntryStart($line) && $currentEntryText !== '') {
                    $entryLength = $streamOffset - $currentEntryOffset;
                    $entries[] = $parser->parse(trim($currentEntryText), $currentEntryOffset, $entryLength);
                    $currentEntryOffset = $streamOffset;
                    $currentEntryText = '';
                }

                $currentEntryText .= $line;
                $streamOffset += $lineLength;
            }

            if (trim($currentEntryText) !== '') {
                $entryLength = $streamOffset - $currentEntryOffset;
                $entries[] = $parser->parse(trim($currentEntryText), $currentEntryOffset, $entryLength);
            }
        } finally {
            fclose($handle);
        }

        $encodedFile = LogInspector::encodeFile($file);
        $formatted = array_map(static function ($entry) use ($encodedFile) {
            return [
                'timestamp' => $entry->formattedTimestamp(),
                'level' => $entry->level->value,
                'badge_class' => $entry->level->badgeClass(),
                'channel' => $entry->channel,
                'message' => \Illuminate\Support\Str::limit(trim($entry->message), 240),
                'has_exception' => $entry->hasException(),
                'exception_class' => $entry->exceptionClass,
                'exception_location' => $entry->exceptionLocation(),
                'detail_url' => route('log-inspector.show', [
                    'file' => $encodedFile,
                    'offset' => $entry->byteOffset,
                    'length' => $entry->byteLength,
                ]),
            ];
        }, $entries);

        return response()->json([
            'entries' => $formatted,
            'offset' => $streamOffset,
        ]);
    }

    private function authorizeIfGateMode(string $ability): void
    {
        $mode = (string) config('log-inspector.access.mode', 'link');
        if ($mode === 'gate') {
            $gateName = (string) config("log-inspector.authorization.{$ability}", "{$ability}-log-inspector");

            if (! Gate::has($gateName) || ! Gate::allows($gateName)) {
                abort(403, sprintf('Access denied. Missing or unauthorized gate [%s].', $gateName));
            }
        }
    }
}
