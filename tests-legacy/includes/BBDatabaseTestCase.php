<?php

use PHPUnit\Framework\TestCase;

abstract class BBDatabaseTestCase extends TestCase
{
    private static ?PDO $pdo = null;
    private $conn;

    protected $_seedFilesPath;
    protected $_initialSeedFile = 'initial.xml';

    final public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO('mysql:dbname=' . BB_DB_NAME . ';host=127.0.0.1;port=' . BB_DB_PORT, BB_DB_USER, BB_DB_PASSWORD);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, BB_DB_NAME);
        }

        return $this->conn;
    }

    /**
     * Returns the seed files folder path.
     *
     * @return string
     */
    public function getSeedFilesPath()
    {
        if ($this->_seedFilesPath == null) {
            $this->_seedFilesPath = PATH_TESTS . '/fixtures';
        }

        return rtrim((string) $this->_seedFilesPath, '/') . '/';
    }

    /**
     * Retrieve from flat XML files data used to populate the database.
     *
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    protected function getDataSet()
    {
        return $this->createFlatXmlDataSet($this->getSeedFilesPath() . $this->_initialSeedFile);
    }
}
