<?php

$offsetImageData = 0;
if (!file_exists('./resource')) mkdir('./resource');
function colorToDword($color)
{
    $rgba = explode(',',$color);
    $dec = ((($rgba[3]*0x7F)&0x7F)<<24)|($rgba[2]<<16)|($rgba[1]<<8)|($rgba[0]);
    return '0x'.str_pad(dechex($dec), 8, "0", STR_PAD_LEFT);
}
function rle_encode($bytes)
{
    $new = [pack('L', strlen($bytes)),chr(0)];
    if (!strlen($bytes)) return $new;
    $current = ord($bytes[0]);
    $len = 0;
    for ($i = 1; $i < strlen($bytes); $i++)
    {
        $byte = ord($bytes[$i]);
        if ($current !== $byte && $len >= 0xFF)
        {
            $new[] = chr($len);
            $new[] = chr($current);
            $len = 0;
            $current = $byte;
        }
        else
        {
            $len++;
        }
    }
    $new[] = chr($len);
    $new[] = chr($current);
    $new[1] = pack('L', count($new)-8);
    return implode('', $new);
}
function textToString($text)
{
    $text = str_replace('"','\\"', $text);
    return '"'.$text.'"';
}
function imgToDword($img, $name="image")
{
    global $offsetImageData;
    $data = explode(',', $img->{'image'});
    $x = $img->{'center-x'};
    $y = $img->{'center-y'};
    $b64 = $data[1];
    //$type = str_replace(['data:',';base64'],['',''],$data[0]);
    $im = imagecreatefromstring(base64_decode($b64));
    $width = imagesx($im);
    $height = imagesy($im);
    $opacity = 0;

    $dword = [$width,$height,$x,$y,$opacity,0];
    $offsetImageData = count($dword)*4;

    for ($y = 0; $y < $height; $y++)
    {
        for ($x = 0; $x < $width; $x++) $dword[] = imagecolorat($im, $x, $y);
    }
    $data = pack("L*", ...$dword);
    //$data = rle_encode($data);
    file_put_contents("./resource/$name.raw", $data);
    return "unsigned char {$name}[] = FROM \"resource/$name.raw\";";
}

$xml = simplexml_load_file('./default.xml');


$stage = $xml->{'stage'};
$title = textToString($xml->attributes()->{'name'});
$attribute = $stage->attributes();
$width = $attribute->{'width'};
$height = $attribute->{'height'};
$costume = $attribute->{'costume'}-1;
$bgcolor = colorToDword($attribute->{'color'});
//$pentrails = imgToDword($stage->{'pentrails'}, 'background1');
$background = [];
$backgroundList = [];
$scripts = [];
$receiveGo = [];
$receiveKey = [];
$initFunction = [];
$initFunctionCount = 0;
$initObjectCount = 0;
$nameListImages = [];
$drawObjects = [];
$initDrawData = [];
$globalVariable = [];
$globalCountCostume = 0;

$variableAddress = [
    'doSetVar','doChangeVar'
];

foreach ($xml->{'variables'}->{'variable'} as $var)
{
    $name = ''.$var->attributes()->{'name'};
    $globalVariable[$name] = (int)$var->{'l'};
}

