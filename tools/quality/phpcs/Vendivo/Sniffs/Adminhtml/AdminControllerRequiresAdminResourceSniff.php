<?php
/**
 * Copyright 2026 Vendivo.
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Vendivo\Sniffs\Adminhtml;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class AdminControllerRequiresAdminResourceSniff implements Sniff
{
    private const ERROR_CODE = 'MissingAdminResource';

    private const BACKEND_ACTION = 'Magento\Backend\App\Action';

    /**
     * @inheritDoc
     */
    public function register()
    {
        return [T_CLASS];
    }

    /**
     * @inheritDoc
     */
    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if (!isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        if ($this->isAbstractClass($phpcsFile, $stackPtr)) {
            return;
        }

        $useMap = $this->collectUseMap($phpcsFile, $stackPtr);
        if (!$this->isDirectBackendAction($phpcsFile, $stackPtr, $useMap)) {
            return;
        }

        if ($this->hasAdminResourceConstant($phpcsFile, $stackPtr)) {
            return;
        }

        $phpcsFile->addError(
            'Admin controllers extending Magento\Backend\App\Action must declare ADMIN_RESOURCE.',
            $stackPtr,
            self::ERROR_CODE
        );
    }

    private function isAbstractClass(File $phpcsFile, int $classPtr): bool
    {
        $abstractPtr = $phpcsFile->findPrevious(T_ABSTRACT, $classPtr - 1, null, false, null, true);

        return $abstractPtr !== false;
    }

    /**
     * @return array<string, string>
     */
    private function collectUseMap(File $phpcsFile, int $classPtr): array
    {
        $useMap = [];
        $usePtr = 0;
        while (($usePtr = $phpcsFile->findNext(T_USE, $usePtr + 1, $classPtr)) !== false) {
            $semicolonPtr = $phpcsFile->findNext(T_SEMICOLON, $usePtr + 1, $classPtr);
            if ($semicolonPtr === false) {
                continue;
            }

            $statement = trim($phpcsFile->getTokensAsString($usePtr + 1, $semicolonPtr - $usePtr - 1));
            if ($statement === '' || preg_match('/^(function|const)\s+/i', $statement)) {
                continue;
            }

            $statement = preg_replace('/\s+/', ' ', $statement) ?? $statement;
            $parts = preg_split('/\s+as\s+/i', $statement);
            $className = $this->normalizeClassName($parts[0] ?? '');
            if ($className === '') {
                continue;
            }

            $lastSeparator = strrpos($className, '\\');
            $alias = $parts[1] ?? ($lastSeparator === false ? $className : substr($className, $lastSeparator + 1));
            $useMap[strtolower($alias)] = $className;
        }

        return $useMap;
    }

    /**
     * @param array<string, string> $useMap
     */
    private function isDirectBackendAction(File $phpcsFile, int $classPtr, array $useMap): bool
    {
        $tokens = $phpcsFile->getTokens();
        $extendsPtr = $phpcsFile->findNext(T_EXTENDS, $classPtr + 1, $tokens[$classPtr]['scope_opener']);
        if ($extendsPtr === false) {
            return false;
        }

        $extendedClass = $this->readClassName($phpcsFile, $extendsPtr + 1, $tokens[$classPtr]['scope_opener']);
        if ($extendedClass === '') {
            return false;
        }

        return $this->resolveClassName($extendedClass, $useMap) === self::BACKEND_ACTION;
    }

    private function hasAdminResourceConstant(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $scopeOpener = $tokens[$classPtr]['scope_opener'];
        $scopeCloser = $tokens[$classPtr]['scope_closer'];
        $constPtr = $scopeOpener;

        while (($constPtr = $phpcsFile->findNext(T_CONST, $constPtr + 1, $scopeCloser)) !== false) {
            $semicolonPtr = $phpcsFile->findNext(T_SEMICOLON, $constPtr + 1, $scopeCloser);
            if ($semicolonPtr === false) {
                continue;
            }

            $namePtr = $constPtr;
            while (($namePtr = $phpcsFile->findNext(T_STRING, $namePtr + 1, $semicolonPtr)) !== false) {
                if ($tokens[$namePtr]['content'] === 'ADMIN_RESOURCE') {
                    return true;
                }
            }
        }

        return false;
    }

    private function readClassName(File $phpcsFile, int $startPtr, int $endPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $className = '';
        for ($ptr = $startPtr; $ptr < $endPtr; $ptr++) {
            if (in_array($tokens[$ptr]['code'], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $className .= $tokens[$ptr]['content'];
                continue;
            }

            if ($className !== '' && $tokens[$ptr]['code'] !== T_WHITESPACE) {
                break;
            }
        }

        return $this->normalizeClassName($className);
    }

    /**
     * @param array<string, string> $useMap
     */
    private function resolveClassName(string $className, array $useMap): string
    {
        $className = $this->normalizeClassName($className);
        if ($className === '') {
            return '';
        }

        $separatorPos = strpos($className, '\\');
        $alias = $separatorPos === false ? $className : substr($className, 0, $separatorPos);
        $mappedClass = $useMap[strtolower($alias)] ?? null;
        if ($mappedClass === null) {
            return $className;
        }

        if ($separatorPos === false) {
            return $mappedClass;
        }

        return $mappedClass . substr($className, $separatorPos);
    }

    private function normalizeClassName(string $className): string
    {
        return trim(trim($className), '\\');
    }
}
