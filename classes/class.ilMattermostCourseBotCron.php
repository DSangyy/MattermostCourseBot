<?php

class ilMattermostCourseBotCron extends ilCronJob
{
    public const JOB_ID = 'mmcoursebot_cron';

    private ilMattermostCourseBotPlugin $plugin;

    public function __construct()
    {
        global $DIC;
        $DIC->logger()->root()->debug('init MattermostCourseBot CronJob');
        $this->plugin = ilMattermostCourseBotPlugin::getInstance();
    }

    public function getId(): string
    {
        return self::JOB_ID;
    }

    public function getTitle(): string
    {
        return "MattermostCourseBot Cron";
    }

    public function getDescription(): string
    {
        return "CronJob for sending post about course's updates to Mattermost";
    }

    public function getDefaultScheduleType(): \ILIAS\Cron\Schedule\CronJobScheduleType
    {
        return \ILIAS\Cron\Schedule\CronJobScheduleType::SCHEDULE_TYPE_IN_MINUTES;
    }

    public function getDefaultScheduleValue(): int
    {
        return 10;
    }

    public function hasAutoActivation(): bool
    {
        return true;
    }

    public function hasFlexibleSchedule(): bool
    {
        return false;
    }

    public function run(): ilCronJobResult
    {
        global $DIC;
        $cronResult = new ilCronJobResult();
        $cronResult->setStatus(ilCronJobResult::STATUS_NO_ACTION);

        try {
            $this->execJob();
            $cronResult->setStatus(ilCronJobResult::STATUS_OK);
        } catch(Exception $e) {
            $cronResult->setStatus(ilCronJobResult::STATUS_FAIL);
            $DIC->logger()->root()->log($e->getMessage());
        }

        return $cronResult;
    }

    private function execJob(): void
    {
        global $DIC;
        $logger = $DIC->logger()->root();
        $db = $DIC->database();

        $logger->debug("mmcoursebot cron exec");

        if (!$this->shouldRun())
        {
            return;
        }

        // Process watching objects
        $query = "SELECT id, obj_id, parent_ref_id, type FROM il_mmcoursebot_watch";
        $result = $db->query($query);
        if ($db->numRows($result) > 0)
        {
            $obj_data = [];
            while ($row = $db->fetchAssoc($result)) {
                $obj_data[] = $row;
            }

            foreach ($obj_data as $data)
            {

                $obj = new ilObject(0, false);
                $obj->setId((int)$data['obj_id']);
                $obj->setType((string)$data['type']);
                $obj->read();

                $logger->info('obj: ' . $obj->untranslatedTitle);
                if ($obj->getOfflineStatus())
                {
                    continue;
                }
                $this->plugin->prepareMessage($obj, (int)$data['parent_ref_id']);

                $query = "DELETE FROM il_mmcoursebot_watch WHERE id = " . $db->quote($data['id'], 'integer');
                $db->manipulate($query);
            }
        }

        // Get messages and send them
        $query = "SELECT id, mmchannel_id, message FROM il_mmcoursebot_cron";
        $result = $db->query($query);
        if ($db->numRows($result) == 0)
        {
            return;
        }

        $msg_data = [];
        while ($row = $db->fetchAssoc($result)) {
            $msg_data[] = $row;
        }

        foreach ($msg_data as $data)
        {
            $is_success = $this->plugin->botAPI->postMessage($data['mmchannel_id'], $data['message']);
            if ($is_success)
            {
                $query = "DELETE FROM il_mmcoursebot_cron WHERE id = " .$db->quote($data['id'], 'integer');
                $db->manipulate($query);
            }
        }
    }

    private function shouldRun() : bool
    {
        $start_time = $this->plugin->cron_start_time;
        $end_time = $this->plugin->cron_end_time;

        $now = date('H:i', time());

        if (strcmp($start_time, $end_time) > 0)
        {
            return strcmp($now, $start_time) >= 0 || strcmp($now, $end_time) <= 0;
        }
        else
        {
            return strcmp($now, $start_time) >= 0 && strcmp($now, $end_time) <= 0;
        }
    }

    public static function installCronJob(ilMattermostCourseBotPlugin $plugin)
    {
        global $DIC;
        if (isset($DIC['cron.repository'])) {
            $job = $DIC->cron()->repository()->getJobInstance(
                self::JOB_ID,
                'Plugins/MattermostCourseBot',
                self::class,
                false
            );
            $DIC->cron()->repository()->createDefaultEntry(
                $job,
                'Plugins/MattermostCourseBot',
                self::class,
                $plugin->getDirectory() . '/classes/'
            );
        }
    }

    public static function uninstallCronJob(ilMattermostCourseBotPlugin $plugin)
    {
        global $DIC;
        if (isset($DIC['cron.repository'])) {
            $DIC->cron()->repository()->unregisterJob('Plugins/MattermostCourseBot', []);
        }
    }
}

?>