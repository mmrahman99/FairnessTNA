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

/*
 * Adds recurring holidays X days in advance,
 * This file should run once a day.
 *
 */
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'global.inc.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'CLI.inc.php');

$offset = 86400 * 60; //60 days

$hplf = new HolidayPolicyListFactory();

//Get all holiday policies
$hplf->getAll(null, null, null);

$epoch = time();

foreach ($hplf as $hp_obj) {
    //Get all recurring holidays
    $recurring_holiday_ids = $hp_obj->getRecurringHoliday();

    if (is_array($recurring_holiday_ids) and count($recurring_holiday_ids) > 0) {
        Debug::Text('Found Recurring Holidays...', __FILE__, __LINE__, __METHOD__, 10);
        foreach ($recurring_holiday_ids as $recurring_holiday_id) {
            $rhlf = new RecurringHolidayListFactory();
            $rhlf->getById($recurring_holiday_id);
            if ($rhlf->getRecordCount() == 1) {
                $rh_obj = $rhlf->getCurrent();
                Debug::Text('Found Recurring Holiday: ' . $rh_obj->getName(), __FILE__, __LINE__, __METHOD__, 10);

                $next_holiday_date = $rh_obj->getNextDate($epoch);
                Debug::Text('Next Holiday Date: ' . TTDate::getDate('DATE+TIME', $next_holiday_date), __FILE__, __LINE__, __METHOD__, 10);

                if ($next_holiday_date <= ($epoch + $offset)) {
                    Debug::Text('Next Holiday Date is within Time Period (offset) adding...', __FILE__, __LINE__, __METHOD__, 10);

                    $hf = new HolidayFactory();
                    $hf->setHolidayPolicyId($hp_obj->getId());
                    $hf->setDateStamp($next_holiday_date);
                    $hf->setName($rh_obj->getName());

                    if ($hf->isValid()) {
                        $hf->Save();
                    }
                } else {
                    Debug::Text('Next Holiday Date is NOT within Time Period (offset)!', __FILE__, __LINE__, __METHOD__, 10);
                }
            }
        }
    }

    /*
    $end_date = NULL;

    $pay_period_schedule->createNextPayPeriod($end_date, $offset);

    unset($ppf);
    unset($pay_period_schedule);
    */
}
Debug::writeToLog();
Debug::Display();
