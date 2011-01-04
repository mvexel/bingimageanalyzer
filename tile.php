<?php
/*
	tile.php
	===========================================================================
	A quick hack to overlay bing tiles with their capture date as provided in the HTTP metadata.

	Sample request:
	tile.php?t=12020033230
	
	Call in Bing Ajax SDK like this:
		var tileSource = new Microsoft.Maps.TileSource({uriConstructor: 'http://server/tile.php?t={quadkey}'});
		var tilelayer= new Microsoft.Maps.TileLayer({ mercator: tileSource, opacity: 1 });
		map.entities.push(tilelayer);

	===========================================================================
	Copyright (c) 2010 Very Furry / Martijn van Exel

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	*/

// Your Tilecache base directory.
// You will need to create two directories here: tiles and tiles_simple.
// These directories need to be writable by the web server.
//$TC_BASE = '/home/mvexel/www/';
$TC_BASE = 'd:\\ms4w\\apache\\htdocs\\';

// Optionally, define a path to a local PHP error log file here if for some reason you don't want to use PHP's main error log file. If empty, errors will be logged using the global PHP configuration.
// You will need to create this file and make it writable for the web server. 
$LOG_LOCAL = 'php_errors.log';

// From here on, no need for user configuration
$BING_ZOOM_LEVELS=22;
$ZOOM_THRESHOLD=11;
$CACHED=FALSE;


error_reporting(E_ALL ^ E_NOTICE);
if(strlen($LOG_LOCAL)>0) ini_set("error_log","php_errors.log");

// This checks for valid TMS type request URIs, like http://domain/1.0.0/basic/17/67321/43067.png 

$t = parse_query();
$d = $_GET['debug'];
$force = strlen($_GET['force'])>0;
$cur_zoom=strlen($t);
$nodepth = strlen($_GET['nodepth']) > 0;
$s=rand(0,7);
$url_base='http://ecn.t'.$s.'.tiles.virtualearth.net/tiles/a';
$url_end='.jpeg?g=587&n=z';
$url=$url_base.$t.$url_end;

// VE CONSTANTS
$EarthRadius = 6378137;
$MinLatitude = -85.05112878;
$MaxLatitude = 85.05112878;
$MinLongitude = -180;
$MaxLongitude = 180;

$tilecache_basedir = $nodepth&&$cur_zoom>$ZOOM_THRESHOLD?$TC_BASE.'tiles_simple':$TC_BASE.'tiles';

$tile_fn = preg_replace('/(\d)/','/\1',$t);
$tile_dir = substr(($tilecache_basedir . $tile_fn),0,-2);
$tile_fn = $tilecache_basedir . $tile_fn . '.png';

//$latlon = QuadKeyToLatLong($t);

if(!file_exists($tile_fn)) {
	if (!is_dir($tile_dir)) mkdir($tile_dir,0777,true);
} elseif(!$force) {
	$CACHED=TRUE;
	error_log("tile " . $t . " CACHED, fetching...");
}
	
if(!($d)) header("Content-type: image/png");
else print($url);