function getKeyCode($key)
{
    $keyCode = [
        'right arrow' => 77,
        'left arrow' => 75,
        'up arrow' => 72,
        'down arrow' => 80,
        'space' => 57,

        'clicked' => 1,
        'pressed' => 2,
        'mouse-entered' => 4,
        'mouse-departed' => 5,

        'random position' => 1,
        'mouse-pointer' => 2,

        'myself' => 1
    ];
    if (array_key_exists($key, $keyCode)) $key = $keyCode[$key];
    return (int)$key;
}
function makeFunction($name, $item)
{
    global $variableAddress;
    if ($item->attributes()->{'var'})
    {
        return 'variable_'.$item->attributes()->{'var'};
    }
    $args = [];
    $l = [];
    $b = [];
    $s = [];
    $flags = [];
    $nameFunction = $item->attributes()->{'s'};

    if ($item->{'l'})
    {
        foreach ((array)$item->{'l'} as $itemName => $element)
        {
            if ($itemName === 'option')
            {
                $l[] = getKeyCode(trim($element));
            }
            else
            {
                if ((array_search($nameFunction, $variableAddress) !== false) && !is_numeric($element)) $l[] = '#variable_'.$element;
                else $l[] = $element;
            }
        }
    }
    if ($item->block)
    {
        foreach ($item->block as $next)
        {
            $b[] = '#'.initScript($name, [$next], true);
        }

    }
    if ($item->script)
    {
        foreach ($item->script as $element)
        {
            $s[] = '#'.initScript($name, $element->block);
        }
    }

    foreach ($item as $nameArgs => $v)
    {
        if (count($l) && $nameArgs === 'l')
        {
            foreach($l as $vv)
            {
                //$args[] = '"'.str_replace('"','\"',$vv).'"';
                $args[] = $vv;
                $flags[] = '0';
            }
            $l = [];
        }
        elseif (count($b) && $nameArgs === 'block')
        {
            foreach($b as $vv)
            {
                $args[] = $vv;
                $flags[] = '1';
            }
            $b = [];
        }
        elseif (count($s) && $nameArgs === 'script')
        {
            foreach($s as $vv)
            {
                $args[] = $vv;
                $flags[] = '1';
            }
            $s = [];
        }
    }

    if (!count($args)) return $nameFunction."(#{$name})";
    $flags = implode('',$flags);
    return $nameFunction."(#{$name},{$flags}b,".implode(',',$args).")";
}
$receive = [
    'receiveGo' => [],
    'receiveKey' => [],
    'receiveOnClone' => [],
    'receiveCondition' => [],
    'receiveInteraction' => []
];
function addEvent($func, $args = [])
{
    global $receive;
    $func = (string)$func;
    if (!isset($receive[$func])) $receive[$func] = [];
    $receive[$func][] = $args;
}
function initScript($nameSprite, $block, $return = false, $position = 0)
{
    global $initFunction, $initFunctionCount,$globalCountCostume;
    if ($block)
    {
        $name = 'f'.$initFunctionCount;
        $initFunctionCount++;
        $scripts = [];
        $idProccess = 0;
        foreach ($block as $item)
        {
            $nameFunction = $item->attributes()->{'s'};
            switch ($nameFunction)
            {
                case 'receiveGo':
                    $idProccess = $nameFunction;
                    addEvent($nameFunction, ['#'.$name]);
                    break;
                case 'receiveOnClone':
                    $idProccess = $nameFunction;
                    addEvent($nameFunction, ['#'.$name,'#'.$nameSprite]);
                    break;
                case 'receiveKey':
                    $key = trim($item->l->option);
                    $idProccess = $nameFunction;
                    addEvent($nameFunction, ['#'.$name, getKeyCode($key), 1]);
                    break;
                case 'receiveInteraction':
                    $idProccess = $nameFunction;
                    addEvent($nameFunction, ['#'.$name, getKeyCode(trim($item->l->option)), '#'.$nameSprite]);
                    break;
                case 'receiveCondition':
                    $idProccess = $nameFunction;
                    $func = initScript($nameSprite, $item->block, 1);
                    addEvent($nameFunction, ['#'.$name, '#'.$func]);
                    break;
                case 'doSwitchToCostume':
                    $costume = array_search((string)$item->l,$globalCountCostume);
                    if ($costume === false) $costume = 1;
                    else ++$costume;
                    $item->l = $costume;
                    $scripts[] = makeFunction($nameSprite, $item);
                    break;
                case 'doWait':
                    if ($item->l)
                    {
                        if(strpos((string)$item->l, '.') === false) $item->l = (string)$item->l.'.0';
                    }
                    $scripts[] = makeFunction($nameSprite, $item);
                    break;
                default:
                    $scripts[] = makeFunction($nameSprite, $item);
            }
        }

        if ($return) $initFunction[] = "inline dword $name(){RETURN ".$scripts[$position].';}';
        else
        {
            if ($idProccess)
            {
                $initFunction[] = "inline void $name(){dword allocProcessStack=0;\$pop allocProcessStack;".implode(";", $scripts).';free(allocProcessStack);EAX = -1; $int 0x40;}';
            }
            else $initFunction[] = "inline void $name(){".implode(";", $scripts).';}';
        }
        return $name;
    }
    return null;
}

function loadObject($nameSprite, $object)
{
    global $nameListImages, $initObjectCount, $globalCountCostume;
    $images = [];
    $address = [];
    foreach ($object->costumes->list->item as $item)
    {
        $globalCountCostume[] = (string)$item->costume->attributes()->name;
        $name = 'costume'.$initObjectCount;
        $initObjectCount++;
        $images[] = imgToDword($item->costume->attributes(), $name);
        $address[] = '#'.$name;
        $nameListImages[] = $item->costume->attributes()->name;
    }

    foreach ($object->scripts->script as $item)
    {
        initScript($nameSprite, $item);
    }
    return [$images, $address];
}

list($data, $bgAddress) = loadObject('', $xml->stage);
foreach ($data as $item2) $initDrawData[] = $item2;

$sprites = [];
$spriteName = [];
foreach ($xml->stage->sprites->sprite as $item)
{
    $globalCountCostume = [];
    $name = 'sprite'.count($sprites);
    $attr = $item->attributes();
    $hide = 0;
    $event = 0;
    if ((bool)$attr->hidden) $hide = 1;
    $object = [(int)$attr->x,(int)$attr->y,(int)$attr->costume,$hide,$event];
    $offsetSpriteCostume = count($object)*4;
    list($data, $address) = loadObject($name, $item);
    foreach ($data as $item2) $initDrawData[] = $item2;
    foreach ($address as $item3) $object[] = $item3;
    $object[] = 0;
    $sprites[] = 'dword '.$name.'[] = {'.implode(",", $object).'};';
    $spriteName[] = '#'.$name;
}

$events = '';
foreach ($receive as $nameEvent => $event)
{
    $list = implode(',', array_map(function ($value) {
        if (is_array($value)) return implode(',',$value);
        return $value;
    }, $event));
    if (!count($event)) $events .= 'dword '.$nameEvent.'[] = {0};';
    else $events .= 'dword '.$nameEvent.'[] = {'.$list.',0};';
}

