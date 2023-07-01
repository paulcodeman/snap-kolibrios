/* API PaulCodeman */

// engine function
inline dword getPixel(dword x, y)
{

	dword r = 0;
	dword g = 0;
	dword b = 0;

	IF (x < 0) || (x > windowWidth) || (y < 0) || (y > windowHeight) return 0x7F000000;

	EDX = y*windowWidth+x*3+buffer;
	r = DSBYTE[EDX+2]<<16;
	g = DSBYTE[EDX+1]<<8;
	b = DSBYTE[EDX];

	return r|g|b;
}
inline void putPixel(dword x, y, color)
{
	dword r = 0;
	dword g = 0;
	dword b = 0;
	dword a = 0;

	dword br = 0;
	dword bg = 0;
	dword bb = 0;

	dword bgcolor = 0;

	a = color>>24&0x7F;

	IF (a == 0x7F) || (x < 0) || (x >= windowWidth) || (y < 0) || (y > windowHeight) return;

	r = color>>16&0xFF;
	g = color>>8&0xFF;
	b = color&0xFF;

	if (a)
	{
		bgcolor = getPixel(x, y);

		br = bgcolor>>16&0xFF;
		bg = bgcolor>>8&0xFF;
		bb = bgcolor&0xFF;

		r *= 0x7F-a;
		g *= 0x7F-a;
		b *= 0x7F-a;

		br *= a;
		bg *= a;
		bb *= a;

		r += br;
		g += bg;
		b += bb;

		r /= 0x7F;
		g /= 0x7F;
		b /= 0x7F;
	}

	EAX = y*windowWidth+x*3+buffer;

	DSBYTE[EAX+2] = r;
	DSBYTE[EAX+1] = g;
	DSBYTE[EAX] = b;
}
inline dword mixedAlpha(dword color, alpha)
{
	IF (!alpha) RETURN color;
	EDX = color>>24 + alpha;
	IF (EDX > 0x7F) EDX = 0x7F;
	EDX <<= 24;
	RETURN color&0xFFFFFF + EDX;
}
inline void drawImage(dword ctx, data)
{
	dword position = buffer;
	dword x = 0;
	dword y = 0;
	dword w = 0;
	dword h = 0;
	dword ix = 0;
	dword iy = 0;
	dword a = 0;
	dword color = 0;
	dword alpha = 0;
	dword turn = 0;

	IF (!data) return;
	//data = rle_decode(data);
	w = DSDWORD[data];
	h = DSDWORD[data+4];
	alpha = DSDWORD[data+16];
	turn = DSDWORD[data+20];

	switch(turn)
	{
		case 90:
		case 270:
			x = windowWidth/2 - DSDWORD[data+12] + DSDWORD[ctx];
			y = windowHeight/2 - DSDWORD[data+8] - DSDWORD[ctx+4];
			break;
		default:
			x = windowWidth/2 - DSDWORD[data+8] + DSDWORD[ctx];
			y = windowHeight/2 - DSDWORD[data+12] - DSDWORD[ctx+4];
	}

	data += offsetImageData;

	switch(turn)
	{
		case 90:
			WHILE (iy < h)
			{
				ix = w;
				WHILE (ix)
				{
					putPixel(x+iy, y+ix, mixedAlpha(DSDWORD[data],alpha));
					data += 4;
					ix--;
				}
				iy++;
			}
			break;
		case 180:
			iy = h;
			WHILE (iy)
			{
				ix = w;
				WHILE (ix)
				{
					putPixel(x+ix, y+iy, mixedAlpha(DSDWORD[data],alpha));
					data += 4;
					ix--;
				}
				iy--;
			}
			break;
		case 270:
			iy = h;
			WHILE (iy)
			{
				ix = 0;
				WHILE (ix < w)
				{
					putPixel(x+iy, y+ix, mixedAlpha(DSDWORD[data],alpha));
					data += 4;
					ix++;
				}
				iy--;
			}
			break;
		default:
			WHILE (iy < h)
			{
				ix = 0;
				WHILE (ix < w)
				{
					putPixel(x+ix, y+iy, mixedAlpha(DSDWORD[data],alpha));
					data += 4;
					ix++;
				}
				iy++;
			}
	}
}

