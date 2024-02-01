<?php

IncludeModuleLangFile(__FILE__);

class CIMYIEStores4Filter
{
    public static function OnStoreProductUpdateHandler($id, $arFields)
    {
        global $APPLICATION;
        $PROPERTY_CODE = "IMYIE_STORES4FILTER";

        $ELEMENT_ID = $arFields["PRODUCT_ID"];

        $dbRes = CIBlockElement::GetByID($ELEMENT_ID);
        if ($arElement = $dbRes->GetNext()) {
            $imyie_s4f_iblocks = unserialize(
                COption::GetOptionString("imyie.stores4filter", "imyie_s4f_iblocks", array())
            );
            $IBLOCK_ID = $arElement["IBLOCK_ID"];
            if (CModule::IncludeModule('iblock') && CModule::IncludeModule('catalog') && in_array(
                    $IBLOCK_ID,
                    $imyie_s4f_iblocks
                ) && $PROPERTY_CODE != "") {
                CIMYIEStores4Filter::CheckProperty($IBLOCK_ID, $PROPERTY_CODE);

                // store
                $arStores = array();
                $arOrder = array("SORT" => "ASC");
                $arFilter = array("PRODUCT_ID" => $ELEMENT_ID, "ACTIVE" => "Y");
                $arSelect = array("ID", "TITLE", "ADDRESS", "PRODUCT_AMOUNT");
                $rsProps = CCatalogStore::GetList($arOrder, $arFilter, false, false, $arSelect);
                while ($arStore = $rsProps->GetNext()) {
                    $arStores[] = $arStore;
                }
                // /store

                $arEnums = array();
                $arOrderEn = array("ID" => "ASC");
                $arFilterEn = array("IBLOCK_ID" => $IBLOCK_ID, "CODE" => $PROPERTY_CODE);
                $dbEnums = CIBlockPropertyEnum::GetList($arOrderEn, $arFilterEn);
                while ($arEnum = $dbEnums->GetNext()) {
                    $arEnums[$arEnum["VALUE"]] = $arEnum;
                }

                $PROPERTY_VALUES = array("VALUE" => "");
                foreach ($arStores as $arStor) {
                    if ($arStor["PRODUCT_AMOUNT"] > 0) {
                        $PROPERTY_VALUES[] = array("VALUE" => $arEnums[$arStor["TITLE"]]["ID"]);
                    }
                }

                CIBlockElement::SetPropertyValuesEx($ELEMENT_ID, $IBLOCK_ID, array($PROPERTY_CODE => $PROPERTY_VALUES));
                \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($IBLOCK_ID, $ELEMENT_ID);
            }
        }
    }

    public static function CheckProperty($IBLOCK_ID, $PROPERTY_CODE)
    {
        // stores
        $arrValues = array();
        $arOrderSt = array("ID" => "ASC");
        $arFilterSt = array();
        $dbStores = CCatalogStore::GetList($arOrderSt, $arFilterSt);
        while ($arStore = $dbStores->GetNext()) {
            $arrValues[] = array(
                "XML_ID" => ($arStore["XML_ID"] != "" ? $arStore["XML_ID"] : $arStore["ID"]),
                "VALUE" => $arStore["TITLE"],
                "DEF" => "N",
            );
        }
        // /stores

        $dbProperties = CIBlockProperty::GetList(
            array("sort" => "asc", "name" => "asc"),
            array("IBLOCK_ID" => $IBLOCK_ID, "CODE" => $PROPERTY_CODE)
        );
        if ($arFields = $dbProperties->GetNext()) {
            $PROPERTY_ID = $arFields["ID"];
            $arrValuesOld = array();
            $arrValuesOld2 = array();
            // check values
            $arOrderEn = array("ID" => "ASC");
            $arFilterEn = array("IBLOCK_ID" => $IBLOCK_ID, "CODE" => $PROPERTY_CODE);
            $dbEnums = CIBlockPropertyEnum::GetList($arOrderEn, $arFilterEn);
            while ($arEnum = $dbEnums->GetNext()) {
                $arrValuesOld[] = array(
                    "XML_ID" => ($arEnum["XML_ID"] != "" ? $arEnum["XML_ID"] : $arEnum["ID"]),
                    "VALUE" => $arEnum["VALUE"],
                    "DEF" => "N",
                );
                $arrValuesOld2[$arEnum["ID"]] = array(
                    "XML_ID" => ($arEnum["XML_ID"] != "" ? $arEnum["XML_ID"] : $arEnum["ID"]),
                    "VALUE" => $arEnum["VALUE"],
                    "DEF" => "N",
                );
            }

            if ($arrValues != $arrValuesOld) { // values ne sovpali
                $arNewValues = array();
                foreach ($arrValues as $arNow) {
                    $was = false;
                    foreach ($arrValuesOld2 as $id => $arWas) {
                        if ($arNow["VALUE"] == $arWas["VALUE"]) {
                            $was = true;
                            $arNewValues[$id] = $arWas;
                            break;
                        }
                    }
                    if (!$was) {
                        $arNewValues[] = $arNow;
                    }
                }
                $CIBlockProp = new CIBlockProperty;
                $CIBlockProp->UpdateEnum($PROPERTY_ID, $arNewValues);
            }
        } else {
            // add property
            $arFields = array(
                "IBLOCK_ID" => $IBLOCK_ID,
                "NAME" => GetMessage("IMYIE_S4F_CLASS_PROP_NAME"),
                "ACTIVE" => "Y",
                "SORT" => "100000",
                "CODE" => $PROPERTY_CODE,
                "PROPERTY_TYPE" => "L",
                "MULTIPLE" => "Y",
                "VALUES" => $arrValues,
            );

            $ibp = new CIBlockProperty;
            $PropID = $ibp->Add($arFields);
        }
    }