$spriteName = 'dword listDrawSprite[] = {'.implode(',', $spriteName).',0};';

$bgAddress[] = 0;
$backgroundList = 'dword bgList[] = {'.implode(',',$bgAddress).'};';

$cmmGlobalVariable = '';
foreach ($globalVariable as $name => $data)
{
    $cmmGlobalVariable .= "dword variable_{$name} = {$data};\r\n";
}

$data = implode("\n",$initDrawData)."\n".implode("\n",$sprites)."\n".$spriteName."\n".implode("\n",$initFunction);

print_r($xml);

$cmm = <<<CMM
#define evReDraw  1
#define evKey     2
#define evButton  3
#define evExit    4
#define evDesktop 5
#define evMouse   6
#define evIPC     7
#define evNetwork 8
#define evDebug   9

#define offsetImageData {$offsetImageData}
#define offsetSpriteCostume {$offsetSpriteCostume}

#define updateDisplayTimeWait 2

#pragma option OST
#pragma option ON
#pragma option cri-
#pragma option -CPA
#initallvar 0
#jumptomain FALSE

#startaddress 0x10000

#code32 TRUE

char   os_name[]   = {'M','E','N','U','E','T','0','1'};
dword  os_version   = 0x00000001;
dword  start_addr   = #______INIT______;
dword  final_addr   = #______STOP______+32;
dword  alloc_mem    = #______STOP______+0x8000;
dword  x86esp_reg   = #______STOP______+0x8000;
dword  I_Param      = 0;
dword  I_Path       = 0;

dword screenWidth = 0;
dword screenHeight = 0;
dword windowWidth = 0;
dword windowHeight = 0;
dword windowCostume = 0;
dword backgroundColor = 0;
dword title = 0;
dword buffer = 0;
dword endBuffer = 0;
dword eventRedrawCommand = 0;
dword listProcess = 0;
dword keyCode = 0;
dword keyDown = 0;
dword keyUp = 0;
dword redrawDisplay = 0;
dword timeStartUpdate = 0;
dword positionBG = 0;
dword chkKeyCodeGlobal = 1;
int mouseX = 0;
int mouseY = 0;
dword mouseKeyCode = 0;
dword mousePress = 0;
dword mouseUp = 0;
dword mouseEnter = 0;
dword opacity = 0;
dword seedRand = 0;
dword cloneData = 0;
dword cloneDataPosition = 0;
dword cloneDataAlloc = 0;

$cmmGlobalVariable

#include "include/system.c"
#include "include/api.c"

void ______INIT______()
{
	int id = 0;
	dword key = 0;
	dword x = 0;
	dword y = 0;
	dword i = 0;
	dword position = 0;
	dword start = 0xFF;
	dword event = 0;
	
	EAX = 68;
	EBX = 11;
	\$int 0x40
	
	EAX = 66;
	EBX = 1;
	ECX = 1;
	\$int 0x40
	
	EAX = 14;
	\$int 0x40
	
	screenWidth = EAX>>16;
	screenHeight = EAX&0xFFFF;
	windowWidth = $width;
	windowHeight = $height;
	backgroundColor = $bgcolor;
	windowCostume = $costume;
	title = $title;
	listProcess = malloc(0x1000);
	
	positionBG = DSDWORD[#bgList];
	
	x = screenWidth-windowWidth;
	x /= 2;
	y = screenHeight-windowHeight;
	y /= 2;
	
	buffer = malloc(4*windowWidth*windowHeight);
	endBuffer = 4*windowWidth*windowHeight+buffer;
	
	EAX = 40;
	EBX = 100111b;
	\$int 0x40;
	
	timeStartUpdate = time();
	seedRand = timeStartUpdate;
	
	loop()
	{
		event = WaitEventTime(updateDisplayTimeWait);
		switch(event)
	  {
		  case evButton:
				id = GetButtonID();
				if (id == 1) ExitProcess();
				break;
			case evKey:
				\$mov eax,2
				\$int 0x40;
				key = EAX>>8;
				if (key < 128) keyCode = key;
				createThread(#receiveKeyStart, 0x1000);
				break;
			case evMouse:
				getMouse();
				receiveInteractionStart();
				break;
			case evReDraw:
				redrawDisplay = 1;
				EAX = 12; 
				EBX = 1;
				\$int 0x40
				DefineAndDrawWindow(x,y,windowWidth,windowHeight,0x74,backgroundColor,title,0);
				EAX = 12; 
				EBX = 2;
				\$int 0x40

				break;
	  }
	  IF (event != evKey) keyCode = 0;
	  IF (start)
		{
			receiveGoStart();
			start = 0;
		}
		receiveConditionUpdate();
	  updateDisplay();
  }
}

$data
$backgroundList
$events
______STOP______:


CMM;

echo $cmm;
file_put_contents('application.c', str_replace("\n","\r\n",$cmm));
//$dir = __DIR__;
//echo exec("/bin/cmm 2>&1");