if($CACHED) {
	$im = imagecreatefrompng($tile_fn);
	imagealphablending($im, true);
	imagesavealpha($im, true); 
} else {
	error_log("tile " . $t . " not CACHED, creating...");
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL,            $url); 
	curl_setopt($ch, CURLOPT_HEADER,         true); 
	curl_setopt($ch, CURLOPT_NOBODY,         true); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($ch, CURLOPT_TIMEOUT,        15); 
	$r = curl_exec($ch); 
	if(!$nodepth) {
		$tt=$t;
		if($cur_zoom > $ZOOM_THRESHOLD) {
			for($i=0;$i<4;$i++) {
				$max_zoom=$cur_zoom;
				$ttt=$tt.$i;
				if(check_tile_exists($ttt))
				{	
					$max_zoom++;
					for($max_zoom=$cur_zoom;$max_zoom<=$BING_ZOOM_LEVELS;$max_zoom++) {
						$n=$max_zoom%2?0:3;
						$ttt.=$n;
						if(!check_tile_exists($ttt)) 
							break;
					}
				}
				$zz[$i]=max(0,$max_zoom-$cur_zoom);
			}
		}
	}
	$headers = array();

	$r = explode("\n", $r); 

	foreach ($r as $kv) {
		$x = explode(":",$kv);
		if(count($x)>1) $headers[$x[0]] = $x[1];
	}

	if($d) {
		echo("<pre>");
		print_r($headers);
		echo("</pre>");
	} else {
		
		$w=256;$h=256;
		$date=preg_replace_callback("/(\d+)\/(\d+)\/(\d+)\-(\d+)\/(\d+)\/(\d+)/i","date_out",trim($headers['X-VE-TILEMETA-CaptureDatesRange']));
		$im = imagecreatetruecolor(256,256);
		imagealphablending($im, false);
		$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefill($im, 0, 0, $transparent);
		imagesavealpha($im,true);
		imagealphablending($im, true); 		
		$background_color = imagecolorallocate($im, 0, 0, 0);
		$text_color = imagecolorallocate($im, $w-1, $h-1, 0);
		$text_color_shadow = imagecolorallocate($im, 64,64,0);
		
		if($cur_zoom > 11 && !$nodepth) {

			$grid_colors = array();
			$levels=8;
			for($i=0;$i<$levels;$i++) {
				array_push(
				$grid_colors, 
				imagecolorallocate($im, 
				floor(256-(256/($levels-$i))), 
				floor(256-(256/($i+1))),
				0)
				);
			}
			
			imagefilledrectangle($im, 0, 0, $w/2-1, $h/2-1,  $grid_colors[min($levels-1,$zz[0])]);
			imagestring($im, 2, floor($w/4) - 20 + 1, floor($h/4) - 5 + 1, ($zz[0]>0?$zz[0]:"no") . " more", $text_color_shadow);
			imagestring($im, 2, floor($w/4) - 20, floor($h/4) - 5, ($zz[0]>0?$zz[0]:"no") . " more", $text_color);
			
			imagefilledrectangle($im, $w/2, 0, $w-1, $h/2-1, $grid_colors[min($levels-1,$zz[1])]);
			imagestring($im, 2, floor(3*$w/4) - 20 + 1, floor($h/4) - 5 + 1, ($zz[1]>0?$zz[1]:"no") . " more", $text_color_shadow);
			imagestring($im, 2, floor(3*$w/4) - 20, floor($h/4) - 5, ($zz[1]>0?$zz[1]:"no") . " more", $text_color);
			
			imagefilledrectangle($im, 0, $h/2, $w/2-1, $h-1,  $grid_colors[min($levels-1,$zz[2])]);
			imagestring($im, 2, floor($w/4) - 20 + 1, floor(3*$h/4) - 5 + 1, ($zz[2]>0?$zz[2]:"no") . " more", $text_color_shadow);
			imagestring($im, 2, floor($w/4) - 20, floor(3*$h/4) - 5, ($zz[2]>0?$zz[2]:"no") . " more", $text_color);
			
			imagefilledrectangle($im, $w/2, $h/2, $w-1, $h-1,  $grid_colors[min($levels-1,$zz[3])]);
			imagestring($im, 2, floor(3*$w/4) - 20 + 1, floor(3*$h/4) - 5 + 1, ($zz[3]>0?$zz[3]:"no") . " more", $text_color_shadow);
			imagestring($im, 2, floor(3*$w/4) - 20, floor(3*$h/4) - 5, ($zz[3]>0?$zz[3]:"no") . " more", $text_color);
		};
		

		imagestring($im, 2, 6, 6, $date, $text_color_shadow);
//		imagestring($im, 2, 6, 18, $latlon['lon'] . ',' . $latlon['lat'], $text_color_shadow);
		imagestring($im, 2, 5, 5, $date, $text_color);
		imageline($im, 0, 0, 0, $h-1, $text_color);
		imageline($im, 0, 0, $w-1, 0, $text_color);
	}
}

