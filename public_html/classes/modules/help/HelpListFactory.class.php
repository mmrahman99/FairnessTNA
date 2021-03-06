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
 * @package Modules\Help
 */
class HelpListFactory extends HelpFactory implements IteratorAggregate
{
    public function getById($id, $where = null, $order = null)
    {
        if ($id == '') {
            return false;
        }

        $ph = array(
            'id' => (int)$id,
        );


        $query = '
					select	*
					from	' . $this->getTable() . '
					where	id = ?
						AND deleted=0';
        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getByScriptNameAndGroupName($script_name, $group_name = null, $where = null, $order = null)
    {
        if ($script_name == '' and $group_name == '') {
            return false;
        }

        $hgcf = new HelpGroupControlFactory();
        $hg = new HelpGroupFactory();

        $ph = array(
            'script_name' => $script_name,
            'group_name' => $group_name,
        );

        $query = '
					select	a.*
					from	' . $this->getTable() . ' as a,
							' . $hgcf->getTable() . ' as b,
							' . $hg->getTable() . ' as c
					where	b.id = c.help_group_control_id
							AND c.help_id = a.id
						';
        //if ( $script_name != '' ) {
        $query .= ' AND b.script_name = ?';
        //}

        //if ( $group_name != '') {
        $query .= ' AND b.name = ?';
        //}

        $query .= ' AND a.deleted=0
					AND b.deleted=0
					ORDER BY c.order_value asc';

        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getByScriptNameAndType($script_name, $type, $where = null, $order = null)
    {
        if ($script_name == '') {
            return false;
        }

        if ($type == '') {
            return false;
        }

        $type_key = Option::getByValue($type, $this->getOptions('type'));
        if ($type_key !== false) {
            $type = $type_key;
        }

        $hgcf = new HelpGroupControlFactory();
        $hg = new HelpGroupFactory();

        $ph = array(
            'script_name' => $script_name,
            'type_id' => $type,
        );

        $query = '
					select	a.*, b.name as group_name
					from	' . $this->getTable() . ' as a,
							' . $hgcf->getTable() . ' as b,
							' . $hg->getTable() . ' as c
					where	b.id = c.help_group_control_id
							AND c.help_id = a.id
							AND b.script_name = ?
							AND a.type_id = ?
							AND a.deleted=0
							AND b.deleted=0
					ORDER BY c.order_value asc
						';

        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getByScriptNameAndTypeAndStatus($script_name, $type, $status, $where = null, $order = null)
    {
        if ($script_name == '') {
            return false;
        }

        if ($type == '') {
            return false;
        }

        if ($status == '') {
            return false;
        }

        $type_key = Option::getByValue($type, $this->getOptions('type'));
        if ($type_key !== false) {
            $type = $type_key;
        }

        $hgcf = new HelpGroupControlFactory();
        $hg = new HelpGroupFactory();

        $ph = array(
            'script_name' => $script_name,
            'type_id' => $type,
            'status_id' => $status,
        );

        $query = '
					select	a.*, b.name as group_name
					from	' . $this->getTable() . ' as a,
							' . $hgcf->getTable() . ' as b,
							' . $hg->getTable() . ' as c
					where	b.id = c.help_group_control_id
							AND c.help_id = a.id
							AND b.script_name = ?
							AND a.type_id = ?
							AND a.status_id = ?
							AND a.deleted=0
							AND b.deleted=0
					ORDER BY c.order_value asc
						';

        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getByScriptNameAndStatus($script_name, $status, $where = null, $order = null)
    {
        if ($script_name == '') {
            return false;
        }

        if ($status == '') {
            return false;
        }

        $status_key = Option::getByValue($status, $this->getOptions('status'));
        if ($status_key !== false) {
            $status = $status_key;
        }

        $hgcf = new HelpGroupControlFactory();
        $hg = new HelpGroupFactory();

        $ph = array(
            'script_name' => $script_name,
            'status_id' => $status,
        );

        $query = '
					select	a.*, b.name as group_name
					from	' . $this->getTable() . ' as a,
							' . $hgcf->getTable() . ' as b,
							' . $hg->getTable() . ' as c
					where	b.id = c.help_group_control_id
							AND c.help_id = a.id
							AND b.script_name = ?
							AND a.status_id = ?
							AND a.deleted=0
							AND b.deleted=0
					ORDER BY a.type_id desc, c.order_value asc
						';

        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getAllArray()
    {
        $hlf = new HelpListFactory();
        $hlf->getAll();

        $help_list[0] = '--';

        foreach ($hlf as $help) {
            $help_list[$help->getID()] = '(' . $help->getID() . ') [' . Option::getByKey($help->getType(), $help->getOptions('type')) . '] ' . $help->getHeading();
        }

        return $help_list;
    }

    public function getAll($limit = null, $page = null, $where = null, $order = null)
    {
        $strict_order = true;
        if ($order == null) {
            $order = array('created_date' => 'desc');
            //$strict_order = FALSE;
        }

        $query = '
					select	*
					from	' . $this->getTable() . '
					WHERE deleted=0';
        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order, $strict_order);

        $this->ExecuteSQL($query, null, $limit, $page);

        return $this;
    }
}
