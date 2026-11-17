<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Http\Controllers;

use Explorin\LogInspector\Services\LogFileManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FileActionController
{
    public function __construct(
        private readonly LogFileManager $fileManager,
    ) {
    }

    /**
     * Download a log file (Default: Masked. Raw requires download_raw authorization).
     */
    public function download(Request $request): StreamedResponse
    {
        $file = (string) $request->query('file');
        $raw = (bool) $request->query('raw', false);

        if ($raw) {
            $rawGate = (string) config('log-inspector.authorization.download_raw', 'download-raw-log-inspector');
            if (! Gate::has($rawGate) || ! Gate::allows($rawGate)) {
                abort(403, 'Unauthorized to download raw/unmasked log files.');
            }
        } else {
            $gate = (string) config('log-inspector.authorization.download', 'download-log-inspector');
            if (! Gate::has($gate) || ! Gate::allows($gate)) {
                abort(403, 'Unauthorized to download log files.');
            }
        }

        $userId = $request->user()?->getAuthIdentifier();
        $ip = $request->ip();

        return $this->fileManager->download($file, $raw, $userId, $ip);
    }

    /**
     * Delete an inactive log file.
     * Must be called via POST/DELETE request (Protected with CSRF).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $gate = (string) config('log-inspector.authorization.delete', 'delete-log-inspector');
        if (! Gate::has($gate) || ! Gate::allows($gate)) {
            abort(403, 'Unauthorized to delete log files.');
        }

        $file = (string) $request->input('file');
        $userId = $request->user()?->getAuthIdentifier();
        $ip = $request->ip();

        $this->fileManager->delete($file, $userId, $ip);

        return redirect()
            ->route('log-inspector.index')
            ->with('status', sprintf('File [%s] deleted successfully.', basename($file)));
    }
}