inline void windowColor(dword color)
{
	dword size = windowWidth*windowHeight*4;
	dword position = 0;
	position = buffer;
	size += position;
	while (position < size)
	{
		DSDWORD[position] = color&0x00FFFFFF;
		position += 3;
	}
}

inline void updateDisplay()
{
	dword position = 0;
	dword positionCostume = 0;
	dword costume = 0;
	dword sprite = 0;
	dword chkTime = 0;
	IF (!redrawDisplay) return;
	chkTime = time();
	IF (chkTime-timeStartUpdate<updateDisplayTimeWait) return;
	timeStartUpdate = chkTime;
	redrawDisplay = 0;
	windowColor(backgroundColor);
	allDraw(#listDrawSprite);
	IF(cloneData) allDraw(cloneData);
	PutImage(0,0,windowWidth,windowHeight,buffer);
}


inline dword rle_decode(dword data)
{
	dword bufferRLE = 0;
	dword len = 0;
	dword pack = 0;
	dword count = 0;
	dword buffer = 0;
	IF (!data) return 0;
	len = DSDWORD[data];
	pack = DSDWORD[data+4];
	bufferRLE = malloc(len);
	buffer = bufferRLE;
	data +=8;
	pack += data;
	while (data < pack)
	{
		DSBYTE[buffer] = DSBYTE[data+1];
		buffer++;
		count = DSBYTE[data];
		WHILE (count)
		{
			DSBYTE[buffer] = DSBYTE[data+1];
			buffer++;
			count--;
		}
		data += 2;
	}
	return bufferRLE;
}

inline int rand(int x, y)
{
	int length = 0;
	int seed = 0;
	seedRand = 8253729 * seedRand + 2396403;
	IF (y < x) RETURN 0;
	IF (x == y) RETURN x;
	length = y - x;
	EDX = seedRand/length;
	EDX *= length;
	seed = seedRand-EDX;
	seed += x;
	RETURN seed;
}

inline dword getPixelDraw(dword img, int x, y)
{
	int w = 0;
	int h = 0;
	w = DSDWORD[img];
	h = DSDWORD[img+4];
	IF (x < 0) || (x > w) || (y < 0) || (y > h) RETURN 0x7F000000;
	RETURN DSDWORD[y*w+x*4+img];
}


inline dword reportTouchingObject(dword ctx, flags, cmd)
{
	dword img = 0;
	dword a = 0;
	int x1 = 0;
	int x2 = 0;
	int y1 = 0;
	int y2 = 0;
	IF (!DSDWORD[ctx+8]) return 0;
	EDX = DSDWORD[ctx+8]*4+offsetSpriteCostume-4+ctx; // select costume
	img = DSDWORD[EDX];

	x1 = DSDWORD[ctx];
	y1 = DSDWORD[ctx+4];

	a = getPixelDraw(img, DSDWORD[img+8]+mouseX-x1, mouseY+DSDWORD[img+12]-y1)>>24;
	if (a == 0x7F) return 0;
	//if (mouseX >= x1) && (mouseX <= x2) && (mouseY >= y1) && (mouseY <= y2) return 1;
	return 1;
}
inline dword reportAnd(dword ctx, flags, x, y)
{
	IF (flags&10b)
	{
		$call x;
		x = EAX;
	}
	IF (flags&01b)
	{
		$call y;
		y = EAX;
	}
	IF (x) && (y) RETURN 1;
	RETURN 0;
}
inline dword reportMouseDown(dword ctx)
{
	RETURN mousePress;
}
inline dword reportMouseX(dword ctx)
{
	RETURN mouseX;
}
inline dword reportMouseY(dword ctx)
{
	RETURN mouseY;
}



inline dword reportSum(dword a, b, cmd)
{
	IF (cmd&10b)
	{
		$call a
		a = EAX;
	}
	IF (cmd&01b)
	{
		$call b
		b = EAX;
	}
	return a+b;
}

inline void doWait(dword sprite, flags, float time)
{
	time *= 100.0;
	EAX = 5;
	EBX = time;
	$int 0x40;
}

inline void setEffect(dword command, flags, arg)
{
	float opacity = 0;
	switch (command)
	{
		case 0:
			if (!arg) arg = 0;
			else {
				opacity = arg;
				opacity /= 100.0;
				opacity *= 127.0;
				arg = opacity;
			}
			EAX = DSDWORD[positionBG];
			DSDWORD[EAX+16] = arg;
			redrawDisplay = 1;
			break;
	}
}
inline void forward(dword ctx, flags, arg)
{
	dword img = 0;
	IF (flags&1b)
	{
		$call arg;
		arg = EAX;
	}
	IF (!arg) RETURN;
	img = DSDWORD[ctx+offsetSpriteCostume];
	switch(DSDWORD[img+20])
	{
		case 90:
			DSDWORD[ctx+4] += arg;
			break;
		case 180:
			DSDWORD[ctx] -= arg;
			break;
		case 270:
			DSDWORD[ctx+4] -= arg;
			break;
		default:
			DSDWORD[ctx] += arg;
	}
	redrawDisplay = 1;
}
inline void turnLeft(dword ctx, flags, arg)
{
	dword img = 0;
	signed int t = 0;
	IF (flags&1b)
	{
		$call arg;
		arg = EAX;
	}
	IF (!arg) RETURN;
	t = arg;
	img = DSDWORD[ctx+offsetSpriteCostume];
	t += DSDWORD[img+20];
	IF (t>360) t -= 360;
	IF (t<0) t += 360;
	DSDWORD[img+20] = t;
	redrawDisplay = 1;
}
inline void turn(dword ctx, flags, arg)
{
	dword img = 0;
	signed int t = 0;
	IF (flags&1b)
	{
		$call arg;
		arg = EAX;
	}
	IF (!arg) RETURN;
	t = arg;
	img = DSDWORD[ctx+offsetSpriteCostume];
	t -= DSDWORD[img+20];
	IF (t>360) t -= 360;
	IF (t<0) t += 360;
	DSDWORD[img+20] = t;
	redrawDisplay = 1;
}
inline void gotoXY(dword ctx, flags, x, y)
{
	IF (!ctx) return;
	IF (flags&10b)
	{
		$call x;
		x = EAX;
	}
	IF (flags&01b)
	{
		$call y;
		y = EAX;
	}
	IF (DSDWORD[ctx] != x) || (DSDWORD[ctx+4] != y)
	{
		DSDWORD[ctx] = x;
		DSDWORD[ctx+4] = y;
		redrawDisplay = 1;
	}
}
inline void changeYPosition(dword ctx, flags, arg)
{
	IF (!arg) RETURN;
	IF (flags&1b)
	{
		$call arg
		arg = EAX;
	}
	IF (!arg) RETURN;
	DSDWORD[ctx+4] += arg;
	redrawDisplay = 1;
}
inline void setYPosition(dword ctx, flags, arg)
{
	IF (flags&1b)
	{
		$call arg
		arg = EAX;
	}
	IF (DSDWORD[ctx+4] != arg)
	{
		redrawDisplay = 1;
		DSDWORD[ctx+4] = arg;
	}
}
inline signed int yPosition(dword ctx)
{
	IF (!ctx) RETURN;
	RETURN DSDWORD[ctx+4];
}
inline void changeXPosition(dword ctx, flags, arg)
{
	IF (!arg) RETURN;
	IF (flags&1b)
	{
		$call arg
		arg = EAX;
	}
	IF (!arg) RETURN;
	DSDWORD[ctx] += arg;
	redrawDisplay = 1;
}
inline void setXPosition(dword ctx, flags, arg)
{
	IF (flags&1b)
	{
		$call arg
		arg = EAX;
	}
	IF (DSDWORD[ctx] != arg)
	{
		DSDWORD[ctx] = arg
		redrawDisplay = 1;
	}
}

inline signed int xPosition(dword ctx)
{
	IF (!ctx) RETURN;
	RETURN DSDWORD[ctx];
}

inline void receiveInteractionStart()
{
	dword position = 0;
	dword ctx = 0;
	dword event = 0;
	position = #receiveInteraction;
	while(DSDWORD[position])
	{
		ctx = DSDWORD[position+8];
		if (reportTouchingObject(ctx,0,0))
		{
			switch(DSDWORD[position+4])
			{
				case 1:
					IF (mouseUp)
					{
						createThread(DSDWORD[position], 0x1000);
					}
				break;
				case 2:
					IF (mousePress) && (!DSDWORD[ctx+16]&10b)
					{
						DSDWORD[ctx+16] = 11b;
						createThread(DSDWORD[position], 0x1000);
					}
					ELSE IF (mouseUp) DSDWORD[ctx+16] = 01b;
				break;
				case 4:
					IF (DSDWORD[ctx+16]&1b)
					{
						DSDWORD[ctx+16] |= 01b;
						createThread(DSDWORD[position], 0x1000);
					}
				break;
			}
		}
		else
		{
			DSDWORD[ctx+16] &= 10b;
			IF (DSDWORD[position+4] == 5) && (DSDWORD[ctx+16]&1b) createThread(DSDWORD[position], 0x1000);
		}
		position+=12;
	}
}
inline void receiveConditionUpdate()
{
	dword position = 0;
	position = #receiveCondition;
	WHILE(DSDWORD[position])
	{
		$call DSDWORD[position+4];
		IF (EAX) createThread(DSDWORD[position], 0x1000);
		position+=8;
	}
}

inline dword reportLessThan(dword ctx, flags, int a, b)
{
	IF (!ctx) return;
	IF (flags & 10b)
	{
		$call a;
		a = EAX;
	}
	IF (flags & 01b)
	{
		$call b;
		b = EAX;
	}
	IF (a < b) RETURN 1;
	RETURN 0;
}
inline dword reportEquals(dword ctx, flags, int a, b)
{
	IF (!ctx) return;
	IF (flags & 10b)
	{
		$call a;
		a = EAX;
	}
	IF (flags & 01b)
	{
		$call b;
		b = EAX;
	}
	IF (a == b) RETURN 1;
	RETURN 0;
}
inline void doForever(dword ctx, flags, func)
{
	WHILE(1)
	{
		$call func
	}
}

inline void doRepeat(dword ctx, flags, func, count)
{
	IF (flags&1b)
	{
		$call count;
		count = EAX;
	}
	WHILE(count)
	{
		$call func
		count--;
	}
}

inline void doWearNextCostume(dword ctx)
{
	dword next = 0;
	dword position = 0;
	DSDWORD[ctx+8]++;
	position = DSDWORD[ctx+8];
	next = DSDWORD[position*4+ctx+offsetSpriteCostume-4];
	if (!next) DSDWORD[ctx+8] = 1;
	redrawDisplay = 1;
}

inline void doSwitchToCostume(dword ctx, flags, position)
{
	IF (DSDWORD[ctx+8] != position)
	{
		DSDWORD[ctx+8] = position;
		redrawDisplay = 1;
	}
}
inline void allDraw(dword position)
{
	dword sprite = 0;
	dword costume = 0;
	dword positionCostume = 0;
	while (DSDWORD[position])
	{
		sprite = DSDWORD[position];
		IF (DSDWORD[sprite+12])
		{
			position+=4;
			continue;
		}
		position+=4;
		positionCostume = DSDWORD[sprite+8];
		IF (!positionCostume) continue;
		positionCostume--;
		costume = positionCostume*4+offsetSpriteCostume+sprite;
		drawImage(sprite, DSDWORD[costume]);
	}
}

inline void receiveGoStart()
{
	dword position = 0;
	position = #receiveGo;
	WHILE(DSDWORD[position])
	{
		createThread(DSDWORD[position], 0x1000);
		position += 4;
	}
}
inline void doSetVar(dword ctx, flags, name, data)
{
	IF (flags & 01b)
	{
		$call data;
		DSDWORD[name] = EAX;
	}
	ELSE DSDWORD[name] = data;
}
inline void doChangeVar(dword ctx, flags, name, data)
{
	IF (flags & 01b)
	{
		$call data;
		DSDWORD[name] += EAX;
	}
	ELSE DSDWORD[name] += data;
}
inline void receiveKeyStart()
{
	dword position = 0;
	dword allocProcessStack=0;
	$pop allocProcessStack;
	position = #receiveKey;
	WHILE(DSDWORD[position])
	{
		IF (!DSDWORD[position+8])
		{
			position += 12;
			CONTINUE;
		}
		IF (keyCode == DSDWORD[position+4])
		{
			DSDWORD[position+8] = 0;
			$call DSDWORD[position];
			DSDWORD[position+8] = 1;
			BREAK;
		}
		position += 12;
	}
	free(allocProcessStack);
	EAX = -1; $int 0x40;
}
inline void doIf(dword ctx, flags, arg, func)
{
	$call arg
	IF (EAX)
	{
		$call func
	}
}
inline void doIfElse(dword ctx, flags, arg, func1, func2)
{
	$call arg
	IF (EAX)
	{
		$call func1
	}
	ELSE
	{
		$call func2
	}
}
inline void hide(dword ctx)
{
	IF (!DSDWORD[ctx+12]) redrawDisplay = 1;
	DSDWORD[ctx+12] = 1;
}
inline void show(dword ctx)
{
	IF (DSDWORD[ctx+12]) redrawDisplay = 1;
	DSDWORD[ctx+12] = 0;
}

inline dword reportKeyPressed(dword ctx, flags, key)
{
	IF (keyCode == key) RETURN 1;
	RETURN 0;
}

inline void doGotoObject(dword ctx, flags, cmd)
{
	switch(cmd)
	{
		case 1:
			gotoXY(ctx,0,rand(-240,240),rand(-180,180));
		break;
		case 2:
			gotoXY(ctx,0,mouseX,mouseY);
		break;
	}
}
inline void createClone(dword ctx, flags, cmd)
{
	dword clone = 0;
	dword position = 0;
	dword positionClone = 0;
	IF (!cloneData)
	{
		cloneData = malloc(0x1000);
		cloneDataPosition = cloneData;
		cloneDataAlloc = 0x1000;
	}
	ELSE IF(cloneDataPosition-cloneData>=cloneDataAlloc)
	{
		cloneDataAlloc += 0x1000;
		//cloneData = realloc(cloneData, cloneDataAlloc);
	}
	switch(cmd)
	{
		case 1:
			clone = malloc(0x1000);
			position = clone;
			WHILE(1)
			{
				IF (!DSDWORD[ctx]) && (position-clone>offsetSpriteCostume) BREAK;
				DSDWORD[position] = DSDWORD[ctx];
				ctx+=4;
				position += 4;
			}
			DSDWORD[position] = 0;
			DSDWORD[cloneDataPosition] = clone;
			cloneDataPosition+=4;
			positionClone = #receiveOnClone;
			WHILE(DSDWORD[positionClone])
			{
				IF (DSDWORD[positionClone+4] == ctx)
				{
					createThread(DSDWORD[positionClone],0x1000);
				}
				positionClone+=8;
			}
		break;
	}
}