<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Controllers;

use Explorin\LogInspector\Contracts\PathGuardInterface;
use Explorin\LogInspector\Services\LogInspector;
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
        $this->authorize('view');

        $files = $this->logInspector->files();
        $selectedFile = $request->query('file');

        if (empty($selectedFile) && ! empty($files)) {
            $selectedFile = $files[0]['name'];
        }

        $entries = null;
        $stats = null;
        $activeFilePath = null;

        if (! empty($selectedFile)) {
            $allowedDirs = (array) config('log-inspector.paths', []);
            $activeFilePath = $this->pathGuard->validate($selectedFile, $allowedDirs);

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
                (int) $request->query('per_page', config('log-inspector.limits.per_page', 50)),
                (int) config('log-inspector.limits.max_per_page', 200)
            );
            $page = max(1, (int) $request->query('page', 1));

            $entries = $query->paginate($perPage, $page);
            $stats = $this->logInspector->statistics($selectedFile);
        }

        return view('log-inspector::index', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'entries' => $entries,
            'stats' => $stats,
            'filters' => $request->only(['level', 'search', 'exception_hash', 'per_page']),
        ]);
    }

    /**
     * Show detail of an individual log entry or exception.
     */
    public function show(Request $request): View
    {
        $this->authorize('view');

        $file = (string) $request->query('file');
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

        return view('log-inspector::show', [
            'entry' => $entry,
            'file' => $file,
        ]);
    }

    private function authorize(string $ability): void
    {
        $gateName = (string) config("log-inspector.authorization.{$ability}", "{$ability}-log-inspector");

        if (! Gate::has($gateName) || ! Gate::allows($gateName)) {
            abort(403, sprintf('Access denied. Missing or unauthorized gate [%s].', $gateName));
        }
    }
}
