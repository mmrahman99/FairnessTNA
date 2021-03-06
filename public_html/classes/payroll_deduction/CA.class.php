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
 * @package PayrollDeduction\CA
 */
class PayrollDeduction_CA extends PayrollDeduction_CA_Data
{
    public function setFederalTotalClaimAmount($value)
    {
        //TC
        $this->data['federal_total_claim_amount'] = $value;

        return true;
    }

    public function setProvincialTotalClaimAmount($value)
    {
        //TCP
        $this->data['provincial_total_claim_amount'] = $value;

        return true;
    }

    public function setFederalAdditionalDeduction($value)
    {
        if ($value >= 0) {
            $this->data['additional_deduction'] = $value;

            return true;
        }

        return false;
    }

    public function setUnionDuesAmount($value)
    {
        $this->data['union_dues_amount'] = $value;

        return true;
    }

    public function setCPPExempt($value)
    {
        $this->data['cpp_exempt'] = $value;

        return true;
    }

    public function setYearToDateCPPContribution($value)
    {
        if ($value >= 0) {
            $this->data['cpp_year_to_date_contribution'] = $value;

            return true;
        }

        return false;
    }

    public function setEIExempt($value)
    {
        $this->data['ei_exempt'] = $value;

        return true;
    }

    public function setYearToDateEIContribution($value)
    {
        if ($value >= 0) {
            $this->data['ei_year_to_date_contribution'] = $value;

            return true;
        }

        return false;
    }

    public function setWCBRate($value)
    {
        $this->data['wcb_rate'] = $value;

        return true;
    }

    public function setFederalTaxExempt($value)
    {
        $this->data['federal_tax_exempt'] = $value;

        return true;
    }

    public function setProvincialTaxExempt($value)
    {
        $this->data['provincial_tax_exempt'] = $value;

        return true;
    }

    public function setEnableCPPAndEIDeduction($value)
    {
        $this->data['enable_cpp_and_ei_deduction'] = $value;

        return true;
    }

    public function getPayPeriodEmployeeNetPay()
    {
        return bcsub($this->getGrossPayPeriodIncome(), $this->getPayPeriodEmployeeTotalDeductions());
    }

    public function getPayPeriodEmployeeTotalDeductions()
    {
        return bcadd(bcadd($this->getPayPeriodTaxDeductions(), $this->getEmployeeCPP()), $this->getEmployeeEI());
    }

    public function getPayPeriodTaxDeductions()
    {
        /*
            T = [(T1 + T2) / P] + L
        */

        $T1 = $this->getFederalTaxPayable();
        $T2 = $this->getProvincialTaxPayable();
        $P = $this->getAnnualPayPeriods();
        $L = $this->getFederalAdditionalDeduction();

        //$T = (($T1 + $T2) / $P) + $L;
        $T = bcadd(bcdiv(bcadd($T1, $T2), $P), $L);

        Debug::text('T: ' . $T, __FILE__, __LINE__, __METHOD__, 10);

        return $T;
    }

    public function getFederalTaxPayable()
    {
        //If employee is federal tax exempt, return 0 dollars.
        if ($this->getFederalTaxExempt() == true) {
            Debug::text('Federal Tax Exempt!', __FILE__, __LINE__, __METHOD__, 10);
            return 0;
        }

        /*
        T1= (T3 - LCF)*
            * If the result is negative, substitute $0

        LCF = The lesser of:
            i) $750 and
            ii) 15% of amount deducted for the year of accusistion.
        */

        $T3 = $this->getFederalBasicTax();
        $LCF = 0; //Ignore 15% for now.

        //$T1 = ($T3 - $LCF);
        $T1 = bcsub($T3, $LCF);

        if ($T1 < 0) {
            $T1 = 0;
        }

        Debug::text('T1: ' . $T1, __FILE__, __LINE__, __METHOD__, 10);
        return $T1;
    }

    public function getFederalTaxExempt()
    {
        //Default to true
        if (isset($this->data['federal_tax_exempt'])) {
            return $this->data['federal_tax_exempt'];
        }

        return false;
    }

