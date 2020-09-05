<?php
/**
 * Output.php
 *
 * @since       2008-01-02
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 *
 * This file is part of tc-lib-pdf-encrypt software library.
 */

namespace Com\Tecnick\Pdf\Encrypt;

/**
 * Com\Tecnick\Pdf\Encrypt\Output
 *
 * PHP class for output encrypt PDF object
 *
 * @since       2008-01-02
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
abstract class Output
{
    /**
     * Get the PDF encryption block
     *
     * @param int $pon Current PDF object number
     *
     * return string
     */
    public function getPdfEncryptionObj(&$pon)
    {
        $this->setMissingValues();
        $this->encryptdata['objid'] = ++$pon;
        $out = $this->encryptdata['objid'].' 0 obj'."\n"
            .'<<'."\n"
            .'/Filter /'.$this->encryptdata['Filter']."\n";
        if (!empty($this->encryptdata['SubFilter'])) {
            $out .= '/SubFilter /'.$this->encryptdata['SubFilter']."\n";
        }
        // V is a code specifying the algorithm to be used in encrypting and decrypting the document
        $out .= '/V '.$this->encryptdata['V']."\n";
        // The length of the encryption key, in bits. The value shall be a multiple of 8, in the range 40 to 256
        $out .= '/Length '.$this->encryptdata['Length']."\n";
        if ($this->encryptdata['V'] >= 4) {
            $out .= $this->getCryptFilter();
            // The name of the crypt filter that shall be used by default when decrypting streams.
            $out .= '/StmF /'.$this->encryptdata['StmF']."\n";
            // The name of the crypt filter that shall be used when decrypting all strings in the document.
            $out .= '/StrF /'.$this->encryptdata['StrF']."\n";
            /*
            if (!empty($this->encryptdata['EFF'])) {
                // The name of the crypt filter that shall be used when encrypting embedded file streams
                // that do not have their own crypt filter specifier.
                $out .= ' /EFF /'.$this->encryptdata['EFF'];
            }
            */
        }
        $out .= $this->getAdditionalEncDic()
            .'>>'."\n"
            .'endobj'."\n";
        return $out;
    }

    /**
     * Get Crypt Filter section
     *
     * A dictionary whose keys shall be crypt filter names
     * and whose values shall be the corresponding crypt filter dictionaries.
     *
     * @return string
     */
    protected function getCryptFilter()
    {
        $out = '/CF <<'."\n"
            .'/'.$this->encryptdata['StmF'].' <<'."\n"
            .'/Type /CryptFilter'."\n"
            .'/CFM /'.$this->encryptdata['CF']['CFM']."\n";  // The method used
        if ($this->encryptdata['pubkey']) {
            $out .= '/Recipients [';
            foreach ($this->encryptdata['Recipients'] as $rec) {
                $out .= ' <'.$rec.'>';
            }
            $out .= ' ]'."\n"
                .'/EncryptMetadata '.$this->getBooleanString($this->encryptdata['CF']['EncryptMetadata'])."\n";
        }
        // The event to be used to trigger the authorization
        // that is required to access encryption keys used by this filter.
        $out .= '/AuthEvent /'.$this->encryptdata['CF']['AuthEvent']."\n";
        if (!empty($this->encryptdata['CF']['Length'])) {
            // The bit length of the encryption key.
            $out .= '/Length '.$this->encryptdata['CF']['Length']."\n";
        }
        $out .= '>>'."\n"
            .'>>'."\n";
        return $out;
    }

    /**
     * get additional encryption dictionary entries for the standard security handler
     *
     * @return string
     */
    protected function getAdditionalEncDic()
    {
        $out = '';
        if ($this->encryptdata['pubkey']) {
            if (($this->encryptdata['V'] < 4) && !empty($this->encryptdata['Recipients'])) {
                $out .= ' /Recipients [';
                foreach ($this->encryptdata['Recipients'] as $rec) {
                    $out .= ' <'.$rec.'>';
                }
                $out .= ' ]'."\n";
            }
        } else {
            $out .= '/R ';
            if ($this->encryptdata['V'] == 5) { // AES-256
                $out .= '5'."\n"
                    .'/OE ('.$this->escapeString($this->encryptdata['OE']).')'."\n"
                    .'/UE ('.$this->escapeString($this->encryptdata['UE']).')'."\n"
                    .'/Perms ('.$this->escapeString($this->encryptdata['perms']).')'."\n";
            } elseif ($this->encryptdata['V'] == 4) { // AES-128
                $out .= '4'."\n";
            } elseif ($this->encryptdata['V'] < 2) { // RC-40
                $out .= '2'."\n";
            } else { // RC-128
                $out .= '3'."\n";
            }
            $out .= '/O ('.$this->escapeString($this->encryptdata['O']).')'."\n"
                .'/U ('.$this->escapeString($this->encryptdata['U']).')'."\n"
                .'/P '.$this->encryptdata['P']."\n"
                .'/EncryptMetadata '.$this->getBooleanString($this->encryptdata['EncryptMetadata'])."\n";
        }
        return $out;
    }

    /**
     * Return a string representation of a boolean value
     *
     * @param bool $value Value to convert
     *
     * @return string
     */
    protected function getBooleanString($value)
    {
        return ($value ? 'true' : 'false');
    }

    /**
     * Set missing values
     */
    protected function setMissingValues()
    {
        if (!isset($this->encryptdata['EncryptMetadata'])) {
            $this->encryptdata['EncryptMetadata'] = true;
        }
        if (!empty($this->encryptdata['CF'])) {
            if (!isset($this->encryptdata['CF']['EncryptMetadata'])) {
                $this->encryptdata['CF']['EncryptMetadata'] = true;
            }
        }
    }
}
