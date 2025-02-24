<?php

/*
 * itemLink.php -- by Dave Humphrey (dave@uesp.net), December 2014
 * 
 * Outputs a HTML page containing an ESO item and its data in the same/similar format
 * as the in-game item tooltips.
 * 
 * TODO:
 *		- Items with no level/quality data
 *		- Items with same name but different itemIds
 *		- Output extra data:
 *			- style
 *			- craft related
 *			- glyph related
 *			- flags
 *		- Search
 *		- Browse items
 *		- IE fix
 *		- Better error page
 *		- Page Types:
 *			- Normal
 *			- Short/plain link
 *			- Error
 *		- Better web font
 *		- Armor/weapon models/textures/pictures?
 *		- Handle "missing" levels/qualities
 *			- Items with no level data (fixed level like item #5216)?
 *		- Combine identical enchantments
 *		- Dynamic updates of enchantment data?
 *		- Controls for enchantment level/quality
 *		- Check "comment" data mismatches
 *		- Move icons to files1
 *		- Long item names (26721, 26658, 57584=46 chars)
 *		- Remove temporary web fonts (wait for new fonts to be used for a while to prevent caching issues)
 *		- Infused modify in client side JS
 * 
 */

// Database users, passwords and other secrets
require("/home/uesp/secrets/esolog.secrets");
require("esoCommon.php");
require("esoPotionData.php");


function compareRawDataSortedIndex($a, $b)
{
	$a0 = (int) $a[0];
	$b0 = (int) $b[0];
	
	if ($a0 == $b0)
	{
		$a1 = (int) $a[1];
		$b1 = (int) $b[1];
		if ($a1 == $b1) return 0;
		return ($a1 < $b1) ? -1 : 1;
	}

	return ($a0 < $b0) ? -1 : 1;
}


class CEsoItemLink
{
	const ESOIL_HTML_TEMPLATE = "templates/esoitemlink_template.txt";
	const ESOIL_RAWDATA_HTML_TEMPLATE = "templates/esoitemlink_rawdata_template.txt";
	const ESOIL_HTML_EMBED_TEMPLATE = "templates/esoitemlink_embed_template.txt";
	const ESOIL_ICON_PATH = "/home/uesp/www/eso/gameicons/";
	const ESOIL_ICON_URL = UESP_ESO_ICON_URL;
	const ESOIL_ICON_UNKNOWN = "/unknown.png";
	
	const ESOIL_POTION_MAGICITEMID = 1;
	const ESOIL_POISON_MAGICITEMID = 2;
	const ESOIL_ENCHANT_ITEMID = 23662;
	
	const MAXSETINDEX = 12;
	
		// Weird results based on level when enabled (depends on level of character not item)
	const ESOIL_USEPRECENT_CRITICALVALUE = false;
	
	static public $ESOIL_ERROR_ITEM_DATA = array(
			"name" => "Unknown",
			"itemId" => 0,
			"internalSubtype" => 0,
			"internalLevel" => 0,
			"quality" => 0,
			"level" => "?",
			"value" => "?",
			"type" => 0,
			"bind" => 0,
			"description" => "Unknown item!",
			"icon" => "/esoui/art/icons/icon_missing.dds",
			"style" => -1,
	);
	
	static public $ESOIL_ERROR_SETITEM_DATA = array(
			"name" => "Unknown",
			"set" => "Unknown",
			"itemId" => 0,
			"internalSubtype" => 0,
			"internalLevel" => 0,
			"quality" => 0,
			"level" => "?",
			"value" => "?",
			"type" => 0,
			"bind" => 0,
			"description" => "Unknown set!",
			"icon" => "/esoui/art/icons/icon_missing.dds",
			"style" => -1,
	);
	
	static public $ESOIL_ERROR_QUESTITEM_DATA = array(
			"name" => "Unknown",
			"questId" => "",
			"itemLink" => "",
			"questName" => "",
			"itemId" => "",
			"header" => "",
			"icon" => "/esoui/art/icons/icon_missing.dds",
			"description" => "Unknown quest item!",
			"stepIndex" => "",
			"conditionIndex" => "",
	);
	
	static public $ESOIL_ERROR_COLLECTIBLEITEM_DATA = array(
			"name" => "Unknown",
			"itemLink" => "",
			"nickname" => "",
			"description" => "Unknown collectible item!",
			"hint" => "",
			"icon" => "/esoui/art/icons/icon_missing.dds",
			"lockedIcon" => "",
			"backgroundIcon" => "",
			"categoryType" => "",
			"zoneIndex" => "",
			"categoryIndex" => "",
			"subCategoryIndex" => "",
			"collectibleIndex" => "",
			"achievementIndex" => "",
			"categoryName" => "",
			"subCategoryName" => "",
			"isUnlocked" => "",
			"isActive" => "",
			"isSlottable" => "",
			"isUsable" => "",
			"isPlaceholder" => "",
			"isHidden" => "",
			"hasAppeareance" => "",
			"visualPriority" => "",
			"helpCategoryIndex" => "",
			"helpIndex" => "",
			"questName" => "",
			"backgroundText" => "",
			"cooldown" => "",
	);
	
	static public $ESOIL_ERROR_ANTIQUITYITEM_DATA = array(
			"name" => "Unknown",
			"itemLink" => "",
			"itemId" => "",
			"header" => "",
			"icon" => "/esoui/art/icons/icon_missing.dds",
			"description" => "Unknown antiquity lead!",
			"stepIndex" => "",
			"conditionIndex" => "",
	);
	
	static public $ESOIL_ITEM_SUMMARY_FIELDS = array(
			"level",
			"value",
			"weaponPower",
			"armorRating",
			"traitDesc",
			"enchantDesc",
			"abilityDesc",
			"traitAbilityDesc",
			"setBonusDesc1",
			"setBonusDesc2",
			"setBonusDesc3",
			"setBonusDesc4",
			"setBonusDesc5",
			"setBonusDesc6",
			"setBonusDesc7",
			"setBonusDesc8",
			"setBonusDesc9",
			"setBonusDesc10",
			"setBonusDesc11",
			"setBonusDesc12",
			"icon",
	);
	
	public $inputParams = array();
	public $itemId = 0;
	public $itemLink = "";
	public $itemLevel = 66;		// 1-66
	public $itemQuality = 5;	// 0-5
	public $hasSetItemLevel = false;
	public $hasSetItemQuality = false;
	public $itemIntLevel = 50;	// 1-50
	public $itemIntType = 370;	// 1-400
	public $itemBound = -1;
	public $itemStyle = -1;
	public $itemCrafted = -1;
	public $itemCharges = -1;
	public $itemPotionData = -1;
	public $itemIsPoison = false;
	public $itemStolen = -1;
	public $itemSetCount = -1;
	public $itemPerfectCount = -1;
	public $itemDisableEnchant = false;
	public $itemTrait = 0;
	public $itemSet = "";
	public $showSetSummary = false;
	public $showStyle = true;
	public $enchantId1 = 0;
	public $enchantIntLevel1 = 0;
	public $enchantIntType1 = 0;
	public $enchantFactor = 0;
	public $weaponTraitFactor = 0;
	public $extraArmor = 0;
	public $inputIntType = -1;
	public $inputIntLevel = -1;
	public $inputLevel = -1;
	public $inputQuality = -1;
	public $itemRecord = array();
	public $transmuteTrait = 0;
	public $writData1 = 0;
	public $writData2 = 0;
	public $writData3 = 0;
	public $writData4 = 0;
	public $writData5 = 0;
	public $writData6 = 0;
	public $resultItemRecord = array();
	public $enchantRecord1 = null;
	public $enchantRecord2 = null;
	public $itemAllData = array();
	public $version = "";
	public $useUpdate10Display = true;
	public $itemSimilarRecords = array();
	public $itemSummary = array();
	public $outputType = "html";
	public $outputRaw = false;
	public $rawDataKeys = array();
	public $rawDataSortedIndexes = array();
	public $showAll = false;
	public $itemErrorDesc = "";
	public $db = null;
	public $htmlTemplate = "";
	public $embedLink = false;
	public $showSummary = false;
	
	public $questItemId = -1;
	public $questItemData = array();
	
	public $collectibleItemId = -1;
	public $collectibleItemData = array();
	
	public $antiquityItemId = -1;
	public $antiquityItemData = array();
	
	public $setItemData = array();
	
	
	public function __construct ()
	{
		$this->SetInputParams();
		$this->ParseInputParams();
		$this->InitDatabase();
	}
	
	
	public function ReportError($errorMsg)
	{
		//print($errorMsg);
		error_log($errorMsg);
		return false;
	}
	
	
	public function escape($text)
	{
		if (gettype($text) != "string") return $text;
		return htmlspecialchars($text);
	}
	
