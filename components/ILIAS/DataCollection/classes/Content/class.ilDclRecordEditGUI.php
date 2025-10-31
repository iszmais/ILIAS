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
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;

/**
 * @ilCtrl_Calls ilDclRecordEditGUI: ilDataCollectionUploadHandlerGUI
 */
class ilDclRecordEditGUI
{
    public const REDIRECT_RECORD_LIST = 1;
    public const REDIRECT_DETAIL = 2;

    protected ilDclTable $table;
    protected ilDclTableView $tableview;
    protected ilDclBaseRecordModel $record;
    protected readonly ilCtrl $ctrl;
    protected readonly ilLanguage $lng;
    protected readonly ilObjUser $user;
    protected readonly Factory $factory;
    protected readonly ilRbacSystem $rbac;
    protected readonly Renderer $renderer;
    protected readonly ILIAS\HTTP\Services $http;
    protected readonly ilGlobalTemplateInterface $tpl;
    protected readonly ILIAS\Refinery\Factory $refinery;

    public function __construct(protected ilObjDataCollection $obj, protected int $table_id, protected int $tableview_id)
    {
        global $DIC;

        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->ctrl = $DIC->ctrl();
        $this->rbac = $DIC->rbac()->system();
        $this->renderer = $DIC->ui()->renderer();
        $this->factory = $DIC->ui()->factory();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->user = $DIC->user();

        if ($this->http->wrapper()->query()->has('record_id')) {
            $record_id = $this->http->wrapper()->query()->retrieve(
                'record_id',
                $this->refinery->kindlyTo()->int()
            );
            $this->record = ilDclCache::getRecordCache($record_id);
        }
        $this->table = ilDclCache::getTableCache($this->table_id);
        $this->tableview = ilDclTableView::findOrGetInstance($this->tableview_id);
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        switch ($cmd) {
            case 'create':
                if ($this->rbac->checkAccess('add_entry', $this->obj->getRefid())) {
                    global $DIC;
                    $DIC->help()->setSubScreenId('create');
                    $this->tpl->setContent($this->renderer->render($this->getForm()));
                    return;
                }
                break;
            case 'edit':
                if (isset($this->record) && $this->record->hasPermissionToEdit($this->obj->getRefid())) {
                    $form = $this->getForm();
                    $this->tpl->setContent($this->renderer->render($form));
                    return;
                }
                break;
            case 'save':
                if (isset($this->record)) {
                    if ($this->record->hasPermissionToEdit($this->obj->getRefid())) {
                        $this->save();
                    }
                } else {
                    if ($this->rbac->checkAccess('add_entry', $this->obj->getRefid())) {
                        $this->save();
                    }
                }
                break;
            case 'confirmSave':
                $this->saveConfirmation();
                break;
            case 'delete':
                if ($this->record->hasPermissionToDelete($this->obj->getRefid())) {
                    $this->record->doDelete();
                    $this->tpl->setOnScreenMessage('success', $this->lng->txt('dcl_record_deleted'), true);
                    return;
                }
                break;
            case 'confirmDelete':
                if ($this->record->hasPermissionToDelete($this->obj->getRefid())) {
                    $this->confirmDelete();
                }
                break;
            default:
                $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_FAILURE, $this->lng->txt('dcl_msg_no_perm_edit'), true);
        }

        if ($this->http->wrapper()->query()->has('detail')) {
            $this->ctrl->setParameterByClass(ilDclDetailedViewGUI::class, 'record_id', $this->record->getId());
            $this->ctrl->setParameterByClass(ilDclDetailedViewGUI::class, 'table_id', $this->table->getId());
            $this->ctrl->setParameterByClass(ilDclDetailedViewGUI::class, 'tableview_id', $this->tableview->getId());
            $this->ctrl->redirectByClass(ilDclDetailedViewGUI::class, 'renderRecord');
        }

