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
class GovernmentForms_US_941 extends GovernmentForms_US
{
    public $xml_schema = '94x/94x/IRS941.xsd';

    public $pdf_template = '941.pdf';
    //public $template_offsets = array( -2, +35 ); //x, y
    public $page_offsets = array(0, -35); //x, y

    public $social_security_rate = 0.124; //Line: 5a2, 5b2

    public $medicare_rate = 0.029; //Line: 5c2
    public $medicare_additional_rate = 0.009; //Line: 5d2

    public $line_16_cutoff_amount = 2500; //Line 16

    public function getFilterFunction($name)
    {
        $variable_function_map = array(
            'year' => 'isNumeric',
            'ein' => array('stripNonNumeric', 'isNumeric'),
            'l1' => array('stripNonNumeric', 'isNumeric'),
            'l2' => array('stripNonFloat', 'isNumeric'),
            'l3' => array('stripNonFloat', 'isNumeric'),
            'l5a' => array('stripNonFloat', 'isNumeric'),
            'l5b' => array('stripNonFloat', 'isNumeric'),
            'l5c' => array('stripNonFloat', 'isNumeric'),
            'l5d' => array('stripNonFloat', 'isNumeric'),
            'l9' => array('stripNonFloat', 'isNumeric'),
            'l11' => array('stripNonFloat', 'isNumeric'),
            'l16_month_1' => array('stripNonFloat', 'isNumeric'),
            'l16_month_2' => array('stripNonFloat', 'isNumeric'),
            'l16_month_3' => array('stripNonFloat', 'isNumeric'),
        );

        if (isset($variable_function_map[$name])) {
            return $variable_function_map[$name];
        }

        return false;
    }

    public function filterL11($value, $schema)
    {
        if ($this->l11 > 0) {
            return $value;
        } else {
            return $this->l10; //If no deposit amount is specified, assume they deposit the amount calculated.
        }

        return false;
    }

    public function filterL13A($value, $schema)
    {
        if ($this->l13 > 0) {
            return $value;
        }

        return false;
    }

    public function filterL13B($value, $schema)
    {
        if ($this->l13 > 0) {
            return $value;
        }

        return false;
    }

    public function drawPage3Quarter($value, $schema)
    {
        return $this->quarter;
    }

    public function drawPage3EIN($value, $schema)
    {
        $value = $this->ein;

        $this->Draw(substr($value, 0, 2), $this->getSchemaSpecificCoordinates($schema, 0));
        $this->Draw(substr($value, 2, 7), $this->getSchemaSpecificCoordinates($schema, 1));
        return true;
    }

    public function calcL5D2($value = null, $schema = null)
    {
        $this->l5d2 = $this->MoneyFormat(($this->l5d * $this->medicare_additional_rate), false);
        return $this->l5d2;
    }

    public function calcL5E($value = null, $schema = null)
    {
        $this->l5e = ($this->l5a2 + $this->l5b2 + $this->l5c2 + $this->l5d2);

        if ($this->l5e > 0) {
            $this->l4 = true;
        } else {
            $this->l4 = false;
        }

        return $this->l5e;
    }

    public function calcL6($value = null, $schema = null)
    {
        $this->l6 = ($this->l3 + $this->l5e + $this->l5f);
        return $this->l6;
    }

    public function calcL7($value, $schema)
    {
        $this->l7 = ($this->l7z > 0) ? ($this->l7z - $this->l5e) : 0;
        Debug::Text('Raw: L7: ' . $this->l7 . ' L5e: ' . $this->l5e . ' L7z: ' . $this->l7z, __FILE__, __LINE__, __METHOD__, 10);

        if (abs($this->l7) > 100) { //As a precaution, check to see if cents adjustment exceeds $100, if it does assume its wrong and zero it out.
            Debug::Text('L7 seems incorrect, ignoring it...', __FILE__, __LINE__, __METHOD__, 10);
            $this->l7 = 0;
        }

        return $this->l7;
    }

