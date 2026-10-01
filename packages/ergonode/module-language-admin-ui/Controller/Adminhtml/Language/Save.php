<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Controller\Adminhtml\Language;

use Ergonode\Language\Exception\MappingConflictException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;
use Ergonode\Language\Api\LanguageStoreMappingSaverInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Language::language_mapping_save';

    public function __construct(
        Context $context,
        private readonly Json $json,
        private readonly LanguageStoreMappingSaverInterface $mappingSaver,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $saved = $this->saveMappings();
            return $result->setData([
                'success' => true,
                'message' => (string)__('Language mappings have been saved.'),
                'stats' => $saved['stats'],
                'revision' => $saved['revision'],
            ]);
        } catch (MappingConflictException $exception) {
            return $result->setHttpResponseCode(409)->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected error while saving Ergonode language mappings.', [
                'exception' => $exception,
            ]);
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to save language mappings. Check the Magento logs for details.'),
            ]);
        }
    }

    /**
     * @return array{stats: array{inserted: int, updated: int, deleted: int, unchanged: int}, revision: string}
     * @throws LocalizedException
     */
    private function saveMappings(): array
    {
        $payload = trim((string)$this->getRequest()->getParam('payload', ''));
        if ($payload === '') {
            throw new LocalizedException(__('Missing language mapping payload.'));
        }

        $decoded = $this->json->unserialize($payload);
        if (!is_array($decoded) || !isset($decoded['mappings']) || !is_array($decoded['mappings'])) {
            throw new LocalizedException(__('Invalid language mapping payload.'));
        }
        $visibility = isset($decoded['visibility']) && is_array($decoded['visibility'])
            ? $decoded['visibility']
            : [];

        $revision = $decoded['revision'] ?? null;
        if (!is_string($revision) || preg_match('/^[a-f0-9]{64}$/D', $revision) !== 1) {
            throw new LocalizedException(__('Missing or invalid language mapping revision. Reload the page.'));
        }

        return $this->mappingSaver->save($decoded['mappings'], $visibility, $revision);
    }
}
