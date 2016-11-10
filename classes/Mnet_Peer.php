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

/**
 * An object to represent lots of information about an RPC-peer machine
 * This is a special implementation override for vmoodle MNET admin operations
 *
 * @author  Valery fremaux valery.fremaux@gmail.com
 * @version 0.0.1
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mnet
 * @version Moodle 2.2
 */

namespace local_vmoodle;
Use \StdClass;

require_once($CFG->libdir.'/filelib.php'); // download_file_content() used here

class Mnet_Peer {

    var $id                 = 0;
    var $wwwroot            = '';
    var $ip_address         = '';
    var $name               = '';
    var $public_key         = '';
    var $public_key_expires = 0;
    var $deleted             = 0;
    var $last_connect_time  = 0;
    var $last_log_id        = 0;
    var $force_theme        = 0;
    var $theme              = '';
    var $applicationid      = 1; // Default of 1 == Moodle
    var $keypair            = array();
    var $error              = array();
    var $bootstrapped       = false; // set when the object is populated

    function __construct() {
        $this->updateparams = new StdClass();
        return true;
    }

    /*
     * Fetch information about a peer identified by wwwroot
     * If information does not preexist in db, collect it together based on
     * supplied information
     *
     * @param string $wwwroot - address of peer whose details we want
     * @param string $pubkey - to use if we add a record to db for new peer
     * @param int $application - table id - what kind of peer are we talking to
     * @return bool - indication of success or failure
     */
    function bootstrap($wwwroot, $pubkey = null, $application, $force = false, $localname = '') {
        global $DB;
        
        if (substr($wwwroot, -1, 1) == '/') {
            $wwwroot = substr($wwwroot, 0, -1);
        }

        if ( ! $this->set_wwwroot($wwwroot) ) {
            $hostname = mnet_get_hostname_from_uri($wwwroot);

            // Get the IP address for that host - if this fails, it will
            // return the hostname string
            $ip_address = gethostbyname($hostname);

            // Couldn't find the IP address?
            if ($ip_address === $hostname && !preg_match('/^\d+\.\d+\.\d+.\d+$/',$hostname)) {
                $this->errors[] = 'ErrCode 2 - '.get_string("noaddressforhost", 'mnet');
                return false;
            }

            if (empty($localname)) {
                $this->name = stripslashes($wwwroot);
                $this->updateparams->name = $wwwroot;
            } else {
                $this->name = $localname;
                $this->updateparams->name = $localname;
            }

            // TODO: In reality, this will be prohibitively slow... need another
            // default - maybe blank string
            $homepage = file_get_contents($wwwroot);
            if (!empty($homepage)) {
                $count = preg_match("@<title>(.*)</title>@siU", $homepage, $matches);
                if ($count > 0) {
                    $this->name = $matches[1];
                    $this->updateparams->name = str_replace("'", "''", $matches[1]);
                }
            } else {
                // debug_trace("Missing remote real name guessing, no other side response");
            }
            // debug_trace("final name : ".$this->name);

            $this->wwwroot = stripslashes($wwwroot);
            $this->updateparams->wwwroot = $wwwroot;
            $this->ip_address = $ip_address;
            $this->updateparams->ip_address = $ip_address;
            $this->deleted = 0;
            $this->updateparams->deleted = 0;

            $this->application = $DB->get_record('mnet_application', array('name' => $application));
            if (empty($this->application)) {
                $this->application = $DB->get_record('mnet_application', array('name' => 'moodle'));
            }

            $this->applicationid = $this->application->id;
            $this->updateparams->applicationid = $this->application->id;

            // start bootstraping as usual through the system command
            $pubkeytemp = clean_param(mnet_get_public_key($this->wwwroot, $this->application), PARAM_PEM);
            if (empty($pubkey)) {
                // This is the key difference : force the exchange using vmoodle RPC keyswap !!
                if (empty($pubkeytemp)) {
                    $pubkeytemp = clean_param(mnet_get_public_key($this->wwwroot, $this->application, $force), PARAM_PEM);
                }
            } else {
                $pubkeytemp = clean_param($pubkey, PARAM_PEM);
            }
            $this->public_key_expires = $this->check_common_name($pubkeytemp);

            if ($this->public_key_expires == false) {
                return false;
            }
            $this->updateparams->public_key_expires = $this->public_key_expires;

            $this->updateparams->public_key = $pubkeytemp;
            $this->public_key = $pubkeytemp;

            $this->last_connect_time = 0;
            $this->updateparams->last_connect_time = 0;
            $this->last_log_id = 0;
            $this->updateparams->last_log_id = 0;
        }

        return true;
    }

    /*
     * Delete mnet peer
     * the peer is marked as deleted in the database
     * we delete current sessions.
     * @return bool - success
     */
    function delete() {
        global $DB;

        if ($this->deleted) {
            return true;
        }

        $this->delete_all_sessions();

        $this->deleted = 1;
        return $this->commit();
    }

    function count_live_sessions() {
        global $DB;
        $obj = $this->delete_expired_sessions();
        return $DB->count_records('mnet_session', array('mnethostid' => $this->id));
    }

    function delete_expired_sessions() {
        global $DB;

        $now = time();
        return $DB->delete_records_select('mnet_session', " mnethostid = ? AND expires < ? ", array($this->id, $now));
    }

