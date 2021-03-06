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
 * @package Modules\Punch
 */
class PunchControlListFactory extends PunchControlFactory implements IteratorAggregate
{
    public function getAll($limit = null, $page = null, $where = null, $order = null)
    {
        $query = '
					select	*
					from	' . $this->getTable() . '
					WHERE deleted = 0';
        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, null, $limit, $page);

        return $this;
    }

    public function getById($id, $where = null, $order = null)
    {
        if ($id == '') {
            return false;
        }

        $ph = array(
            'id' => (int)$id,
        );

        $this->rs = $this->getCache($id);
        if ($this->rs === false) {
            $query = '
						select	*
						from	' . $this->getTable() . '
						where	id = ?
							AND deleted = 0';
            $query .= $this->getWhereSQL($where);
            $query .= $this->getSortSQL($order);

            $this->ExecuteSQL($query, $ph);

            $this->saveCache($this->rs, $id);
        }

        return $this;
    }

    public function getByCompanyId($company_id, $limit = null, $page = null, $where = null, $order = null)
    {
        if ($company_id == '') {
            return false;
        }

        if ($order == null) {
            $order = array('a.date_stamp' => 'asc');
            $strict = false;
        } else {
            $strict = true;
        }

        $uf = new UserFactory();

        $ph = array(
            'company_id' => (int)$company_id,
        );

        $query = '
					select	a.*
					from	' . $this->getTable() . ' as a,
							' . $uf->getTable() . ' as c
					where	a.user_id = c.id
						AND c.company_id = ?
						AND ( a.deleted = 0 AND c.deleted = 0 )
					';

        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order, $strict);

        $this->ExecuteSQL($query, $ph, $limit, $page);

        return $this;
    }

    public function getByIdAndCompanyId($id, $company_id)
    {
        if ($company_id == '') {
            return false;
        }

        if ($id == '') {
            return false;
        }

        $uf = new UserFactory();

        $ph = array(
            'company_id' => (int)$company_id,
        );

        $query = '
					select	a.*
					from	' . $this->getTable() . ' as a,
							' . $uf->getTable() . ' as c
					where	a.user_id = c.id
						AND c.company_id = ?
						AND a.id in (' . $this->getListSQL($id, $ph, 'int') . ')
						AND ( a.deleted = 0 AND c.deleted = 0 )
					';

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getByPunchId($punch_id, $order = null)
    {
        if ($punch_id == '') {
            return false;
        }

        $pf = new PunchFactory();

        $ph = array(
            'punch_id' => (int)$punch_id,
        );

        $query = '
					select	a.*
					from	' . $this->getTable() . ' as a,
							' . $pf->getTable() . ' as b
					where	a.id = b.punch_control_id
						AND b.id = ?
						AND ( a.deleted = 0 AND b.deleted=0 )
					';
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    public function getByUserIdAndDateStamp($user_id, $date_stamp, $order = null)
    {
        if ($user_id == '') {
            return false;
        }

        if ($date_stamp == '') {
            return false;
        }

        $ph = array(
            'user_id' => (int)$user_id,
            'date_stamp' => $this->db->BindDate($date_stamp),
        );

        $query = '
					select	a.*
					from	' . $this->getTable() . ' as a
					where
						a.user_id = ?
						AND a.date_stamp = ?
						AND ( a.deleted = 0 )
					';
        $query .= $this->getSortSQL($order);

        $this->ExecuteSQL($query, $ph);

        return $this;
    }

    //This function grabs all the punches on the given day
    //and determines where the epoch will fit in.
    public function getInCompletePunchControlIdByUserIdAndEpoch($user_id, $epoch, $status_id)
    {
        Debug::text(' Epoch: ' . TTDate::getDate('DATE+TIME', $epoch), __FILE__, __LINE__, __METHOD__, 10);
        if ($user_id == '') {
            return false;
        }

        if ($epoch == '') {
            return false;
        }

        $plf = new PunchListFactory();
        $plf->getShiftPunchesByUserIDAndEpoch($user_id, $epoch);
        if ($plf->getRecordCount() > 0) {
            $punch_arr = array();
            $prev_punch_arr = array();
            //Check for gaps.
            $prev_time_stamp = 0;
            foreach ($plf as $p_obj) {
                if ($p_obj->getStatus() == 10) {
                    $punch_arr[$p_obj->getPunchControlId()]['in'] = $p_obj->getTimeStamp();
                } else {
                    $punch_arr[$p_obj->getPunchControlId()]['out'] = $p_obj->getTimeStamp();
                }

                if ($prev_time_stamp != 0) {
                    $prev_punch_arr[$p_obj->getTimeStamp()] = $prev_time_stamp;
                }

                $prev_time_stamp = $p_obj->getTimeStamp();
            }
            unset($prev_time_stamp);

            if (isset($prev_punch_arr)) {
                $next_punch_arr = array_flip($prev_punch_arr);
            }

            //Debug::Arr( $punch_arr, ' Punch Array: ', __FILE__, __LINE__, __METHOD__, 10);
            //Debug::Arr( $next_punch_arr, ' Next Punch Array: ', __FILE__, __LINE__, __METHOD__, 10);

            if (empty($punch_arr) == false) {
                $i = 0;
                foreach ($punch_arr as $punch_control_id => $data) {
                    $found_gap = false;
                    Debug::text(' Iteration: ' . $i, __FILE__, __LINE__, __METHOD__, 10);

                    //Skip complete punch control rows.
                    if (isset($data['in']) and isset($data['out'])) {
                        Debug::text(' Punch Control ID is Complete: ' . $punch_control_id, __FILE__, __LINE__, __METHOD__, 10);
                    } else {
                        //Make sure we don't assign a In punch that comes AFTER an Out punch to the same pair.
                        //As well the opposite, an Out punch that comes BEFORE an In punch to the same pair.
                        if ($status_id == 10 and !isset($data['in']) and (isset($data['out']) and $epoch <= $data['out'])) {
                            Debug::text(' aFound Valid Gap...', __FILE__, __LINE__, __METHOD__, 10);
                            $found_gap = true;
                        } elseif ($status_id == 20 and !isset($data['out']) and (isset($data['in']) and $epoch >= $data['in'])) {
                            Debug::text(' bFound Valid Gap...', __FILE__, __LINE__, __METHOD__, 10);
                            $found_gap = true;
                        } else {
                            Debug::text(' No Valid Gap Found...', __FILE__, __LINE__, __METHOD__, 10);
                        }
                    }

                    if ($found_gap == true) {
                        if ($status_id == 10) { //In Gap
                            Debug::text(' In Gap...', __FILE__, __LINE__, __METHOD__, 10);
                            if (isset($prev_punch_arr[$data['out']])) {
                                Debug::text(' Punch Before In Gap... Range Start: ' . TTDate::getDate('DATE+TIME', $prev_punch_arr[$data['out']]) . ' End: ' . TTDate::getDate('DATE+TIME', $data['out']), __FILE__, __LINE__, __METHOD__, 10);
                                if ($prev_punch_arr[$data['out']] == $data['out'] or TTDate::isTimeOverLap($epoch, $epoch, $prev_punch_arr[$data['out']], $data['out'])) {
                                    Debug::text(' Epoch OverLaps, THIS IS GOOD!', __FILE__, __LINE__, __METHOD__, 10);
                                    Debug::text(' aReturning Punch Control ID: ' . $punch_control_id, __FILE__, __LINE__, __METHOD__, 10);
                                    $retval = $punch_control_id;
                                    break; //Without this adding mass punches fails in some basic circumstances because it loops and attaches to a later punch control
                                } else {
                                    Debug::text(' Epoch does not OverLaps, Cant attached to this punch_control!', __FILE__, __LINE__, __METHOD__, 10);
                                }
                            } else {
                                //No Punch After
                                Debug::text(' NO Punch Before In Gap...', __FILE__, __LINE__, __METHOD__, 10);
                                $retval = $punch_control_id;
                                break;
                            }
                        } else { //Out Gap
                            Debug::text(' Out Gap...', __FILE__, __LINE__, __METHOD__, 10);
                            //Start: $data['in']
                            //End: $data['in']
                            if (isset($next_punch_arr[$data['in']])) {
                                Debug::text(' Punch After Out Gap... Range Start: ' . TTDate::getDate('DATE+TIME', $data['in']) . ' End: ' . TTDate::getDate('DATE+TIME', $next_punch_arr[$data['in']]), __FILE__, __LINE__, __METHOD__, 10);
                                if ($data['in'] == $next_punch_arr[$data['in']] or TTDate::isTimeOverLap($epoch, $epoch, $data['in'], $next_punch_arr[$data['in']])) {
                                    Debug::text(' Epoch OverLaps, THIS IS GOOD!', __FILE__, __LINE__, __METHOD__, 10);
                                    Debug::text(' bReturning Punch Control ID: ' . $punch_control_id, __FILE__, __LINE__, __METHOD__, 10);
                                    $retval = $punch_control_id;
                                    break; //Without this adding mass punches fails in some basic circumstances because it loops and attaches to a later punch control
                                } else {
                                    Debug::text(' Epoch does not OverLaps, Cant attached to this punch_control!', __FILE__, __LINE__, __METHOD__, 10);
                                }
                            } else {
                                //No Punch After
                                Debug::text(' NO Punch After Out Gap...', __FILE__, __LINE__, __METHOD__, 10);
                                $retval = $punch_control_id;
                                break;
                            }
                        }
                    }
                    $i++;
                }
            }
        }

        if (isset($retval)) {
            Debug::text(' Returning Punch Control ID: ' . $retval, __FILE__, __LINE__, __METHOD__, 10);
            return $retval;
        }

        Debug::text(' Returning FALSE No Valid Gaps Found...', __FILE__, __LINE__, __METHOD__, 10);
        //FALSE means no gaps in punch control rows found.
        return false;
    }

    public function getAPISearchByCompanyIdAndArrayCriteria($company_id, $filter_data, $limit = null, $page = null, $where = null, $order = null)
    {
        if ($company_id == '') {
            return false;
        }

        if (!is_array($order)) {
            //Use Filter Data ordering if its set.
            if (isset($filter_data['sort_column']) and $filter_data['sort_order']) {
                $order = array(Misc::trimSortPrefix($filter_data['sort_column']) => $filter_data['sort_order']);
            }
        }

        //$additional_order_fields = array('b.name', 'c.name', 'd.name', 'e.name');
        $additional_order_fields = array('first_name', 'last_name', 'date_stamp', 'time_stamp', 'type_id', 'status_id', 'branch', 'department', 'default_branch', 'default_department', 'group', 'title');
        if ($order == null) {
            $order = array('b.pay_period_id' => 'asc', 'b.user_id' => 'asc');
            $strict = false;
        } else {
            $strict = true;
        }
        //Debug::Arr($order, 'Order Data:', __FILE__, __LINE__, __METHOD__, 10);
        //Debug::Arr($filter_data, 'Filter Data:', __FILE__, __LINE__, __METHOD__, 10);

        if (isset($filter_data['exclude_user_ids'])) {
            $filter_data['exclude_id'] = $filter_data['exclude_user_ids'];
        }
        if (isset($filter_data['user_id'])) {
            $filter_data['id'] = $filter_data['user_id'];
        }
        if (isset($filter_data['include_user_ids'])) {
            $filter_data['id'] = $filter_data['include_user_ids'];
        }
        if (isset($filter_data['user_status_ids'])) {
            $filter_data['status_id'] = $filter_data['user_status_ids'];
        }
        if (isset($filter_data['user_title_ids'])) {
            $filter_data['title_id'] = $filter_data['user_title_ids'];
        }
        if (isset($filter_data['group_ids'])) {
            $filter_data['group_id'] = $filter_data['group_ids'];
        }
        if (isset($filter_data['branch_ids'])) {
            $filter_data['default_branch_id'] = $filter_data['branch_ids'];
        }
        if (isset($filter_data['department_ids'])) {
            $filter_data['default_department_id'] = $filter_data['department_ids'];
        }
        if (isset($filter_data['punch_branch_ids'])) {
            $filter_data['punch_branch_id'] = $filter_data['punch_branch_ids'];
        }
        if (isset($filter_data['punch_department_ids'])) {
            $filter_data['punch_department_id'] = $filter_data['punch_department_ids'];
        }

        if (isset($filter_data['exclude_job_ids'])) {
            $filter_data['exclude_id'] = $filter_data['exclude_job_ids'];
        }
        if (isset($filter_data['include_job_ids'])) {
            $filter_data['include_job_id'] = $filter_data['include_job_ids'];
        }
        if (isset($filter_data['job_group_ids'])) {
            $filter_data['job_group_id'] = $filter_data['job_group_ids'];
        }
        if (isset($filter_data['job_item_ids'])) {
            $filter_data['job_item_id'] = $filter_data['job_item_ids'];
        }

        $uf = new UserFactory();
        $uwf = new UserWageFactory();
        $bf = new BranchFactory();
        $df = new DepartmentFactory();
        $ugf = new UserGroupFactory();
        $utf = new UserTitleFactory();

        $ph = array(
            'company_id' => (int)$company_id,
        );

        $query = '
					select
							b.id as id,
							b.branch_id as branch_id,
							j.name as branch,
							b.department_id as department_id,
							k.name as department,
							b.job_id as job_id,
							b.job_item_id as job_item_id,
							b.quantity as quantity,
							b.bad_quantity as bad_quantity,
							b.total_time as total_time,
							b.actual_total_time as actual_total_time,
							b.other_id1 as other_id1,
							b.other_id2 as other_id2,
							b.other_id3 as other_id3,
							b.other_id4 as other_id4,
							b.other_id5 as other_id5,
							b.note as note,

							b.user_id as user_id,
							b.date_stamp as date_stamp,
							b.pay_period_id as pay_period_id,

							d.first_name as first_name,
							d.last_name as last_name,
							d.status_id as user_status_id,
							d.group_id as group_id,
							g.name as "group",
							d.title_id as title_id,
							h.name as title,
							d.default_branch_id as default_branch_id,
							e.name as default_branch,
							d.default_department_id as default_department_id,
							f.name as default_department,
							d.created_by as user_created_by,

							z.id as user_wage_id,
							z.effective_date as user_wage_effective_date ';

        $query .= '
					from	' . $this->getTable() . ' as b
							LEFT JOIN ' . $uf->getTable() . ' as d ON b.user_id = d.id

							LEFT JOIN ' . $bf->getTable() . ' as e ON ( d.default_branch_id = e.id AND e.deleted = 0)
							LEFT JOIN ' . $df->getTable() . ' as f ON ( d.default_department_id = f.id AND f.deleted = 0)
							LEFT JOIN ' . $ugf->getTable() . ' as g ON ( d.group_id = g.id AND g.deleted = 0 )
							LEFT JOIN ' . $utf->getTable() . ' as h ON ( d.title_id = h.id AND h.deleted = 0 )

							LEFT JOIN ' . $bf->getTable() . ' as j ON ( b.branch_id = j.id AND j.deleted = 0)
							LEFT JOIN ' . $df->getTable() . ' as k ON ( b.department_id = k.id AND k.deleted = 0)

							LEFT JOIN ' . $uwf->getTable() . ' as z ON z.id = (select z.id
																		from ' . $uwf->getTable() . ' as z
																		where z.user_id = b.user_id
																			and z.effective_date <= b.date_stamp
																			and z.deleted = 0
																			order by z.effective_date desc LiMiT 1)
					';
        $query .= '	WHERE d.company_id = ?';

        $query .= (isset($filter_data['permission_children_ids'])) ? $this->getWhereClauseSQL('d.id', $filter_data['permission_children_ids'], 'numeric_list', $ph) : null;
        $query .= (isset($filter_data['id'])) ? $this->getWhereClauseSQL('b.id', $filter_data['id'], 'numeric_list', $ph) : null;
        $query .= (isset($filter_data['exclude_id'])) ? $this->getWhereClauseSQL('d.id', $filter_data['exclude_id'], 'not_numeric_list', $ph) : null;

        $query .= (isset($filter_data['user_id'])) ? $this->getWhereClauseSQL('b.user_id', $filter_data['user_id'], 'numeric_list', $ph) : null;

        $query .= (isset($filter_data['status_id'])) ? $this->getWhereClauseSQL('d.status_id', $filter_data['status_id'], 'numeric_list', $ph) : null;
        $query .= (isset($filter_data['group_id'])) ? $this->getWhereClauseSQL('d.group_id', $filter_data['group_id'], 'numeric_list', $ph) : null;

        $query .= (isset($filter_data['default_branch_id'])) ? $this->getWhereClauseSQL('d.default_branch_id', $filter_data['default_branch_id'], 'numeric_list', $ph) : null;
        $query .= (isset($filter_data['default_department_id'])) ? $this->getWhereClauseSQL('d.default_department_id', $filter_data['default_department_id'], 'numeric_list', $ph) : null;
        $query .= (isset($filter_data['title_id'])) ? $this->getWhereClauseSQL('d.title_id', $filter_data['title_id'], 'numeric_list', $ph) : null;

        $query .= (isset($filter_data['punch_branch_id'])) ? $this->getWhereClauseSQL('b.branch_id', $filter_data['punch_branch_id'], 'numeric_list', $ph) : null;
        $query .= (isset($filter_data['punch_department_id'])) ? $this->getWhereClauseSQL('b.department_id', $filter_data['punch_department_id'], 'numeric_list', $ph) : null;

        $query .= (isset($filter_data['pay_period_ids'])) ? $this->getWhereClauseSQL('b.pay_period_id', $filter_data['pay_period_ids'], 'numeric_list', $ph) : null;

        $query .= (isset($filter_data['has_note']) and $filter_data['has_note'] == true) ? ' AND b.note != \'\'' : null;

        if (isset($filter_data['start_date']) and !is_array($filter_data['start_date']) and trim($filter_data['start_date']) != '') {
            $ph[] = $this->db->BindDate((int)$filter_data['start_date']);
            $query .= ' AND b.date_stamp >= ?';
        }
        if (isset($filter_data['end_date']) and !is_array($filter_data['end_date']) and trim($filter_data['end_date']) != '') {
            $ph[] = $this->db->BindDate((int)$filter_data['end_date']);
            $query .= ' AND b.date_stamp <= ?';
        }

        $query .= ' AND ( b.deleted = 0 AND d.deleted = 0 ) ';
        $query .= $this->getWhereSQL($where);
        $query .= $this->getSortSQL($order, $strict, $additional_order_fields);

        $this->ExecuteSQL($query, $ph, $limit, $page);

        //Debug::Arr($ph, 'Query: '. $query, __FILE__, __LINE__, __METHOD__, 10);

        return $this;
    }
}
