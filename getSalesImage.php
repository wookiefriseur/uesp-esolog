<?php 

require_once("/home/uesp/secrets/esosalesdata.secrets");
require_once("/home/uesp/secrets/esolog.secrets");
require_once(__DIR__."/esoCommon.php");
require_once(__DIR__."/esoPotionData.php");


class EsoGetSalesImage
{
	const REGULARFONT_FILE = "./resources/esofontregular-webfont.ttf";
	const BOLDFONT_FILE = "./resources/esofontbold-webfont.ttf";
	
	//const REGULARFONT_FILE = "./resources/arial.ttf";
	//const BOLDFONT_FILE = "./resources/arialbd.ttf";
	
	const ALIGN_LEFT = 0;
	const ALIGN_RIGHT = 1;
	const ALIGN_CENTER = 2;
	const ALIGN_TOP = 3;
	const ALIGN_BOTTOM = 5;
	
	const BORDER_LEFT_MARGIN = 60;
	const BORDER_RIGHT_MARGIN = 7;
	const BORDER_TOP_MARGIN = 7;
	const BORDER_BOTTOM_MARGIN = 40;
	const TICK_LENGTH = 5;
	const TICK_FONT_SIZE = 10;
		
	const MIN_WEIGHTED_AVERAGE_INTERVAL = 11;
	const WEIGHTED_AVERAGE_BUCKETS = 20;
	const WEIGHTED_SMOOTH_INTERVAL = 21;
	
	const MAX_ZSCORE = 2;
	
	public $db = null;
	public $dbReadInitialized = false;
	public $dbWriteInitialized = false;
	public $lastQuery = "";
	
	public $inputParams = array();
	public $itemId = 0;
	public $outputWidth = 1024;
	public $outputHeight = 600;
	public $viewData = "all";
	public $timePeriod = 0;
	public $showTrends = true;
	
	public $salesData = array();
	public $soldData = array();
	public $listData = array();
	public $avgData = array();
	public $avgSoldData = array();
	public $avgListData = array();
	public $minPrice = 0;
	public $maxPrice = 100;
	public $minTime = 0;
	public $maxTime = 1;
	public $minPriceLimit = 0;
	public $maxPriceLimit = 100;
	
	public $soldAvgPrice = 0;
	public $listAvgPrice = 0;
	public $totalAvgPrice = 0;
	public $totalSoldCount = 0;
	public $totalListCount = 0;
	public $totalItemCount = 0;
	
	public $trendsData = array();
	
