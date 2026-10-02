<?php

declare(strict_types=1);

namespace Vendivo\ModuleComposer\Validation;

use Composer\Json\JsonFile;
use Composer\Json\JsonValidationException;
use Throwable;
use Vendivo\ModuleComposer\Diagnostic;

final class ComposerSchemaValidator
{
    /** @return list<Diagnostic> */
    public function validate(string $composerPath, string $relativePath): array
    {
        try {
            (new JsonFile($composerPath))->validateSchema(JsonFile::STRICT_SCHEMA);

            return [];
        } catch (JsonValidationException $exception) {
            $errors = $exception->getErrors();
            if ($errors === []) {
                $errors = [$exception->getMessage()];
            }

            return array_values(array_map(
                static fn (string $error): Diagnostic => Diagnostic::error(
                    'COMPOSER-SCHEMA',
                    $relativePath,
                    $error
                ),
                $errors
            ));
        } catch (Throwable $exception) {
            return [Diagnostic::error('COMPOSER-SCHEMA', $relativePath, $exception->getMessage())];
        }
    }
}
