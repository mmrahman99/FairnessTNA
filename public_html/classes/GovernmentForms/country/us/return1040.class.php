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
 * @package GovernmentForms
 */

//This is the header record for submitting XML forms to the CRA.
include_once('US.class.php');

class GovernmentForms_US_RETURN1040 extends GovernmentForms_US
{
    public $xml_schema = '1040/IndividualIncomeTax/Ind1040/Return1040.xsd';

    public function getFilterFunction($name)
    {
        $variable_function_map = array(
            //'year' => 'isNumeric',
            //'ein' => array( 'stripNonNumeric', 'isNumeric'),
        );

        if (isset($variable_function_map[$name])) {
            return $variable_function_map[$name];
        }

        return false;
    }

    public function getTemplateSchema($name = null)
    {
        $template_schema = array();

        if (isset($template_schema[$name])) {
            return $name;
        } else {
            return $template_schema;
        }
    }

    //Set the submission status. Original, Amended, Cancel.
    public function getStatus()
    {
        if (isset($this->status)) {
            return $this->status;
        }

        return 'O'; //Original
    }

    public function setStatus($value)
    {
        if (strtoupper($value) == 'C') {
            $value = 'A'; //Cancel isn't valid for this, only original and amendment.
        }
        $this->status = strtoupper(trim($value));
        return true;
    }

    public function filterPhone($value)
    {
        //Strip non-digits.
        $value = $this->stripNonNumeric($value);

        return array(substr($value, 0, 3), substr($value, 3, 3), substr($value, 6, 4));
    }

    public function _outputXML()
    {
        $xml = new SimpleXMLElement('<Return returnVersion="2012v3.0" xsi:schemaLocation="http://www.irs.gov/efile Return1040.xsd" xmlns:efile="http://www.irs.gov/efile" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></Return>'); //IRSW2 must be wrapped in <ReturnData></ReturnData>
        $this->setXMLObject($xml);

        $xml->addChild('ReturnHeader');
        $xml->ReturnHeader->addAttribute('binaryAttachmentCount', 0); //Base type for a non-negative integer

        if ($this->software_id == '') {
            $this->software_id = '00000000';
        }
        if ($this->originator_efin == '') {
            $this->originator_efin = '000000';
        }
        if ($this->originator_type_code == '') {
            $this->originator_type_code = 'OnlineFiler';
        }
        if ($this->pin_type_code == '') {
            $this->pin_type_code = 'Practitioner';
        }
        if ($this->jurat_disclosure_code == '') {
            $this->jurat_disclosure_code = 'Practitioner PIN';
        }
        if ($this->pin_entered_by == '') {
            $this->pin_entered_by = 'Taxpayer';
        }
        if ($this->return_type == '') {
            $this->return_type = 'IRSW2';
        }
        if ($this->ssn == '') {
            $this->ssn = '000000000';
        }
        if ($this->name_control == '') {
            $this->name_control = 'A';
        }
        if ($this->ip_address == '') {
            $this->ip_address = '0.0.0.0';
        }
        if ($this->timezone == '') {
            $this->timezone = 'US';
        }

        // Just set the required column at here
        $xml->ReturnHeader->addChild('Timestamp', $this->return_created_timestamp); // The date and time when the return was created
        $xml->ReturnHeader->addChild('TaxYear', $this->year);
        $xml->ReturnHeader->addChild('TaxPeriodBeginDate', $this->tax_period_begin_date); //Tax Period Begin Date
        $xml->ReturnHeader->addChild('TaxPeriodEndDate', $this->tax_period_end__date); //Tax Period End Date
        $xml->ReturnHeader->addChild('SoftwareId', $this->software_id); // Software Identification
        $xml->ReturnHeader->addChild('Originator');
        $xml->ReturnHeader->Originator->addChild('EFIN', $this->originator_efin);
        $xml->ReturnHeader->Originator->addChild('OriginatorTypeCd', $this->originator_type_code);
        $xml->ReturnHeader->addChild('PINTypeCode', $this->pin_type_code); // PIN Type Code
        $xml->ReturnHeader->addChild('JuratDisclosureCode', $this->jurat_disclosure_code); // Jurat Disclosure Code
        $xml->ReturnHeader->addChild('PrimaryPINEnteredBy', $this->pin_entered_by); // Primary PIN entered by
        $xml->ReturnHeader->addChild('PrimarySignatureDate', $this->signature_date); // Primary Signature Date
        $xml->ReturnHeader->addChild('ReturnType', $this->return_type); // Return Type
        $xml->ReturnHeader->addChild('Filer');
        $xml->ReturnHeader->Filer->addChild('PrimarySSN', $this->ssn); // Primary SSN
        $xml->ReturnHeader->Filer->addChild('Name', $this->name);
        $xml->ReturnHeader->Filer->addChild('PrimaryNameControl', $this->name_control); // Primary Name Control
        $xml->ReturnHeader->Filer->addChild('USAddress');
        $xml->ReturnHeader->Filer->USAddress->addChild('AddressLine1', $this->address1);
        $xml->ReturnHeader->Filer->USAddress->addChild('City', $this->city);
        $xml->ReturnHeader->Filer->USAddress->addChild('State', $this->state);
        $xml->ReturnHeader->Filer->USAddress->addChild('ZIPCode', $this->zip_code);
        $xml->ReturnHeader->addChild('IPAddress');
        $xml->ReturnHeader->IPAddress->addChild('IPv4Address', $this->ip_address);
        $xml->ReturnHeader->addChild('IPDate', $this->ip_date);
        $xml->ReturnHeader->addChild('IPTime', $this->ip_time);
        $xml->ReturnHeader->addChild('IPTimezone', $this->timezone);

        $xml->addChild('ReturnData');
        $xml->ReturnData->addAttribute('documentCount', 0); // The number of return documents in the return.

        return true;
    }

    public function _outputPDF()
    {
        return false;
    }
}
