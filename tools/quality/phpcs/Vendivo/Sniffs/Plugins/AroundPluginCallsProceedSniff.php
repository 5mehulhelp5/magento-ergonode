<?php
/**
 * Copyright 2026 Vendivo.
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Vendivo\Sniffs\Plugins;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class AroundPluginCallsProceedSniff implements Sniff
{
    private const ERROR_CODE = 'MissingProceedCall';

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_FUNCTION];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null || !str_starts_with($methodName, 'around')) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        if (!$this->hasProceedParameter($phpcsFile, $stackPtr)) {
            $phpcsFile->addError(
                'Around plugin methods must accept and call $proceed unless intentionally ignored with a PHPCS annotation.',
                $stackPtr,
                self::ERROR_CODE
            );
            return;
        }

        if ($this->callsProceed($phpcsFile, $tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $phpcsFile->addError(
            'Around plugin methods must call $proceed(...) unless intentionally ignored with a PHPCS annotation.',
            $stackPtr,
            self::ERROR_CODE
        );
    }

    private function hasProceedParameter(File $phpcsFile, int $functionPtr): bool
    {
        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            if (ltrim((string)($parameter['name'] ?? ''), '$') === 'proceed') {
                return true;
            }
        }

        return false;
    }

    private function callsProceed(File $phpcsFile, int $scopeOpener, int $scopeCloser): bool
    {
        $tokens = $phpcsFile->getTokens();
        $variablePtr = $scopeOpener;
        while (($variablePtr = $phpcsFile->findNext(T_VARIABLE, $variablePtr + 1, $scopeCloser)) !== false) {
            if ($tokens[$variablePtr]['content'] !== '$proceed') {
                continue;
            }

            $nextPtr = $phpcsFile->findNext(T_WHITESPACE, $variablePtr + 1, $scopeCloser, true);
            if ($nextPtr !== false && $tokens[$nextPtr]['code'] === T_OPEN_PARENTHESIS) {
                return true;
            }
        }

        return false;
    }
}
