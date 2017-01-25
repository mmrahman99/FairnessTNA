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
class APICompanyDeduction extends APIFactory
{
    protected $main_class = 'CompanyDeductionFactory';

    public function __construct()
    {
        parent::__construct(); //Make sure parent constructor is always called.

        return true;
    }

    /**
     * Get default company_deduction data for creating new company_deductiones.
     * @return array
     */
    public function getCompanyDeductionDefaultData()
    {
        $company_obj = $this->getCurrentCompanyObject();

        Debug::Text('Getting company_deduction default data...', __FILE__, __LINE__, __METHOD__, 10);

        $data = array(
            'company_id' => $company_obj->getId(),
            'status_id' => 10,
            'type_id' => 10,
            'apply_frequency_id' => 10, //each Pay Period
            'calculation_order' => 100,
        );

        return $this->returnHandler($data);
    }

    /**
     * Export data to csv
     * @param array $data filter data
     * @param string $format file format (csv)
     * @return array
     */
    public function exportCompanyDeduction($format = 'csv', $data = null, $disable_paging = true)
    {
        $result = $this->stripReturnHandler($this->getCompanyDeduction($data, $disable_paging));
        return $this->exportRecords($format, 'export_company_deduction', $result, ((isset($data['filter_columns'])) ? $data['filter_columns'] : null));
    }

