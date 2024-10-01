<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\UI\Component\Input\Container\Form\Form;

class ilDclTableEditGUI
{
    private ?int $table_id;
    private ilDclTable $table;
    protected \ILIAS\UI\Factory $ui_factory;
    protected \ILIAS\UI\Renderer $ui_renderer;
    protected ilLanguage $lng;
    protected ilCtrl $ctrl;
    protected ilGlobalTemplateInterface $tpl;
    protected ilToolbarGUI $toolbar;
    protected \ILIAS\UI\Component\Input\Container\Form\Factory $formNew;
    protected ilPropertyFormGUI $form;
    protected ILIAS\HTTP\Services $http;
    protected ILIAS\Refinery\Factory $refinery;
    protected ilDclTableListGUI $parent_object;


    /**
     * Constructor
     */
    public function __construct(ilDclTableListGUI $a_parent_obj)
    {
        global $DIC;

        $locator = $DIC['ilLocator'];

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->parent_object = $a_parent_obj;
        $this->obj_id = $a_parent_obj->getObjId();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();

        $table_id = null;
        if ($this->http->wrapper()->query()->has("table_id")) {
            $table_id = $this->http->wrapper()->query()->retrieve('table_id', $this->refinery->kindlyTo()->int());
        }

        $this->table_id = $table_id;
        $this->table = ilDclCache::getTableCache($this->table_id);

        $this->ctrl->saveParameter($this, 'table_id');
        if ($this->table->getTitle()) {
            $locator->addItem($this->table->getTitle(), $this->ctrl->getLinkTarget($this, 'edit'));
        }
        $this->tpl->setLocator();

        if (!$this->checkAccess()) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('permission_denied'), true);
            $this->ctrl->redirectByClass(ilDclRecordListGUI::class, 'listRecords');
        }
    }

    protected int $obj_id;

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case 'update':
                $newForm = $this->initNewForm()->withRequest($this->http->request());
                $data = $newForm->getData();
                $this->table->setTitle($data["edit"]["title"]);
                $this->table->setDefaultSortField($data["edit"]["sort_by"]);
                $this->table->setDefaultSortFieldOrder($data["edit"]["sort_order"]);
                $this->table->setDescription($data["edit"]["description"]);
                $this->table->setAddPerm($data["user"]["user_add_entries"]);
                $this->table->setSaveConfirmation($data["user"]["confirm_save"]);
                $this->table->setEditPerm((bool)$data["user"]["user_edit_entries"]);
                $this->table->setEditByOwner(($data["user"]["user_edit_entries"]["edit"] ?? "") === "own");
                $this->table->setDeletePerm((bool)$data["user"]["user_delete_entries"]);
                $this->table->setDeleteByOwner(($data["user"]["user_delete_entries"]["delete"] ?? "") === "own");
                $this->table->setViewOwnRecordsPerm($data["user"]["view_only_own"]);
                $this->table->setExportEnabled($data["user"]["allow_export"]);
                $this->table->setImportEnabled($data["user"]["allow_import"]);
                if ($data["user"]["limited_action_period"] !== null) {
                    $this->table->setLimited(true);
                    $this->table->setLimitStart($data["user"]["limited_action_period"]["start"]);
                    $this->table->setLimitEnd($data["user"]["limited_action_period"]["end"]);
                } else {
                    $this->table->setLimited(false);
                }
                $this->table->doUpdate();
                $this->tpl->setOnScreenMessage('success', $this->lng->txt("dcl_msg_table_edited"), true);
                $this->ctrl->redirectByClass(self::class, "edit");
                break;
            default:
                $this->$cmd();
                break;
        }
    }

    public function create(): void
    {
        $this->initForm();
        $this->getStandardValues();
        $this->tpl->setContent($this->form->getHTML());
    }

    private function initNewForm(): Form
    {
        $fields = array_filter($this->table->getFields(), function (ilDclBaseFieldModel $field) {
            return !is_null($field->getRecordQuerySortObject());
        });
        $options = [0 => $this->lng->txt('dcl_please_select')];
        foreach ($fields as $field) {
            if ($field->getId() == 'comments') {
                continue;
            }
            $options[$field->getId()] = $field->getTitle();
        }

        $input_field = $this->ui_factory->input()->field();
        $this->formNew = $this->ui_factory->input()->container()->form();
        $title = $input_field->text("Title")->withRequired(true)->withValue($this->table->getTitle());
        $sort_field = $input_field->select("Default sort field", $options, "The table will be sorted by this field by default")
            ->withValue($this->table->getDefaultSortField());
        $sort_order = $input_field->select("Default sort field order", ['asc' => $this->lng->txt('dcl_asc'), 'desc' => $this->lng->txt('dcl_desc')])
            ->withValue($this->table->getDefaultSortFieldOrder());
        $markdown = $input_field->markdown(new ilUIMarkdownPreviewGUI(), "Additional Information")->withValue($this->table->getDescription());
        $edit_inputs = [
            "title" => $title,
            "sort_by" => $sort_field,
            "sort_order" => $sort_order,
            "description" => $markdown
        ];
        $edit_section = $input_field->section($edit_inputs, "Edit Table Settings");

        $user_add_entries = $input_field->checkbox("User can add entries")->withValue($this->table->getAddPerm());
        $save_confirmation = $input_field->checkbox("Save confirmation")->withValue($this->table->getSaveConfirmation());
        $user_can_edit_radio = $input_field->radio("")
            ->withOption("all", "All entries")
            ->withOption("own", "Only Own Entries");
        if ($this->table->getEditPerm()) {
            $user_can_edit_radio = $user_can_edit_radio->withValue("all");
            $user_can_edit = $input_field->optionalGroup(["delete" => $user_can_edit_radio], "User can delete entries");
        } else if ($this->table->getEditByOwner()) {
            $user_can_edit_radio = $user_can_edit_radio->withValue("own");
            $user_can_edit = $input_field->optionalGroup(["delete" => $user_can_edit_radio], "User can delete entries");
        } else {
            $user_can_edit = $input_field->optionalGroup(["delete" => $user_can_edit_radio], "User can delete entries")->withValue(null);
        }

        $user_can_delete_radio = $input_field->radio("")
            ->withOption("all", "All entries")
            ->withOption("own", "Only Own Entries");
        if ($this->table->getDeletePerm()) {
            $user_can_delete_radio = $user_can_delete_radio->withValue("all");
            $user_can_delete = $input_field->optionalGroup(["delete" => $user_can_delete_radio], "User can delete entries");
        } else if ($this->table->getDeleteByOwner()) {
            $user_can_delete_radio = $user_can_delete_radio->withValue("own");
            $user_can_delete = $input_field->optionalGroup(["delete" => $user_can_delete_radio], "User can delete entries");
        } else {
            $user_can_delete = $input_field->optionalGroup(["delete" => $user_can_delete_radio], "User can delete entries")->withValue(null);
        }

        $view_only_own = $input_field->checkbox("View only own entries");
        $allow_export = $input_field->checkbox("Allow Export function for all user")->withValue($this->table->getExportEnabled());
        $allow_import = $input_field->checkbox("Allow Import function for all user")->withValue($this->table->getImportEnabled());
        if ($this->table->getLimitStart() === "" && $this->table->getLimitEnd() === "") {
            $start_date = $input_field->dateTime("Start")->withValue(null);
            $end_date = $input_field->dateTime("End")->withValue(null);
            $limited_action_period = $input_field->optionalGroup(["start" => $start_date, "end" => $end_date], "Limited Add / Edit / Delete Period")->withValue(null);
        } else {
            $start_date = $input_field->dateTime("Start")->withValue($this->table->getLimitStart());
            $end_date = $input_field->dateTime("End")->withValue($this->table->getLimitEnd());
            $limited_action_period = $input_field->optionalGroup(["start" => $start_date, "end" => $end_date], "Limited Add / Edit / Delete Period");
        }

        //😭
        $user_action_inputs = [
            "user_add_entries" => $user_add_entries,
            "confirm_save" => $save_confirmation,
            "user_edit_entries" => $user_can_edit,
            "user_delete_entries" => $user_can_delete,
            "view_only_own" => $view_only_own,
            "allow_export" => $allow_export,
            "allow_import" => $allow_import,
            "limited_action_period" => $limited_action_period,
        ];
        $user_action_section = $input_field->section($user_action_inputs, "User Actions");

        return $this->formNew->standard($this->ctrl->getFormAction($this, "update"), ["edit" => $edit_section, "user" => $user_action_section]);
    }

    public function edit(): void
    {
        if (!$this->table_id) {
            $this->ctrl->redirectByClass(ilDclFieldEditGUI::class, "listFields");

            return;
        } else {
            $this->table = ilDclCache::getTableCache($this->table_id);
        }
        $this->initForm("edit");
        $this->getValues();
        $newForm = $this->initNewForm();
        $this->tpl->setContent($this->ui_renderer->render($newForm));
//        $this->tpl->setContent($this->form->getHTML());
    }

    public function getValues(): void
    {
        $values = [
            'title' => $this->table->getTitle(),
            'add_perm' => (int)$this->table->getAddPerm(),
            'edit_perm' => (int)$this->table->getEditPerm(),
            'edit_perm_mode' => $this->table->getEditByOwner() ? 'own' : 'all',
            'delete_perm' => (int)$this->table->getDeletePerm(),
            'delete_perm_mode' => $this->table->getDeleteByOwner() ? 'own' : 'all',
            'export_enabled' => $this->table->getExportEnabled(),
            'import_enabled' => $this->table->getImportEnabled(),
            'limited' => $this->table->getLimited(),
            'limit_start' => substr($this->table->getLimitStart(), 0, 10) . " " . substr(
                    $this->table->getLimitStart(),
                    -8
                ),
            'limit_end' => substr($this->table->getLimitEnd(), 0, 10) . " " . substr($this->table->getLimitEnd(), -8),
            'default_sort_field' => $this->table->getDefaultSortField(),
            'default_sort_field_order' => $this->table->getDefaultSortFieldOrder(),
            'description' => $this->table->getDescription(),
            'view_own_records_perm' => $this->table->getViewOwnRecordsPerm(),
            'save_confirmation' => $this->table->getSaveConfirmation(),
        ];
        if (!$this->table->getLimitStart()) {
            $values['limit_start'] = null;
        }
        if (!$this->table->getLimitEnd()) {
            $values['limit_end'] = null;
        }
        $this->form->setValuesByArray($values);
    }

    public function getStandardValues(): void
    {
        $values = [
            'title' => "",
            'add_perm' => 1,
            'edit_perm' => 1,
            'edit_perm_mode' => 'own',
            'delete_perm_mode' => 'own',
            'delete_perm' => 1,
            'edit_by_owner' => 1,
            'export_enabled' => 0,
            'import_enabled' => 0,
            'limited' => 0,
            'limit_start' => null,
            'limit_end' => null,
        ];
        $this->form->setValuesByArray($values);
    }

    public function cancel(): void
    {
        $this->ctrl->redirectByClass("ilDclTableListGUI", "listTables");
    }

    /**
     * initEditCustomForm
     */
    public function initForm(string $a_mode = "create"): void
    {
        $this->form = new ilPropertyFormGUI();

        $item = new ilTextInputGUI($this->lng->txt('title'), 'title');
        $item->setRequired(true);
        $this->form->addItem($item);

        // Show default order field, direction and tableswitcher only in edit mode, because table id is not yet given and there are no fields to select
        if ($a_mode != 'create') {
            $switcher = new ilDclSwitcher($this->toolbar, $this->ui_factory, $this->ctrl, $this->lng);
            $switcher->addTableSwitcherToToolbar(
                $this->parent_object->getDataCollectionObject()->getTables(),
                self::class,
                'edit'
            );

            $item = new ilSelectInputGUI($this->lng->txt('dcl_default_sort_field'), 'default_sort_field');
            $item->setInfo($this->lng->txt('dcl_default_sort_field_desc'));
            $fields = array_filter($this->table->getFields(), function (ilDclBaseFieldModel $field) {
                return !is_null($field->getRecordQuerySortObject());
            });
            $options = [0 => $this->lng->txt('dcl_please_select')];
            foreach ($fields as $field) {
                if ($field->getId() == 'comments') {
                    continue;
                }
                $options[$field->getId()] = $field->getTitle();
            }
            $item->setOptions($options);
            $this->form->addItem($item);

            $item = new ilSelectInputGUI($this->lng->txt('dcl_default_sort_field_order'), 'default_sort_field_order');
            $options = ['asc' => $this->lng->txt('dcl_asc'), 'desc' => $this->lng->txt('dcl_desc')];
            $item->setOptions($options);
            $this->form->addItem($item);
        }

        $item = new ilTextAreaInputGUI($this->lng->txt('additional_info'), 'description');
        $item->setUseRte(true);
        $item->setInfo($this->lng->txt('dcl_additional_info_desc'));
        $item->setRteTagSet('mini');
        $this->form->addItem($item);

        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->lng->txt('dcl_permissions_form'));
        $this->form->addItem($section);

        $item = new ilCustomInputGUI();
        $item->setHtml($this->lng->txt('dcl_table_info'));
        $item->setTitle($this->lng->txt('dcl_table_info_title'));
        $this->form->addItem($item);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_add_perm'), 'add_perm');
        $item->setInfo($this->lng->txt("dcl_add_perm_desc"));
        $this->form->addItem($item);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_save_confirmation'), 'save_confirmation');
        $item->setInfo($this->lng->txt('dcl_save_confirmation_desc'));
        $this->form->addItem($item);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_edit_perm'), 'edit_perm');
        $this->form->addItem($item);

        $radios = new ilRadioGroupInputGUI('', 'edit_perm_mode');
        $radios->addOption(new ilRadioOption($this->lng->txt('dcl_all_entries'), 'all'));
        $radios->addOption(new ilRadioOption($this->lng->txt('dcl_own_entries'), 'own'));
        $item->addSubItem($radios);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_delete_perm'), 'delete_perm');
        $this->form->addItem($item);

        $radios = new ilRadioGroupInputGUI('', 'delete_perm_mode');
        $radios->addOption(new ilRadioOption($this->lng->txt('dcl_all_entries'), 'all'));
        $radios->addOption(new ilRadioOption($this->lng->txt('dcl_own_entries'), 'own'));
        $item->addSubItem($radios);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_view_own_records_perm'), 'view_own_records_perm');
        $this->form->addItem($item);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_export_enabled'), 'export_enabled');
        $item->setInfo($this->lng->txt('dcl_export_enabled_desc'));
        $this->form->addItem($item);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_import_enabled'), 'import_enabled');
        $item->setInfo($this->lng->txt('dcl_import_enabled_desc'));
        $this->form->addItem($item);

        $item = new ilCheckboxInputGUI($this->lng->txt('dcl_limited'), 'limited');
        $sitem1 = new ilDateTimeInputGUI($this->lng->txt('dcl_limit_start'), 'limit_start');
        $sitem1->setShowTime(true);
        $sitem2 = new ilDateTimeInputGUI($this->lng->txt('dcl_limit_end'), 'limit_end');
        $sitem2->setShowTime(true);
        $item->setInfo($this->lng->txt("dcl_limited_desc"));
        $item->addSubItem($sitem1);
        $item->addSubItem($sitem2);
        $this->form->addItem($item);

        if ($a_mode == "edit") {
            $this->form->addCommandButton('update', $this->lng->txt('dcl_table_' . $a_mode));
        } else {
            $this->form->addCommandButton('save', $this->lng->txt('dcl_table_' . $a_mode));
        }

        $this->form->addCommandButton('cancel', $this->lng->txt('cancel'));
        $this->ctrl->setParameter($this, "table_id", $this->table_id);
        $this->form->setFormAction($this->ctrl->getFormAction($this, $a_mode));
        if ($a_mode == "edit") {
            $this->form->setTitle($this->lng->txt('dcl_edit_table'));
        } else {
            $this->form->setTitle($this->lng->txt('dcl_new_table'));
        }
    }

    public function save(string $a_mode = "create"): void
    {
        //TODO update method to load own form
        global $DIC;
        $ilTabs = $DIC['ilTabs'];

        if (!ilObjDataCollectionAccess::checkActionForObjId('write', $this->obj_id)) {
            $this->accessDenied();

            return;
        }

        $ilTabs->activateTab("id_fields");
        $this->initForm($a_mode);

//        dd($this->checkInput($a_mode),$a_mode,$this->table_id);

        if ($this->checkInput($a_mode)) {
            if ($a_mode != "update") {
                $this->table = ilDclCache::getTableCache();
            } elseif ($this->table_id) {
                // we get here
                $this->table = ilDclCache::getTableCache($this->table_id);
            } else {
                $this->ctrl->redirectByClass("ildclfieldeditgui", "listFields");
            }

            $this->table->setTitle($this->form->getInput("title"));
            $this->table->setObjId($this->obj_id);
            $this->table->setSaveConfirmation((bool)$this->form->getInput('save_confirmation'));
            $this->table->setAddPerm((bool)$this->form->getInput("add_perm"));
            $this->table->setEditPerm((bool)$this->form->getInput("edit_perm"));
            if ($this->table->getEditPerm()) {
                $edit_by_owner = ($this->form->getInput('edit_perm_mode') == 'own');
                $this->table->setEditByOwner($edit_by_owner);
            }
            $this->table->setDeletePerm((bool)$this->form->getInput("delete_perm"));
            if ($this->table->getDeletePerm()) {
                $delete_by_owner = ($this->form->getInput('delete_perm_mode') == 'own');
                $this->table->setDeleteByOwner($delete_by_owner);
            }
            $this->table->setViewOwnRecordsPerm((bool)$this->form->getInput('view_own_records_perm'));
            $this->table->setExportEnabled((bool)$this->form->getInput("export_enabled"));
            $this->table->setImportEnabled((bool)$this->form->getInput("import_enabled"));
            $this->table->setDefaultSortField($this->form->getInput("default_sort_field"));
            $this->table->setDefaultSortFieldOrder($this->form->getInput("default_sort_field_order"));
            $this->table->setLimited((bool)$this->form->getInput("limited"));
            $this->table->setDescription($this->form->getInput('description'));
            $this->table->setLimitStart((string)$this->form->getInput("limit_start"));
            $this->table->setLimitEnd((string)$this->form->getInput("limit_end"));
            if ($a_mode == "update") {
                $this->table->doUpdate();
                $this->tpl->setOnScreenMessage('success', $this->lng->txt("dcl_msg_table_edited"), true);
                $this->ctrl->redirectByClass("ildcltableeditgui", "edit");
            } else {
                $this->table->doCreate();
                $this->tpl->setOnScreenMessage('success', $this->lng->txt("dcl_msg_table_created"), true);
                $this->ctrl->setParameterByClass("ildclfieldlistgui", "table_id", $this->table->getId());
                $this->ctrl->redirectByClass("ildclfieldlistgui", "listFields");
            }
        } else {
            $this->form->setValuesByPost();
            $this->tpl->setContent($this->form->getHTML());
        }
    }

    /**
     * Custom checks for the form input
     * @param $a_mode 'create' | 'update'
     */
    protected function checkInput(string $a_mode): bool
    {
        $return = $this->form->checkInput();

        // Title of table must be unique in one DC
        if ($a_mode == 'create') {
            if ($title = $this->form->getInput('title')) {
                if (ilObjDataCollection::_hasTableByTitle($title, $this->obj_id)) {
                    $inputObj = $this->form->getItemByPostVar('title');
                    $inputObj->setAlert($this->lng->txt("dcl_table_title_unique"));
                    $return = false;
                }
            }
        }

        if (!$return) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("form_input_not_valid"));
        }

        return $return;
    }

    public function accessDenied(): void
    {
        $this->tpl->setContent("Access denied.");
    }

    public function confirmDelete(): void
    {
        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setHeaderText($this->lng->txt('dcl_confirm_delete_table'));

        $conf->addItem('table', (string)$this->table->getId(), $this->table->getTitle());

        $conf->setConfirm($this->lng->txt('delete'), 'delete');
        $conf->setCancel($this->lng->txt('cancel'), 'cancelDelete');

        $this->tpl->setContent($conf->getHTML());
    }

    public function cancelDelete(): void
    {
        $this->ctrl->redirectByClass("ilDclTableListGUI", "listTables");
    }

    public function delete(): void
    {
        if (count($this->table->getCollectionObject()->getTables()) < 2) {
            $this->tpl->setOnScreenMessage(
                'failure',
                $this->lng->txt("dcl_cant_delete_last_table"),
                true
            ); //TODO change lng var
            $this->table->doDelete(true);
        } else {
            $this->table->doDelete();
        }
        $this->ctrl->clearParameterByClass("ilobjdatacollectiongui", "table_id");
        $this->ctrl->redirectByClass("ildcltablelistgui", "listtables");
    }

    public function enableVisible(): void
    {
        $this->table->setIsVisible(true);
        $this->table->doUpdate();
        $this->ctrl->redirectByClass(ilDclTableListGUI::class, 'listTables');
    }

    public function disableVisible(): void
    {
        $this->table->setIsVisible(false);
        $this->table->doUpdate();
        $this->ctrl->redirectByClass(ilDclTableListGUI::class, 'listTables');
    }

    public function enableComments(): void
    {
        $this->table->setPublicCommentsEnabled(true);
        $this->table->doUpdate();
        $this->ctrl->redirectByClass(ilDclTableListGUI::class, 'listTables');
    }

    public function disableComments(): void
    {
        $this->table->setPublicCommentsEnabled(false);
        $this->table->doUpdate();
        $this->ctrl->redirectByClass(ilDclTableListGUI::class, 'listTables');
    }

    public function setAsDefault(): void
    {
        $object = ilObjectFactory::getInstanceByObjId($this->obj_id);
        $order = 20;
        foreach ($object->getTables() as $table) {
            if ($table->getId() === $this->table->getId()) {
                $table->setOrder(10);
            } else {
                $table->setOrder($order);
                $order += 10;
            }
            $table->doUpdate();
        }
        $this->ctrl->redirectByClass(ilDclTableListGUI::class, 'listTables');
    }

    protected function checkAccess(): bool
    {
        $ref_id = $this->parent_object->getDataCollectionObject()->getRefId();

        return $this->table_id ? ilObjDataCollectionAccess::hasAccessToEditTable(
            $ref_id,
            $this->table_id
        ) : ilObjDataCollectionAccess::hasWriteAccess($ref_id);
    }
}
