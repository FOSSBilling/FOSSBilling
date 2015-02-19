<?php
/**
 * Akismet CURL Class
 *
 * ABSTRACT
 *
 * After looking over the current PHP Akismet API classes (http://askimet.com)
 * I couldn't help but notice they all used fopen() to talk with akismet. 
 * Which besides being slow is also disabled on some hosts that have 
 * "safe mode" on. This class solves that problem by using the fast CURL 
 * php extension (http://php.net/curl) available on most web hosts.
 *
 * EXAMPLE
 *
 * <?php
 * require(akismet.curl.class.php);
 *
 * $bad_comment = array(
 *     'comment_type'              => 'comment',
 *     'comment_author'            => 'viagra-test-123',
 *     'comment_author_email'      => 'test@example.com',
 *     'comment_author_url'        => 'http://www.example.com/',
 *     'comment_content'           => 'This is a test comment',
 *     'permalink'                 => 'http://yoursite.com/post.php?id=9999',
 * );
 * 
 * $akismet = new akismet('akismet_api_key');
 * 
 * //If there was no problem connecting to Akismet.
 * if(!$akismet->error) {
 *     
 *     //Check to see if the key is valid
 *     if($akismet->valid_key()) {
 *         print 'Akismet Key is valid!';
 *     }
 *     
 *     if($akismet->is_spam($bad_comment)) {
 *         print 'Comment Spam!';
 *     } else {
 *         print 'Not spam!';
 *     }
 * }
 * ?>
 *
 *
 * @package    akismet.curl.class.php
 * @version    1.0.0 <4/18/2008>
 * @author     David Pennington <@codexplorer.com>
 * @copyright  Copyright (c) 2008 CodeXplorer <http://www.codexplorer.com>
 * @license    http://www.gnu.org/licenses/gpl-3.0.html (GPL v3)
 *
 ********************************** 80 Columns *********************************
 */





// Used by the Akismet class to communicate with the Akismet service
class akismet {
    
