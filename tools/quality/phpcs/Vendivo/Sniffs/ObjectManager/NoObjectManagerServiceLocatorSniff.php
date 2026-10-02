<?php
/**
 * Copyright 2026 Vendivo.
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Vendivo\Sniffs\ObjectManager;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class NoObjectManagerServiceLocatorSniff implements Sniff
{
    private const ERROR_CODE = 'ServiceLocator';

    /**
     * @var array<string, bool>
     */
    private array $reportedObjectManagerInterface = [];

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isAllowedFile($phpcsFile->getFilename())) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $content = ltrim($tokens[$stackPtr]['content'], '\\');
        if ($content === 'ObjectManagerInterface' || str_ends_with($content, '\ObjectManagerInterface')) {
            $filename = $phpcsFile->getFilename();
            if (!isset($this->reportedObjectManagerInterface[$filename])) {
                $phpcsFile->addError(
                    'Do not inject Magento\Framework\ObjectManagerInterface as a service locator.',
                    $stackPtr,
                    self::ERROR_CODE
                );
                $this->reportedObjectManagerInterface[$filename] = true;
            }

            return;
        }

        if ($content !== 'ObjectManager' && !str_ends_with($content, '\ObjectManager')) {
            return;
        }

        $doubleColonPtr = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($doubleColonPtr === false || $tokens[$doubleColonPtr]['code'] !== T_DOUBLE_COLON) {
            return;
        }

        $methodPtr = $phpcsFile->findNext(T_WHITESPACE, $doubleColonPtr + 1, null, true);
        if ($methodPtr === false || strtolower($tokens[$methodPtr]['content']) !== 'getinstance') {
            return;
        }

        $phpcsFile->addError(
            'Do not use ObjectManager::getInstance() as a service locator.',
            $stackPtr,
            self::ERROR_CODE
        );
    }

    private function isAllowedFile(string $filename): bool
    {
        $normalized = str_replace('\\', '/', $filename);

        return str_contains($normalized, '/Test/')
            || str_contains($normalized, '/dev/tests/')
            || str_ends_with($normalized, 'Factory.php');
    }
}
