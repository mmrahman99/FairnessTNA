<?php
/**********************************************************************************
 * This file is part of "FairnessTNA", a Payroll and Time Management program.
 * FairnessTNA is copyright 2013-2017 Aydan Coskun (aydan.ayfer.coskun@gmail.com)
 * others. For full attribution and copyrights details see the COPYRIGHT file.
 *
 * FairnessTNA is free software; you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation, either version 3 of the License, or (at you option )
 * any later version.
 *
 * FairnessTNA is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along
 * with this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *********************************************************************************/


/**
 * @package Modules\Policy
 */
class RegularTimePolicyFactory extends Factory
{
    protected $table = 'regular_time_policy';
    protected $pk_sequence_name = 'regular_time_policy_id_seq'; //PK Sequence name

    protected $company_obj = null;
    protected $contributing_shift_policy_obj = null;
    protected $pay_code_obj = null;

    public function _getFactoryOptions($name, $parent = null)
    {
        $retval = null;
        switch ($name) {
            case 'branch_selection_type':
                $retval = array(
                    10 => TTi18n::gettext('All Branches'),
                    20 => TTi18n::gettext('Only Selected Branches'),
                    30 => TTi18n::gettext('All Except Selected Branches'),
                );
                break;
            case 'department_selection_type':
                $retval = array(
                    10 => TTi18n::gettext('All Departments'),
                    20 => TTi18n::gettext('Only Selected Departments'),
                    30 => TTi18n::gettext('All Except Selected Departments'),
                );
                break;
            case 'job_group_selection_type':
                $retval = array(
                    10 => TTi18n::gettext('All Job Groups'),
                    20 => TTi18n::gettext('Only Selected Job Groups'),
                    30 => TTi18n::gettext('All Except Selected Job Groups'),
                );
                break;
            case 'job_selection_type':
                $retval = array(
                    10 => TTi18n::gettext('All Jobs'),
                    20 => TTi18n::gettext('Only Selected Jobs'),
                    30 => TTi18n::gettext('All Except Selected Jobs'),
                );
                break;
            case 'job_item_group_selection_type':
                $retval = array(
                    10 => TTi18n::gettext('All Task Groups'),
                    20 => TTi18n::gettext('Only Selected Task Groups'),
                    30 => TTi18n::gettext('All Except Selected Task Groups'),
                );
                break;
            case 'job_item_selection_type':
                $retval = array(
                    10 => TTi18n::gettext('All Tasks'),
                    20 => TTi18n::gettext('Only Selected Tasks'),
                    30 => TTi18n::gettext('All Except Selected Tasks'),
                );
                break;
            case 'columns':
                $retval = array(
                    '-1010-name' => TTi18n::gettext('Name'),
                    '-1020-description' => TTi18n::gettext('Description'),

                    '-1030-calculation_order' => TTi18n::gettext('Calculation Order'),

                    '-1900-in_use' => TTi18n::gettext('In Use'),

                    '-2000-created_by' => TTi18n::gettext('Created By'),
                    '-2010-created_date' => TTi18n::gettext('Created Date'),
                    '-2020-updated_by' => TTi18n::gettext('Updated By'),
                    '-2030-updated_date' => TTi18n::gettext('Updated Date'),
                );
                break;
            case 'list_columns':
                $retval = Misc::arrayIntersectByKey($this->getOptions('default_display_columns'), Misc::trimSortPrefix($this->getOptions('columns')));
                break;
            case 'default_display_columns': //Columns that are displayed by default.
                $retval = array(
                    'name',
                    'description',
                    'updated_date',
                    'updated_by',
                );
                break;
            case 'unique_columns': //Columns that are unique, and disabled for mass editing.
                $retval = array(
                    'name',
                );
                break;
            case 'linked_columns': //Columns that are linked together, mainly for Mass Edit, if one changes, they all must.
                $retval = array();
                break;

        }

        return $retval;
    }

