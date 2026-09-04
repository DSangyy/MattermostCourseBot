<?php

class ilMattermostCourseBotPlugin extends ilEventHookPlugin
{
	public const ID = 'mmcoursebot';

	public ilSetting $settings;

	private $tracked_obj_types = []; // ['tst', 'file'];

	private $tracked_obj_types_name = []; // ['Test', 'File'];

	private $msg_format = ""; // "**%title** has been added to course! Check it out now!";

	public $cron_start_time = "";

	public $cron_end_time = "";
	
	public ilMattermostCourseBotAPI $botAPI;

	private $folder_mem = [];

	protected static ?ilMattermostCourseBotPlugin $instance = null;
	
	public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
	{
		$this->settings = new ilSetting(self::ID . "_config");
		$this->botAPI = new ilMattermostCourseBotAPI();
		$this->updateSettings();

		parent::__construct($db, $component_repository, $id);
	}

	public static function getInstance(): self
	{
		if (static::$instance === null) {
			global $DIC;

			$component_factory = $DIC['component.factory'];
			$plugin = $component_factory->getPlugin(self::ID);

			static::$instance = $plugin;
		}
		return self::$instance;
	}

	public function updateSettings()
	{
		$this->tracked_obj_types = array_filter(explode(";", $this->settings->get("tracked_obj_types", "tst")));
		$this->tracked_obj_types_name = array_filter(explode(";", $this->settings->get("tracked_obj_types_name", "Test")));
		$this->msg_format = $this->settings->get("msg_format", "**%title** has been added to the course!");
		$this->cron_start_time = $this->settings->get("cron_start_time", "00:00");
		$this->cron_end_time = $this->settings->get("cron_end_time", "23:59");

		$this->botAPI->updateSettings($this->settings);
	}

	protected function beforeActivation(): bool
	{
		ilMattermostCourseBotCron::installCronJob($this);

		return parent::beforeActivation();
	}

	protected function afterDeactivation(): void
	{
		ilMattermostCourseBotCron::uninstallCronJob($this);

		parent::afterDeactivation();
	}

	protected function uninstallCustom(): void
	{
		global $DIC;
		$db = $DIC->database();

		if ($db->tableExists('il_mmcoursebot_table')) {
			$db->dropTable('il_mmcoursebot_table');
		}
		
		if ($db->tableExists('il_mmcoursebot_cron')) {
			$db->dropTable('il_mmcoursebot_cron');
		}

		if ($db->tableExists('il_mmcoursebot_watch')) {
			$db->dropTable('il_mmcoursebot_watch');
		}
	}
	
	public function getPluginName() : string
	{
		return "MattermostCourseBot";
	}

	public function handleEvent(string $a_component, string $a_event, array $a_parameter) : void
	{
		global $DIC;
		$logger = $DIC->logger()->root();
		$logger->info("new event: " . $a_component . " - " . $a_event . " - " . json_encode($a_parameter ?? null));

		try {
			if ($a_component == 'components/ILIAS/Course') {

				switch ($a_event) {
					case "create":
						//$this->handleCourseCreated($a_parameter);
						break;

					case "update":
						$this->updateTableEntry($a_parameter['object'], true);
						break;

					case "delete":
						$this->handleCourseDeleted($a_parameter);
						break;
				}
			} elseif ($a_component == 'components/ILIAS/ILIASObject') {
				if ($a_event == 'putObjectInTree') {
					// New course put in root tree
					if ($a_parameter['obj_type'] == 'crs' && $a_parameter['parent_ref_id'] == 1) {
						$this->addToTable($a_parameter['object'], 1);
					}
					// New file put in known courses
					if (in_array($a_parameter['obj_type'], $this->tracked_obj_types)) {
						$this->handleNewObjEvent($a_parameter);
					}
				} // Check if new folder needed to be tracked
				elseif ($a_event == 'create' && $a_parameter['obj_type'] == 'fold') {
					$this->folder_mem[] = $a_parameter['obj_id'];
				} elseif ($a_event == 'update' && $a_parameter['obj_type'] == 'fold') {
					$this->updateTableEntry(new ilObject($a_parameter['ref_id']), false);
				}
			} elseif ($a_component == 'components/ILIAS/Tree' && $a_event == 'insertNode') {
				$this->checkNewFolderToTrack($a_parameter);
			}
		} catch (Exception $e) {
			$logger->error($e);
		}
	}
	
