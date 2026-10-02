<?php
/**
 * Copyright 2026 Vendivo.
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Vendivo\Sniffs\StrictTypes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class RequireStrictTypesSniff implements Sniff
{
    private const MISSING_CODE = 'MissingStrictTypes';
    private const INLINE_HTML_CODE = 'InlineHtmlBeforeDeclare';

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_OPEN_TAG, T_INLINE_HTML];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $firstContentPtr = $this->findFirstContentToken($phpcsFile);
        if ($firstContentPtr === null || $firstContentPtr !== $stackPtr) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        if ($tokens[$firstContentPtr]['code'] !== T_OPEN_TAG) {
            if ($this->phpFileBoundaryPolicyOwns($phpcsFile)) {
                $openingTagPtr = $phpcsFile->findNext(T_OPEN_TAG, 0);
                if ($openingTagPtr === false) {
                    return;
                }
                $firstContentPtr = $openingTagPtr;
            } else {
                $phpcsFile->addError(
                    'PHP and PHTML files must start with <?php and declare(strict_types=1); before any output.',
                    $firstContentPtr,
                    self::INLINE_HTML_CODE
                );

                return;
            }
        }

        $declarePtr = $this->findFirstStatementAfterOpenTag($phpcsFile, $firstContentPtr);
        if (
            $declarePtr !== null
            && $tokens[$declarePtr]['code'] === T_DECLARE
            && $this->hasStrictTypesValue($phpcsFile, $declarePtr, 1)
        ) {
            return;
        }

        if (strtolower((string) pathinfo($phpcsFile->getFilename(), PATHINFO_EXTENSION)) === 'php') {
            $strictTypesPtr = $this->findStrictTypesDeclare($phpcsFile);
            if (
                $strictTypesPtr !== null
                && ($strictTypesPtr !== $declarePtr
                    || !$this->hasStrictTypesValue($phpcsFile, $strictTypesPtr, 0))
            ) {
                // PHPStan owns invalid values and declarations that are not the first statement.
                return;
            }
        }

        $phpcsFile->addError(
            'PHP and PHTML files must declare strict_types=1 as the first statement after the opening tag.',
            $firstContentPtr,
            self::MISSING_CODE
        );
    }

    private function findFirstContentToken(File $phpcsFile): ?int
    {
        foreach ($phpcsFile->getTokens() as $ptr => $token) {
            if ($token['code'] === T_WHITESPACE) {
                continue;
            }

            if ($token['code'] === T_INLINE_HTML && trim($token['content']) === '') {
                continue;
            }

            return $ptr;
        }

        return null;
    }

    private function findFirstStatementAfterOpenTag(File $phpcsFile, int $openTagPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $openTagPtr + 1;
        $tokenCount = count($tokens);

        while ($ptr < $tokenCount) {
            if (!$this->isSkippableBeforeDeclare($tokens[$ptr]['code'])) {
                return $ptr;
            }

            $ptr++;
        }

        return null;
    }

    private function isSkippableBeforeDeclare(int|string $tokenCode): bool
    {
        return in_array(
            $tokenCode,
            [
                T_WHITESPACE,
                T_COMMENT,
                T_DOC_COMMENT_OPEN_TAG,
                T_DOC_COMMENT_WHITESPACE,
                T_DOC_COMMENT_STAR,
                T_DOC_COMMENT_STRING,
                T_DOC_COMMENT_TAG,
                T_DOC_COMMENT_CLOSE_TAG,
            ],
            true
        );
    }

    private function findStrictTypesDeclare(File $phpcsFile): ?int
    {
        foreach ($phpcsFile->getTokens() as $ptr => $token) {
            if ($token['code'] !== T_DECLARE) {
                continue;
            }

            $statement = $this->declareStatement($phpcsFile, $ptr);
            if ($statement !== null && preg_match('/\bstrict_types\s*=/i', $statement) === 1) {
                return $ptr;
            }
        }

        return null;
    }

    private function hasStrictTypesValue(File $phpcsFile, int $declarePtr, int $value): bool
    {
        $statement = $this->declareStatement($phpcsFile, $declarePtr);

        return $statement !== null
            && preg_match('/\bstrict_types\s*=\s*' . $value . '\b/i', $statement) === 1;
    }

    private function declareStatement(File $phpcsFile, int $declarePtr): ?string
    {
        $semicolonPtr = $phpcsFile->findNext(T_SEMICOLON, $declarePtr + 1);
        if ($semicolonPtr === false) {
            return null;
        }

        return $phpcsFile->getTokensAsString($declarePtr, $semicolonPtr - $declarePtr + 1);
    }

    private function phpFileBoundaryPolicyOwns(File $phpcsFile): bool
    {
        if (strtolower((string) pathinfo($phpcsFile->getFilename(), PATHINFO_EXTENSION)) !== 'php') {
            return false;
        }

        $tokens = $phpcsFile->getTokens();
        $openingTagPtr = $phpcsFile->findNext(T_OPEN_TAG, 0);
        if ($openingTagPtr === false || $openingTagPtr === 0) {
            return false;
        }

        $prefix = (string) ($tokens[0]['content'] ?? '');
        foreach (["\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"] as $byteOrderMark) {
            if ($prefix === $byteOrderMark) {
                return true;
            }
        }

        if (str_starts_with($prefix, '#!')) {
            return false;
        }

        return true;
    }
}
