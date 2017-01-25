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
 * @package API\Users
 */
class APIUserGenericData extends APIFactory
{
    protected $main_class = 'UserGenericDataFactory';

    public function __construct()
    {
        parent::__construct(); //Make sure parent constructor is always called.

        return true;
    }

    /**
     * Get user data for one or more users.
     *   Default to disable paging as it would rarely be used for this.
     * @param array $data filter data
     * @return array
     */
    public function getUserGenericData($data = null, $disable_paging = true)
    {
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        //Only allow getting generic data for currently logged in user unless user_id = 0, then get company wide data.
        //$data['filter_data']['user_id'] = $this->getCurrentUserObject()->getId();
        if (!isset($data['filter_data']['user_id']) or (isset($data['filter_data']['user_id']) and (int)$data['filter_data']['user_id'] !== 0)) {
            Debug::Text('Forcing User ID to current user: ' . $this->getCurrentUserObject()->getId(), __FILE__, __LINE__, __METHOD__, 10);
            $data['filter_data']['user_id'] = $this->getCurrentUserObject()->getId();
        } else {
            Debug::Text('Company wide data...', __FILE__, __LINE__, __METHOD__, 10);
            $data['filter_data']['user_id'] = 0; //Company wide data.
        }

        Debug::Arr($data, 'Getting User Generic Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $ugdlf = TTnew('UserGenericDataListFactory');
        $ugdlf->getAPISearchByCompanyIdAndArrayCriteria($this->getCurrentCompanyObject()->getId(), $data['filter_data'], $data['filter_items_per_page'], $data['filter_page'], null, $data['filter_sort']);
        Debug::Text('Record Count: ' . $ugdlf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($ugdlf->getRecordCount() > 0) {
            $this->setPagerObject($ugdlf);

            $retarr = array();
            foreach ($ugdlf as $ugd_obj) {
                $retarr[] = $ugd_obj->getObjectAsArray($data['filter_columns']);
            }

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true);
    }

    /**
     * Set user data for one or more users.
     * @param array $data user data
     * @return array
     */
    public function setUserGenericData($data, $ignore_warning = true)
    {
        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        extract($this->convertToMultipleRecords($data));
        Debug::Text('Received data for: ' . $total_records . ' Users', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        $validator = $save_result = false;
        if (is_array($data) and $total_records > 0) {
            foreach ($data as $key => $row) {
                $row['company_id'] = $this->getCurrentUserObject()->getCompany();
                if (!isset($row['user_id']) or (isset($row['user_id']) and (int)$row['user_id'] !== 0)) {
                    Debug::Text('Forcing User ID to current user: ' . $this->getCurrentUserObject()->getId(), __FILE__, __LINE__, __METHOD__, 10);
                    $row['user_id'] = $this->getCurrentUserObject()->getId();
                } else {
                    Debug::Text('Company wide data...', __FILE__, __LINE__, __METHOD__, 10);
                    $row['user_id'] = 0; //Company wide data.
                }

                $primary_validator = new Validator();
                $lf = TTnew('UserGenericDataListFactory');
                $lf->StartTransaction();
                if (isset($row['id'])) {
                    //Modifying existing object.
                    //Get object, so we can only modify just changed data for specific records if needed.
                    //$lf->getByUserIdAndId( $row['user_id'], $row['id'] );
                    $lf->getByCompanyIdAndUserIdAndId($row['company_id'], $row['user_id'], $row['id']);
                    if ($lf->getRecordCount() == 1) {
                        //Object exists, check edit permissions
                        $row = array_merge($lf->getCurrent()->getObjectAsArray(), $row);
                    } else {
                        //Object doesn't exist.
                        $primary_validator->isTrue('id', false, TTi18n::gettext('Edit permission denied, employee does not exist'));
                    }
                } //else {
                //Adding new object, check ADD permissions.
                //$primary_validator->isTrue( 'permission', $this->getPermissionObject()->Check('user', 'add'), TTi18n::gettext('Add permission denied') );
                //}
                Debug::Arr($row, 'User Generic Data: ', __FILE__, __LINE__, __METHOD__, 10);

                $is_valid = $primary_validator->isValid($ignore_warning);
                if ($is_valid == true) { //Check to see if all permission checks passed before trying to save data.
                    Debug::Text('Attempting to save User Data...', __FILE__, __LINE__, __METHOD__, 10);

                    //Force Company ID to current company.
                    $row['company_id'] = $this->getCurrentCompanyObject()->getId();

                    $lf->setObjectFromArray($row);

                    $is_valid = $lf->isValid($ignore_warning);
                    if ($is_valid == true) {
                        Debug::Text('Saving User Data...', __FILE__, __LINE__, __METHOD__, 10);
                        $save_result[$key] = $lf->Save();
                        $validator_stats['valid_records']++;
                    }
                }

                if ($is_valid == false) {
                    Debug::Text('User Data is Invalid...', __FILE__, __LINE__, __METHOD__, 10);

                    $lf->FailTransaction(); //Just rollback this single record, continue on to the rest.

                    $validator[$key] = $this->setValidationArray($primary_validator, $lf);
                }

                $lf->CommitTransaction();
            }

            return $this->handleRecordValidationResults($validator, $validator_stats, $key, $save_result);
        }

        return $this->returnHandler(false);
    }

    /**
     * Delete one or more users.
     * @param array $data user data
     * @return array
     */
    public function deleteUserGenericData($data)
    {
        if (is_numeric($data)) {
            $data = array($data);
        }

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        Debug::Text('Received data for: ' . count($data) . ' Users', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $total_records = count($data);
        $validator = $save_result = false;
        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        if (is_array($data) and $total_records > 0) {
            foreach ($data as $key => $id) {
                $primary_validator = new Validator();
                $lf = TTnew('UserGenericDataListFactory');
                $lf->StartTransaction();
                if (is_numeric($id)) {
                    //Modifying existing object.
                    //Get user object, so we can only modify just changed data for specific records if needed.
                    $lf->getByUserIdAndId($this->getCurrentUserObject()->getId(), $id);
                    if ($lf->getRecordCount() == 1) {
                        //Object exists
                        Debug::Text('User Generic Data Exists, deleting record: ', $id, __FILE__, __LINE__, __METHOD__, 10);
                        $lf = $lf->getCurrent();
                    } else {
                        //Object doesn't exist.
                        $primary_validator->isTrue('id', false, TTi18n::gettext('Delete permission denied, generic data does not exist'));
                    }
                } else {
                    $primary_validator->isTrue('id', false, TTi18n::gettext('Delete permission denied, generic data does not exist'));
                }

                //Debug::Arr($lf, 'AData: ', __FILE__, __LINE__, __METHOD__, 10);

                $is_valid = $primary_validator->isValid();
                if ($is_valid == true) { //Check to see if all permission checks passed before trying to save data.
                    Debug::Text('Attempting to delete user generic data...', __FILE__, __LINE__, __METHOD__, 10);
                    $lf->setDeleted(true);

                    $is_valid = $lf->isValid();
                    if ($is_valid == true) {
                        Debug::Text('User Deleted...', __FILE__, __LINE__, __METHOD__, 10);
                        $save_result[$key] = $lf->Save();
                        $validator_stats['valid_records']++;
                    }
                }

                if ($is_valid == false) {
                    Debug::Text('User Generic Data is Invalid...', __FILE__, __LINE__, __METHOD__, 10);

                    $lf->FailTransaction(); //Just rollback this single record, continue on to the rest.

                    $validator[$key] = $this->setValidationArray($primary_validator, $lf);
                }

                $lf->CommitTransaction();
            }

            return $this->handleRecordValidationResults($validator, $validator_stats, $key, $save_result);
        }

        return $this->returnHandler(false);
    }
}