    private $api_version = '1.1';
    private $connection_handle;
    private $urls = array();
    public $api_key;
    public $site_url;
    public $error = null;
    
    
    /**
     * Class Constructor function
     *
     * @access	Private
     * @param 	String	$api_key        The Akismet API Key from wordpress.com
     * @param 	String	$site_url       The (optional) site URL of this site
     * @return	void
    */
    function __construct($api_key=null, $site_url=null) {
        
        //Set the key to use
        if($api_key) {
            $this->api_key = $api_key;
        } else {
            $this->error = true;
            throw new Exception(__('Akismet API Key not set.'));
        }
        
        //If no site URL was given
        if(!$site_url) {
            //Set it to the current site
            $this->site_url = 'http://'. $_SERVER['SERVER_NAME'];
        } else {
            //Set the site url
            $this->site_url = $site_url;
        }
        
        //Set the REST API URL's that we will use
        $this->urls = array(
                    'verify' => 'rest.akismet.com/'. $this->api_version
                             . '/verify-key',
                    'check_spam' => $this->api_key. '.rest.akismet.com/'
                                 . $this->api_version. '/comment-check',
                    'submit_spam' => $this->api_key. '.rest.akismet.com/'
                                  . $this->api_version. '/submit-spam',
                    'submit_ham' => $this->api_key. '.rest.akismet.com/'
                                 . $this->api_version. '/submit-ham'
                    );
        
        //Now connect
        $this->connect();
        
    }
    
    
    /**
     * Initializes a new cURL session/handle
     *
     * @access	Public
     * @return	boolean
    */
    public function connect() {
       
        //If there is no connection
        if(!is_resource($this->connection_handle)) {
            //Try to create one
            if(!$this->connection_handle = curl_init()) {
                throw new Exception(__('Could not start new CURL instance'));
                $this->error = true;
                return;
            }
        }
        
        //Include header in result? (no)
        curl_setopt($this->connection_handle, CURLOPT_HEADER, 0);
        //Do a regular HTTP POST? (yes)
        curl_setopt($this->connection_handle, CURLOPT_POST, 1);
        //The maximum number of seconds to allow cURL to execute
        curl_setopt($this->connection_handle, CURLOPT_TIMEOUT, 6); 
        //Return the transfer as a string - instead of printing it
        curl_setopt($this->connection_handle, CURLOPT_RETURNTRANSFER, 1);
        //The "User-Agent" header to be used in a HTTP request
        curl_setopt($this->connection_handle, CURLOPT_USERAGENT, 
                    "CodeXplorer/1.0.0 | Askimet/1.0.0");
        //Don't use a cached version of the url
        curl_setopt($this->connection_handle, CURLOPT_FRESH_CONNECT, 1);
        
        return true;
        
    }
    
    
    /**
     * Close the current cURL session/handle
     *
     * @access	Public
     * @return	boolean
    */
    public function close() {
    
        //If there is no connection
        if(is_resource($this->connection_handle)) {
            //Try to close it
            if(!curl_close($this->connection_handle)) {
                $this->error = true;
                throw new Exception(__('Could not close the CURL instance'));
            }
        }
        return true;
        
    }
    
    
    /**
     * Send a request through the current cURL session
     *
     * @access	Private
     * @param 	String	$request        The data to be $_POST'ed
     * @param 	String	$url            The URL to send it too
     * @return	boolean|string
    */
    private function send_data($request=null, $url=null) {
    
        //Set the url to send data too
        curl_setopt($this->connection_handle, CURLOPT_URL, $url);
        //The data to post in the HTTP operation
        curl_setopt($this->connection_handle, CURLOPT_POSTFIELDS, $request);
        
        //Send Data and grab the result
        if(!$response = curl_exec($this->connection_handle)) {
            $this->error = true;
            throw new Exception(__('Could not send cURL request'));
            return;
        }
        
        return $response;
    }
    
    
    /**
     * Check if Akismet API key is valid
     *
     * @access	Public
     * @return	boolean
    */
    public function valid_key() {
        $string = $this->create_query_string(
            array('key' => $this->api_key, 'blog' => $this->site_url)
        );
        return ($this->send_data($string, $this->urls['verify']) == 'valid');
    }
    
    
    /**
     * Format the comment array in accordance to the Akismet API
     *
     * @access	Public
     * @param 	Array	$comment        An array containing comment information
     * @return	void
    */
    public function is_spam($comment) {
        //Add this site_url
        $comment['blog'] = $this->site_url;
        
        //Convert array to string
        $comment = $this->create_query_string($comment);
        //Add $_SERVER data to string
        $comment .= $this->create_server_string();
        
        //Send the request to Akismet!
        $response = $this->send_data($comment, $this->urls['check_spam']);
        return ($response == 'true');
        
    }
    
    
    /**
     * Submit a comment that Akismet missed as SPAM!
     *
     * @access	Public
     * @param 	Array	$comment        Comment information
     * @param 	Array	$server_data    Optional extra $_SERVER data
     * @return	void
    */
    public function submit_spam($comment, $server_data=null) {
        
        //Add this site_url
        $comment['blog'] = $this->site_url;
        
        //Convert array to string
        $comment = $this->create_query_string($comment);
        
        //Optionally add stored $_SERVER data about the spam
        if($server_data) {
            //Add stored $_SERVER data to the string.
            //Note: do NOT use current user's data as it might be an admin!
            $comment .= $this->create_query_string($server_data);
        }
        
        //Send the request to Akismet!
        $this->send_data($comment, $this->urls['submit_spam']);
        
    }
    
    
    /**
     * Let Akismet know that the comment was valid and NOT spam.
     *
     * @access	Public
     * @param 	Array	$comment        Comment information
     * @param 	Array	$server_data	Optional extra $_SERVER data
     * @return	void
    */
    public function submit_ham($comment, $server_data=null) {
        
        //Add this site_url
        $comment['blog'] = $this->site_url;
        
        //Convert array to string
        $comment = $this->create_query_string($comment);
        
        //Optionally add stored $_SERVER data about the spam
        if($server_data) {
            //Add stored $_SERVER data to the string.
            //Note: do NOT use current user's data as it might be an admin!
            $comment .= $this->create_query_string($server_data);
        }
        
        //Send the request to Akismet!
        $this->send_data($comment, $this->urls['submit_ham']);
        
    }
    
    
    /**
     * Build a query string containing the items in the given array
     *
     * @access	Public
     * @return	String
     */
    public function create_query_string($array=null) {
        $query_string = null;
        if(is_array($array)) {
            foreach($array as $key => $value) {
                $query_string .= $key. '='. urlencode($value). '&';
            }
        }
        return $query_string;
    }
    
    
    /**
     * Build a query string containing $_SERVER info to help Akismet fight spam!
     *
     * @access	Private
     * @return	String
     */
    private function create_server_string() {
        
        $array = array(
            'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'],
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
            'QUERY_STRING' => $_SERVER['QUERY_STRING'], 
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'], 
            'REMOTE_PORT' => $_SERVER['REMOTE_PORT'], 
            'HTTP_ACCEPT' => $_SERVER['HTTP_ACCEPT'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'], 
            'referrer' => $_SERVER['HTTP_REFERER'], 
            'user_ip' => ($_SERVER['REMOTE_ADDR'] != getenv('SERVER_ADDR')) ? 
                        $_SERVER['REMOTE_ADDR'] : getenv('HTTP_X_FORWARDED_FOR'),
            'blog' => $this->site_url);
        
        return $this->create_query_string($array);
    }
    
}

