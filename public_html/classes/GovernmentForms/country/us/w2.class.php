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


include_once('US.class.php');

/**
 * @package GovernmentForms
 */
class GovernmentForms_US_W2 extends GovernmentForms_US
{
    public $xml_schema = '1040/IndividualIncomeTax/Common/IRSW2/IRSW2.xsd';
    public $pdf_template = 'w2.pdf';

    public $template_offsets = array(0, 0);

    public function getOptions($name)
    {
        $retval = null;
        switch ($name) {
            case 'type':
                $retval = array(
                    'government' => TTi18n::gettext('Government (Multiple Employees/Page)'),
                    'employee' => TTi18n::gettext('Employee (One Employee/Page)'),
                );
                break;
        }

        return $retval;
    }

    public function setType($value)
    {
        $this->type = trim($value);
        return true;
    }

    public function getShowInstructionPage()
    {
        if (isset($this->show_instruction_page)) {
            return $this->show_instruction_page;
        }

        return false;
    }

    public function setShowInstructionPage($value)
    {
        $this->show_instruction_page = (bool)trim($value);
        return true;
    }

    //Set the type of form to display/print. Typically this would be:
    // government or employee.

    public function getPreCalcFunction($name)
    {
        $variable_function_map = array(
            'l4' => 'preCalcL4',
            'l6' => 'preCalcL6',
            'l3' => 'preCalcL3',
        );

        if (isset($variable_function_map[$name])) {
            return $variable_function_map[$name];
        }

        return false;
    }

    public function getFilterFunction($name)
    {
        $variable_function_map = array(
            'year' => 'isNumeric',
            'ein' => array('stripNonNumeric', 'isNumeric'),
        );

        if (isset($variable_function_map[$name])) {
            return $variable_function_map[$name];
        }

        return false;
    }

    public function preCalcL3($value, $key, &$array)
    {
        if ($value > $this->getSocialSecurityMaximumEarnings()) {
            Debug::Text('Social security earnings exceeds maximum...', __FILE__, __LINE__, __METHOD__, 10);
            $value = $this->getSocialSecurityMaximumEarnings();
        }

        return $value;
    }

    public function getSocialSecurityMaximumEarnings()
    {
        return $this->getPayrollDeductionObject()->getSocialSecurityMaximumEarnings();
    }

    public function getPayrollDeductionObject()
    {
        if (!isset($this->payroll_deduction_obj)) {
            require_once(Environment::getBasePath() . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'payroll_deduction' . DIRECTORY_SEPARATOR . 'PayrollDeduction.class.php');
            $this->payroll_deduction_obj = new PayrollDeduction('US', null);
            $this->payroll_deduction_obj->setDate(TTDate::getTimeStamp($this->year, 12, 31));
        }

        return $this->payroll_deduction_obj;
    }

    public function preCalcL4($value, $key, &$array)
    {
        if ($value === false or $value <= 0) {
            $value = false;
            $array['l3'] = false; //If no Social Security Tax was withheld, assume exempt and change Social Security wages to 0.
            Debug::Text('No social security tax withheld, setting wages to 0: ', __FILE__, __LINE__, __METHOD__, 10);
        } elseif ($value > $this->getSocialSecurityMaximumContribution()) {
            Debug::Text('Social security contributions exceeds maximum...', __FILE__, __LINE__, __METHOD__, 10);
            $value = $this->getSocialSecurityMaximumContribution();
        }

        return $value;
    }

    public function getSocialSecurityMaximumContribution($type = 'employee')
    {
        return $this->getPayrollDeductionObject()->getSocialSecurityMaximumContribution($type);
    }

    public function preCalcL6($value, $key, &$array)
    {
        if ($value === false or $value <= 0) {
            $value = false;
            $array['l5'] = false; //If no Medicare Tax was withheld, assume exempt change Medicare wages to 0.
            Debug::Text('No medicare tax withheld, setting wages to 0: ', __FILE__, __LINE__, __METHOD__, 10);
        }

        return $value;
    }

    public function filterMiddleName($value)
    {
        //Return just initial
        $value = substr($value, 0, 1);
        return $value;
    }

    public function filterCompanyAddress($value)
    {
        //Debug::Text('Filtering company address: '. $value, __FILE__, __LINE__, __METHOD__, 10);

        //Combine company address for multicell display.
        $retarr[] = $this->company_address1;
        if ($this->company_address2 != '') {
            $retarr[] = $this->company_address2;
        }
        $retarr[] = $this->company_city . ', ' . $this->company_state . ' ' . $this->company_zip_code;

        return implode("\n", $retarr);
    }

    public function filterAddress($value)
    {
        //Combine company address for multicell display.
        $retarr[] = $this->address1;
        if ($this->address2 != '') {
            $retarr[] = $this->address2;
        }
        $retarr[] = $this->city . ', ' . $this->state . ' ' . $this->zip_code;

        return implode("\n", $retarr);
    }

    public function filterControlNumber($value)
    {
        $value = str_pad($value, 4, 0, STR_PAD_LEFT);
        return $value;
    }

    public function _outputEFILE()
    {
        /*
         Submitter Record (RA)
         Employer Record (RE)
         Employee Wage Records (RW AND RO)
         State Wage Record (RS)
         Total Records (RT and RU)
         State Total Record (RV) - Page 64
         Final Record (RF)

         Publication 42-007: http://www.ssa.gov/employer/EFW2&EFW2C.htm

         Download: AccuWage from the bottom of this website for testing: http://www.socialsecurity.gov/employer/accuwage/index.html
         */

        $records = $this->getRecords();

        //Debug::Arr($records, 'Output EFILE Records: ',__FILE__, __LINE__, __METHOD__, 10);

        if (is_array($records) and count($records) > 0) {
            $retval = $this->padLine($this->_compileRA());
            $retval .= $this->padLine($this->_compileRE());

            $total = Misc::preSetArrayValues(new stdClass(), array('total', 'l1', 'l2', 'l3', 'l4', 'l5', 'l6', 'l7', 'l10', 'l12d', 'l12e', 'l12f', 'l12g', 'l12h', 'l12w', 'l12aa', 'l12bb', 'l12dd'), 0);

            $i = 0;
            foreach ($records as $w2_data) {
                $this->arrayToObject($w2_data); //Convert record array to object

                $retval .= $this->padLine($this->_compileRW());
                foreach (range('a', 'z') as $z) {
                    if (!isset($state_total[$z])) {
                        $state_total[$z] = Misc::preSetArrayValues(new stdClass(), array('total', 'state_taxable_wages', 'state_income_tax'), 0);
                    }
                    $retval .= $this->padLine($this->_compileRS($z));

                    $state_total[$z]->total += 1;
                    $state_total[$z]->state_taxable_wages += $this->{'l16' . $z};
                    $state_total[$z]->state_income_tax += $this->{'l17' . $z};
                }

                $total->total += 1;
                $total->l1 += $this->l1;
                $total->l2 += $this->l2;
                $total->l3 += $this->l3;
                $total->l4 += $this->l4;
                $total->l5 += $this->l5;
                $total->l6 += $this->l6;
                $total->l7 += $this->l7;
                $total->l10 += $this->l10;
                $total->l12d += $this->_getL12AmountByCode('D');
                $total->l12e += $this->_getL12AmountByCode('E');
                $total->l12f += $this->_getL12AmountByCode('F');
                $total->l12g += $this->_getL12AmountByCode('G');
                $total->l12h += $this->_getL12AmountByCode('H');
                $total->l12w += $this->_getL12AmountByCode('W');
                $total->l12aa += $this->_getL12AmountByCode('AA');
                $total->l12bb += $this->_getL12AmountByCode('BB');
                $total->l12dd += $this->_getL12AmountByCode('DD');

                $i++;
            }

            $retval .= $this->padLine($this->_compileRT($total));
            foreach (range('a', 'z') as $z) {
                $retval .= $this->padLine($this->_compileRV($state_total[$z], $z)); //State Total Record
            }
            $retval .= $this->padLine($this->_compileRF($total->total));
        }

        if (isset($retval)) {
            return $retval;
        }

        return false;
    }