    public static function CheckSettings($StartID, $IBLOCK_ID, $arSettings)
    {
        $arDefaultSetings = array(
            "time_limit" => 20,
            "time_delay" => 3,
            "full_count_el" => 0,
            "chetchik" => 0,
        );
        foreach ($arDefaultSetings as $key => $value) {
            if (array_key_exists($key, $arSettings)) {
                $arDefaultSetings[$key] = $arSettings[$key];
            }
        }
        $StartID = intval($StartID);
        $IBLOCK_ID = intval($IBLOCK_ID);
        if ($IBLOCK_ID < 1) {
            return false;
        }
        if (!CModule::IncludeModule("iblock")) {
            return false;
        }
        $arDefaultSetings["START_ID"] = $StartID;
        $arDefaultSetings["IBLOCK_ID"] = $IBLOCK_ID;
        return $arDefaultSetings;
    }

    public static function Processing($StartID, $IBLOCK_ID, $arSettings)
    {
        global $DB, $APPLICATION;
        $chetchik = 0;
        $the_end = "N";
        $time_start = time();

        $arSelect = array("ID", "IBLOCK_ID", "ACTIVE", "ACTIVE_DATE");
        $arFilter = array(">ID" => $StartID, "IBLOCK_ID" => $IBLOCK_ID, "ACTIVE" => "Y", "ACTIVE_DATE" => "Y");
        if ($StartID < 1) {
            $full_count_el = CIBlockElement::GetList(
                array("ID" => "ASC"),
                $arFilter,
                array(),
                array("nPageSize" => 1000000),
                $arSelect
            );
        } else {
            $full_count_el = $arSettings["full_count_el"];
        }
        $dbRes = CIBlockElement::GetList(
            array("ID" => "ASC"),
            $arFilter,
            false,
            array("nPageSize" => 1000000),
            $arSelect
        );
        while ($row = $dbRes->GetNext()) {
            usleep(500000); //0.5sec
            $LastID = $row["ID"];
            $arFields = array("PRODUCT_ID" => $LastID);
            CIMYIEStores4Filter::OnStoreProductUpdateHandler(1, $arFields);
            $time_now = time();
            if (($time_now - $time_start) > $arSettings["time_limit"]) {
                $the_end = "N";
                break;
            } else {
                $the_end = "Y";
            }
            $chetchik++;
        }

        $result_html = CIMYIEStores4Filter::_ReturneJS(
            $LastID,
            $IBLOCK_ID,
            $arSettings,
            $full_count_el,
            $chetchik,
            $the_end
        );
        return $result_html;
    }

    public static function _ReturneJS($LastID, $IBLOCK_ID, $arSettings, $full_count_el, $chetchik, $the_end)
    {
        global $DB, $APPLICATION;

        if ($the_end == "N") {
            $m = new CAdminMessage(
                array(
                    "TYPE" => "PROGRESS",
                    "DETAILS" => GetMessage(
                            "IMYIE_S4F_SHOWMESSAGE_PROGRESS",
                            array(
                                "#VSEGO#" => $full_count_el,
                                "#OBRABOTANO#" => ($arSettings["chetchik"] + $chetchik)
                            )
                        ) . "#PROGRESS_BAR#",
                    "HTML" => true,
                    "PROGRESS_TOTAL" => $full_count_el,
                    "PROGRESS_VALUE" => ($arSettings["chetchik"] + $chetchik),
                )
            );
            $messaga = $m->Show();
            $return = '<script>CloseWaitWindow();';
            $return .= 'document.getElementById("imyie_stores4filter_result").innerHTML = \'' . CUtil::JSEscape(
                    $messaga
                ) . '\';';
            $return .= 'setTimeout(function(){
	IMYIE_S4F_Start(' . $LastID . ',' . $full_count_el . ',' . ($arSettings["chetchik"] + $chetchik) . ');
}, ' . ($arSettings["time_delay"]) . '000);
</script>';
        } else {
            $m = new CAdminMessage(
                array(
                    "TYPE" => "PROGRESS",
                    "DETAILS" => GetMessage("IMYIE_S4F_SHOWMESSAGE_PROGRESS_FINISH") . "#PROGRESS_BAR#",
                    "HTML" => true,
                    "PROGRESS_TOTAL" => 100,
                    "PROGRESS_VALUE" => 100,
                )
            );
            $messaga = $m->Show();
            $return = '<script>CloseWaitWindow();';
            $return .= 'document.getElementById("imyie_stores4filter_result").innerHTML = \'' . CUtil::JSEscape(
                    $messaga
                ) . '\';';
            $return .= 'IMYIE_S4F_Finish();';
            $return .= '</script>';
        }
        return $return;
    }
}
