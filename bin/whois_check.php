<?php
    //include php whois class

    class Box_Exception extends Exception
    {
        /**
         * Creates a new translated exception.
         *
         * @param   string   error message
         * @param   array    translation variables
         */
        public function __construct($message, array $variables = NULL, $code = 0)
        {
            // Pass the message to the parent
            parent::__construct($message, $code);
        }

        private function stackTrace($Length = 25) {

        }
    }

    include('../src/library/Registrar/AdapterAbstract.php');
    include('../src/library/Registrar/Adapter/Custom.php');

    $domain = 'randondomainthat-shouldnotexists123';

    foreach ( Whois::getServers() as $server ){

        $whois = new Whois($domain . '.' . $server[0]);
        echo $server[0].' '.$server[1]."\r\n";
        if(!$whois -> isAvailable()){
            echo $whois -> info();
            echo "Not working"."\r\n"."\r\n";
        }
    }


?>