	public function ParseItemLink($itemLink)
	{
		$matches = ParseEsoItemLink($itemLink);
		if (!$matches) return $this->ReportError("Failed to parse item link: $itemLink");
		
		$this->itemId = (int) $matches['itemId'];
		$this->itemIntLevel = (int) $matches['level'];
		$this->itemIntType = (int) $matches['subtype'];
		
		$this->itemStyle = (int) $matches['style'];
		$this->itemBound = (int) $matches['bound'];
		$this->itemCrafted = (int) $matches['crafted'];
		$this->itemCharges = (int) $matches['charges'];
		$this->itemPotionData = (int) $matches['potionData'];
		$this->itemStolen = (int) $matches['stolen'];
		
		$this->enchantId1 = (int) $matches['enchantId1'];
		$this->enchantIntLevel1 = (int) $matches['enchantLevel1'];
		$this->enchantIntType1 = (int) $matches['enchantSubtype1'];
		
		$this->writData1 = (int) $matches['writ1'];
		$this->writData2 = (int) $matches['writ2'];
		$this->writData3 = (int) $matches['writ3'];
		$this->writData4 = (int) $matches['writ4'];
		$this->writData5 = (int) $matches['writ5'];
		$this->writData6 = (int) $matches['writ6'];
		
		$this->transmuteTrait = $this->writData1;
		
		return true;
	}
	
	
	private function ParseInputParams ()
	{
		if (array_key_exists('itemlink', $this->inputParams))
		{ 
			$this->itemLink = urldecode($this->inputParams['itemlink']);
			$this->ParseItemLink($this->itemLink);
		}
		elseif (array_key_exists('link', $this->inputParams))
		{
			$this->itemLink = urldecode($this->inputParams['link']);
			$this->ParseItemLink($this->itemLink);
		}
		
		if (array_key_exists('itemid', $this->inputParams)) $this->itemId = (int) $this->inputParams['itemid'];
		
		if (array_key_exists('summary', $this->inputParams))
		{
			$this->showSummary = true;
			$this->showSetSummary = true;
		}
		
		if (array_key_exists('intlevel', $this->inputParams))
		{
			$this->itemLevel = -1;
			$this->itemQuality = -1;
			$this->itemIntLevel = (int) $this->inputParams['intlevel'];
		}
		
		if (array_key_exists('inttype', $this->inputParams))
		{
			$this->itemLevel = -1;
			$this->itemQuality = -1;
			$this->itemIntType = (int) $this->inputParams['inttype'];
		}
		
		if (array_key_exists('level', $this->inputParams))
		{
			$level = strtolower($this->inputParams['level']);
			$this->hasSetItemLevel = true;
			
			if ($level[0] == 'v')
			{
				$this->itemLevel = (int) ltrim($level, 'v') + 50;
			}
			else if ($level[0] == 'c' && $level[1] == 'p')
			{
				$this->itemLevel = floor(((int) substr($level, 2))/10) + 50;
			}
			else
			{
				$this->itemLevel = (int) $level;
			}
			
			if (!$this->showSummary) $this->itemQuality = 1;
		}
		
		if (array_key_exists('quality', $this->inputParams))
		{
			$this->itemQuality = (int) $this->inputParams['quality'];
			if (!$this->showSummary && $this->itemLevel < 0) $this->itemLevel = 1;
			$this->hasSetItemQuality = true;
		}
		
		if (array_key_exists('show', $this->inputParams) || array_key_exists('showall', $this->inputParams)) $this->showAll = true;
		if (array_key_exists('rawdata', $this->inputParams)) $this->outputRaw = true;
		if (array_key_exists('setcount', $this->inputParams)) $this->itemSetCount = (int) $this->inputParams['setcount'];
		if (array_key_exists('perfectcount', $this->inputParams)) $this->itemPerfectCount = (int) $this->inputParams['perfectcount'];
		if (array_key_exists('enchantdisable', $this->inputParams)) $this->itemDisableEnchant = ((int) $this->inputParams['enchantdisable']) != 0;
		if (array_key_exists('potiondata', $this->inputParams)) $this->itemPotionData = (int) $this->inputParams['potiondata'];
		if (array_key_exists('ispoison', $this->inputParams)) $this->itemIsPoison = ((int) $this->inputParams['ispoison']) != 0;
		if (array_key_exists('stolen', $this->inputParams)) $this->itemStolen = (int) $this->inputParams['stolen'];
		if (array_key_exists('style', $this->inputParams)) $this->itemStyle = (int) $this->inputParams['style'];
		if (array_key_exists('enchantfactor', $this->inputParams)) $this->enchantFactor = (float) $this->inputParams['enchantfactor'];
		if (array_key_exists('weapontraitfactor', $this->inputParams)) $this->weaponTraitFactor = (float) $this->inputParams['weapontraitfactor'];
		if (array_key_exists('extraarmor', $this->inputParams)) $this->extraArmor = (int) $this->inputParams['extraarmor'];
		if (array_key_exists('trait', $this->inputParams)) $this->transmuteTrait = (int) $this->inputParams['trait'];
		
		if (array_key_exists('extradata', $this->inputParams)) 
		{
			$extraData = explode(":", $this->inputParams['extradata']);
			$this->writData1 = intval($extraData[0]);
			$this->writData2 = intval($extraData[1]);
			$this->writData3 = intval($extraData[2]);
			$this->writData4 = intval($extraData[3]);
			$this->writData5 = intval($extraData[4]);
			$this->writData6 = intval($extraData[5]);
		}
		
		if (array_key_exists('writ1', $this->inputParams)) $this->writData1 = (int) $this->inputParams['writ1'];
		if (array_key_exists('writ2', $this->inputParams)) $this->writData2 = (int) $this->inputParams['writ2'];
		if (array_key_exists('writ3', $this->inputParams)) $this->writData3 = (int) $this->inputParams['writ3'];
		if (array_key_exists('writ4', $this->inputParams)) $this->writData4 = (int) $this->inputParams['writ4'];
		if (array_key_exists('writ5', $this->inputParams)) $this->writData5 = (int) $this->inputParams['writ5'];
		if (array_key_exists('writ6', $this->inputParams)) $this->writData6 = (int) $this->inputParams['writ6'];
		if (array_key_exists('vouchers', $this->inputParams)) $this->itemPotionData = (int) $this->inputParams['vouchers'];
		
		if (array_key_exists('version', $this->inputParams)) $this->version = urldecode($this->inputParams['version']);
		if (array_key_exists('v', $this->inputParams)) $this->version = urldecode($this->inputParams['v']);
		
		if (array_key_exists('embed', $this->inputParams))
		{
			$this->embedLink = true;
			$this->htmlTemplate = file_get_contents(self::ESOIL_HTML_EMBED_TEMPLATE);
		}
		else
		{
			$this->htmlTemplate = file_get_contents(self::ESOIL_HTML_TEMPLATE);
		}
		
		if (array_key_exists('enchantid', $this->inputParams))
		{
			$this->enchantId1 = (int) $this->inputParams['enchantid'];
			$this->enchantIntType1 = 1;
			$this->enchantIntLevel1 = 1;
		}
		
		if (array_key_exists('enchantintlevel', $this->inputParams))
		{
			$this->enchantIntLevel1 = (int) $this->inputParams['enchantintlevel'];
		}
		
		if (array_key_exists('enchantinttype', $this->inputParams))
		{
			$this->enchantIntType1 = (int) $this->inputParams['enchantinttype'];
		}
		
		if (array_key_exists('output', $this->inputParams)) 
		{
			$this->inputParams['output'] = strtolower($this->inputParams['output']);
			
			switch ($this->inputParams['output'])
			{
				case "text":
				case "html":
				case "csv":
					$this->outputType = $this->inputParams['output'];
					break;
			}
		}
		
		if (array_key_exists('qid', $this->inputParams)) $this->questItemId = (int) $this->inputParams['qid'];
		if (array_key_exists('questid', $this->inputParams)) $this->questItemId = (int) $this->inputParams['questid'];
		if (array_key_exists('cid', $this->inputParams)) $this->collectibleItemId = (int) $this->inputParams['cid'];
		if (array_key_exists('collectid', $this->inputParams)) $this->collectibleItemId = (int) $this->inputParams['collectid'];
		if (array_key_exists('collectibleid', $this->inputParams)) $this->collectibleItemId = (int) $this->inputParams['collectibleid'];
		if (array_key_exists('aid', $this->inputParams)) $this->antiquityItemId = (int) $this->inputParams['aid'];
		if (array_key_exists('antiid', $this->inputParams)) $this->antiquityItemId = (int) $this->inputParams['antiid'];
		if (array_key_exists('antiquityid', $this->inputParams)) $this->antiquityItemId = (int) $this->inputParams['antiquityid'];
		
		if (array_key_exists('set', $this->inputParams)) $this->itemSet = trim($this->inputParams['set']);
		
		$this->useUpdate10Display = IsEsoVersionAtLeast($this->version, 10);
		
		$this->inputIntType = $this->itemIntType;
		$this->inputIntLevel = $this->itemIntLevel;
		$this->inputLevel = $this->itemLevel;
		$this->inputQuality = $this->itemQuality;
		
		return true;
	}
	
	
	private function SetInputParams ()
	{
		global $argv;
		$this->inputParams = $_REQUEST;
		
			// Add command line arguments to input parameters for testing
		if ($argv !== null)
		{
			$argIndex = 0;
			
			foreach ($argv as $arg)
			{
				$argIndex += 1;
				if ($argIndex <= 1) continue;
				$e = explode("=", $arg);
				
				if(count($e) == 2)
				{
					$this->inputParams[$e[0]] = $e[1];
				}
				else
				{
					$this->inputParams[$e[0]] = 1;
				}
			}
		}
	}
	
	
	private function GetTableSuffix()
	{
		return GetEsoItemTableSuffix($this->version);
	}
	
	
	private function InitDatabase()
	{
		global $uespEsoLogReadDBHost, $uespEsoLogReadUser, $uespEsoLogReadPW, $uespEsoLogDatabase;
		
		$this->db = new mysqli($uespEsoLogReadDBHost, $uespEsoLogReadUser, $uespEsoLogReadPW, $uespEsoLogDatabase);
		if ($this->db->connect_error) return $this->ReportError("ERROR: Could not connect to mysql database!");
		
		UpdateEsoPageViews("itemLinkViews");
		
		return true;
	}
	
	
	private function ReduceAllItemData()
	{
		if (count($this->itemAllData) == 0) return;
		
		$firstItem = $this->itemAllData[0];
		
		foreach ($this->itemAllData as $index => &$item)
		{
			if ($firstItem == $item) continue;
			$delItems = array("link");
			
			foreach ($item as $key => $value)
			{
				if (array_key_exists($key, $firstItem) && $firstItem[$key] == $value)
				{
					$delItems[] = $key;
				}
			}
			
			foreach ($delItems as $key => $value)
			{
				unset($item[$value]);
			}
		}
	}
	
	
	private function LoadAllItemData()
	{
		$this->itemAllData = array();
		if ($this->itemId <= 0) return false;
		
		$minedTable = "minedItem" . $this->GetTableSuffix();
		$summaryTable = "minedItemSummary" . $this->GetTableSuffix();
		
		$query = "SELECT $summaryTable.*, $minedTable.* FROM $minedTable LEFT JOIN $summaryTable ON $summaryTable.itemId=$minedTable.itemId WHERE $minedTable.itemId='{$this->itemId}' ORDER BY $minedTable.level, $minedTable.quality;";
		
		$result = $this->db->query($query);
		if (!$result) return false;
		if ($result->num_rows === 0) return false;
		
		$result->data_seek(0);
		
		while (($row = $result->fetch_assoc()))
		{
					// TODO: Temporary fix for setMaxEquipCount
			if (array_key_exists('setMaxEquipCount', $row) && $row['setMaxEquipCount'] == -1)
			{
				$highestSetDesc = "";
				
				if (array_key_exists('setBonusDesc1', $row) && $row['setBonusDesc1'] != "") $highestSetDesc = $row['setBonusDesc1'];
				if (array_key_exists('setBonusDesc2', $row) && $row['setBonusDesc2'] != "") $highestSetDesc = $row['setBonusDesc2'];
				if (array_key_exists('setBonusDesc3', $row) && $row['setBonusDesc3'] != "") $highestSetDesc = $row['setBonusDesc3'];
				if (array_key_exists('setBonusDesc4', $row) && $row['setBonusDesc4'] != "") $highestSetDesc = $row['setBonusDesc4'];
				if (array_key_exists('setBonusDesc5', $row) && $row['setBonusDesc5'] != "") $highestSetDesc = $row['setBonusDesc5'];
				if (array_key_exists('setBonusDesc6', $row) && $row['setBonusDesc6'] != "") $highestSetDesc = $row['setBonusDesc6'];
				if (array_key_exists('setBonusDesc7', $row) && $row['setBonusDesc7'] != "") $highestSetDesc = $row['setBonusDesc7'];
				if (array_key_exists('setBonusDesc8', $row) && $row['setBonusDesc8'] != "") $highestSetDesc = $row['setBonusDesc8'];
				if (array_key_exists('setBonusDesc9', $row) && $row['setBonusDesc9'] != "") $highestSetDesc = $row['setBonusDesc9'];
				if (array_key_exists('setBonusDesc10', $row) && $row['setBonusDesc10'] != "") $highestSetDesc = $row['setBonusDesc10'];
				if (array_key_exists('setBonusDesc11', $row) && $row['setBonusDesc11'] != "") $highestSetDesc = $row['setBonusDesc11'];
				if (array_key_exists('setBonusDesc12', $row) && $row['setBonusDesc12'] != "") $highestSetDesc = $row['setBonusDesc12'];
				
				if ($highestSetDesc != "")
				{
					$matches = array();
					$matchResult = preg_match("/\(([0-9]+) items\)/", $highestSetDesc, $matches);
					if ($matchResult) $row['setMaxEquipCount'] = (int) $matches[1];
				}
			}
			
			$row['isBound'] =  $this->itemBound < 0 ? 0 : $this->itemBound;
			$row['charges'] =  $this->itemCharges < 0 ? 0 : $this->itemCharges;
			$row['isCrafted'] = $this->itemCrafted < 0 ? 0 : $this->itemCrafted;
			$row['enchantId1'] = $this->enchantId1;
			$row['enchantIntLevel1'] = $this->enchantIntLevel1;
			$row['enchantIntType1'] = $this->enchantIntType1;
			
			if ($this->enchantRecord1 != null)
			{
				$row['enchantName1'] = $this->enchantRecord1['enchantName'];
				$row['enchantDesc1'] = $this->enchantRecord1['enchantDesc'];
			}
			
			if ($this->itemStyle > 0 && $this->itemStyle != $row['style'])
			{
				$row['origStyle'] = $row['style'];
				$row['style'] = $this->itemStyle;
			}
			
			$row['version'] = $this->version;
			$row['name'] = preg_replace("#\|.*#", "", $row['name']);
			
			if ($row['weaponType'] == 14) $row['armorRating'] += $this->extraArmor;
			
			if ($row['link'] == null)
			{
				$internalLevel = $row['internalLevel'];
				$internalSubtype = $row['internalSubtype'];
				$row['link'] = "|H0:item:{$this->itemId}:$internalSubtype:$internalLevel:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0|h|h";
			}
			
			$this->itemAllData[] = $row;
		}
		
		$this->ReduceAllItemData();
		return true;
	}
	
	
	private function LoadItemErrorData()
	{
		$this->itemRecord = self::$ESOIL_ERROR_ITEM_DATA;
		$this->itemRecord['name'] = "Unknown Item #" . $this->itemId;
		$this->itemRecord['itemId'] = $this->itemId;
		$this->itemRecord['quality'] = $this->itemQuality;
		$this->itemRecord['level'] = $this->itemLevel;
		$this->itemRecord['internalSubtype'] = $this->itemIntType;
		$this->itemRecord['internalLevel'] = $this->itemIntLevel;
		$this->itemRecord['link'] = "";
		$this->itemRecord['secBonusCount1'] = -1;
		$this->itemRecord['secBonusCount2'] = -1;
		$this->itemRecord['secBonusCount3'] = -1;
		$this->itemRecord['secBonusCount4'] = -1;
		$this->itemRecord['secBonusCount5'] = -1;
		$this->itemRecord['enchantId1'] = 0;
		$this->itemRecord['enchantIntLevel1'] = 0;
		$this->itemRecord['enchantIntType1'] = 0;
		$this->itemRecord['traitAbilityDescArray'] = array();
		$this->itemRecord['traitCooldownArray'] = array();
		
		if ($this->itemLevel > 0 && $this->itemQuality >= 0)
			$this->itemRecord['description'] = "No item found matching itemId # {$this->itemId}, level {$this->itemLevel}, and quality {$this->itemQuality}!";
		else if ($this->itemIntType >= 0 && $this->itemIntLevel > 0)
			$this->itemRecord['description'] = "No item found matching itemId # {$this->itemId}, internalLevel {$this->itemIntLevel}, and internalSubtype {$this->itemIntType}!";
		else
			$this->itemRecord['description'] = "No item found matching itemId # {$this->itemId}!";
		
	}
	
	
	private function LoadSetItemErrorData()
	{
		$this->setItemData = self::$ESOIL_ERROR_SETITEM_DATA;
		
		$this->setItemData['name'] = "Unknown Set";
		$this->setItemData['setName'] = $this->itemSet;
		$this->setItemData['setMaxEquipCount'] = '0';
		$this->setItemData['setBonusCount'] = '0';
		$this->setItemData['description'] = "No set found matching '{$this->itemSet}'!";
	}
	
	
	private function LoadQuestItemErrorData()
	{
		$this->questItemData = self::$ESOIL_ERROR_QUESTITEM_DATA;
		
		$this->questItemData['name'] = "Unknown Quest Item";
		$this->questItemData['itemId'] = $this->questItemId;
		$this->questItemData['description'] = "No quest item found matching ID# {$this->questItemId}!";
	}
	
	
	private function LoadCollectibleItemErrorData()
	{
		$this->collectibleItemData = self::$ESOIL_ERROR_COLLECTIBLEITEM_DATA;
	
		$this->collectibleItemData['name'] = "Unknown Collectible";
		$this->collectibleItemData['id'] = $this->collectibleItemId;
		$this->collectibleItemData['description'] = "No collectible item found matching ID# {$this->collectibleItemId}!";
	}
	
	
	private function LoadAntiquityItemErrorData()
	{
		$this->antiquityItemData = self::$ESOIL_ERROR_ANTIQUITYITEM_DATA;
	
		$this->antiquityItemData['name'] = "Unknown Antiquity";
		$this->antiquityItemData['id'] = $this->antiquityItemId;
		$this->antiquityItemData['description'] = "No antiquity lead found matching ID# {$this->antiquityItemId}!";
	}
	
	
	public function MergeItemSummary()
	{
		if ($this->itemSummary == null || count($this->itemSummary) == 0) return false;
		
		foreach (self::$ESOIL_ITEM_SUMMARY_FIELDS as $field)
		{
			$value = $this->itemSummary[$field];
			
			if ($field == "level" && $value == "")
				$this->itemRecord[$field] = '1-CP160';
			else
				$this->itemRecord[$field] = $value;
		}
		
		$this->ParsePerfectSetCounts();
		
		return true;
	}
	
	
	public function MergeItemSummaryAll()
	{
		if ($this->itemSummary == null || count($this->itemSummary) == 0) return false;
	
		foreach ($this->itemSummary as $field => $value)
		{
			if ($field == "level" && $value == "")
				$this->itemRecord[$field] = '1-CP160';
			else
				$this->itemRecord[$field] = $value;
		}
		
		$this->itemRecord['setBonusCount'] = 0;
		$this->itemRecord['setMaxEquipCount'] = 0;
		$numSetItems = 0;
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc1'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc2'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc3'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc4'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc5'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc6'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc7'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc8'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc9'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc10'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc11'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$result = preg_match("#\(([0-9]+) items\)#", $this->itemRecord['setBonusDesc12'], $matches);
		if ($result) $numSetItems = max($numSetItems, $matches[1]);
		
		$this->itemRecord['setMaxEquipCount'] = $numSetItems;
		
		if ($this->itemRecord['setBonusDesc1'] != "") $this->itemRecord['setBonusCount'] = 1;
		if ($this->itemRecord['setBonusDesc2'] != "") $this->itemRecord['setBonusCount'] = 2;
		if ($this->itemRecord['setBonusDesc3'] != "") $this->itemRecord['setBonusCount'] = 3;
		if ($this->itemRecord['setBonusDesc4'] != "") $this->itemRecord['setBonusCount'] = 4;
		if ($this->itemRecord['setBonusDesc5'] != "") $this->itemRecord['setBonusCount'] = 5;
		if ($this->itemRecord['setBonusDesc6'] != "") $this->itemRecord['setBonusCount'] = 6;
		if ($this->itemRecord['setBonusDesc7'] != "") $this->itemRecord['setBonusCount'] = 7;
		if ($this->itemRecord['setBonusDesc8'] != "") $this->itemRecord['setBonusCount'] = 8;
		if ($this->itemRecord['setBonusDesc9'] != "") $this->itemRecord['setBonusCount'] = 9;
		if ($this->itemRecord['setBonusDesc10'] != "") $this->itemRecord['setBonusCount'] = 10;
		if ($this->itemRecord['setBonusDesc11'] != "") $this->itemRecord['setBonusCount'] = 11;
		if ($this->itemRecord['setBonusDesc12'] != "") $this->itemRecord['setBonusCount'] = 12;
		
		if ($this->hasSetItemQuality && $this->itemQuality > 0) $this->itemRecord['quality'] = $this->itemQuality;
		if ($this->hasSetItemLevel && $this->itemLevel > 0) $this->itemRecord['level'] = $this->itemLevel;
		
		$this->ParsePerfectSetCounts();
		
		return true;
	}
	
	
	private function LoadItemSummaryTransmuteTraitData()
	{
		$this->itemSummary['origTraitDesc'] = $this->itemSummary['traitDesc']; 
		$this->itemSummary['traitDesc'] = LoadEsoTraitSummaryDescription($this->itemTrait, $this->itemSummary['equipType'], $this->db, $this->version);
	}
	
	
	private function LoadItemSummaryData()
	{
		if ($this->itemId <= 0) return $this->ReportError("ERROR: Missing or invalid item ID specified!");
		$query = "SELECT * FROM minedItemSummary". $this->GetTableSuffix() ." WHERE itemId={$this->itemId};";
		
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		$row = $result->fetch_assoc();
		$row['name'] = preg_replace("#\|.*#", "", $row['name']);
		
		$row['traitAbilityDescArray'] = array();
		$row['traitCooldownArray'] = array();
		$row['setBonusCount1'] = "";
		$row['setBonusCount2'] = "";
		$row['setBonusCount3'] = "";
		$row['setBonusCount4'] = "";
		$row['setBonusCount5'] = "";
		$row['setBonusCount6'] = "";
		$row['setBonusCount7'] = "";
		$row['setBonusCount8'] = "";
		$row['setBonusCount9'] = "";
		$row['setBonusCount10'] = "";
		$row['setBonusCount11'] = "";
		$row['setBonusCount12'] = "";
		$row['internalSubtype'] = "";
		$row['internalLevel'] = "";
		$row['abilityCooldown'] = "";
		$row['maxCharges'] = "";
		$row['enchantId1'] = "";
		$row['enchantIntLevel1'] = "";
		$row['enchantIntType1'] = "";
		$row['link'] = "";
		
		if ($row['traitAbilityDesc'] != "")
		{
			$row['traitAbilityDescArray'][] = $row['traitAbilityDesc'];
			$row['traitCooldownArray'][] = $row['traitCooldown'];
		}
		
		$this->itemSummary = $row;
		if (!$this->itemSummary) $this->ReportError("ERROR: No item summary found matching ID {$this->itemId}!");
		
		$this->itemTrait = $this->itemSummary['trait'];
		
		if ($this->itemSummary['type'] == 2 || $this->itemSummary['type'] == 1)
		{
			if ($this->transmuteTrait > 0)
			{
				$this->itemTrait = $this->transmuteTrait;
				
				$this->LoadItemSummaryTransmuteTraitData();
			}
		}
		else
		{
			$this->transmuteTrait = 0;
		}
		
		if ($this->itemSummary['type'] == 7)  $this->LoadItemPotionData();
		if ($this->itemSummary['type'] == 30) $this->LoadItemPoisonData();
		if ($this->itemSummary['type'] == 60) $this->CreateMasterWritData();
		
		return true;
	}
	
	
	private function LoadItemRecord()
	{
		if ($this->itemId <= 0) return $this->ReportError("ERROR: Missing or invalid item ID specified (1-185000)!");
		$query = "";
		
		$minedTable = "minedItem" . $this->GetTableSuffix();
		$summaryTable = "minedItemSummary" . $this->GetTableSuffix();
		
		if ($this->itemLevel >= 1)
		{
			if ($this->itemLevel <= 0) return $this->ReportError("ERROR: Missing or invalid item Level specified (1-64)!");
			if ($this->itemQuality < 0) return $this->ReportError("ERROR: Missing or invalid item Quality specified (1-5)!");
			//$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='{$this->itemId}' AND level='{$this->itemLevel}' AND quality='{$this->itemQuality}' LIMIT 1;";
			$query = "SELECT $summaryTable.*, $minedTable.* FROM $minedTable LEFT JOIN $summaryTable ON $summaryTable.itemId=$minedTable.itemId WHERE $minedTable.itemId='{$this->itemId}' AND $minedTable.level='{$this->itemLevel}' AND $minedTable.quality='{$this->itemQuality}' LIMIT 1;";
			$this->itemErrorDesc = "id={$this->itemId}, Level={$this->itemLevel}, Quality={$this->itemQuality}";
		}
		else
		{
			if ($this->itemIntType < 0) return $this->ReportError("ERROR: Missing or invalid item internal type specified (1-400)!");
			//$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='{$this->itemId}' AND internalLevel='{$this->itemIntLevel}' AND internalSubtype='{$this->itemIntType}' LIMIT 1;";
			$query = "SELECT $summaryTable.*, $minedTable.* FROM $minedTable LEFT JOIN $summaryTable ON $summaryTable.itemId=$minedTable.itemId WHERE $minedTable.itemId='{$this->itemId}' AND $minedTable.internalLevel='{$this->itemIntLevel}' AND $minedTable.internalSubtype='{$this->itemIntType}' LIMIT 1;";
			$this->itemErrorDesc = "id={$this->itemId}, Internal Level={$this->itemIntLevel}, Internal Type={$this->itemIntType}";
		}
		
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		if ($result->num_rows === 0)
		{
			if ($this->itemLevel <= 0 && $this->itemIntType == 1)
			{
				$this->itemIntType = 2;
				//$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='{$this->itemId}' AND internalLevel='{$this->itemIntLevel}' AND internalSubtype='{$this->itemIntType}' LIMIT 1;";
				$query = "SELECT $summaryTable.*, $minedTable.* FROM $minedTable LEFT JOIN $summaryTable ON $summaryTable.itemId=$minedTable.itemId WHERE $minedTable.itemId='{$this->itemId}' AND $minedTable.internalLevel='{$this->itemIntLevel}' AND $minedTable.internalSubtype='{$this->itemIntType}' LIMIT 1;";
				$this->itemErrorDesc = "id={$this->itemId}, Internal Level={$this->itemIntLevel}, Internal Type={$this->itemIntType}";
				
				$result = $this->db->query($query);
				if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
				
				if ($result->num_rows === 0)
				{
					$this->itemLevel = 50;
					$this->itemIntLevel = 50;
					$this->itemIntType = 370;
					//$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='{$this->itemId}' AND internalLevel='{$this->itemIntLevel}' AND internalSubtype='{$this->itemIntType}' LIMIT 1;";
					$query = "SELECT $summaryTable.*, $minedTable.* FROM $minedTable LEFT JOIN $summaryTable ON $summaryTable.itemId=$minedTable.itemId WHERE $minedTable.itemId='{$this->itemId}' AND $minedTable.internalLevel='{$this->itemIntLevel}' AND $minedTable.internalSubtype='{$this->itemIntType}' LIMIT 1;";
					$this->itemErrorDesc = "id={$this->itemId}, Internal Level={$this->itemIntLevel}, Internal Type={$this->itemIntType}";
					
					$result = $this->db->query($query);
					if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
				}
			}
			else
			{
				$this->itemIntType = 1;
				$this->itemIntLevel = 1;
				
				//$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='{$this->itemId}' AND internalLevel='{$this->itemIntLevel}' AND internalSubtype='{$this->itemIntType}' LIMIT 1;";
				$query = "SELECT $summaryTable.*, $minedTable.* FROM $minedTable LEFT JOIN $summaryTable ON $summaryTable.itemId=$minedTable.itemId WHERE $minedTable.itemId='{$this->itemId}' AND $minedTable.internalLevel='{$this->itemIntLevel}' AND $minedTable.internalSubtype='{$this->itemIntType}' LIMIT 1;";
				$this->itemErrorDesc = "id={$this->itemId}, Internal Level={$this->itemIntLevel}, Internal Type={$this->itemIntType}";
				
				$result = $this->db->query($query);
				if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
			}
			
			if ($result->num_rows === 0) return $this->ReportError("ERROR: No item found matching {$this->itemErrorDesc}!");
		}
		
		$result->data_seek(0);
		$row = $result->fetch_assoc();
		if (!$row) $this->ReportError("ERROR: No item found matching {$this->itemErrorDesc}!");
		
		$this->itemLevel = (int) $row['level'];
		$this->itemQuality = (int) $row['quality'];
		
			// TODO: Temporary fix for setMaxEquipCount
		if (array_key_exists('setMaxEquipCount', $row) && $row['setMaxEquipCount'] == -1)
		{
			$highestSetDesc = "";
			$row['setMaxEquipCount'] = 0;
			
			if (array_key_exists('setBonusDesc1', $row) && $row['setBonusDesc1'] != "") $highestSetDesc = $row['setBonusDesc1'];
			if (array_key_exists('setBonusDesc2', $row) && $row['setBonusDesc2'] != "") $highestSetDesc = $row['setBonusDesc2'];
			if (array_key_exists('setBonusDesc3', $row) && $row['setBonusDesc3'] != "") $highestSetDesc = $row['setBonusDesc3'];
			if (array_key_exists('setBonusDesc4', $row) && $row['setBonusDesc4'] != "") $highestSetDesc = $row['setBonusDesc4'];
			if (array_key_exists('setBonusDesc5', $row) && $row['setBonusDesc5'] != "") $highestSetDesc = $row['setBonusDesc5'];
			if (array_key_exists('setBonusDesc6', $row) && $row['setBonusDesc6'] != "") $highestSetDesc = $row['setBonusDesc6'];
			if (array_key_exists('setBonusDesc7', $row) && $row['setBonusDesc7'] != "") $highestSetDesc = $row['setBonusDesc7'];
			if (array_key_exists('setBonusDesc8', $row) && $row['setBonusDesc8'] != "") $highestSetDesc = $row['setBonusDesc8'];
			if (array_key_exists('setBonusDesc9', $row) && $row['setBonusDesc9'] != "") $highestSetDesc = $row['setBonusDesc9'];
			if (array_key_exists('setBonusDesc10', $row) && $row['setBonusDesc10'] != "") $highestSetDesc = $row['setBonusDesc10'];
			if (array_key_exists('setBonusDesc11', $row) && $row['setBonusDesc11'] != "") $highestSetDesc = $row['setBonusDesc11'];
			if (array_key_exists('setBonusDesc12', $row) && $row['setBonusDesc12'] != "") $highestSetDesc = $row['setBonusDesc12'];
				
			if ($highestSetDesc != "")
			{
				$row['setMaxEquipCount'] = 1;
				$matches = array();
				$matchResult = preg_match("/\(([0-9]+) items\)/", $highestSetDesc, $matches);
				if ($matchResult) $row['setMaxEquipCount'] = (int) $matches[1];
			}
		}
		
		$row['enchantId1'] = $this->enchantId1;
		$row['enchantIntLevel1'] = $this->enchantIntLevel1;
		$row['enchantIntType1'] = $this->enchantIntType1;
		
		$row['traitAbilityDescArray'] = array();
		$row['traitCooldownArray'] = array();
		
		if ($row['traitAbilityDesc'] != "")
		{
			$row['traitAbilityDescArray'][] = $row['traitAbilityDesc'];
			$row['traitCooldownArray'][] = $row['traitCooldown'];
		}
		
		$row['name'] = preg_replace("#\|.*#", "", $row['name']);
		
		if ($row['weaponType'] == 14) $row['armorRating'] += $this->extraArmor;
		
		$this->itemRecord = $row;
		
		if ($this->itemRecord['type'] == 7)  $this->LoadItemPotionData();
		if ($this->itemRecord['type'] == 30) $this->LoadItemPoisonData();
		
		if ($this->itemRecord['type'] == 60)
		{
			$this->transmuteTrait = 0;
			$this->CreateMasterWritData();
		}
		
		$this->itemTrait = $this->itemRecord['trait'];
		
		if ($this->itemRecord['type'] == 2 || $this->itemRecord['type'] == 1) 
		{
			if ($this->transmuteTrait > 0)
			{
				$this->itemTrait = $this->transmuteTrait;
				
				$this->itemRecord['origTrait'] = $this->itemRecord['trait'];
				$this->itemRecord['origTraitDesc'] = $this->itemRecord['traitDesc'];
				$this->itemRecord['origArmorRating'] = $this->itemRecord['armorRating'];
				$this->itemRecord['origWeaponPower'] = $this->itemRecord['weaponPower'];
				$this->itemRecord['origEnchantDesc'] = $this->itemRecord['enchantDesc'];
				
				if ($this->itemRecord['trait'] == 13)		/* Original Reinforced */
				{
					$factor = 1;
					$result = preg_match("#by (?:\|c[0-9a-fA-F]{6})?([0-9.]+)(?:\|r)?%#", $this->itemRecord['origTraitDesc'], $matches);
					
					if ($result)
					{
						$factor = 1 + floatval($matches[1])/100;
						$this->itemRecord['armorRating'] = round($this->itemRecord['armorRating'] / $factor);
					}
				}
				else if ($this->itemRecord['trait'] == 25)	/* Original armor nirnhoned */
				{
					$amount = 0;
					$result = preg_match("#by ([0-9.]+)#", $this->itemRecord['origTraitDesc'], $matches);
					
					if ($result)
					{
						$amount = intval($matches[1]);
						$this->itemRecord['armorRating'] -= $amount;
					}
				}
				else if ($this->itemRecord['trait'] == 26)	/* Original weapon nirnhoned */
				{
					$factor = 0;
					$result = preg_match("#by (?:\|c[0-9a-fA-F]{6})?([0-9.]+)(?:\|r)?%#", $this->itemRecord['origTraitDesc'], $matches);
					
					if ($result)
					{
						$factor = 1 + floatval($matches[1])/100;
						$this->itemRecord['weaponPower'] = round($this->itemRecord['weaponPower'] / $factor);
					}
				}
				
				$this->LoadItemTransmuteTraitData();
				
				if ($this->transmuteTrait == 13)		/* Transmuted Reinforced */
				{
					$factor = 1;
					$result = preg_match("#by ([0-9.]+)\%#", $this->itemRecord['traitDesc'], $matches);
					
					if ($result)
					{
						$factor = 1 + floatval($matches[1])/100;
						$this->itemRecord['armorRating'] = floor($this->itemRecord['armorRating'] * $factor);
					}
				}
				else if ($this->transmuteTrait == 25)	/* Transmuted armor nirnhoned */
				{
					$amount = 0;
					$result = preg_match("#by ([0-9.]+)#", $this->itemRecord['traitDesc'], $matches);
					
					if ($result)
					{
						$amount = intval($matches[1]);
						$this->itemRecord['armorRating'] += $amount;
					}
				}
				else if ($this->transmuteTrait == 26)	/* Transmuted weapon nirnhoned */
				{
					$factor = 0;
					$result = preg_match("#by (?:\|c[0-9a-fA-F]{6})?([0-9.]+)(?:\|r)?%#", $this->itemRecord['traitDesc'], $matches);
					
					if ($result)
					{
						$factor = 1 + floatval($matches[1])/100;
						$this->itemRecord['weaponPower'] = round($this->itemRecord['weaponPower'] * $factor);
					}
				}
			}
			
			if ($this->weaponTraitFactor > 0)
			{
				
				if ($this->itemRecord['trait'] == 26 && $this->transmuteTrait <= 0)
				{
					$factor = 0;
					$result = preg_match("#by (?:\|c[0-9a-fA-F]{6})?([0-9.]+)(?:\|r)?%#", $this->itemRecord['traitDesc'], $matches);
					
					if ($result)
					{
						$factor = floatval($matches[1])/100;
						$newFactor = (1 + $factor * (1 + $this->weaponTraitFactor));
						$oldWeaponPower = round($this->itemRecord['weaponPower'] / (1 + $factor));
						$this->itemRecord['weaponPower'] = round($this->itemRecord['weaponPower'] * $newFactor);
					}
				}
				else if ($this->transmuteTrait == 26)
				{
					$factor = 0;
					$result = preg_match("#by (?:\|c[0-9a-fA-F]{6})?([0-9.]+)(?:\|r)?%#", $this->itemRecord['traitDesc'], $matches);
					
					if ($result)
					{
						$factor = floatval($matches[1])/100;
						$newFactor = (1 + $factor * (1 + $this->weaponTraitFactor));
						$oldWeaponPower = round($this->itemRecord['weaponPower'] / (1 + $factor));
						$this->itemRecord['weaponPower'] = round($oldWeaponPower * $newFactor);
					}
				}
			}
		}
		else
		{
			$this->transmuteTrait = 0;
		}
		
		$this->ParsePerfectSetCounts();
		$this->LoadEnchantMaxCharges();
		
		return true;
	}
	
	
	private function LoadItemTransmuteTraitData()
	{
		$this->itemRecord['origTraitDesc'] = $this->itemRecord['traitDesc'];
		
		$intLevel = $this->itemRecord['internalLevel'];
		$intSubtype = $this->itemRecord['internalSubtype'];
		
			/* Special case for mythic items */
		if ($this->itemRecord['quality'] == 6) {
			$intLevel = 50;
			$intSubtype = 370;
		}
		
		$this->itemRecord['traitDesc'] = LoadEsoTraitDescription($this->itemTrait, $intLevel, $intSubtype, $this->itemRecord['equipType'], $this->db, $this->version);
	}	
	
	
	public function CreateMasterWritData()
	{
		$text = CreateEsoMasterWritText($this->db, $this->itemRecord['name'], $this->writData1, $this->writData2, $this->writData3,
										$this->writData4, $this->writData5, $this->writData6, $this->itemPotionData);
		
		$this->itemRecord['abilityName'] = '';
		$this->itemRecord['abilityDesc'] = $text;
	}
	
	
	private function LoadResultItemRecord()
	{
		$itemLink = $this->itemRecord['resultItemLink'];
		if ($itemLink == null || $itemLink == "") return true;
		
		$result = preg_match('/\|H(?P<color>[A-Za-z0-9]*)\:item\:(?P<itemId>[0-9]*)\:(?P<subtype>[0-9]*)\:(?P<level>[0-9]*)\:(.*)\|h/', $itemLink, $matches);
		if (!$result) return true;
		
		$resultItemId = $matches['itemId'];
		$resultItemLevel = $matches['level']; 
		$resultItemSubType = $matches['subtype'];
		
		if ($resultItemId == null || $resultItemId == "") return true;
		if ($resultItemLevel == null || $resultItemLevel == "") return true;
		if ($resultItemSubType == null || $resultItemSubType == "") return true;
		
		/*
		 * $query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='$resultItemId' AND internalLevel='$resultItemLevel' AND internalSubType='$resultItemSubType' LIMIT 1;";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		if ($result->num_rows === 0)
		{
			$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='$resultItemId' AND internalLevel=1 AND internalSubType=1 LIMIT 1;";
			$result = $this->db->query($query);
			if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		}
		
		$result->data_seek(0);
		$row = $result->fetch_assoc();
		if ($result->num_rows === 0) $this->ReportError("ERROR: No result item found matching '$itemLink'!"); */
		
		$row = LoadEsoMinedItem($this-db, $resultItemId, $resultItemLevel, $resultItemSubType,  $this->GetTableSuffix());
		if (!$row) $this->ReportError("ERROR: No result item found matching '$itemLink'!");
		
		if ($row['weaponType'] == 14) $row['armorRating'] += $this->extraArmor;
		
		$this->resultItemRecord = $row;
		
		return true;
	}
	
	
	private function LoadSetItemRecord()
	{
		if ($this->itemSet == "") return $this->ReportError("ERROR: Missing or invalid set name specified!");
		
		$safeSet = $this->db->real_escape_string($this->itemSet);
		$query = "SELECT * FROM setSummary". $this->GetTableSuffix() ." WHERE setName='$safeSet' LIMIT 1;";
		$this->itemErrorDesc = "set='{$this->itemSet}'";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		$this->setItemData = $result->fetch_assoc();
		
		if (!$this->setItemData)
		{
			$query = "SELECT * FROM $setTable WHERE setName LIKE '%$safeSet%' LIMIT 1;";
			$result = $this->db->query($query);
			if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
			
			$this->setItemData = $result->fetch_assoc();
		}
		
		if (!$this->setItemData)
		{
			$safeSet = strtolower($this->itemSet);
			$safeSet = str_replace("'", "", $safeSet);
			$safeSet = str_replace(",", "", $safeSet);
			$safeSet = str_replace(" ", "-", $safeSet);
			$safeSet = $this->db->real_escape_string($safeSet);
			
			$query = "SELECT * FROM setSummary". $this->GetTableSuffix() ." WHERE indexName='$safeSet' LIMIT 1;";
			
			$result = $this->db->query($query);
			if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
			
			$this->setItemData = $result->fetch_assoc();
			
			if (!$this->setItemData)
			{
				$this->ReportError("ERROR: No set found matching '{$this->itemSet}'!");
				$this->setItemData = array();
				return false;
			}
		}
		
		$this->setItemData['name'] = $this->setItemData['setName'];
		$this->setItemData['quality'] = 5;
		
		$safeSet = $this->db->real_escape_string($this->setItemData['setName']);
		
		$query = "SELECT icon FROM minedItemSummary". $this->GetTableSuffix() ." WHERE setName='$safeSet' AND type=2 AND equipType=1 LIMIT 1;";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		if ($result->num_rows == 0)
		{
			$query = "SELECT icon FROM minedItemSummary". $this->GetTableSuffix() ." WHERE setName='$safeSet' AND type=1 AND (equipType=5 OR equipType=6) LIMIT 1;";
			$result = $this->db->query($query);
			if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
			
			if ($result->num_rows == 0)
			{
				$query = "SELECT icon FROM $summaryTable WHERE setName='$safeSet' AND (type=1 or type=2) LIMIT 1;";
				$result = $this->db->query($query);
				if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
			}
		}
		
		$iconRow = $result->fetch_assoc();
		
		if ($iconRow)
		{
			$this->setItemData['icon'] = $iconRow['icon'];
		}
		
		if (!$this->showSetSummary)
		{
			$this->setItemData['setBonusDesc'] = $this->RemoveSummaryNumbers($this->setItemData['setBonusDesc']);
			
			for ($i = 1; $i <= self::MAXSETINDEX; ++$i)
			{
				$this->setItemData["setBonusDesc$i"] = $this->RemoveSummaryNumbers($this->setItemData["setBonusDesc$i"]);
			}
		}
		
		return true;
	}
	
	
	private function RemoveSummaryNumbers($desc)
	{
		$newDesc = preg_replace('#([0-9]+)\-([0-9]+)#', '\2', $desc);
		return $newDesc;
	}
	
	
	private function LoadQuestItemRecord()
	{
		if ($this->questItemId <= 0) return $this->ReportError("ERROR: Missing or invalid quest item ID specified (1-85000)!");
		$query = "";
		
		$query = "SELECT * FROM questItem". $this->GetTableSuffix() ." WHERE itemId='{$this->questItemId}' LIMIT 1;";
		$this->itemErrorDesc = "qid={$this->questItemId}";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		$result->data_seek(0);
		$this->questItemData = $result->fetch_assoc();
		
		if (!$this->questItemData)
		{
			$this->ReportError("ERROR: No quest item found matching {$this->itemErrorDesc}!");
			$this->questItemData = array();
			return false;
		}
		
		return true;
	}
	
	
	private function LoadCollectibleItemRecord()
	{
		if ($this->collectibleItemId <= 0) return $this->ReportError("ERROR: Missing or invalid collectible item ID specified (1-85000)!");
		$query = "";
		
		$query = "SELECT * FROM collectibles". $this->GetTableSuffix() ." WHERE id='{$this->collectibleItemId}' LIMIT 1;";
		$this->itemErrorDesc = "cid={$this->collectibleItemId}";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		$result->data_seek(0);
		$this->collectibleItemData = $result->fetch_assoc();
		
		if (!$this->collectibleItemData) 
		{
			$this->ReportError("ERROR: No collectible item found matching {$this->itemErrorDesc}!");
			$this->collectibleItemData = array();
			return false;
		}
		
		return true;
	}
	
	
	private function LoadAntiquityItemRecord()
	{
		if ($this->antiquityItemId <= 0) return $this->ReportError("ERROR: Missing or invalid collectible item ID specified (1-85000)!");
		$query = "";
		
		$query = "SELECT * FROM antiquityLeads". $this->GetTableSuffix() ." WHERE id='{$this->antiquityItemId}' LIMIT 1;";
		$this->itemErrorDesc = "aid={$this->antiquityItemId}";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		
		$result->data_seek(0);
		$this->antiquityItemData = $result->fetch_assoc();
		
		if (!$this->antiquityItemData) 
		{
			$this->ReportError("ERROR: No antiquity item found matching {$this->itemErrorDesc}!");
			$this->antiquityItemData = array();
			return false;
		}
		
		return true;
	}
	
	
	private function LoadItemPotionData()
	{
		if ($this->itemPotionData <= 0) return true;
		
		$potionData = intval($this->itemPotionData);
		$potionEffect1 = $potionData & 255;
		$potionEffect2 = ($potionData >> 8) & 255;
		$potionEffect3 = ($potionData >> 16) & 127;
		
		$this->LoadItemPotionDataEffect($potionEffect1, true);
		$this->LoadItemPotionDataEffect($potionEffect2, true);
		$this->LoadItemPotionDataEffect($potionEffect3, true);
		
		ksort($this->itemRecord['traitAbilityDescArray']);
		ksort($this->itemRecord['traitCooldownArray']);
		return true;
	}
	
	
	private function LoadItemPoisonData()
	{
		if ($this->itemPotionData <= 0) return true;
	
		$potionData = intval($this->itemPotionData);
		$potionEffect1 = $potionData & 255;
		$potionEffect2 = ($potionData >> 8) & 255;
		$potionEffect3 = ($potionData >> 16) & 127;
		
		$this->LoadItemPotionDataEffect($potionEffect1, false);
		$this->LoadItemPotionDataEffect($potionEffect2, false);
		$this->LoadItemPotionDataEffect($potionEffect3, false);
		
		$effectCount = count($this->itemRecord['traitAbilityDescArray']);
		$this->ModifyPoisonEffectDurations($effectCount);
		
		ksort($this->itemRecord['traitAbilityDescArray']);
		ksort($this->itemRecord['traitCooldownArray']);
		
		$this->itemIsPoison = true;
		return true;
	}
	
	
	private function ModifyPoisonEffectDurations($effectCount)
	{
		global $ESO_POTIONEFFECT_DATA;
		
		if ($effectCount == 1) return;
		
		$newArray = [];
		
		foreach ($this->itemRecord['traitAbilityDescArray'] as $effectIndex => $desc)
		{
			$newDesc = $desc;
			
			$effectData = $ESO_POTIONEFFECT_DATA[$effectIndex];
			
			if ($effectData == null)
			{
				$newArray[$effectIndex] = $newDesc;
				continue;
			}
			
			$factor = 1;
			if ($effectCount == 2) $factor = $effectData['factor2'];
			if ($effectCount == 3) $factor = $effectData['factor3'];
			
			if ($factor == null || $factor == 1)
			{
				$newArray[$effectIndex] = $newDesc;
				continue;
			}
			
			$newDesc = preg_replace_callback('/(for \|c[a-fA-F0-9]{6})([0-9.]+)(\|r second)/', function($matches) use($factor) {
				$duration = floatval($matches[2]);
				if ($duration == 0) return $matches[1] + $matches[2] + $matches[3];
				
				$newDuration = round($duration * $factor, 1);
				return $matches[1] . $newDuration . $matches[3];
			}, $newDesc);
			
			$newArray[$effectIndex] = $newDesc;
		}
		
		$this->itemRecord['traitAbilityDescArray'] = $newArray;
	}
	
	
	private function LoadItemPotionDataEffect($effectIndex, $loadPotion = true)
	{
		$effectIndex = intval($effectIndex);
		if ($effectIndex <= 0 || $effectIndex > 127) return true;
		
		if ($loadPotion)
			$itemId = self::ESOIL_POTION_MAGICITEMID;
		else
			$itemId = self::ESOIL_POISON_MAGICITEMID;
		
		if ($this->inputIntLevel >= 0 && $this->inputIntType >= 0)
		{
			$intlevel = $this->inputIntLevel;
			$subtype = $this->inputIntType;
			$query = "SELECT traitAbilityDesc FROM minedItem{$this->GetTableSuffix()} WHERE itemId=$itemId AND internalLevel=$intlevel AND internalSubtype=$subtype AND potionData=$effectIndex LIMIT 1;";
		}
		else if ($this->itemIntLevel >= 0 && $this->itemIntType >= 0)
		{
			$intlevel = $this->itemIntLevel;
			$subtype = $this->itemIntType;
			$query = "SELECT traitAbilityDesc FROM minedItem{$this->GetTableSuffix()} WHERE itemId=$itemId AND internalLevel=$intlevel AND internalSubtype=$subtype AND potionData=$effectIndex LIMIT 1;";
		}
		else if ($this->itemLevel >= 1)
		{
			$level = $this->itemLevel;
			$quality = $this->itemQuality;
			$query = "SELECT traitAbilityDesc FROM minedItem{$this->GetTableSuffix()} WHERE itemId=$itemId AND level=$level AND quality=$quality AND potionData=$effectIndex LIMIT 1;";
		}
		else
		{
			$intlevel = 1;
			$subtype = 1;
			$query = "SELECT traitAbilityDesc FROM minedItem{$this->GetTableSuffix()} WHERE itemId=$itemId AND internalLevel=$intlevel AND internalSubtype=$subtype AND potionData=$effectIndex LIMIT 1;";
		}
		
		$this->lastQuery = $query;
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		if ($result->num_rows == 0) return true;
		
		$result->data_seek(0);
		$row = $result->fetch_assoc();
		
		if ($row['traitAbilityDesc'] == "") return true;
		
		$this->itemRecord['traitAbilityDescArray'][$effectIndex] = $row['traitAbilityDesc'];
		$this->itemRecord['traitCooldownArray'][$effectIndex] = $row['traitCooldown'];
		return true;
	}
	
	
	private function LoadEnchantMaxCharges()
	{
		if ($this->itemRecord['maxCharges'] > 0) return true;
		
		if ($this->itemLevel >= 1)
		{
			$level = $this->itemLevel;
			$quality = $this->itemQuality;
			$query = "SELECT maxCharges FROM minedItem{$this->GetTableSuffix()} WHERE itemId='".self::ESOIL_ENCHANT_ITEMID."' AND level='$level' AND quality='$quality' LIMIT 1;";
		}
		else
		{
			$intlevel = $this->itemIntLevel;
			$subtype = $this->itemIntType;
			$query = "SELECT maxCharges FROM minedItem{$this->GetTableSuffix()} WHERE itemId='".self::ESOIL_ENCHANT_ITEMID."' AND internalLevel='$intlevel' AND internalSubtype='$type' LIMIT 1;";
		}
		
		$this->lastQuery = $query;
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		if ($result->num_rows == 0) return true;
		
		$result->data_seek(0);
		$row = $result->fetch_assoc();
		if ($row['maxCharges'] == "") return true;
		
		$maxCharges = $row['maxCharges'];
		if ($this->itemTrait == 2) $maxCharges *= $this->itemRecord['quality']*0.25 + 2;
		
		$this->itemRecord['maxCharges'] = $maxCharges; 
		return true;
	}
	
	
	private function LoadEnchantRecords()
	{
		if ($this->enchantId1 > 0 && $this->enchantIntLevel1 > 0 && $this->enchantIntType1 > 0)
		{
			$item = LoadEsoMinedItemExact($this->db, $this->enchantId1, $this->enchantIntLevel1, $this->enchantIntType1, $this->GetTableSuffix());
			if ($item) $this->enchantRecord1 = $item;
			/*
			$query = "SELECT * FROM minedItem". $this->GetTableSuffix() ." WHERE itemId='{$this->enchantId1}' AND internalLevel='{$this->enchantIntLevel1}' AND internalSubtype='{$this->enchantIntType1}' LIMIT 1;";
			$result = $this->db->query($query);
			if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
			
			$result->data_seek(0);
			$row = $result->fetch_assoc();
			if ($row) $this->enchantRecord1 = $row; */
		}
		
		if ($this->enchantRecord1 != null)
		{
			$this->itemRecord['enchantName1'] = $this->enchantRecord1['enchantName'];
			$this->itemRecord['enchantDesc1'] = $this->enchantRecord1['enchantDesc'];
		}
		
		return true;
	}
	
	
	private function MakeSimilarItemBlock()
	{
		$output = "";
		
		foreach ($this->itemSimilarRecords as $key => $item)
		{
			if ($item['id'] == $this->itemRecord['id']) continue;
			
			$intType = intval($item['internalSubtype']);
			$intLevel = intval($item['internalLevel']);
			$itemId = intval($this->itemId);

			if ($this->enchantId1 > 0)
			{
				$enchantId = intval($this->enchantId1);
				$enchantLevel = intval($this->enchantIntLevel1);
				$enchantType = intval($this->enchantIntType1);
				$output .= "<li><a href='itemLink.php?itemid=$itemId&intlevel=$intLevel&inttype=$intType&enchantid=$enchantId&enchantintlevel=$enchantLevel&enchantinttype=$enchantType'>Internal Type $intLevel:$intType</a></li>";
			}
			else
				$output .= "<li><a href='itemLink.php?itemid=$itemId&intlevel=$intLevel&inttype=$intType'>Internal Type $intLevel:$intType</a></li>";
		}
		
		if ($output == "") $output = "<li>No similar items found.</li>";
		
		return $output;
	}
	
	
	private function LoadSimilarItemRecords()
	{
		$query = "SELECT id, internalLevel, internalSubtype FROM minedItem". $this->GetTableSuffix() ." WHERE itemId={$this->itemId} AND level={$this->itemLevel} AND quality={$this->itemQuality};";
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("ERROR: Database query error! " . $this->db->error);
		if ($result->num_rows === 0) return true;
		$result->data_seek(0);
		
		while (($row = $result->fetch_assoc()))
		{
			$this->itemSimilarRecords[] = $row;
		}
		
		return false;
	}
	
	
	private function TestAddSetData ($row)
	{ 
		$row['setMaxEquipCount'] = 5;
		$row['setBonusCount'] = 4;
		$row['setBonusCount1'] = 2;
		$row['setBonusCount2'] = 3;
		$row['setBonusCount3'] = 4;
		$row['setBonusCount4'] = 5;
		$row['setBonusDesc1'] = "Adds 139 Armor";
		$row['setBonusDesc2'] = "Adds 104 Max Health";
		$row['setBonusDesc3'] = "Adds 104 Max Health";
		$row['setBonusDesc4'] = "Death's Wind If struck by a melee attack while below 35% health, nearby enemies are knocked back and stunned for 4.0 seconds. This effect can only happen once every 30.0 seconds";
		return $row;
	}
	
	
	private function OutputHtmlHeader()
	{
		ob_start("ob_gzhandler");
		header("Expires: 0");
		header("Pragma: no-cache");
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		
		header("Access-Control-Allow-Origin: *");
		
		if ($this->outputType == "html")
			header("content-type: text/html");
		elseif ($this->outputType == "csv")
			header("content-type: text/plain");
		else
			header("content-type: text/plain");
	}
	
	
	public function FormatResultItemLink ($link)
	{
		$safeLink = $this->escape($link);
		
		$matches = ParseEsoItemLink($link);
		if (!$matches) return $link;
		
		$itemId = intval($matches['itemId']);
		if ($itemId <= 0) return $link;
		
		$version = "";
		if ($this->version != "") $version = "&version=" . $this->version;
		$dataLink = "<a href=\"itemLink.php?itemid=$itemId&summary=1$version\">";
		
		$query = "SELECT name FROM minedItemSummary WHERE itemId=$itemId";
		$result = $this->db->query($query);
		if ($result === false) return $dataLink . $safeLink . "</a>";
		
		$resultItem = $result->fetch_assoc();
		if ($resultItem == null) return $dataLink . $safeLink . "</a>";
		
		$resultName = $resultItem['name'];
		if ($resultName == null || $resultName == '') $resultName = $safeLink;
		
		return $dataLink . $resultName . "</a>";
	}
	
	
	private function GetItemRawDataListKeys($itemRecord)
	{
		$fixedKeys = [ "itemId", "link", "names" ];
		$addedKeys = [];
		$keys = [];
		
		foreach ($fixedKeys as $key)
		{
			$keys[] = $key;
			$addedKeys[$key] = 1;
		}
		
		foreach ($itemRecord as $key => $value)
		{
			if ($addedKeys[$key] == null)
			{
				$keys[] = $key;
				$addedKeys[$key] = 1;
			}
		}
		
		return $keys;
	}
	
	
	private function MakeItemRawDataList()
	{	
		$output = "";
		
		if ($this->itemRecord['link'] == null) $this->itemRecord['link'] = $this->MakeItemLink();
		
		$this->itemRecord['origItemLink'] = $this->itemRecord['link'];
		$this->itemRecord['isBound'] = $this->itemBound < 0 ? 0 : $this->itemBound;
		$this->itemRecord['isCrafted'] = $this->itemCrafted < 0 ? 0 : $this->itemCrafted;
		$this->itemRecord['charges'] = $this->itemCharges < 0 ? 0 : $this->itemCharges;
		$this->itemRecord['version'] = $this->version;
		
		if ($this->itemStyle > 0 && $this->itemStyle != $this->itemRecord['style'])
		{
			$this->itemRecord['origStyle'] = $this->itemRecord['style'];
			$this->itemRecord['style'] = $this->itemStyle;
		}
		
		$keys = $this->GetItemRawDataListKeys($this->itemRecord);
		
		foreach ($keys as $key)
		{
			$value = $this->itemRecord[$key];
			if (!$this->showAll && ($key == 'id' || $key == 'logId' || $value == "" || $value == '-1')) continue;
			$id = "esoil_rawdata_" . $key;
			
			if ($key == "link") $value = $this->MakeItemLink(true);
			
			$safeValue = $this->escape($value);
			
			if ($key == "icon")
			{
				$output .= "\t<tr><td>$key</td><td id='$id'><img id='esoil_rawdata_iconimage' src='{$this->MakeItemIconImageLink()}' /> $safeValue</td></tr>\n";
			}
			elseif ($key == 'resultItemLink')
			{
				$newValue = $this->FormatResultItemLink($value);
				$output .= "\t<tr><td>resultItem</td><td id='esoil_rawdata_resultItem'>$newValue</td></tr>\n";
				
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
			}
			elseif ($key == "furnCategory")
			{
				$value = str_replace(":", " : ", $safeValue);
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
			}
			else
			{
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
			}
			
		}
		
		return $output;
	}
	
	
	private function MakeQuestItemRawDataList()
	{
		$output = "";
		$this->questItemData['version'] = $this->version;
		
		foreach ($this->questItemData as $key => $value)
		{
			if (!$this->showAll && ($key == 'id' || $key == 'logId' || $value == "" || $value == '-1')) continue;
			$id = "esoil_rawdata_" . $key;
			
			$safeValue = $this->escape($value);
			
			if ($key == "icon")
				$output .= "\t<tr><td>$key</td><td id='$id'><img id='esoil_rawdata_iconimage' src='{$this->MakeQuestItemIconImageLink()}' /> $safeValue</td></tr>\n";
			else
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
		}
		
		return $output;
	}
	
	
	private function MakeSetItemRawDataList()
	{
		$output = "";
		$this->setItemData['version'] = $this->version;
		
		foreach ($this->setItemData as $key => $value)
		{
			if (!$this->showAll && ($key == 'id' || $key == 'logId' || $value == "" || $value == '-1')) continue;
			$id = "esoil_rawdata_" . $key;
			
			$safeValue = $this->escape($value);
			
			if ($key == "icon")
				$output .= "\t<tr><td>$key</td><td id='$id'><img id='esoil_rawdata_iconimage' src='{$this->MakeSetItemIconImageLink()}' /> $safeValue</td></tr>\n";
			else
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
		}
		
		return $output;
	}
	
	
	private function MakeCollectibleItemRawDataList()
	{
		$output = "";
		$this->collectibleItemData['version'] = $this->version;
		
		foreach ($this->collectibleItemData as $key => $value)
		{
			if (!$this->showAll && ($key == 'id' || $key == 'logId' || $value == "" || $value == '-1')) continue;
			$id = "esoil_rawdata_" . $key;
			
			$safeValue = $this->escape($value);
			
			if ($key == "icon")
				$output .= "\t<tr><td>$key</td><td id='$id'><img id='esoil_rawdata_iconimage' src='{$this->MakeCollectibleItemIconImageLink()}' /> $safeValue</td></tr>\n";
			else
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
	
		}
	
		return $output;
	}
	
	
	private function MakeAntiquityItemRawDataList()
	{
		$output = "";
		$this->antiquityItemData['version'] = $this->version;
	
		foreach ($this->antiquityItemData as $key => $value)
		{
			if (!$this->showAll && ($key == 'id' || $key == 'logId' || $value == "" || $value == '-1')) continue;
			$id = "esoil_rawdata_" . $key;
			
			$safeValue = $this->escape($value);
			
			if ($key == "icon")
				$output .= "\t<tr><td>$key</td><td id='$id'><img id='esoil_rawdata_iconimage' src='{$this->MakeAntiquityItemIconImageLink()}' /> $safeValue</td></tr>\n";
			else
				$output .= "\t<tr><td>$key</td><td id='$id'>$safeValue</td></tr>\n";
	
		}
	
		return $output;
	}
	
	
	private function MakeIconImageLink($icon)
	{
		if ($icon == null || $icon == "") $icon = self::ESOIL_ICON_UNKNOWN;
		
		$icon = preg_replace('/dds$/', 'png', $icon);
		$safeIcon = $this->escape($icon);
		
		$iconLink = self::ESOIL_ICON_URL . $safeIcon;
		return $iconLink;
	}
	
	
	private function MakeItemIconImageLink()
	{
		$icon = $this->itemRecord['icon'];
		return $this->MakeIconImageLink($icon);
	}
	
	
	private function MakeQuestItemIconImageLink()
	{
		$icon = $this->questItemData['icon'];
		return $this->MakeIconImageLink($icon);
	}
	
	
	private function MakeSetItemIconImageLink()
	{
		$icon = $this->setItemData['icon'];
		return $this->MakeIconImageLink($icon);
	}
	
	
	private function MakeCollectibleItemIconImageLink()
	{
		$icon = $this->collectibleItemData['icon'];
		return $this->MakeIconImageLink($icon);
	}
	
	
	private function MakeAntiquityItemIconImageLink()
	{
		$icon = $this->antiquityItemData['icon'];
		return $this->MakeIconImageLink($icon);
	}
	
	
	private function MakeItemLevelSimpleString()
	{
		$level = intval($this->itemRecord['level']);
		if ($level <= 0) return "Level ?";
		
		if ($level > 50)
		{
			$level -= 50;
			$cp = $level * 10;
			
			if ($this->useUpdate10Display)
				return "CP$cp";
			else
				return "Rank V$level";
		}
		
		return "Level $level";
	}
	
	
	private function ShouldShowLevel()
	{
		$itemType = intval($this->itemRecord['type']);
		
		if ($itemType == 1) return true;
		if ($itemType == 2) return true;
		if ($itemType == 4) return true;
		if ($itemType == 7) return true;
		if ($itemType == 12) return true;
		if ($itemType == 20) return true;
		if ($itemType == 21) return true;
		if ($itemType == 26) return true;
		if ($itemType == 32) return true;
		
		return false;
	}
	
	
	private function MakeItemLevelString()
	{
		//if (!$this->ShouldShowLevel()) return "";
		
		if ($this->showSummary)
		{
			$level = $this->itemRecord['level'];
			return "LEVEL <div id='esoil_itemlevel'>$level</div>";
		}
		
		$level = intval($this->itemRecord['level']);
		
		if ($level <= 0) return "";
		
		if ($level > 50) 
		{
			$level -= 50;
			
			if ($this->useUpdate10Display)
				return "LEVEL <div id='esoil_itemlevel'>50</div>";
			else
				return "<img src='//esoitem.uesp.net/resources/eso_item_veteranicon.png' /> RANK <div id='esoil_itemlevel'>$level</div>";
		}
		
		return "LEVEL <div id='esoil_itemlevel'>$level</div>";
	}
	
	
	private function MakeItemLeftBlock()
	{
		$type = $this->itemRecord['type'];
		$equipType = $this->itemRecord['equipType'];
		
		if ($type == 2) //armor 
		{
			if ($equipType == 2 || $equipType == 12) // ring/neck
				return "";
			else
				return "ARMOR <div id='esoil_itemleft'>{$this->itemRecord['armorRating']}</div>";
		}
		elseif ($type == 1) //weapon / shield 
		{
			if ($equipType == 7) // shield
				return "ARMOR <div id='esoil_itemleft'>{$this->itemRecord['armorRating']}</div>";
			else //weapon
				return "DAMAGE <div id='esoil_itemleft'>{$this->itemRecord['weaponPower']}</div>";
		}
		
		return "";
	}
	
	
	private function MakeItemRightBlock()
	{
		if (!$this->useUpdate10Display) return $this->MakeItemOldValueBlock();

		$level = $this->itemRecord['level'];
		
		if (!is_numeric($level)) 
		{
			$prefix = "";
			if ($this->showSummary) $prefix = "LEVEL ";
			if ($level == "CP160") return "$prefix <img src='//esoitem.uesp.net/resources/champion_icon.png' class='esoil_cpimg'>CP<div id='esoil_itemlevel'>160</div>";
			if ($level == "1-CP160") return "$prefix <div id='esoil_itemlevel'>1 - </div> <img src='//esoitem.uesp.net/resources/champion_icon.png' class='esoil_cpimg'>CP<div id='esoil_itemlevel'>160</div>";
			return "$prefix <div id='esoil_itemlevel'>$level</div>";
		}
		
		if ($level <= 50) return "";
		$cp = ($level - 50) * 10;
		
		$output = "";
		
		//if ($this->showSummary) $output .= "LEVEL ";
		$output .= "<img src='//esoitem.uesp.net/resources/champion_icon.png' class='esoil_cpimg'>CP<div id='esoil_itemlevel'>$cp</div>";
		
		return $output;
	}
	
	
	private function MakeItemOldValueBlock()
	{
		if ($value <= 0) return "";
		
		$value = $this->itemRecord['value'];
		$output = "VALUE <div id='esoil_itemoldvalue'>$value</div>";
		return $output;
	}
	
	
	private function MakeItemNewValueBlock()
	{
		if ($value <= 0) return "";
		
		$value = $this->itemRecord['value'];
		$output = "<div id='esoil_itemnewvalue'>$value</div> <img src='//esoitem.uesp.net/resources/currency_gold_32.png' class='esoil_goldimg'>";
		return $output;
	}
	
	
	private function MakeItemStolenText()
	{
		if ($this->itemStolen <= 0) return "";
		
		$output = "<img src='//esoitem.uesp.net/resources/stolenitem.png' class='esoil_stolenicon' />";
		
		return $output;
	}
	
	
	private function MakeItemBindTypeText()
	{
		if ($this->itemBound > 0) return "Bound";
		$bindType = $this->itemRecord['bindType'];
		
		if ($bindType <= 0) return "";
		return GetEsoItemBindTypeText($bindType);
	}
	
	
	private function MakeItemTypeText()
	{
		if ($this->itemRecord['specialType'] > 0) return GetEsoItemSpecialTypeText($this->itemRecord['specialType']);
		
		switch ($this->itemRecord['type'])
		{
			case 1:
			case 2:
				return GetEsoItemEquipTypeText($this->itemRecord['equipType']);
			case 4:
				return "Food";
			default:
				return GetEsoItemTypeText($this->itemRecord['type']);
		}
	}
	
	
	private function MakeItemSubTypeText()
	{
		$type = $this->itemRecord['type'];
		$craftType = $this->itemRecord['craftType'];
		$specialType = $this->itemRecord['specialType'];
		
		if ($type <= 0) 
		{
			return "";
		}
		else if ($type == 2) //armor
		{
			if ($this->itemRecord['armorType'] > 0) return "" . GetEsoItemArmorTypeText($this->itemRecord['armorType']) . "";
			return "";
		}
		elseif ($type == 1) //weapon
		{
			return "" . GetEsoItemWeaponTypeText($this->itemRecord['weaponType']) . "";
		}
		elseif ($type == 29) // Recipe
		{
			if ($craftType == null || $craftType == "") return "";
			return "" . GetEsoItemCraftTypeText($craftType) . "";
		}
		elseif ($type == 61) // Furniture
		{
			$furnCate = $this->itemRecord['furnCategory'];
			$splitCats = explode(":", explode("(", $furnCate)[0]);
			$type1 = trim($splitCats[0]);
			$type2 = trim($splitCats[1]);
			
			//$type1 = $this->itemRecord['setBonusDesc4'];
			//$type2 = $this->itemRecord['setBonusDesc5'];
			
			if ($type1 != "" && $type != "") return "" . $type1 . " / " . $type2 . "";
			return "" . $type1 . $type2 . "";
		}
		else if ($craftType > 0)
		{
			return "" . GetEsoItemCraftTypeText($craftType) . "";
		}
		
		return "";
	}
	
	
	private function HasItemBar()
	{
		$type = $this->itemRecord['type'];
		$equipType = $this->itemRecord['equipType'];
		
		if ($type <= 0 || $equipType == 12 || $equipType == 2) return false;
		
			/* Weapons with no enchantments */
		if ($type == 1 && $this->itemRecord['weaponType'] != 14)
		{
			$hasEnchant = false;
			if ($this->itemRecord['enchantName'] != "") $hasEnchant = true;
			if ($this->itemRecord['enchantDesc'] != "") $hasEnchant = true;
			if ($this->enchantRecord1 != null) $hasEnchant = true;
			if ($this->enchantRecord2 != null)  $hasEnchant = true;
			if ($this->itemDisableEnchant) $hasEnchant = false;
			
			if (!$hasEnchant) return false;
			return true;
		}
		else if ($type == 2)
		{
			return true;
		}
		else if ($type == 60 || $type == 29)
		{
			return false;
		}
		
		return false;
	}
	
	
	private function MakeItemBarClass()
	{
		if (!$this->HasItemBar()) return "esoilHidden";
		return "";
	}
	
	
	private function MakeItemBarLink()
	{
		if (!$this->HasItemBar()) return "";
		
		$type = $this->itemRecord['type'];
		$maxCharges = $this->itemRecord['maxCharges'];
		
		if ($maxCharges <= 0 && ($this->enchantRecord1 != null || $this->enchantRecord2 != null))
		{
					// TODO: This is a rough Estimate
					// MaxCharges = 6.242428345 * Level + 112.8789751
					// MaxCharges = -108.8146179 + 0.6707490449 * WD - 6.371375423E-005 * WD*WD - 7.121772545E-008 * WD*WD*WD
					// $maxCharges = $this->itemRecord['weaponPower'] / 2;
			$wp = intval($this->itemRecord['weaponPower']);
			$maxCharges = -108.8146179 + 0.6707490449 * $wp - 6.371375423E-005 * $wp * $wp - 7.121772545E-008 * $wp * $wp * $wp;
			if ($this->itemTrait == 2) $maxCharges *= $this->itemRecord['quality']*0.25 + 2;
			
			$this->itemRecord['estimatedMaxCharges'] = $maxCharges;
			$this->itemAllData[0]['estimatedMaxCharges'] = $maxCharges;
		}
		
		if ($type == 1 && $maxCharges > 0 && $this->itemRecord['weaponType'] != 14)
		{
			$charges = $this->itemCharges;
			if ($charges < 0) $charges = $maxCharges;
		
			$coverImageSize = ($maxCharges - $charges) * 112 / $maxCharges;
			if ($coverImageSize < 0) $coverImageSize = 0;
			if ($coverImageSize > 112) $coverImageSize = 112;
			
			return "<img src='//esoitem.uesp.net/resources/eso_item_chargebar.png' id='esoil_chargebar' /><img src='//esoitem.uesp.net/resources/eso_item_barblack.png' id='esoil_chargebar_coverleft' style='width: {$coverImageSize}px;' /><img src='//esoitem.uesp.net/resources/eso_item_barblack.png' id='esoil_chargebar_coverright' style='width: {$coverImageSize}px;' />";
		}
		
		if ($type == 1 || $type == 2)
		{
			$condition = $this->itemCharges/100;
			if ($condition < 0) $condition = 100;
			
			$coverImageSize = (100 - $condition) * 112 / 100;
			if ($coverImageSize < 0) $coverImageSize = 0;
			if ($coverImageSize > 112) $coverImageSize = 112;
			
			return "<img src='//esoitem.uesp.net/resources/eso_item_conditionbar.png' id='esoil_conditionbar' /><img src='//esoitem.uesp.net/resources/eso_item_barblack.png' id='esoil_conditionbar_coverleft' style='width: {$coverImageSize}px;' /><img src='//esoitem.uesp.net/resources/eso_item_barblack.png' id='esoil_conditionbar_coverright' style='width: {$coverImageSize}px;' />";
		}
		
		return "";
	}
	
	
	private function ModifyEnchantDesc($desc, $isDefaultEnchant)
	{
		static $WEAPON_MATCHES = array
		(
			"#(Deals \|c[0-9a-fA-F]{6})([0-9]+)(\|r)#i",
			"#(restores \|c[0-9a-fA-F]{6})([0-9]+)(\|r)#i",
			"#(by \|c[0-9a-fA-F]{6})([0-9]+)(\|r)#i",
			"#(Grants a \|c[0-9a-fA-F]{6})([0-9]+)(\|r)#i",
			"#(maximum of \|c[0-9a-fA-F]{6})([0-9]+)(\|r)#i",
		);
		
		$newDesc = $desc;
		$trait = $this->itemTrait;
		$traitDesc = FormatRemoveEsoItemDescriptionText($this->itemRecord['traitDesc']);
		
		$armorFactor = 1 + $this->enchantFactor;
		$weaponFactor = 1 + $this->enchantFactor;
		
				/* Infused */
		if (!$isDefaultEnchant && ($trait == 16 || $trait == 4 || $trait == 33))
		{
			$result = preg_match("#effect(?:iveness|) by ([0-9]\.?[0-9]*)#", $traitDesc, $matches);
			$traitValue = 0;
			
			if ($result && $matches[1])
			{
				$armorTraitValue = 1 + ((float) $matches[1]) / 100;
				$weaponTraitValue = 1 + ((float) $matches[1]) / 100 * (1 + $this->weaponTraitFactor);
				
				if ($trait == 16) $armorFactor *= $armorTraitValue;
				if ($trait == 33) $armorFactor *= $armorTraitValue;
				if ($trait ==  4) $weaponFactor *= $weaponTraitValue;
			}
		}
		else if ($isDefaultEnchant && $this->transmuteTrait > 0)
		{
			$origTraitDesc = FormatRemoveEsoItemDescriptionText($this->itemRecord['origTraitDesc']);
			
			if ($this->transmuteTrait != 16 && $this->itemRecord['origTrait'] == 16)
			{
				$result = preg_match("#effect by ([0-9]\.?[0-9]*)#", $origTraitDesc, $matches);
				
				if ($result && $matches[1])
				{
					$traitValue = 1 + ((float) $matches[1]) / 100;
					$armorFactor /= $traitValue;
				}
			}
			elseif ($this->transmuteTrait != 33 && $this->itemRecord['origTrait'] == 33)
			{
				$result = preg_match("#effectiveness by ([0-9]\.?[0-9]*)#", $origTraitDesc, $matches);
				
				if ($result && $matches[1])
				{
					$traitValue = 1 + ((float) $matches[1]) / 100;
					$armorFactor /= $traitValue;
				}
			}
			elseif ($this->transmuteTrait != 4 && $this->itemRecord['origTrait'] == 4)
			{
				$result = preg_match("#effect by ([0-9]\.?[0-9]*)#", $origTraitDesc, $matches);
			
				if ($result && $matches[1])
				{
					$traitValue = 1 + ((float) $matches[1]) / 100;
					$weaponFactor /= $traitValue;
				}
			}
			
			if ($this->transmuteTrait == 16 && $this->itemRecord['origTrait'] != 16)
			{
				$result = preg_match("#effect by ([0-9]\.?[0-9]*)#", $traitDesc, $matches);
			
				if ($result && $matches[1])
				{
					$traitValue = 1 + ((float) $matches[1]) / 100;
					$armorFactor *= $traitValue;
				}
			}
			elseif ($this->transmuteTrait == 33 && $this->itemRecord['origTrait'] != 33)
			{
				$result = preg_match("#effectiveness by ([0-9]\.?[0-9]*)#", $traitDesc, $matches);
			
				if ($result && $matches[1])
				{
					$traitValue = 1 + ((float) $matches[1]) / 100;
					$armorFactor *= $traitValue;
				}
			}
			elseif ($this->transmuteTrait == 4 && $this->itemRecord['origTrait'] != 4)
			{
				$result = preg_match("#effect by ([0-9]\.?[0-9]*)#", $traitDesc, $matches);
					
				if ($result && $matches[1])
				{
					$traitValue = 1 + ((float) $matches[1]) / 100 * (1 + $this->weaponTraitFactor);
					$weaponFactor *= $traitValue;
				}
			}
			
			//error_log("Transmute Infused $armorFactor, $newDesc");
			//error_log("Transmute Infused $weaponFactor, $newDesc");
		} 
		
		$armorType = $this->itemRecord['armorType'];
		$weaponType = $this->itemRecord['weaponType'];
		$equipType = $this->itemRecord['equipType'];
		$itemType = $this->itemRecord['type'];
		
			/* Half-enchants on 1H weapons, update 21 */
		if (!$isDefaultEnchant && ($weaponType == 1 || $weaponType == 2 || $weaponType == 3 || $weaponType == 11))
		{
			$weaponFactor *= 0.5;
		}
		
			/* Modify enchants of small armor pieces */
		if (!$isDefaultEnchant && $armorType > 0 && ($equipType == 4 || $equipType == 8 || $equipType == 10 || $equipType == 13))
		{
			$armorFactor *= 0.405; 
		}
		
		if (($itemType == 2 || $weaponType == 14) && $armorFactor != 1)
		{
			$newDesc = preg_replace_callback("#((?:Adds \|c[0-9a-fA-F]{6})|(?: by \|c[0-9a-fA-F]{6})|)([0-9]+)((?:\|r)|)#i",
				
				function ($matches) use ($armorFactor) {
					$result = floor($matches[2] * $armorFactor);
            		return $matches[1] . $result . $matches[3];
        		},
        		
        		$newDesc);
		}
		else if ($weaponType > 0 && $weaponType != 14 && $weaponFactor != 1)
		{
			
			foreach ($WEAPON_MATCHES as $match)
			{
				$newDesc = preg_replace_callback($match,
							
						function ($matches) use ($weaponFactor) {
							$result = floor($matches[2] * $weaponFactor);
							return $matches[1] . $result . $matches[3];
						},
						
						$newDesc);
			}
		}
		
		$newDesc = $this->escape($newDesc);
		$newDesc = $this->FormatDescriptionText($newDesc);
		
		return $newDesc;
	}	

	
	private function MakeItemEnchantBlock()
	{
		$output = "";
		
			/* TODO: Temp fix for potions showing enchantments/sets */
		if ($this->itemRecord['type'] == 7) return "";
		
		if ($this->itemDisableEnchant) return "";
		
		if ($this->enchantRecord1 != null)
		{
			$enchantName = strtoupper($this->enchantRecord1['enchantName']);
			$enchantDesc = $this->ModifyEnchantDesc($this->enchantRecord1['enchantDesc'], false);
			if ($enchantName != "") $output .= "<div class='esoil_white esoil_small'>$enchantName</div><br />";
			if ($enchantDesc != "") $output .= "$enchantDesc";
		}
		
		if ($this->enchantRecord2 != null)
		{
			$enchantName = strtoupper($this->enchantRecord2['enchantName']);
			$enchantDesc = $this->ModifyEnchantDesc($this->enchantRecord2['enchantDesc'], false);
			
			if ($enchantDesc != "")
			{
				if ($output != "") $output .= "<p style='margin-top: 0.7em; margin-bottom: 0.7em;'/>";
				if ($enchantName != "") $output .= "<div class='esoil_white esoil_small'>$enchantName</div><br />";
				$output .= "$enchantDesc";
			}
		}
		
		if ($this->enchantRecord1 == null && $this->enchantRecord2 == null)
		{
			$enchantName = strtoupper($this->itemRecord['enchantName']);
			$enchantDesc = $this->ModifyEnchantDesc($this->itemRecord['enchantDesc'], true);
			if ($enchantName != "") $output .= "<div class='esoil_white esoil_small'>$enchantName</div><br />";
			if ($enchantDesc != "") $output .= "$enchantDesc";
		}
		
		return $output;
	}
	
	
	private function MakeItemTraitBlock()
	{
		$transmuteIcon = "";
		$trait = $this->itemTrait;
		
		if ($this->weaponTraitFactor > 0 && $this->itemRecord['type'] == 1 && $this->itemRecord['weaponType'] != 14 && $this->itemRecord['trait'] != 9 && $this->itemRecord['trait'] != 10)
		{
			$rawDesc = $this->itemRecord['traitDesc'];
			
			$rawDesc = preg_replace_callback('/by \|cffffff([0-9.]+)\|r/', function($matches) {
				$traitValue = floatval($matches[1]);
				$newTraitValue = $traitValue * (1 + $this->weaponTraitFactor);
				return "by |cffffff$newTraitValue|r";
			}, $rawDesc, 1);
			
			$rawDesc = preg_replace_callback('/a \|cffffff([0-9.]+)\|r% chance/', function($matches) {
				$traitValue = floatval($matches[1]);
				$newTraitValue = $traitValue * (1 + $this->weaponTraitFactor);
				return "a |cffffff$newTraitValue|r% chance";
			}, $rawDesc, 1);
			
			$traitDesc = $this->FormatDescriptionText($rawDesc);
		}
		else
		{
			$traitDesc = $this->FormatDescriptionText($this->itemRecord['traitDesc']);
		}
		
		$traitName = strtoupper(GetEsoItemTraitText($trait, $this->version));
		
		if ($trait <= 0) return "";
		
		if ($this->transmuteTrait > 0) $transmuteIcon = "<img src='//esoitem.uesp.net/resources/transmute_icon.png' class='esoil_transmute_icon'>";
		
		return "$transmuteIcon<div class='esoil_white esoil_small'>$traitName</div><br />$traitDesc";
	}
	
	
	private function FormatDescriptionText($desc)
	{
		$desc = $this->escape($desc);
		return FormatEsoItemDescriptionText($desc);
	}
	
	
	private function FormatSetDescriptionText($desc, $setCount, $perfectCount = -1)
	{
		$desc = $this->escape($desc);
		
		if (self::ESOIL_USEPRECENT_CRITICALVALUE)
			$output = FormatEsoCriticalDescriptionText($desc, $this->itemRecord['level']);
		else
			$output = $desc;
		
		if (($this->itemSetCount >= 0 && $setCount > $this->itemSetCount) || ($this->itemPerfectCount > 0 && $perfectCount > $this->itemPerfectCount))
		{
			$output = preg_replace("#\|c([0-9a-fA-F]{6})([a-zA-Z \-0-9\.%]+)\|r#s", "$2", $output);
			$output = str_replace("\n", "<br />", $output);
			
			$output = "<div class='esoil_itemsetdisabled'>" . $output . "</div>";
		}
		else
		{
			$output = FormatEsoItemDescriptionText($desc);
		}
		
		return $output;
	}
	
	
	private function ParsePerfectSetCounts()
	{
		for ($i = 1; $i <= self::MAXSETINDEX; $i += 1)
		{
			//$setCount = $this->itemRecord['setBonusCount' . $i];
			//$perfectCount = $this->itemRecord['perfectBonusCount' . $i];
			$setDesc = $this->itemRecord['setBonusDesc' . $i];
			
			$result = preg_match('/\(([0-9]+) items\)/', $setDesc, $matches);
			
			if ($result)
			{
				$this->itemRecord['perfectBonusCount' . $i] = -1;
				continue;
			}
			
			$result = preg_match('/\(([0-9]+) perfected items\)/', $setDesc, $matches);
			
			if ($result)
			{
				$this->itemRecord['perfectBonusCount' . $i] = intval($matches[1]);
				$this->itemRecord['setBonusCount' . $i] = -1;
			}
		}
	}
	
	
	private function MakeItemSetBlock()
	{
			/* TODO: Temp fix for potions showing enchantments/sets */
		if ($this->itemRecord['type'] == 7) return "";
		
		$setName = strtoupper($this->itemRecord['setName']);
		if ($setName == "") return "";
		
		$safeSetName = $this->escape($setName);
		
		$setMaxEquipCount = $this->itemRecord['setMaxEquipCount'];
		$setBonusCount = (int) $this->itemRecord['setBonusCount'];
		$output = "<div class='esoil_white esoil_small'>PART OF THE $safeSetName SET ($setMaxEquipCount/$setMaxEquipCount ITEMS)</div>";
		
		for ($i = 1; $i <= self::MAXSETINDEX; $i += 1)
		{
			$setCount = $this->itemRecord['setBonusCount' . $i];
			$perfectCount = $this->itemRecord['perfectBonusCount' . $i];
			$setDesc = $this->itemRecord['setBonusDesc' . $i];
			if ($setDesc == null || $setDesc == "") continue;
			
			$setDesc = $this->FormatSetDescriptionText($setDesc, $setCount, $perfectCount);
			$output .= "<br />$setDesc";
		}
		
		return $output;
	}
	
	
	private function MakeSetItemSetBlock()
	{
		$setName = strtoupper($this->setItemData['setName']);
		if ($setName == "") return "";
		
		$safeSetName = $this->escape($setName);
		
		$setMaxEquipCount = $this->setItemData['setMaxEquipCount'];
		$setBonusCount = (int) $this->setItemData['setBonusCount'];
		$output = "<div class='esoil_white esoil_small'>PART OF THE $safeSetName SET ($setMaxEquipCount/$setMaxEquipCount ITEMS)</div>";
		
		for ($i = 1; $i <= self::MAXSETINDEX; $i += 1)
		{
			$setCount = $this->setItemData['setBonusCount' . $i];
			$perfectCount = $this->itemRecord['perfectBonusCount' . $i];
			$setDesc = $this->setItemData['setBonusDesc' . $i];
			if ($setDesc == null || $setDesc == "") continue;
			
			$setDesc = $this->FormatSetDescriptionText($setDesc, $setCount, $perfectCount);
			$output .= "<br />$setDesc";
		}
		
		return $output;
	}
	
	
	private function MakeItemAbilityBlock()
	{
		
		if ($this->itemRecord['type'] == 60)	// Master Writs
		{
			$abilityDesc = $this->itemRecord['abilityDesc'];
			$abilityDesc = $this->FormatDescriptionText($abilityDesc);
			$abilityDesc = FormatEsoItemDescriptionIcons($abilityDesc);
			if ($abilityDesc == "") return "";
			return "$abilityDesc";
		}
		else if ($this->itemRecord['type'] == 29)	//Recipes
		{
			$ability = $this->escape(strtoupper($this->itemRecord['abilityName']));
			$abilityDesc = $this->itemRecord['abilityDesc'];
			$abilityDesc = $this->FormatDescriptionText($abilityDesc);
			$abilityDesc = FormatEsoItemDescriptionIcons($abilityDesc);
			if ($abilityDesc == "") return "";
			return "<div class='esoil_white esoil_small'>$ability</div> $abilityDesc";
		}
		else if ($this->itemRecord['type'] == 33)	//Potion Base
		{
			$level = $this->MakeLevelTooltipText($this->itemRecord['level']);
			$craft = $this->itemRecord['craftType'];
			$skillRank = $this->itemRecord['craftSkillRank'];
			$desc = "Makes a $level potion.";
			
			if ($craft > 0 && $skillRank > 0)
			{
				$craft = GetEsoItemCraftRequireText($craft);
				$skillRank = intval($skillRank);
				$desc .= "\n\n|c00ff00Requires $craft $skillRank.|r";
			}
		
			return FormatEsoItemDescriptionText($desc);
		}
		else if ($this->itemRecord['type'] == 58)	//Poison Base
		{
			$level = $this->MakeLevelTooltipText($this->itemRecord['level']);
			$craft = $this->itemRecord['craftType'];
			$skillRank = $this->itemRecord['craftSkillRank'];
			$desc = "Makes a $level poison.";
			
			if ($craft > 0 && $skillRank > 0)
			{
				$craft = GetEsoItemCraftRequireText($craft);
				$skillRank = intval($skillRank);
				$desc .= "\n\n|c00ff00Requires $craft $skillRank.|r";
			}
			
			return FormatEsoItemDescriptionText($desc);
		}
		else if (($this->itemRecord['type'] == 30 || $this->itemRecord['type'] == 7) && $this->itemPotionData > 0)
		{
			return "";
		}
		else
		{
			$ability = $this->escape(strtoupper($this->itemRecord['abilityName']));
			$abilityDesc = $this->FormatDescriptionText($this->itemRecord['abilityDesc']);
			$cooldown = ((int) $this->itemRecord['abilityCooldown']) / 1000;
		}
		
		if ($abilityDesc == "") return "";
		
		$output = "";
		if ($ability != "") $output .= "<div class='esoil_white esoil_small'>$ability</div><br/>";
		$output .= "$abilityDesc";
		if ($cooldown > 0) $output .= " ($cooldown second cooldown)";
		return $output;
	}
	
	
	private function MakeItemTraitAbilityBlock()
	{
		if ($this->itemRecord['traitAbilityDescArray'] == null)
		{
			$abilityDesc = strtoupper($this->itemRecord['traitAbilityDesc']);
			if ($abilityDesc == "") return "";
			//$cooldown = round($this->itemRecord['traitCooldown'] / 1000);
			//return "$abilityDesc ($cooldown second cooldown)";
			return $abilityDesc;
		}
		
		$abilityDesc = array();
		$cooldownDesc = "";
		
		
		foreach ($this->itemRecord['traitAbilityDescArray'] as $index => $desc)
		{
			$abilityDesc[] = $desc;
			//$cooldown = round($this->itemRecord['traitCooldownArray'][$index] / 1000);
			//$cooldownDesc = " ($cooldown second cooldown)";
		}
		
		if (count($abilityDesc) == 0) return "";
		
		$output = implode("\n\n", $abilityDesc);
		$output = $this->escape($output);
		//$output .= "\n" . $cooldownDesc;
		$output = $this->FormatDescriptionText($output);
		return $output;
	}
	
	
	private function GetItemLeftBlockDisplay()
	{
		switch ($this->itemRecord['type'])
		{
			case 2:
				$equipType = $this->itemRecord['equipType'];
				if ($equipType == 2 || $equipType == 12) return "none";
				return "inline-block";
			case 1:
				return "inline-block";
		}
		
		return "none";
	}
	
	
	private function GetItemLevelBlockDisplay()
	{
		$level = $this->itemRecord['level'];
		if ($level <= 0) return "none";
		
		//if ($this->showSummary) return "none";
		
		switch ($this->itemRecord['type'])
		{
			case 61:	// Furnishing
			case 29:	// Recipe
			case 59:	// Dye Stamp
			case 60:	// Master Writ
			case 44:	// Style Material
			case 31:	// Reagent
			case 10:	// Ingredient
			case 52:	// Rune
			case 53:
			case 41:	// Blacksmith Temper
			case 43:
			case 34:
			case 39:
			case 18:
			case 55:
			case 57:
			case 47: 	// PVP Repair
			case 36:	// BS Material
			case 35:	// BS Raw Material
			case 40:
			case 54:
			case 16:
			case 8:
			case 17:
			case 6:
			case 19:
			case 48:
			case 5:
			case 46:
			case 42:
			case 38:
			case 37:
			case 0:
			case -1:
			case 58:	// Poison Base
			case 33:	// Potion Base
			case 45:
			case 51:
				return "none";
				
			case 2:		// Armor/Weapons
			case 1:
			case 12: 	// Drink
			case 4: 	// Food
			case 26:	// Glyph
			case 3:
			case 20:
			case 21:
			case 30:	// Poison
			case 7:		// Potion
			case 51:
			case 9:		// Repair Kit
				return "inline-block";
		}
		
		return "inline-block";
	}
	
	
	private function GetItemValueBlockDisplay()
	{
		$value = $this->itemRecord['value'];
		
		if ($value <= 0) return "none";
		return "inline-block";
	}
	
	
	private function GetItemRightBlockDisplay()
	{
		if (!$this->useUpdate10Display) return $this->GetItemValueBlockDisplay();
		
		$level = $this->itemRecord['level'];
		
		if ($this->showSummary) return "none";
		if ($this->GetItemLevelBlockDisplay() == "none") return "none";
		
		if ($level > 50) return "inline-block";
		if (!is_numeric($level)) return "inline-block";
		
		return "none";
	}
	
	
	private function GetItemNewValueBlockDisplay()
	{
		if (!$this->useUpdate10Display) return "none";
		
		if ($this->itemRecord['value'] > 0) return "inline-block";
		return "none";
	}
	
	
	private function GetItemDataJson()
	{
		$output = json_encode($this->itemAllData);
		return $output;
	}
	
	
	private function MakeItemLink($matchLiveSubtype = false)
	{
		global $ESO_ITEMQUALITYLEVEL_INTTYPEMAP;
		global $ESO_ITEMINTTYPE_QUALITYMAP;
		
		$d1 = $this->itemRecord['itemId'];
		
		$d2 = $this->itemRecord['internalSubtype'];
		if ($d2 == null || $d2 == "") $d2 = 370;
		
		$d3 = $this->itemRecord['internalLevel'];
		if ($d3 == null || $d3 == "") $d3 = 50;
		
		if ($matchLiveSubtype)
		{
			$quality = $ESO_ITEMINTTYPE_QUALITYMAP[$d2];
			
			if ($quality != null)
			{
				$level = GetEsoLevelFromIntType($d2, $d3);
				$subtypes = FindEsoItemLevelIntTypeMap($level);
				$newSubtype = $subtypes[$quality];
				
				if ($newSubtype) $d2 = $newSubtype;
			}
		}
		
		$d4 = $this->enchantId1;
		$d5 = $this->enchantIntType1;
		$d6 = $this->enchantIntLevel1;
		$d7 = $this->writData1;
		$d8 = $this->writData2;
		$d9 = $this->writData3;
		$d10 = $this->writData4;
		$d11 = $this->writData5;
		$d12 = $this->writData6;
		$d13 = 0;
		$d14 = 0;
		$d15 = 0;
		$d16 = $this->itemRecord['style']; //Style
		$d17 = $this->itemCrafted < 0 ? 0 : $this->itemCrafted; //Crafted
		$d18 = $this->itemBound < 0 ? 0 : $this->itemBound; //Bound
		$d19 = ($this->itemStolen <= 0) ? 0 : 1;
		$d19 = $this->itemCharges < 0 ? 0 : $this->itemCharges; //Charges
		
		if ($this->itemStolen < 0)
			$d20 = "0";
		else
			$d20 = $this->itemStolen;
		
		if ($this->itemPotionData <= 0)
			$d21 = "0";
		else
			$d21 = $this->itemPotionData;
		
		$itemName = $this->itemRecord['name'];
		
		$link = "|H0:item:$d1:$d2:$d3:$d4:$d5:$d6:$d7:$d8:$d9:$d10:$d11:$d12:$d13:$d14:$d15:$d16:$d17:$d18:$d19:$d20:$d21|h|h";
		
		return $link;
	}
	
	
	private function MakeItemCraftedBlock()
	{
		if ($this->itemCrafted <= 0) return "";
		return "Created by: Someone";
	}
	
	
	private function MakeCollectibleItemTags()
	{
		$output = "";
		
		if ($this->collectibleItemData['tags'] != "")
		{
			if ($this->collectibleItemData['furnCategory'] != "")
				$output .= "<div id='esoil_itemfurntype_title'>Furnishing Behavior</div>";
			else
				$output .= "<div id='esoil_itemtag_title'>Treasure Type</div>";
			
			$output .= $this->escape($this->collectibleItemData['tags']);
		}
		
		if ($this->collectibleItemData['furnCategory'] != "" && $this->collectibleItemData['furnLimitType'] >= 0)
		{
			$output .= "<div id='esoil_itemfurntype_title'>Furnishing Limit Type</div>";
			$furnType = GetEsoFurnLimitTypeText($this->collectibleItemData['furnLimitType']);
			$output .= "$furnType";
		}
		
		return $output;
	}
	
	
	private function MakeItemTagsBlock()
	{
		$output = "";
		
		if ($this->itemRecord['tags'] != "") 
		{
			if ($this->itemRecord['type'] == 61)
				$output .= "<div id='esoil_itemfurntype_title'>Furnishing Behavior</div>";
			else
				$output .= "<div id='esoil_itemtags_title'>Treasure Type:</div>";
			
			$output .= $this->escape($this->itemRecord['tags']);
		}
		
		if ($this->itemRecord['type'] == 61 && $this->itemRecord['furnLimitType'] >= 0)
		{
			$output .= "<div id='esoil_itemfurntype_title'>Furnishing Limit Type</div>";
			$furnType = GetEsoFurnLimitTypeText($this->itemRecord['furnLimitType']);
			$output .= "$furnType";
		}
		
		return $output;
	}
	
	
	private function GetItemLinkURL()
	{
		$itemLinkURL = '';
		
		if ($this->version != '' || $this->showSummary || $this->enchantId1 > 0 ||
			$this->itemPotionData > 0 || $this->itemCharges >= 0 || $this->itemStolen > 0 ||
			$this->writData1 > 0 || $this->writData2 > 0 || $this->writData3 > 0 || $this->writData4 > 0 || $this->writData5 > 0 || $this->writData6 > 0 ||
			$this->transmuteTrait > 0)
		{
			$showSummary = $this->showSummary ? 'summary' : '';
			$itemLinkURL = 	"itemLinkImage.php?itemid={$this->itemRecord['itemId']}&level={$this->itemRecord['level']}&" .
							"quality={$this->itemRecord['quality']}&enchantid={$this->enchantId1}&enchantintlevel={$this->enchantIntLevel1}&" .
							"enchantinttype={$this->enchantIntType1}&v={$this->version}&{$showSummary}&potiondata={$this->itemPotionData}&stolen={$this->itemStolen}&" .
							"writ1={$this->writData1}&writ2={$this->writData2}&writ3={$this->writData3}&writ4={$this->writData4}&writ5={$this->writData5}&writ6={$this->writData6}&" .
							"itemlink={$this->itemLink}&trait={$this->transmuteTrait}";
		}
		else 
		{
			$itemLinkURL = "//esoitem.uesp.net/item-{$this->itemRecord['itemId']}-{$this->itemRecord['level']}-{$this->itemRecord['quality']}.png";
		}
		
		return $itemLinkURL;
	}
	
	
	private function MakeItemStyle()
	{
		$type = $this->itemRecord['type'];
		if ($type != 1 && $type != 2) return "";
		
		if ($this->itemStyle > 0) return GetEsoItemStyleText($this->itemStyle);
		if ($this->itemRecord['style'] > 0) return GetEsoItemStyleText($this->itemRecord['style']);
		return "";
	}
	
	
	private function CheckVersionLessThan($version)
	{
		if ($this->version == "") return false;
		return $this->version < $version;
	}
	
	
	private function MakePotencyItemDescription()
	{
		if (!$this->useUpdate10Display) return $this->MakeOldPotencyItemDescription();
		
		$glyphMinLevel = $this->itemRecord['glyphMinLevel'];
		if ($glyphMinLevel <= 0) $glyphMinLevel = $this->itemRecord['level'];
		
		$minDesc = $this->MakeLevelTooltipText($glyphMinLevel);
		$desc = "Used to create glyphs of $minDesc and higher.";
		return $desc;
	}
	
	
	public function MakeLevelTooltipText($level)
	{
		$desc = "";
		
		if ($level < 50)
		{
			$desc = "level $level";
		}
		else
		{
			$cp = ($level - 50) * 10;
			$desc = "<img src='//esoitem.uesp.net/resources/champion_icon.png' class='esoil_cpimgsmall'>CP $cp";
			//$desc = "|t16:16:esoui/art/champion/champion_icon_32.dds|tCP $cp";
			//$desc = FormatEsoItemDescriptionIcons($desc);
		}
		
		return $desc;
	}
	
	
	private function MakeOldPotencyItemDescription()
	{
		$glyphMinLevel = $this->itemRecord['glyphMinLevel'];
		$glyphMaxLevel = $this->itemRecord['glyphMaxLevel'];
		if ($glyphMinLevel == 0 && $glyphMaxLevel == 0) return $this->itemRecord['description'];
	
		$minDesc = "";
		$maxDesc = "";
	
		if ($glyphMinLevel < 50)
		{
			$minDesc = "level $glyphMinLevel";
		}
		else
		{
			$glyphMinLevel = $glyphMinLevel - 50;
			if ($this->CheckVersionLessThan(9)) $glyphMinLevel += 1;
			$minDesc = "|t32:32:EsoUI/Art/UnitFrames/target_veteranRank_icon.dds|trank $glyphMinLevel";
		}
	
		if ($glyphMaxLevel < 50)
		{
			$maxDesc = "level $glyphMaxLevel";
		}
		else
		{
			$glyphMaxLevel = $glyphMaxLevel - 50;
			if ($this->CheckVersionLessThan(9)) $glyphMaxLevel += 1;
			$maxDesc = "|t32:32:EsoUI/Art/UnitFrames/target_veteranRank_icon.dds|trank $glyphMaxLevel";
		}
	
		if ($minDesc == $maxDesc)
			$desc = "Used to create glyphs of $minDesc.";
		else
			$desc = "Used to create glyphs of $minDesc to $maxDesc.";
	
		return $desc;
	}
	
	
	private function MakeItemDescription()
	{
		$desc = $this->escape($this->itemRecord['description']);
		$matDesc = $this->escape($this->itemRecord['materialLevelDesc']);
		
		if ($this->itemRecord['type'] == 51)
		{
			$desc = $this->MakePotencyItemDescription();
		}
		else if ($this->itemRecord['type'] == 44)
		{
			$style = GetEsoItemStyleText($this->itemRecord['style']);
			$desc = "An ingredient for crafting in the |cffffff$style|r style.";
		}
		else if ($this->itemRecord['type'] == 58)
		{
			$desc = "";
		}
		else if ($this->itemRecord['type'] == 33)
		{
			$desc = "";
		}
		else if ($matDesc != "") 
		{
			$desc = FormatEsoItemDescriptionIcons($matDesc);
		}
		
		return FormatEsoItemDescriptionText($desc);
	}
	
	
	private function MakeSetItemDescription()
	{
		//return "";
		
		//$desc = $this->escape($this->setItemData['description']);
		//$setName = $this->escape($this->setItemData['setName']);
		$slots = $this->escape($this->setItemData['itemSlots']); 
		return FormatEsoItemDescriptionText($desc) . "<br/><div class='esoil_itemdescSetName'>$slots</div>";
	}
	
	
	private function MakeQuestItemDescription()
	{
		$desc = $this->escape($this->questItemData['description']);
		$questName = $this->escape($this->questItemData['questName']);
		return FormatEsoItemDescriptionText($desc) . "<br/><div class='esoil_itemdescQuestName'>$questName</div>";
	}
	
	
	private function MakeCollectibleItemDescription()
	{
		$desc = $this->escape($this->collectibleItemData['description']) . "\n\n" . $this->escape($this->collectibleItemData['hint']);
		$desc = preg_replace("#\<\<player{his/her}\>\>#", "his", $desc);
		return FormatEsoItemDescriptionText($desc);
	}
	
	
	private function MakeAntiquityItemDescription()
	{
		$desc = "";
		
		if ($this->antiquityItemData['loreName1'] != "") {
			$desc .= "<b>" . $this->escape($this->antiquityItemData['loreName1']) . "</b>\n" . $this->escape($this->antiquityItemData['loreDescription1']);
		}
		
		if ($this->antiquityItemData['loreName2'] != "") {
			$desc .= "\n\n<b>" . $this->escape($this->antiquityItemData['loreName2']) . "</b>\n" . $this->escape($this->antiquityItemData['loreDescription2']);
		}
		
		if ($this->antiquityItemData['loreName3'] != "") {
			$desc .= "\n\n<b>" . $this->escape($this->antiquityItemData['loreName3']) . "</b>\n" . $this->escape($this->antiquityItemData['loreDescription3']);
		}
		
		return FormatEsoItemDescriptionText($desc);
	}
	
	
	private function MakeAntiquitySetDescription()
	{
		$desc = "";
		
		if ($this->antiquityItemData['setName']  == "") return "";
		if ($this->antiquityItemData['setCount'] == 0) return "";
		
		$desc = "One of the " . $this->antiquityItemData['setCount'] . " pieces of the " . $this->escape($this->antiquityItemData['setName']) . ".";
		return $desc;
	}
	
	
	private function GetItemRawVersion()
	{
		if ($this->version == "")
			$suffix = GetEsoUpdateVersion();
		else
			$suffix = intval(GetEsoItemTableSuffix($this->version));
		
		return $suffix;
	}
	
	
	private function MakeItemDyeStampBlock()
	{
		$dyeData = $this->itemRecord['dyeData'];
		if ($this->itemRecord['type'] != 59 || $dyeData == null || $dyeData == "") return "";
		
		$parsedDyeData = explode(",", $dyeData);
		$dyeStampId = $parsedDyeData[0];
		$dye1 = $parsedDyeData[1];
		$dye2 = $parsedDyeData[2];
		$dye3 = $parsedDyeData[3];
				
		$output = "<div class='esoil_dyedesc'>Dyes all the channels of your currently equipped<br/><div class='esoil_white'>Costume</div> and <div class='esoil_white'>Hat.</div></div>";
		
		$output .= $this->MakeItemDyeStampSubBlock($dye1);
		$output .= $this->MakeItemDyeStampSubBlock($dye2);
		$output .= $this->MakeItemDyeStampSubBlock($dye3);
		
		return $output;
	}
	
	
	private function GetItemDescriptionClass()
	{
		$itemType = $this->itemRecord['type'];
		
		if ($itemType == 61) return "esoil_itemdescQuest";
		return "";
	}
	
	
	private function MakeItemDyeStampSubBlock($rawDyeData)
	{
		if ($rawDyeData == null) return "";
		
		$result = preg_match("/(?P<dyeId>[0-9]+){(?P<dyeName>[^}]*)}{(?P<dyeColor>[0-9a-zA-Z]*)}/", $rawDyeData, $matches);
		if (!$result) return "";
		
		$dyeId = intval($matches['dyeId']);
		$dyeName = $this->escape($matches['dyeName']);
		$dyeColor = $this->escape($matches['dyeColor']);
		
		if ($dyeName  == null || $dyeName  == "") return "";
		if ($dyeColor == null || $dyeColor == "") return "";
		
		$output = "<div class='esoil_dyename'>";
		
			////esoitem.uesp.net/resources/dyeStampBox.png  24x24 px
		$output .= "<div class='esoil_dyecolorbox'>";
		$output .= "<div class='esoil_dyecolor' style='background-color: #{$dyeColor}'></div>";
		$output .= "</div>"; 
			
		$output .= $dyeName;
		$output .= "</div>";
		
		return $output;
	}
	
	
	private function GetItemQualityText()
	{
		if ($this->showSummary)
		{
			if ($this->itemRecord['quality'] == "1-5" || $this->itemRecord['quality'] == "0-5") return GetEsoItemQualityText(5); 
		}
		
		return GetEsoItemQualityText($this->itemRecord['quality']);
	}
	
	
	private function GetSetItemQualityText()
	{
		return GetEsoItemQualityText($this->setItemData['quality']);
	}
	
	
	private function OutputHtml()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->itemRecord['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->itemRecord['name'])),
				'{itemDesc}' => $this->MakeItemDescription(),
				'{itemLink}' => $this->MakeItemLink(),
				'{itemStyle}' => $this->MakeItemStyle(),
				'{itemId}' => $this->itemRecord['itemId'],
				'{itemType1}' => $this->MakeItemTypeText(),
				'{itemType2}' => $this->MakeItemSubTypeText(),
				'{itemStolen}' => $this->MakeItemStolenText(),
				'{itemBindType}' => $this->MakeItemBindTypeText(),
				'{itemValue}' => $this->itemRecord['value'],
				'{itemLevel}' => $this->MakeItemLevelSimpleString(),
				'{itemLevelRaw}' => $this->itemRecord['level'],
				'{itemQualityRaw}' => $this->itemRecord['quality'],
				'{itemLevelBlock}' => $this->MakeItemLevelString(),
				'{itemQuality}' => $this->GetItemQualityText(),
				'{iconLink}' => $this->MakeItemIconImageLink(),
				'{itemLeftBlock}' => $this->MakeItemLeftBlock(),
				'{itemRightBlock}' => $this->MakeItemRightBlock(),
				'{itemNewValueBlock}' => $this->MakeItemNewValueBlock(),
				'{itemBar}' => $this->MakeItemBarLink(),
				'{itemBarClass}' =>  $this->MakeItemBarClass(),
				'{itemEnchantBlock}' => $this->MakeItemEnchantBlock(),
				'{itemTraitBlock}' => $this->MakeItemTraitBlock(),
				'{itemSetBlock}' => $this->MakeItemSetBlock(),
				'{itemAbilityBlock}' => $this->MakeItemAbilityBlock(),
				'{itemTraitAbilityBlock}' => $this->MakeItemTraitAbilityBlock(),
				'{itemLeftBlockDisplay}' => $this->GetItemLeftBlockDisplay(),
				'{itemLevelBlockDisplay}' => $this->GetItemLevelBlockDisplay(),
				'{itemRightBlockDisplay}' => $this->GetItemRightBlockDisplay(),
				'{itemNewValueBlockDisplay}' => $this->GetItemNewValueBlockDisplay(),
				'{itemCraftedBlock}' => $this->MakeItemCraftedBlock(),
				'{itemTags}' => $this->MakeItemTagsBlock(),
				'{itemDataJson}' => $this->GetItemDataJson(),
				'{itemSimilarBlock}' => $this->MakeSimilarItemBlock(),
				'{itemEnchantId1}' => $this->itemRecord['enchantId1'],
				'{itemEnchantIntLevel1}' => $this->itemRecord['enchantIntLevel1'],
				'{itemEnchantIntType1}' => $this->itemRecord['enchantIntType1'],
				'{showSummary}' => $this->showSummary ? 'summary' : '',
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{itemLinkURL}' => $this->GetItemLinkURL(),
				'{viewSumDataExtraQuery}' => $this->GetSummaryDataExtraQuery(),
				'{itemRawDataList}' => $this->MakeItemRawDataList(),
				'{rawItemVersion}' => $this->GetItemRawVersion(),
				'{extraDataLinkDisplay}' => "block",
				'{controlBlockDisplay}' => "inline-block",
				'{similarItemBlockDisplay}' => "none",
				'{itemTypeTitle}' => "",
				'{itemDescClass}' => $this->GetItemDescriptionClass(),
				'{itemDyeStampBlock}' => $this->MakeItemDyeStampBlock(),
			);
		