	public $image = null;
	
	
	public function __construct ()
	{
		$this->minTime = time() - 86400*10;
		$this->maxTime = time();
		
		$this->SetInputParams();
		$this->ParseInputParams();
		$this->InitDatabaseRead();
	}
	
	
	public function ReportError($errorMsg)
	{
		//print($errorMsg);
		error_log($errorMsg);
		return false;
	}
	
	
	private function ParseInputParams ()
	{
		if (array_key_exists('id', $this->inputParams))
		{
			$this->itemId = intval($this->inputParams['id']);
		}
		
		if (array_key_exists('width', $this->inputParams))
		{
			$this->outputWidth = intval($this->inputParams['width']);
			if ($this->outputWidth < 100) $this->outputWidth = 100;
		}
		
		if (array_key_exists('height', $this->inputParams))
		{
			$this->outputHeight = intval($this->inputParams['height']);
			if ($this->outputHeight < 50) $this->outputWidth = 50;
		}
		
		if (array_key_exists('view', $this->inputParams))
		{
			$view = strtolower($this->inputParams['view']);
			if ($view == "list" || $view == "sold" || $view == "both" || $view == "all") $this->viewData = $view;
			if ($this->viewData == "both") $this->viewData = "all";
		}
		
		if (array_key_exists('timeperiod', $this->inputParams))
		{
			$this->timePeriod = intval($this->inputParams['timeperiod']);
			if ($this->timePeriod < 0) $this->timePeriod = 0;
		}
		
		if (array_key_exists('trends', $this->inputParams))
		{
			$this->showTrends = intval($this->inputParams['trends']);
		}
		
		return true;
	}
	
	
	private function SetInputParams ()
	{
		global $argv;
		$this->inputParams = $_REQUEST;
	}
	
	
	private function InitDatabaseRead ()
	{
		global $uespEsoSalesDataReadDBHost, $uespEsoSalesDataReadUser, $uespEsoSalesDataReadPW, $uespEsoSalesDataDatabase;

		if ($this->dbReadInitialized) return true;

		$this->db = new mysqli($uespEsoSalesDataReadDBHost, $uespEsoSalesDataReadUser, $uespEsoSalesDataReadPW, $uespEsoSalesDataDatabase);
		if ($this->db->connect_error) return $this->ReportError("Could not connect to mysql database!");

		$this->dbReadInitialized = true;
		$this->dbWriteInitialized = false;
		
		return true;
	}
	
	
	public function LoadSalesData()
	{
		if ($this->itemId <= 0) return $this->ReportError("No itemId specified for sales data!");
		
		$this->lastQuery = "SELECT * FROM sales WHERE itemId='{$this->itemId}';";
		
		if ($this->timePeriod > 0)
		{
			$timestamp = time() - $this->timePeriod;
			$this->lastQuery = "SELECT * FROM sales WHERE itemId='{$this->itemId}' AND timestamp>=$timestamp;";
		}
		
		$result = $this->db->query($this->lastQuery);
		if ($result === false) return $this->ReportError("Failed to load sales data records!");
		if ($result->num_rows == 0) return $this->ReportError("No sales data found for itemId {$this->itemId}!");
		
		$this->salesData = array();
		$this->soldData  = array();
		$this->listData  = array();
		$currentTime = time();
		
		while (($row = $result->fetch_assoc()))
		{
			$row['unitPrice'] = $row['price'] / $row['qnt'];
			if ($row['buyTimestamp']  > 0) $row['timestamp'] = $row['buyTimestamp'];
			if ($row['listTimestamp'] > 0) $row['timestamp'] = $row['listTimestamp'];
			
			if ($this->timePeriod > 0)
			{
				if ($currentTime - $row['timestamp'] > $this->timePeriod) continue;
			}
			
			$this->salesData[] = $row;
			
			if ($row['buyTimestamp']  > 0) $this->soldData[] = $row;
			if ($row['listTimestamp'] > 0) $this->listData[] = $row;
		}
		
		return true;
	}
	
	
	public function LoadTrends()
	{
		$this->lastQuery = "SELECT * FROM trends WHERE itemId='{$this->itemId}' ORDER BY timestamp;";
		$result = $this->db->query($this->lastQuery);
		if ($result === false) return $this->ReportError("Failed to load item '{$this->itemId}' trends data!");
		
		$timestamp = time() - $this->timePeriod;
		
		while (($row = $result->fetch_assoc()))
		{
			if ($row['timestamp'] < $timestamp) continue;
			
			$row['bothCount'] = intval($row['sellCount']) + intval($row['buyCount']);
			$row['bothItemCount'] = intval($row['sellItemCount']) + intval($row['buyItemCount']);
			
			$this->trendsData[] = $row;
		}
		
		return true;
	}
	
	
	public function SalesDataSortTimestamp($a, $b)
	{
		return $a['timestamp'] - $b['timestamp'];
	}
	
	
	public function SalesDataSortSoldTimestamp($a, $b)
	{
		return $a['buyTimestamp'] - $b['buyTimestamp'];
	}
	
	
	public function SalesDataSortListTimestamp($a, $b)
	{
		return $a['listTimestamp'] - $b['listTimestamp'];
	}
	
	
	public function ComputeWeightedAverages()
	{
		$this->soldData = array();
		$this->listData = array();
		$this->validSalesData = array();
		
		$numPoints = 0;
		
		foreach ($this->salesData as $sale)
		{
			if (array_key_exists('outlier', $sale) && $sale['outlier'] === true) continue;
			
			++$numPoints;
			$this->validSalesData[] = $sale;
			if ($sale['listTimestamp'] > 0) $this->listData[] = $sale;
			if ($sale['buyTimestamp'] > 0) $this->soldData[] = $sale;
		}
		
		if ($numPoints == 0)
		{
			foreach ($this->salesData as $sale)
			{
				$this->validSalesData[] = $sale;
				if ($sale['listTimestamp'] > 0) $this->listData[] = $sale;
				if ($sale['buyTimestamp'] > 0) $this->soldData[] = $sale;
			}
		}
		
		usort($this->validSalesData, array('EsoGetSalesImage','SalesDataSortTimestamp'));
		usort($this->soldData, array('EsoGetSalesImage','SalesDataSortSoldTimestamp'));
		usort($this->listData, array('EsoGetSalesImage','SalesDataSortListTimestamp'));
		
		$this->avgData = $this->ComputeWeightedAverageData($this->validSalesData);
		$this->avgSoldData = $this->ComputeWeightedAverageData($this->soldData);
		$this->avgListData = $this->ComputeWeightedAverageData($this->listData);
		
		return true;
	}
	
	
	public function ComputeWeightedAverageData($dataArray)
	{
		$weighted = array();
		$count = 0;
		$sum = 0;
		$lastTimestamp = 0;
		
		$numPoints = intval(count($dataArray) / self::WEIGHTED_AVERAGE_BUCKETS);
		if ($numPoints < self::MIN_WEIGHTED_AVERAGE_INTERVAL) $numPoints = self::MIN_WEIGHTED_AVERAGE_INTERVAL;
		
		$numDataPoints = count($dataArray);
		$endTimestamp = 0;
		$endCount = 0;
		
		for ($i = 0; $i < $numDataPoints; ++$i)
		{
			$data = $dataArray[$i];
			$unitPrice = $data['unitPrice'];
			$lastTimestamp = $data['timestamp'];
			
			if ($count < $numPoints)
			{
				++$count;
				$sum += $unitPrice;
				$endCount = $count;
				$endTimestamp = $data['timestamp'];
				$timestamp = ($data['timestamp'] + $dataArray[$i - $count + 1]['timestamp'])/2;
				$weighted[$timestamp] = $sum / $count;
			}
			else
			{
				$endCount = $numPoints;
				$timestamp = ($data['timestamp'] + $dataArray[$i - $numPoints]['timestamp'])/2;
				$weighted[$timestamp] = $sum / $numPoints;
				$endTimestamp = $data['timestamp'];
			
				$sum += $unitPrice;
				$sum -= $dataArray[$i - $numPoints]['unitPrice'];
			}
			
		}
		
		if ($count > 0) {
			$weighted[$lastTimestamp] = $sum / $count;
			$weighted[time()] = $sum / $count;
		}
		
		$this->SmoothWeightedArray($weighted);

		return $weighted;
	}	
	
		
	public function SmoothWeightedArray(&$weighted)
	{
		$orig = $weighted;
		$numPoints = self::WEIGHTED_SMOOTH_INTERVAL;
		$count = 0;
		$sum = 0;
		$savePrices = array_fill(0, $numPoints, 0);
		$saveIndex = 0;
		$lastPrice = 0;
		$lastTimestamp = 0;
				
		foreach ($orig as $timestamp => $price)
		{
			$lastPrice = $price;
			$lastTimestamp = $timestamp;
			
			if ($count < $numPoints)
			{
				$savePrices[$saveIndex] = $price;
				++$saveIndex;
				if ($saveIndex >= $numPoints) $saveIndex = 0;
				
				++$count;
				$sum += $price;
				$weighted[$timestamp] = $sum / $count;
			}
			else
			{
				$sum += $price;
				$sum -= $savePrices[$saveIndex];
				
				$weighted[$timestamp] = $sum / $count;
				
				$savePrices[$saveIndex] = $price;
				++$saveIndex;
				if ($saveIndex >= $numPoints) $saveIndex = 0;
			}
		}
		
		$weighted[$lastTimestamp] = $lastPrice;
	}
	
	
	public function ComputeBasicTrendStats()
	{
		foreach ($this->trendsData as $trend)
		{
			if ($trend['timestamp'] < $this->minTime) $this->minTime = intval($trend['timestamp']);
			if ($trend['bothLow']  < $this->minPrice) $this->minPrice = floatval($trend['bothLow']);
			if ($trend['bothHigh'] > $this->maxPrice) $this->maxPrice = floatval($trend['bothHigh']);
			
			if ($trend['sellCount'] > 0)
			{
				if ($trend['sellLow']  < $this->minPrice) $this->minPrice = floatval($trend['sellLow']);
				if ($trend['sellHigh'] > $this->maxPrice) $this->maxPrice = floatval($trend['sellHigh']);
			}
			
			if ($trend['buyCount'] > 0)
			{
				if ($trend['buyLow']  < $this->minPrice) $this->minPrice = floatval($trend['buyLow']);
				if ($trend['buyHigh'] > $this->maxPrice) $this->maxPrice = floatval($trend['buyHigh']);
			}
		}
		
	}
	
	
	public function ComputeBasicStats()
	{
		if (count($this->salesData) < 1) return false;
		
		$listSum = 0;
		$soldSum = 0;
		$listCount = 0;
		$soldCount = 0;
		$minPrice = 1000000000;
		$maxPrice = -1;
		$minTime = time();
		$maxTime = 0;
		
		foreach ($this->salesData as $sale)
		{
			$price = intval($sale['price']);
			$qnt = intval($sale['qnt']);
			$unitPrice = $sale['unitPrice'];
			$soldTime = intval($sale['buyTimestamp']);
			$listTime = intval($sale['listTimestamp']);
			
			if ($soldTime > 0)
			{
				if ($this->viewData == "list") continue;
				
				$soldSum += $price;
				$soldCount += $qnt;
				
				if ($minPrice > $unitPrice) $minPrice = $unitPrice;
				if ($maxPrice < $unitPrice) $maxPrice = $unitPrice;
				if ($minTime > $soldTime) $minTime = $soldTime;
				if ($maxTime < $soldTime) $maxTime = $soldTime;
			}
			
			if ($listTime > 0)
			{
				if ($this->viewData == "sold") continue;
				
				$listSum += $price;
				$listCount += $qnt;
				
				if ($minPrice > $unitPrice) $minPrice = $unitPrice;
				if ($maxPrice < $unitPrice) $maxPrice = $unitPrice;
				if ($minTime > $listTime) $minTime = $listTime;
				if ($maxTime < $listTime) $maxTime = $listTime;
			}
		}
		
		
		$this->minTime = $minTime;
		$this->maxTime = time();
		$this->maxTimeAction = $maxTime;
		$this->minPrice = $minPrice;
		$this->maxPrice = $maxPrice;
		
		if ($this->showTrends) $this->ComputeBasicTrendStats();
		
		if ($this->minTime == $this->maxTime)
		{
			$this->minTime -= 3600;
		}
		
		$this->soldAvgPrice = 0;
		$this->listAvgPrice = 0;
		$this->totalAvgPrice = 0;

		if ($soldCount > 0) $this->soldAvgPrice = $soldSum / $soldCount;
		$this->soldItemCount = $soldCount;
		if ($listCount > 0) $this->listAvgPrice = $listSum / $listCount;
		$this->listItemCount = $listCount;
		
		$this->totalItemCount = $soldCount + $listCount;
		if ($this->totalItemCount > 0) $this->totalAvgPrice = ($soldSum + $listSum) / $this->totalItemCount;
		
		if ($this->minPrice == $this->maxPrice)
		{
			$this->minPrice = $this->maxPrice*0.9 - 1;
			$this->maxPrice = $this->maxPrice*1.1 + 1;
		}
		
		$this->minPriceLimit = $this->minPrice;
		$this->maxPriceLimit = $this->maxPrice;
		
		return true;
	}
	
	
	public function ComputeAdvancedStatistics()
	{
		$sumSquareAll = 0;
		$sumSquareListed = 0;
		$sumSquareSold = 0;
		
		foreach ($this->salesData as $sale)
		{
			$price = intval($sale['price']);
			$qnt = intval($sale['qnt']);
			$unitPrice = $sale['unitPrice'];
				
			$sumSquareAll += pow($unitPrice - $this->totalAvgPrice, 2);
				
			if ($sale['buyTimestamp']  > 0)
			{
				$sumSquareSold += pow($unitPrice - $this->soldAvgPrice, 2);
			}
				
			if ($sale['listTimestamp'] > 0)
			{
				$sumSquareListed += pow($unitPrice - $this->listAvgPrice, 2);
			}
		}
		
		$this->totalPriceStdDev = 0;
		$this->soldPriceStdDev = 0;
		$this->listedPriceStdDev = 0;
		
		if ($this->totalItemCount > 0)
		{
			$this->totalPriceStdDev = sqrt($sumSquareAll / floatval($this->totalItemCount));
		}
		
		if ($this->soldItemCount > 0)
		{
			$this->soldPriceStdDev = sqrt($sumSquareSold / floatval($this->soldItemCount));
		}
		
		if ($this->listItemCount > 0)
		{
			$this->listedPriceStdDev = sqrt($sumSquareListed / floatval($this->listItemCount));
		}
		
		return true;
	}
	
	
	public function RecalculatePriceLimits()
	{
		if (count($this->salesData) <= 0) return false;
		if ($this->totalPriceStdDev == 0) return false;
		
		$minPrice = 1000000000;
		$maxPrice = -1;
		$numPoints = 0;
			
		foreach ($this->salesData as $i => $sale)
		{
			$unitPrice = $sale['unitPrice'];
			
			if ($sale['buyTimestamp']  > 0 && $this->viewData == "list") continue;
			if ($sale['listTimestamp'] > 0 && $this->viewData == "sold") continue;
			
			$zScoreAll = abs(($unitPrice - $this->totalAvgPrice) / $this->totalPriceStdDev);
			$zScoreSold = 1;
			$zScoreListed = 1;
			$isOK = true;
			
			if ($zScoreAll > self::MAX_ZSCORE) $isOK = false;
						
			if ($sale['buyTimestamp'] > 0 && $this->soldPriceStdDev != 0)
			{
				$zScoreSold = abs(($unitPrice - $this->soldAvgPrice) / $this->soldPriceStdDev);
				if ($zScoreSold > self::MAX_ZSCORE) $isOK = false;
			}
			
			if ($sale['listTimestamp'] > 0 && $this->listedPriceStdDev != 0)
			{
				$zScoreListed = abs(($unitPrice - $this->listAvgPrice) / $this->listedPriceStdDev);
				if ($zScoreListed > self::MAX_ZSCORE) $isOK = false;
			}
			
			if ($isOK)
			{
				if ($minPrice > $unitPrice) $minPrice = $unitPrice;
				if ($maxPrice < $unitPrice) $maxPrice = $unitPrice;
				++$numPoints;
			}
			else
			{
				$this->salesData[$i]['outlier'] = true;
			}
			
		}
		
		if ($numPoints <= 0) return false;
		
		$this->minPriceLimit = $minPrice;
		$this->maxPriceLimit = $maxPrice;
		
		if ($this->minPriceLimit == $this->maxPriceLimit)
		{
			$this->minPriceLimit = $this->maxPriceLimit * 0.9 - 1;
			$this->maxPriceLimit = $this->maxPriceLimit * 1.1 + 1;
		}
			
		return true;
	}
	
	
	public function OutputHtmlHeader()
	{
		ob_start("ob_gzhandler");
		header("Expires: 0");
		header("Pragma: no-cache");
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("content-type: image/png");
		header("Access-Control-Allow-Origin: *");
	}
	
	
	public function ConvertGraphToPixelX($x)
	{
		$graphRange = $this->maxTime - $this->minTime;
		if ($graphRange == 0) $graphRange = 3600;
		$pixelRange = $this->outputWidth - self::BORDER_LEFT_MARGIN - self::BORDER_RIGHT_MARGIN;
		
		return intval(($x - $this->minTime) / $graphRange * $pixelRange) + self::BORDER_LEFT_MARGIN;
	}
	
	
	public function ConvertGraphToPixelY($y)
	{
		$graphRange = $this->maxPriceLimit - $this->minPriceLimit;
		if ($graphRange == 0) $graphRange = 1;
		$pixelRange = $this->outputHeight - self::BORDER_TOP_MARGIN - self::BORDER_BOTTOM_MARGIN;
	
		return intval(($this->maxPriceLimit - $y) / $graphRange * $pixelRange) + self::BORDER_TOP_MARGIN;
	}
	
		
	public function CreateImageBorders(&$image)
	{
		$x1 = self::BORDER_LEFT_MARGIN;
		$y1 = self::BORDER_TOP_MARGIN;
		$x2 = $this->outputWidth - self::BORDER_RIGHT_MARGIN;
		$y2 = $this->outputHeight - self::BORDER_BOTTOM_MARGIN;
		
		imagerectangle($image, $x1, $y1, $x2, $y2, $this->borderColor);
		
		$label = "Hours Ago";
		if ($this->ShowTimeAsDays()) $label = "Days Ago";
		
		$x = ($x2 + $x1) / 2;
		$y = $this->outputHeight - 7;
		$this->PrintText($image, $label, self::TICK_FONT_SIZE + 1, $this->borderColor, $x, $y, self::ALIGN_CENTER, self::ALIGN_BOTTOM);
		
		$x = 15;
		$y = ($y1 + $y2) / 2;		
		$this->PrintText($image, "Price", self::TICK_FONT_SIZE + 1, $this->borderColor, $x, $y, self::ALIGN_LEFT, self::ALIGN_CENTER, self::REGULARFONT_FILE, 90);

		$goldImage = imagecreatefrompng("./resources/currency_gold_32.png");
			
		if ($goldImage != null)
		{
			imageantialias($goldImage, true);
			imagealphablending($goldImage, true);
			imagesavealpha($goldImage, true);
			$y -= 50;
			$x -= 12;
			imagecopyresampled($image, $goldImage, $x, $y, 0, 0, 16, 16, imagesx($goldImage), imagesy($goldImage));
		}
		
		$x = self::BORDER_LEFT_MARGIN + 2;
		$y = $this->outputHeight - self::BORDER_BOTTOM_MARGIN - 2;
		
		if ($this->viewData != "list") 
		{
			$count = count($this->soldData);
			$this->PrintText($image, "Sales ($count)", self::TICK_FONT_SIZE, $this->saleColor, $x, $y, self::ALIGN_LEFT, self::ALIGN_BOTTOM);
			$y -= 15;
		}
		
		if ($this->viewData != "sold") 
		{
			$count = count($this->listData);
			$this->PrintText($image, "Listed ($count)", self::TICK_FONT_SIZE, $this->listColor, $x, $y, self::ALIGN_LEFT, self::ALIGN_BOTTOM);
		}
	}
	
	
	public function CreateImageTicks(&$image)
	{
		$this->CreateImageTicksX($image);	
		$this->CreateImageTicksY($image);
	}
	
	
	public function FormatTimestampValue($timestamp, $factor = 86400)
	{
		$days = intval(($this->maxTime - $timestamp) / $factor);
		return $days;
	}
	
	
	public function FormatPriceValue($price)
	{
		if ($price >= 10000000)
			$price = intval($price/100000) . "M";
		else if ($price >= 10000)
			$price = intval($price/1000) . "k";
		else if ($price >= 100)
			$price = round($price);
		else if ($price >= 10)
			$price = round($price, 1);
		else
			$price = round($price, 2);
		
		return $price;
	}

	
	public function ShowTimeAsDays()
	{
		$diffTime = $this->maxTime - $this->minTime;
		return ($diffTime >= 100000);
	}
	
	
	public function CreateImageTicksX(&$image)
	{
		$y1 = $this->outputHeight - self::BORDER_BOTTOM_MARGIN;
		$y2 = $y1 + self::TICK_LENGTH;
		
		$timeFactor = 86400;
		if (!$this->ShowTimeAsDays()) $timeFactor /= 24;
		
		$tickRange = $this->GetNiceTickRange($this->minTime, $this->maxTime, $timeFactor);
		$startValue = $this->minTime + $tickRange - fmod($this->minTime, $tickRange);
		
		for ($value = $this->maxTime; $value >= $this->minTime; $value -= $tickRange)
		{
			$days = $this->FormatTimestampValue($value, $timeFactor);
			$x = $this->ConvertGraphToPixelX($value);
			imageline($image, $x, $y1, $x, $y2, $this->borderColor);

			$this->PrintText($image, $days, self::TICK_FONT_SIZE, $this->borderColor, $x, $y2 + 4, self::ALIGN_CENTER, self::ALIGN_TOP);
		}
		
	}
	
	
	public function CreateImageTicksY(&$image)
	{
		$x2 = self::BORDER_LEFT_MARGIN;
		$x1 = $x2 - self::TICK_LENGTH;
		$tickRange = $this->GetNiceTickRange($this->minPriceLimit, $this->maxPriceLimit);
		$startValue = $this->minPriceLimit + $tickRange - fmod($this->minPriceLimit, $tickRange);
		
		for ($value = $startValue; $value < $this->maxPriceLimit; $value += $tickRange)
		{
			$y = $this->ConvertGraphToPixelY($value);
			imageline($image, $x1, $y, $x2, $y, $this->borderColor);
			$price = $this->FormatPriceValue($value);
			
			$this->PrintText($image, $price, self::TICK_FONT_SIZE, $this->borderColor, $x1 - 4, $y, self::ALIGN_RIGHT, self::ALIGN_CENTER);
			
			if ($value == $startValue)
			{
				$y1 = $this->ConvertGraphToPixelY($this->minPriceLimit);
				
				if ($y1 - $y > 40)
				{
					imageline($image, $x1, $y1, $x2, $y1, $this->borderColor);
					$price = $this->FormatPriceValue($this->minPriceLimit);
					$this->PrintText($image, $price, self::TICK_FONT_SIZE, $this->borderColor, $x1 - 4, $y1, self::ALIGN_RIGHT, self::ALIGN_CENTER);
				}
			}
			
		}
				
		$y1 = $this->ConvertGraphToPixelY($this->maxPriceLimit);
		
		if ($y - $y1 > 40)
		{
			imageline($image, $x1, $y1, $x2, $y1, $this->borderColor);
			$price = $this->FormatPriceValue($this->maxPriceLimit);
			$this->PrintText($image, $price, self::TICK_FONT_SIZE, $this->borderColor, $x1 - 4, $y1, self::ALIGN_RIGHT, self::ALIGN_CENTER);
		}
	}
	
	
	public function CreateImageSalesData(&$image)
	{
		foreach ($this->salesData as $sale)
		{
			if (array_key_exists('outlier', $sale) && $sale['outlier'] === true) continue;
			
			$price = intval($sale['price']);
			$qnt = intval($sale['qnt']);
			$unitPrice = $sale['unitPrice'];
			$soldTime = intval($sale['buyTimestamp']);
			$listTime = intval($sale['listTimestamp']);
			
			$color = $this->saleColor;
			$time = $soldTime;
			
			if ($soldTime > 0 && $this->viewData == "list") continue;
			if ($listTime > 0 && $this->viewData == "sold") continue;
			
			if ($listTime > 0) 
			{
				$color = $this->listColor;
				$time = $listTime;
			}
			
			$x = $this->ConvertGraphToPixelX($time);
			$y = $this->ConvertGraphToPixelY($unitPrice);
			
			imagefilledellipse($image, $x, $y, 2, 2, $color);
		}
	}
	
	
	public function GetNiceTickRange(&$min, &$max, $nearestMultiple = 0, $numTicks = 5)
	{
		$range = $max - $min;
		if ($range == 0) $range = $max/5;
		$tempStep = $range / $numTicks;
		
		$mag = floor(log10($tempStep));
		$magPow = pow(10, $mag);
		
		$magMsd = intval($tempStep/$magPow + 0.5);
		
		if ($magMsd > 5.0)
			$magMsd = 10.0;
		else if ($magMsd > 2.0)
			$magMsd = 5.0;
		else if ($magMsd > 1.0)
			$magMsd = 2.0;
		
		$tickRange = $magMsd * $magPow;
		
		if ($tickRange <= 0) $tickRange = 1;
		
		if ($nearestMultiple > 0)
		{
			$remainder = $tickRange % $nearestMultiple;
			if ($remainder != 0) $tickRange = $tickRange + $nearestMultiple - $remainder;
		}
		
		if ($tickRange <= 0) $tickRange = 1;
		return $tickRange;
	}
	
	
	public function CreateImageWeightedAverages(&$image, $data, $color)
	{
		if (count($data) == 0) return false;
		
		$lastX = -1;
		$lastY = -1;
		
		foreach ($data as $timestamp => $price)
		{
			$x = $this->ConvertGraphToPixelX($timestamp);
			$y = $this->ConvertGraphToPixelY($price);
			
			if ($lastX >= 0)
				imageline($image, $lastX, $lastY, $x, $y, $color);
			else
				imageline($image, $x, $y, $x, $y, $color);
			
			$lastX = $x;
			$lastY = $y;
		}
		
		$lastValue = end($data);
		
		$x1 = 2;
		$y = $this->ConvertGraphToPixelY($lastValue);
		$x2 = $this->outputWidth - self::BORDER_RIGHT_MARGIN;
		
		$roundValue = $lastValue;
		
		if ($lastValue > 100)
			$roundValue = round($lastValue); 
		else if ($lastValue > 10)
			$roundValue = round($lastValue, 1);
		else 
			$roundValue = round($lastValue, 2);
		
		$textBox = $this->PrintText($image, $roundValue . "gp", 10, $color, $x1, $y, self::ALIGN_LEFT, self::ALIGN_TOP);
		imagefilledrectangle($image, $x1, $y, $x1 + $textBox['width'], $y + $textBox['height'], $this->backgroundColor);
		$this->PrintText($image, $roundValue . "gp", 10, $color, $x1, $y, self::ALIGN_LEFT, self::ALIGN_TOP);
		
		imageline($image, $x1, $y, $x2, $y, $color);
		
		return true;
	}
	
	
	public function PrintText(&$image, $text, $size, $color, $x, $y, $halign = self::ALIGN_LEFT, $valign = self::ALIGN_TOP, $font = self::REGULARFONT_FILE, $rotate = 0)
	{
		$box = imagettfbbox($size, $rotate, $font, $text);
		$width = $box[2] - $box[0];
		$height = $box[1] - $box[7];
		
		if ($halign == self::ALIGN_LEFT)
		{
		}
		else if ($halign == self::ALIGN_RIGHT)
		{
			$x = $x - $width;
		}
		else if ($halign == self::ALIGN_CENTER)
		{
			$x = $x - intval($width/2);
		}
		
		if ($valign == self::ALIGN_TOP)
		{
			$y = $y + $height;
		}
		else if ($valign == self::ALIGN_BOTTOM)
		{
		}
		else if ($valign == self::ALIGN_CENTER)
		{
			$y = $y + intval($height/2);
		}
		
		imagefttext($image, $size, $rotate, $x, $y, $color, $font, $text);
		
		return array('x' => $x, 'y' => $y, 'width' => $width, 'height' => $height);
	}
	
	
	public function CreateTrendLines($image)
	{
		//$this->trendColor1 = imagecolorallocate($image, 0x77, 0x77, 0x77);
		//$this->trendColor2 = imagecolorallocate($image, 0xcc, 0xcc, 0xcc);
		//$this->trendColor3 = imagecolorallocate($image, 0x77, 0x77, 0x77);
		
		$this->trendColor2 = imagecolorallocate($image, 0xff, 0xff, 0x00);
		$this->trendColor1 = imagecolorallocatealpha($image, 0x77, 0x77, 0x77, 64);
		$this->trendColor3 = imagecolorallocatealpha($image, 0x77, 0x77, 0x77, 64);
		
		$lastX = -1;
		$lastY1 = -1;
		$lastY2 = -1;
		$lastY3 = -1;
		
		foreach ($this->trendsData as $data)
		{
			$timestamp = intval($data['timestamp']);
			
			if ($this->viewData == "list")
			{
				if ($data['sellCount'] <= 0) continue;
				$price1 = floatval($data['sellLow']);
				$price2 = floatval($data['sellMid']);
				$price3 = floatval($data['sellHigh']);
			}
			else if ($this->viewData == "sold")
			{
				if ($data['buyCount'] <= 0) continue;
				$price1 = floatval($data['buyLow']);
				$price2 = floatval($data['buyMid']);
				$price3 = floatval($data['buyHigh']);
			}
			else
			{
				$price1 = floatval($data['bothLow']);
				$price2 = floatval($data['bothMid']);
				$price3 = floatval($data['bothHigh']);
			}
			
			$x = $this->ConvertGraphToPixelX($timestamp);
			$y1 = $this->ConvertGraphToPixelY($price1);
			$y2 = $this->ConvertGraphToPixelY($price2);
			$y3 = $this->ConvertGraphToPixelY($price3);
			
			if ($lastX >= 0)
			{
				imagefilledrectangle($image, $lastX+1, $lastY1, $x, $y3, $this->trendColor1);
				
				//imageline($image, $lastX, $lastY1, $x, $y1, $this->trendColor1);
				imageline($image, $lastX, $lastY2, $x, $y2, $this->trendColor2);
				//imageline($image, $lastX, $lastY3, $x, $y3, $this->trendColor3);
			}
			else
			{
				imagefilledrectangle($image, $x, $y1, $x, $y3, $this->trendColor1);
				
				//imageline($image, $x, $y1, $x, $y1, $this->trendColor1);
				imageline($image, $x, $y2, $x, $y2, $this->trendColor2);
				//imageline($image, $x, $y3, $x, $y3, $this->trendColor3);
			}
			
			$lastX = $x;
			$lastY1 = $y1;
			$lastY2 = $y2;
			$lastY3 = $y3;
		}
		
		return true;
	}
	
	
	public function CreateImage()
	{
		$image = imagecreatetruecolor($this->outputWidth, $this->outputHeight);
		$this->image = $image;
		if ($image == null) return false;
		
		imageantialias($image, true);
		imagealphablending($image, true);
		imagesavealpha($image, true);
		
		$this->invis = imagecolorallocatealpha($image, 0, 0, 0, 0);
		$this->black = imagecolorallocate($image, 0, 0, 0);
		$this->white = imagecolorallocate($image, 255, 255, 255);
		$this->textColor = imagecolorallocate($image, 0xC5, 0xC2, 0x9E);
		$this->darkGray = imagecolorallocate($image, 0x55, 0x55, 0x55);
		
		$this->backgroundColor = imagecolorallocate($image, 0, 0, 0);
		$this->borderColor = imagecolorallocate($image, 0xf0, 0xf0, 0xf0);
		$this->saleColor = imagecolorallocate($image, 0x00, 0xff, 0x00);
		$this->listColor = imagecolorallocate($image, 0xff, 0x33, 0x33);
		$this->saleWeightColor = imagecolorallocate($image, 0xcc, 0xff, 0xcc);
		$this->listWeightColor = imagecolorallocate($image, 0xff, 0xcc, 0xcc);
		$this->weightColor = imagecolorallocate($image, 0xff, 0xff, 0x00);
		
		imagefilledrectangle($image, 0, 0, $this->outputWidth, $this->outputHeight, $this->backgroundColor);
		
		$this->CreateImageBorders($image);
		$this->CreateImageTicks($image);
		$this->CreateImageSalesData($image);
		
		if ($this->viewData == "all")
			$this->CreateImageWeightedAverages($image, $this->avgData, $this->weightColor);
		else if ($this->viewData == "sold")
			$this->CreateImageWeightedAverages($image, $this->avgSoldData, $this->weightColor);
		else if ($this->viewData == "list")
			$this->CreateImageWeightedAverages($image, $this->avgListData, $this->weightColor);
		
		if ($this->showTrends) $this->CreateTrendLines($image);
		
		return true;
	}
	
	
	public function OutputImage()
	{
		$this->OutputHtmlHeader();
		
		$this->LoadSalesData();
		if ($this->showTrends) $this->LoadTrends();
		
		$this->ComputeBasicStats();
		$this->ComputeAdvancedStatistics();
		$this->RecalculatePriceLimits();
		$this->ComputeWeightedAverages();
		
		$this->CreateImage();
		
		imagepng($this->image);
	}
};


$image = new EsoGetSalesImage();
$image->OutputImage();

