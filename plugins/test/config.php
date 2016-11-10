<?php

namespace vmoodleadminset_test;
Use \local_vmoodle\commands\Command;
Use \local_vmoodle\commands\Command_Category;
Use \local_vmoodle\commands\Command_Parameter;
Use \local_vmoodle\commands\Command_Parameter_Internal;
Use \local_vmoodle\commands\Command_Exception;
Use \vmoodleadminset_roles\Command_Role_Sync;
Use \vmoodleadminset_roles\Command_Role_Compare;
Use \vmoodleadminset_roles\Command_Role_Capability_Sync;
Use \vmoodleadminset_upgrade\Command_Upgrade;
Use \vmoodleadminset_sql\Command_Sql;
Use \vmoodleadminset_sql\Command_MultiSql;
Use \vmoodleadminset_test\CommandWrapper;
Use \Exception;

/**
 * Description of assisted commands for testing generic oommands.
 * 
 * @package local_vmoodle
 * @category local
 * @author Bruce Bujon (bruce.bujon@gmail.com)
 */

// Creating category
$category = new Command_Category('test');

// Adding commands
$cmd = new Command_Sql(
    'Command 1',
    'Command without parameter.',
    'SELECT aa FROM bb'
);
$category->addCommand($cmd);

/********************************/

$cmd = new Command_Sql(
    'Command 2',
    'Command with a boolean parameter.',
    'SELECT [[?parameter1]] FROM bb',
    new Command_Parameter(
        'parameter1',
        'boolean',
        'The boolean'
    )
);
$category->addCommand($cmd);

/********************************/

$cmd = new Command_Sql(
    'Command 3',
    'Command with a boolean parameter selected.',
    'SELECT [[?parameter1]] FROM bb',
    new Command_Parameter(
        'parameter1',
        'boolean',
        'The boolean selected by default',
        true
    )
);
$category->addCommand($cmd);

/********************************/

$cmd = new Command_Sql(
    'Command 4',
    'Command with a boolean parameter unselected.',
    'SELECT [[?parameter1]] FROM bb',
    new Command_Parameter(
        'parameter1',
        'boolean',
        'The boolean unselected by default',
        false
    )
);
$category->addCommand($cmd);

/********************************/

$param1 = new Command_Parameter(
    'parameter1',
    'enum',
    'The enum choice',
    null,
    array(
        'value1' => vmoodle_get_string('value1', 'vmoodleadminset_test'),
        'value2' => vmoodle_get_string('value2', 'vmoodleadminset_test'),
        'value3' => vmoodle_get_string('value3', 'vmoodleadminset_test'),
    )
);
$cmd = new Command_Sql(
    'Command 5',
    'Command with an enum choice without default value.',
    'SELECT [[?parameter1]] FROM bb',
    $param1
);
$category->addCommand($cmd);

/********************************/

$param1 = new Command_Parameter(
    'parameter1',
    'enum',
    'The enum choice values 2 by default',
    'value2',
    array(
        'value1' => vmoodle_get_string('value1', 'vmoodleadminset_test'),
        'value2' => vmoodle_get_string('value2', 'vmoodleadminset_test'),
        'value3' => vmoodle_get_string('value3', 'vmoodleadminset_test'),
    )
);
$cmd = new Command_Sql(
    'Command 6',
    'Command with an enum choice with default value.',
    'SELECT [[?parameter1]] FROM bb',
    $param1
);
$category->addCommand($cmd);

/*********************************/

$param1 = new Command_Parameter(
    'parameter1',
    'text',
    'The free text without default value'
);
$cmd = new Command_Sql(
    'Command 7',
    'Command with free text without default value.',
    'SELECT [[?parameter1]] FROM bb',
    $param1
);
$category->addCommand($cmd);

/************************************/

$param1 = new Command_Parameter(
    'parameter1',
    'text',
    'The free text with default value',
    'the default value'
);
$cmd = new Command_Sql(
    'Command 8',
    'Command with free text with default value.',
    'SELECT [[?parameter1]] FROM bb',
    $param1
);
$category->addCommand($cmd);

/**************************************/

