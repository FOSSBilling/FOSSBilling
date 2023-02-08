<?php
class Registrar_Exception extends Box_Exception
{
    /**
     * Creates a new translated exception, using the Box_Exception class.
     *
     * @param   string   error message
     * @param   array|null    translation variables
     * @param   int 	 The exception code.
     * @param 	bool 	 If the varibles in this should be considered protect, if so, disable stacktracing abilities.
     */
    public function __construct(string $message, array $variables = NULL, int $code = 0)
    {
        parent::__construct($message, $variables, $code, true);
    }
}
