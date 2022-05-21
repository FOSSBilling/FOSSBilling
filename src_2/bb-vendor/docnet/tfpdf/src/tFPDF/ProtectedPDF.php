<?php

namespace tFPDF;

/**
 * Class ProtectedPDF
 * @package tFPDF
 */
class ProtectedPDF extends PDF
{

    const ENCRYPTION_PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";
    const PERMISSION_PRINT = 4;
    const PERMISSION_MODIFY = 8;
    const PERMISSION_COPY = 16;
    const PERMISSION_ANNOTATE_FORMS = 32;
    const PROTECTION_BASE = 192;
    const PASSWORD_MAX_LENGTH = 32;

    /**
     * Whether encryption is enabled
     *
     * @var bool
     */
    private $bol_encrypted = false;

    /**
     * @var int
     */
    private $int_encryption_object_id;

    /**
     * The generated encryption key for the document
     *
     * @var string
     */
    private $str_encryption_key;

    /**
     * @var string
     */
    private $str_u_value;

    /**
     * @var string
     */
    private $str_o_value;

    /**
     * @var string
     */
    private $int_p_value;

    /** {@inheritdoc} */
    protected function TextString($s)
    {
        if ($this->bol_encrypted) {
            // Encrypt text
            $s = self::RC4($this->ObjectKey($this->int_current_object), $s);
        }

        return parent::TextString($s);
    }

    /** {@inheritdoc} */
    protected function PutStream($str_data)
    {
        if ($this->bol_encrypted) {
            // Encrypt content
            $str_data = self::RC4($this->ObjectKey($this->int_current_object), $str_data);
        }

        parent::PutStream($str_data);
    }

    /** {@inheritdoc} */
    public function PutResources()
    {
        parent::PutResources();

        if ($this->bol_encrypted) {
            $this->NewObject();
            $this->int_encryption_object_id = $this->int_current_object;
            $this->Out('<<');
            $this->PutEncryption();
            $this->Out('>>');
            $this->Out('endobj');
        }
    }

    /** {@inheritdoc} */
    public function PutTrailer()
    {
        parent::PutTrailer();

        if ($this->bol_encrypted) {
            $this->Out('/Encrypt ' . $this->int_encryption_object_id . ' 0 R');
            $this->Out('/ID [()()]');
        }
    }

    /**
     * Write encryption metadata to the document
     */
    public function PutEncryption()
    {
        $this->Out('/Filter /Standard');
        $this->Out('/V 1');
        $this->Out('/R 2');

        $this->Out('/O ('.$this->EscapeString($this->str_o_value).')');
        $this->Out('/U ('.$this->EscapeString($this->str_u_value).')');
        $this->Out('/P '.$this->int_p_value);
    }

    /**
     * @param array|null $permissions     An array of ::PERMISSION_*
     * @param string $user_password       The password for restricted access to the document (obeying $permissions)
     * @param string|null $owner_password The password for unrestricted access to the document (ignoring $permissions)
     */
    public function SetProtection(array $permissions = null, $user_password = '', $owner_password = null)
    {
        $protection = self::PROTECTION_BASE;
        foreach((array)$permissions as $permission){
            // Test for duplicate permissions
            if (($protection & $permission) === 0) {
                $protection += $permission;
            }
        }

        if (strlen($user_password) > self::PASSWORD_MAX_LENGTH || strlen((string)$owner_password) > self::PASSWORD_MAX_LENGTH) {
            throw new \InvalidArgumentException('Passwords must be no greater than '.self::PASSWORD_MAX_LENGTH.' chars');
        }

        if ($owner_password === null) {
            // Generate random ownerpassword
            $owner_password = substr(uniqid(mt_rand(), true).uniqid(mt_rand(), true), 0, self::PASSWORD_MAX_LENGTH);
        }

        $this->InitializeEncryption($user_password, $owner_password, $protection);

        $this->bol_encrypted = true;
    }

    /**
     * Compute encryption key
     */
    private function InitializeEncryption($user_password, $owner_password, $protection)
    {
        // Pad passwords
        $user_password  = self::FormatEncryptionPassword($user_password);
        $owner_password = self::FormatEncryptionPassword($owner_password);

        // Compute values
        $this->str_o_value        = $this->GenerateOvalue($user_password, $owner_password);
        $this->str_encryption_key = $this->GenerateEncryptionKey($user_password, $this->str_o_value, $protection);
        $this->str_u_value        = $this->GenerateUvalue($this->str_encryption_key);
        $this->int_p_value        = $this->GeneratePvalue($protection);
    }

    /**
     * @param string $object_index
     * @return string The key for the specified object
     */
    private function ObjectKey($object_index)
    {
        return substr($this->MD5to16($this->str_encryption_key.pack('VXxx', $object_index)), 0, 10);
    }

    /**
     * Get MD5 as binary string
     */
    private static function MD5to16($string)
    {
        return pack('H*', md5($string));
    }

    /**
     * @param string $user_password
     * @param string $owner_password
     *
     * @return string
     */
    private static function GenerateOvalue($user_password, $owner_password)
    {
        $tmp           = self::MD5to16($owner_password);
        $owner_rc4_key = substr($tmp, 0, 5);
        return self::RC4($owner_rc4_key, $user_password);
    }

    /**
     * @param string $str_encryption_key
     * @return string
     */
    private static function GenerateUvalue($str_encryption_key)
    {
        return self::RC4($str_encryption_key, self::ENCRYPTION_PADDING);
    }

    /**
     * @param int $protection
     * @return int
     */
    private static function GeneratePvalue($protection)
    {
        return -(($protection ^ 255) + 1);
    }

    /**
     * @param string $user_password The padded user password
     * @param string $str_o_value
     * @param int $protection       The protection flags integer value
     *
     * @return string
     */
    private static function GenerateEncryptionKey($user_password, $str_o_value, $protection)
    {
        $tmp = self::MD5to16($user_password.$str_o_value.chr($protection)."\xFF\xFF\xFF");
        return substr($tmp, 0, 5);
    }

    /**
     * @param string $password The raw password
     * @return string The password suitable for generating encryption values, padded with data if necessary
     */
    private static function FormatEncryptionPassword($password)
    {
        return substr($password.self::ENCRYPTION_PADDING, 0, self::PASSWORD_MAX_LENGTH);
    }

    /**
     * @param string $key The encryption key
     * @param string $data Data to encrypt
     * @return string The encrypted data
     */
    private static function RC4($key, $data)
    {
        static $last_key;
        static $last_state;

        if ($key === $last_key) {
            // Same key - use same state
            $state = $last_state;
        } else {
            // Calculate new state
            $k     = str_repeat($key, 256 / strlen($key) + 1);
            $state = range(0, 255);
            $j     = 0;
            for ($i = 0; $i < 256; $i++) {
                $t         = $state[$i];
                $j         = ($j + $t + ord($k[$i])) % 256;
                $state[$i] = $state[$j];
                $state[$j] = $t;
            }
            $last_key   = $key;
            $last_state = $state;
        }

        $len = strlen($data);
        $a   = 0;
        $b   = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $a         = ($a + 1) % 256;
            $t         = $state[$a];
            $b         = ($b + $t) % 256;
            $state[$a] = $state[$b];
            $state[$b] = $t;
            $k         = $state[($state[$a] + $state[$b]) % 256];
            $out       .= chr(ord($data[$i]) ^ $k);
        }

        return $out;
    }

}
