<?php

namespace SnapKolibrios;

use SimpleXMLElement;

class XmlParser
{
    private const VARIABLE_ADDRESS_FUNCS = ['doSetVar', 'doChangeVar'];

    private ResourceProcessor $resourceProcessor;

    private int $offsetSpriteCostume = 0;
    private int $functionCount = 0;
    private int $objectCount = 0;

    private array $functions = [];
    private array $drawData = [];
    private array $costumeNames = [];
    private array $globalVariables = [];

    private array $events = [
        'receiveGo' => [],
        'receiveKey' => [],
        'receiveOnClone' => [],
        'receiveCondition' => [],
        'receiveInteraction' => [],
    ];

    public function __construct(ResourceProcessor $resourceProcessor)
    {
        $this->resourceProcessor = $resourceProcessor;
    }

    public function parse(SimpleXMLElement $xml): ParsedProject
    {
        $stage = $xml->stage;
        $title = $this->escapeString((string)$xml->attributes()->name);

        $attr = $stage->attributes();
        $width = (int)$attr->width;
        $height = (int)$attr->height;
        $costume = (int)$attr->costume - 1;
        $bgColor = $this->resourceProcessor->colorToDword((string)$attr->color);

        $this->parseGlobalVariables($xml);

        $bgAddress = $this->loadStage($stage);
        [$sprites, $spriteNames] = $this->loadSprites($stage);

        $eventsStr = $this->buildEvents();
        $spriteNameStr = 'dword listDrawSprite[] = {' . implode(',', $spriteNames) . ',0};';

        $bgAddress[] = 0;
        $bgListStr = 'dword bgList[] = {' . implode(',', $bgAddress) . '};';

        $globalVarStr = '';
        foreach ($this->globalVariables as $name => $value) {
            $globalVarStr .= "dword variable_{$name} = {$value};\r\n";
        }

        $dataSection = implode("\n", $this->drawData) . "\n"
            . implode("\n", $sprites) . "\n"
            . $spriteNameStr . "\n"
            . implode("\n", $this->functions);

        return new ParsedProject(
            width: $width,
            height: $height,
            bgColor: $bgColor,
            costume: $costume,
            title: $title,
            globalVarStr: $globalVarStr,
            dataSection: $dataSection,
            bgListStr: $bgListStr,
            eventsStr: $eventsStr,
            offsetImageData: $this->resourceProcessor->getOffsetImageData(),
            offsetSpriteCostume: $this->offsetSpriteCostume,
        );
    }

    private function parseGlobalVariables(SimpleXMLElement $xml): void
    {
        foreach ($xml->variables->variable as $var) {
            $this->globalVariables[(string)$var->attributes()->name] = (int)$var->l;
        }
    }

    private function loadStage(SimpleXMLElement $stage): array
    {
        [$images, $address] = $this->loadObject('', $stage);
        foreach ($images as $img) {
            $this->drawData[] = $img;
        }
        return $address;
    }

    private function loadSprites(SimpleXMLElement $stage): array
    {
        $sprites = [];
        $spriteNames = [];

        foreach ($stage->sprites->sprite as $item) {
            $this->costumeNames = [];
            $name = 'sprite' . count($sprites);
            $spriteAttr = $item->attributes();
            $hide = (bool)$spriteAttr->hidden ? 1 : 0;
            $object = [(int)$spriteAttr->x, (int)$spriteAttr->y, (int)$spriteAttr->costume, $hide, 0];
            $this->offsetSpriteCostume = count($object) * 4;

            [$images, $address] = $this->loadObject($name, $item);
            foreach ($images as $img) {
                $this->drawData[] = $img;
            }
            foreach ($address as $addr) {
                $object[] = $addr;
            }
            $object[] = 0;
            $sprites[] = 'dword ' . $name . '[] = {' . implode(',', $object) . '};';
            $spriteNames[] = '#' . $name;
        }

        return [$sprites, $spriteNames];
    }

    private function loadObject(string $nameSprite, SimpleXMLElement $object): array
    {
        $images = [];
        $address = [];

        foreach ($object->costumes->list->item as $item) {
            $this->costumeNames[] = (string)$item->costume->attributes()->name;
            $name = 'costume' . $this->objectCount;
            $this->objectCount++;
            $images[] = $this->resourceProcessor->imageToRaw($item->costume->attributes(), $name);
            $address[] = '#' . $name;
        }

        foreach ($object->scripts->script as $item) {
            $this->initScript($nameSprite, $item);
        }

        return [$images, $address];
    }

