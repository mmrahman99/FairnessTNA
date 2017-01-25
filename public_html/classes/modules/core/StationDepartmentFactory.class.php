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
 * @package Core
 */
class StationDepartmentFactory extends Factory
{
    public $department_obj = null;
        protected $table = 'station_department'; //PK Sequence name
protected $pk_sequence_name = 'station_department_id_seq';

    public function setStation($id)
    {
        $id = trim($id);

        if ($id == 0
            or
            $this->Validator->isNumeric('station',
                $id,
                TTi18n::gettext('Selected Station is invalid')
            /*
                            $this->Validator->isResultSetWithRows(	'station',
                                                                $slf->getByID($id),
                                                                TTi18n::gettext('Selected Station is invalid')
            */
            )
        ) {
            $this->data['station_id'] = $id;

            return true;
        }

        return false;
    }

    public function setDepartment($id)
    {
        $id = trim($id);

        $dlf = TTnew('DepartmentListFactory');

        if ($this->Validator->isResultSetWithRows('department',
            $dlf->getByID($id),
            TTi18n::gettext('Selected Department is invalid')
        )
        ) {
            $this->data['department_id'] = $id;

            return true;
        }

        return false;
    }

    public function getDeleted()
    {
        return false;
    }

    public function setDeleted($bool)
    {
        return false;
    }

    public function getCreatedDate()
    {
        return false;
    }

    //This table doesn't have any of these columns, so overload the functions.

    public function setCreatedDate($epoch = null)
    {
        return false;
    }

    public function getCreatedBy()
    {
        return false;
    }

    public function setCreatedBy($id = null)
    {
        return false;
    }

    public function getUpdatedDate()
    {
        return false;
    }

    public function setUpdatedDate($epoch = null)
    {
        return false;
    }

    public function getUpdatedBy()
    {
        return false;
    }

    public function setUpdatedBy($id = null)
    {
        return false;
    }

    public function getDeletedDate()
    {
        return false;
    }

    public function setDeletedDate($epoch = null)
    {
        return false;
    }

    public function getDeletedBy()
    {
        return false;
    }

    public function setDeletedBy($id = null)
    {
        return false;
    }

    public function addLog($log_action)
    {
        $d_obj = $this->getDepartmentObject();
        if (is_object($d_obj)) {
            return TTLog::addEntry($this->getStation(), $log_action, TTi18n::getText('Department') . ': ' . $d_obj->getName(), null, $this->getTable());
        }

        return false;
    }

    public function getDepartmentObject()
    {
        if (is_object($this->department_obj)) {
            return $this->department_obj;
        } else {
            $dlf = TTnew('DepartmentListFactory');
            $dlf->getById($this->getDepartment());
            if ($dlf->getRecordCount() == 1) {
                $this->department_obj = $dlf->getCurrent();
                return $this->department_obj;
            }

            return false;
        }
    }

    public function getDepartment()
    {
        if (isset($this->data['department_id'])) {
            return (int)$this->data['department_id'];
        }

        return false;
    }

    public function getStation()
    {
        if (isset($this->data['station_id'])) {
            return (int)$this->data['station_id'];
        }
    }
}
