<?php
/**
 * Copyright 2026 Vendivo.
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Vendivo\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ApiDataInterfaceDocBlockSniff implements Sniff
{
    private const MISSING_METHOD_DOCBLOCK_CODE = 'MissingMethodDocBlock';
    private const MISSING_PARAM_DOC_CODE = 'MissingParamDoc';
    private const MISSING_RETURN_DOC_CODE = 'MissingReturnDoc';
    private const MISSING_SETTER_RETURN_THIS_CODE = 'MissingSetterReturnThis';
    private const INVALID_SETTER_RETURN_DOC_CODE = 'InvalidSetterReturnDoc';
    private const GENERIC_ARRAY_PARAM_CODE = 'GenericArrayParamType';
    private const GENERIC_ARRAY_RETURN_CODE = 'GenericArrayReturnType';

    public function register()
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if (!$this->isApiInterfaceMethod($phpcsFile, $stackPtr)) {
            return;
        }

        $commentStart = $this->findFunctionDocBlock($phpcsFile, $stackPtr);
        if ($commentStart === null) {
            $phpcsFile->addError(
                'Magento API interface methods must have PHPDoc with @param and @return contract metadata.',
                $stackPtr,
                self::MISSING_METHOD_DOCBLOCK_CODE
            );

            return;
        }

        $paramDocs = $this->collectParamDocs($phpcsFile, $commentStart);
        $returnDoc = $this->findReturnDoc($phpcsFile, $commentStart);

        $this->validateParams($phpcsFile, $stackPtr, $paramDocs);
        $this->validateReturn(
            $phpcsFile,
            $stackPtr,
            $returnDoc,
            $this->isApiDataInterfaceFileOrNamespace($phpcsFile, $stackPtr)
        );
    }

    private function isApiInterfaceMethod(File $phpcsFile, int $functionPtr): bool
    {
        return $this->isInsideInterface($phpcsFile, $functionPtr)
            && $this->isApiInterfaceFileOrNamespace($phpcsFile, $functionPtr);
    }

    private function isInsideInterface(File $phpcsFile, int $functionPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (($tokens[$functionPtr]['conditions'] ?? []) as $conditionCode) {
            if ((int)$conditionCode === T_INTERFACE) {
                return true;
            }
        }

        return false;
    }

    private function isApiInterfaceFileOrNamespace(File $phpcsFile, int $functionPtr): bool
    {
        $path = str_replace('\\', '/', $phpcsFile->getFilename());
        if (
            str_ends_with($path, 'Interface.php')
            && preg_match('#/Api(?:/|$)#', $path) === 1
        ) {
            return true;
        }

        $namespace = $this->findNamespace($phpcsFile, $functionPtr);
        if ($namespace === '') {
            return false;
        }

        return preg_match('/\\\\Api(?:\\\\|$)/', '\\' . $namespace . '\\') === 1;
    }

    private function isApiDataInterfaceFileOrNamespace(File $phpcsFile, int $functionPtr): bool
    {
        $path = str_replace('\\', '/', $phpcsFile->getFilename());
        if (
            str_ends_with($path, 'Interface.php')
            && preg_match('#/Api/(?:[^/]+/)*Data/#', $path) === 1
        ) {
            return true;
        }

        $namespace = $this->findNamespace($phpcsFile, $functionPtr);
        if ($namespace === '') {
            return false;
        }

        return preg_match('/\\\\Api\\\\(?:[^\\\\]+\\\\)*Data(\\\\|$)/', '\\' . $namespace . '\\') === 1;
    }

    private function findNamespace(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $namespacePtr = $phpcsFile->findPrevious(T_NAMESPACE, $stackPtr);
        if ($namespacePtr === false) {
            return '';
        }

        $endPtr = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], $namespacePtr + 1);
        if ($endPtr === false) {
            return '';
        }

        $namespace = '';
        for ($ptr = $namespacePtr + 1; $ptr < $endPtr; $ptr++) {
            if ($tokens[$ptr]['code'] === T_WHITESPACE) {
                continue;
            }

            $namespace .= $tokens[$ptr]['content'];
        }

        return trim($namespace);
    }

    private function findFunctionDocBlock(File $phpcsFile, int $functionPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $functionPtr - 1;
        $allowedBeforeFunction = [
            T_ABSTRACT => true,
            T_FINAL => true,
            T_PRIVATE => true,
            T_PROTECTED => true,
            T_PUBLIC => true,
            T_STATIC => true,
        ];

        while (($ptr = $phpcsFile->findPrevious(T_WHITESPACE, $ptr, null, true)) !== false) {
            if (isset($allowedBeforeFunction[$tokens[$ptr]['code']])) {
                $ptr--;
                continue;
            }

            if ($tokens[$ptr]['code'] === T_DOC_COMMENT_CLOSE_TAG) {
                return (int)$tokens[$ptr]['comment_opener'];
            }

            return null;
        }

        return null;
    }

    /**
     * @return array<string, array{type: string, token: int}>
     */
    private function collectParamDocs(File $phpcsFile, int $commentStart): array
    {
        $tokens = $phpcsFile->getTokens();
        $paramDocs = [];

        foreach ($tokens[$commentStart]['comment_tags'] as $tagPtr) {
            if (strtolower($tokens[$tagPtr]['content']) !== '@param') {
                continue;
            }

            $contentPtr = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tagPtr + 1, $tokens[$commentStart]['comment_closer']);
            if ($contentPtr === false || $tokens[$contentPtr]['line'] !== $tokens[$tagPtr]['line']) {
                continue;
            }

            $matches = [];
            if (!preg_match('/^\s*(.+?)\s+(?:\.\.\.)?&?(\$[A-Za-z_][A-Za-z0-9_]*)\b/', $tokens[$contentPtr]['content'], $matches)) {
                continue;
            }

            $paramDocs[ltrim($matches[2], '$')] = [
                'type' => trim($matches[1]),
                'token' => $tagPtr,
            ];
        }

        return $paramDocs;
    }

    /**
     * @return array{type: string, token: int}|null
     */
    private function findReturnDoc(File $phpcsFile, int $commentStart): ?array
    {
        $tokens = $phpcsFile->getTokens();

        foreach ($tokens[$commentStart]['comment_tags'] as $tagPtr) {
            if (strtolower($tokens[$tagPtr]['content']) !== '@return') {
                continue;
            }

            $contentPtr = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tagPtr + 1, $tokens[$commentStart]['comment_closer']);
            if ($contentPtr === false || $tokens[$contentPtr]['line'] !== $tokens[$tagPtr]['line']) {
                return [
                    'type' => '',
                    'token' => $tagPtr,
                ];
            }

            return [
                'type' => $this->extractLeadingDocType($tokens[$contentPtr]['content']),
                'token' => $tagPtr,
            ];
        }

        return null;
    }

    /**
     * @param array<string, array{type: string, token: int}> $paramDocs
     */
    private function validateParams(File $phpcsFile, int $functionPtr, array $paramDocs): void
    {
        foreach ($phpcsFile->getMethodParameters($functionPtr) as $parameter) {
            $paramName = ltrim((string)($parameter['name'] ?? ''), '$');
            if ($paramName === '') {
                continue;
            }

            $doc = $paramDocs[$paramName] ?? null;
            if ($doc === null) {
                $phpcsFile->addError(
                    'Magento API interface parameter $%s must have @param PHPDoc.',
                    $functionPtr,
                    self::MISSING_PARAM_DOC_CODE,
                    [$paramName]
                );

                continue;
            }

            $nativeType = $this->normalizeNativeType((string)($parameter['type_hint'] ?? ''));
            if ($this->containsArrayType($nativeType) && !$this->isPreciseArrayDocType($doc['type'])) {
                $phpcsFile->addError(
                    'Magento API interface parameter $%s must not use generic PHPDoc type "%s"; declare item type, key/value type, or shape.',
                    $doc['token'],
                    self::GENERIC_ARRAY_PARAM_CODE,
                    [$paramName, $doc['type']]
                );
            }
        }
    }

    /**
     * @param array{type: string, token: int}|null $returnDoc
     */
    private function validateReturn(
        File $phpcsFile,
        int $functionPtr,
        ?array $returnDoc,
        bool $enforceDataInterfaceSetterContract
    ): void
    {
        $methodName = (string)$phpcsFile->getDeclarationName($functionPtr);
        if ($enforceDataInterfaceSetterContract && $this->isSetter($methodName)) {
            $this->validateSetterReturn($phpcsFile, $functionPtr, $returnDoc);

            return;
        }

        if ($returnDoc === null) {
            $phpcsFile->addError(
                'Magento API interface method %s() must have @return PHPDoc.',
                $functionPtr,
                self::MISSING_RETURN_DOC_CODE,
                [$methodName]
            );

            return;
        }

        $properties = $phpcsFile->getMethodProperties($functionPtr);
        $nativeType = $this->normalizeNativeType((string)($properties['return_type'] ?? ''));
        if ($this->containsArrayType($nativeType) && !$this->isPreciseArrayDocType($returnDoc['type'])) {
            $phpcsFile->addError(
                'Magento API interface return PHPDoc must not use generic type "%s"; declare item type, key/value type, or shape.',
                $returnDoc['token'],
                self::GENERIC_ARRAY_RETURN_CODE,
                [$returnDoc['type']]
            );
        }
    }

    /**
     * @param array{type: string, token: int}|null $returnDoc
     */
    private function validateSetterReturn(File $phpcsFile, int $functionPtr, ?array $returnDoc): void
    {
        $methodName = (string)$phpcsFile->getDeclarationName($functionPtr);
        if ($returnDoc === null) {
            $phpcsFile->addError(
                'Magento API data interface setter %s() must document @return $this for fluent WebAPI metadata.',
                $functionPtr,
                self::MISSING_SETTER_RETURN_THIS_CODE,
                [$methodName]
            );

            return;
        }

        if (trim($returnDoc['type']) !== '$this') {
            $phpcsFile->addError(
                'Magento API data interface setter %s() must document @return $this, got "%s".',
                $returnDoc['token'],
                self::INVALID_SETTER_RETURN_DOC_CODE,
                [$methodName, $returnDoc['type']]
            );
        }
    }

    private function normalizeNativeType(string $type): string
    {
        $type = strtolower(trim($type));
        $type = str_replace([' ', '\\'], '', $type);

        if (str_starts_with($type, '?')) {
            $type = substr($type, 1) . '|null';
        }

        if (str_contains($type, '|')) {
            $parts = array_values(array_filter(explode('|', $type), static fn (string $part): bool => $part !== ''));
            sort($parts);

            return implode('|', $parts);
        }

        return $type;
    }

    private function containsArrayType(string $normalizedNativeType): bool
    {
        return in_array('array', explode('|', $normalizedNativeType), true);
    }

    private function isPreciseArrayDocType(string $type): bool
    {
        $type = strtolower(trim($type));
        $type = ltrim($type, '?');
        $type = preg_replace('/\|null\b|\bnull\|/', '', $type) ?? $type;

        if (in_array($type, ['array', 'mixed[]', 'array<mixed>', 'array<array-key,mixed>'], true)) {
            return false;
        }

        return str_contains($type, '[]')
            || str_starts_with($type, 'array<')
            || str_starts_with($type, 'array{')
            || str_starts_with($type, 'list<')
            || str_starts_with($type, 'non-empty-array<')
            || str_starts_with($type, 'non-empty-list<');
    }

    private function extractLeadingDocType(string $content): string
    {
        $content = trim($content);
        $type = '';
        $depth = 0;
        $length = strlen($content);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $content[$offset];
            if ($depth === 0 && ctype_space($char)) {
                break;
            }

            if (in_array($char, ['<', '{', '[', '('], true)) {
                $depth++;
            } elseif (in_array($char, ['>', '}', ']', ')'], true) && $depth > 0) {
                $depth--;
            }

            $type .= $char;
        }

        return trim($type);
    }

    private function isSetter(string $methodName): bool
    {
        return str_starts_with($methodName, 'set') && strlen($methodName) > 3;
    }
}
