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
 * @package Modules\Install
 */
class InstallSchema_1041A extends InstallSchema_Base
{
    public function preInstall()
    {
        Debug::text('preInstall: ' . $this->getVersion(), __FILE__, __LINE__, __METHOD__, 9);

        return true;
    }

    public function postInstall()
    {
        Debug::text('postInstall: ' . $this->getVersion(), __FILE__, __LINE__, __METHOD__, 9);

        //Modify all hierarchies with the request object type included, to add new request object types.
        $hclf = TTnew('HierarchyControlListFactory');
        $hclf->getAll();
        if ($hclf->getRecordCount() > 0) {
            foreach ($hclf as $hc_obj) {
                $src_object_types = $hc_obj->getObjectType();
                $request_key = array_search(50, $src_object_types);
                if ($request_key !== false) {
                    Debug::Text('Found request object type, ID: ' . $hc_obj->getId() . ' Company ID: ' . $hc_obj->getCompany(), __FILE__, __LINE__, __METHOD__, 10);
                    unset($src_object_types[$request_key]);

                    $src_object_types[] = 1010;
                    $src_object_types[] = 1020;
                    $src_object_types[] = 1030;
                    $src_object_types[] = 1040;
                    $src_object_types[] = 1100;
                    $src_object_types = array_unique($src_object_types);

                    $hc_obj->setObjectType($src_object_types);
                    if ($hc_obj->isValid()) {
                        $hc_obj->Save();
                    }
                } else {
                    Debug::Text('Request object type not found for ID: ' . $hc_obj->getId() . ' Company ID: ' . $hc_obj->getCompany(), __FILE__, __LINE__, __METHOD__, 10);
                }
            }
        }

        return true;
    }
}