    public function _getVariableToFunctionMap($data)
    {
        $variable_function_map = array(
            'id' => 'ID',
            'company_id' => 'Company',
            'name' => 'Name',
            'description' => 'Description',

            'contributing_shift_policy_id' => 'ContributingShiftPolicy',
            'contributing_shift_policy' => false,

            'pay_code_id' => 'PayCode',
            'pay_code' => false,
            'pay_formula_policy_id' => 'PayFormulaPolicy',
            'pay_formula_policy' => false,

            'calculation_order' => 'CalculationOrder',

            'branch' => 'Branch',
            'branch_selection_type_id' => 'BranchSelectionType',
            'branch_selection_type' => false,
            'exclude_default_branch' => 'ExcludeDefaultBranch',
            'department' => 'Department',
            'department_selection_type_id' => 'DepartmentSelectionType',
            'department_selection_type' => false,
            'exclude_default_department' => 'ExcludeDefaultDepartment',
            'job_group' => 'JobGroup',
            'job_group_selection_type_id' => 'JobGroupSelectionType',
            'job_group_selection_type' => false,
            'job' => 'Job',
            'job_selection_type_id' => 'JobSelectionType',
            'job_selection_type' => false,
            'exclude_default_job' => 'ExcludeDefaultJob',
            'job_item_group' => 'JobItemGroup',
            'job_item_group_selection_type_id' => 'JobItemGroupSelectionType',
            'job_item_group_selection_type' => false,
            'job_item' => 'JobItem',
            'job_item_selection_type_id' => 'JobItemSelectionType',
            'job_item_selection_type' => false,
            'exclude_default_job_item' => 'ExcludeDefaultJobItem',

            'in_use' => false,
            'deleted' => 'Deleted',
        );
        return $variable_function_map;
    }

    public function getCompanyObject()
    {
        return $this->getGenericObject('CompanyListFactory', $this->getCompany(), 'company_obj');
    }

    public function getCompany()
    {
        if (isset($this->data['company_id'])) {
            return (int)$this->data['company_id'];
        }

        return false;
    }

    public function getContributingShiftPolicyObject()
    {
        return $this->getGenericObject('ContributingShiftPolicyListFactory', $this->getContributingShiftPolicy(), 'contributing_shift_policy_obj');
    }

    public function getContributingShiftPolicy()
    {
        if (isset($this->data['contributing_shift_policy_id'])) {
            return (int)$this->data['contributing_shift_policy_id'];
        }

        return false;
    }

    public function setCompany($id)
    {
        $id = trim($id);

        Debug::Text('Company ID: ' . $id, __FILE__, __LINE__, __METHOD__, 10);
        $clf = TTnew('CompanyListFactory');

        if ($this->Validator->isResultSetWithRows('company',
            $clf->getByID($id),
            TTi18n::gettext('Company is invalid')
        )
        ) {
            $this->data['company_id'] = $id;

            return true;
        }

        return false;
    }

    public function setName($name)
    {
        $name = trim($name);
        if ($this->Validator->isLength('name',
                $name,
                TTi18n::gettext('Name is too short or too long'),
                2, 50)
            and
            $this->Validator->isTrue('name',
                $this->isUniqueName($name),
                TTi18n::gettext('Name is already in use'))
        ) {
            $this->data['name'] = $name;

            return true;
        }

        return false;
    }

    public function isUniqueName($name)
    {
        $name = trim($name);
        if ($name == '') {
            return false;
        }

        $ph = array(
            'company_id' => (int)$this->getCompany(),
            'name' => TTi18n::strtolower($name),
        );

        $query = 'select id from ' . $this->getTable() . ' where company_id = ? AND lower(name) = ? AND deleted=0';
        $id = $this->db->GetOne($query, $ph);
        Debug::Arr($id, 'Unique: ' . $name, __FILE__, __LINE__, __METHOD__, 10);

        if ($id === false) {
            return true;
        } else {
            if ($id == $this->getId()) {
                return true;
            }
        }

        return false;
    }

    public function getDescription()
    {
        if (isset($this->data['description'])) {
            return $this->data['description'];
        }

        return false;
    }

    public function setDescription($description)
    {
        $description = trim($description);

        if ($description == ''
            or $this->Validator->isLength('description',
                $description,
                TTi18n::gettext('Description is invalid'),
                1, 250)
        ) {
            $this->data['description'] = $description;

            return true;
        }

        return false;
    }

    public function setContributingShiftPolicy($id)
    {
        $id = trim($id);

        $csplf = TTnew('ContributingShiftPolicyListFactory');

        if (
        $this->Validator->isResultSetWithRows('contributing_shift_policy_id',
            $csplf->getByID($id),
            TTi18n::gettext('Contributing Shift Policy is invalid')
        )
        ) {
            $this->data['contributing_shift_policy_id'] = $id;

            return true;
        }

        return false;
    }