    public function _compileRA()
    {
        $line[] = 'RA'; //RA Record
        $line[] = $this->padRecord($this->stripNonNumeric($this->ein), 9, 'N'); //EIN
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->efile_user_id), 8, 'AN'); //User ID
        $line[] = $this->padRecord('', 4, 'AN'); //Software Vendor code
        $line[] = $this->padRecord('', 5, 'AN'); //Blank
        $line[] = '0'; //Resub
        $line[] = $this->padRecord('', 6, 'AN'); //Blank
        $line[] = '98'; //Software Code
        $line[] = $this->padRecord($this->trade_name, 57, 'AN'); //Company Name
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address2), 22, 'AN'); //Company Location Address
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address1), 22, 'AN'); //Company Delivery Address
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_city), 22, 'AN'); //Company City
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_state), 2, 'AN'); //Company State
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_zip_code), 5, 'AN'); //Company Zip Code
        $line[] = $this->padRecord('', 4, 'AN'); //Company Zip Code Extension
        $line[] = $this->padRecord('', 5, 'AN'); //Blank
        $line[] = $this->padRecord('', 23, 'AN'); //Foreign State/Province
        $line[] = $this->padRecord('', 15, 'AN'); //Foreign Postal Code
        $line[] = $this->padRecord('', 2, 'AN'); //Company Country, fill with blanks if its the US
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->trade_name), 57, 'AN'); //Submitter organization.
        $line[] = $this->padRecord($this->stripNonAlphaNumeric(($this->company_address2 != '') ? $this->company_address2 : $this->company_address1), 22, 'AN'); //Submitter Location Address
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address1), 22, 'AN'); //Submitter Delivery Address
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_city), 22, 'AN'); //Submitter City
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_state), 2, 'AN'); //Submitter State
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_zip_code), 5, 'AN'); //Submitter Zip Code
        $line[] = $this->padRecord('', 4, 'AN'); //Submitter Zip Code Extension
        $line[] = $this->padRecord('', 5, 'AN'); //Blank
        $line[] = $this->padRecord('', 23, 'AN'); //Submitter Foreign State/Province
        $line[] = $this->padRecord('', 15, 'AN'); //Submitter Foreign Postal Code
        $line[] = $this->padRecord('', 2, 'AN'); //Submitter Country, fill with blanks if its the US
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->contact_name), 27, 'AN'); //Contact Name
        $line[] = $this->padRecord($this->stripNonNumeric($this->contact_phone), 15, 'AN'); //Contact Phone
        $line[] = $this->padRecord($this->stripNonNumeric($this->contact_phone_ext), 5, 'AN'); //Contact Phone Ext
        $line[] = $this->padRecord('', 3, 'AN'); //Blank
        $line[] = $this->padRecord($this->contact_email, 40, 'AN'); //Contact Email
        $line[] = $this->padRecord('', 3, 'AN'); //Blank
        $line[] = $this->padRecord('', 10, 'AN'); //Contact Fax
        $line[] = $this->padRecord('', 1, 'AN'); //Blank
        $line[] = $this->padRecord('L', 1, 'AN'); //PreParers Code
        $line[] = $this->padRecord('', 12, 'AN'); //Blank

        $retval = implode(($this->debug == true) ? ',' : '', $line);
        Debug::Text('RA Record:' . $retval, __FILE__, __LINE__, __METHOD__, 10);

        return $retval;
    }

    public function _compileRE()
    {
        $line[] = 'RE'; //RE Record [1-2]
        $line[] = $this->padRecord($this->stripNonNumeric($this->year), 4, 'N'); //Tax Year [3-6]
        $line[] = $this->padRecord('', 1, 'AN'); //Agent Indicator [7-8]
        $line[] = $this->padRecord($this->stripNonNumeric($this->ein), 9, 'N'); //EIN [9-17]
        $line[] = $this->padRecord('', 9, 'AN'); //Agent for EIN
        $line[] = $this->padRecord('0', 1, 'N'); //Terminating Business
        $line[] = $this->padRecord('', 4, 'AN'); //Establishment Number
        $line[] = $this->padRecord('', 9, 'AN'); //Other EIN
        $line[] = $this->padRecord($this->trade_name, 57, 'AN'); //Company Name [40-96]
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address2), 22, 'AN'); //Company Location Address [97-118]
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address1), 22, 'AN'); //Company Delivery Address [119-140]
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_city), 22, 'AN'); //Company City [141-162]
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_state), 2, 'AN'); //Company State [163-164]
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_zip_code), 5, 'AN'); //Company Zip Code [165-169]
        $line[] = $this->padRecord('', 4, 'AN'); //Company Zip Code Extension [170-173]
        $line[] = $this->padRecord(strtoupper($this->kind_of_employer), 1, 'AN'); //Kind of Employer
        $line[] = $this->padRecord('', 4, 'AN'); //Blank
        $line[] = $this->padRecord('', 23, 'AN'); //Foreign State/Province
        $line[] = $this->padRecord('', 15, 'AN'); //Foreign Postal Code
        $line[] = $this->padRecord('', 2, 'AN'); //Country, fill with blanks if its the US
        $line[] = $this->padRecord('R', 1, 'AN'); //Employment Code - 941 Form
        $line[] = $this->padRecord('', 1, 'AN'); //Tax Jurisdiction
        $line[] = $this->padRecord(($this->l13c == '') ? 0 : 1, 1, 'N'); //Third Party Sick Pay
        $line[] = $this->padRecord('', 291, 'AN'); //Blank

        $retval = implode(($this->debug == true) ? ',' : '', $line);
        Debug::Text('RE Record:' . $retval, __FILE__, __LINE__, __METHOD__, 10);

        return $retval;
    }

    public function _compileRW()
    {
        $line[] = 'RW'; //RW Record
        $line[] = $this->padRecord($this->stripNonNumeric($this->ssn), 9, 'N'); //SSN
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->first_name), 15, 'AN'); //First Name
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->middle_name), 15, 'AN'); //Middle Name
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->last_name), 20, 'AN'); //Last Name
        $line[] = $this->padRecord('', 4, 'AN'); //Suffix
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address2), 22, 'AN'); //Location Address
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address1), 22, 'AN'); //Delivery Address
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->city), 22, 'AN'); //City
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->state), 2, 'AN'); //State
        $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->zip_code), 5, 'AN'); //Zip
        $line[] = $this->padRecord('', 4, 'AN'); //Zip Extension
        $line[] = $this->padRecord('', 5, 'AN'); //Blank
        $line[] = $this->padRecord('', 23, 'AN'); //Foreign State/Province
        $line[] = $this->padRecord('', 15, 'AN'); //Foreign Postal Code
        $line[] = $this->padRecord('', 2, 'AN'); //Country, fill with blanks if its the US
        $line[] = $this->padRecord($this->removeDecimal($this->l1), 11, 'N'); //Wages, Tips and Other Compensation
        $line[] = $this->padRecord($this->removeDecimal($this->l2), 11, 'N'); //Federal Income Tax
        $line[] = $this->padRecord($this->removeDecimal($this->l3), 11, 'N'); //Social Security Wages
        $line[] = $this->padRecord($this->removeDecimal($this->l4), 11, 'N'); //Social Security Tax
        $line[] = $this->padRecord($this->removeDecimal($this->l5), 11, 'N'); //Medicare Wages and Tips
        $line[] = $this->padRecord($this->removeDecimal($this->l6), 11, 'N'); //Medicare Tax
        $line[] = $this->padRecord($this->removeDecimal($this->l7), 11, 'N'); //Social Security Tips
        $line[] = $this->padRecord('', 11, 'N'); //Advanced EIC
        $line[] = $this->padRecord($this->removeDecimal($this->l10), 11, 'N'); //Dependant Care Benefits
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('D')), 11, 'N'); //Deferred Compensation Contributions to 401K //Code D in any of the Box 12(a throug d).
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('E')), 11, 'N'); //Deferred Compensation Contributions to 403(b) //Code E in any of the Box 12(a throug d).
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('F')), 11, 'N'); //Deferred Compensation Contributions to 408(k)(6) //Code F in any of the Box 12(a throug d).
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('G')), 11, 'N'); //Deferred Compensation Contributions to 457(b) //Code G in any of the Box 12(a throug d).
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('H')), 11, 'N'); //Deferred Compensation Contributions to 501(c)(18)(D) //Code H in any of the Box 12(a throug d).
        $line[] = $this->padRecord('', 11, 'AN'); //Blank
        $line[] = $this->padRecord('', 11, 'N'); //Non-qualified Plan Section 457
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('W')), 11, 'N'); //Employer Contributions to Health Savings Account //Code W in any of the Box 12(a throug d).
        $line[] = $this->padRecord('', 11, 'N'); //Non-qualified NOT Plan Section 457
        $line[] = $this->padRecord('', 11, 'N'); //Non taxable combat pay
        $line[] = $this->padRecord('', 11, 'AN'); //Blank
        $line[] = $this->padRecord('', 11, 'N'); //Employer Cost of Premiums for Group Term Life Insurance over $50K
        $line[] = $this->padRecord('', 11, 'N'); //Income from the Exercise of Nonstatutory Stock Options
        $line[] = $this->padRecord('', 11, 'N'); //Deferrals Under a Section 409A non-qualified plan
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('AA')), 11, 'N'); //Desiginated Roth Contributions under a section 401K //Code AA in any of the Box 12(a throug d).
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('BB')), 11, 'N'); //Desiginated Roth Contributions under a section 403B //Code BB in any of the Box 12(a throug d).
        $line[] = $this->padRecord($this->removeDecimal($this->_getL12AmountByCode('DD')), 11, 'N'); //Cost of Employer Sponsored Health Coverage //Code DD in any of the Box 12(a throug d).
        $line[] = $this->padRecord('', 12, 'AN'); //Blank
        $line[] = $this->padRecord('0', 1, 'N'); //Statutory Employee
        $line[] = $this->padRecord('', 1, 'AN'); //Blank
        $line[] = $this->padRecord('0', 1, 'N'); //Retirement Plan Indicator
        $line[] = $this->padRecord(($this->l13c == '') ? 0 : 1, 1, 'N'); //3rd Party Sick Pay Indicator
        $line[] = $this->padRecord('', 23, 'AN'); //Blank

        $retval = implode(($this->debug == true) ? ',' : '', $line);
        Debug::Text('RW Record:' . $retval, __FILE__, __LINE__, __METHOD__, 10);

        return $retval;
    }

    public function _getL12AmountByCode($code)
    {
        Debug::Text('Checking for Code:' . $code, __FILE__, __LINE__, __METHOD__, 10);
        foreach (range('a', 'z') as $z) {
            if (isset($this->{'l12' . $z . '_code'}) and $this->{'l12' . $z . '_code'} == $code) {
                Debug::Text('Found amount for Code:' . $code, __FILE__, __LINE__, __METHOD__, 10);
                return $this->{'l12' . $z};
            }
        }

        Debug::Text('Not amount found, Code:' . $code, __FILE__, __LINE__, __METHOD__, 10);

        return false;
    }

    public function _compileRS($id)
    {
        $l15_state = 'l15' . $id . '_state';
        $l15_state_id = 'l15' . $id . '_state_id';
        $l16 = 'l16' . $id;
        $l17 = 'l17' . $id;
        $l18 = 'l18' . $id;
        $l19 = 'l19' . $id;
        $l20 = 'l20' . $id . '_district';

        if (!isset($this->$l15_state)) {
            return false;
        }


        Debug::Text('RS Record State: ' . $this->efile_state, __FILE__, __LINE__, __METHOD__, 10);
        switch (strtolower($this->efile_state)) {
            case 'ga': //Georgia
                $line[] = 'RS'; //RS Record
                $line[] = $this->padRecord($this->_getStateNumericCode($this->$l15_state), 2, 'N'); //State Code
                $line[] = $this->padRecord('', 5, 'AN'); //Tax Entity Code (Leave Blank)
                $line[] = $this->padRecord($this->stripNonNumeric($this->ssn), 9, 'N'); //SSN
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->first_name), 15, 'AN'); //First Name
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->middle_name), 15, 'AN'); //Middle Name
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->last_name), 20, 'AN'); //Last Name
                $line[] = $this->padRecord('', 4, 'AN'); //Suffix
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address2), 22, 'AN'); //Location Address
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address1), 22, 'AN'); //Delivery Address
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->city), 22, 'AN'); //City
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->state), 2, 'AN'); //State
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->zip_code), 5, 'AN'); //Zip
                $line[] = $this->padRecord('', 4, 'AN'); //Zip Extension
                $line[] = $this->padRecord('', 5, 'AN'); //Blank
                $line[] = $this->padRecord('', 23, 'AN'); //Foreign State/Province
                $line[] = $this->padRecord('', 15, 'AN'); //Foreign Postal Code
                $line[] = $this->padRecord('', 2, 'AN'); //Country, fill with blanks if its the US

                //Unemployment reporting
                $line[] = $this->padRecord('', 2, 'AN'); //Optional Code
                $line[] = $this->padRecord('', 6, 'AN'); //Reporting Period
                $line[] = $this->padRecord('', 11, 'AN'); //State Quarterly Unemployment Total
                $line[] = $this->padRecord('', 11, 'AN'); //State Quarterly Unemployment Insurance
                $line[] = $this->padRecord('', 2, 'AN'); //Number of weeks worked
                $line[] = $this->padRecord('', 8, 'AN'); //Date first employed
                $line[] = $this->padRecord('', 8, 'AN'); //Date of separation
                $line[] = $this->padRecord('', 5, 'AN'); //Blank
                $line[] = $this->padRecord('', 20, 'AN'); //State Employer Account Number
                $line[] = $this->padRecord('', 6, 'AN'); //Blank

                //Income Tax Reporting
                $line[] = $this->padRecord($this->_getStateNumericCode($this->$l15_state), 2, 'N'); //State Code
                $line[] = $this->padRecord($this->removeDecimal($this->$l16), 11, 'N'); //State Taxable Wages
                $line[] = $this->padRecord($this->removeDecimal($this->$l17), 11, 'N'); //State income tax
                $line[] = $this->padRecord('12/31/' . $this->year, 10, 'N'); //Period End Date (last day of the year)
                $line[] = $this->padRecord('', 1, 'AN'); //Tax Type Code
                $line[] = $this->padRecord('', 11, 'AN'); //Local Wages (blank)
                $line[] = $this->padRecord('', 11, 'AN'); //Local Income Tax (blank)
                $line[] = $this->padRecord(str_replace(array('-', ' '), '', strtoupper($this->$l15_state_id)), 9, 'AN'); //Withholding Number, no hyphen and upper case alpha
                $line[] = $this->padRecord($this->trade_name, 57, 'AN'); //Company Name
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address2), 22, 'AN'); //Company Location Address
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_address1), 22, 'AN'); //Company Delivery Address
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_city), 22, 'AN'); //Company City
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_state), 2, 'AN'); //Company State
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->company_zip_code), 5, 'AN'); //Company Zip Code
                $line[] = $this->padRecord('', 4, 'AN'); //Company Zip Code Extension
                $line[] = $this->padRecord($this->stripNonNumeric($this->ein), 9, 'N'); //EIN
                $line[] = $this->padRecord('', 5, 'AN'); //Blank
                $line[] = $this->padRecord('', 25, 'AN'); //Blank

                break;
            case 'oh': //Ohio - They share with Federal SSA. This is for RITA/Local format instead. It does not include school district taxes.
                //File format specifications: https://www.ritaohio.com/businesses/magnetic-reporting-of-w2s/
                $municipality_code = false;
                $tax_type = 'C';

                //District/City Name must contain: [NNNA] ie: [123R] or [123C] -- Where R is tax based on residence location and C is tax based on work location.
                $municipality_match = preg_match('/\[([0-9]{3})([A-Z]{1})\]/i', $this->$l20, $matches);
                if (isset($matches[0])) {
                    if (isset($matches[1])) {
                        $municipality_code = $matches[1];
                    }
                    if (isset($matches[2])) {
                        $tax_type = $matches[2];
                    }
                }

                if ($municipality_code != '' and $tax_type != '') {
                    //Withholding Number for State format is the State ID number.
                    $line[] = 'RS'; //RS Record
                    $line[] = $this->padRecord($this->_getStateNumericCode($this->$l15_state), 2, 'N'); //State Code
                    $line[] = $this->padRecord('RO' . $municipality_code, 5, 'AN'); //Tax Entity Code (Leave Blank) [5-9]
                    $line[] = $this->padRecord($this->stripNonNumeric($this->ssn), 9, 'N'); //SSN [10-18]
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->first_name), 15, 'AN'); //First Name
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->middle_name), 15, 'AN'); //Middle Name
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->last_name), 20, 'AN'); //Last Name
                    $line[] = $this->padRecord('', 4, 'AN'); //Suffix
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address2), 22, 'AN'); //Location Address
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address1), 22, 'AN'); //Delivery Address
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->city), 22, 'AN'); //City
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->state), 2, 'AN'); //State
                    $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->zip_code), 5, 'AN'); //Zip
                    $line[] = $this->padRecord('', 4, 'AN'); //Zip Extension [146-149]
                    $line[] = $this->padRecord('', 5, 'AN'); //Blank
                    $line[] = $this->padRecord('', 23, 'AN'); //Foreign State/Province
                    $line[] = $this->padRecord('', 15, 'AN'); //Foreign Postal Code
                    $line[] = $this->padRecord('', 2, 'AN'); //Country, fill with blanks if its the US

                    //Unemployment reporting: Starts at 194
                    $line[] = $this->padRecord('', 2, 'AN'); //Optional Code
                    $line[] = $this->padRecord('', 6, 'AN'); //Reporting Period
                    $line[] = $this->padRecord('', 11, 'AN'); //State Quarterly Unemployment Total
                    $line[] = $this->padRecord('', 11, 'AN'); //State Quarterly Unemployment Insurance
                    $line[] = $this->padRecord('', 2, 'AN'); //Number of weeks worked
                    $line[] = $this->padRecord('', 8, 'AN'); //Date first employed
                    $line[] = $this->padRecord('', 8, 'AN'); //Date of separation
                    $line[] = $this->padRecord('', 5, 'AN'); //Blank
                    $line[] = $this->padRecord($this->stripNonNumeric($this->$l15_state_id), 20, 'N'); //State Employer Account Number
                    $line[] = $this->padRecord('', 6, 'AN'); //Blank

                    //Income Tax Reporting: Starts at 273
                    $line[] = $this->padRecord($this->_getStateNumericCode($this->$l15_state), 2, 'N'); //State Code [273-275]
                    $line[] = $this->padRecord($this->removeDecimal($this->$l16), 11, 'N'); //State Taxable Wages [276-286]
                    $line[] = $this->padRecord($this->removeDecimal($this->$l17), 11, 'N'); //State income tax [287-297]
                    $line[] = $this->padRecord('', 10, 'N'); //Other State Data [298-307]
                    $line[] = $this->padRecord(strtoupper($tax_type), 1, 'AN'); //Tax Type Code [308] //C=Employment Municipality, R=Residence Municapility
                    $line[] = $this->padRecord($this->removeDecimal($this->$l18), 11, 'N'); //Local Wages [309-319]
                    $line[] = $this->padRecord($this->removeDecimal($this->$l19), 11, 'N'); //Local Income Tax [320-330]
                    $line[] = $this->padRecord('', 7, 'AN'); //State Control Number
                    $line[] = $this->padRecord('', 75, 'AN'); //Supplemental Data 1
                    $line[] = $this->padRecord('', 75, 'AN'); //Supplemental Data 2
                    $line[] = $this->padRecord('', 25, 'AN'); //Blank
                } else {
                    Debug::Text('Skipping RS Record due to incorrect Municipality Code: ' . $municipality_code . ' Tax Type: ' . $tax_type, __FILE__, __LINE__, __METHOD__, 10);
                }
                break;
            default: //Federal
                //Withholding Number for State format is the State ID number.
                $line[] = 'RS'; //RS Record
                $line[] = $this->padRecord($this->_getStateNumericCode($this->$l15_state), 2, 'N'); //State Code
                $line[] = $this->padRecord('', 5, 'AN'); //Tax Entity Code (Leave Blank)
                $line[] = $this->padRecord($this->stripNonNumeric($this->ssn), 9, 'N'); //SSN
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->first_name), 15, 'AN'); //First Name
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->middle_name), 15, 'AN'); //Middle Name
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->last_name), 20, 'AN'); //Last Name
                $line[] = $this->padRecord('', 4, 'AN'); //Suffix
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address2), 22, 'AN'); //Location Address
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->address1), 22, 'AN'); //Delivery Address
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->city), 22, 'AN'); //City
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->state), 2, 'AN'); //State
                $line[] = $this->padRecord($this->stripNonAlphaNumeric($this->zip_code), 5, 'AN'); //Zip
                $line[] = $this->padRecord('', 4, 'AN'); //Zip Extension
                $line[] = $this->padRecord('', 5, 'AN'); //Blank
                $line[] = $this->padRecord('', 23, 'AN'); //Foreign State/Province
                $line[] = $this->padRecord('', 15, 'AN'); //Foreign Postal Code
                $line[] = $this->padRecord('', 2, 'AN'); //Country, fill with blanks if its the US

                //Unemployment reporting
                $line[] = $this->padRecord('', 2, 'AN'); //Optional Code
                $line[] = $this->padRecord('', 6, 'AN'); //Reporting Period
                $line[] = $this->padRecord('', 11, 'AN'); //State Quarterly Unemployment Total
                $line[] = $this->padRecord('', 11, 'AN'); //State Quarterly Unemployment Insurance
                $line[] = $this->padRecord('', 2, 'AN'); //Number of weeks worked
                $line[] = $this->padRecord('', 8, 'AN'); //Date first employed
                $line[] = $this->padRecord('', 8, 'AN'); //Date of separation
                $line[] = $this->padRecord('', 5, 'AN'); //Blank
                $line[] = $this->padRecord($this->stripNonNumeric($this->$l15_state_id), 20, 'N'); //State Employer Account Number
                $line[] = $this->padRecord('', 6, 'AN'); //Blank

                //Income Tax Reporting
                $line[] = $this->padRecord($this->_getStateNumericCode($this->$l15_state), 2, 'N'); //State Code
                $line[] = $this->padRecord($this->removeDecimal($this->$l16), 11, 'N'); //State Taxable Wages
                $line[] = $this->padRecord($this->removeDecimal($this->$l17), 11, 'N'); //State income tax
                $line[] = $this->padRecord('', 10, 'N'); //Other State Data
                $line[] = $this->padRecord('D', 1, 'AN'); //Tax Type Code
                $line[] = $this->padRecord($this->removeDecimal($this->$l18), 11, 'N'); //Local Wages
                $line[] = $this->padRecord($this->removeDecimal($this->$l19), 11, 'N'); //Local Income Tax
                $line[] = $this->padRecord('', 7, 'AN'); //State Control Number
                $line[] = $this->padRecord('', 75, 'AN'); //Supplemental Data 1
                $line[] = $this->padRecord('', 75, 'AN'); //Supplemental Data 2
                $line[] = $this->padRecord('', 25, 'AN'); //Blank
                break;
        }

        if (isset($line)) {
            $retval = implode(($this->debug == true) ? ',' : '', $line);
            Debug::Text('RS Record: ' . $retval, __FILE__, __LINE__, __METHOD__, 10);

            return $retval;
        } else {
            Debug::Text('Skipping RS Record... ', __FILE__, __LINE__, __METHOD__, 10);
        }
    }

    public function _getStateNumericCode($state)
    {
        $map = array(
            'AL' => '01',
            'AK' => '02',
            'AZ' => '04',
            'AR' => '05',
            'CA' => '06',
            'CO' => '08',
            'CT' => '09',
            'DE' => '10',
            'DC' => '11',
            'FL' => '12',
            'GA' => '13',
            'HI' => '15',
            'ID' => '16',
            'IL' => '17',
            'IN' => '18',
            'IA' => '19',
            'KS' => '20',
            'KY' => '21',
            'LA' => '22',
            'ME' => '23',
            'MD' => '24',
            'MA' => '25',
            'MI' => '26',
            'MN' => '27',
            'MS' => '28',
            'MO' => '29',
            'MT' => '30',
            'NE' => '31',
            'NV' => '32',
            'NH' => '33',
            'NM' => '34',
            'NJ' => '35',
            'NY' => '36',
            'NC' => '37',
            'ND' => '38',
            'OH' => '39',
            'OK' => '40',
            'OR' => '41',
            'PA' => '42',
            'RI' => '44',
            'SC' => '45',
            'SD' => '46',
            'TN' => '47',
            'TX' => '48',
            'UT' => '49',
            'VT' => '50',
            'VA' => '51',
            'WA' => '53',
            'WV' => '54',
            'WI' => '55',
            'WY' => '56'
        );

        if (isset($map[strtoupper($state)])) {
            return $map[strtoupper($state)];
        }

        return false;
    }

    //ID is the state identifier like: a, b, c, d,...

    public function _compileRT($total)
    {
        $line[] = 'RT'; //RT Record
        $line[] = $this->padRecord($total->total, 7, 'N'); //Total RW records.
        $line[] = $this->padRecord($this->removeDecimal($total->l1), 15, 'N'); //Wages, Tips and Other Compensation
        $line[] = $this->padRecord($this->removeDecimal($total->l2), 15, 'N'); //Federal Income Tax
        $line[] = $this->padRecord($this->removeDecimal($total->l3), 15, 'N'); //Social Security Wages
        $line[] = $this->padRecord($this->removeDecimal($total->l4), 15, 'N'); //Social Security Tax
        $line[] = $this->padRecord($this->removeDecimal($total->l5), 15, 'N'); //Medicare Wages and Tips
        $line[] = $this->padRecord($this->removeDecimal($total->l6), 15, 'N'); //Medicare Tax
        $line[] = $this->padRecord($this->removeDecimal($total->l7), 15, 'N'); //Social Security Tips
        $line[] = $this->padRecord('', 15, 'N'); //Advanced EIC
        $line[] = $this->padRecord($this->removeDecimal($total->l10), 15, 'N'); //Dependant Care Benefits
        $line[] = $this->padRecord($this->removeDecimal($total->l12d), 15, 'N'); //Deferred Compensation Contributions to 401K
        $line[] = $this->padRecord($this->removeDecimal($total->l12e), 15, 'N'); //Deferred Compensation Contributions to 403(b)
        $line[] = $this->padRecord($this->removeDecimal($total->l12f), 15, 'N'); //Deferred Compensation Contributions to 408(k)(6)
        $line[] = $this->padRecord($this->removeDecimal($total->l12g), 15, 'N'); //Deferred Compensation Contributions to 457(b)
        $line[] = $this->padRecord($this->removeDecimal($total->l12h), 15, 'N'); //Deferred Compensation Contributions to 501(c)(18)(D)
        $line[] = $this->padRecord('', 15, 'AN'); //Blank
        $line[] = $this->padRecord('', 15, 'N'); //Non-qualified Plan Section 457
        $line[] = $this->padRecord($this->removeDecimal($total->l12w), 15, 'N'); //Employer Contributions to Health Savings Account
        $line[] = $this->padRecord('', 15, 'N'); //Non-qualified NOT Plan Section 457
        $line[] = $this->padRecord('', 15, 'N'); //Non taxable combat pay
        $line[] = $this->padRecord($this->removeDecimal($total->l12dd), 15, 'N'); //Cost of Employer Sponsored Health Coverage
        $line[] = $this->padRecord('', 15, 'N'); //Employer Cost of Premiums for Group Term Life Insurance over $50K
        $line[] = $this->padRecord('', 15, 'N'); //3rd party sick pay.
        $line[] = $this->padRecord('', 15, 'N'); //Income from the Exercise of Nonstatutory Stock Options
        $line[] = $this->padRecord('', 15, 'N'); //Deferrals Under a Section 409A non-qualified plan
        $line[] = $this->padRecord($this->removeDecimal($total->l12aa), 15, 'N'); //Desiginated Roth Contributions under a section 401K
        $line[] = $this->padRecord($this->removeDecimal($total->l12bb), 15, 'N'); //Desiginated Roth Contributions under a section 403B
        $line[] = $this->padRecord('', 113, 'AN'); //Blank

        $retval = implode(($this->debug == true) ? ',' : '', $line);
        Debug::Text('RT Record:' . $retval, __FILE__, __LINE__, __METHOD__, 10);

        return $retval;
    }

    public function _compileRV($total, $id)
    {
        $l15_state = 'l15' . $id . '_state';
        if (!isset($this->$l15_state)) {
            return false;
        }

        if ($total->total > 0) {
            $line[] = 'RV'; //RT Record
            $line[] = $this->padRecord($total->total, 7, 'N'); //Total RW records.
            $line[] = $this->padRecord($this->removeDecimal($total->state_taxable_wages), 15, 'N'); //State Wages, Tips and Other Compensation
            $line[] = $this->padRecord($this->removeDecimal($total->state_income_tax), 15, 'N'); //State Income Tax
            $line[] = $this->padRecord('', 473, 'AN'); //Blank

            $retval = implode(($this->debug == true) ? ',' : '', $line);
            Debug::Text('RV Record:' . $retval, __FILE__, __LINE__, __METHOD__, 10);
        }

        return $retval;
    }

    public function _compileRF($total_records)
    {
        $line[] = 'RF'; //RF Record
        $line[] = $this->padRecord('', 5, 'AN'); //Blank
        $line[] = $this->padRecord($total_records, 9, 'N'); //Total RW records.
        $line[] = $this->padRecord('', 496, 'AN'); //Blank

        $retval = implode(($this->debug == true) ? ',' : '', $line);
        Debug::Text('RF Record:' . $retval, __FILE__, __LINE__, __METHOD__, 10);

        return $retval;
    }

    public function _outputPDF()
    {
        //Initialize PDF with template.
        $pdf = $this->getPDFObject();

        if ($this->getShowBackground() == true) {
            $pdf->setSourceFile($this->getTemplateDirectory() . DIRECTORY_SEPARATOR . $this->pdf_template);

            for ($tp = 1; $tp <= 11; $tp++) {
                $this->template_index[$tp] = $pdf->ImportPage($tp);
            }
        }

        if ($this->year == '') {
            $this->year = $this->getYear();
        }

        if ($this->getType() == 'government') {
            $employees_per_page = 2;
            $n = 2; //Don't loop the same employee.
            $form_template_pages = array(2, 3, 10); //Template pages to use.
        } else {
            $employees_per_page = 1;
            $n = 1; //Loop the same employee twice.
            $form_template_pages = array(4, 6, 8); //Template pages to use.
        }

        //Get location map, start looping over each variable and drawing
        $records = $this->getRecords();

        if (is_array($records) and count($records) > 0) {
            $template_schema = $this->getTemplateSchema();

            foreach ($form_template_pages as $form_template_page) {
                //Set the template used.
                $template_schema[0]['template_page'] = $form_template_page;

                if ($this->getShowBackground() == true and $this->getType() == 'government' and count($records) > 1) {
                    $template_schema[0]['combine_templates'] = array(
                        array('template_page' => $form_template_page, 'x' => 0, 'y' => 0),
                        array('template_page' => $form_template_page, 'x' => 0, 'y' => 400) //Place two templates on the same page.
                    );
                }

                $e = 0;
                foreach ($records as $employee_data) {
                    //Debug::Arr($employee_data, 'Employee Data: ', __FILE__, __LINE__, __METHOD__,10);
                    $employee_data['control_number'] = ($e + 1);
                    $this->arrayToObject($employee_data); //Convert record array to object

                    for ($i = 0; $i < $n; $i++) {
                        $this->page_offsets = array(0, 0);

                        if (($employees_per_page == 1 and $i > 0)
                            or ($employees_per_page == 2 and $e % 2 != 0)
                        ) {
                            $this->page_offsets = array(0, 400);
                        }

                        foreach ($template_schema as $field => $schema) {
                            $this->Draw($this->$field, $schema);
                        }
                    }

                    if ($employees_per_page == 1 or ($employees_per_page == 2 and $e % $employees_per_page != 0)) {
                        $this->resetTemplatePage();
                        //if ( $this->getShowInstructionPage() == TRUE ) {
                        //	$this->addPage( array('template_page' => 2) );
                        //}
                    }

                    $e++;
                }
            }
        }

        $this->clearRecords();

        return true;
    }

    //Fixed length field EFW2 format

    public function getType()
    {
        if (isset($this->type)) {
            return $this->type;
        }

        return false;
    }

    public function getTemplateSchema($name = null)
    {
        $template_schema = array(
            array(
                //'page' => 1,
                //'template_page' => array(
                //						array( 'template_page' => 2, 'x'=> 0, 'y' => 0),
                //						array( 'template_page' => 2, 'x'=> 0, 'y' => 350), //Place two templates on the same page.
                //						),
                'value' => $this->year,
                'on_background' => true,
                'coordinates' => array(
                    'x' => 260,
                    'y' => 340,
                    'h' => 20,
                    'w' => 120,
                    'halign' => 'C',
                    'fill_color' => array(255, 255, 255),
                ),
                'font' => array(
                    'size' => 18,
                    'type' => 'B')
            ),
            //Finish initializing page 1.
            'ssn' => array(
                'function' => array('formatSSN', 'drawNormal'),
                'coordinates' => array(
                    'x' => 153,
                    'y' => 47,
                    'h' => 15,
                    'w' => 127,
                    'halign' => 'C',
                ),
            ),
            'ein' => array(
                'function' => array('formatEIN', 'drawNormal'),
                'coordinates' => array(
                    'x' => 38,
                    'y' => 70,
                    'h' => 15,
                    'w' => 280,
                    'halign' => 'L',
                ),
            ),
            'trade_name' => array(
                'coordinates' => array(
                    'x' => 38,
                    'y' => 92,
                    'h' => 15,
                    'w' => 280,
                    'halign' => 'L',
                ),
            ),
            'company_address' => array(
                'function' => array('filterCompanyAddress', 'drawNormal'),
                'coordinates' => array(
                    'x' => 38,
                    'y' => 107,
                    'h' => 48,
                    'w' => 280,
                    'halign' => 'L',
                ),
                'font' => array(
                    'size' => 8,
                    'type' => ''),
                'multicell' => true,
            ),
            'control_number' => array(
                'function' => array('filterControlNumber', 'drawNormal'),
                'coordinates' => array(
                    'x' => 38,
                    'y' => 165,
                    'h' => 15,
                    'w' => 127,
                    'halign' => 'L',
                ),
            ),


            'first_name' => array(
                'coordinates' => array(
                    'x' => 38,
                    'y' => 189,
                    'h' => 15,
                    'w' => 122,
                    'halign' => 'L',
                ),
            ),
            'middle_name' => array(
                'function' => array('filterMiddleName', 'drawNormal'),
                'coordinates' => array(
                    'x' => 162,
                    'y' => 189,
                    'h' => 15,
                    'w' => 10,
                    'halign' => 'L',
                ),
            ),
            'last_name' => array(
                'coordinates' => array(
                    'x' => 175,
                    'y' => 189,
                    'h' => 15,
                    'w' => 127,
                    'halign' => 'L',
                ),
            ),
            'address' => array(
                'function' => array('filterAddress', 'drawNormal'),
                'coordinates' => array(
                    'x' => 38,
                    'y' => 205,
                    'h' => 68,
                    'w' => 280,
                    'halign' => 'L',
                ),
                'font' => array(
                    'size' => 8,
                    'type' => ''),
                'multicell' => true,
            ),
            'l1' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 335,
                    'y' => 70,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l2' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 459,
                    'y' => 70,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l3' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 335,
                    'y' => 94,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l4' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 459,
                    'y' => 94,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l5' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 335,
                    'y' => 118,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l6' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 459,
                    'y' => 118,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l7' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 335,
                    'y' => 142,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l8' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 459,
                    'y' => 142,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l9' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 335,
                    'y' => 166,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l10' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 459,
                    'y' => 166,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),
            'l11' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 335,
                    'y' => 189,
                    'h' => 15,
                    'w' => 115,
                    'halign' => 'R',
                ),
            ),


            'l12a_code' => array(
                'coordinates' => array(
                    'x' => 460,
                    'y' => 189,
                    'h' => 15,
                    'w' => 30,
                    'halign' => 'C',
                ),
            ),
            'l12a' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 491,
                    'y' => 189,
                    'h' => 15,
                    'w' => 83,
                    'halign' => 'R',
                ),
            ),


            'l12b_code' => array(
                'coordinates' => array(
                    'x' => 460,
                    'y' => 214,
                    'h' => 15,
                    'w' => 30,
                    'halign' => 'C',
                ),
            ),
            'l12b' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 491,
                    'y' => 214,
                    'h' => 15,
                    'w' => 83,
                    'halign' => 'R',
                ),
            ),
            'l12c_code' => array(
                'coordinates' => array(
                    'x' => 460,
                    'y' => 238,
                    'h' => 15,
                    'w' => 30,
                    'halign' => 'C',
                ),
            ),
            'l12c' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 491,
                    'y' => 238,
                    'h' => 15,
                    'w' => 83,
                    'halign' => 'R',
                ),
            ),
            'l12d_code' => array(
                'coordinates' => array(
                    'x' => 460,
                    'y' => 262,
                    'h' => 15,
                    'w' => 30,
                    'halign' => 'C',
                ),
            ),
            'l12d' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 491,
                    'y' => 262,
                    'h' => 15,
                    'w' => 83,
                    'halign' => 'R',
                ),
            ),

            'l13a' => array(
                'function' => 'drawCheckBox',
                'coordinates' => array(
                    array(
                        'x' => 348,
                        'y' => 216,
                        'h' => 11,
                        'w' => 10,
                        'halign' => 'C',
                    )
                ),
            ),
            'l13b' => array(
                'function' => 'drawCheckBox',
                'coordinates' => array(
                    array(
                        'x' => 384,
                        'y' => 216,
                        'h' => 11,
                        'w' => 10,
                        'halign' => 'C',
                    )
                ),
            ),
            'l13c' => array(
                'function' => 'drawCheckBox',
                'coordinates' => array(
                    array(
                        'x' => 420,
                        'y' => 216,
                        'h' => 11,
                        'w' => 10,
                        'halign' => 'C',
                    )
                ),
            ),

            'l14a_name' => array(
                'coordinates' => array(
                    'x' => 331,
                    'y' => 238,
                    'h' => 12,
                    'w' => 40,
                    'halign' => 'L',
                ),
            ),
            'l14a' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 371,
                    'y' => 238,
                    'h' => 12,
                    'w' => 82,
                    'halign' => 'R',
                ),
            ),
            'l14b_name' => array(
                'coordinates' => array(
                    'x' => 331,
                    'y' => 250,
                    'h' => 12,
                    'w' => 40,
                    'halign' => 'L',
                ),
            ),
            'l14b' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 371,
                    'y' => 250,
                    'h' => 12,
                    'w' => 82,
                    'halign' => 'R',
                ),
            ),
            'l14c_name' => array(
                'coordinates' => array(
                    'x' => 331,
                    'y' => 262,
                    'h' => 12,
                    'w' => 40,
                    'halign' => 'L',
                ),
            ),
            'l14c' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 371,
                    'y' => 262,
                    'h' => 12,
                    'w' => 82,
                    'halign' => 'R',
                ),
            ),
            'l14d_name' => array(
                'coordinates' => array(
                    'x' => 331,
                    'y' => 274,
                    'h' => 12,
                    'w' => 40,
                    'halign' => 'L',
                ),
            ),
            'l14d' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 371,
                    'y' => 274,
                    'h' => 12,
                    'w' => 82,
                    'halign' => 'R',
                ),
            ),


            //State (Line 1)
            'l15a_state' => array(
                'coordinates' => array(
                    'x' => 38,
                    'y' => 298,
                    'h' => 12,
                    'w' => 27,
                    'halign' => 'C',
                ),
            ),
            'l15a_state_id' => array(
                'coordinates' => array(
                    'x' => 65,
                    'y' => 298,
                    'h' => 12,
                    'w' => 130,
                    'halign' => 'C',
                ),
            ),
            'l16a' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 196,
                    'y' => 298,
                    'h' => 12,
                    'w' => 85,
                    'halign' => 'R',
                ),
            ),
            'l17a' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 281,
                    'y' => 298,
                    'h' => 12,
                    'w' => 79,
                    'halign' => 'R',
                ),
            ),
            'l18a' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 360,
                    'y' => 298,
                    'h' => 12,
                    'w' => 86,
                    'halign' => 'R',
                ),
            ),
            'l19a' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 446,
                    'y' => 298,
                    'h' => 12,
                    'w' => 80,
                    'halign' => 'R',
                ),
            ),
            'l20a_district' => array(
                'coordinates' => array(
                    'x' => 526,
                    'y' => 298,
                    'h' => 12,
                    'w' => 50,
                    'halign' => 'R',
                ),
            ),

            //State (Line 2)
            'l15b_state' => array(
                'coordinates' => array(
                    'x' => 38,
                    'y' => 320,
                    'h' => 12,
                    'w' => 27,
                    'halign' => 'C',
                ),
            ),
            'l15b_state_id' => array(
                'coordinates' => array(
                    'x' => 65,
                    'y' => 320,
                    'h' => 12,
                    'w' => 130,
                    'halign' => 'C',
                ),
            ),
            'l16b' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 196,
                    'y' => 320,
                    'h' => 12,
                    'w' => 85,
                    'halign' => 'R',
                ),
            ),
            'l17b' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 281,
                    'y' => 320,
                    'h' => 12,
                    'w' => 79,
                    'halign' => 'R',
                ),
            ),
            'l18b' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 360,
                    'y' => 320,
                    'h' => 12,
                    'w' => 86,
                    'halign' => 'R',
                ),
            ),
            'l19b' => array(
                'function' => array('MoneyFormat', 'drawNormal'),
                'coordinates' => array(
                    'x' => 446,
                    'y' => 320,
                    'h' => 12,
                    'w' => 80,
                    'halign' => 'R',
                ),
            ),
            'l20b_district' => array(
                'coordinates' => array(
                    'x' => 526,
                    'y' => 320,
                    'h' => 12,
                    'w' => 50,
                    'halign' => 'R',
                ),
            ),
        );

        if (isset($template_schema[$name])) {
            return $name;
        } else {
            return $template_schema;
        }
    }

    public function _outputXML()
    {
        if (is_object($this->getXMLObject())) {
            $xml = $this->getXMLObject();
        } else {
            return false; //No XML object to append too. Needs return1040 form first.
        }

        $records = $this->getRecords();

        Debug::Arr($records, 'Output XML Records: ', __FILE__, __LINE__, __METHOD__, 10);

        if (is_array($records) and count($records) > 0) {
            $e = 0;
            foreach ($records as $w2_data) {
                $w2_data['control_number'] = ($e + 1);
                $this->arrayToObject($w2_data); //Convert record array to object

                $xml->ReturnData->addChild('IRSW2');

                $xml->ReturnData->IRSW2[$e]->addAttribute('documentId', $this->control_number); // Must be unique within the return

                //Corrected W2 Indicator
                $xml->ReturnData->IRSW2[$e]->addChild('CorrectedW2Ind', 'X');

                //Employee SSN
                if (empty($this->ssn) == false) {
                    $xml->ReturnData->IRSW2[$e]->addChild('EmployeeSSN', $this->stripNonNumeric($this->ssn));
                }

                //Employer EIN
                $xml->ReturnData->IRSW2[$e]->addChild('EmployerEIN', $this->stripNonNumeric($this->ein));


                $xml->ReturnData->IRSW2[$e]->addChild('EmployerNameControl', substr(strtoupper($this->trade_name), 0, 1));

                //Employer name
                $xml->ReturnData->IRSW2[$e]->addChild('EmployerName');

                $xml->ReturnData->IRSW2[$e]->EmployerName->addChild('BusinessNameLine1', $this->trade_name);
                //$xml->EmployerName->addChild('BusinessNameLine2', '' );

                //Employer US address
                $xml->ReturnData->IRSW2[$e]->addChild('EmployerUSAddress');
                $xml->ReturnData->IRSW2[$e]->EmployerUSAddress->addChild('AddressLine1', $this->stripNonAlphaNumeric($this->company_address1));
                $xml->ReturnData->IRSW2[$e]->EmployerUSAddress->addChild('City', $this->company_city);
                $xml->ReturnData->IRSW2[$e]->EmployerUSAddress->addChild('State', $this->company_state);
                $xml->ReturnData->IRSW2[$e]->EmployerUSAddress->addChild('ZIPCode', $this->company_zip_code);

                //Employer foreign address
                /*
                $xml->ReturnData->IRSW2[$e]->addChild('EmployerForeignAddress');
                $xml->ReturnData->IRSW2[$e]->EmployerForeignAddress->addChild('AddressLine1', );
                $xml->ReturnData->IRSW2[$e]->EmployerForeignAddress->addChild('AddressLine2', );
                $xml->ReturnData->IRSW2[$e]->EmployerForeignAddress->addChild('City', );
                $xml->ReturnData->IRSW2[$e]->EmployerForeignAddress->addChild('ProvinceOrState', );
                $xml->ReturnData->IRSW2[$e]->EmployerForeignAddress->addChild('Country', );
                $xml->ReturnData->IRSW2[$e]->EmployerForeignAddress->addChild('PostalCode', );
                */
                //Control number
                $xml->ReturnData->IRSW2[$e]->addChild('ControlNumber', $this->control_number);

                //Employee name
                $xml->ReturnData->IRSW2[$e]->addChild('EmployeeName', $this->first_name . ' ' . $this->last_name);

                //EmployeeUS address
                $xml->ReturnData->IRSW2[$e]->addChild('EmployeeUSAddress');
                $xml->ReturnData->IRSW2[$e]->EmployeeUSAddress->addChild('AddressLine1', $this->stripNonAlphaNumeric($this->address1));
                $xml->ReturnData->IRSW2[$e]->EmployeeUSAddress->addChild('AddressLine2', $this->stripNonAlphaNumeric($this->address2));
                $xml->ReturnData->IRSW2[$e]->EmployeeUSAddress->addChild('City', $this->city);
                $xml->ReturnData->IRSW2[$e]->EmployeeUSAddress->addChild('State', $this->state);
                $xml->ReturnData->IRSW2[$e]->EmployeeUSAddress->addChild('ZIPCode', $this->zip_code);

                //Employee foreign address
                /*
                $xml->ReturnData->IRSW2[$e]->addChild('EmployeeForeignAddress');
                $xml->ReturnData->IRSW2[$e]->EmployeeForeignAddress->addChild('AddressLine1', );
                $xml->ReturnData->IRSW2[$e]->EmployeeForeignAddress->addChild('AddressLine2', );
                $xml->ReturnData->IRSW2[$e]->EmployeeForeignAddress->addChild('City', );
                $xml->ReturnData->IRSW2[$e]->EmployeeForeignAddress->addChild('ProvinceOrState', );
                $xml->ReturnData->IRSW2[$e]->EmployeeForeignAddress->addChild('Country', );
                $xml->ReturnData->IRSW2[$e]->EmployeeForeignAddress->addChild('PostalCode', );
                */
                //Wages amount
                if ($this->isNumeric($this->l1) and $this->l1 >= 0) {
                    $xml->ReturnData->IRSW2[$e]->addChild('WagesAmt', $this->getBeforeDecimal($this->l1));
                }

                //Withholding amount
                if ($this->isNumeric($this->l2)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('WithholdingAmt', $this->getBeforeDecimal($this->l2));
                }

                //Social Security wages amount
                if ($this->isNumeric($this->l3)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('SocialSecurityWagesAmt', $this->getBeforeDecimal($this->l3));
                }

                //Social Security tax amount
                if ($this->isNumeric($this->l4)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('SocialSecurityTaxAmt', $this->getBeforeDecimal($this->l4));
                }

                //Medicare wages and tips amount
                if ($this->isNumeric($this->l5)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('MedicareWagesAndTipsAmt', $this->getBeforeDecimal($this->l5));
                }
                //Medicare tax withheld amount
                if ($this->isNumeric($this->l6)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('MedicareTaxWithheldAmt', $this->getBeforeDecimal($this->l6));
                }

                //Social security tips amount
                if ($this->isNumeric($this->l7)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('SocialSecurityTipsAmt', $this->getBeforeDecimal($this->l7));
                }

                //Allocated tips amount
                if ($this->isNumeric($this->l8)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('AllocatedTipsAmt', $this->getBeforeDecimal($this->l8));
                }

                //Dependent care benefits amount
                if ($this->isNumeric($this->l10)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('DependentCareBenefitsAmt', $this->getBeforeDecimal($this->l10));
                }

                //Nonqualified plans amount
                if ($this->isNumeric($this->l11)) {
                    $xml->ReturnData->IRSW2[$e]->addChild('NonqualifiedPlansAmt', $this->getBeforeDecimal($this->l11));
                }

                $x = 0;
                foreach (range('a', 'd') as $z) {
                    $code_col = 'l12' . $z . '_code';
                    $amount_col = 'l12' . $z;
                    if (empty($this->$code_col) == false or empty($this->$amount_col) == false) {
                        $xml->ReturnData->IRSW2[$e]->addChild('EmployersUseGrp');
                    }
                    //Employer&apos;s Use Code
                    if (empty($this->$code_col) == false) {
                        $xml->ReturnData->IRSW2[$e]->EmployersUseGrp[$x]->addChild('EmployersUseCd', (string)$this->$code_col);
                    }
                    //Employer&apos;s Use Amount
                    if (empty($this->$amount_col) == false and $this->isNumeric($this->$amount_col)) {
                        $xml->ReturnData->IRSW2[$e]->EmployersUseGrp[$x]->addChild('EmployersUseAmt', $this->getBeforeDecimal($this->$amount_col));
                    }

                    $x++;
                }

                //Statutory Employee Ind
                if (empty($this->l13a) == false) {
                    $xml->ReturnData->IRSW2[$e]->addChild('StatutoryEmployeeInd', 'X');
                }
                //Retirement Plan Ind
                if (empty($this->l13b) == false) {
                    $xml->ReturnData->IRSW2[$e]->addChild('RetirementPlanInd', 'X');
                }
                //Third-Party Sick Pay Ind
                if (empty($this->l13c) == false) {
                    $xml->ReturnData->IRSW2[$e]->addChild('ThirdPartySickPayInd', 'X');
                }


                //Other Deducts/Benefits Cd
                $x = 0;
                foreach (range('a', 'd') as $z) {
                    $des_col = 'l14' . $z . '_name';
                    $amount_col = 'l14' . $z;
                    if (empty($this->$des_col) == false and empty($this->$amount_col) == false) {
                        $xml->ReturnData->IRSW2[$e]->addChild('OtherDeductsBenefits');
                        $xml->ReturnData->IRSW2[$e]->OtherDeductsBenefits[$x]->addChild('Description', $this->$des_col);
                        $xml->ReturnData->IRSW2[$e]->OtherDeductsBenefits[$x]->addChild('Amount', (int)$this->$amount_col);
                    }

                    $x++;
                }


                //W2 State Local Tax Group
                $x = 0;
                foreach (range('a', 'z') as $z) {
                    $l15_state = 'l15' . $z . '_state';
                    $l15_state_id = 'l15' . $z . '_state_id';
                    $l16 = 'l16' . $z;
                    $l17 = 'l17' . $z;
                    $l18 = 'l18' . $z;
                    $l19 = 'l19' . $z;
                    $l20 = 'l20' . $z . '_district';

                    if (empty($this->$l15_state) == false
                        or empty($this->$l15_state_id) == false
                        or empty($this->$l16) == false
                        or empty($this->$l17) == false
                        or empty($this->$l18) == false
                        or empty($this->$l19) == false
                        or empty($this->$l20) == false
                    ) {
                        $xml->ReturnData->IRSW2[$e]->addChild('W2StateLocalTaxGrp');
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->addChild('W2StateTaxGrp');
                    }
                    if (empty($this->$l15_state) == false) {
                        //State Abbreviation Code
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->addChild('StateAbbreviationCd', $this->$l15_state);
                    }
                    if (empty($this->$l15_state_id) == false) {
                        //Employer&apos;s State ID Number
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->addChild('EmployersStateIdNumber', $this->$l15_state_id);
                    }
                    if (empty($this->$l16) == false and $this->isNumeric($this->$l16)) {
                        //State Wages Amount
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->addChild('StateWagesAmt', $this->getBeforeDecimal($this->$l16));
                    }
                    if (empty($this->$l17) == false and $this->isNumeric($this->$l17)) {
                        //State Income Tax Amount
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->addChild('StateIncomeTaxAmt', $this->getBeforeDecimal($this->$l17));
                    }

                    if (empty($this->$l18) == false or empty($this->$l19) == false or empty($this->$l20) == false) {
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->addChild('W2LocalTaxGrp');
                    }

                    if (empty($this->$l18) == false and $this->isNumeric($this->$l18)) {
                        //Local Wages/Tips Amount
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->W2LocalTaxGrp->addChild('LocalWagesAndTipsAmt', $this->getBeforeDecimal($this->$l18));
                    }
                    if (empty($this->$l19) == false and $this->isNumeric($this->$l19)) {
                        //Local Income Tax Amount
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->W2LocalTaxGrp->addChild('LocalIncomeTaxAmt', $this->getBeforeDecimal($this->$l19));
                    }
                    if (empty($this->$l20) == false and $this->isNumeric($this->$l20)) {
                        //Name of Locality
                        $xml->ReturnData->IRSW2[$e]->W2StateLocalTaxGrp[$x]->W2StateTaxGrp->W2LocalTaxGrp->addChild('NameOfLocality', $this->getBeforeDecimal($this->$l20));
                    }

                    $x++;
                }

                //Standard or Non Standard Code
                $xml->ReturnData->IRSW2[$e]->addChild('StandardOrNonStandardCd', 'S');

                $e++;
            }
        }

        return true;
    }
}
