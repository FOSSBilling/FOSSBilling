<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch90 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Patch 57 used hashes from templates newer than the last 0.7 release. During a
        // 0.7.2 upgrade, patch 52 consequently classified untouched built-ins as user
        // overrides and retained Twig filters that no longer exist in 0.8.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3985
        $brokenV072TemplateHashes = [
            'mod_client_confirm' => ['7473f9235472556c07fbb28ca81cd32d5ba059251520938a113322cf9d63e9cf', 'be777c99543dc373d7466cfce4b497b1542a02718927d4e88797abd651a99044'],
            'mod_client_password_reset_information' => ['f4e810c0a880b72dac35d7d7c1317fabdbc253c94b2c40387e72300fc502e75b', '2f5719037fbc92cfc8f6688b0c704ffbe0005ee3fa172a5b77b3f9ae16fe3ab6'],
            'mod_client_password_reset_request' => ['7afb81438ba5fe02b0cb1de5e2a0adafa1d54163fa6db3c594b052ae41d7a702', 'c2c974c9e7dd6418e1505ce3b3c6107427752e7332893b38be9af0ad5af98ead'],
            'mod_client_signup' => ['6f2cbf4dce2868d78962dd5f558782dc39932b94eff5d94a74b4c0b25bd5bc11', '5bee627ec67d303672375552c78b87e6126c25cffb935f44aa18db1493ffda8f'],
            'mod_invoice_created' => ['60efe042a16f3841b2194dc2324847d7d0ba9dcf91c4dee4ced6c9fb8fc7ff85', '3b9677641c2eb3e8b34abae05596593a66d7014cd5ad40220c1a1ea8614e5b43'],
            'mod_invoice_due_after' => ['25f93a3b7c96de7817ed0a39e7e881bc3cfe502e882a31bd821ee7ddfc90fce6', 'cce15f29e75f5c10221ba67aca1e65408b48562a23d4a1ae921d79ec28f81f4f'],
            'mod_invoice_paid' => ['f652477160cbed14cd4721f1d07f6b1013dfa5f07a14f6917fca929a906b82cf', '74c4e432018002c14b698eb332974691bd61fa30da4b29ac12484669abaabbe6'],
            'mod_invoice_payment_reminder' => ['6384d7498e061a983796a0b6a0e6cff59f88c19512be044538d4f34809325fc9', 'fcd79aa5dbb8652a2a9ca7c876486fbe03932ebe93177eb3f4a6dd43c0c280a3'],
            'mod_serviceapikey_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', '10b0df7c373b586dc4cb896fe932c1970fc99fefa7b3668146d1bdd50ef7f40d'],
            'mod_serviceapikey_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '617d90370bd247e2445887971f075b33730f712f4de735cf5652dffa562969bb'],
            'mod_serviceapikey_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '42cfb6531c20ba5994619772c6ddad336941e311ade3ffc8a1b42d137376f737'],
            'mod_serviceapikey_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'e340eec846ca44d5606a7e2dcbbe898df95cde58ff9214e1f5b223bc33c0afe3'],
            'mod_serviceapikey_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '96c1b1cc94262379db834bc2ce71fbceee00142947f1c85a13d9e032fe3a1695'],
            'mod_servicecustom_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', '984a47ff17244c4289ba67aa1b787e351c613fdb0b4cdf40a59cb29b70ac94a3'],
            'mod_servicecustom_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '617d90370bd247e2445887971f075b33730f712f4de735cf5652dffa562969bb'],
            'mod_servicecustom_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '42cfb6531c20ba5994619772c6ddad336941e311ade3ffc8a1b42d137376f737'],
            'mod_servicecustom_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'e340eec846ca44d5606a7e2dcbbe898df95cde58ff9214e1f5b223bc33c0afe3'],
            'mod_servicecustom_unsuspended' => ['c3db0c91caadb6fa1b107613271aada2ec237a209ad6743b5e574ef9827c7eb4', '96c1b1cc94262379db834bc2ce71fbceee00142947f1c85a13d9e032fe3a1695'],
            'mod_servicedomain_activated' => ['86bae76df848375254eb517edd07e7427ad3592e826198b1c5b6acc802feb9d2', '331f67a2c3fe4138ce674a2815501da786a01f23bac29bfea64ea187932d5494'],
            'mod_servicedomain_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '42cfb6531c20ba5994619772c6ddad336941e311ade3ffc8a1b42d137376f737'],
            'mod_servicedomain_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'e340eec846ca44d5606a7e2dcbbe898df95cde58ff9214e1f5b223bc33c0afe3'],
            'mod_servicedomain_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '96c1b1cc94262379db834bc2ce71fbceee00142947f1c85a13d9e032fe3a1695'],
            'mod_servicedownloadable_activated' => ['8b6b568e1c5e7cef0c431ef488d9ed26d846fdf44770f48c9665cce81b6f89c9', '880935ac246cbd24b445b5370bc2a9a6f7a44e614c2d452cf237cc6488490dbf'],
            'mod_servicehosting_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', '35f6d7fb074c50b8668e627651a88efc2170afbdd01692e2f0a89dcdac7b6273'],
            'mod_servicehosting_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '617d90370bd247e2445887971f075b33730f712f4de735cf5652dffa562969bb'],
            'mod_servicehosting_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '42cfb6531c20ba5994619772c6ddad336941e311ade3ffc8a1b42d137376f737'],
            'mod_servicehosting_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'e340eec846ca44d5606a7e2dcbbe898df95cde58ff9214e1f5b223bc33c0afe3'],
            'mod_servicehosting_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '96c1b1cc94262379db834bc2ce71fbceee00142947f1c85a13d9e032fe3a1695'],
            'mod_servicelicense_activated' => ['e0b4a447ea77919c587fd7446d546131270bebd3aa2d3890259ac52bf591721d', 'bb4570dc54ce070127effef08282b468079a24e7a084525a9d5ece5e4761dd05'],
            'mod_servicelicense_canceled' => ['4f2432d5320378a3420f1ebebe46e49c4e40f784b9e118b40f66f12815bc3e04', '617d90370bd247e2445887971f075b33730f712f4de735cf5652dffa562969bb'],
            'mod_servicelicense_renewed' => ['e84378b8698fec220a03bc1bd84943fa103593804d46bde1fe527b73b7dd027c', '42cfb6531c20ba5994619772c6ddad336941e311ade3ffc8a1b42d137376f737'],
            'mod_servicelicense_suspended' => ['514cb59946a731b9bb9f1180324463eae52fecb8b3ed1a5322ff6d666835f425', 'e340eec846ca44d5606a7e2dcbbe898df95cde58ff9214e1f5b223bc33c0afe3'],
            'mod_servicelicense_unsuspended' => ['9c959a766a9dbc4eeda29040a93fb48d095b0e7f2403b0a0d7f36162aaa749e0', '96c1b1cc94262379db834bc2ce71fbceee00142947f1c85a13d9e032fe3a1695'],
            'mod_staff_password_reset_approve' => ['f4e810c0a880b72dac35d7d7c1317fabdbc253c94b2c40387e72300fc502e75b', '08432b7e7b989b15fb35c0c0bbec965656895fcc600ae1bacc768682f2cbc3a9'],
            'mod_staff_password_reset_request' => ['7afb81438ba5fe02b0cb1de5e2a0adafa1d54163fa6db3c594b052ae41d7a702', '8e8d075aa1cb278f1a19c3b8424fd69e4d04afd539dd3f036de1e8cf26f01d6a'],
            'mod_staff_ticket_close' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'a3be974b06446135d1af41f21f35ccc92480cb0034e35f932b3eb97ebad2b402'],
            'mod_staff_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '08edc2cbc649426256f5d185b383365a0170ee643d57e1b42b6d861a61ab0bb5'],
            'mod_staff_ticket_reply' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', '083ae3df7b9f81d9970e95409ac335e489d719ccc29181f8df00241470f8f604'],
            'mod_support_helpdesk_ticket_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '2030fb956b8bbedde4e4e3c05c62675f3c6dfa34cea47a9990fd3c00e268b797'],
            'mod_support_ticket_open' => ['5b80a40322052bccb1e00854c02fe4ad46be9dfbbdbf0b17a641af99175b72c5', 'f18b62589ee5774c57c136d50ac1261e5749165ae37d8ed175782d39b5eebe8b'],
            'mod_support_ticket_staff_close' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', 'e3594bcf97a16bfa5ec7ac52d1bdb90897555950b03405a82c5e418027c58930'],
            'mod_support_ticket_staff_open' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', 'd25b63f99ed7aeb758bab9dbe30c5c31b4bc1f739d72df2543d25cc561290e2d'],
            'mod_support_ticket_staff_reply' => ['2fb0c49c240c05925211f0bd0e90b3de4ceab4287c1c89be6155f0a3d71d7811', '31b276116db735b2bd758f7091acd24cdb082d9a623630232142a44ad238d4bd'],
        ];

        $this->restoreLegacyDefaultEmailTemplates($patcher, $brokenV072TemplateHashes);
    }

    private function restoreLegacyDefaultEmailTemplates(Patcher $patcher, array $legacyHashes): void
    {
        $templates = $patcher->fetchAll('SELECT id, action_code, subject, content FROM email_template WHERE is_overridden = 1 AND is_custom = 0');

        foreach ($templates as $template) {
            $code = (string) ($template['action_code'] ?? '');
            if (!isset($legacyHashes[$code])) {
                continue;
            }

            $subject = (string) ($template['subject'] ?? '');
            $content = (string) ($template['content'] ?? '');

            [$oldSubjectHash, $oldContentHash] = $legacyHashes[$code];
            if (hash('sha256', $subject) !== $oldSubjectHash || hash('sha256', $content) !== $oldContentHash) {
                continue;
            }

            $default = $this->getDefaultEmailTemplateData($patcher, $code);
            if ($default === null) {
                continue;
            }

            $patcher->executeSql(
                'UPDATE email_template SET is_overridden = 0, subject = :subject, content = :content WHERE id = :id',
                [
                    'subject' => $default['subject'],
                    'content' => $default['content'],
                    'id' => $template['id'],
                ]
            );
        }
    }

    private function getDefaultEmailTemplateData(Patcher $patcher, string $code): ?array
    {
        $path = $this->getDefaultEmailTemplatePath($patcher, $code);
        if ($path === null) {
            return null;
        }

        $template = $patcher->filesystem->readFile($path);

        $subject = ucwords(str_replace('_', ' ', $code));
        preg_match('#{%\\s*block subject\\s*%}(.*?){%\\s*endblock\\s*%}#s', $template, $subjectMatches);
        if (isset($subjectMatches[1])) {
            $subject = $subjectMatches[1];
        }

        $content = '';
        preg_match('/{%.?block content.?%}((.*?\n)+){%.?endblock.?%}/m', $template, $contentMatches);
        if (isset($contentMatches[1])) {
            $content = $contentMatches[1];
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    private function getDefaultEmailTemplatePath(Patcher $patcher, string $code): ?string
    {
        $matches = [];
        if (!preg_match('/mod_([a-zA-Z0-9]+)_([a-zA-Z0-9]+)/i', $code, $matches)) {
            return null;
        }

        $path = Path::join(PATH_MODS, ucfirst($matches[1]), 'templates/email', "{$code}.html.twig");

        return $patcher->filesystem->exists($path) ? $path : null;
    }
}
