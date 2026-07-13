# snap-kolibrios

Конвертер проектов Snap! в нативные исполняемые файлы (.kex) для KolibriOS.


## Быстрый старт

```powershell
powershell -ExecutionPolicy Bypass -File snap/setup.ps1
```

Откройте в браузере `editor.php` — редактируйте игру в Snap!, тестируйте в браузере,
нажмите «Compile for KolibriOS» → получите `.kex` для KolibriOS.

### CLI

```bash
php index.php [snap-project.xml] [output.c]
```

По умолчанию читает `default.xml`, пишет `application.c`.  
Затем соберите `cmm application.c` (или `build.bat`).

---

## Архитектура

```
snap/                            # Snap! 5.4 (форкнутая версия)
src/
├── autoload.php                 # Автозагрузчик PSR-4
└── SnapKolibrios/
    ├── Transpiler.php           # Фасад: точка входа транспиляции
    ├── XmlParser.php            # Парсинг Snap! XML → ParsedProject
    ├── CodeGenerator.php        # Генерация C-- кода из ParsedProject
    ├── ResourceProcessor.php    # Конвертация изображений в .raw
    ├── CompileHandler.php       # HTTP-обработчик компиляции
    ├── ParsedProject.php        # DTO с данными проекта
    └── KeyCodes.php             # Константы клавиш
include/
├── system.c                    # Системные вызовы KolibriOS
└── api.c                       # Игровой движок C-- (sprites, events, rendering)
c--.ini                          # Конфиг C-- компилятора
editor.php                       # Веб-редактор (Snap! iframe + compile button)
compile.php                      # API endpoint
index.php                        # CLI точка входа
build.bat                        # CLI-сборка через cmm.exe
```

### Поток компиляции

```
Snap! XML → XmlParser → ParsedProject → CodeGenerator → .c → cmm.exe → .kex
```

---

## Статус поддержки блоков Snap!

**Реализовано ~40 из ~115 блоков (35%).** Ниже — прогресс по категориям.

### Motion — 12/17 (71%)

| Блок | Статус |
|------|--------|
| `forward`, `turn`, `turnLeft` | ✅ |
| `gotoXY`, `doGotoObject` | ✅ random/mouse-pointer |
| `changeXPosition`, `setXPosition`, `xPosition` | ✅ |
| `changeYPosition`, `setYPosition`, `yPosition` | ✅ |
| `setHeading` (point in direction) | ❌ |
| `doFaceTowards` (point towards) | ❌ |
| `doGlide` | ❌ |
| `bounceOffEdge` | ❌ |
| `direction` (reporter) | ❌ |

### Looks — 6/23 (26%)

| Блок | Статус |
|------|--------|
| `doSwitchToCostume`, `doWearNextCostume` | ✅ |
| `show`, `hide` | ✅ |
| `setEffect` | ✅ только color |
| `getCostumeIdx` | ❌ |
| `doSayFor`, `bubble`, `doThinkFor`, `doThink` | ❌ |
| `changeEffect`, `getEffect`, `clearEffects` | ❌ |
| `changeScale`, `setScale`, `getScale` | ❌ |
| `goToLayer`, `goBack` | ❌ |
| `reportShown` | ❌ |

### Sound — 0/21 (0%)

Полностью отсутствует аудиоподсистема.

### Pen — 0/20 (0%)

Полностью отсутствует.

### Control — 12/34 (35%)

| Блок | Статус |
|------|--------|
| `receiveGo` (green flag) | ✅ |
| `receiveKey` | ✅ |
| `receiveInteraction` (mouse) | ✅ |
| `receiveCondition` | ✅ |
| `receiveOnClone` | ✅ |
| `doWait` | ✅ |
| `doForever`, `doRepeat` | ✅ |
| `doIf`, `doIfElse` | ✅ |
| `createClone` | ✅ только myself |
| `receiveMessage` (broadcast) | ❌ |
| `doBroadcast`, `doBroadcastAndWait` | ❌ |
| `doWaitUntil`, `doUntil`, `doFor` | ❌ |
| `doStopThis` | ❌ |
| `doRun`, `fork`, `evaluate` | ❌ |
| `removeClone` | ❌ |
| `doWarp`, `doTellTo`, `reportAskFor` | ❌ |

### Sensing — 6/23 (26%)

| Блок | Статус |
|------|--------|
| `reportTouchingObject` | ✅ |
| `reportMouseX`, `reportMouseY`, `reportMouseDown` | ✅ |
| `reportKeyPressed` | ✅ |
| `reportTouchingColor`, `reportColorIsTouchingColor` | ❌ |
| `doAsk`, `getLastAnswer` | ❌ |
| `getTimer`, `doResetTimer` | ❌ |
| `reportAttributeOf`, `reportObject` | ❌ |
| `reportURL`, `reportDate` | ❌ |
| `reportAudio`, `reportVideo` | ❌ |

