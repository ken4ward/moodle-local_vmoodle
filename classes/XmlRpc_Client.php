<?php

namespace local_vmoodle;

require_once($CFG->dirroot.'/mnet/peer.php');
require_once($CFG->dirroot.'/mnet/xmlrpc/client.php');

/**
 * Improvement of default MNET XML_RPC client.
 * 
 * Improvements :
 * - Support multiple send.
 * - Support local calls.
 * - Error localisation.
 * - Auto add current user.
 * 
 * @package local_vmoodle
 * @category local
 * @author Bruce Bujon (bruce.bujon@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class XmlRpc_Client extends \mnet_xmlrpc_client {

    /**
     * Errors by host
     */
    private $host_errors = array();

    /**
     * Create a client and set the user.
     * @param $user array The calling user (optional / current platform user by default).
     */
    public function __construct($user = null) {
        global $CFG, $USER, $DB;

        // Calling parent constructor
        parent::__construct();

        // Checking user
        if (is_null($user)) {
            // Creating current user
            if (!($user_mnet_host = $DB->get_record('mnet_host', array('id' => $USER->mnethostid)))) {
                throw new \local_vmoodle\commands\Command_Exception('unknownuserhost');
            }
            $user = array(
                        'username' => $USER->username,
                        'remoteuserhostroot' => $user_mnet_host->wwwroot,
                        'remotehostroot' => $CFG->wwwroot
                    );
            $this->add_param($user, 'array');
        } elseif (array_key_exists('username', $user) && array_key_exists('remoteuserhostroot', $user) && array_key_exists('remotehostroot', $user)) {
            $this->add_param($user, 'array');
        } else {
            throw new \local_vmoodle\commands\Command_Exception('badclientuser');
        }
    }

    /**
     * Set method to call.
     * @param xmlrpcpath string The method to call.
     */
    public function set_method($xmlrpcpath) {
        // Save parameters
        $temp = $this->params;
        // Set methods
        if (parent::set_method($xmlrpcpath)) {
            // Restore parameters
            $this->params = $temp;
        }
    }

    /**
     * Empties all param stack.
     * @param xmlrpcpath string The method to call.
     */
    public function reset_method() {
        $this->params = array();
    }

    /**
     * Get host errors.
     * @param                    mnet_peer            An host to get errors (optional).
     * @return                    array                The host error.
     */
    public function getErrors($host = null) {
        if (is_null($host)) {
            return $this->host_errors;
        } elseif (array_key_exists($host->wwwroot, $this->host_errors)) {
            return $this->host_errors[$host->wwwroot];
        } else {
            return null;
        }
    }

    /**
     * Send the request to the server or execute function if is a local call.
     * @param $host mnet_peer A mnet_peer object with details of the remote host we're connecting to.
     * @return boolean True if the request is successfull, False otherwise.
     */
    public function send($host) {
        global $CFG;
        // Defining result
        $return = false;
        $this->error = array();

        // Checking if is a local call
        if ($host->wwwroot == $CFG->wwwroot) {

            // Getting method
            $uri = explode('/', $this->method);
            $method = array_pop($uri);
            $file = implode('/', $uri);
            // Adding librairie
            if (!include_once($CFG->dirroot.'/'.$file)) {
                $this->error[] = 'No such file.';
            }
            // Checking local function existance
            else if (!function_exists($method)) {
                $this->error[] = 'No such function.';
            }
            // Making a local call
            else {
                $this->response = call_user_func_array($method, $this->params);
                $result = true;
            }
        } else {
            // Make the default remote call
            $result = parent::send($host);
        }
        
        // Capturing host errors
        $this->host_errors[$host->wwwroot] = $this->error;
        // Reseting errors for next send
        $this->error = array();

        // Returning result
        return $result;
    }
}