    public function setPayCode($id)
    {
        $id = trim($id);

        if ($id == '' or empty($id)) {
            $id = 0;
        }

        $pclf = TTnew('PayCodeListFactory');

        if ($id == 0
            or
            $this->Validator->isResultSetWithRows('pay_code_id',
                $pclf->getById($id),
                TTi18n::gettext('Invalid Pay Code')
            )
        ) {
            $this->data['pay_code_id'] = $id;

            return true;
        }

        return false;
    }

    public function setPayFormulaPolicy($id)
    {
        $id = trim($id);

        if ($id == '' or empty($id)) {
            $id = 0;
        }

        $pfplf = TTnew('PayFormulaPolicyListFactory');

        if ($id == 0
            or
            $this->Validator->isResultSetWithRows('pay_formula_policy_id',
                $pfplf->getByID($id),
                TTi18n::gettext('Pay Formula Policy is invalid')
            )
        ) {
            $this->data['pay_formula_policy_id'] = $id;

            return true;
        }

        return false;
    }

    public function getCalculationOrder()
    {
        if (isset($this->data['calculation_order'])) {
            return $this->data['calculation_order'];
        }

        return false;
    }

    public function setCalculationOrder($value)
    {
        $value = trim($value);

        if ($this->Validator->isNumeric('calculation_order',
            $value,
            TTi18n::gettext('Invalid Calculation Order')
        )
        ) {
            $this->data['calculation_order'] = $value;

            return true;
        }

        return false;
    }

    public function getBranchSelectionType()
    {
        if (isset($this->data['branch_selection_type_id'])) {
            return (int)$this->data['branch_selection_type_id'];
        }

        return false;
    }

    public function setBranchSelectionType($value)
    {
        $value = (int)trim($value);

        if ($value == 0
            or $this->Validator->inArrayKey('branch_selection_type',
                $value,
                TTi18n::gettext('Incorrect Branch Selection Type'),
                $this->getOptions('branch_selection_type'))
        ) {
            $this->data['branch_selection_type_id'] = $value;

            return true;
        }

        return false;
    }

    //Last regular time policy gets all remaining worked time.

    public function getExcludeDefaultBranch()
    {
        if (isset($this->data['exclude_default_branch'])) {
            return $this->fromBool($this->data['exclude_default_branch']);
        }

        return false;
    }

    public function setExcludeDefaultBranch($bool)
    {
        $this->data['exclude_default_branch'] = $this->toBool($bool);

        return true;
    }

    /*

    Branch/Department/Job/Task filter functions

    */