    public function calcL12($value, $schema)
    {
        if ($this->l11 > 0 and $this->l10 > $this->l11) {
            $this->l12 = ($this->l10 - $this->l11);

            return $this->l12;
        }
    }

    public function _outputPDF()
    {
        //Initialize PDF with template.
        $pdf = $this->getPDFObject();

        if ($this->getShowBackground() == true) {
            $pdf->setSourceFile($this->getTemplateDirectory() . DIRECTORY_SEPARATOR . $this->pdf_template);

            $this->template_index[1] = $pdf->ImportPage(1);
            $this->template_index[2] = $pdf->ImportPage(2);
            $this->template_index[3] = $pdf->ImportPage(3);
        }

        if ($this->year == '') {
            $this->year = $this->getYear();
        }

        //Get location map, start looping over each variable and drawing
        $template_schema = $this->getTemplateSchema();
        if (is_array($template_schema)) {
            $template_page = null;

            foreach ($template_schema as $field => $schema) {
                $this->Draw($this->$field, $schema);
            }
        }

        return true;
    }

    public function getTemplateSchema($name = null)
    {
        $template_schema = array(
            //Initialize page1, replace years on template.
            array(
                'page' => 1,
                'template_page' => 1,
                'value' => 'Form',
                'on_background' => true,
                'coordinates' => array(
                    'x' => 35,
                    'y' => 71,
                    'h' => 23,
                    'w' => 22,
                    'halign' => 'L',
                    'fill_color' => array(255, 255, 255),
                ),
                'font' => array(
                    'size' => 8,
                    'type' => '')
            ),

            array(
                'value' => '941 for ' . $this->year,
                'on_background' => true,
                'coordinates' => array(
                    'x' => 57,
                    'y' => 66,
                    'h' => 28,
                    'w' => 97,
                    'halign' => 'C',
                    'fill_color' => array(255, 255, 255),
                ),
                'font' => array(
                    'size' => 16,
                    'type' => 'B')
            ),

            array(
                'value' => $this->year, //Top right, in quarter checkbox section.
                'on_background' => true,
                'coordinates' => array(
                    'x' => 539,
                    'y' => 101,
                    'h' => 8,
                    'w' => 21,
                    'halign' => 'C',
                    'text_color' => array(255, 255, 255),
                    'fill_color' => array(30, 30, 30),
                ),
                'font' => array(
                    'size' => 10,
                    'type' => 'B')
            ),

            array(
                'value' => '(Rev. ' . $this->year . ')', //Bottom right of first page.
                'on_background' => true,
                'coordinates' => array(
                    'x' => 533,
                    'y' => 768,
                    'h' => 11,
                    'w' => 45,
                    'halign' => 'C',
                    'fill_color' => array(255, 255, 255),
                ),
                'font' => array(
                    'size' => 7)
            ),
            //Finish initializing page 1.

            'ein' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawChars', //custom drawing function.
                'coordinates' => array(
                    array('type' => 'static', //static or relative
                        'x' => 151,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 178,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 216,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 242,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 267,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 292,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 318,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 343,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 369,
                        'y' => 102,
                        'h' => 17,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 12,
                    'type' => 'B')
            ),

            'name' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 139,
                    'y' => 125,
                    'h' => 18,
                    'w' => 246,
                    'halign' => 'L',
                ),
            ),