        $this->ctrl->redirectByClass(ilDclRecordListGUI::class, 'listRecords');
    }

    public function confirmDelete(): void
    {
        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setHeaderText($this->lng->txt('dcl_confirm_delete_record'));

        $all_fields = $this->table->getRecordFields();
        $record_data = "";
        foreach ($all_fields as $field) {
            $field_record = ilDclCache::getRecordFieldCache($this->record, $field);

            $record_representation = ilDclCache::getRecordRepresentation($field_record);
            if ($record_representation->getConfirmationHTML() != false) {
                $record_data .= $field->getTitle() . ": " . $record_representation->getConfirmationHTML() . "<br />";
            }
        }
        $conf->addItem('record_id', (string) $this->record->getId(), $record_data);
        $conf->addHiddenItem('table_id', (string) $this->table_id);
        $conf->addHiddenItem('tableview_id', (string) $this->tableview_id);
        $conf->setConfirm($this->lng->txt('delete'), 'delete');
        $conf->setCancel($this->lng->txt('cancel'), 'listRecords');
        $this->tpl->setContent($conf->getHTML());
    }

    public function saveConfirmation(ilDclBaseRecordModel $record_obj, string $filehash): void
    {
        $permission = ilObjDataCollectionAccess::hasWriteAccess($this->obj->getRefId());
        if ($permission) {
            $all_fields = $this->table->getRecordFields();
        } else {
            $all_fields = $this->table->getEditableFields(!$this->record->getId());
        }

        $date_obj = new ilDateTime(time(), IL_CAL_UNIX);
        $record_obj->setTableId($this->table_id);
        $record_obj->setLastUpdate($date_obj);
        $record_obj->setLastEditBy($this->user->getId());

        $confirmation = new ilConfirmationGUI();
        $confirmation->setFormAction($this->ctrl->getFormAction($this));
        $header_text = $this->lng->txt('dcl_confirm_storing_records');
        if (!$permission && !ilObjDataCollectionAccess::hasEditAccess($this->obj->getRefId())
            && !$this->table->getEditByOwner()
            && !$this->table->getEditPerm()
        ) {
            $header_text .= " " . $this->lng->txt('dcl_confirm_storing_records_no_permission');
        }
        $confirmation->setHeaderText($header_text);

        $confirmation->setCancel($this->lng->txt('edit'), 'edit');
        $confirmation->setConfirm($this->lng->txt('save'), 'save');

        $record_data = "";

        $empty_fileuploads = [];
        foreach ($all_fields as $field) {
            $record_field = $record_obj->getRecordField((int) $field->getId());
            /** @var ilDclBaseRecordFieldModel $record_field */
            $record_field->addHiddenItemsToConfirmation($confirmation);

            if ($record_field instanceof ilDclFileRecordFieldModel && $record_field->getValue() == null) {
                $empty_fileuploads['field_' . $field->getId()] = [
                    "name" => "",
                    "type" => "",
                    "tmp_name" => "",
                    "error" => 4,
                    "size" => 0
                ];
            }
            $record_representation = ilDclFieldFactory::getRecordRepresentationInstance($record_field);

            if ($record_representation->getConfirmationHTML() !== '') {
                $record_data .= $field->getTitle() . ": " . $record_representation->getConfirmationHTML() . "<br />";
            }
        }

        $confirmation->addHiddenItem('ilfilehash', $filehash);
        $confirmation->addHiddenItem('empty_fileuploads', htmlspecialchars(json_encode($empty_fileuploads)));
        $confirmation->addHiddenItem('table_id', (string) $this->table_id);
        $confirmation->addHiddenItem('tableview_id', (string) $this->tableview_id);
        $confirmation->addItem('save_confirmed', "1", $record_data);

        if ($this->ctrl->isAsynch()) {
            echo $confirmation->getHTML();
            exit();
        } else {
            $this->tpl->setContent($confirmation->getHTML());
        }
    }

    public function save(): void
    {
        global $DIC;
        $ilAppEventHandler = $DIC['ilAppEventHandler'];

        $this->initForm();

        // if save confirmation is enabled: Temporary file-uploads need to be handled
        $has_save_confirmed = $this->http->wrapper()->post()->has('save_confirmed');
        $has_ilfilehash = $this->http->wrapper()->post()->has('ilfilehash');
        $table_has_save_confirmation = $this->table->getSaveConfirmation();

        $ilfilehash = $has_ilfilehash ? $this->http->wrapper()->post()->retrieve(
            'ilfilehash',
            $this->refinery->kindlyTo()->string()
        ) : '';
        ilDclPropertyFormGUI::rebuildTempFileByHash($ilfilehash);


        if ($table_has_save_confirmation
            && $has_save_confirmed
            && $has_ilfilehash
            && $this->record !== null
            && !$this->ctrl->isAsynch()
        ) {
            $has_empty_fileuploads = $this->http->wrapper()->post()->has('empty_fileuploads');

            //handle empty fileuploads, since $_FILES has to have an entry for each fileuploadGUI
            if ($has_empty_fileuploads) {
                $empty_fileuploads = $this->http->wrapper()->post()->retrieve(
                    'empty_fileuploads',
                    $this->refinery->kindlyTo()->string()
                );
                if (json_decode($empty_fileuploads)) {
                    $_FILES = $_FILES + json_decode($empty_fileuploads, true);
                }
            }
        }

        $valid = $this->form->checkInput();

        $date_obj = new ilDateTime(time(), IL_CAL_UNIX);

        $unchanged_obj = $this->record;
        $this->record->setTableId($this->table_id);
        $this->record->setLastUpdate($date_obj);
        $this->record->setLastEditBy($this->user->getId());

        if (ilObjDataCollectionAccess::hasWriteAccess($this->obj->getRefId()) || $this->record === null) {
            $all_fields = $this->table->getRecordFields();
        } else {
            $all_fields = $this->table->getEditableFields(!$this->record->getId());
        }

        // Check if we can create this record.
        if ($valid) {
            foreach ($all_fields as $field) {
                try {
                    $field->checkValidityFromForm($this->form, $this->record->getId());
                } catch (ilDclInputException $e) {
                    $valid = false;
                    $item = $this->form->getItemByPostVar('field_' . $field->getId());
                    $item->setAlert($e->getMessage());
                }
            }
        }

        if (!$valid) {
            // Form not valid...
            //TODO: URL title flushes on invalid form
            ilDclPropertyFormGUI::rebuildTempFileByHash($ilfilehash);
            $this->form->setValuesByPost();
            $this->tpl->setContent($this->form->getHTML());

            return;
        }

        if ($this->record === null) {
            if (!(ilObjDataCollectionAccess::hasPermissionToAddRecord(
                $this->obj->getRefId(),
                $this->table_id
            ))) {
                $this->accessDenied();

                return;
            }

            if ($table_has_save_confirmation && $this->form->getInput('save_confirmed') == null && !$this->ctrl->isAsynch()) {
                $hash = $this->rebuildUploadsForFileHash($has_ilfilehash);

                //edit values, they are valid we already checked them above
                foreach ($all_fields as $field) {
                    $this->record->setRecordFieldValueFromForm((int) $field->getId(), $this->form);
                }

                $this->saveConfirmation($this->record, $hash);

                return;
            }

            $this->record->setOwner($this->user->getId());
            $this->record->setCreateDate($date_obj);
            $this->record->setTableId($this->table_id);
            $this->record->doCreate();
        } else {
            if (!$this->record->hasPermissionToEdit($this->obj->getRefId())) {
                $this->accessDenied();

                return;
            }
        }

        foreach ($all_fields as $field) {
            $field_setting = $field->getViewSetting($this->tableview_id);

            if ($field_setting->isVisibleInForm($this->record === null) &&
                    (!$field_setting->isLocked($this->record === null) || ilObjDataCollectionAccess::hasWriteAccess($this->obj->getRefId()))) {
                $this->record->setRecordFieldValueFromForm((int) $field->getId(), $this->form);
            } elseif ($this->record === null) {
                $default_value = ilDclTableViewBaseDefaultValue::findSingle(
                    $field_setting->getFieldObject()->getDatatypeId(),
                    $field_setting->getId()
                );
                if ($default_value !== null) {
                    $this->record->setRecordFieldValue($field->getId(), $default_value->getValue());
                }
            }
        }

        if ($this->record !== null && $this->tableview->getFieldSetting('owner')->isVisibleEdit()) {
            if ($this->http->wrapper()->post()->has('field_owner')) {
                $field_owner = $this->http->wrapper()->post()->retrieve(
                    'field_owner',
                    $this->refinery->kindlyTo()->string()
                );
                $owner_id = ilObjUser::_lookupId($field_owner);
                if (!$owner_id) {

                    return;
                }
                $this->record->setOwner($owner_id);
            }
        }

        $dispatchEvent = "update";

        $dispatchEventData = [
                'dcl' => $this->obj,
                'table_id' => $this->table_id,
                'record_id' => $this->record->getId(),
                'record' => $this->record,
        ];

        if ($this->record === null) {
            $dispatchEvent = "create";
            $this->obj->sendRecordNotification(ilDclNotificationType::RECORD_CREATE, $this->record);
        } else {
            $dispatchEventData['prev_record'] = $unchanged_obj;
        }

        $this->record->doUpdate();

        $ilAppEventHandler->raise(
            'components/ILIAS/DataCollection',
            $dispatchEvent . 'Record',
            $dispatchEventData
        );

        $this->ctrl->setParameter($this, "table_id", $this->table_id);
        $this->ctrl->setParameter($this, "tableview_id", $this->tableview_id);
        $this->ctrl->setParameter($this, "record_id", $this->record->getId());

        if (!$this->ctrl->isAsynch()) {
            $this->tpl->setOnScreenMessage('success', $this->lng->txt("msg_obj_modified"), true);
        }
    }

    public function getForm(array $values = []): Form
    {
        $inputs = [];

        $edit = ilObjDataCollectionAccess::hasWriteAccess($this->obj->getRefId());

        $create = !isset($this->record);
        foreach ($this->table->getRecordFields() as $rfield) {
            $field_setting = $rfield->getViewSetting($this->tableview_id);
            if ($field_setting->isVisibleInForm($create)) {
                $field = ilDclCache::getFieldRepresentation($rfield)->getInputField();
                if ($field !== null) {
                    if ($rfield instanceof ilDclCopyFieldModel && !$create) {
                        $value = $this->record->getRecordFieldValue($rfield->getId());
                        if ($value !== '') {
                            $item = ilDclCache::getFieldRepresentation($rfield)->getInputField($value);
                        }
                    }
                    $field = $field->withDisabled($field_setting->isLocked($create) && !$edit);
                    $field = $field->withRequired($field_setting->isRequired($create));
                    $inputs[$rfield->getId()] = $field;
                }
            }
        }

        if ($create) {
            $section = $this->factory->input()->field()->section($inputs, $this->lng->txt('dcl_add_new_record'));
        } else {
            $field_setting = $this->tableview->getFieldSetting('owner');
            if ($field_setting->isVisibleEdit()) {
                $inputs['owner'] = $this->factory->input()->field()->text($this->lng->txt('dcl_owner'))
                    ->withDisabled($field_setting->isLocked($create) && !$edit)
                    ->withRequired(true);
            }
            $section = $this->factory->input()->field()->section($inputs, $this->lng->txt('dcl_update_record'));
        }

        $section = $section->withValue(array_intersect_key($this->getValues(), $section->getInputs()));

        return $this->factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($this, $create ? 'create' : 'save'),
            [$section]
        );
    }

    protected function getValues(): array
    {
        $values = [];

        if (isset($this->record)) {
            foreach ($this->table->getRecordFields() as $field) {
                $rfield = $this->record->getRecordField((int) $field->getId());
                $value = $rfield->getRecordRepresentation()->parseFormInput($rfield->getValue());
                if ($field instanceof ilDclBooleanFieldModel) {
                    $value = (bool) $value;
                }
                if ($field instanceof ilDclFileFieldModel) {
                    $value = $value ? [$rfield->getValue()] : [];
                }
                if ($field instanceof ilDclMobFieldModel) {
                    if ($value > 0) {
                        //$obj = new ilObjMediaObject($value);
                        $value = [];
                    } else {
                        $value = [];
                    }
                }
                if ($field instanceof ilDclIliasReferenceFieldModel) {
                    $value = $value ?: 1;
                }
                $values[$field->getId()] = $value;
            }
            $values['owner'] = ilObjUser::_lookupName($this->record->getOwner())['login'];
        } else {
            foreach ($this->table->getRecordFields() as $field) {
                $value = ilDclTableViewBaseDefaultValue::findSingle(
                    $field->getDatatypeId(),
                    $field->getViewSetting($this->tableview->getId())->getId()
                )?->getValue();
                if ($field instanceof ilDclBooleanFieldModel) {
                    $value = (bool) $value;
                }
                if ($field instanceof ilDclFileFieldModel) {
                    $value = [];//$value ?? [];
                }
                //TODO: This is a temporary fix for 46020: If no node is selected it fallsback to the root node, since there as to be a node selected at anytime...
                if ($field instanceof ilDclIliasReferenceFieldModel) {
                    $value = $value ?? 1;
                }
                $values[$field->getId()] = $value;
            }
        }
        return $values;
    }
}
