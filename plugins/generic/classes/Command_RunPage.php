<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace vmoodleadminset_generic;
Use \local_vmoodle\commands\Command;
Use \StdClass;

/**
 * Describes meta-administration plugin's command for running a remote authenticated page in hosts
 * This is for instance used for running mass fixture scripts.
 * 
 * @package local_vmoodle
 * @category local
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class Command_RunPage extends Command {

    /**
     * If command's result should be returned.
     */
    private $returned;

    /**
     * Constructor.
     * @param string $name Command's name.
     * @param string $description Command's description.
     * @param string $sql SQL command.
     * @param string $parameters Command's parameters (optional / could be null, Command_Parameter object or Command_Parameter array).
     * @param Command $rpcommand Retrieve platforms command (optional / could be null or Command object).
     * @throws Command_Exception
     */
    public function __construct($name, $description, $parameters = null, $rpcommand = null) {
        global $vmcommands_constants;

        // Creating Command.
        parent::__construct($name, $description, $parameters, $rpcommand);

        if (is_null($parameters) || !is_array($parameters)) {
            throw new Command_RunPage_Exception('arrayexpected');
        }

        foreach ($parameters as $param) {
            $pname = $param->getName();
            if (!in_array($pname, array('pageurl'))) {
                throw new Command_RunPage_Exception('unexpectedparam');
            }
        }
    }

    /**
     * Execute the command.
     * @param mixed $host The hosts where run the command (may be wwwroot or an array).
     * @throws Command_SetConfig_Exception
     */
    public function run($hosts) {
        global $CFG, $USER;

        // Adding constants.
        require_once $CFG->dirroot.'/local/vmoodle/rpclib.php';

        // Checking host.
        if (!is_array($hosts)) {
            $hosts = array($hosts => 'Unnamed host');
        }

        // Checking capabilities.
        if (!has_capability('local/vmoodle:execute', \context_system::instance())) {
            throw new Command_RunPage_Exception('insuffisantcapabilities');
        }

        $requiredurl = $this->getParameter('pageurl')->getValue();
        if (preg_match('/^https?:/', $requiredurl)) {
            throw new Command_RunPage_Exception('notlocalurl');
        }

        // Initializing responses.
        $responses = array();

        // Creating peers.
        $mnet_hosts = array();
        foreach ($hosts as $host => $name) {
            $mnet_host = new \mnet_peer();
            if ($mnet_host->bootstrap($host, null, 'moodle')) {
                $mnet_hosts[] = $mnet_host;
            } else {
                $responses[$host] = (object) array('status' => MNET_FAILURE, 'error' => get_string('couldnotcreateclient', 'local_vmoodle', $host));
            }
        }

        $auth = is_enabled_auth('multimnet') ? 'multimnet' : 'mnet';
        $uri = new moodle_url('/auth/'.$auth.'/jump.php', array('hostwwwroot' => $mnet_host->wwwroot, 'wantsurl' => $requiredurl));

        // Getting command.
        $command = $this->isReturned();

        // Sending requests.
        foreach($mnet_hosts as $mnet_host) {
            // Sending request.
            $ch = curl_init($uri);
            if (debugging()) {
                mtrace('Calling : '.$uri.'?'.$rq."<br/>\n");
            }

            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Moodle Remote Script Request');
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $raw = curl_exec($ch);
            $responses[$mnet_host->wwwroot] = $raw;
        }

        // Saving results in array.
        $this->results = $responses + $this->results;
    }

    /**
     * Get the result of command execution for one host.
     * @param string $host The host to retrieve result (optional, if null, returns general result).
     * @param string $key The information to retrieve (ie status, error / optional).
     * @throws Command_Sql_Exception
     */
    public function getResult($host = null, $key = null) {
        // Checking if command has been runned.
        if (is_null($this->results)) {
            throw new Command_Exception('commandnotrun');
        }

        // Checking host (general result isn't provide in this kind of command).
        if (is_null($host) || !array_key_exists($host, $this->results)) {
            return null;
        }
        $result = $this->results[$host];

        // Checking key.
        if (is_null($key)) {
            return $result;
        } elseif (property_exists($result, $key)) {
            return $result->$key;
        } else {
            return null;
        }
    }

    /**
     * Get if the command's result is returned.
     * @return bool True if the command's result should be returned, false otherwise.
     */
    public function isReturned() {
        return $this->returned;
    }

    /**
     * Set if the command's result is returned.
     * @param bool $returned True if the command's result should be returned, false otherwise.
     */
    public function setReturned($returned) {
        $this->returned = $returned;
    }
}