<?php
/**
 * Description of assisted commands for administrating configs.
 * 
 * @package local_vmoodle
 * @category local
 * @author Bruce Bujon (bruce.bujon@gmail.com)
 * @copyright valeisti (http://www.valeisti.fr)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

namespace vmoodleadminset_generic;
Use \local_vmoodle\commands\Command_Category;
Use \local_vmoodle\commands\Command_Parameter;
Use \vmoodleadminset_sql\Command_MultiSql;
Use \vmoodleadminset_sql\Command_Sql;

function vmoodle_config_get_plugins_params() {
    global $CFG, $DB;
    
    $paramslist = $DB->get_records_sql_menu("SELECT DISTINCT id,CONCAT(plugin,'/',name) FROM {config_plugins} ");
    $paramlist = array_combine(array_values($paramslist), array_values($paramslist));
    return $paramlist;
}

function vmoodle_config_get_params() {
    global $CFG, $DB;

    $paramslist = $DB->get_records_sql_menu("SELECT DISTINCT id,name FROM {config} ");
    $paramlist = array_combine(array_values($paramslist), array_values($paramslist));
    return $paramlist;
}


$category = new Command_Category('generic');

// Set on/off the maintenance mode
$param1 = new Command_Parameter(
    'source1',
    'boolean',
    'Maintenance mode',
    null,
    null
);

$param2 = new Command_Parameter(
    'source2',
    'ltext',
    'Maintenance message',
    null,
    null
);

$cmd = new Command_MultiSql(
    'Vmoodle Maintenance',
    'Setting on/off the maintenance mode',
    'UPDATE {config} SET value = [[?source1]] WHERE name = \'maintenance_enabled\' '.";\n".' UPDATE {config} SET value = [[?source2]] WHERE name = \'maintenance_message\'',
    array($param1,$param2)
);
$category->addCommand($cmd);

$cmd = new Command_PurgeCaches(
    'Vmoodle Purge Caches',
    'Purge remote caches'
);

$category->addCommand($cmd);


// Distribute a config value to all nodes (Using Generic SQL)
/*
$param1 = new Command_Parameter(
    'source1',
    'enum',
    'Config Key',
    null,
    vmoodle_config_get_params()
);

$param2 = new Command_Parameter(
    'source2',
    'text',
    'Config Value',
    null,
    null
);

$cmd = new Command_Sql(
    'Vmoodle Config Value',
    'Distributing a configuration value',
    'UPDATE {config} SET value = [[?source2]] WHERE name = [[?source1]] ',
    array($param1,$param2)
);
$category->addCommand($cmd);
*/

// Distribute a config value to all nodes (Using SetConfig)
$param1 = new Command_Parameter(
    'key',
    'enum',
    'Config Key',
    null,
    vmoodle_config_get_params()
);

$param2 = new Command_Parameter(
    'value',
    'text',
    'Config Value',
    null,
    null
);

$cmd = new Command_SetConfig(
    'Vmoodle Config Value',
    'Distributing a configuration value',
    array($param1,$param2)
);
$category->addCommand($cmd);


$param1 = new Command_Parameter(
    'key',
    'enum',
    'Config Key',
    null,
    vmoodle_config_get_plugins_params()
);

$param2 = new Command_Parameter(
    'value',
    'text',
    'Config Value',
    null,
    null
);

$cmd = new Command_SetPluginConfig(
    'Vmoodle Plugin Config Value',
    'Distributing a configuration value in Config Plugin',
    array($param1,$param2)
);
$category->addCommand($cmd);

return $category;