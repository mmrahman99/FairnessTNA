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
 * @package API\Company
 */
class APICompany extends APIFactory
{
    protected $main_class = 'CompanyFactory';

    public function __construct()
    {
        parent::__construct(); //Make sure parent constructor is always called.

        return true;
    }

    /**
     * Get default company data for creating new companyes.
     * @return array
     */
    public function getCompanyDefaultData()
    {
        $company_obj = $this->getCurrentCompanyObject();

        $data = array(
            'status_id' => 10,
            'parent_id' => $company_obj->getId(),
            'password_minimum_length' => 8,
            'password_minimum_age' => 1,
            'password_maximum_age' => 180,
        );

        return $this->returnHandler($data);
    }

    /**
     * @param string $format
     * @param null $data
     * @param bool $disable_paging
     * @return array|bool
     */
    public function exportCompany($format = 'csv', $data = null, $disable_paging = true)
    {
        $result = $this->stripReturnHandler($this->getCompany($data, $disable_paging));
        return $this->exportRecords($format, 'export_company', $result, ((isset($data['filter_columns'])) ? $data['filter_columns'] : null));
    }

    /**
     * Get company data for one or more companyes.
     * @param array $data filter data
     * @return array
     */
    public function getCompany($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child'))
        ) {
            //return $this->getPermissionObject()->PermissionDenied();
            $data['filter_columns'] = $this->handlePermissionFilterColumns((isset($data['filter_columns'])) ? $data['filter_columns'] : null, Misc::trimSortPrefix($this->getOptions('list_columns')));
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        if (!$this->getPermissionObject()->Check('company', 'view')) {
            //Force ID to current company.
            $data['filter_data']['id'] = $this->getCurrentCompanyObject()->getId();
        }

        //FIXME: This filters company by created_by.
        //if ( $this->getPermissionObject()->Check('company', 'view') == FALSE AND $this->getPermissionObject()->Check('company', 'view_own') == TRUE ) {
        //	$data['filter_data']['permission_children_ids'] = $this->getCurrentUserObject()->getId(); //The created_by is unlikely to be the first user in the system, so this isn't going to work.
        //}

        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $blf = TTnew('CompanyListFactory');
        $blf->getAPISearchByCompanyIdAndArrayCriteria($this->getCurrentCompanyObject()->getId(), $data['filter_data'], $data['filter_items_per_page'], $data['filter_page'], null, $data['filter_sort']);
        Debug::Text('Record Count: ' . $blf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($blf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $blf->getRecordCount());

            $this->setPagerObject($blf);

            $retarr = array();
            foreach ($blf as $b_obj) {
                $retarr[] = $b_obj->getObjectAsArray($data['filter_columns']);

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $blf->getCurrentRow());
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true); //No records returned.
    }

