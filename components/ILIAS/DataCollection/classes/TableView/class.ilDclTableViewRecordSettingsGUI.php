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

use ILIAS\Dashboard\TableviewSettings;
use ILIAS\UI\Component\Input\Container\Form\Form;
use ILIAS\UI\Component\Table\Data;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;

/**
 * @ilCtrl_IsCalledBy ilDclTableViewRecordSettingsGUI: ilDclTableViewEditGUI
 */
class ilDclTableViewRecordSettingsGUI
{
    protected ilCtrl $ctrl;
    protected ilLanguage $lng;
    protected ilGlobalTemplateInterface $tpl;
    public ilDclTableView $tableview;
    public ilDclTable $table;
    protected ILIAS\HTTP\Services $http;
    private Factory $factory;
    private Renderer $renderer;
    /** @var int[] */
    private array $available_roles = [];

    public function __construct(ilDclTableView $tableview)
    {
        global $DIC;
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->tableview = $tableview;
        $this->http = $DIC->http();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
    }

    public function executeCommand(): void
    {
        $form = $this->getForm();
        switch ($this->ctrl->getCmd('show')) {
            case 'edit':
                $this->tpl->setContent($this->renderer->render($form));
                break;
            case 'save':
                $form = $form->withRequest($this->http->request());
                $data = $form->getData();
                if ($data !== null) {
                    $this->tpl->setOnScreenMessage($this->tpl::MESSAGE_TYPE_SUCCESS, $this->lng->txt('dcl_msg_tableview_updated'));
                }
                //no break
            case 'show':
            default:
                $this->tpl->setContent($this->renderer->render([
                    $this->factory->button()->standard($this->lng->txt('dcl_edit_record_settings'), $this->ctrl->getLinkTarget($this, 'edit')),
                    $this->getTable()->withRequest($this->http->request())
                ]));
        }
    }

    protected function getTable(): Data
    {
        return $this->factory->table()->data(
            new TableviewSettings($this->tableview, $this->renderer, $this->factory, $this->lng),
            $this->lng->txt('settings'),
            [
                'title' => $this->factory->table()->column()->text($this->lng->txt('dcl_fieldtitle'))->withIsSortable(false),
                'visible' => $this->factory->table()->column()->statusIcon($this->lng->txt('visible'))->withIsSortable(false),
                'create' => $this->factory->table()->column()->text($this->lng->txt('create'))->withIsSortable(false)->withIsOptional(true),
                'default' => $this->factory->table()->column()->text($this->lng->txt('dcl_tableview_default_value'))->withIsSortable(false)->withIsOptional(true),
                'edit' => $this->factory->table()->column()->text($this->lng->txt('edit'))->withIsSortable(false)->withIsOptional(true),
                'filter' => $this->factory->table()->column()->statusIcon($this->lng->txt('dcl_filter'))->withIsSortable(false)->withIsOptional(true),
                'filter_changeable' => $this->factory->table()->column()->statusIcon($this->lng->txt('dcl_filter_changeable'))->withIsSortable(false)->withIsOptional(true),
                'filter_default' => $this->factory->table()->column()->text($this->lng->txt('dcl_std_filter'))->withIsSortable(false)->withIsOptional(true),
            ]
        );
    }

    protected function getForm(): Form
    {
        $inputs = [];
        foreach ($this->tableview->getFieldSettings() as $field) {
            $properties = [];
            if (!$field->getFieldObject()->isStandardField()) {
                switch ($field->getFieldObject()->getDatatypeId()) {
                    case ilDclDatatype::INPUTFORMAT_TEXT:
                        $properties['default'] = $this->factory->input()->field()
                            ->text($this->lng->txt('dcl_tableview_default_value'))
                            ->withValue($field->getDefaultValue() ?? '');
                        break;
                    case ilDclDatatype::INPUTFORMAT_NUMBER:
                        $properties['default'] = $this->factory->input()->field()
                            ->numeric($this->lng->txt('dcl_tableview_default_value'))
                            ->withValue($field->getDefaultValue() ?? 0);
                        break;
                    case ilDclDatatype::INPUTFORMAT_BOOLEAN:
                        $properties['default'] = $this->factory->input()->field()
                            ->checkbox($this->lng->txt('dcl_tableview_default_value'))
                            ->withValue($field->getDefaultValue() === '1');
                        $default = $field->getDefaultValue();
                        $default = $field->getDefaultValue();
                        break;
                    default:
                }
                $properties['create'] = $this->factory->input()->field()->optionalGroup(
                    [
                        'required' => $this->factory->input()->field()->checkbox($this->lng->txt('dcl_required')),
                        'locked' => $this->factory->input()->field()->checkbox($this->lng->txt('dcl_locked')),
                    ],
                    $this->lng->txt('dcl_create_visibility'),
                )->withValue($field->isVisibleCreate() ? ['required' => $field->isRequiredCreate(), 'locked' => $field->isLockedCreate()] : null);
            }
            if (!$field->getFieldObject()->isStandardField() || $field->getField() === 'owner') {
                $properties['edit'] = $this->factory->input()->field()->optionalGroup(
                    [
                        'required' => $this->factory->input()->field()->checkbox($this->lng->txt('dcl_required')),
                        'locked' => $this->factory->input()->field()->checkbox($this->lng->txt('dcl_locked')),
                    ],
                    $this->lng->txt('dcl_edit_visibility'),
                )->withValue($field->isVisibleEdit() ? ['required' => $field->isRequiredEdit(), 'locked' => $field->isLockedEdit()] : null);
            }
            $properties['visible'] = $this->factory->input()->field()->checkbox($this->lng->txt('dcl_visible'))->withValue($field->isVisibleInList());
            if ($field->getFieldObject()->allowFilterInListView()) {
                $properties['filter_default'] = $this->factory->input()->field()->text($this->lng->txt('dcl_std_filter'))
                    ->withValue('');
                $properties['filter'] = $this->factory->input()->field()->optionalGroup(
                    [
                        'change' => $this->factory->input()->field()->checkbox($this->lng->txt('dcl_filter_changeable')),
                    ],
                    $this->lng->txt('dcl_filter')
                )->withValue($field->isInFilter() ? ['change' => $field->isFilterChangeable()] : null);
            }
            $inputs[$field->getId()] = $this->factory->input()->field()->section($properties, $field->getFieldObject()->getTitle());
        }

        return $this->factory->input()->container()->form()->standard($this->ctrl->getFormAction($this, 'save'), $inputs);
    }

    //TODO Refactor for new UI
    protected function getStandardFilterHTML(ilDclBaseFieldModel $field, array $value): string
    {
        $field_representation = ilDclFieldFactory::getFieldRepresentationInstance($field);
        $field_representation->addFilterInputFieldToTable($this);
        $filter = end($this->filters);
        $this->filters = [];
        $filter->setValueByArray($value);

        return $filter->render();
    }
}