### Operators — 4/25+ (16%)

| Блок | Статус |
|------|--------|
| `reportSum` (+) | ✅ |
| `reportLessThan` (<) | ✅ |
| `reportEquals` (=) | ✅ |
| `reportAnd` | ✅ |
| `reportDifference`, `reportProduct`, `reportQuotient` | ❌ |
| `reportModulus`, `reportPower` | ❌ |
| `reportRound`, `reportMonadic` (sqrt, abs...) | ❌ |
| `reportRandom` | ❌ (engine: rand есть) |
| `reportGreaterThan`, `reportOr`, `reportNot` | ❌ |
| `reportJoinWords`, `reportLetter`, `reportStringSize` | ❌ |
| `reportUnicode`, `reportUnicodeAsLetter` | ❌ |

### Variables — 2/5 (40%)

| Блок | Статус |
|------|--------|
| `doSetVar`, `doChangeVar` | ✅ |
| `doShowVar`, `doHideVar` | ❌ |
| `doDeleteAttr` | ❌ |

### Lists — 0/20+ (0%)

Все блоки списков отсутствуют.

---

## Roadmap

### Phase 1 — Motion & Operators (Q3 2026)
- [ ] `setHeading`, `direction` — направление спрайта
- [ ] `doFaceTowards` — поворот к цели
- [ ] `doGlide` — плавное движение
- [ ] `bounceOffEdge` — отскок от края
- [ ] `reportRandom` (random: обёртка над `rand()`)
- [ ] `reportDifference`, `reportProduct`, `reportQuotient`
- [ ] `reportGreaterThan`, `reportOr`, `reportNot`
- [ ] `reportModulus`, `reportMonadic` (sqrt, abs, floor)
- [ ] `reportJoinWords`, `reportLetter`, `reportStringSize`

### Phase 2 — Looks & Effects (Q3 2026)
- [ ] `doSayFor` / `bubble` — текстовые пузыри (через окно/overlay)
- [ ] `changeEffect`, `getEffect`, `clearEffects` — полный набор эффектов
- [ ] `changeScale`, `setScale`, `getScale` — масштабирование спрайтов
- [ ] `goToLayer`, `goBack` — z-order
- [ ] `getCostumeIdx`, `reportShown`
- [ ] `reportTouchingColor`, `reportColorIsTouchingColor`

### Phase 3 — Control Flow (Q3-Q4 2026)
- [ ] `doBroadcast` / `doBroadcastAndWait` + `receiveMessage` — система сообщений
- [ ] `doWaitUntil`, `doUntil`, `doFor` — расширенные циклы
- [ ] `doStopThis` — остановка скриптов
- [ ] `removeClone` — удаление клонов
- [ ] `doRun` / `fork` — C-функции для запуска команд

### Phase 4 — Lists (Q4 2026)
- [ ] `reportNewList`, `reportNumbers` — создание списков
- [ ] `reportListItem`, `reportListLength` — чтение
- [ ] `doAddToList`, `doDeleteFromList`, `doInsertInList`, `doReplaceInList` — мутация
- [ ] `reportListContainsItem`, `reportListIsEmpty`
- [ ] `reportMap`, `reportKeep`, `reportFindFirst`, `reportCombine`

### Phase 5 — Sensing & Interaction (Q4 2026)
- [ ] `doAsk` / `getLastAnswer` — ввод текста
- [ ] `getTimer` / `doResetTimer` — таймер
- [ ] `reportAttributeOf`, `reportObject` — интроспекция
- [ ] `reportDate`, `reportURL` — дата/сеть

### Phase 6 — Sound (2027)
- [ ] PCM-буфер + прерывания таймера для `playNote`/`playFreq`
- [ ] `playSound`, `doPlaySoundUntilDone`
- [ ] `change/getTempo`, `change/setVolume`
- [ ] `doRest`, `doSetInstrument`

### Phase 7 — Pen (2027)
- [ ] `clear`, `down`, `up`, `getPenDown` — перо вкл/выкл
- [ ] `setColor`, `setPenHSVA` — цвет пера
- [ ] `changeSize`, `setSize` — толщина
- [ ] `doStamp` — штамп спрайта
- [ ] `floodFill`, `write` — заливка/текст

---

## Разработка

```bash
# Проверка синтаксиса PHP
php -l src/SnapKolibrios/*.php
php -l index.php compile.php

# Транспиляция тестового проекта
php index.php default.xml output.c

# Сборка (требуется cmm.exe в PATH)
build.bat
```

---

## Лицензия

См. [LICENSE](./snap/LICENSE) (Snap! распространяется под AGPL).
