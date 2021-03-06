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
 * @package Modules\Qualification
 */
class QualificationFactory extends Factory
{
    protected $table = 'qualification';
    protected $pk_sequence_name = 'qualification_id_seq'; //PK Sequence name

    protected $company_obj = null;
    protected $tmp_data = null;

    public function _getFactoryOptions($name, $parent = null)
    {
        $retval = null;
        switch ($name) {
            case 'type':
                $retval = array(
                    10 => TTi18n::gettext('Skill'),
                    20 => TTi18n::gettext('Education'),
                    30 => TTi18n::gettext('License'),
                    40 => TTi18n::gettext('Language'),
                    50 => TTi18n::gettext('Membership')
                );
                break;
            case 'columns':
                $retval = array(
                    '-1030-name' => TTi18n::gettext('Name'),
                    '-1040-description' => TTi18n::getText('Description'),
                    '-1050-type' => TTi18n::getText('Type'),

                    '-2040-group' => TTi18n::gettext('Group'),
                    '-1300-tag' => TTi18n::gettext('Tags'),

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
                    'type',
                    'name',
                    'description',
                );
                break;
            case 'unique_columns': //Columns that are unique, and disabled for mass editing.
                $retval = array(
                    'name'
                );
                break;
        }

        return $retval;
    }

    public function _getVariableToFunctionMap($data)
    {
        $variable_function_map = array(
            'id' => 'ID',
            'type_id' => 'Type',
            'type' => false,
            'company_id' => 'Company',
            'group_id' => 'Group',
            'group' => false,
            'name' => 'Name',
            'name_metaphone' => 'NameMetaphone',
            'description' => 'Description',

            'tag' => 'Tag',

            'deleted' => 'Deleted',
        );
        return $variable_function_map;
    }

    public function getType()
    {
        if (isset($this->data['type_id'])) {
            return (int)$this->data['type_id'];
        }
        return false;
    }

