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

namespace ILIAS\Dashboard;

use ilDclDatatype;
use ilDclDateFieldModel;
use ilDclDatetimeFieldModel;
use ilDclSelectionFieldModel;
use ilDclSelectionOption;
use ilDclTableView;
use ilDclTableViewBaseDefaultValue;
use ilDclTableViewFieldSetting;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Symbol\Icon\Standard;
use ILIAS\UI\Component\Table\DataRetrieval;
use Generator;
use Closure;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLanguage;
use ilUtil;

use function ILIAS\UI\examples\Deck\user;

class TableviewSettings implements DataRetrieval
{
    public const VALID_DEFAULT_VALUE_TYPES = [
        ilDclDatatype::INPUTFORMAT_NUMBER,
        ilDclDatatype::INPUTFORMAT_TEXT,
        ilDclDatatype::INPUTFORMAT_BOOLEAN,
        ilDclDatatype::INPUTFORMAT_DATE,
        ilDclDatatype::INPUTFORMAT_DATETIME,
        ilDclDatatype::INPUTFORMAT_DATE_SELECTION,
        ilDclDatatype::INPUTFORMAT_DATETIME_SELECTION,
        ilDclDatatype::INPUTFORMAT_TEXT_SELECTION,
        ilDclDatatype::INPUTFORMAT_REFERENCE,
        ilDclDatatype::INPUTFORMAT_COPY,
    ];

    public function __construct(
        private readonly ilDclTableView $tableview,
        private readonly Renderer $renderer,
        private readonly Factory $factory,
        private readonly ilLanguage $lng,
    ) {
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): Generator {
        $visible = $this->lng->txt('dcl_field_visible');
        $required = $this->lng->txt('dcl_field_required');
        $locked = $this->lng->txt('dcl_locked');
        $yes = $this->factory->symbol()->icon()->custom(ilUtil::getImagePath('standard/icon_checked.svg'), 'yes');
        $no = $this->factory->symbol()->icon()->custom(ilUtil::getImagePath('standard/icon_unchecked.svg'), 'no');
        $fields = array_slice($this->tableview->getFieldSettings(), $range->getStart(), $range->getLength());
        foreach ($fields as $field) {
            $data = [];
            $data['title'] = $field->getFieldObject()->getTitle();
            $data['visible'] = $field->isVisibleInList() ? $yes : $no;
            if (!$field->getFieldObject()->isStandardField()) {
                $create = [];
                if ($field->isVisibleCreate()) {
                    $create[] = $visible;
                }
                if ($field->isRequiredCreate()) {
                    $create[] = $required;
                }
                if ($field->isLockedCreate()) {
                    $create[] = $locked;
                }
                $data['create'] = implode(', ', $create);
                if (in_array($field->getFieldObject()->getDatatypeId(), self::VALID_DEFAULT_VALUE_TYPES, true)) {
                    $data['default'] = ilDclTableViewBaseDefaultValue::findSingle($field->getFieldObject()->getDatatypeId(), $field->getId())?->getValue();
                } else {
                    $data['default'] = $this->lng->txt('not_available');
                }
            }
            if (!$field->getFieldObject()->isStandardField() || $field->getField() === 'owner') {
                $edit = [];
                if ($field->isVisibleEdit()) {
                    $edit[] = $visible;
                }
                if ($field->isRequiredEdit()) {
                    $edit[] = $required;
                }
                if ($field->isLockedEdit()) {
                    $edit[] = $locked;
                }
                $data['edit'] = implode(', ', $edit);
            }
            if ($field->getFieldObject()->allowFilterInListView()) {
                $data['filter'] = $field->isInFilter() ? $yes : $no;
                $data['filter_changeable'] = $field->isFilterChangeable() ? $yes : $no;
                $data['filter_default'] = $this->getFilterValue($field);
            } else {
                $data['filter_default'] = $this->lng->txt('not_available');
            }

            yield $row_builder->buildDataRow((string) $field->getId(), $data);
        }
    }

    protected function getFilterValue(ilDclTableViewFieldSetting $field)
    {
        $value = $field->getFilterValue();
        if ($field->getFieldObject() instanceof ilDclSelectionFieldModel) {
            return ilDclSelectionOption::getValues((int) $field->getFieldObject()->getId(), reset($value))[0];
        }

        if ($field->getFieldObject() instanceof ilDclDateFieldModel) {
            global $DIC;
            foreach ($value as $i => $v) {
                $value[$i] = date($DIC->user()->getDateFormat()->toString(), strtotime($v));
            }
        }
        if ($field->getFieldObject() instanceof ilDclDateTimeFieldModel) {
            global $DIC;
            foreach ($value as $i => $v) {
                $value[$i] = date($DIC->user()->getDateTimeFormat()->toString(), strtotime($v));
            }
        }

        $return = reset($value);
        if (implode('', $value) !== '') {
            if (count($value) === 2) {
                $return .= ' - ' . reset($value);
            }
        }

        return $return;
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return count($this->tableview->getFieldSettings());
    }
}
