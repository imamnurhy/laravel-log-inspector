<?php

declare(strict_types=1);

namespace Explorin\LogInspector\Security;

use Explorin\LogInspector\Contracts\MaskerInterface;

final class Masker implements MaskerInterface
{
    /**
     * @var array<int, string>
     */
    private array $compiledPatterns = [];

    /**
     * @param bool $enabled
     * @param array<int, string> $patterns Custom or default regex patterns
     * @param string $maskReplacement String used to substitute secrets
     */
    public function __construct(
        private readonly bool $enabled = true,
        array $patterns = [],
        private readonly string $maskReplacement = '******',
    ) {
        $effectivePatterns = empty($patterns) ? $this->defaultPatterns() : $patterns;
        $this->compiledPatterns = array_values($effectivePatterns);
    }

    /**
     * {@inheritdoc}
     */
    public function mask(string $content): string
    {
        if (! $this->enabled || $content === '') {
            return $content;
        }

        return (string) preg_replace(
            $this->compiledPatterns,
            '${1}' . $this->maskReplacement . '${3}',
            $content
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Default high-coverage patterns for passwords, tokens, API keys, credentials.
     * Captures pre-delimiter in group 1, secret in group 2, and post-delimiter in group 3.
     *
     * @return array<int, string>
     */
    private function defaultPatterns(): array
    {
        return [
            // JSON / Array key matching (e.g. "password": "value" or 'secret' => 'value')
            '/(["\']?(?:password|passwd|secret|api[_-]?key|access[_-]?token|refresh[_-]?token|auth[_-]?token|private[_-]?key|app[_-]?key|db_password|database_password)["\']?\s*[:=]>\s*["\'])([^"\']+)(["\'])/i',
            // Bearer / Authorization headers
            '/((?:Authorization|Bearer)\s+)([A-Za-z0-9\-\._~\+\/]+=*)(\b)/i',
            // Generic token assignment (e.g. token=xyz123)
            '/((?:token|api_key|apikey|secret)=)([^&\s]+)(\b)/i',
            // Basic credit card number detection (13-16 digits)
            '/(\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|6(?:011|5[0-9]{2})[0-9]{12})\b)/',
        ];
    }
}