    /**
     * Get company_deduction data for one or more company_deductiones.
     * @param array $data filter data
     * @return array
     */
    public function getCompanyDeduction($data = null, $disable_paging = false)
    {
        if (!$this->getPermissionObject()->Check('company_tax_deduction', 'enabled')
            or !($this->getPermissionObject()->Check('company_tax_deduction', 'view') or $this->getPermissionObject()->Check('company_tax_deduction', 'view_own') or $this->getPermissionObject()->Check('company_tax_deduction', 'view_child'))
        ) {
            //return $this->getPermissionObject()->PermissionDenied();

            if (!$this->getPermissionObject()->Check('user_tax_deduction', 'enabled')
                or !($this->getPermissionObject()->Check('user_tax_deduction', 'view') or $this->getPermissionObject()->Check('user_tax_deduction', 'view_own') or $this->getPermissionObject()->Check('user_tax_deduction', 'view_child'))
            ) {
                $data['filter_columns'] = $this->handlePermissionFilterColumns((isset($data['filter_columns'])) ? $data['filter_columns'] : null, Misc::trimSortPrefix($this->getOptions('list_columns')));
            } else {
                //User has access the user_tax_deduction permissions, restrict columns that are returned so we don't include all users, include/exclude PSA's etc...
                if (!isset($data['filter_columns'])) {
                    $cdf = TTnew('CompanyDeductionFactory');
                    $data['filter_columns'] = Misc::preSetArrayValues(array(), array_keys($cdf->getVariableToFunctionMap()), true);
                    unset($cdf, $data['filter_columns']['user'], $data['filter_columns']['include_pay_stub_entry_account'], $data['filter_columns']['exclude_pay_stub_entry_account'], $data['filter_columns']['total_users']);
                }
            }
        }
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        //Need to pass this into getObjectAsArray() separately to help handle Edit Employee -> Tax tab and is_owner/is_child columns.
        if (isset($data['filter_data']['include_user_id'])) {
            $include_user_id = $data['filter_data']['include_user_id'];
        } else {
            $include_user_id = false;
        }

        //Help handle cases where a supervisor can access the Edit Employee -> Tax tab, but not the Payroll -> Tax/Deduction list.
        if ($this->getPermissionObject()->Check('company_tax_deduction', 'enabled') and ($this->getPermissionObject()->Check('company_tax_deduction', 'view') or $this->getPermissionObject()->Check('company_tax_deduction', 'view_own') or $this->getPermissionObject()->Check('company_tax_deduction', 'view_child'))) {
            Debug::Text('Using company_tax_deduction permission_children...', __FILE__, __LINE__, __METHOD__, 10);
            $data['filter_data']['permission_children_ids'] = $this->getPermissionObject()->getPermissionChildren('company_tax_deduction', 'view');
        } elseif ($this->getPermissionObject()->Check('user_tax_deduction', 'enabled') and ($this->getPermissionObject()->Check('user_tax_deduction', 'view') or $this->getPermissionObject()->Check('user_tax_deduction', 'view_own') or $this->getPermissionObject()->Check('user_tax_deduction', 'view_child'))) {
            Debug::Text('Using user_tax_deduction permission_children...', __FILE__, __LINE__, __METHOD__, 10);
            $data['filter_data']['permission_children_ids'] = $this->getPermissionObject()->getPermissionChildren('user_tax_deduction', 'view');
        } else {
            $data['filter_data']['permission_children_ids'] = null;
        }

        $blf = TTnew('CompanyDeductionListFactory');
        $blf->getAPISearchByCompanyIdAndArrayCriteria($this->getCurrentCompanyObject()->getId(), $data['filter_data'], $data['filter_items_per_page'], $data['filter_page'], null, $data['filter_sort']);
        Debug::Text('Record Count: ' . $blf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
        if ($blf->getRecordCount() > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $blf->getRecordCount());

            $this->setPagerObject($blf);

            $retarr = array();
            foreach ($blf as $b_obj) {
                $retarr[] = $b_obj->getObjectAsArray($data['filter_columns'], $data['filter_data']['permission_children_ids'], $include_user_id);

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
            and (!$this->getPermissionObject()->Check('company_tax_deduction', 'enabled')
                or !($this->getPermissionObject()->Check('company_tax_deduction', 'view') or $this->getPermissionObject()->Check('company_tax_deduction', 'view_own') or $this->getPermissionObject()->Check('company_tax_deduction', 'view_child')))
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
    public function getCommonCompanyDeductionData($data)
    {
        return Misc::arrayIntersectByRow($this->stripReturnHandler($this->getCompanyDeduction($data, true)));
    }

    /**
     * Validate company_deduction data for one or more company_deductiones.
     * @param array $data company_deduction data
     * @return array
     */
    public function validateCompanyDeduction($data)
    {
        return $this->setCompanyDeduction($data, true);
    }

    /**
     * Set company_deduction data for one or more company_deductiones.
     * @param array $data company_deduction data
     * @return array
     */
    public function setCompanyDeduction($data, $validate_only = false, $ignore_warning = true)
    {
        $validate_only = (bool)$validate_only;
        $ignore_warning = (bool)$ignore_warning;

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        if (!$this->getPermissionObject()->Check('company_tax_deduction', 'enabled')
            or !($this->getPermissionObject()->Check('company_tax_deduction', 'edit') or $this->getPermissionObject()->Check('company_tax_deduction', 'edit_own') or $this->getPermissionObject()->Check('company_tax_deduction', 'edit_child') or $this->getPermissionObject()->Check('company_tax_deduction', 'add'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }

        if ($validate_only == true) {
            Debug::Text('Validating Only!', __FILE__, __LINE__, __METHOD__, 10);
        }

        extract($this->convertToMultipleRecords($data));
        Debug::Text('Received data for: ' . $total_records . ' CompanyDeductions', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        $validator = $save_result = false;
        if (is_array($data) and $total_records > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $total_records);

            foreach ($data as $key => $row) {
                $primary_validator = new Validator();
                $lf = TTnew('CompanyDeductionListFactory');
                $lf->StartTransaction();
                if (isset($row['id']) and $row['id'] > 0) {
                    //Modifying existing object.
                    //Get company_deduction object, so we can only modify just changed data for specific records if needed.
                    $lf->getByIdAndCompanyId($row['id'], $this->getCurrentCompanyObject()->getId());
                    if ($lf->getRecordCount() == 1) {
                        //Object exists, check edit permissions
                        if (
                            $validate_only == true
                            or
                            (
                                $this->getPermissionObject()->Check('company_tax_deduction', 'edit')
                                or ($this->getPermissionObject()->Check('company_tax_deduction', 'edit_own') and $this->getPermissionObject()->isOwner($lf->getCurrent()->getCreatedBy(), $lf->getCurrent()->getID()) === true)
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
                    $primary_validator->isTrue('permission', $this->getPermissionObject()->Check('company_tax_deduction', 'add'), TTi18n::gettext('Add permission denied'));

                    //Because this class has sub-classes that depend on it, when adding a new record we need to make sure the ID is set first,
                    //so the sub-classes can depend on it. We also need to call Save( TRUE, TRUE ) to force a lookup on isNew()
                    $row['id'] = $lf->getNextInsertId();
                }
                Debug::Arr($row, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

                $is_valid = $primary_validator->isValid($ignore_warning);
                if ($is_valid == true) { //Check to see if all permission checks passed before trying to save data.
                    Debug::Text('Setting object data...', __FILE__, __LINE__, __METHOD__, 10);

                    //Force Company ID to current company.
                    $row['company_id'] = $this->getCurrentCompanyObject()->getId();

                    $lf->setObjectFromArray($row);

                    $is_valid = $lf->isValid($ignore_warning);
                    if ($is_valid == true) {
                        Debug::Text('Saving data...', __FILE__, __LINE__, __METHOD__, 10);
                        if ($validate_only == true) {
                            $save_result[$key] = true;
                        } else {
                            $save_result[$key] = $lf->Save(true, true);
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
     * Delete one or more company_deductions.
     * @param array $data company_deduction data
     * @return array
     */
    public function deleteCompanyDeduction($data)
    {
        if (is_numeric($data)) {
            $data = array($data);
        }

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        if (!$this->getPermissionObject()->Check('company_tax_deduction', 'enabled')
            or !($this->getPermissionObject()->Check('company_tax_deduction', 'delete') or $this->getPermissionObject()->Check('company_tax_deduction', 'delete_own') or $this->getPermissionObject()->Check('company_tax_deduction', 'delete_child'))
        ) {
            return $this->getPermissionObject()->PermissionDenied();
        }

        Debug::Text('Received data for: ' . count($data) . ' CompanyDeductions', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $total_records = count($data);
        $validator = $save_result = false;
        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        if (is_array($data) and $total_records > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $total_records);

            foreach ($data as $key => $id) {
                $primary_validator = new Validator();
                $lf = TTnew('CompanyDeductionListFactory');
                $lf->StartTransaction();
                if (is_numeric($id)) {
                    //Modifying existing object.
                    //Get company_deduction object, so we can only modify just changed data for specific records if needed.
                    $lf->getByIdAndCompanyId($id, $this->getCurrentCompanyObject()->getId());
                    if ($lf->getRecordCount() == 1) {
                        //Object exists, check edit permissions
                        if ($this->getPermissionObject()->Check('company_tax_deduction', 'delete')
                            or ($this->getPermissionObject()->Check('company_tax_deduction', 'delete_own') and $this->getPermissionObject()->isOwner($lf->getCurrent()->getCreatedBy(), $lf->getCurrent()->getID()) === true)
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
     * Copy one or more company_deductiones.
     * @param array $data company_deduction IDs
     * @return array
     */
    public function copyCompanyDeduction($data)
    {
        if (is_numeric($data)) {
            $data = array($data);
        }

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        Debug::Text('Received data for: ' . count($data) . ' CompanyDeductions', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $src_rows = $this->stripReturnHandler($this->getCompanyDeduction(array('filter_data' => array('id' => $data)), true));
        if (is_array($src_rows) and count($src_rows) > 0) {
            Debug::Arr($src_rows, 'SRC Rows: ', __FILE__, __LINE__, __METHOD__, 10);
            foreach ($src_rows as $key => $row) {
                unset($src_rows[$key]['id']); //Clear fields that can't be copied
                $src_rows[$key]['name'] = Misc::generateCopyName($row['name']); //Generate unique name
            }
            Debug::Arr($src_rows, 'bSRC Rows: ', __FILE__, __LINE__, __METHOD__, 10);

            return $this->setCompanyDeduction($src_rows); //Save copied rows
        }

        return $this->returnHandler(false);
    }

    /**
     * Returns combined calculation ID for company deduction layers.
     * @param string $calculation_id , $country, $province
     * @return string
     */
    public function getCombinedCalculationID($calculation_id = null, $country = null, $province = null)
    {
        $cdf = TTnew('CompanyDeductionFactory');
        //Don't use returnHandler here as its boolean TRUE/FALSE that is returned.
        return $cdf->getCombinedCalculationID($calculation_id, $country, $province);
    }

    /**
     * Returns boolean if provided calculation ID is for a country.
     * @param integer $calculation_id
     * @return boolean
     */
    public function isCountryCalculationID($calculation_id)
    {
        $cdf = TTnew('CompanyDeductionFactory');
        return $cdf->isCountryCalculationID($calculation_id);
    }

    /**
     * Returns boolean if provided calculation ID is for a province/state
     * @param integer $calculation_id
     * @return boolean
     */
    public function isProvinceCalculationID($calculation_id)
    {
        $cdf = TTnew('CompanyDeductionFactory');
        return $cdf->isProvinceCalculationID($calculation_id);
    }

    /**
     * Returns boolean if provided calculation ID is for a district.
     * @param integer $calculation_id
     * @return boolean
     */
    public function isDistrictCalculationID($calculation_id)
    {
        $cdf = TTnew('CompanyDeductionFactory');
        return $cdf->isDistrictCalculationID($calculation_id);
    }
}