    /**
     * Get options for dropdown boxes.
     * @param string $name Name of options to return, ie: 'columns', 'type', 'status'
     * @param mixed $parent Parent name/ID of options to return if data is in hierarchical format. (ie: Province)
     * @return array
     */
    public function getOptions($name = false, $parent = null)
    {
        if ($name == 'columns'
            and (!$this->getPermissionObject()->Check('company', 'enabled')
                or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child')))
        ) {
            $name = 'list_columns';
        }

        return parent::getOptions($name, $parent);
    }

    /**
     * Get only the fields that are common across all records in the search criteria. Used for Mass Editing of records.
     * @param array $data filter data
     * @return array
     */
    public function getCommonCompanyData($data)
    {
        return Misc::arrayIntersectByRow($this->stripReturnHandler($this->getCompany($data, true)));
    }

    /**
     * Validate company data for one or more companyes.
     * @param array $data company data
     * @return array
     */
    public function validateCompany($data)
    {
        return $this->setCompany($data, true);
    }

    /**
     * Set company data for one or more companyes.
     * @param array $data company data
     * @return array
     */
    public function setCompany($data, $validate_only = false, $ignore_warning = true)
    {
        global $config_vars;

        $validate_only = (bool)$validate_only;
        $ignore_warning = (bool)$ignore_warning;

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'edit') or $this->getPermissionObject()->Check('company', 'edit_own') or $this->getPermissionObject()->Check('company', 'edit_child') or $this->getPermissionObject()->Check('company', 'add'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }

        if ($validate_only == true) {
            Debug::Text('Validating Only!', __FILE__, __LINE__, __METHOD__, 10);
        }

        extract($this->convertToMultipleRecords($data));
        Debug::Text('Received data for: ' . $total_records . ' Companys', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        $validator = $save_result = false;
        if (is_array($data) and $total_records > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $total_records);

            foreach ($data as $key => $row) {
                $primary_validator = new Validator();
                $lf = TTnew('CompanyListFactory');
                $lf->StartTransaction();
                if (isset($row['id']) and $row['id'] > 0) {
                    //Modifying existing object.
                    //Get company object, so we can only modify just changed data for specific records if needed.
                    //$lf->getByIdAndCompanyId( $row['id'], $this->getCurrentCompanyObject()->getId() );
                    if (isset($config_vars['other']['primary_company_id']) and $this->getCurrentCompanyObject()->getId() == $config_vars['other']['primary_company_id']) {
                        $lf->getById($row['id']);
                    } else {
                        $lf->getByIdAndCompanyId($row['id'], $this->getCurrentCompanyObject()->getId());
                    }
                    //$lf->getById( $row['id'] );
                    if ($lf->getRecordCount() == 1) {
                        //Object exists, check edit permissions
                        if (
                            $validate_only == true
                            or
                            (
                                $this->getPermissionObject()->Check('company', 'edit')
                                or ($this->getPermissionObject()->Check('company', 'edit_own') and $this->getCurrentCompanyObject()->getId() == $lf->getCurrent()->getID())
                            )
                        ) {
                            Debug::Text('Row Exists, getting current data: ', $row['id'], __FILE__, __LINE__, __METHOD__, 10);
                            $lf = $lf->getCurrent();
                            $row = array_merge($lf->getObjectAsArray(), $row);
                        } else {
                            $primary_validator->isTrue('permission', false, TTi18n::gettext('Edit permission denied'));
                        }
                    } else {
                        //Object doesn't exist.
                        $primary_validator->isTrue('id', false, TTi18n::gettext('Edit permission denied, record does not exist'));
                    }
                } else {
                    //Adding new object, check ADD permissions.
                    $primary_validator->isTrue('permission', $this->getPermissionObject()->Check('company', 'add'), TTi18n::gettext('Add permission denied'));
                }
                Debug::Arr($row, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

                $is_valid = $primary_validator->isValid($ignore_warning);
                if ($is_valid == true) { //Check to see if all permission checks passed before trying to save data.
                    Debug::Text('Attempting to save data...', __FILE__, __LINE__, __METHOD__, 10);

                    //Don't allow changing edition, status unless they can edit all companies, or its the primary company (for On-Site installs)
                    if (!((isset($config_vars['other']['primary_company_id']) and $this->getCurrentCompanyObject()->getId() == $config_vars['other']['primary_company_id']) or $this->getPermissionObject()->Check('company', 'edit'))) {
                        unset($row['status_id']);
                    }

                    $lf->setObjectFromArray($row);

                    if (!$this->getPermissionObject()->Check('company', 'edit')) {
                        //Force ID to current company.
                        $lf->setID($this->getCurrentCompanyObject()->getId());
                    }

                    if ($lf->isNew() == true) {
                        $lf->setEnableAddCurrency(true);
                        $lf->setEnableAddPermissionGroupPreset(true);
                        $lf->setEnableAddStation(true);
                        $lf->setEnableAddPayStubEntryAccountPreset(true);
                        $lf->setEnableAddRecurringHolidayPreset(true);
                        $lf->setEnableAddUserDefaultPreset(true);
                    }

                    $is_valid = $lf->isValid($ignore_warning);
                    if ($is_valid == true) {
                        Debug::Text('Saving data...', __FILE__, __LINE__, __METHOD__, 10);
                        if ($validate_only == true) {
                            $save_result[$key] = true;
                        } else {
                            $save_result[$key] = $lf->Save();
                        }
                        $validator_stats['valid_records']++;
                    }
                }

                if ($is_valid == false) {
                    Debug::Text('Data is Invalid...', __FILE__, __LINE__, __METHOD__, 10);

                    $lf->FailTransaction(); //Just rollback this single record, continue on to the rest.

                    $validator[$key] = $this->setValidationArray($primary_validator, $lf);
                } elseif ($validate_only == true) {
                    $lf->FailTransaction();
                }


                $lf->CommitTransaction();

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $key);
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->handleRecordValidationResults($validator, $validator_stats, $key, $save_result);
        }

        return $this->returnHandler(false);
    }

    /**
     * Delete one or more companys.
     * @param array $data company data
     * @return array
     */
    public function deleteCompany($data)
    {
        global $config_vars;

        if (is_numeric($data)) {
            $data = array($data);
        }

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'delete') or $this->getPermissionObject()->Check('company', 'delete_own') or $this->getPermissionObject()->Check('company', 'delete_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }

        Debug::Text('Received data for: ' . count($data) . ' Companys', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $total_records = count($data);
        $validator = $save_result = false;
        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        if (is_array($data) and $total_records > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $total_records);

            foreach ($data as $key => $id) {
                $primary_validator = new Validator();
                $lf = TTnew('CompanyListFactory');
                $lf->StartTransaction();
                if (is_numeric($id)) {
                    //Modifying existing object.
                    //Get company object, so we can only modify just changed data for specific records if needed.
                    if (isset($config_vars['other']['primary_company_id']) and $this->getCurrentCompanyObject()->getId() == $config_vars['other']['primary_company_id']) {
                        $lf->getById($id);
                    } else {
                        $lf->getByIdAndCompanyId($id, $this->getCurrentCompanyObject()->getId());
                    }
                    if ($lf->getRecordCount() == 1) {
                        //Object exists, check edit permissions
                        if ($this->getPermissionObject()->Check('company', 'delete')
                            or ($this->getPermissionObject()->Check('company', 'delete_own') and $this->getPermissionObject()->isOwner($lf->getCurrent()->getCreatedBy(), $lf->getCurrent()->getID()) === true)
                        ) {
                            Debug::Text('Record Exists, deleting record: ', $id, __FILE__, __LINE__, __METHOD__, 10);
                            $lf = $lf->getCurrent();
                        } else {
                            $primary_validator->isTrue('permission', false, TTi18n::gettext('Delete permission denied'));
                        }
                    } else {
                        //Object doesn't exist.
                        $primary_validator->isTrue('id', false, TTi18n::gettext('Delete permission denied, record does not exist'));
                    }
                } else {
                    $primary_validator->isTrue('id', false, TTi18n::gettext('Delete permission denied, record does not exist'));
                }

                //Debug::Arr($lf, 'AData: ', __FILE__, __LINE__, __METHOD__, 10);

                $is_valid = $primary_validator->isValid();
                if ($is_valid == true) { //Check to see if all permission checks passed before trying to save data.
                    Debug::Text('Attempting to delete record...', __FILE__, __LINE__, __METHOD__, 10);
                    $lf->setDeleted(true);

                    $is_valid = $lf->isValid();
                    if ($is_valid == true) {
                        Debug::Text('Record Deleted...', __FILE__, __LINE__, __METHOD__, 10);
                        $save_result[$key] = $lf->Save();
                        $validator_stats['valid_records']++;
                    }
                }

                if ($is_valid == false) {
                    Debug::Text('Data is Invalid...', __FILE__, __LINE__, __METHOD__, 10);

                    $lf->FailTransaction(); //Just rollback this single record, continue on to the rest.

                    $validator[$key] = $this->setValidationArray($primary_validator, $lf);
                }

                $lf->CommitTransaction();

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $key);
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->handleRecordValidationResults($validator, $validator_stats, $key, $save_result);
        }

        return $this->returnHandler(false);
    }

    /**
     * Copy one or more companyes.
     * @param array $data company data
     * @return array
     */
    public function copyCompany($data)
    {
        $src_rows = $this->stripReturnHandler($this->getCompany($data, true));
        if (is_array($src_rows) and count($src_rows) > 0) {
            Debug::Arr($src_rows, 'SRC Rows: ', __FILE__, __LINE__, __METHOD__, 10);
            foreach ($src_rows as $key => $row) {
                unset($src_rows[$key]['id']); //Clear fields that can't be copied
                $src_rows[$key]['name'] = Misc::generateCopyName($row['name']); //Generate unique name
                $src_rows[$key]['short_name'] = rand(1000, 9999);
            }
            Debug::Arr($src_rows, 'bSRC Rows: ', __FILE__, __LINE__, __METHOD__, 10);

            return $this->setCompany($src_rows); //Save copied rows
        }

        return $this->returnHandler(false);
    }


    /*

    Additional Functions...

    */


    /**
     * Get user counts for a single company. We should be able to support multiple companies as well, or getting data for all companies by not specifying the company filter.
     * @param array $data filter data
     * @return array
     */
    public function getCompanyMinAvgMaxUserCounts($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        if ($this->getPermissionObject()->Check('company', 'view') == false) {
            if ($this->getPermissionObject()->Check('company', 'view_child')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
            if ($this->getPermissionObject()->Check('company', 'view_own')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
        }

        if (!isset($data['filter_data']['company_id'])) {
            $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
        }
        if (!isset($data['filter_data']['start_date'])) {
            $data['filter_data']['start_date'] = TTDate::getBeginMonthEpoch(time());
        }
        if (!isset($data['filter_data']['end_date'])) {
            $data['filter_data']['end_date'] = TTDate::getEndMonthEpoch(time());
        }

        Debug::Arr($data, 'Final Filter Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $cuclf = TTnew('CompanyUserCountListFactory');
        $cuclf->getMinAvgMaxByCompanyIdsAndStartDateAndEndDate($data['filter_data']['company_id'], $data['filter_data']['start_date'], $data['filter_data']['end_date']);
        Debug::Text('Record Count: ' . $cuclf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($cuclf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $cuclf->getRecordCount());

            $this->setPagerObject($cuclf);

            $retarr = array();
            foreach ($cuclf as $cuc_obj) {
                $retarr[] = array(
                    //'company_id' => $data['filter_data']['company_id'],
                    'company_id' => $cuc_obj->getColumn('company_id'),
                    'min_active_users' => $cuc_obj->getColumn('min_active_users'),
                    'avg_active_users' => $cuc_obj->getColumn('avg_active_users'),
                    'max_active_users' => $cuc_obj->getColumn('max_active_users'),

                    'min_inactive_users' => $cuc_obj->getColumn('min_inactive_users'),
                    'avg_inactive_users' => $cuc_obj->getColumn('avg_inactive_users'),
                    'max_inactive_users' => $cuc_obj->getColumn('max_inactive_users'),

                    'min_deleted_users' => $cuc_obj->getColumn('min_deleted_users'),
                    'avg_deleted_users' => $cuc_obj->getColumn('avg_deleted_users'),
                    'max_deleted_users' => $cuc_obj->getColumn('max_deleted_users'),
                );

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $cuclf->getCurrentRow());
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true); //No records returned.
    }

    /**
     * Get user email addresses for a single company. We should be able to support multiple companies as well, or getting data for all companies by not specifying the company filter.
     * @param array $data filter data
     * @return array
     */
    public function getCompanyEmailAddresses($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        if ($this->getPermissionObject()->Check('company', 'view') == false) {
            if ($this->getPermissionObject()->Check('company', 'view_child')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
            if ($this->getPermissionObject()->Check('company', 'view_own')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
        }

        if (!isset($data['filter_data']['company_id'])) {
            $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
        }

        if (!isset($data['filter_sort'])) {
            $data['filter_sort'] = array('company_id' => 'asc', 'a.last_name' => 'asc');
        }

        Debug::Arr($data, 'Final Filter Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $ulf = TTnew('UserListFactory');
        $ulf->getAPIEmailAddressDataByArrayCriteria($data['filter_data'], $data['filter_items_per_page'], $data['filter_page'], null, $data['filter_sort']);
        Debug::Text('Record Count: ' . $ulf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($ulf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $ulf->getRecordCount());

            $this->setPagerObject($ulf);

            $retarr = array();
            foreach ($ulf as $u_obj) {
                $retarr[] = $u_obj->data;

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $ulf->getCurrentRow());
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true); //No records returned.
    }

    /**
     * Get phone minutes for a single company. We should be able to support multiple companies as well, or getting data for all companies by not specifying the company filter.
     * @param array $data filter data
     * @return array
     */
    public function getCompanyPhonePunchData($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        if ($this->getPermissionObject()->Check('company', 'view') == false) {
            if ($this->getPermissionObject()->Check('company', 'view_child')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
            if ($this->getPermissionObject()->Check('company', 'view_own')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
        }

        if (!isset($data['filter_data']['company_id'])) {
            $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
        }
        if (!isset($data['filter_data']['start_date'])) {
            $data['filter_data']['start_date'] = TTDate::getBeginMonthEpoch(time());
        }
        if (!isset($data['filter_data']['end_date'])) {
            $data['filter_data']['end_date'] = TTDate::getEndMonthEpoch(time());
        }

        $llf = TTnew('LogListFactory');
        $llf->getByPhonePunchDataByCompanyIdAndStartDateAndEndDate($data['filter_data']['company_id'], $data['filter_data']['start_date'], $data['filter_data']['end_date']);
        Debug::Text('Record Count: ' . $llf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($llf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $llf->getRecordCount());

            $this->setPagerObject($llf);

            $retarr = array();
            foreach ($llf as $l_obj) {
                $retarr[] = array(
                    'company_id' => $l_obj->getColumn('company_id'),
                    'product' => $l_obj->getColumn('product'),
                    'minutes' => $l_obj->getColumn('minutes'),
                    'billable_minutes' => $l_obj->getColumn('billable_units'),
                    'calls' => $l_obj->getColumn('calls'),
                    'unique_users' => $l_obj->getColumn('unique_users'),
                );

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $llf->getCurrentRow());
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true); //No records returned.
    }

    /**
     * Get station counts for a single company. We should be able to support multiple companies as well, or getting data for all companies by not specifying the company filter.
     * @param array $data filter data
     * @return array
     */
    public function getCompanyStationCounts($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        if ($this->getPermissionObject()->Check('company', 'view') == false) {
            if ($this->getPermissionObject()->Check('company', 'view_child')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
            if ($this->getPermissionObject()->Check('company', 'view_own')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
        }

        if (!isset($data['filter_data']['company_id'])) {
            $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
        }

        $llf = TTnew('StationListFactory');
        if (!isset($data['filter_data']['type_id'])) {
            //$data['filter_data']['type_id'] = array_keys( Misc::trimSortPrefix( $llf->getOptions('type') ) );
            $data['filter_data']['type_id'] = array(61, 65);
        }

        $llf->getCountByCompanyIdAndTypeId($data['filter_data']['company_id'], $data['filter_data']['type_id']);
        Debug::Text('Record Count: ' . $llf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($llf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $llf->getRecordCount());

            $this->setPagerObject($llf);

            $retarr = array();
            foreach ($llf as $l_obj) {
                $retarr[] = array(
                    'company_id' => $l_obj->getColumn('company_id'),
                    'type_id' => $l_obj->getColumn('type_id'),
                    'total' => $l_obj->getColumn('total'),
                );

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $llf->getCurrentRow());
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true); //No records returned.
    }

    /**
     * Get timeclock stations associated with each company.
     * @param array $data filter data
     * @return array
     */
    public function getCompanyTimeClockStations($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company', 'enabled')
            or !($this->getPermissionObject()->Check('company', 'view') or $this->getPermissionObject()->Check('company', 'view_own') or $this->getPermissionObject()->Check('company', 'view_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        if ($this->getPermissionObject()->Check('company', 'view') == false) {
            if ($this->getPermissionObject()->Check('company', 'view_child')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
            if ($this->getPermissionObject()->Check('company', 'view_own')) {
                $data['filter_data']['company_id'] = $this->getCurrentCompanyObject()->getId();
            }
        }

        $llf = TTnew('StationListFactory');
        if (!isset($data['filter_data']['status_id'])) {
            $data['filter_data']['status_id'] = array(20);
        }
        if (!isset($data['filter_data']['type_id'])) {
            $data['filter_data']['type_id'] = array(150);
        }
        if (!isset($data['filter_columns'])) {
            $data['filter_columns'] = array('id' => true, 'station_id' => true, 'status_id' => true, 'type_id' => true, 'updated_date' => true);
        }

        $llf->getAPITimeClockStationsByArrayCriteria($data['filter_data']);
        Debug::Text('Record Count: ' . $llf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($llf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $llf->getRecordCount());

            $this->setPagerObject($llf);

            $retarr = array();
            foreach ($llf as $l_obj) {
                $retarr[] = $l_obj->getObjectAsArray($data['filter_columns']);
                $this->getProgressBarObject()->set($this->getAMFMessageID(), $llf->getCurrentRow());
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->returnHandler($retarr);
        }

        return $this->returnHandler(true); //No records returned.
    }

    /**
     * Return an array to determine if branches, department, job and task dropdown boxes should be enabled and have data.
     * @return array
     */
    public function isBranchAndDepartmentAndJobAndJobItemEnabled()
    {
        $retarr = array(
            'branch' => false,
            'department' => false,
            'job' => false,
            'job_item' => false,
        );

        $blf = TTnew('BranchListFactory');
        $blf->getByCompanyId($this->getCurrentCompanyObject()->getId(), 1);
        if ($blf->getRecordCount() >= 1) {
            $retarr['branch'] = true;
        }

        $dlf = TTnew('DepartmentListFactory');
        $dlf->getByCompanyId($this->getCurrentCompanyObject()->getId(), 1);
        if ($dlf->getRecordCount() >= 1) {
            $retarr['department'] = true;
        }

        return $retarr;
    }
}