	protected function updateTableEntry($obj, $is_course) : void
	{
		global $DIC;
		$db = $DIC->database();
		
		$obj_id = $obj->getId();
		$title = $obj->untranslatedTitle;
		$should_notify = str_contains($title, '[no-notification]') ? 0 : 1;
		
		// Only update online course
		if ($is_course && !$obj->getObjectProperties()->getPropertyIsOnline()->getIsOnline())
		{
			return;
		}
		
		$query = "SELECT obj_id, mmchannel_id FROM il_mmcoursebot_table WHERE obj_id = " . $db->quote($obj_id, 'integer');
		$result = $db->query($query);
		
		if ($db->numRows($result) == 0)
		{
			return;
		}

		$data = $db->fetchAssoc($result);
		$mmchannel_id = (string) $data['mmchannel_id'];
		
		if ($is_course && $should_notify == 1 && $mmchannel_id == '')
		{
			$mmchannel_id = $this->botAPI->createChannel($title);
		}
		
		if ($is_course)
		{
			$db->update('il_mmcoursebot_table',
				array(
					'title' => array('text', $title),
					'should_notify' => array('integer', $should_notify),
					'mmchannel_id' => array('text', $mmchannel_id)),
				array(
					'obj_id' => array('integer', $obj_id))
			);
		}
		else
		{
			$db->update('il_mmcoursebot_table',
				array(
					'title' => array('text', $title),
					'should_notify' => array('integer', $should_notify)),
				array(
					'obj_id' => array('integer', $obj_id))
			);
		}
	}
	
	protected function handleCourseCreated($a_parameter)
	{
	}
	
	protected function handleCourseDeleted($a_parameter)
	{
		global $DIC;
		$db = $DIC->database();

		$query = "DELETE FROM il_mmcoursebot_table " .
			"WHERE course_ref_id = " .
			"(SELECT ref_id FROM il_mmcoursebot_table WHERE obj_id = " . $db->quote($a_parameter['obj_id'], 'integer') . ")" ;
		$db->manipulate($query);
	}
	
	protected function addToTable($obj, $parent_ref_id, $course_ref_id = 0) : void
	{
		global $DIC;
		$db = $DIC->database();
		
		$obj_id = $obj->getId();
		$ref_id = $obj->getRefId();
		$title = $obj->untranslatedTitle;
		$should_notify = str_contains($title, '[no-notification]') ? 0 : 1;
		
		$id = $db->nextId('il_mmcoursebot_table');
		$db->insert('il_mmcoursebot_table',
			array(
				'id' => array('integer', $id),
				'obj_id' => array('integer', $obj_id),
				'ref_id' => array('integer', $ref_id),
				'parent_ref_id' => array('integer', $parent_ref_id),
				'course_ref_id' => array('integer', $course_ref_id == 0 ? $ref_id : $course_ref_id),
				'title' => array('text', $title),
				'should_notify' => array('integer', $should_notify)
			)
		);
	}

	protected function handleNewObjEvent($a_parameter) : void
	{
		global $DIC;
		$db = $DIC->database();

		$obj = $a_parameter['object'];
		$is_online = $obj->getObjectProperties()->getPropertyIsOnline()->getIsOnline();
		$parent_ref_id = $a_parameter['parent_ref_id'];

		if ($parent_ref_id == 1) {
			return;
		}

		$query = "SELECT id FROM il_mmcoursebot_table WHERE ref_id = " . $db->quote($parent_ref_id, 'integer');
		$result = $db->query($query);

		if ($db->numRows($result) == 0) {
			return;
		}

		if ($is_online)
		{
			$this->prepareMessage($obj, $parent_ref_id);
		}
		else
		{
			$id = $db->nextId('il_mmcoursebot_watch');
			$db->insert('il_mmcoursebot_watch',
				array(
					'id' => array('integer', $id),
					'obj_id' => array('integer', $obj->getId()),
					'parent_ref_id' => array('integer', $parent_ref_id),
					'type' => array('text', $a_parameter['obj_type']),
				)
			);
		}

	}