$param1 = new Command_Parameter(
    'parameter1',
    'ltext',
    'The free long text without default value'
);
$cmd = new Command_Sql(
    'Command 9',
    'Command with free long text without default value.',
    'SELECT [[?parameter1]] FROM bb',
    $param1
);
$category->addCommand($cmd);

/*****************************************/

$param1 = new Command_Parameter(
    'parameter1',
    'ltext',
    'The free long text with default value',
    'default value'
);
$cmd = new Command_Sql(
    'Command 10',
    'Command with free long text with default value.',
    'SELECT [[?parameter1]] FROM bb',
    $param1
);
$category->addCommand($cmd);

/*****************************/

$param1 = new Command_Parameter(
    'parameter1',
    'boolean',
    'A boolean selected by default',
    true
);
$param2 = new Command_Parameter(
    'parameter2',
    'enum',
    'The enum choice values 2 by default',
    'value2',
    array(
        'value1' => vmoodle_get_string('value1', 'vmoodleadminset_test'),
        'value2' => vmoodle_get_string('value2', 'vmoodleadminset_test'),
        'value3' => vmoodle_get_string('value3', 'vmoodleadminset_test'),
    )
);
$param3 = new Command_Parameter(
    'parameter3',
    'text',
    'The free text with default value',
    'the default value'
);
$cmd = new Command_Sql(
    'Command 11',
    'Command which combine different fields.',
    'SELECT [[?parameter1]], [[?parameter2]], [[?parameter3]] FROM bb',
    array( $param1,
           $param2,
           $param3
    )
);
$category->addCommand($cmd);

/*********************************/

$param1 = new Command_Parameter(
    'parameter1',
    'boolean',
    'The boolean selected by default',
    true
);
$param2 = new Command_Parameter(
    'parameter2',
    'enum',
    'The enum choice values 2 by default',
    'value2',
    array(
        'value1' => vmoodle_get_string('value1', 'vmoodleadminset_test'),
        'value2' => vmoodle_get_string('value2', 'vmoodleadminset_test'),
        'value3' => vmoodle_get_string('value3', 'vmoodleadminset_test'),
    )
);
$param3 = new Command_Parameter_Internal(
    'parameter3',
    'explode',
    array('[[?parameter1]]', '[[?parameter2]]', '[[bibi]]', 'ba[[?parameter1:tot]]be[[prefix]]bi', true, 'aa')
);
$cmd = new Command_Sql(
    'Command 12',
    'Command with a boolean parameter selected and an internal parameter.',
    'SELECT [[?parameter1]],[[?parameter2]],[[?parameter3]] FROM bb',
    array(
        $param1,
        $param2,
        $param3
    )
);
$category->addCommand($cmd);

$cmd = new Command_Sql(
    'Command 13',
    'Command to  try error handling.',
    'SELECT [[?parameter1]] FROM bb',
    new Command_Parameter_Internal(
        'parameter1',
        'vmoodleadminset_test\\CommandWrapper::myTestFunction'
    )
);
$category->addCommand($cmd);

$test_rpcommad = new Command_Sql(
    'Command 14',
    'Command with a retrieve platforms command.',
    'UPDATE bb SET value = \'aa\' WHERE name = \'cc\'',
    null
);

$param1 = new Command_Parameter(
    'param',
    'text',
    'Name of config directive',
    'local_vmoodle_host_source'
);
$param2 = new Command_Parameter(
    'value',
    'text',
    'Value of config directive',
    'vmoodle'
);
$cmd = new Command_Sql(
    'Retrieve platforms command',
    'Command used to retrieve platforms from their original value.',
    'SELECT id FROM {config} WHERE name = [[?param]] AND value = [[?value]] ',
    array(
        $param1,
        $param2
    )
);

$test_rpcommad->attachRPCommand($cmd);
$category->addCommand($test_rpcommad);

$category->addCommand(new Command_Role_Sync());
$category->addCommand(new Command_Role_Capability_Sync());
$category->addCommand(new Command_Role_Compare());
$category->addCommand(new Command_Upgrade());
    
// Returning the category
return $category;