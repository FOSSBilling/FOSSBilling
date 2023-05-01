<?php
class FOSSBillingAutoloader
{
    private array $psr0 = [];
    private array $psr4 = [];

    private string $typePsr0 = 'psr0';
    private string $typePsr4 = 'psr4';

    public function register()
    {
        spl_autoload_register(array($this, 'autoload'));
    }

    /**
     * @param string $prefix Class prefix. Use an empty prefix to allow this prefix to work with any path. Paths must already have directory separators normalized for the current system.
     * @param string $path Base path associated with the class prefix
     * @param string $type The type of PSR autoloader the prefix is associated with. EX: psr4
     * @return void 
     */
    public function addPrefix(string $prefix, string $path, string $type): void
    {
        //The loader assumes the path does NOT end in a directory separator, so let's remove it now.
        if (str_ends_with($path, DIRECTORY_SEPARATOR)) {
            $path = substr($path, 0, -1);
        }

        switch ($type) {
            case $this->typePsr0:
                $this->psr0[$prefix] = $path;
                break;
            case $this->typePsr4:
                $this->psr4[$prefix] = $path;
                break;
            default:
                throw new \Exception("Unknown PSR autoloader type: {$type}");
        }
    }

    /**
     * @param string $class Classname, after having and specialized handling performed
     * @param string $path The path associated with the prefix
     * @param mixed $prefix The prefix matching the classname.
     * @return string The completed file path.
     */
    private function getFile(string $class, string $path, $prefix): string
    {
        //Remove the "prefix" so we get just the classname
        $classname = substr($class, strlen($prefix));

        //Now convert it to a path and ensure it has a directory separator at the start, since our paths won't.
        $classname = str_replace('\\', DIRECTORY_SEPARATOR, $classname) . '.php';
        if (!str_starts_with($classname, DIRECTORY_SEPARATOR)) {
            $classname = DIRECTORY_SEPARATOR . $classname;
        }

        return $path . $classname;
    }

    /**
     * @param string $class Classname to load. If found, file will be included and execution will be completed.
     * @return void 
     */
    public function autoload(string $class): void
    {
        /* PSR-0 Loader.
         * @see https://www.php-fig.org/psr/psr-0/
         */
        foreach ($this->psr0 as $prefix => $path) {
            if (empty($prefix) || strpos($class, $prefix) === 0) {
                $class = str_replace('_', DIRECTORY_SEPARATOR, $class);
                $file = $this->getFile($class, $path, $prefix);
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        }

        /* PSR-4 Loader.
         * @see https://www.php-fig.org/psr/psr-4/
         */
        foreach ($this->psr4 as $prefix => $path) {
            if (empty($prefix) || strpos($class, $prefix) === 0) {
                $file = $this->getFile($class, $path, $prefix);
                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        }
    }
}