		$output = strtr($this->htmlTemplate, $replacePairs);
		
		print ($output);
	}
	
	
	private function OutputSetItemHtml()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->setItemData['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->setItemData['name'])),
				'{itemDesc}' => $this->MakeSetItemDescription(),
				'{itemLink}' => $this->escape($this->setItemData['itemLink']),
				'{itemStyle}' => "",
				'{itemId}' => $this->escape($this->itemSet),
				'{itemType1}' => $this->escape($this->setItemData['gameId']),
				'{itemType2}' => "",
				'{itemStolen}' => "",
				'{itemBindType}' => "",
				'{itemValue}' => "",
				'{itemLevel}' => "",
				'{itemLevelRaw}' => "",
				'{itemQualityRaw}' => $this->setItemData['quality'],
				'{itemLevelBlock}' => "",
				'{itemQuality}' => $this->GetSetItemQualityText(),
				'{iconLink}' => $this->MakeSetItemIconImageLink(),
				'{itemLeftBlock}' => "",
				'{itemRightBlock}' => "",
				'{itemNewValueBlock}' => "",
				'{itemBar}' => "",
				'{itemBarClass}' => "esoilHidden",
				'{itemEnchantBlock}' => "",
				'{itemTraitBlock}' => "",
				'{itemSetBlock}' => $this->MakeSetItemSetBlock(),
				'{itemAbilityBlock}' => "",
				'{itemTraitAbilityBlock}' => "",
				'{itemLeftBlockDisplay}' => "none",
				'{itemLevelBlockDisplay}' => "none",
				'{itemRightBlockDisplay}' => "none",
				'{itemNewValueBlockDisplay}' => "none",
				'{itemCraftedBlock}' => "",
				'{itemTags}' => "",
				'{itemDataJson}' => "{}",
				'{itemSimilarBlock}' => "",
				'{itemEnchantId1}' => "",
				'{itemEnchantIntLevel1}' => "",
				'{itemEnchantIntType1}' => "",
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{itemLinkURL}' => "",
				'{viewSumDataExtraQuery}' => "",
				'{itemRawDataList}' => $this->MakeSetItemRawDataList(),
				'{rawItemVersion}' => $this->GetItemRawVersion(),
				'{extraDataLinkDisplay}' => "none",
				'{controlBlockDisplay}' => "none",
				'{similarItemBlockDisplay}' => "none",
				'{itemTypeTitle}' => "Set ",
				'{itemDescClass}' => "esoil_itemdescSet",
				'{itemDyeStampBlock}' => '',
		);
		
		$output = strtr($this->htmlTemplate, $replacePairs);
		
		print ($output);
	}
	
	private function OutputQuestItemHtml()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->questItemData['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->questItemData['name'])),
				'{itemDesc}' => $this->MakeQuestItemDescription(),
				'{itemLink}' => $this->escape($this->questItemData['itemLink']),
				'{itemStyle}' => "",
				'{itemId}' => $this->questItemId,
				'{itemType1}' => $this->escape($this->questItemData['header']),
				'{itemType2}' => "",
				'{itemStolen}' => "",
				'{itemBindType}' => "",
				'{itemValue}' => "",
				'{itemLevel}' => "",
				'{itemLevelRaw}' => "",
				'{itemQualityRaw}' => "",
				'{itemLevelBlock}' => "",
				'{itemQuality}' => "",
				'{iconLink}' => $this->MakeQuestItemIconImageLink(),
				'{itemLeftBlock}' => "",
				'{itemRightBlock}' => "",
				'{itemNewValueBlock}' => "",
				'{itemBar}' => "",
				'{itemBarClass}' => "esoilHidden",
				'{itemEnchantBlock}' => "",
				'{itemTraitBlock}' => "",
				'{itemSetBlock}' => "",
				'{itemAbilityBlock}' => "",
				'{itemTraitAbilityBlock}' => "",
				'{itemLeftBlockDisplay}' => "none",
				'{itemLevelBlockDisplay}' => "none",
				'{itemRightBlockDisplay}' => "none",
				'{itemNewValueBlockDisplay}' => "none",
				'{itemCraftedBlock}' => "",
				'{itemTags}' => "",
				'{itemDataJson}' => "{}",
				'{itemSimilarBlock}' => "",
				'{itemEnchantId1}' => "",
				'{itemEnchantIntLevel1}' => "",
				'{itemEnchantIntType1}' => "",
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{itemLinkURL}' => "",
				'{viewSumDataExtraQuery}' => "",
				'{itemRawDataList}' => $this->MakeQuestItemRawDataList(),
				'{rawItemVersion}' => $this->GetItemRawVersion(),
				'{extraDataLinkDisplay}' => "none",
				'{controlBlockDisplay}' => "none",
				'{similarItemBlockDisplay}' => "none",
				'{itemTypeTitle}' => "Quest ",
				'{itemDescClass}' => "esoil_itemdescQuest",
				'{itemDyeStampBlock}' => '',
		);
		
		$output = strtr($this->htmlTemplate, $replacePairs);
		
		print ($output);
	}
	
	
	private function MakeCollectibleItemName()
	{
		$name = $this->escape(strtoupper($this->collectibleItemData['name']));
		$nickname = $this->escape(strtoupper($this->collectibleItemData['nickname']));
		
		if ($nickname != "") $name .= "<div class='esoil_nickname'>\"".$nickname."\"</div>";
		
		return $name;
	}	
	
	
	private function MakeAntiquityItemName()
	{
		$name = $this->escape(strtoupper($this->antiquityItemData['name']));
		return $name;
	}
	
	
	private function OutputCollectibleItemHtml()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->collectibleItemData['name']),
				'{itemNameUpper}' => $this->MakeCollectibleItemName(),
				'{itemDesc}' => $this->MakeCollectibleItemDescription(),
				'{itemLink}' => $this->escape($this->collectibleItemData['itemLink']),
				'{itemStyle}' => "",
				'{itemId}' => $this->collectibleItemId,
				'{itemType1}' => $this->escape($this->collectibleItemData['categoryName']),
				'{itemType2}' => "",
				'{itemStolen}' => "",
				'{itemBindType}' => "",
				'{itemValue}' => "",
				'{itemLevel}' => "",
				'{itemLevelRaw}' => "",
				'{itemQualityRaw}' => "",
				'{itemLevelBlock}' => "",
				'{itemQuality}' => "",
				'{iconLink}' => $this->MakeCollectibleItemIconImageLink(),
				'{itemLeftBlock}' => "",
				'{itemRightBlock}' => "",
				'{itemNewValueBlock}' => "",
				'{itemBar}' => "",
				'{itemBarClass}' => "esoilHidden",
				'{itemEnchantBlock}' => "",
				'{itemTraitBlock}' => "",
				'{itemSetBlock}' => "",
				'{itemAbilityBlock}' => "",
				'{itemTraitAbilityBlock}' => "",
				'{itemLeftBlockDisplay}' => "none",
				'{itemLevelBlockDisplay}' => "none",
				'{itemRightBlockDisplay}' => "none",
				'{itemNewValueBlockDisplay}' => "none",
				'{itemCraftedBlock}' => "",
				'{itemTags}' => $this->MakeCollectibleItemTags(),
				'{itemDataJson}' => "{}",
				'{itemSimilarBlock}' => "",
				'{itemEnchantId1}' => "",
				'{itemEnchantIntLevel1}' => "",
				'{itemEnchantIntType1}' => "",
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{itemLinkURL}' => "",
				'{viewSumDataExtraQuery}' => "",
				'{itemRawDataList}' => $this->MakeCollectibleItemRawDataList(),
				'{rawItemVersion}' => $this->GetItemRawVersion(),
				'{extraDataLinkDisplay}' => "none",
				'{controlBlockDisplay}' => "none",
				'{similarItemBlockDisplay}' => "none",
				'{itemTypeTitle}' => "Collectible ",
				'{itemDescClass}' => "esoil_itemdescQuest",
				'{itemDyeStampBlock}' => '',
		);
	
		$output = strtr($this->htmlTemplate, $replacePairs);
	
		print ($output);
	}
	
	
	private function OutputAntiquityItemHtml()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->antiquityItemData['name']),
				'{itemNameUpper}' => $this->MakeAntiquityItemName(),
				'{itemDesc}' => $this->MakeAntiquityItemDescription(),
				'{itemLink}' => $this->escape($this->antiquityItemData['itemLink']),
				'{itemStyle}' => "",
				'{itemId}' => $this->antiquityItemId,
				'{itemType1}' => "Antiquity",
				'{itemType2}' => $this->escape($this->antiquityItemData['categoryName']),
				'{itemStolen}' => "",
				'{itemBindType}' => "",
				'{itemValue}' => "",
				'{itemLevel}' => "",
				'{itemLevelRaw}' => "",
				'{itemQualityRaw}' => $this->antiquityItemData['quality'],
				'{itemLevelBlock}' => "",
				'{itemQuality}' => GetEsoItemQualityText($this->antiquityItemData['quality']),
				'{iconLink}' => $this->MakeAntiquityItemIconImageLink(),
				'{itemLeftBlock}' => "",
				'{itemRightBlock}' => "",
				'{itemNewValueBlock}' => "",
				'{itemBar}' => "",
				'{itemBarClass}' => "esoilHidden",
				'{itemEnchantBlock}' => "",
				'{itemTraitBlock}' => "",
				'{itemSetBlock}' => $this->MakeAntiquitySetDescription(),
				'{itemAbilityBlock}' => "",
				'{itemTraitAbilityBlock}' => "",
				'{itemLeftBlockDisplay}' => "none",
				'{itemLevelBlockDisplay}' => "none",
				'{itemRightBlockDisplay}' => "none",
				'{itemNewValueBlockDisplay}' => "none",
				'{itemCraftedBlock}' => "",
				'{itemTags}' => "",
				'{itemDataJson}' => "{}",
				'{itemSimilarBlock}' => "",
				'{itemEnchantId1}' => "",
				'{itemEnchantIntLevel1}' => "",
				'{itemEnchantIntType1}' => "",
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{itemLinkURL}' => "",
				'{viewSumDataExtraQuery}' => "",
				'{itemRawDataList}' => $this->MakeAntiquityItemRawDataList(),
				'{rawItemVersion}' => $this->GetItemRawVersion(),
				'{extraDataLinkDisplay}' => "none",
				'{controlBlockDisplay}' => "none",
				'{similarItemBlockDisplay}' => "none",
				'{itemTypeTitle}' => "Antiquity ",
				'{itemDescClass}' => "esoil_itemdescQuest",
				'{itemDyeStampBlock}' => '',
		);
	
		$output = strtr($this->htmlTemplate, $replacePairs);
	
		print ($output);
	}
	
	
	public function GetSummaryDataExtraQuery()
	{
		$output = "";
		
		if ($this->version != "")
		{
			$output = "version=". urlencode($this->version) ."&";
		}
		
		return $output;
	}
	
	
	public function OutputRawData()
	{
		$this->CreateRawItemDataKeys();
		$this->CreateRawItemDataSortedIndexes();
		
		if ($this->outputType == "html") return $this->OutputRawDataHtml();
		
		print($this->GetRawItemDataCsv());
	}
	
	
	public function OutputSetItemRawData()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->setItemData['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->setItemData['name'])),
				'{itemId}' => $this->escape($this->itemSet),
				'{iconLink}' => $this->MakeSetItemIconImageLink(),
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{rawItemData}' => $this->GetRawQuestItemDataHtml(),
				'{itemDyeStampBlock}' => '',
		);
		
		$rawDataTemplate = file_get_contents(self::ESOIL_RAWDATA_HTML_TEMPLATE);
		
		$output = strtr($rawDataTemplate, $replacePairs);
		print ($output);
	}
	
	
	public function OutputQuestItemRawData()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->questItemData['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->questItemData['name'])),
				'{itemId}' => $this->questItemId,
				'{iconLink}' => $this->MakeQuestItemIconImageLink(),
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{rawItemData}' => $this->GetRawQuestItemDataHtml(),
				'{itemDyeStampBlock}' => '',
		);
		
		$rawDataTemplate = file_get_contents(self::ESOIL_RAWDATA_HTML_TEMPLATE);
		
		$output = strtr($rawDataTemplate, $replacePairs);
		print ($output);
	}
	
	
	public function OutputCollectibleItemRawData()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->collectibleItemData['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->collectibleItemData['name'])),
				'{itemId}' => $this->collectibleItemId,
				'{iconLink}' => $this->MakeCollectibleItemIconImageLink(),
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{rawItemData}' => $this->GetRawCollectibleItemDataHtml(),
				'{itemDyeStampBlock}' => '',
		);
		
		$rawDataTemplate = file_get_contents(self::ESOIL_RAWDATA_HTML_TEMPLATE);
		
		$output = strtr($rawDataTemplate, $replacePairs);
		print ($output);
	}
	
	
	public function OutputAntiquityItemRawData()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->antiquityItemData['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->antiquityItemData['name'])),
				'{itemId}' => $this->antiquityItemId,
				'{iconLink}' => $this->MakeAntiquityItemIconImageLink(),
				'{showSummary}' => "",
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{rawItemData}' => $this->GetRawAntiquityItemDataHtml(),
				'{itemDyeStampBlock}' => '',
		);
		
		$rawDataTemplate = file_get_contents(self::ESOIL_RAWDATA_HTML_TEMPLATE);
		
		$output = strtr($rawDataTemplate, $replacePairs);
		print ($output);
	}
	
	
	public function OutputRawDataHtml()
	{
		$replacePairs = array(
				'{itemName}' => $this->escape($this->itemRecord['name']),
				'{itemNameUpper}' => $this->escape(strtoupper($this->itemRecord['name'])),
				'{itemId}' => $this->itemRecord['itemId'],
				'{iconLink}' => $this->MakeItemIconImageLink(),
				'{showSummary}' => $this->showSummary ? 'summary' : '',
				'{version}' => $this->version,
				'{versionTitle}' => $this->GetVersionTitle(),
				'{itemLinkURL}' => $this->GetItemLinkURL(),
				'{rawItemData}' => $this->GetRawItemDataHtml(),
				'{itemDyeStampBlock}' => '',
		);		
		
		$rawDataTemplate = file_get_contents(self::ESOIL_RAWDATA_HTML_TEMPLATE);
		
		$output = strtr($rawDataTemplate, $replacePairs);
		print ($output);
	}
	
	
	public function GetRawItemDataHtml()
	{
		$output = "<table class='esoil_rawitemdata_table'>\n";
		$output .= $this->GetRawItemDataHeaderHtml();
		
		foreach ($this->rawDataSortedIndexes as $sortedIndex)
		{
			$index = $sortedIndex[2];
			$item = $this->itemAllData[$index];
			$output .= $this->CreateRawItemDataHtml($item);
		}
		
		$output .= "</table>\n";
		return $output;
	}
	
	
	public function GetRawQuestItemDataHtml()
	{
		$output  = "<table class='esoil_rawitemdata_table'>\n";
		$output .= "<tr>";
		
		foreach ($this->questItemData as $key => $value)
		{
			$output .= "<th>$key</th>\n";
		}
		
		$output .= "</tr>\n";
		
		foreach ($this->questItemData as $key => $value)
		{
			$value = $this->escape($value);
			$output .= "<tr>";
			$output .= "<td>$value</td>\n";
			$output .= "</tr>\n";
		}
	
		$output .= "</table>\n";
		return $output;
	}
	
	
	public function GetRawCollectibleItemDataHtml()
	{
		$output  = "<table class='esoil_rawitemdata_table'>\n";
		$output .= "<tr>";
		
		foreach ($this->collectibleItemData as $key => $value)
		{
			$output .= "<th>$key</th>\n";
		}
		
		$output .= "</tr>\n";
	
		foreach ($this->collectibleItemData as $key => $value)
		{
			$value = $this->escape($value);
			$output .= "<tr>";
			$output .= "<td>$value</td>\n";
			$output .= "</tr>\n";
		}
	
		$output .= "</table>\n";
		return $output;
	}
	
	
	public function GetRawAntiquityItemDataHtml()
	{
		$output  = "<table class='esoil_rawitemdata_table'>\n";
		$output .= "<tr>";
		
		foreach ($this->antiquityItemData as $key => $value)
		{
			$output .= "<th>$key</th>\n";
		}
		
		$output .= "</tr>\n";
		
		foreach ($this->antiquityItemData as $key => $value)
		{
			$value = $this->escape($value);
			$output .= "<tr>";
			$output .= "<td>$value</td>\n";
			$output .= "</tr>\n";
		}
		
		$output .= "</table>\n";
		return $output;
	}
	
	
	public function GetRawItemDataCsv()
	{
		$output = "";
		$output .= $this->GetRawItemDataHeaderCsv();
		
		foreach ($this->rawDataSortedIndexes as $sortedIndex)
		{
			$index = $sortedIndex[2];
			$item = $this->itemAllData[$index];
			$output .= $this->CreateRawItemDataCsv($item);
		}
		
		return $output;
	}
	
	
	public function CreateRawItemDataSortedIndexes()
	{
		$this->rawDataSortedIndexes = array();
		
		foreach ($this->itemAllData as $key => $item)
		{
			$level = $item['internalLevel'];
			$type = $item['internalSubtype'];
			
			if ($level == null) $level = $this->itemAllData[0]['internalLevel'];
			if ($type == null) $type = $this->itemAllData[0]['internalSubtype'];
			
			$this->rawDataSortedIndexes[] = array(
				0 => $level,
				1 => $type,
				2 => $key,
			);
		}
		
		usort($this->rawDataSortedIndexes, "compareRawDataSortedIndex");
	}
	
	
	public function CreateRawItemDataKeys()
	{
		$this->rawDataKeys = array();
		
		foreach ($this->itemAllData[0] as $key => $value)
		{
			if ($key == "id" || $key == "logId" || $key == "itemId" || $key == "link") continue;
			$this->rawDataKeys[] = $key;
		}
	}
	
	
	public function GetRawItemDataHeaderHtml()
	{
		$output = "<tr>";
		
		foreach ($this->rawDataKeys as $key)
		{
			$output .= "<th>$key</th>\n";
		}
		
		$output .= "</tr>\n";
		
		return $output;
	}
	
	
	public function GetRawItemDataHeaderCsv()
	{
		$output = "";
		
		foreach ($this->rawDataKeys as $key)
		{
			if ($output != "") $output .= ",";
			$output .= "\"$key\"";
		}
		
		$output .= "\n";
	
		return $output;
	}
	
	
	public function CreateRawItemDataHtml($item)
	{
		$output = "<tr>";
		
		foreach ($this->rawDataKeys as $key)
		{
			$value = $item[$key];
			if ($value == null) $value = $this->itemAllData[0][$key];
			$value = $this->escape($value);
			$output .= "<td>$value</td>\n";
		}
		
		$output .= "</tr>\n";
		
		return $output;
	}
	
	
	public function CreateRawItemDataCsv($item)
	{
		$output = "";
		
		foreach ($this->rawDataKeys as $key)
		{
			$value = $item[$key];
			if ($value == null) $value = $this->itemAllData[0][$key];
			$value = addslashes($value);
			$value = str_replace("\n", "\\n", $value);
			
			if ($output != "") $output .= ",";
			$output .= "\"$value\"";
		}
		
		$output .= "\n";
		
		return $output;
	}
	
	
	public function GetVersionTitle()
	{
		if ($this->GetTableSuffix() == "") return "";
		return " v" . $this->version . "";
	}
	
	
	public function DumpItem()
	{
		foreach ($this->itemRecord as $key => $value)
		{
			print("$key = $value\n");
		}
	}
	
	
	public function DumpQuestItem()
	{
		foreach ($this->questItemData as $key => $value)
		{
			print("$key = $value\n");
		}
	}
	
	
	public function DumpSetItem()
	{
		foreach ($this->setItemData as $key => $value)
		{
			print("$key = $value\n");
		}
	}
	
	
	public function DumpCollectibleItem()
	{
		foreach ($this->collectibleItemData as $key => $value)
		{
			print("$key = $value\n");
		}
	}
	
	
	public function DumpAntiquityItem()
	{
		foreach ($this->antiquityItemData as $key => $value)
		{
			print("$key = $value\n");
		}
	}
	
	
	public function ShowSetItem()
	{
		if (!$this->LoadSetItemRecord()) $this->LoadSetItemErrorData();
		
		if ($this->outputRaw)
			$this->OutputSetItemRawData();
		else if ($this->outputType == "html")
			$this->OutputSetItemHtml();
		elseif ($this->outputType == "text")
			$this->DumpSetItem();
		else
			return $this->ReportError("Error: Unknown output type '{$this->outputType}' specified!");
		
		return true;
	}
	
	
	public function ShowQuestItem()
	{
		if (!$this->LoadQuestItemRecord()) $this->LoadQuestItemErrorData();
		
		if ($this->outputRaw)
			$this->OutputQuestItemRawData();
		else if ($this->outputType == "html")
			$this->OutputQuestItemHtml();
		elseif ($this->outputType == "text")
			$this->DumpQuestItem();
		else
			return $this->ReportError("Error: Unknown output type '{$this->outputType}' specified!");
		
		return true;
	}
	
	
	public function ShowCollectibleItem()
	{
		if (!$this->LoadCollectibleItemRecord()) $this->LoadCollectibleItemErrorData();
		
		if ($this->outputRaw)
			$this->OutputCollectibleItemRawData();
		else if ($this->outputType == "html")
			$this->OutputCollectibleItemHtml();
		elseif ($this->outputType == "text")
			$this->DumpCollectibleItem();
		else
			return $this->ReportError("Error: Unknown output type '{$this->outputType}' specified!");
		
		return true;
	}
	
	
	public function ShowAntiquityItem()
	{
		if (!$this->LoadAntiquityItemRecord()) $this->LoadAntiquityItemErrorData();
		
		if ($this->outputRaw)
			$this->OutputAntiquityItemRawData();
		else if ($this->outputType == "html")
			$this->OutputAntiquityItemHtml();
		elseif ($this->outputType == "text")
			$this->DumpAntiquityItem();
		else
			return $this->ReportError("Error: Unknown output type '{$this->outputType}' specified!");
		
		return true;
	}
	
	
	public function ShowItem()
	{
		$this->OutputHtmlHeader();
		
		if (!CanViewEsoLogVersion($this->version))
		{
			$this->LoadItemErrorData();
			$this->itemRecord['name'] = "Permission Denied!";
			
			if ($this->outputRaw)
				$this->OutputRawData();
			else if ($this->outputType == "html")
				$this->OutputHtml();
			elseif ($this->outputType == "text")
				$this->DumpItem();
			else
				print("Permission Denied!");
			
			return;
		}
		
		if ($this->questItemId > 0) return $this->ShowQuestItem();
		if ($this->collectibleItemId > 0) return $this->ShowCollectibleItem();
		if ($this->antiquityItemId > 0) return $this->ShowAntiquityItem();
		if ($this->itemSet != "") return $this->ShowSetItem();
		
		if ($this->version != "" && $this->version < GetEsoUpdateVersion()) $this->showSummary = true;
		
		if ($this->showSummary)
		{
			if ($this->version >= GetEsoUpdateVersion())
			{
				if (!$this->LoadItemRecord()) $this->LoadItemErrorData();
			}
			
			$this->LoadEnchantRecords();
			if (!$this->LoadItemSummaryData()) $this->LoadItemErrorData();
			
			if ($this->version >= GetEsoUpdateVersion())
				$this->MergeItemSummary();
			else
				$this->MergeItemSummaryAll();
		}
		else
		{
			if (!$this->LoadItemRecord()) $this->LoadItemErrorData();
			$this->LoadEnchantRecords();
		}
		
		if (!$this->embedLink)
		{
			$this->LoadAllItemData();
			$this->LoadSimilarItemRecords();
		}
		
		if ($this->outputRaw)
			$this->OutputRawData();
		else if ($this->outputType == "html")
			$this->OutputHtml();
		elseif ($this->outputType == "text")
			$this->DumpItem();
		else
			$this->ReportError("Error: Unknown output type '{$this->outputType}' specified!");
		
		return true;
	}
	
};

$g_EsoItemLink = new CEsoItemLink();
$g_EsoItemLink->ShowItem();