    public function setType($type_id)
    {
        $type_id = trim($type_id);

        if ($this->Validator->inArrayKey('type_id',
            $type_id,
            TTi18n::gettext('Type is invalid'),
            $this->getOptions('type'))
        ) {
            $this->data['type_id'] = $type_id;

            return true;
        }

        return false;
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

    public function setCompany($id)
    {
        $id = trim($id);

        $clf = TTnew('CompanyListFactory');

        if ($id == 0
            or $this->Validator->isResultSetWithRows('company_id',
                $clf->getByID($id),
                TTi18n::gettext('Company is invalid')
            )
        ) {
            $this->data['company_id'] = $id;

            return true;
        }

        return false;
    }

    public function getGroup()
    {
        if (isset($this->data['group_id'])) {
            return (int)$this->data['group_id'];
        }

        return false;
    }

    public function setGroup($id)
    {
        $id = (int)trim($id);

        Debug::Text('Group ID: ' . $id, __FILE__, __LINE__, __METHOD__, 10);
        $qglf = TTnew('QualificationGroupListFactory');
        if ($id == 0
            or
            $this->Validator->isResultSetWithRows('group_id',
                $qglf->getByID($id),
                TTi18n::gettext('Group is invalid')
            )
        ) {
            $this->data['group_id'] = $id;

            return true;
        }

        return false;
    }

    public function setName($name)
    {
        $name = trim($name);

        if ($this->Validator->isLength('name',
                $name,
                TTi18n::gettext('Qualification name is invalid'),
                1)
            and
            $this->Validator->isTrue('name',
                $this->isUniqueName($name),
                TTi18n::gettext('Qualification name already exists'))
        ) {
            $this->data['name'] = $name;

            $this->setNameMetaphone($name);

            return true;
        }

        return false;
    }

    public function isUniqueName($name)
    {
        if ($this->getCompany() == false) {
            return false;
        }

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

    public function setNameMetaphone($value)
    {
        $value = metaphone(trim($value));

        if ($value != '') {
            $this->data['name_metaphone'] = $value;

            return true;
        }

        return false;
    }

    public function getNameMetaphone()
    {
        if (isset($this->data['name_metaphone'])) {
            return $this->data['name_metaphone'];
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
            or
            $this->Validator->isLength('description',
                $description,
                TTi18n::gettext('Description is invalid'),
                2, 255)
        ) {
            $this->data['description'] = $description;

            return true;
        }

        return false;
    }

    public function setTag($tags)
    {
        $tags = trim($tags);

        //Save the tags in temporary memory to be committed in postSave()
        $this->tmp_data['tags'] = $tags;

        return true;
    }

    public function Validate($ignore_warning = true)
    {
        //$this->setProvince( $this->getProvince() ); //Not sure why this was there, but it causes duplicate errors if the province is incorrect.

        return true;
    }

    public function preSave()
    {
        return true;
    }

    public function postSave()
    {
        $this->removeCache($this->getId());

        if ($this->getDeleted() == false) {
            Debug::text('Setting Tags...', __FILE__, __LINE__, __METHOD__, 10);
            CompanyGenericTagMapFactory::setTags($this->getCompany(), 250, $this->getID(), $this->getTag());
        }

        if ($this->getDeleted() == true) {
            Debug::Text('UnAssign Hours from Qualification: ' . $this->getId(), __FILE__, __LINE__, __METHOD__, 10);
            //Unassign hours from this qualification.

            $sf = TTnew('UserSkillFactory');
            $ef = TTnew('UserEducationFactory');
            $lf = TTnew('UserLicenseFactory');
            $lg = TTnew('UserLanguageFactory');
            $mf = TTnew('UserMembershipFactory');

            $query = 'update ' . $sf->getTable() . ' set qualification_id = 0 where qualification_id = ' . (int)$this->getId();
            $this->db->Execute($query);

            $query = 'update ' . $ef->getTable() . ' set qualification_id = 0 where qualification_id = ' . (int)$this->getId();
            $this->db->Execute($query);

            $query = 'update ' . $lf->getTable() . ' set qualification_id = 0 where qualification_id = ' . (int)$this->getId();
            $this->db->Execute($query);

            $query = 'update ' . $lg->getTable() . ' set qualification_id = 0 where qualification_id = ' . (int)$this->getId();
            $this->db->Execute($query);

            $query = 'update ' . $mf->getTable() . ' set qualification_id = 0 where qualification_id = ' . (int)$this->getId();
            $this->db->Execute($query);
            //Job employee criteria
        }

        return true;
    }

    public function getTag()
    {
        //Check to see if any temporary data is set for the tags, if not, make a call to the database instead.
        //postSave() needs to get the tmp_data.
        if (isset($this->tmp_data['tags'])) {
            return $this->tmp_data['tags'];
        } elseif ($this->getCompany() > 0 and $this->getID() > 0) {
            return CompanyGenericTagMapListFactory::getStringByCompanyIDAndObjectTypeIDAndObjectID($this->getCompany(), 250, $this->getID());
        }

        return false;
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

    public function getObjectAsArray($include_columns = null, $permission_children_ids = false)
    {
        $data = array();
        $variable_function_map = $this->getVariableToFunctionMap();
        if (is_array($variable_function_map)) {
            foreach ($variable_function_map as $variable => $function_stub) {
                if ($include_columns == null or (isset($include_columns[$variable]) and $include_columns[$variable] == true)) {
                    $function = 'get' . $function_stub;

                    switch ($variable) {
                        case 'group':
                            $data[$variable] = $this->getColumn($variable);
                            break;
                        case 'type':
                            $function = 'get' . $variable;
                            if (method_exists($this, $function)) {
                                $data[$variable] = Option::getByKey($this->$function(), $this->getOptions($variable));
                            }
                            break;
                        case 'name_metaphone':
                            break;
                        default:
                            if (method_exists($this, $function)) {
                                $data[$variable] = $this->$function();
                            }
                            break;
                    }
                }
            }
            $this->getPermissionColumns($data, $this->getCreatedBy(), false, $permission_children_ids, $include_columns);

            $this->getCreatedAndUpdatedColumns($data, $include_columns);
        }

        return $data;
    }

    public function addLog($log_action)
    {
        return TTLog::addEntry($this->getId(), $log_action, TTi18n::getText('Qualification') . ': ' . $this->getName(), null, $this->getTable(), $this);
    }

    public function getName()
    {
        if (isset($this->data['name'])) {
            return $this->data['name'];
        }

        return false;
    }
}
