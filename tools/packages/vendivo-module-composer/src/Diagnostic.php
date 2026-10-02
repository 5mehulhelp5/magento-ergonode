<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer;

final readonly class Diagnostic
{
    public const ERROR = 'ERROR';

    public const WARNING = 'WARNING';

    public function __construct(
        public string $severity,
        public string $code,
        public string $file,
        public string $message
    ) {
    }

    public static function error(string $code, string $file, string $message): self
    {
        return new self(self::ERROR, $code, $file, self::normalize($message));
    }

    public static function warning(string $code, string $file, string $message): self
    {
        return new self(self::WARNING, $code, $file, self::normalize($message));
    }

    public function render(): string
    {
        return sprintf('%s [%s] %s: %s', $this->severity, $this->code, $this->file, $this->message);
    }

    private static function normalize(string $message): string
    {
        return trim((string)preg_replace('/\s+/', ' ', $message));
    }
}