    function delete_all_sessions() {
        global $CFG, $DB;
        // TODO: Expires each PHP session individually
        $sessions = $DB->get_records('mnet_session', array('mnethostid' => $this->id));

        if (count($sessions) > 0 && file_exists($CFG->dirroot.'/auth/mnet/auth.php')) {
            require_once($CFG->dirroot.'/auth/mnet/auth.php');
            $auth = new \auth_plugin_mnet();
            $auth->end_local_sessions($sessions);
        }

        $deletereturn = $DB->delete_records('mnet_session', array('mnethostid' => $this->id));
        return true;
    }

    function check_common_name($key) {
        $credentials = $this->check_credentials($key);
        return $credentials['validTo_time_t'];
    }

    function check_credentials($key) {
        $credentials = openssl_x509_parse($key);
        if ($credentials == false) {
            $this->error[] = array('code' => 3, 'text' => get_string("nonmatchingcert", 'mnet', array('subject' => '','host' => '')));
            return false;
        } elseif (array_key_exists('subjectAltName', $credentials['subject']) && $credentials['subject']['subjectAltName'] != $this->wwwroot) {
            $a['subject'] = $credentials['subject']['subjectAltName'];
            $a['host'] = $this->wwwroot;
            $this->error[] = array('code' => 5, 'text' => get_string("nonmatchingcert", 'mnet', $a));
            return false;
        // PATCH : Accept partial certificates
        // } elseif ($credentials['subject']['CN'] != $this->wwwroot) {
        // this change accept certificates that having only the common first chars
        } elseif ($credentials['subject']['CN'] != substr($this->wwwroot, 0, 64)) {
        // /PATCH
            $a['subject'] = $credentials['subject']['CN'];
            $a['host'] = $this->wwwroot;
            $this->error[] = array('code' => 4, 'text' => get_string('nonmatchingcert', 'mnet', $a));
            return false;
        } else {
            if (array_key_exists('subjectAltName', $credentials['subject'])) {
                $credentials['wwwroot'] = $credentials['subject']['subjectAltName'];
            } else {
                $credentials['wwwroot'] = $credentials['subject']['CN'];
            }
            return $credentials;
        }
    }

    function commit() {
        global $DB;
        $obj = new StdClass();

        $obj->wwwroot               = $this->wwwroot;
        $obj->ip_address            = $this->ip_address;
        $obj->name                  = $this->name;
        $obj->public_key            = $this->public_key;
        $obj->public_key_expires    = $this->public_key_expires;
        $obj->deleted               = $this->deleted;
        $obj->last_connect_time     = $this->last_connect_time;
        $obj->last_log_id           = $this->last_log_id;
        $obj->force_theme           = $this->force_theme;
        $obj->theme                 = $this->theme;
        $obj->applicationid         = $this->applicationid;

        if (isset($this->id) && $this->id > 0) {
            $obj->id = $this->id;
            return $DB->update_record('mnet_host', $obj);
        } else {
            $this->id = $DB->insert_record('mnet_host', $obj);
            return $this->id > 0;
        }
    }

    function touch() {
        $this->last_connect_time = time();
        $this->commit();
    }

    function set_name($newname) {
        if (is_string($newname) && strlen($newname <= 120)) {
            $this->name = $newname;
            return true;
        }
        return false;
    }

    function set_applicationid($applicationid) {
        if (is_numeric($applicationid) && $applicationid == intval($applicationid)) {
            $this->applicationid = $applicationid;
            return true;
        }
        return false;
    }

    /**
     * Load information from db about an mnet peer into this object's properties
     *
     * @param string $wwwroot - address of peer whose details we want to load
     * @return bool - indication of success or failure
     */
    function set_wwwroot($wwwroot) {
        global $CFG, $DB;

        $hostinfo = $DB->get_record('mnet_host', array('wwwroot'=>$wwwroot));

        if ($hostinfo != false) {
            $this->populate($hostinfo);
            return true;
        }
        return false;
    }

    function set_id($id) {
        global $CFG, $DB;

        if (clean_param($id, PARAM_INT) != $id) {
            $this->errno[]  = 1;
            $this->errmsg[] = 'Your id ('.$id.') is not legal';
            return false;
        }

        $sql = "
                SELECT
                    h.*
                FROM
                    {mnet_host} h
                WHERE
                    h.id = ?";

        if ($hostinfo = $DB->get_record_sql($sql, array($id))) {
            $this->populate($hostinfo);
            return true;
        }
        return false;
    }

    /**
     * Several methods can be used to get an 'mnet_host' record. They all then
     * send it to this private method to populate this object's attributes.
     *
     * @param object $hostinfo   A database record from the mnet_host table
     * @return  void
     */
    function populate($hostinfo) {
        global $DB;

        $this->id                   = $hostinfo->id;
        $this->wwwroot              = $hostinfo->wwwroot;
        $this->ip_address           = $hostinfo->ip_address;
        $this->name                 = $hostinfo->name;
        $this->deleted              = $hostinfo->deleted;
        $this->public_key           = $hostinfo->public_key;
        $this->public_key_expires   = $hostinfo->public_key_expires;
        $this->last_connect_time    = $hostinfo->last_connect_time;
        $this->last_log_id          = $hostinfo->last_log_id;
        $this->force_theme          = $hostinfo->force_theme;
        $this->theme                = $hostinfo->theme;
        $this->applicationid        = $hostinfo->applicationid;
        $this->application = $DB->get_record('mnet_application', array('id'=>$this->applicationid));
        $this->bootstrapped = true;
        $this->visible = @$hostinfo->visible; // let it flexible if not using the host visibility hack
    }

    function get_public_key() {
        if (isset($this->public_key_ref)) return $this->public_key_ref;
        $this->public_key_ref = openssl_pkey_get_public($this->public_key);
        return $this->public_key_ref;
    }
}
