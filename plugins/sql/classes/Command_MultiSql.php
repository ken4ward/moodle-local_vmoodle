<?php

namespace vmoodleadminset_sql;
Use \local_vmoodle\commands\Command;
Use \local_vmoodle\commands\Command_Exception;
Use \StdClass;

/**
 * Describes meta-administration multiple SQL (script) command.
 * 
 * @package local_vmoodle
 * @category local
 * @author Valery Fremaux (valery.Fremaux@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class Command_MultiSql extends Command {

    /**
     * SQL command
     */
    private $sqls;

    /**
     * If command's result should be returned
     */
    private $returned;

    /**
     * if commands has place holders, they are converted into Moodle SQL named variables 
     */
    private $values;

    /**
     * Constructor.
     * @param string $name Command's name.
     * @param string $description Command's description.
     * @param string $sql SQL command.
     * @param mixed $parameters Command's parameters (optional / could be null, Command_Parameter object or Command_Parameter array).
     * @param Command $rpcommand Retrieve platforms command (optional / could be null or Command object).
     * @throws Command_Exception
     */
    public function __construct($name, $description, $sqls, $parameters = null, $rpcommand = null) {
        global $vmcommands_constants;

        // Creating Command.
        parent::__construct($name, $description, $parameters, $rpcommand);
        // Checking SQL command
        if (empty($sqls)) {
            throw new Command_Sql_Exception('sqlemtpycommand', $this->name);
        } else {
            // Looking for parameters
            preg_match_all(Command::placeholder, $sqls, $sql_vars);
            // Checking parameters to show
            foreach($sql_vars[2] as $key => $sql_var) {
                $is_param = !(empty($sql_vars[1][$key]));
                if (!$is_param && !array_key_exists($sql_var, $vmcommands_constants)) {
                    throw new Command_Sql_Exception('sqlconstantnotgiven', (object)array('constant_name' => $sql_var, 'command_name' => $this->name));
                } elseif ($is_param && !array_key_exists($sql_var, $this->parameters)) {
                    throw new Command_Sql_Exception('sqlparameternotgiven', (object)array('parameter_name' => $sql_var, 'command_name' => $this->name));
                }
            }
            $this->sqls = $sqls;
        }

        $this->values = array();
    }

    /**
     * Execute the command.
     * @param mixed $host The hosts where run the command (may be wwwroot or an array).
     * @throws Command_Sql_Exception
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
            throw new Command_Sql_Exception('insuffisantcapabilities');
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
                $responses[$host] = (object) array(
                    'status' => MNET_FAILURE,
                    'error' => get_string('couldnotcreateclient', 'local_vmoodle', $host)
                );
            }
        }

        // Getting command.
        // $command = $this->isReturned();

        // Creating XMLRPC client.
        $rpc_client = new \local_vmoodle\XmlRpc_Client();
        $rpc_client->set_method('local/vmoodle/plugins/sql/rpclib.php/mnetadmin_rpc_run_sql_command');
        $rpc_client->add_param($this->_getGeneratedCommand(), 'string');
        $rpc_client->add_param($this->values, 'array');
        $rpc_client->add_param(false, 'boolean');
        $rpc_client->add_param(true, 'boolean'); // telling other side we are a multiple command

        // Sending requests.
        foreach($mnet_hosts as $mnet_host) {
            // Sending request.
            if (!$rpc_client->send($mnet_host)) {
                $response = new StdClass();
                $response->status = MNET_FAILURE;
                $response->errors[] = implode('<br/>', $rpc_client->getErrors($mnet_host));
                if (debugging()) {
                    print_object($rpc_client);
                }
            } else {
                $response = json_decode($rpc_client->response);
            }

            // Recording response.
            $responses[$mnet_host->wwwroot] = $response;
        }

        // Saving results.
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
            throw new \local_vmoodle\commands\Command_Exception('commandnotrun');
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
     * Get SQL command.
     * @return SQL command.
     */
    public function getSql() {
        return $this->sqls;
    }

    /**
     * Get if the command's result is returned.
     * @return boolean True if the command's result should be returned, false otherwise.
     */
    public function isReturned() {
        return $this->returned;
    }

    /**
     * Set if the command's result is returned.
     * @param boolean $returned True if the command's result should be returned, false otherwise.
     */
    public function setReturned($returned) {
        $this->returned = $returned;
    }

    /**
     * Get the command to execute.
     * @return string The final SQL command to execute.
     */
    private function _getGeneratedCommand() {
        return preg_replace_callback(self::placeholder, array($this, '_replaceParametersValues'), $this->getSql());
    }

    /**
     * Bind the replace_parameters_values function to create a callback.
      * @param array $matches The placeholders found.
      * @return string|array The parameters' values.
     */
    private function _replaceParametersValues($matches) {

        list($paramname, $paramvalue) = replace_parameters_values($matches, $this->getParameters(), true, false);

        $this->values[$paramname] = $paramvalue;

        // Return the named placeholder.
        return ':'.$paramname;
    }
}