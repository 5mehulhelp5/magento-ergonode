<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Model;

use Ergonode\Language\Api\LanguageCodeNormalizerInterface;
use Magento\Framework\Locale\ListsInterface;

use function mb_strtolower;
use function mb_strtoupper;
use function mb_substr;

class LocaleLabelProvider
{
    /** @var array<string, string>|null */
    private ?array $labelsByCode = null;

    public function __construct(
        private readonly ListsInterface $localeLists,
        private readonly LanguageCodeNormalizerInterface $languageCodeNormalizer
    ) {
    }

    public function getLabel(string $code): string
    {
        $normalizedCode = $this->languageCodeNormalizer->normalize($code);
        $label = $this->getLabelsByCode()[$normalizedCode] ?? '';

        return $label !== ''
            ? $this->uppercaseFirst($this->removeDuplicateQualifier($label))
            : $code;
    }

    /** @return array<string, string> */
    private function getLabelsByCode(): array
    {
        if ($this->labelsByCode !== null) {
            return $this->labelsByCode;
        }

        $this->labelsByCode = [];
        foreach ($this->localeLists->getOptionLocales() as $locale) {
            if (!is_array($locale)) {
                continue;
            }

            $code = trim((string)($locale['value'] ?? ''));
            $label = trim((string)($locale['label'] ?? ''));
            if ($code !== '' && $label !== '') {
                $this->labelsByCode[$this->languageCodeNormalizer->normalize($code)] = $label;
            }
        }

        return $this->labelsByCode;
    }

    private function uppercaseFirst(string $label): string
    {
        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    private function removeDuplicateQualifier(string $label): string
    {
        if (preg_match('/^(.+?)\s*\((.+)\)$/u', $label, $matches) !== 1) {
            return $label;
        }

        $name = trim((string)($matches[1] ?? ''));
        $qualifier = trim((string)($matches[2] ?? ''));

        return $name !== '' && mb_strtolower($name) === mb_strtolower($qualifier)
            ? $name
            : $label;
    }
}