    private function initScript(string $nameSprite, SimpleXMLElement|array $block, bool $return = false, int $position = 0): ?string
    {
        if (!$block) {
            return null;
        }

        $name = 'f' . $this->functionCount;
        $this->functionCount++;
        $scripts = [];
        $idProcess = null;

        foreach ($block as $item) {
            $nameFunction = (string)$item->attributes()->s;

            switch ($nameFunction) {
                case 'receiveGo':
                    $idProcess = $nameFunction;
                    $this->addEvent($nameFunction, ['#' . $name]);
                    break;

                case 'receiveOnClone':
                    $idProcess = $nameFunction;
                    $this->addEvent($nameFunction, ['#' . $name, '#' . $nameSprite]);
                    break;

                case 'receiveKey':
                    $key = trim($item->l->option);
                    $idProcess = $nameFunction;
                    $this->addEvent($nameFunction, ['#' . $name, KeyCodes::getCode($key), 1]);
                    break;

                case 'receiveInteraction':
                    $idProcess = $nameFunction;
                    $this->addEvent($nameFunction, ['#' . $name, KeyCodes::getCode(trim($item->l->option)), '#' . $nameSprite]);
                    break;

                case 'receiveCondition':
                    $idProcess = $nameFunction;
                    $func = $this->initScript($nameSprite, $item->block, true);
                    $this->addEvent($nameFunction, ['#' . $name, '#' . $func]);
                    break;

                case 'doSwitchToCostume':
                    $costumeIdx = array_search((string)$item->l, $this->costumeNames);
                    $item->l = ($costumeIdx === false) ? 1 : ++$costumeIdx;
                    $scripts[] = $this->makeFunction($nameSprite, $item);
                    break;

                case 'doWait':
                    if ($item->l) {
                        if (strpos((string)$item->l, '.') === false) {
                            $item->l = (string)$item->l . '.0';
                        }
                    }
                    $scripts[] = $this->makeFunction($nameSprite, $item);
                    break;

                default:
                    $scripts[] = $this->makeFunction($nameSprite, $item);
            }
        }

        if ($return) {
            $this->functions[] = "inline dword $name(){RETURN " . $scripts[$position] . ';}';
        } else {
            if ($idProcess) {
                $body = 'dword allocProcessStack=0;$pop allocProcessStack;'
                    . implode(';', $scripts)
                    . ';free(allocProcessStack);EAX = -1; $int 0x40;';
            } else {
                $body = implode(';', $scripts) . ';';
            }
            $this->functions[] = "inline void $name(){{$body}}";
        }

        return $name;
    }

    private function makeFunction(string $nameSprite, SimpleXMLElement $item): string
    {
        if ($item->attributes()->var) {
            return 'variable_' . $item->attributes()->var;
        }

        $args = [];
        $l = [];
        $b = [];
        $s = [];
        $flags = [];
        $nameFunction = (string)$item->attributes()->s;

        if ($item->l) {
            foreach ((array)$item->l as $itemName => $element) {
                if ($itemName === 'option') {
                    $l[] = KeyCodes::getCode(trim($element));
                } else {
                    if (in_array($nameFunction, self::VARIABLE_ADDRESS_FUNCS, true) && !is_numeric($element)) {
                        $l[] = '#variable_' . $element;
                    } else {
                        $l[] = $element;
                    }
                }
            }
        }

        if ($item->block) {
            foreach ($item->block as $next) {
                $b[] = '#' . $this->initScript($nameSprite, [$next], true);
            }
        }

        if ($item->script) {
            foreach ($item->script as $element) {
                $s[] = '#' . $this->initScript($nameSprite, $element->block);
            }
        }

        foreach ($item as $nameArgs => $v) {
            if (count($l) && $nameArgs === 'l') {
                foreach ($l as $vv) {
                    $args[] = $vv;
                    $flags[] = '0';
                }
                $l = [];
            } elseif (count($b) && $nameArgs === 'block') {
                foreach ($b as $vv) {
                    $args[] = $vv;
                    $flags[] = '1';
                }
                $b = [];
            } elseif (count($s) && $nameArgs === 'script') {
                foreach ($s as $vv) {
                    $args[] = $vv;
                    $flags[] = '1';
                }
                $s = [];
            }
        }

        if (!count($args)) {
            return $nameFunction . "(#{$nameSprite})";
        }

        return $nameFunction . "(#{$nameSprite}," . implode('', $flags) . 'b,' . implode(',', $args) . ')';
    }

    private function addEvent(string $func, array $args = []): void
    {
        $this->events[$func][] = $args;
    }

    private function buildEvents(): string
    {
        $out = '';
        foreach ($this->events as $eventName => $eventList) {
            $list = implode(',', array_map(function ($v) {
                return is_array($v) ? implode(',', $v) : $v;
            }, $eventList));
            if (!count($eventList)) {
                $out .= 'dword ' . $eventName . '[] = {0};';
            } else {
                $out .= 'dword ' . $eventName . '[] = {' . $list . ',0};';
            }
        }
        return $out;
    }

    private function escapeString(string $text): string
    {
        return '"' . str_replace('"', '\\"', $text) . '"';
    }
}
