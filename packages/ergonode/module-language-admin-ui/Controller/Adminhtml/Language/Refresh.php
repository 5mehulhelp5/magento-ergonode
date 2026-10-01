<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Controller\Adminhtml\Language;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Throwable;
use Ergonode\Language\Api\ErgonodeLanguageCodesRefresherInterface;

class Refresh extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Ergonode_Language::language_mapping_refresh';

    public function __construct(
        Context $context,
        private readonly ErgonodeLanguageCodesRefresherInterface $languageCodesRefresher,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $languageCodes = $this->languageCodesRefresher->refreshErgonodeLanguageCodes();

            return $result->setData([
                'success' => true,
                'message' => (string)__('Ergonode languages have been refreshed.'),
                'count' => count($languageCodes),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('Unexpected error while refreshing Ergonode languages.', [
                'exception' => $exception,
            ]);
            return $result->setData([
                'success' => false,
                'message' => (string)__('Unable to refresh Ergonode languages. Check the Magento logs for details.'),
            ]);
        }
    }
}