            'trade_name' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 118,
                    'y' => 149,
                    'h' => 18,
                    'w' => 267,
                    'halign' => 'L',
                ),
            ),

            'address' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 83,
                    'y' => 172,
                    'h' => 18,
                    'w' => 302,
                    'halign' => 'L',
                ),
            ),

            'city' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 83,
                    'y' => 202,
                    'h' => 18,
                    'w' => 182,
                    'halign' => 'L',
                ),
            ),
            'state' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 273,
                    'y' => 202,
                    'h' => 18,
                    'w' => 35,
                    'halign' => 'C',
                ),
            ),
            'zip_code' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 317,
                    'y' => 202,
                    'h' => 18,
                    'w' => 70,
                    'halign' => 'C',
                ),
            ),


            'quarter' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawCheckBox',
                'coordinates' => array(
                    1 => array(
                        'x' => 424,
                        'y' => 128,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                    2 => array(
                        'x' => 424,
                        'y' => 145,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                    3 => array(
                        'x' => 424,
                        'y' => 162.5,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                    4 => array(
                        'x' => 424,
                        'y' => 179.5,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 10,
                    'type' => 'B')
            ),

            'l1' => array(
                'page' => 1,
                'template_page' => 1,
                'coordinates' => array(
                    'x' => 447, //431
                    'y' => 305, //264 = 41diff
                    'h' => 15,
                    'w' => 128,
                    'halign' => 'C',
                ),
            ),

            'l2' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 447,
                        'y' => 330,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 330,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l3' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 447,
                        'y' => 354,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 354,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l4' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawCheckbox',
                'coordinates' => array(
                    array(
                        'x' => 447,
                        'y' => 378,
                        'h' => 6,
                        'w' => 10,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 8,
                    'type' => 'B')
            ),
            'l5a' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 217, //190
                        'y' => 413, //351
                        'h' => 14,
                        'w' => 65, //75
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 287,
                        'y' => 413,
                        'h' => 14,
                        'w' => 20,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5b' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 217,
                        'y' => 432,
                        'h' => 14,
                        'w' => 65,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 287,
                        'y' => 432,
                        'h' => 14,
                        'w' => 20,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5c' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 217,
                        'y' => 450,
                        'h' => 14,
                        'w' => 65,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 287,
                        'y' => 450,
                        'h' => 14,
                        'w' => 20,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5d' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 217,
                        'y' => 474,
                        'h' => 14,
                        'w' => 65,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 287,
                        'y' => 474,
                        'h' => 14,
                        'w' => 20,
                        'halign' => 'C',
                    ),
                ),
            ),

            'l5a2' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL5A2', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 352,
                        'y' => 413,
                        'h' => 14,
                        'w' => 70,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 425,
                        'y' => 413,
                        'h' => 14,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5b2' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL5B2', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 352,
                        'y' => 432,
                        'h' => 14,
                        'w' => 70,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 425,
                        'y' => 432,
                        'h' => 14,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5c2' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL5C2', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 352,
                        'y' => 450,
                        'h' => 14,
                        'w' => 70,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 425,
                        'y' => 450,
                        'h' => 14,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5d2' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL5D2', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 352,
                        'y' => 474,
                        'h' => 14,
                        'w' => 70,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 425,
                        'y' => 474,
                        'h' => 14,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5e' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL5e', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 498,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 498,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l5f' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 521,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 521,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l6' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL6', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 546,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 546,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l7' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL7', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 570,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 570,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l8' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 593,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 593,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l9' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 619,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 619,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),

            'l10' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL10', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 642,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 642,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l11' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('filterL11', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 683,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 683,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l12' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL12', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 708,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 549,
                        'y' => 708,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l13' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('calcL13', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 300,
                        'y' => 732,
                        'h' => 14,
                        'w' => 70,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 377,
                        'y' => 732,
                        'h' => 14,
                        'w' => 19,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l13a' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('filterL13A', 'drawCheckbox'),
                'coordinates' => array(
                    array(
                        'x' => 446,
                        'y' => 733,
                        'h' => 6,
                        'w' => 10,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 8,
                    'type' => 'B')
            ),
            'l13b' => array(
                'page' => 1,
                'template_page' => 1,
                'function' => array('filterL13B', 'drawCheckbox'),
                'coordinates' => array(
                    array(
                        'x' => 518,
                        'y' => 733,
                        'h' => 6,
                        'w' => 10,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 8,
                    'type' => 'B')
            ),
            //Initialize Page 2
            array(
                'page' => 2,
                'template_page' => 2,
                'value' => $this->name,
                'coordinates' => array(
                    'x' => 36,
                    'y' => 89,
                    'h' => 15,
                    'w' => 350,
                    'halign' => 'L',
                ),
            ),
            array(
                'value' => $this->ein,
                'coordinates' => array(
                    'x' => 398,
                    'y' => 89,
                    'h' => 15,
                    'w' => 175,
                    'halign' => 'C',
                ),
            ),
            array(
                'value' => '(Rev. ' . $this->year . ')',
                'on_background' => true,
                'coordinates' => array(
                    'x' => 534,
                    'y' => 792,
                    'h' => 11,
                    'w' => 45,
                    'halign' => 'C',
                    'fill_color' => array(255, 255, 255),
                ),
                'font' => array(
                    'size' => 7)
            ),
            //Finish initialize Page 2

            //Put this after Month1,Month2,Month3 are set, as we can automatically determine it for the most part.
            'l16' => array(
                'page' => 2,
                'template_page' => 2,
                'function' => array('filterL16', 'drawCheckbox'),
                'coordinates' => array(
                    'a' => array(
                        'x' => 117,
                        'y' => 156,
                        'h' => 6,
                        'w' => 10,
                        'halign' => 'C',
                    ),
                    'b' => array(
                        'x' => 117,
                        'y' => 194,
                        'h' => 6,
                        'w' => 10,
                        'halign' => 'C',
                    ),
                    'c' => array(
                        'x' => 117,
                        'y' => 306,
                        'h' => 6,
                        'w' => 10,
                        'halign' => 'C',
                    ),

                ),
                'font' => array(
                    'size' => 8,
                    'type' => 'B')
            ),

            'l16_month1' => array(
                'page' => 2,
                'template_page' => 2,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 237,
                        'y' => 223,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 339,
                        'y' => 223,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l16_month2' => array(
                'page' => 2,
                'template_page' => 2,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 237,
                        'y' => 246,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 339,
                        'y' => 246,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l16_month3' => array(
                'page' => 2,
                'template_page' => 2,
                'function' => 'drawSplitDecimalFloat',
                'coordinates' => array(
                    array(
                        'x' => 237,
                        'y' => 267,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 339,
                        'y' => 267,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),
            'l16_month_total' => array(
                'page' => 2,
                'template_page' => 2,
                'function' => array('calcL16MonthTotal', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 237,
                        'y' => 289,
                        'h' => 14,
                        'w' => 99,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 339,
                        'y' => 289,
                        'h' => 14,
                        'w' => 26,
                        'halign' => 'C',
                    ),
                ),
            ),

            //Initialize Page 3
            array(
                'page' => 3,
                'template_page' => 3,
                'value' => substr($this->year, 2, 2),
                'on_background' => true,
                'coordinates' => array(
                    'x' => 536,
                    'y' => 602,
                    'h' => 0,
                    'w' => 30,
                    'halign' => 'L',
                    'fill_color' => array(255, 255, 255),
                ),
                'font' => array(
                    'size' => 20,
                    'type' => 'B')
            ),
            //Finish initialize Page 3

            array(
                'page' => 3,
                'template_page' => 3,
                'function' => 'drawPage3EIN',
                'coordinates' => array(
                    array(
                        'x' => 54,
                        'y' => 648,
                        'h' => 15,
                        'w' => 30,
                        'halign' => 'C',
                    ),
                    array(
                        'x' => 87,
                        'y' => 648,
                        'h' => 15,
                        'w' => 50,
                        'halign' => 'C',
                    )
                ),
                'font' => array(
                    'size' => 10)
            ),

            array(
                'page' => 3,
                'template_page' => 3,
                'function' => array('calcL14', 'drawSplitDecimalFloat'),
                'coordinates' => array(
                    array(
                        'x' => 444,
                        'y' => 642,
                        'h' => 17,
                        'w' => 95,
                        'halign' => 'R',
                    ),
                    array(
                        'x' => 542,
                        'y' => 642,
                        'h' => 17,
                        'w' => 32,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 22)
            ),

            array(
                'page' => 3,
                'template_page' => 3,
                'value' => $this->trade_name,
                'coordinates' => array(
                    'x' => 229,
                    'y' => 676,
                    'h' => 15,
                    'w' => 250,
                    'halign' => 'L',
                ),
                'font' => array(
                    'size' => 10)
            ),
            array(
                'page' => 3,
                'template_page' => 3,
                'value' => $this->address,
                'coordinates' => array(
                    'x' => 229,
                    'y' => 700,
                    'h' => 15,
                    'w' => 250,
                    'halign' => 'L',
                ),
                'font' => array(
                    'size' => 10)
            ),
            array(
                'page' => 3,
                'template_page' => 3,
                'value' => $this->city . ', ' . $this->state . ', ' . $this->zip_code,
                'coordinates' => array(
                    'x' => 229,
                    'y' => 723,
                    'h' => 15,
                    'w' => 250,
                    'halign' => 'L',
                ),
                'font' => array(
                    'size' => 10)
            ),
            array(
                'page' => 3,
                'template_page' => 3,
                'function' => array('drawPage3Quarter', 'drawCheckBox'),
                'coordinates' => array(
                    1 => array(
                        'x' => 51,
                        'y' => 687,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                    2 => array(
                        'x' => 51,
                        'y' => 718,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                    3 => array(
                        'x' => 138,
                        'y' => 688,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                    4 => array(
                        'x' => 138,
                        'y' => 718,
                        'h' => 10,
                        'w' => 11,
                        'halign' => 'C',
                    ),
                ),
                'font' => array(
                    'size' => 10,
                    'type' => 'B')
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
            return false; //No XML object to append too. Needs return940 form first.
        }

        $xml->IRS941->addAttribute('documentId', 0); //  Must be unique within the return.
        if (isset($this->l1)) {
            $xml->IRS941->addChild('NumberOfEmployees', $this->l1);
        }

        if (isset($this->l2)) {
            $xml->IRS941->addChild('TotalWages', $this->l2);
        }
        if (isset($this->l3)) {
            $xml->IRS941->addChild('TotalIncomeTaxWithheld', $this->l3);
        }

        if (isset($this->l5a)) {
            $xml->IRS941->addChild('TaxableSocialSecurityWages', $this->l5a);
            if ($this->calcL5A2(null, null) > 0) {
                $xml->IRS941->addChild('TaxOnSocialSecurityWages', $this->calcL5A2(null, null));
            }
        }
        if (isset($this->l5b)) {
            $xml->IRS941->addChild('TaxableSocialSecurityTips', $this->l5b);
            if ($this->calcL5B2(null, null) > 0) {
                $xml->IRS941->addChild('TaxOnSocialSecurityTips', $this->calcL5B2(null, null));
            }
        }
        if (isset($this->l5c)) {
            $xml->IRS941->addChild('TaxableMedicareWagesTips', $this->l5c);
            if ($this->calcL5C2(null, null) > 0) {
                $xml->IRS941->addChild('TaxOnMedicareWagesTips', $this->calcL5C2(null, null));
            }
        }
        if ($this->calcL5D(null, null) > 0) {
            $xml->IRS941->addChild('TotalSocialSecurityMedTaxes', $this->calcL5D(null, null));
            $xml->IRS941->addChild('WagesNotSubjToSSMedicareTaxes', 'X');
        }
        if ($this->calcL6E(null, null) > 0) {
            $xml->IRS941->addChild('TotalTaxesBeforeAdjustmentsAmt', $this->calcL6E(null, null));
        }
        if (isset($this->l7)) {
            $xml->IRS941->addChild('FractionsOfCentsAdjustment', $this->l7);
        }
        if (isset($this->l9)) {
            $xml->IRS941->addChild('TipsGroupTermLifeInsAdjAmount', $this->l9);
        }
        if ($this->calcL10(null, null) > 0) {
            $xml->IRS941->addChild('TotalTax', $this->calcL10(null, null));
        } else {
            $xml->IRS941->addChild('TotalTax', 0.00);
        }

        $xml->IRS941->addChild('TotalDepositsOverpaymentForQtr', $this->l11);
        if ($this->calcL13(null, null) > 0) {
            $xml->IRS941->addChild('PaymentCreditTotal', $this->calcL13(null, null));
        } else {
            $xml->IRS941->addChild('PaymentCreditTotal', 0.00);
        }

        if ($this->calcL14(null, null) > 0) {
            $xml->IRS941->addChild('BalanceDue', $this->calcL14(null, null));
        } else {
            $xml->IRS941->addChild('Overpayment');
            if ($this->calcL15(null, null) > 0) {
                $xml->IRS941->Overpayment->addChild('Amount', $this->calcL15(null, null));
                $xml->IRS941->Overpayment->addChild('CreditElect', 'X');
            } else {
                $xml->IRS941->Overpayment->addChild('Amount', 0.00);
            }
        }

        if (isset($this->l16)) {
            $xml->IRS941->addChild('DepositStateCode', $this->l16);
        }

        if (is_array($this->filterL16(null, null))) {
            $L16_ARR = $this->filterL16(null, null);
            foreach ($L16_ARR as $l16) {
                switch ($l16) {
                    case 'a':
                        $xml->IRS941->addChild('LessThan2500', 'X');
                        break;
                    case 'b':
                        $xml->IRS941->addChild('MonthlyDepositorGroup');
                        $xml->IRS941->MonthlyDepositorGroup->addChild('MonthlyScheduleDepositor', 'X');
                        if (isset($this->l16_month1)) {
                            $xml->IRS941->MonthlyDepositorGroup->addChild('Month1Liability', $this->l16_month1);
                        }
                        if (isset($this->l16_month2)) {
                            $xml->IRS941->MonthlyDepositorGroup->addChild('Month2Liability', $this->l16_month2);
                        }
                        if (isset($this->l16_month3)) {
                            $xml->IRS941->MonthlyDepositorGroup->addChild('Month3Liability', $this->l16_month3);
                        }
                        if ($this->calcL16MonthTotal(null, null) > 0) {
                            $xml->IRS941->MonthlyDepositorGroup->addChild('TotalQuarterLiability', $this->calcL16MonthTotal(null, null));
                        }

                        break;
                    case 'c':
                        $xml->IRS941->addChild('SemiweeklyScheduleDepositor', 'X');
                        break;
                }
            }
        }
    }


    //This requires 'l7z' to be passed in as a total of all the amounts actually deducted from the employee.
    //So we can compare that with the calculated amounts that should have been deducted, the result of which is l7.

    public function calcL5A2($value = null, $schema = null)
    {
        $this->l5a2 = $this->MoneyFormat(($this->l5a * $this->social_security_rate), false);
        return $this->l5a2;
    }

    public function calcL5B2($value = null, $schema = null)
    {
        $this->l5b2 = $this->MoneyFormat(($this->l5b * $this->social_security_rate), false);
        return $this->l5b2;
    }

    public function calcL5C2($value = null, $schema = null)
    {
        $this->l5c2 = $this->MoneyFormat(($this->l5c * $this->medicare_rate), false);
        return $this->l5c2;
    }

    public function calcL10($value, $schema)
    {
        $this->l10 = ($this->l6 + $this->l7 + $this->l8 + $this->l9);
        return $this->l10;
    }

    public function calcL13($value, $schema)
    {
        if ($this->l11 > $this->l10) {
            $this->l13 = ($this->l11 - $this->l10);
            return $this->l13;
        }
    }

    public function filterL16($value, $schema)
    {
        if ($this->l10 < $this->line_16_cutoff_amount) {
            $value = array('a');
            unset($this->l16_month1, $this->l16_month2, $this->l16_month3, $this->l16_month_total);
        } elseif ($this->l16_month1 > 0 or $this->l16_month2 > 0 or $this->l16_month3 > 0) {
            $value = array('b');
        } else {
            $value = array('c');
        }

        return $value;
    }

    public function calcL16MonthTotal($value, $schema)
    {
        $this->l16_month_total = ($this->l16_month1 + $this->l16_month2 + $this->l16_month3);
        return $this->l16_month_total;
    }
}