imagepng($im);
if(!$CACHED) imagepng($im,$tile_fn);
imagedestroy($im);

function check_tile_exists($quadkey) 
{
	global $url_base,$url_end;
	$url=$url_base.$quadkey.$url_end;
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL,            $url); 
	curl_setopt($ch, CURLOPT_HEADER,         true); 
	curl_setopt($ch, CURLOPT_NOBODY,         true); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($ch, CURLOPT_TIMEOUT,        15); 
	$rr = curl_exec($ch);
	return preg_match("/X\-VE\-Tile\-Info\:\ no\-tile/m",$rr)>0?false:true; 
}

function date_out($matches)
{
	$mths = array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");
	if($matches[1]==$matches[4] && $matches[3]==$matches[6])
	return $mths[$matches[1]-1] . "/" . $matches[3];
	else
	return $mths[$matches[1]-1] . "/" . $matches[3] . " - " . $mths[$matches[4]-1] . "/" . $matches[6];
}

function charAt($str,$pos) {
	return (substr($str,$pos,1) !== false) ? substr($str,$pos,1) : -1;
}	

// VE tile calculation functions adapted from C# code at http://msdn.microsoft.com/en-us/library/bb259689.aspx
function Clip($n, $minValue, $maxValue)
{
    return min(max($n, $minValue), $maxValue);
}

function MapSize($zoomLevel)
{
    return (int) 256 << $zoomLevel;
}

function GroundResolution($latitude, $zoomLevel)
{
	$MinLatitude = -85.05112878;
	$MaxLatitude = 85.05112878;
	$EarthRadius = 6378137;
    $latitude = Clip($latitude, $MinLatitude, $MaxLatitude);
    return cos($latitude * pi() / 180) * 2 * pi() * $EarthRadius / MapSize($zoomLevel);
}

function MapScale($latitude, $zoomLevel, $screenDpi)
{
	return GroundResolution($latitude, $zoomLevel) * $screenDpi / 0.0254;
}

function LatLongToPixelXY($latitude, $longitude, $zoomLevel)
{
	$EarthRadius = 6378137;
	$MinLatitude = -85.05112878;
	$MaxLatitude = 85.05112878;
	$MinLongitude = -180;
	$MaxLongitude = 180;
	print("lat/lon:" . $latitude . "/" . $longitude);
    $latitude = Clip($latitude, $MinLatitude, $MaxLatitude);
    $longitude = Clip($longitude, $MinLongitude, $MaxLongitude);
	print("lat/lon:" . $latitude . "/" . $longitude);
    $x = ($longitude + 180) / 360; 
    $sinLatitude = sin($latitude * pi() / 180);
    $y = 0.5 - log((1 + $sinLatitude) / (1 - $sinLatitude)) / (4 * pi());

    $mapSize = MapSize($zoomLevel);
    print("mapsize:" . $mapSize);
    $pixelX = Clip($x * $mapSize + 0.5, 0, $mapSize - 1);
    $pixelY = Clip($y * $mapSize + 0.5, 0, $mapSize - 1);

    return array('pixelX' => (int) $pixelX, 'pixelY' => (int) $pixelY);
}
        
function PixelXYToLatLong($pixelX, $pixelY, $zoomLevel)
{
	$mapSize = MapSize($zoomLevel);
	$x = (Clip($pixelX, 0, $mapSize - 1) / $mapSize) - 0.5;
	$y = 0.5 - (Clip($pixelY, 0, $mapSize - 1) / $mapSize);

	$latitude = 90 - 360 * atan(exp(-$y * 2 * pi())) / pi();
	$longitude = 360 * $x;

	return array('latitude' => $latitude, 'longitude' => $longitude);
}
        
function PixelXYToTileXY($pixelX, $pixelY)
{
	$tileX = $pixelX / 256;
	$tileY = $pixelY / 256;
	return array('tileX' => (int) $tileX, 'tileY' => (int) $tileY);
}

