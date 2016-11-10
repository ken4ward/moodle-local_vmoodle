<?php

namespace vmoodleadminset_roles;
Use \local_vmoodle\commands\Command;
Use \local_vmoodle\commands\Command_Parameter;
Use \local_vmoodle\commands\Command_Exception;
Use \StdClass;
Use \moodle_url;

require_once($CFG->libdir.'/accesslib.php');

/**
 * Describes a role comparison command.
 * 
 * @package local_vmoodle
 * @category local
 * @author Bruce Bujon (bruce.bujon@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
class Command_Role_Allow_Compare extends Command {

    /**
     * The role capabilities
     */
    private $capabilities = array();

    /**
     * The html report
     */
    private $report;

    /**
     * Constructor.
     * @throws Command_Exception.
     */
    public function __construct() {
        global $DB;

        // Getting command description
        $cmd_name = vmoodle_get_string('cmdallowcomparename', 'vmoodleadminset_roles');
        $cmd_desc = vmoodle_get_string('cmdallowcomparedesc', 'vmoodleadminset_roles');

        // Creating table parameter
        $tables['assign'] = vmoodle_get_string('assigntable', 'vmoodleadminset_roles');
        $tables['override'] = vmoodle_get_string('overridetable', 'vmoodleadminset_roles');
        $tables['switch'] = vmoodle_get_string('switchtable', 'vmoodleadminset_roles');
        $table_param = new Command_Parameter('table', 'enum', vmoodle_get_string('tableparamdesc', 'vmoodleadminset_roles'), null, $tables);

        // Creating command.
        parent :: __construct($cmd_name, $cmd_desc, $table_param);
    }

    /**
     * Execute the command.
     * @param mixed $hosts The host where run the command (may be wwwroot or an array).
     * @throws Command_Exception.
     */
    public function run($hosts) {
        global $CFG, $USER;

        // Adding constants.
        require_once $CFG->dirroot.'/local/vmoodle/rpclib.php';

        // Checking capabilities.
        if (!has_capability('local/vmoodle:execute', \context_system::instance()))
            throw new Command_Exception('insuffisantcapabilities');

        // Getting role
        $table = $this->getParameter('table')->getValue();

        // Creating XMLRPC client to read role configuration
        $rpc_client = new \local_vmoodle\XmlRpc_Client();
        $rpc_client->set_method('local/vmoodle/plugins/roles/rpclib.php/mnetadmin_rpc_get_role_allow_table');
        $rpc_client->add_param($table, 'string');
        $rpc_client->add_param('', 'string'); // get for all roles

        // Initializing responses
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

        // Sending requests.
        foreach ($mnet_hosts as $mnet_host) {
            // Sending request.
            if (!$rpc_client->send($mnet_host)) {
                $response = new \StdClass();
                $response->status = MNET_FAILURE;
                $response->errors[] = implode('<br/>', $rpc_client->getErrors($mnet_host));
                if (debugging()) {
                    echo '<pre>';
                    var_dump($rpc_client);
                    echo '</pre>';
                }
            } else {
                $response = json_decode($rpc_client->response);
            }
            // Recording response.
            $responses[$mnet_host->wwwroot] = $response;
            // Recording capabilities.
            if ($response->status == RPC_SUCCESS)
                $this->capabilities[$mnet_host->wwwroot] = $response->value;
        }
        // Saving results.
        $this->results = $responses + $this->results;

        // Processing results.
        $this->_process();
    }

    /**
     * Get the result of command execution for one host.
     * @param string $host The host to retrieve result (optional, if null, returns general result).
     * @param string $key The information to retrieve (ie status, error / optional).
     * @return mixed The result or null if result does not exist.
     * @throws Command_Exception.
     */
    public function getResult($host = null, $key = null) {
        // Checking if command has been runned.
        if (!$this->isRunned())
            throw new Command_Exception('commandnotrun');

        // Checking host (general result isn't provide in this kind of command).
        if (is_null($host)) {
            return $this->report;
        } else {
            if (!array_key_exists($host, $this->results)) {
                return null;
            }
        }
        $result = $this->results[$host];

        // Checking key.
        if (is_null($key)) {
            return $result;
        } else {
            if (property_exists($result, $key)) {
                return $result-> $key;
            } else {
                return null;
            }
        }
    }

    /**
     * Process the role comparision.
     * @throws Commmand_Exception.
     */
    private function _process() {
        global $CFG,$DB,$OUTPUT;

        // Checking if command has been runned.
        if (!$this->isRunned()) {
            throw new Command_Exception('commandnotrun');
        }

        // Getting table name.
        $table = $this->getParameter('table')->getValue();
          
        // Getting hosts.
        $hosts = array_keys($this->capabilities);
        $host_labels = get_available_platforms();

        // Getting local roles.
        $roles = $DB->get_records('role', null, '', 'sortorder');

        /*
         * processing results
         */

        // Creating header.
        $this->report = '<h3>'.get_string('allowcompare', 'vmoodleadminset_roles', vmoodle_get_string($table.'table', 'vmoodleadminset_roles')).help_button_vml('rolelib', 'allowcompare', 'vmoodleadminset_roles').'</h3>';
        // Adding edit role link
        $this->report.= '<center><p>'.$OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/admin/roles/allow.php?mode='.$table, array('roleid' => $role->id, 'action' => 'edit')), vmoodle_get_string('editallowtable', 'vmoodleadminset_roles'), 'get').'</p></center>';
        // Creation form.
        $this->report .= '<form action="'.$CFG->wwwroot.'/local/vmoodle/plugins/roles/controller.rolelib.sadmin.php?what=syncallow" method="post" onsubmit="return validate_syncrole()">';
        $this->report .= '<input id="target" type="hidden" name="target" value=""/>';
        $this->report .= '<input id="role" type="hidden" name="role" value=""/>';
        $this->report .= '<input id="source_platform" type="hidden" name="source_platform" value=""/>';

        // Creating table.
        $this->report.= '<table id="allowcompare" cellspacing="1" cellpadding="5" class="generaltable boxaligncenter" style="min-width: 75%;"><tbody>';

        // Creating header.
        $this->report.= '<tr><th scope="col" class="header c0" style="vertical-align: bottom; text-align: left;">&nbsp</th>';
        $col = 1;
        foreach ($hosts as $host) {
            $this->report.= '<th id="cap_'.$col.'" scope="col" class="header c'.$col.'" style="vertical-align: bottom; text-align: center;"><label for="platform_'.$col.'"><img src="'.$CFG->wwwroot.'/local/vmoodle/plugins/roles/draw_platformname.php?caption='.urlencode($host_labels[$host]).'" alt="'.$host_labels[$host].'"/></label><br/><input id="platform_'.$col.'" type="checkbox" name="platforms[]" value="'.$host.'" disabled="disabled"/></th>';
            $col++;
        }
        $this->report.= '</tr>';

        // Initializing variables.
        $row = 0;
        // Creating table data.
        foreach ($allroles as $rolename => $role) {
            $localrole = $DB->get_field('role', 'name', array('shortname' => $rolename));
            $displayrole = ($localrole) ? $localrole : '--'.$rolename.'--';
            $this->report .= "<tr valign='top'>$displayrole</td>";
            $row++;
        }

        // Closing table.
        $this->report.= '</tboby></table><br/>';
        $this->report .= '<center><input type="submit" value="'.vmoodle_get_string('synchronize', 'vmoodleadminset_roles').'"/><div id="allowcompare_validation_message"></div></center></form><br/><br/>';
    }
}