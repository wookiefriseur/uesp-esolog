<?php

require_once("/home/uesp/secrets/esolog.secrets");
require_once("esoCommon.php");


class CEsoLogGetSkillData
{

	public $db = null;

	public $version = "";
	public $inputType = "";

	public $outputData = array();
	public $outputJson = "";


	public function __construct()
	{
		$this->SetInputParams();
		$this->ParseInputParams();
		$this->InitDatabase();
	}


	public function ReportError($errorMsg, $statusCode = 0)
	{
		error_log($errorMsg);

		if ($this->outputData['error'] == null) $this->outputData['error'] = array();
		$this->outputData['error'][] = $errorMsg;

		if ($statusCode > 0) header("X-PHP-Response-Code: " . $statusCode, true, $statusCode);

		return false;
	}
	
	
	private function InitDatabase()
	{
		global $uespEsoLogReadDBHost, $uespEsoLogReadUser, $uespEsoLogReadPW, $uespEsoLogDatabase;
		
		$this->db = new mysqli($uespEsoLogReadDBHost, $uespEsoLogReadUser, $uespEsoLogReadPW, $uespEsoLogDatabase);
		if ($this->db->connect_error) return $this->ReportError("ERROR: Could not connect to mysql database!", 500);
		
		return true;
	}
	
	
	private function GetTableSuffix()
	{
		return GetEsoItemTableSuffix($this->version);
	}
	
	
	private function ParseInputParams ()
	{
		if (array_key_exists('version', $this->inputParams)) $this->version = urldecode($this->inputParams['version']);
		if (array_key_exists('type', $this->inputParams)) $this->inputType = strtolower(urldecode($this->inputParams['type']));
		return true;
	}
	
	
	private function SetInputParams ()
	{
		global $_REQUEST;
		
		$this->inputParams = $_REQUEST;
	}
	
	
	private function OutputHeader()
	{
		ob_start("ob_gzhandler");

		header("Expires: 0");
		header("Pragma: no-cache");
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN'] . "");
		header("content-type: application/json");
	}
	
	
	private function LoadAllSkillData()
	{
		$minedSkillTable = "minedSkills" . $this->GetTableSuffix();
		$skillTreeTable  = "skillTree" . $this->GetTableSuffix();
		
		$query = "SELECT $minedSkillTable.*, $skillTreeTable.* FROM $skillTreeTable LEFT JOIN $minedSkillTable ON abilityId=$minedSkillTable.id;";
		
		$result = $this->db->query($query);
		if (!$result) return $this->ReportError("Database query error trying to load all skill data!");
		
		while (($row = $result->fetch_assoc()))
		{
			$id = $row['abilityId'];
			$this->outputData[$id] = $row;
			++$numRecords;
		}
		
		$this->outputData['numRecords'] += $numRecords;
		
		return true;
	}
	
	
	private function GetDeltiasJsonData()
	{
		$data = [];
		
		foreach ($this->outputData as $id => $skillData)
		{
			$id = intval($id);
			if ($id <= 0) continue;
			
			$newData = [];
			$newData['id'] = $id;
			$newData['name'] = $skillData['name'];
			$newData['skillTypeName'] = $skillData['skillTypeName'];
			$newData['baseName'] = $skillData['baseName'];
			$newData['type'] = ($skillData['type'] == "Active") ? ($skillData['isPassive'] == 1 ? "Passive" : "Active") : $skillData['type'];
			
			$data[$id] = $newData;
		}
		
		$data['numRecords'] = $this->outputData['numRecords'];
		
		return json_encode($data); 
	}
	
	
	private function GetJsonData()
	{
		if ($this->inputType == "deltias")
			$json = $this->GetDeltiasJsonData();
		else
			$json = json_encode($this->outputData);
		
		return $json;
	}
	
	
	public function Export()
	{
		$this->OutputHeader();
		$this->LoadAllSkillData();
		
		$this->outputJson = $this->GetJsonData();
		print($this->outputJson);
	}

};


$g_ExportData = new CEsoLogGetSkillData();
$g_ExportData->Export();


