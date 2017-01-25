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
 * @package Modules\Users
 */
class UserTitleFactory extends Factory
{
    protected $table = 'user_title';
    protected $pk_sequence_name = 'user_title_id_seq'; //PK Sequence name

    public function _getFactoryOptions($name, $parent = null)
    {
        $retval = null;
        switch ($name) {
            case 'columns':
                $retval = array(
                    '-1000-name' => TTi18n::gettext('Name'),

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
                    'created_by',
                    'created_date',
                    'updated_by',
                    'updated_date',
                );
                break;
            case 'unique_columns': //Columns that are unique, and disabled for mass editing.
                $retval = array(
                    'name',
                );
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
            'other_id1' => 'OtherID1',
            'other_id2' => 'OtherID2',
            'other_id3' => 'OtherID3',
            'other_id4' => 'OtherID4',
            'other_id5' => 'OtherID5',
            'deleted' => 'Deleted',
        );
        return $variable_function_map;
    }

    public function setCompany($id)
    {
        $id = trim($id);

        $clf = TTnew('CompanyListFactory');

        if ($id == 0
            or $this->Validator->isResultSetWithRows('company',
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
                2,
                100)
            and
            $this->Validator->isTrue('name',
                $this->isUniqueName($name),
                TTi18n::gettext('Title already exists'))

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

        $query = 'select id from ' . $this->table . '
					where company_id = ?
						AND lower(name) = ?
						AND deleted = 0';
        $name_id = $this->db->GetOne($query, $ph);
        Debug::Arr($name_id, 'Unique Name: ' . $name, __FILE__, __LINE__, __METHOD__, 10);

        if ($name_id === false) {
            return true;
        } else {
            if ($name_id == $this->getId()) {
                return true;
            }
        }

        return false;
    }

    public function getCompany()
    {
        if (isset($this->data['company_id'])) {
            return (int)$this->data['company_id'];
        }
        return false;
    }

    public function getOtherID1()
    {
        if (isset($this->data['other_id1'])) {
            return $this->data['other_id1'];
        }

        return false;
    }

    public function setOtherID1($value)
    {
        $value = trim($value);

        if ($value == ''
            or
            $this->Validator->isLength('other_id1',
                $value,
                TTi18n::gettext('Other ID 1 is invalid'),
                1, 255)
        ) {
            $this->data['other_id1'] = $value;

            return true;
        }

        return false;
    }

    public function getOtherID2()
    {
        if (isset($this->data['other_id2'])) {
            return $this->data['other_id2'];
        }

        return false;
    }

    public function setOtherID2($value)
    {
        $value = trim($value);

        if ($value == ''
            or
            $this->Validator->isLength('other_id2',
                $value,
                TTi18n::gettext('Other ID 2 is invalid'),
                1, 255)
        ) {
            $this->data['other_id2'] = $value;

            return true;
        }

        return false;
    }

    public function getOtherID3()
    {
        if (isset($this->data['other_id3'])) {
            return $this->data['other_id3'];
        }

        return false;
    }

    public function setOtherID3($value)
    {
        $value = trim($value);

        if ($value == ''
            or
            $this->Validator->isLength('other_id3',
                $value,
                TTi18n::gettext('Other ID 3 is invalid'),
                1, 255)
        ) {
            $this->data['other_id3'] = $value;

            return true;
        }

        return false;
    }

    public function getOtherID4()
    {
        if (isset($this->data['other_id4'])) {
            return $this->data['other_id4'];
        }

        return false;
    }

    public function setOtherID4($value)
    {
        $value = trim($value);

        if ($value == ''
            or
            $this->Validator->isLength('other_id4',
                $value,
                TTi18n::gettext('Other ID 4 is invalid'),
                1, 255)
        ) {
            $this->data['other_id4'] = $value;

            return true;
        }

        return false;
    }

    public function getOtherID5()
    {
        if (isset($this->data['other_id5'])) {
            return $this->data['other_id5'];
        }

        return false;
    }

    public function setOtherID5($value)
    {
        $value = trim($value);

        if ($value == ''
            or
            $this->Validator->isLength('other_id5',
                $value,
                TTi18n::gettext('Other ID 5 is invalid'),
                1, 255)
        ) {
            $this->data['other_id5'] = $value;

            return true;
        }

        return false;
    }

    public function Validate($ignore_warning = true)
    {
        if ($this->getName() == '') {
            $this->Validator->isTRUE('name',
                false,
                TTi18n::gettext('Name not specified'));
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

    public function postSave()
    {
        if ($this->getDeleted() == true) {
            Debug::Text('UnAssign title from employees: ' . $this->getId(), __FILE__, __LINE__, __METHOD__, 10);
            //Unassign hours from this job.
            $uf = TTnew('UserFactory');
            $udf = TTnew('UserDefaultFactory');

            $query = 'update ' . $uf->getTable() . ' set title_id = 0 where company_id = ' . (int)$this->getCompany() . ' AND title_id = ' . (int)$this->getId();
            $this->db->Execute($query);

            $query = 'update ' . $udf->getTable() . ' set title_id = 0 where company_id = ' . (int)$this->getCompany() . ' AND title_id = ' . (int)$this->getId();
            $this->db->Execute($query);
        }
    }

    //Support setting created_by, updated_by especially for importing data.
    //Make sure data is set based on the getVariableToFunctionMap order.
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
        return TTLog::addEntry($this->getId(), $log_action, TTi18n::getText('Employee Title'), null, $this->getTable(), $this);
    }
}