function TileXYToPixelXY($tileX, $tileY)
{
	$pixelX = $tileX * 256;
	$pixelY = $tileY * 256;
	return array('pixelX' => $pixelX, 'pixelY' => $pixelY);
}

function TileXYToQuadKey($tileX, $tileY, $zoomLevel)
{
	$quadKey = "";
	for ($i = $zoomLevel; $i > 0; $i--)
	{
		$digit = '0';
		$mask = 1 << ($i - 1);
		if (($tileX & $mask) != 0)
		{
			$digit++;
		}
		if (($tileY & $mask) != 0)
		{
			$digit++;
			$digit++;
		}
		$quadKey .= $digit;
	}
	return $quadKey;
}

function QuadKeyToTileXY($quadKey)
{
	$tileX = $tileY = 0;
	$zoomLevel = strlen(quadKey);
	for ($i = $zoomLevel; $i > 0; $i--)
	{
		$mask = 1 << ($i - 1);
		switch (substr($quadKey,$levelOfDetail - i,1))
		{
			case '0':
				break;

			case '1':
				$tileX |= mask;
				break;

			case '2':
				$tileY |= mask;
				break;

			case '3':
				$tileX |= mask;
				$tileY |= mask;
				break;

			default:
				return false;
		}
	}
	return array('tileX' => $tileX, 'tileY' => $tileY, 'zoomLevel' => $zoomLevel);
}

	// adapted from http://social.msdn.microsoft.com/Forums/en-US/vemapcontroldev/thread/49d2e73a-b826-493b-84fd-34b0cb4d4fc3/  
function QuadKeyToLatLong($quadkey) 
{ 
	$x=0; 
	$y=0; 
	$zoomlevel = strlen($quadkey); 

	//convert quadkey to tile xy coords 
	for ($i = 0; $i < $zoomlevel; $i++) 
	{ 
		$factor = pow(2,$zoomlevel-$i-1); 
		switch (charAt($quadkey,$i)) 
		{ 
			case '0': 
				break; 
			case '1': 
				$x += $factor; 
				break; 
			case '2': 
				$y += $factor; 
				break; 
			case '3': 
				$x += $factor; 
				$y += $factor; 
				break; 
		} 
	} 

	//convert tileXY into pixel coordinates for top left corners 
	$pixelX = $x*256; 
	$pixelY = $y*256; 
 
	//convert to latitude and longitude coordinates 
	$longitude = $pixelX*360/(256*pow(2,$zoomlevel)) - 180;
	$latitude = asin((exp((0.5 - $pixelY / 256 / pow(2,$zoomlevel)) * 4 * pi()) - 1) / (exp((0.5 - $pixelY / 256 / pow(2,$zoomlevel)) * 4 * pi()) + 1)) * 180 / pi();
	return array('lat' => $latitude, 'lon' => $longitude); 
}

function parse_query()
{
	$tms_identifier = 0;
	$req_uri = $_SERVER["REQUEST_URI"];
	$matches = preg_split("/\//",$_SERVER["REQUEST_URI"]);
	for($i = 0; $i < count($matches);  $i++) {
		if($matches[$i]=="1.0.0")
			$tms_identifier = $i;
	}
	if($tms_identifier)
	{
		$tms_zoom = (int)$matches[$tms_identifier+2];
		$tms_x=(int)$matches[$tms_identifier+3];
		preg_match("/\d+/",$matches[$tms_identifier+4],$tms_y_matches);
		$tms_y=(int)$tms_y_matches[0];
		if($tms_zoom && $tms_x && $tms_y) {
			$n = pow(2, $tms_zoom);
			$lon_deg = $tms_x / $n * 360.0 - 180.0;
			$lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $tms_y / $n))));
			$t = TileXYToQuadKey($tms_x,$tms_y,$tms_zoom+1);
		}
	}
	if(!isset($t))
		$t = $_GET['t'];
	return $t;
}
	
?>