	public function prepareMessage($obj, $parent_ref_id) : void
	{
		global $DIC;
		$db = $DIC->database();

		$query =
			"BEGIN NOT ATOMIC". PHP_EOL
			."	SET @continue = 1;". PHP_EOL
			."	SET @course_ref_id = 0;". PHP_EOL
			."	SET @parent_ref_id = ". $db->quote($parent_ref_id, 'integer') .";". PHP_EOL
			."	SET @should_notify = 1;". PHP_EOL
			."	SET @mmchannel_id = '';". PHP_EOL
			."	SET @path = '';". PHP_EOL

			."	WHILE @continue = 1 DO". PHP_EOL
			."		SELECT title, ref_id, course_ref_id, parent_ref_id, mmchannel_id, should_notify INTO @t_title, @t_ref_id, @t_course_ref_id, @t_parent_ref_id, @t_mmchannel_id, @t_should_notify". PHP_EOL
			."		FROM il_mmcoursebot_table WHERE ref_id = @parent_ref_id;". PHP_EOL
			."		IF @t_ref_id IS NULL THEN". PHP_EOL
			."			SET @continue = 0;". PHP_EOL
			."		ELSE". PHP_EOL
			."			SET @should_notify = @should_notify * @t_should_notify;". PHP_EOL
			."			SET @path = CONCAT(@t_title, '/', @path);". PHP_EOL
			."			IF @t_ref_id = @t_course_ref_id THEN". PHP_EOL
			."				SET @mmchannel_id = @t_mmchannel_id;". PHP_EOL
			."				SET @continue = 0;". PHP_EOL
			."			ELSE". PHP_EOL
			."				SET @parent_ref_id = @t_parent_ref_id;". PHP_EOL
			."			END IF;". PHP_EOL
			."		END IF;". PHP_EOL
			."	END WHILE;". PHP_EOL
			."  SELECT @path, @mmchannel_id, @should_notify;". PHP_EOL
			."END";


		$result = $db->query($query);
		$data = $db->fetchAssoc($result);

		$path = (string) $data['@path'];
		$mmchannel_id = (string) $data['@mmchannel_id'];
		$should_notify = (int) $data['@should_notify'];

		$result->closeCursor();
		if ($path == "" || $mmchannel_id == '' || $should_notify == 0)
		{
			return;
		}

		$msg_data = array(
			'%path%' => $path,
			'%title%' => $obj->untranslatedTitle,
			'%type_name%' => $this->tracked_obj_types_name[array_search($obj->getType(), $this->tracked_obj_types)],
		);

		$id = $db->nextId('il_mmcoursebot_cron');
		$db->insert('il_mmcoursebot_cron',
			array(
				'id' => array('integer', $id),
				'mmchannel_id' => array('text', $mmchannel_id),
				'message' => array('text', str_replace(array_keys($msg_data), array_values($msg_data), $this->msg_format))
			)
		);
	}

	protected function checkNewFolderToTrack($a_parameter) : void
	{
		if (count($this->folder_mem) == 0)
		{
			return;
		}

		$obj = new ilObject();
		$obj->setRefId($a_parameter['node_id']);
		$obj->setType('fold');
		try{
			$obj->read();
		} catch (Exception $e)
		{
			return;
		}

		$obj_id = $obj->getId();
		$index = array_search($obj_id, $this->folder_mem);

		if ($index === false)
		{
			return;
		}

		array_splice($this->folder_mem, $index, 1);

		global $DIC;
		$db = $DIC->database();

		$query = "SELECT ref_id, course_ref_id FROM il_mmcoursebot_table WHERE ref_id = " . $db->quote($a_parameter['parent_id'], 'integer');
		$result = $db->query($query);

		if ($db->numRows($result) == 0)
		{
			return;
		}

		$this->addToTable($obj, $a_parameter['parent_id'], (int)$db->fetchAssoc($result)['course_ref_id']);
	}
}

?>
