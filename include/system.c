//--------------------------------------------
//-----------system functions-----------------
//--------------------------------------------
inline fastcall dword atoi(EDI)
{
    $push ebx
    $push esi
    ESI = EDI;
    WHILE (DSBYTE[ESI] == ' ') ESI++;
    IF (DSBYTE[ESI]=='-') ESI++;
    EAX=0;
    WHILE (DSBYTE[ESI]>='0') && (DSBYTE[ESI]<='9')
    {
        $xor ebx, ebx
        EBX = DSBYTE[ESI]-'0';
        EAX *= 10;
        EAX += EBX;
        ESI++;
    }
    IF (DSBYTE[EDI] == '-') -EAX;
    $pop esi
    $pop ebx
}

inline void getMouse()
{
	dword mouse = 0;

	EAX = 37;
	EBX = 1;
	$int 0x40;
	mouse = EAX;
	mouseY = mouse&0xFFFF;
	mouseY = windowHeight - mouseY;
	mouseX = mouse>>16;
	mouseX -= windowWidth/2;
	mouseY -= windowHeight/2;

	EAX = 37;
	EBX = 2;
	$int 0x40;
	mouse = EAX;
	IF (mouse != mouseKeyCode)
	{
		IF (mouse)
		{
			mousePress = 1;
			mouseUp = 0;
		}
		ELSE
		{
			mousePress = 0;
			mouseUp = 1;
		}
		mouseKeyCode = mouse;
	}
	ELSE
	{
		IF (mouse)
		{
			mousePress = 1;
			mouseUp = 0;
		}
		ELSE
		{
			mousePress = 0;
			mouseUp = 0;
		}
	}
}
inline fastcall dword WaitEvent()
{
	$mov eax,10
	$int 0x40
}
inline dword getKeyCode()
{
	dword key = 0;
	$mov eax,2
	$int 0x40;
	key = EAX;

	if (key < 128<<8) chkKeyCodeGlobal = key;
	return chkKeyCodeGlobal;
}
inline fastcall dword chkEvent()
{
	$mov eax,11
	$int 0x40
}
inline dword WaitEventTime(dword time)
{
	EAX = 23;
	EBX = time;
	$int 0x40;

}
inline dword malloc(dword size)
{
	$mov	 eax, 68
	$mov	 ebx, 12
	$mov	 ecx, size
	$int	 0x40;
}
inline dword free(dword ptr)
{
	$mov	 eax, 68
	$mov	 ebx, 13
	$mov	 ecx, ptr
	$int	 0x40;
}
inline void PutImage(dword x,y, w,h, data_offset)
{
	EAX = 7;
	EBX = data_offset;
	ECX = w<<16+h;
	EDX = x<<16+y;
	$int 0x40
}
inline fastcall word GetButtonID()
{
	$mov eax,17
	$int  0x40
	$shr eax,8
}
inline fastcall ExitProcess()
{
	while(DSDWORD[listProcess])
	{
		EAX = 18;
		EBX = 2;
		ECX = DSDWORD[listProcess];
		$int 0x40;

		listProcess-=4;
	}

	$mov eax,-1;
	$int 0x40;
}
inline void DefineAndDrawWindow(dword _x, _y, _w, _h, _window_type, _bgcolor, _title, _flags)
{
	_w += 9;
	_h += 27;
	_bgcolor &= 0xFFFFFF;

	$xor EAX,EAX
	EBX = _x << 16 + _w;
	ECX = _y << 16 + _h;
	EDX = _window_type << 24 | _bgcolor;
	EDI = _title;
	ESI = _flags;
	$int 0x40
}

inline dword time()
{
	EAX = 26;
	EBX = 9;
	$int 0x40
}

inline dword createThread(dword ptr, size)
{
	dword slot = 0;
	dword stack = 0;
	dword position = 0;
	stack = malloc(size);
	position = stack + size;
	DSDWORD[position-4] = stack;
	EAX = 51;
	EBX = 1;
	ECX = ptr;
	EDX = position;
	$int 0x40;

	slot = EAX;
	listProcess+=4;
	DSDWORD[listProcess] = slot;
	RETURN slot;
}

inline dword date()
{
	EAX = 3;
	$int 0x40;
}