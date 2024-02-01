<?if(!$USER->IsAdmin()) return;
IncludeModuleLangFile(__FILE__);

CModule::IncludeModule('imyie.stores4filter');
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');

$arIBlocks = array();
$arOrder = array("SORT"=>"ASC","ID"=>"ASC");
$arFilter = array("ACTIVE"=>"Y");
$dbRes = CIBlock::GetList($arOrder,$arFilter);
while($arFields = $dbRes->Fetch())
{
	if($arCatalog = CCatalog::GetByIDExt($arFields["ID"]))
	{
		if($arCatalog["CATALOG"]=="Y")
		{
			$arIBlocks[] = $arFields;
		}
	}
}

if(isset($_REQUEST["save"]) && check_bitrix_sessid())
{
	$arrForSave = array();
	foreach($arIBlocks as $arIBlock)
	{
		if(in_array($arIBlock["ID"],$_REQUEST["imyie_s4f_iblocks"]))
		{
			$arrForSave[] = $arIBlock["ID"];
		}
	}
	if(is_array($arrForSave) && count($arrForSave)>0)
	{
		COption::SetOptionString("imyie.stores4filter", "imyie_s4f_iblocks", serialize($arrForSave) );
	}
}

$aTabs = array(
	array("DIV" => "imyie_stores4filter", "TAB" => GetMessage("IMYIE_S4F_SETTINGS"), "ICON" => "settings", "TITLE" => GetMessage("IMYIE_S4F_TITLE")),
);
$tabControl = new CAdminTabControl("tabControl", $aTabs);

$tabControl->Begin();
?>
<form method="post" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($mid)?>&amp;lang=<?=LANGUAGE_ID?>">
	<?=bitrix_sessid_post();?>


	<?$tabControl->BeginNextTab();?>
		<tr>
			<?$imyie_s4f_iblocks = unserialize( COption::GetOptionString("imyie.stores4filter", "imyie_s4f_iblocks", serialize(array()) ) );?>
			<td valign="top" width="50%"><?=GetMessage("IMYIE_S4F_IBLOCKS")?>:</td>
			<td valign="top" width="50%"><?
				if(is_array($arIBlocks) && count($arIBlocks)>0)
				{
					?><select name="imyie_s4f_iblocks[]" multiple><?
					foreach($arIBlocks as $arIBlock)
					{
						?><option value="<?=$arIBlock["ID"]?>"<?if(in_array($arIBlock["ID"],$imyie_s4f_iblocks)):?> selected <?endif;?>>[<?=$arIBlock["ID"]?>] <?=$arIBlock["NAME"]?></option><?
					}
					?></select><?
				} else {
					echo GetMessage("IMYIE_S4F_EMPTY_IBLOCKS");
				}
			?></td>
		</tr>


	<?$tabControl->Buttons();?>
		<input type="submit" name="save" value="<?=GetMessage("IMYIE_S4F_BTN_SAVE")?>">
	<?$tabControl->End();?>
</form>