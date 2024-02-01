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

            // values ne sovpali
            if ($arrValues != $arrValuesOld) {
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
                $CIBlockProp = new CIBlockProperty();
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

            $ibp = new CIBlockProperty();
            $PropID = $ibp->Add($arFields);
        }
    }
}
