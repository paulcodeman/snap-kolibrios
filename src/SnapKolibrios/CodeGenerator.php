<?php

namespace SnapKolibrios;

class CodeGenerator
{
    public function generate(ParsedProject $project): string
    {
        return <<<CMM
#define evReDraw  1
#define evKey     2
#define evButton  3
#define evExit    4
#define evDesktop 5
#define evMouse   6
#define evIPC     7
#define evNetwork 8
#define evDebug   9

#define offsetImageData {$project->offsetImageData}
#define offsetSpriteCostume {$project->offsetSpriteCostume}

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

{$project->globalVarStr}
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
	windowWidth = {$project->width};
	windowHeight = {$project->height};
	backgroundColor = {$project->bgColor};
	windowCostume = {$project->costume};
	title = {$project->title};
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

{$project->dataSection}
{$project->bgListStr}
{$project->eventsStr}
______STOP______:
CMM;
    }
}
