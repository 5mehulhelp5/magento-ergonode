<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer;

final class ValidationReport
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    private int $checkedModules = 0;

    public function incrementCheckedModules(): void
    {
        $this->checkedModules++;
    }

    public function add(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /** @param iterable<Diagnostic> $diagnostics */
    public function addAll(iterable $diagnostics): void
    {
        foreach ($diagnostics as $diagnostic) {
            $this->add($diagnostic);
        }
    }

    /** @return list<Diagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function checkedModules(): int
    {
        return $this->checkedModules;
    }

    public function errorCount(): int
    {
        return $this->countSeverity(Diagnostic::ERROR);
    }

    public function warningCount(): int
    {
        return $this->countSeverity(Diagnostic::WARNING);
    }

    public function hasErrors(): bool
    {
        return $this->errorCount() > 0;
    }

    private function countSeverity(string $severity): int
    {
        return count(array_filter(
            $this->diagnostics,
            static fn (Diagnostic $diagnostic): bool => $diagnostic->severity === $severity
        ));
    }
}
