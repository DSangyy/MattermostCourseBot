<?php

declare(strict_types=1);

/**
 * @ilCtrl_isCalledBy ilMattermostCourseBotConfigGUI: ilObjComponentSettingsGUI
 */
class ilMattermostCourseBotConfigGUI extends ilPluginConfigGUI
{
    private ilCtrl $ctrl;
    private $tpl;
    private ilLanguage $lng;

    private ilMattermostCourseBotPlugin $plugin;

    public function __construct()
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->plugin = ilMattermostCourseBotPlugin::getInstance();
    }

    public function performCommand(string $cmd): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd();

        switch ($next_class) {
            default:
                $this->{$cmd}();
                break;
        }
    }

    protected function initConfigurationForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle("Mattermost Course Bot configurations");

        $mm_url = new ilTextInputGUI("Mattermost URL", "mm_url");
        $mm_url->setRequired(true);
        $form->addItem($mm_url);

        $api_key = new ilTextInputGUI("API Key", "api_key");
        $api_key->setRequired(true);
        $form->addItem($api_key);

        $team_id = new ilTextInputGUI("Team ID", "team_id");
        $team_id->setRequired(true);
        $form->addItem($team_id);

        $default_users = new ilTextInputGUI("Default User IDs (separated by ';')", "default_users");
        $default_users->setRequired(true);
        $form->addItem($default_users);

        $tracked_obj_types = new ilTextInputGUI("Tracked object types (separated by ';')", "tracked_obj_types");
        $tracked_obj_types->setRequired(true);
        $form->addItem($tracked_obj_types);

        $tracked_obj_types_name = new ilTextInputGUI("Tracked object type names (separated by ';')", "tracked_obj_types_name");
        $tracked_obj_types_name->setRequired(true);
        $form->addItem($tracked_obj_types_name);

        $msg_format = new ilTextInputGUI("Message (markdown formatted, supports %title% and %type_name%)", "msg_format");
        $msg_format->setRequired(true);
        $form->addItem($msg_format);

        $cron_start_time = new ilTextInputGUI("Cronjob start time (HH:mm)", "cron_start_time");
        $cron_start_time->setRequired(false);
        $form->addItem($cron_start_time);

        $cron_end_time = new ilTextInputGUI("Cronjob end time (HH:mm)", "cron_end_time");
        $cron_end_time->setRequired(false);
        $form->addItem($cron_end_time);

        // Add Save Button
        $form->addCommandButton("save", "Save");
        $form->setFormAction($this->ctrl->getFormAction($this));

        return $form;
    }


    protected function configure(): void
    {
        $form = $this->initConfigurationForm();

        $form->setValuesByArray([
            "mm_url" => $this->plugin->settings->get("mm_url", ""),
            "api_key" => $this->plugin->settings->get("api_key", ""),
            "team_id" => $this->plugin->settings->get("team_id", ""),
            "default_users" => $this->plugin->settings->get("default_users", ""),
            "tracked_obj_types" => $this->plugin->settings->get("tracked_obj_types", "tst"),
            "tracked_obj_types_name" => $this->plugin->settings->get("tracked_obj_types_name", "Test"),
            "msg_format" => $this->plugin->settings->get("msg_format", "%title% %type_name%"),
            "cron_start_time" => $this->plugin->settings->get("cron_start_time", "00:00"),
            "cron_end_time" => $this->plugin->settings->get("cron_end_time", "23:59"),
        ]);

        $this->tpl->setContent($form->getHTML());
    }

    protected function save(): void
    {
        $form = $this->initConfigurationForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->configure();
            return;
        }

        $form->setValuesByPost();
        $this->plugin->settings->set("mm_url", $form->getInput("mm_url"));
        $this->plugin->settings->set("api_key", $form->getInput("api_key"));
        $this->plugin->settings->set("team_id", $form->getInput("team_id"));
        $this->plugin->settings->set("default_users", $form->getInput("default_users"));
        $this->plugin->settings->set("tracked_obj_types", $form->getInput("tracked_obj_types"));
        $this->plugin->settings->set("tracked_obj_types_name", $form->getInput("tracked_obj_types_name"));
        $this->plugin->settings->set("msg_format", $form->getInput("msg_format"));
        $this->plugin->settings->set("cron_start_time", $form->getInput("cron_start_time"));
        $this->plugin->settings->set("cron_end_time", $form->getInput("cron_end_time"));

        // Success alert message
        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
            "Configurations Saved",
            true
        );

        $this->ctrl->redirect($this, "configure");
    }
}