    public function getFederalBasicTax()
    {
        /*
        T3 = (R * A) - K - K1 - K2 - K3
            if the result is negative, $0;

        R = Federal tax rate applicable to annual taxable income
        */

        $T3 = 0;
        $A = $this->getAnnualTaxableIncome();
        $R = $this->getData()->getFederalRate($A);
        $K = $this->getData()->getFederalConstant($A);
        $TC = $this->getFederalTotalClaimAmount();
        $K1 = bcmul($this->getData()->getFederalLowestRate(), $TC);
        if ($this->getEnableCPPAndEIDeduction() == true) {
            $K2 = $this->getFederalCPPAndEITaxCredit();
        } else {
            $K2 = 0; //Do the deduction at the Company Tax Deduction level instead.
        }

        $K3 = 0;

        if ($this->getDate() >= 20060701) {
            $K4 = $this->getFederalEmploymentCredit();
        } else {
            $K4 = 0;
        }

        Debug::text('A: ' . $A, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('R: ' . $R, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K: ' . $K, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('TC: ' . $TC, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K1: ' . $K1, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K2: ' . $K2, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K3: ' . $K3, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K4: ' . $K4, __FILE__, __LINE__, __METHOD__, 10);

        //$T3 = ($R * $A) - $K - $K1 - $K2 - $K3 - $K4;
        $T3 = bcsub(bcsub(bcsub(bcsub(bcsub(bcmul($R, $A), $K), $K1), $K2), $K3), $K4);

        if ($T3 < 0) {
            $T3 = 0;
        }

        Debug::text('T3: ' . $T3, __FILE__, __LINE__, __METHOD__, 10);
        return $T3;
    }

    public function getAnnualTaxableIncome()
    {
        if ($this->getFormulaType() == 20) {
            Debug::text('Formula Type: ' . $this->getFormulaType() . ' YTD Gross: ' . $this->getYearToDateGrossIncome() . ' This Gross: ' . $this->getGrossPayPeriodIncome() . ' Current PP: ' . $this->getCurrentPayPeriod(), __FILE__, __LINE__, __METHOD__, 10);
            $A = $this->calcNonPeriodicIncome($this->getYearToDateGrossIncome(), $this->getGrossPayPeriodIncome());
        } else {
            /*
            A = [P * (I - F - F2 - U1)] - HD - F1
                if the result is negative T = L

                //Take into account non-periodic payments such as one-time bonuses/vacation payout.
                //Must include bonus amount for pay period, as well as YTD bonus amount.
            */

            $A = 0;
            $P = $this->getAnnualPayPeriods();
            $I = $this->getGrossPayPeriodIncome();
            $F = 0;
            $F2 = 0;
            $U1 = $this->getUnionDuesAmount();
            $HD = 0;
            $F1 = 0;
            Debug::text('P: ' . $P, __FILE__, __LINE__, __METHOD__, 10);
            Debug::text('I: ' . $I, __FILE__, __LINE__, __METHOD__, 10);
            Debug::text('U1: ' . $U1, __FILE__, __LINE__, __METHOD__, 10);

            //$A = ($P * ($I - $F - $F2 - $U1) ) - $HD - $F1;
            $A = bcsub(bcsub(bcmul($P, bcsub(bcsub(bcsub($I, $F), $F2), $U1)), $HD), $F1);
            Debug::text('A: ' . $A, __FILE__, __LINE__, __METHOD__, 10);
        }

        return $A;
    }

    public function getUnionDuesAmount()
    {
        if (isset($this->data['union_dues_amount'])) {
            return $this->data['union_dues_amount'];
        }

        return 0;
    }

    public function getFederalTotalClaimAmount()
    {
        //Check to make sure the claim amount is at the minimum,
        //as long as it is NOT 0. (outside country)

        //Check claim amount from the previous year, so if the current year setting matches
        //that exactly, we know to use the current year value instead.
        //This helps when the claim amount decreases.
        //Also check next years amount in case the amount gets increased then they try to calculate pay stubs in the previous year.
        $previous_year = $this->getISODate((TTDate::getBeginYearEpoch($this->getDateEpoch()) - 86400));
        $next_year = $this->getISODate((TTDate::getEndYearEpoch($this->getDateEpoch()) + 86400));

        if ($this->data['federal_total_claim_amount'] > 0) {
            if ($this->getBasicFederalClaimCodeAmount() > 0
                and (
                    $this->data['federal_total_claim_amount'] < $this->getBasicFederalClaimCodeAmount()
                    or
                    $this->data['federal_total_claim_amount'] == $this->getBasicFederalClaimCodeAmount($previous_year)
                    or
                    $this->data['federal_total_claim_amount'] == $this->getBasicFederalClaimCodeAmount($next_year)
                )
            ) {
                Debug::text('Using Basic Federal Claim Code Amount: ' . $this->getBasicFederalClaimCodeAmount() . ' (Previous Amount: ' . $this->data['federal_total_claim_amount'] . ') Date: ' . TTDate::getDate('DATE', $this->getDateEpoch()), __FILE__, __LINE__, __METHOD__, 10);
                return $this->getBasicFederalClaimCodeAmount();
            }
        }

        return $this->data['federal_total_claim_amount'];
    }

    public function getEnableCPPAndEIDeduction()
    {
        //Default to true
        if (isset($this->data['enable_cpp_and_ei_deduction'])) {
            return $this->data['enable_cpp_and_ei_deduction'];
        }

        return false;
    }

    public function getFederalCPPAndEITaxCredit()
    {
        $K2 = bcadd($this->getCPPTaxCredit('federal'), $this->getEITaxCredit('federal'));
        Debug::text('K2: ' . $K2, __FILE__, __LINE__, __METHOD__, 10);

        return $K2;
    }

    public function getCPPTaxCredit($type)
    {
        if ($type == 'provincial') {
            $rate = $this->getData()->getProvincialLowestRate();
        } else {
            $rate = $this->getData()->getFederalLowestRate();
        }

        /*
          K2_CPP = [(0.16 * (P * C, max $1801.80))
        */
        $C = $this->getEmployeeCPP();
        $P = $this->getAnnualPayPeriods();

        if ($this->getFormulaType() == 20) {
            $PR = $this->getRemainingPayPeriods();
            $P_times_C = bcadd($this->getYearToDateCPPContribution(), bcmul($PR, $C));
            Debug::text('PR: ' . $PR . ' C: ' . $C . ' YTD ' . $this->getYearToDateCPPContribution(), __FILE__, __LINE__, __METHOD__, 10);
        } else {
            $P_times_C = bcmul($P, $C);
        }
        if ($P_times_C > $this->getCPPEmployeeMaximumContribution()) {
            $P_times_C = $this->getCPPEmployeeMaximumContribution();
        }
        Debug::text('P_times_C: ' . $P_times_C, __FILE__, __LINE__, __METHOD__, 10);

        $PP_CPP = $this->getEmployeeCPPForPayPeriod(); //Raw CPP amount for the pay period ignoring any YTD amounts.
        if ($P_times_C < ($this->getYearToDateCPPContribution() - $PP_CPP)) {
            $P_times_C = $this->getCPPEmployeeMaximumContribution();
            Debug::text('P_times_C in or after PP where maximum contribution is reached: ' . $P_times_C, __FILE__, __LINE__, __METHOD__, 10);
        }

        $K2_CPP = bcmul($rate, $P_times_C);
        Debug::text('K2_CPP: ' . $K2_CPP, __FILE__, __LINE__, __METHOD__, 10);

        return $K2_CPP;
    }

    public function getEmployeeCPP()
    {
        /*
            C = The lesser of
                i) $1801.80 - D; and
                ii) 0.495 * [I - (3500 / P)
                    if the result is negative, C = 0
        */

        //If employee is CPP exempt, return 0 dollars.
        if ($this->getCPPExempt() == true) {
            return 0;
        }

        $D = $this->getYearToDateCPPContribution();
        $P = $this->getAnnualPayPeriods();
        $I = $this->getGrossPayPeriodIncome();

        Debug::text('D: ' . $D, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('P: ' . $P, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('I: ' . $I, __FILE__, __LINE__, __METHOD__, 10);

        $tmp1_C = bcsub($this->getCPPEmployeeMaximumContribution(), $D);
        //$tmp2_C = bcmul( $this->getCPPEmployeeRate(), bcsub($I, $exemption ) );
        $tmp2_C = $this->getEmployeeCPPForPayPeriod();

        Debug::text('Tmp1_C: ' . $tmp1_C, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('Tmp2_C: ' . $tmp2_C, __FILE__, __LINE__, __METHOD__, 10);

        if ($tmp1_C < $tmp2_C) {
            $C = $tmp1_C;
        } else {
            $C = $tmp2_C;
        }

        if ($C < 0) {
            $C = 0;
        }

        Debug::text('C: ' . $C, __FILE__, __LINE__, __METHOD__, 10);

        return $C;
    }

    public function getCPPExempt()
    {
        if (isset($this->data['cpp_exempt'])) {
            return $this->data['cpp_exempt'];
        }

        return false;
    }

    public function getYearToDateCPPContribution()
    {
        if (isset($this->data['cpp_year_to_date_contribution'])) {
            return $this->data['cpp_year_to_date_contribution'];
        }

        return 0;
    }

    public function getEmployeeCPPForPayPeriod()
    {
        /*
                ii) 0.495 * [I - (3500 / P)
                    if the result is negative, C = 0
        */
        //If employee is CPP exempt, return 0 dollars.
        if ($this->getCPPExempt() == true) {
            return 0;
        }

        $P = $this->getAnnualPayPeriods();
        $I = $this->getGrossPayPeriodIncome();
        $exemption = bcdiv($this->getCPPBasicExemption(), $P);

        //We used to just check if its payroll_run_id > 1 and remove the exemption in that case, but that fails when the first in-cycle run is ID=4 or something.
        //  So switch this to just checking the formula type, and only remove the exemption if its a out-of-cycle run.
        //     That won't handle the case of the last pay stub being a out-of-cycle run and no in-cycle run is done for that employee though, but not sure we can do much about that.
        if ($this->getFormulaType() == 20) {
            $exemption = 0;
        }

        Debug::text('P: ' . $P, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('I: ' . $I, __FILE__, __LINE__, __METHOD__, 10);

        $tmp2_C = bcmul($this->getCPPEmployeeRate(), bcsub($I, $exemption));
        if ($tmp2_C > $this->getCPPEmployeeMaximumContribution()) {
            $tmp2_C = $this->getCPPEmployeeMaximumContribution();
        }

        Debug::text('Tmp2_C: ' . $tmp2_C, __FILE__, __LINE__, __METHOD__, 10);

        return $tmp2_C;
    }

    public function getEITaxCredit($type)
    {
        if ($type == 'provincial') {
            $rate = $this->getData()->getProvincialLowestRate();
        } else {
            $rate = $this->getData()->getFederalLowestRate();
        }

        /*
          K2_EI = [(0.16 * (P * C, max $819))
        */
        $C = $this->getEmployeeEI();
        $P = $this->getAnnualPayPeriods();

        if ($this->getFormulaType() == 20) {
            $PR = $this->getRemainingPayPeriods();
            $P_times_C = bcadd($this->getYearToDateEIContribution(), bcmul($PR, $C));
            Debug::text('PR: ' . $PR . ' C: ' . $C . ' YTD ' . $this->getYearToDateEIContribution(), __FILE__, __LINE__, __METHOD__, 10);
        } else {
            $P_times_C = bcmul($P, $C);
        }
        if ($P_times_C > $this->getEIEmployeeMaximumContribution()) {
            $P_times_C = $this->getEIEmployeeMaximumContribution();
        }
        Debug::text('P_times_C: ' . $P_times_C, __FILE__, __LINE__, __METHOD__, 10);

        $PP_EI = $this->getEmployeeEIForPayPeriod(); //Raw CPP amount for the pay period ignoring any YTD amounts.
        if ($P_times_C < ($this->getYearToDateEIContribution() - $PP_EI)) {
            $P_times_C = $this->getEIEmployeeMaximumContribution();
            Debug::text('P_times_C in or after PP where maximum contribution is reached: ' . $P_times_C, __FILE__, __LINE__, __METHOD__, 10);
        }

        $K2_EI = bcmul($rate, $P_times_C);
        Debug::text('K2_EI: ' . $K2_EI, __FILE__, __LINE__, __METHOD__, 10);

        return $K2_EI;
    }

    public function getEmployeeEI()
    {
        /*
            EI = the lesser of
                i) 819 - D; and
                ii) 0.021 * I, maximum of 819
                    round the resulting amount in ii) to the nearest $0.01
        */

        //If employee is EI exempt, return 0 dollars.
        if ($this->getEIExempt() == true) {
            return 0;
        }

        $D = $this->getYearToDateEIContribution();
        $I = $this->getGrossPayPeriodIncome();

        Debug::text('Employee EI Rate: ' . $this->getEIEmployeeRate() . ' I: ' . $I, __FILE__, __LINE__, __METHOD__, 10);
        $tmp1_EI = bcsub($this->getEIEmployeeMaximumContribution(), $D);
//		$tmp2_EI = bcmul( $this->getEIEmployeeRate(), $I);
//		if ($tmp2_EI > $this->getEIEmployeeMaximumContribution() ) {
//			$tmp2_EI = $this->getEIEmployeeMaximumContribution();
//		}
        $tmp2_EI = $this->getEmployeeEIForPayPeriod();

        if ($tmp1_EI < $tmp2_EI) {
            $EI = $tmp1_EI;
        } else {
            $EI = $tmp2_EI;
        }

        if ($EI < 0) {
            $EI = 0;
        }

        Debug::text('Employee EI: ' . $EI, __FILE__, __LINE__, __METHOD__, 10);

        return $EI;
    }

    public function getEIExempt()
    {
        //Default to true
        if (isset($this->data['ei_exempt'])) {
            return $this->data['ei_exempt'];
        }

        return false;
    }

    public function getYearToDateEIContribution()
    {
        if (isset($this->data['ei_year_to_date_contribution'])) {
            return $this->data['ei_year_to_date_contribution'];
        }

        return 0;
    }

    public function getEmployeeEIForPayPeriod()
    {
        /*
                ii) 0.021 * I, maximum of 819
                    round the resulting amount in ii) to the nearest $0.01
        */
        //If employee is EI exempt, return 0 dollars.
        if ($this->getEIExempt() == true) {
            return 0;
        }

        $I = $this->getGrossPayPeriodIncome();

        Debug::text('Employee EI Rate: ' . $this->getEIEmployeeRate() . ' I: ' . $I, __FILE__, __LINE__, __METHOD__, 10);
        $tmp2_EI = bcmul($this->getEIEmployeeRate(), $I);
        if ($tmp2_EI > $this->getEIEmployeeMaximumContribution()) {
            $tmp2_EI = $this->getEIEmployeeMaximumContribution();
        }

        return $tmp2_EI;
    }

    public function getFederalEmploymentCredit()
    {
        /*
          K4 = The lesser of
            0.155 * A and
            0.155 * $1000
        */

        $tmp1_K4 = bcmul($this->getData()->getFederalLowestRate(), $this->getAnnualTaxableIncome());
        $tmp2_K4 = bcmul($this->getData()->getFederalLowestRate(), $this->getData()->getFederalEmploymentCreditAmount());

        if ($tmp2_K4 < $tmp1_K4) {
            $K4 = $tmp2_K4;
        } else {
            $K4 = $tmp1_K4;
        }

        Debug::text('K4: ' . $K4, __FILE__, __LINE__, __METHOD__, 10);
        return $K4;
    }

    public function getProvincialTaxPayable()
    {
        //If employee is provincial tax exempt, return 0 dollars.
        if ($this->getProvincialTaxExempt() == true) {
            Debug::text('Provincial Tax Exempt!', __FILE__, __LINE__, __METHOD__, 10);
            return 0;
        }

        /*
        T2 = T4 + V1 + V2 - S - LCP
            if the result is negative, T2 = 0
        */

        $T4 = $this->getProvincialBasicTax();
        $V1 = $this->getProvincialSurtax();
        $V2 = $this->getAdditionalProvincialSurtax();
        $S = $this->getProvincialTaxReduction();
        $LCP = 0;

        //$T2 = $T4 + $V1 + $V2 - $S - $LCP;
        $T2 = bcsub(bcsub(bcadd(bcadd($T4, $V1), $V2), $S), $LCP);

        if ($T2 < 0) {
            $T2 = 0;
        }

        Debug::text('T2: ' . $T2, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('T4: ' . $T4, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('V1: ' . $V1, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('V2: ' . $V2, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('S: ' . $S, __FILE__, __LINE__, __METHOD__, 10);

        return $T2;
    }

    public function getProvincialTaxExempt()
    {
        //Default to true
        if (isset($this->data['provincial_tax_exempt'])) {
            return $this->data['provincial_tax_exempt'];
        }

        return false;
    }

    public function getProvincialBasicTax()
    {
        /*
              T4 = (V * A) - KP - K1P - K2P - K3P
        */

        $A = $this->getAnnualTaxableIncome();
        $V = $this->getData()->getProvincialRate($A);
        $KP = $this->getData()->getProvincialConstant($A);
        $TCP = $this->getProvincialTotalClaimAmount();
        $K1P = bcmul($this->getData()->getProvincialLowestRate(), $TCP);
        if ($this->getEnableCPPAndEIDeduction() == true) {
            $K2P = $this->getProvincialCPPAndEITaxCredit();
        } else {
            $K2P = 0; //Use the Company Deduction Exclude funtionality instead.
        }
        $K3P = 0;
        $K4P = $this->getProvincialEmploymentCredit();

        Debug::text('A: ' . $A, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('V: ' . $V, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('KP: ' . $KP, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('TCP: ' . $TCP, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K1P: ' . $K1P, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K2P: ' . $K2P, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K3P: ' . $K3P, __FILE__, __LINE__, __METHOD__, 10);
        Debug::text('K4P: ' . $K4P, __FILE__, __LINE__, __METHOD__, 10);

        //$T4 = ($V * $A) - $KP - $K1P - $K2P - $K3P;
        $T4 = bcsub(bcsub(bcsub(bcsub(bcsub(bcmul($V, $A), $KP), $K1P), $K2P), $K3P), $K4P);

        if ($T4 < 0) {
            $T4 = 0;
        }

        Debug::text('T4: ' . $T4, __FILE__, __LINE__, __METHOD__, 10);

        return $T4;
    }

    public function getProvincialTotalClaimAmount()
    {
        //Check to make sure the claim amount is at the minimum,
        //as long as it is NOT 0. (outside country)

        //Check claim amount from the previous year, so if the current year setting matches
        //that exactly, we know to use the current year value instead.
        //This helps when the claim amount decreases.
        //Also check next years amount in case the amount gets increased then they try to calculate pay stubs in the previous year.
        $previous_year = $this->getISODate((TTDate::getBeginYearEpoch($this->getDateEpoch()) - 86400));
        $next_year = $this->getISODate((TTDate::getEndYearEpoch($this->getDateEpoch()) + 86400));

        if ($this->data['provincial_total_claim_amount'] > 0) {
            if ($this->getBasicProvinceClaimCodeAmount() > 0
                and (
                    $this->data['provincial_total_claim_amount'] < $this->getBasicProvinceClaimCodeAmount()
                    or
                    $this->data['provincial_total_claim_amount'] == $this->getBasicProvinceClaimCodeAmount($previous_year)
                    or
                    $this->data['provincial_total_claim_amount'] == $this->getBasicProvinceClaimCodeAmount($next_year)
                )
            ) {
                Debug::text('Using Basic Provincial Claim Code Amount: ' . $this->getBasicProvinceClaimCodeAmount() . ' (Previous Amount: ' . $this->data['provincial_total_claim_amount'] . ') Date: ' . TTDate::getDate('DATE', $this->getDateEpoch()), __FILE__, __LINE__, __METHOD__, 10);
                return $this->getBasicProvinceClaimCodeAmount();
            }
        }

        return $this->data['provincial_total_claim_amount'];
    }

    public function getProvincialCPPAndEITaxCredit()
    {
        $K2P = bcadd($this->getCPPTaxCredit('provincial'), $this->getEITaxCredit('provincial'));
        Debug::text('K2P: ' . $K2P, __FILE__, __LINE__, __METHOD__, 10);

        return $K2P;
    }

    public function getProvincialEmploymentCredit()
    {
        /*
          K4P = The lesser of
            0.155 * A and
            0.155 * $1000
        */

        //Yukon only currently.
        $K4P = 0;
        Debug::text('K4P: ' . $K4P, __FILE__, __LINE__, __METHOD__, 10);
        return $K4P;
    }

    public function getProvincialSurtax()
    {
        /*
            V1 =
            For Ontario
                Where T4 <= 4016
                V1 = 0

                Where T4 > 4016 <= 5065
                V1 = 0.20 * ( T4 - 4016 )

                Where T4 > 5065
                V1 = 0.20 * (T4 - 4016) + 0.36 * (T4 - 5065)

        */

        $T4 = $this->getProvincialBasicTax();
        $V1 = 0;

        Debug::text('V1: ' . $V1, __FILE__, __LINE__, __METHOD__, 10);

        return $V1;
    }

    public function getAdditionalProvincialSurtax()
    {
        /*
            V2 =

            Where A < 20,000
            V2 = 0

            Where A >
        */

        $A = $this->getAnnualTaxableIncome();
        $V2 = 0;

        Debug::text('V2: ' . $V2, __FILE__, __LINE__, __METHOD__, 10);

        return $V2;
    }

    public function getProvincialTaxReduction()
    {
        $A = $this->getAnnualTaxableIncome();
        $T4 = $this->getProvincialBasicTax();
        $V1 = $this->getProvincialSurtax();
        $Y = 0;
        $S = 0;

        Debug::text('No Specific Province: ' . $this->getProvince(), __FILE__, __LINE__, __METHOD__, 10);

        Debug::text('aS: ' . $S, __FILE__, __LINE__, __METHOD__, 10);

        if ($S < 0) {
            $S = 0;
        }

        Debug::text('bS: ' . $S, __FILE__, __LINE__, __METHOD__, 10);

        return $S;
    }

    public function getFederalAdditionalDeduction()
    {
        if (isset($this->data['additional_deduction'])) {
            return $this->data['additional_deduction'];
        }

        return false;
    }

    public function getArray()
    {
        $array = array(
            'gross_pay' => $this->getGrossPayPeriodIncome(),
            'federal_tax' => $this->getFederalPayPeriodDeductions(),
            'provincial_tax' => $this->getProvincialPayPeriodDeductions(),
            'total_tax' => $this->getPayPeriodTaxDeductions(),
            'employee_cpp' => $this->getEmployeeCPP(),
            'employer_cpp' => $this->getEmployerCPP(),
            'employee_ei' => $this->getEmployeeEI(),
            'employer_ei' => $this->getEmployerEI(),
            'employer_wcb' => $this->getEmployerWCB(),
            'federal_additional_deduction' => $this->getFederalAdditionalDeduction(),
            //'net_pay' => $this->getPayPeriodNetPay()
        );

        Debug::Arr($array, 'Deductions Array:', __FILE__, __LINE__, __METHOD__, 10);

        return $array;
    }

    public function getFederalPayPeriodDeductions()
    {
        if ($this->getFormulaType() == 20) {
            Debug::text('Formula Type: ' . $this->getFormulaType() . ' YTD Payable: ' . $this->getFederalTaxPayable() . ' YTD Paid: ' . $this->getYearToDateDeduction() . ' Current PP: ' . $this->getCurrentPayPeriod(), __FILE__, __LINE__, __METHOD__, 10);
            $retval = $this->calcNonPeriodicDeduction($this->getFederalTaxPayable(), $this->getYearToDateDeduction());
        } else {
            $retval = bcdiv($this->getFederalTaxPayable(), $this->getAnnualPayPeriods());
        }
        return $retval;
    }

    public function getProvincialPayPeriodDeductions()
    {
        if ($this->getFormulaType() == 20) {
            Debug::text('Formula Type: ' . $this->getFormulaType() . ' YTD Payable: ' . $this->getProvincialTaxPayable() . ' YTD Paid: ' . $this->getYearToDateDeduction() . ' Current PP: ' . $this->getCurrentPayPeriod(), __FILE__, __LINE__, __METHOD__, 10);
            $retval = $this->calcNonPeriodicDeduction($this->getProvincialTaxPayable(), $this->getYearToDateDeduction());
        } else {
            $retval = bcdiv($this->getProvincialTaxPayable(), $this->getAnnualPayPeriods());
        }
        return $retval;
    }

    public function getEmployerCPP()
    {
        //EmployerCPP is the same as EmployeeCPP
        return $this->getEmployeeCPP();
    }

    public function getEmployerEI()
    {
        //$EI = $this->getEmployeeEI() * $this->ei_employer_rate;
        //$EI = $this->getEmployeeEI() * $this->getEIEmployerRate();
        $EI = bcmul($this->getEmployeeEI(), $this->getEIEmployerRate());

        Debug::text('Employer EI: ' . $EI . ' Rate: ' . $this->getEIEmployerRate(), __FILE__, __LINE__, __METHOD__, 10);

        return $EI;
    }

    public function getEmployerWCB()
    {
        if ($this->getWCBRate() != false and $this->getWCBRate() > 0) {
            //$WCB = $this->getGrossPayPeriodIncome() * $this->getWCBRate();
            $WCB = bcmul($this->getGrossPayPeriodIncome(), $this->getWCBRate());

            Debug::text('Employer WCB: ' . $WCB . ' Rate: ' . $this->getWCBRate(), __FILE__, __LINE__, __METHOD__, 10);

            return $WCB;
        }

        return false;
    }

    /*
        Use this to get all useful values.
    */

    public function getWCBRate()
    {
        //Divide rate by 100 so its not a percent anymore.
        return bcdiv($this->data['wcb_rate'], 100);

        return true;
    }
}
