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

class Patch57 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $legacyHashes = [
            'mod_client_confirm' => ['7473f9235472556c07fbb28ca81cd32d5ba059251520938a113322cf9d63e9cf', 'f16aa27ac0c8c504aee96f76177aae7f57c3165d9207b1a4367c1d60da7b44b5'],
            'mod_client_password_reset_information' => ['f4e810c0a880b72dac35d7d7c1317fabdbc253c94b2c40387e72300fc502e75b', '90b35823f1a4dd26452e7fda276b3d73e9a84efb93ad36b70dfe31b11ef533f9'],
            'mod_client_password_reset_request' => ['7afb81438ba5fe02b0cb1de5e2a0adafa1d54163fa6db3c594b052ae41d7a702', 'b21dd141467890e4c95a4d1adbf52d692905f352d80db77467ed40f8280e0d58'],
            'mod_client_signup' => ['6f2cbf4dce2868d78962dd5f558782dc39932b94eff5d94a74b4c0b25bd5bc11', '5bee627ec67d303672375552c78b87e6126c25cffb935f44aa18db1493ffda8f'],
            'mod_email_test' => ['4027353f3eb85a550b896902875f774342102ca7acfd2b12f13facc4caca8a54', '148ef2a8b29e8d8a8fee6bfb793444185388c7887c690699065fbd336b4f4ea0'],
            'mod_invoice_created' => ['60efe042a16f3841b2194dc2324847d7d0ba9dcf91c4dee4ced6c9fb8fc7ff85', '7a34fabdb113b81c6f9e83624518ae669ae4e83bb3c4e6a0115aaa5c902830d2'],
            'mod_invoice_due_after' => ['25f93a3b7c96de7817ed0a39e7e881bc3cfe502e882a31bd821ee7ddfc90fce6', 'db783631386791d6289f446b61faef3725457a3aaa20b8af6c078280b1b3b9d9'],
            'mod_invoice_paid' => ['f652477160cbed14cd4721f1d07f6b1013dfa5f07a14f6917fca929a906b82cf', '2eea663a8302b99800b384ccec9b9cc1766c461904914d0591cbae8e307b97ed'],
            'mod_invoice_payment_reminder' => ['6384d7498e061a983796a0b6a0e6cff59f88c19512be044538d4f34809325fc9', 'd589ca24eaae34ebed6df5102739e4a4d5dc0f8744772a9b4d602096e377b910'],
            'mod_serviceapikey_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'de9258ac2a48938f47f51f8e4b1c97731137b68d33e7109f576f2b6a89c4986d'],
            'mod_serviceapikey_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_serviceapikey_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_serviceapikey_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_serviceapikey_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicecustom_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'ab6cfb1c88010cdd10ca310ca41c8ed4ae3d128199ea2be7abdd597bd5f33aff'],
            'mod_servicecustom_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_servicecustom_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicecustom_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicecustom_unsuspended' => ['c3db0c91caadb6fa1b107613271aada2ec237a209ad6743b5e574ef9827c7eb4', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicedomain_activated' => ['86bae76df848375254eb517edd07e7427ad3592e826198b1c5b6acc802feb9d2', '168e68757aa53b3f301f04d878df0dfdf0ea4213a1b37d595be885156c6a24bf'],
            'mod_servicedomain_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicedomain_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicedomain_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicedownloadable_activated' => ['8b6b568e1c5e7cef0c431ef488d9ed26d846fdf44770f48c9665cce81b6f89c9', '2edf67abf09e55967c70a7157d3d988c6ac9d32fd67edaf911c6b65ae8d118bc'],
            'mod_servicehosting_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', '35b4d6cd75ce2fbca69244da44d23f00c823e354d5538818dd1313f1af3ffc8c'],
            'mod_servicehosting_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_servicehosting_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicehosting_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicehosting_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_servicelicense_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'b005b720b43aab8299220e3a5a6fe547c1d5ad9755adc6e3c6efe5fe4546adb6'],
            'mod_servicelicense_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '3e46c6c4dc87633155a8c4d800dea49e0f43e14b6424715d2b340f73b362791f'],
            'mod_servicelicense_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '63711a8a5d274fac3fa001d432b9b822fb621fd78792828cb894de261476c455'],
            'mod_servicelicense_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'cb644bb2681b59cc377f1b5854e7ac8a5bc07ec4b018c88a613c3c30c5542728'],
            'mod_servicelicense_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '8fa4e7c609263662e91edd94d0362a573fe2e34a4b3733791bc7fdd9319f56de'],
            'mod_staff_client_order' => ['070ec5ab4051913d7e4d62904da33f5fddbc35cf6a7a2d71df281d43baa4254b', '4846afa1c791dfcbf17e6e001f1565254a29d9ca0829ff89853b14ecf8f89294'],
            'mod_staff_client_signup' => ['13018c8f888668bbf731367ca3388a34f7170ff213bf1fa07771c375c4aae775', 'a573783ce28183abe13257c5b2663f750f8e37eb9056aad0c6abf203573d2f83'],
            'mod_staff_password_reset_approve' => ['f4e810c0a880b72dac35d7d7c1317fabdbc253c94b2c40387e72300fc502e75b', '8e3a3e5e4ffb9b393c08bcef9c07818a73c70d8c25a6cbed3f9ef339008e32bb'],
            'mod_staff_password_reset_request' => ['7afb81438ba5fe02b0cb1de5e2a0adafa1d54163fa6db3c594b052ae41d7a702', 'f6b1527a328af6b4e1c7a0f4d96d4cea7fa328a053508dc614385d39d78cc925'],
            'mod_staff_ticket_close' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'd54e0d97b88218404c731929eb2a39857ee422070e857802374626b72bf84b2f'],
            'mod_staff_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'dbe1dee98606a36285a79d019e6180e62ee04cc7e3c3f16d34045d6c70aaf78e'],
            'mod_staff_ticket_reply' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '87238cbde59fd9faa31c8e0d6039924ad6068f1ca3858ad91b06cc4633243153'],
            'mod_support_helpdesk_ticket_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '709e2ff17c01e1056ef4824e507e4474a6eeb1e3f35d5acd1c646074d98cc5d6'],
            'mod_support_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '78fe28d46cba9f344b9a2045114b1a7d723dc8a62602690413ecac30287bcae2'],
            'mod_support_ticket_staff_close' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '965d0369564e2edc832db28564e414b960f9559213750d7f1f1baf0b1cceb2a7'],
            'mod_support_ticket_staff_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '98ff5f36b64fb0f874048c7dd44310e98ecf2b08f49e8dab701d9d4bb5e81ba5'],
            'mod_support_ticket_staff_reply' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '74aea13a2cbe71aee7b8071480fb4b6767d5690589070d1a06721002618ed29f'],
        ];

        $patcher->restoreLegacyDefaultEmailTemplates($legacyHashes);
    }
}
