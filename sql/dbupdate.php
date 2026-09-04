

<#1>
<?php
/* Copyright (c) 1998-2017 ILIAS open source, Extended GPL, see docs/LICENSE  */

/**
 * Test Page Component  plugin: database update script
 */ 

/**
 * Additional values
 */
if (!$ilDB->tableExists('il_mmcoursebot_table'))
{
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),

        'obj_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),
        
        'ref_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => false,
            'default' => 0
        ),

        'parent_ref_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => false,
            'default' => 0
        ),

        'course_ref_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => false,
            'default' => 0
        ),
        
        'title' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => ''
        ),
        
        'mmchannel_id' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => ''
        ),
        
        'should_notify' => array(
            'type' => 'integer',
            'length' => 1,
            'notnull' => true,
            'default' => 0
        ),
    );
    $ilDB->createTable('il_mmcoursebot_table', $fields);
    $ilDB->addPrimaryKey('il_mmcoursebot_table', array('id'));
    $ilDB->createSequence('il_mmcoursebot_table');
}

if (!$ilDB->tableExists('il_mmcoursebot_cron'))
{
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),
        
        'mmchannel_id' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => ''
        ),
        
        'message' => array(
            'type' => 'text',
            'length' => 255,
            'notnull' => false,
            'default' => ''
        ),
    );
    $ilDB->createTable('il_mmcoursebot_cron', $fields);
    $ilDB->addPrimaryKey('il_mmcoursebot_cron', array('id'));
    $ilDB->createSequence('il_mmcoursebot_cron');
}

if (!$ilDB->tableExists('il_mmcoursebot_watch'))
{
    $fields = array(
        'id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),

        'obj_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),

        'parent_ref_id' => array(
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
            'default' => 0
        ),

        'type' => array(
            'type' => 'text',
            'length' => 6,
            'notnull' => true,
            'default' => ''
        ),
    );
    $ilDB->createTable('il_mmcoursebot_watch', $fields);
    $ilDB->addPrimaryKey('il_mmcoursebot_watch', array('id'));
    $ilDB->createSequence('il_mmcoursebot_watch');
}
?>
