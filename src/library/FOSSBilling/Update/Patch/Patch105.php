<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch105 implements PatchInterface
{
    public function getVersion(): int
    {
        return 105;
    }

    public function apply(Patcher $patcher): void
    {
        // Remove core files that were deleted or moved after the 0.8.5 release.
        // Updates extract archives over the existing installation, so obsolete
        // files need explicit cleanup while user-owned data remains untouched.
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'Box', 'BeanHelper.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Database.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'DbLoggedPDOStatement.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Log.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'LogDb.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Translate.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Crypt.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Period.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Box', 'Url.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'PdoSessionHandler.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'DbLoggedPDOStatement.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ActivityAdminHistory.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ActivityClientEmail.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ActivityClientHistory.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ActivityClientHistoryTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ActivitySystem.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Admin.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'AdminPasswordReset.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Cart.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'CartProduct.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Client.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ClientBalance.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ClientGroup.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ClientOrder.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ClientOrderMeta.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ClientOrderStatus.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ClientPasswordReset.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Extension.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ExtensionMeta.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Form.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'FormField.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Guest.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Invoice.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'InvoiceItem.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ModEmailQueue.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'PayGateway.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceApiKey.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceCustom.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceDomain.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceDownloadable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceHosting.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceHostingHp.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceHostingServer.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ServiceLicense.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Session.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Setting.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Subscription.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Tax.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Tld.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'TldRegistrar.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Transaction.php') => 'unlink',
            Path::join(PATH_MODS, 'Product', 'Repository', 'DomainPricingRepository.php') => 'unlink',
            Path::join(PATH_MODS, 'Product', 'Repository', 'ProductOrderRepository.php') => 'unlink',
            Path::join(PATH_MODS, 'Product', 'Repository', 'ProductPaymentPeriodRepository.php') => 'unlink',
            Path::join(PATH_MODS, 'Servicecustom', 'Repository', 'ServiceCustomRepository.php') => 'unlink',
        ]);

        $patcher->removeEmptyDirectories([
            Path::join(PATH_LIBRARY, 'Box'),
            Path::join(PATH_LIBRARY, 'FOSSBilling', 'Session'),
            Path::join(PATH_LIBRARY, 'Model'),
            Path::join(PATH_MODS, 'Product', 'Repository'),
            Path::join(PATH_MODS, 'Servicecustom', 'Repository'),
        ]);
    }
}