    public function getBranch()
    {
        return CompanyGenericMapListFactory::getArrayByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 581, $this->getID());
    }

    public function setBranch($ids)
    {
        Debug::text('Setting Branch IDs : ', __FILE__, __LINE__, __METHOD__, 10);
        return CompanyGenericMapFactory::setMapIDs($this->getCompany(), 581, $this->getID(), (array)$ids);
    }

    public function getDepartmentSelectionType()
    {
        if (isset($this->data['department_selection_type_id'])) {
            return (int)$this->data['department_selection_type_id'];
        }

        return false;
    }

    public function setDepartmentSelectionType($value)
    {
        $value = (int)trim($value);

        if ($value == 0
            or $this->Validator->inArrayKey('department_selection_type',
                $value,
                TTi18n::gettext('Incorrect Department Selection Type'),
                $this->getOptions('department_selection_type'))
        ) {
            $this->data['department_selection_type_id'] = $value;

            return true;
        }

        return false;
    }

    public function getExcludeDefaultDepartment()
    {
        if (isset($this->data['exclude_default_department'])) {
            return $this->fromBool($this->data['exclude_default_department']);
        }

        return false;
    }

    public function setExcludeDefaultDepartment($bool)
    {
        $this->data['exclude_default_department'] = $this->toBool($bool);

        return true;
    }

    public function getDepartment()
    {
        return CompanyGenericMapListFactory::getArrayByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 582, $this->getID());
    }

    public function setDepartment($ids)
    {
        Debug::text('Setting Department IDs : ', __FILE__, __LINE__, __METHOD__, 10);
        return CompanyGenericMapFactory::setMapIDs($this->getCompany(), 582, $this->getID(), (array)$ids);
    }

    public function getJobGroupSelectionType()
    {
        if (isset($this->data['job_group_selection_type_id'])) {
            return (int)$this->data['job_group_selection_type_id'];
        }

        return false;
    }

    public function setJobGroupSelectionType($value)
    {
        $value = (int)trim($value);

        if ($value == 0
            or $this->Validator->inArrayKey('job_group_selection_type',
                $value,
                TTi18n::gettext('Incorrect Job Group Selection Type'),
                $this->getOptions('job_group_selection_type'))
        ) {
            $this->data['job_group_selection_type_id'] = $value;

            return true;
        }

        return false;
    }

    public function getJobGroup()
    {
        return CompanyGenericMapListFactory::getArrayByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 583, $this->getID());
    }

    public function setJobGroup($ids)
    {
        Debug::text('Setting Job Group IDs : ', __FILE__, __LINE__, __METHOD__, 10);
        return CompanyGenericMapFactory::setMapIDs($this->getCompany(), 583, $this->getID(), (array)$ids);
    }

    public function getJobSelectionType()
    {
        if (isset($this->data['job_selection_type_id'])) {
            return (int)$this->data['job_selection_type_id'];
        }

        return false;
    }

    public function setJobSelectionType($value)
    {
        $value = (int)trim($value);

        if ($value == 0
            or $this->Validator->inArrayKey('job_selection_type',
                $value,
                TTi18n::gettext('Incorrect Job Selection Type'),
                $this->getOptions('job_selection_type'))
        ) {
            $this->data['job_selection_type_id'] = $value;

            return true;
        }

        return false;
    }

    public function getJob()
    {
        return CompanyGenericMapListFactory::getArrayByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 584, $this->getID());
    }

    public function setJob($ids)
    {
        Debug::text('Setting Job IDs : ', __FILE__, __LINE__, __METHOD__, 10);
        return CompanyGenericMapFactory::setMapIDs($this->getCompany(), 584, $this->getID(), (array)$ids);
    }

    public function getExcludeDefaultJob()
    {
        if (isset($this->data['exclude_default_job'])) {
            return $this->fromBool($this->data['exclude_default_job']);
        }

        return false;
    }

    public function setExcludeDefaultJob($bool)
    {
        $this->data['exclude_default_job'] = $this->toBool($bool);

        return true;
    }

    public function getJobItemGroupSelectionType()
    {
        if (isset($this->data['job_item_group_selection_type_id'])) {
            return (int)$this->data['job_item_group_selection_type_id'];
        }

        return false;
    }

    public function setJobItemGroupSelectionType($value)
    {
        $value = (int)trim($value);

        if ($value == 0
            or $this->Validator->inArrayKey('job_item_group_selection_type',
                $value,
                TTi18n::gettext('Incorrect Task Group Selection Type'),
                $this->getOptions('job_item_group_selection_type'))
        ) {
            $this->data['job_item_group_selection_type_id'] = $value;

            return true;
        }

        return false;
    }

    public function getJobItemGroup()
    {
        return CompanyGenericMapListFactory::getArrayByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 585, $this->getID());
    }

    public function setJobItemGroup($ids)
    {
        Debug::text('Setting Task Group IDs : ', __FILE__, __LINE__, __METHOD__, 10);
        return CompanyGenericMapFactory::setMapIDs($this->getCompany(), 585, $this->getID(), (array)$ids);
    }

    public function getJobItemSelectionType()
    {
        if (isset($this->data['job_item_selection_type_id'])) {
            return (int)$this->data['job_item_selection_type_id'];
        }

        return false;
    }

    public function setJobItemSelectionType($value)
    {
        $value = (int)trim($value);

        if ($value == 0
            or $this->Validator->inArrayKey('job_item_selection_type',
                $value,
                TTi18n::gettext('Incorrect Task Selection Type'),
                $this->getOptions('job_item_selection_type'))
        ) {
            $this->data['job_item_selection_type_id'] = $value;

            return true;
        }

        return false;
    }

    public function getJobItem()
    {
        return CompanyGenericMapListFactory::getArrayByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 586, $this->getID());
    }

    public function setJobItem($ids)
    {
        Debug::text('Setting Task IDs : ', __FILE__, __LINE__, __METHOD__, 10);
        return CompanyGenericMapFactory::setMapIDs($this->getCompany(), 586, $this->getID(), (array)$ids);
    }

    public function getExcludeDefaultJobItem()
    {
        if (isset($this->data['exclude_default_job_item'])) {
            return $this->fromBool($this->data['exclude_default_job_item']);
        }

        return false;
    }

    public function setExcludeDefaultJobItem($bool)
    {
        $this->data['exclude_default_job_item'] = $this->toBool($bool);

        return true;
    }

    public function Validate($ignore_warning = true)
    {
        if ($this->getDeleted() != true and $this->Validator->getValidateOnly() == false) { //Don't check the below when mass editing.
            if ($this->getName() == '') {
                $this->Validator->isTRUE('name',
                    false,
                    TTi18n::gettext('Please specify a name'));
            }

            if ($this->getPayCode() == 0) {
                $this->Validator->isTRUE('pay_code_id',
                    false,
                    TTi18n::gettext('Please choose a Pay Code'));
            }

            //Make sure Pay Formula Policy is defined somewhere.
            if ($this->getPayFormulaPolicy() == 0 and $this->getPayCode() > 0 and (!is_object($this->getPayCodeObject()) or (is_object($this->getPayCodeObject()) and $this->getPayCodeObject()->getPayFormulaPolicy() == 0))) {
                $this->Validator->isTRUE('pay_formula_policy_id',
                    false,
                    TTi18n::gettext('Selected Pay Code does not have a Pay Formula Policy defined'));
            }
        }

        if ($this->getDeleted() == true) {
            //Check to make sure nothing else references this policy, so we can be sure its okay to delete it.
            $pglf = TTnew('PolicyGroupListFactory');
            $pglf->getAPISearchByCompanyIdAndArrayCriteria($this->getCompany(), array('regular_time_policy' => $this->getId()), 1);
            if ($pglf->getRecordCount() > 0) {
                $this->Validator->isTRUE('in_use',
                    false,
                    TTi18n::gettext('This policy is currently in use') . ' ' . TTi18n::gettext('by policy groups'));
            }
        }

        return true;
    }

    public function getName()
    {
        if (isset($this->data['name'])) {
            return $this->data['name'];
        }

        return false;
    }

    public function getPayCode()
    {
        if (isset($this->data['pay_code_id'])) {
            return (int)$this->data['pay_code_id'];
        }

        return false;
    }

    public function getPayFormulaPolicy()
    {
        if (isset($this->data['pay_formula_policy_id'])) {
            return (int)$this->data['pay_formula_policy_id'];
        }

        return false;
    }

    public function getPayCodeObject()
    {
        return $this->getGenericObject('PayCodeListFactory', $this->getPayCode(), 'pay_code_obj');
    }

    public function preSave()
    {
        return true;
    }

    public function postSave()
    {
        $this->removeCache($this->getId());

        return true;
    }

    public function setObjectFromArray($data)
    {
        if (is_array($data)) {
            $variable_function_map = $this->getVariableToFunctionMap();
            foreach ($variable_function_map as $key => $function) {
                if (isset($data[$key])) {
                    $function = 'set' . $function;
                    switch ($key) {
                        default:
                            if (method_exists($this, $function)) {
                                $this->$function($data[$key]);
                            }
                            break;
                    }
                }
            }

            $this->setCreatedAndUpdatedColumns($data);

            return true;
        }

        return false;
    }

    public function getObjectAsArray($include_columns = null)
    {
        $data = array();
        $variable_function_map = $this->getVariableToFunctionMap();
        if (is_array($variable_function_map)) {
            foreach ($variable_function_map as $variable => $function_stub) {
                if ($include_columns == null or (isset($include_columns[$variable]) and $include_columns[$variable] == true)) {
                    $function = 'get' . $function_stub;
                    switch ($variable) {
                        case 'in_use':
                            $data[$variable] = $this->getColumn($variable);
                            break;
                        default:
                            if (method_exists($this, $function)) {
                                $data[$variable] = $this->$function();
                            }
                            break;
                    }
                }
            }
            $this->getCreatedAndUpdatedColumns($data, $include_columns);
        }

        return $data;
    }

    public function addLog($log_action)
    {
        return TTLog::addEntry($this->getId(), $log_action, TTi18n::getText('Regular Time Policy'), null, $this->getTable(), $this);
    }
}
