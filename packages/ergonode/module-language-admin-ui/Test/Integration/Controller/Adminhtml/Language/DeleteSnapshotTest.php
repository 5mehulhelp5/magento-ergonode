<?php

declare(strict_types=1);

namespace Ergonode\LanguageAdminUi\Test\Integration\Controller\Adminhtml\Language;

use Ergonode\Language\Api\LanguageSnapshotRemoverInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DbIsolation;

#[AppArea('adminhtml'), AppIsolation(true), DbIsolation(true)]
class DeleteSnapshotTest extends MutationControllerTestCase
{
    protected const string SERVICE = LanguageSnapshotRemoverInterface::class;
    protected const string OPERATION = 'remove';

    protected $resource = 'Ergonode_Language::language_mapping_save';
    protected $uri = 'backend/ergonode/language/deleteSnapshot';
}
