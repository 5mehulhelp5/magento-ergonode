<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Integration\Controller\Adminhtml\Language;

use Ergonode\Language\Api\ErgonodeLanguageCodesRefresherInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class RefreshTest extends MutationControllerTestCase
{
    protected const string SERVICE = ErgonodeLanguageCodesRefresherInterface::class;
    protected const string OPERATION = 'refreshErgonodeLanguageCodes';

    protected $resource = 'Ergonode_Language::language_mapping_refresh';
    protected $uri = 'backend/ergonode/language/refresh';
}
