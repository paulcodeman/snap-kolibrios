<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Snap! → KolibriOS Builder</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { height: 100%; background: #1e1e2e; color: #cdd6f4; font-family: system-ui, sans-serif; display: flex; flex-direction: column; }
#toolbar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 16px;
    background: #11111b;
    border-bottom: 1px solid #313244;
    flex-shrink: 0;
}
#toolbar h1 {
    font-size: 16px;
    font-weight: 600;
    background: linear-gradient(135deg, #89b4fa, #f5c2e7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
#toolbar .spacer { flex: 1; }
#toolbar .hint { font-size: 12px; color: #6c7086; }
#toolbar button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    background: #89b4fa;
    color: #1e1e2e;
    transition: all .15s;
}
#toolbar button:hover { background: #b4d0fb; transform: translateY(-1px); }
#toolbar button:disabled { opacity: .4; cursor: not-allowed; transform: none; }
#toolbar button .spinner { display: none; width: 16px; height: 16px; border: 2px solid #1e1e2e; border-top-color: transparent; border-radius: 50%; animation: spin .6s linear infinite; }
#toolbar button.loading .spinner { display: inline-block; }
#toolbar button.loading .label { display: none; }
@keyframes spin { to { transform: rotate(360deg); } }
#snap-frame {
    flex: 1;
    width: 100%;
    border: none;
}
.error-bar {
    display: none;
    padding: 8px 16px;
    background: #f38ba8;
    color: #1e1e2e;
    font-size: 13px;
    flex-shrink: 0;
}
.error-bar.show { display: block; }
</style>
</head>
<body>
<div id="toolbar">
    <h1>Snap! → KolibriOS</h1>
    <span class="hint">Build in Snap!, then compile to native .kex</span>
    <div class="spacer"></div>
    <button id="compile-btn">
        <span class="label">Compile for KolibriOS</span>
        <span class="spinner"></span>
    </button>
</div>
<div id="error-bar" class="error-bar"></div>
<iframe id="snap-frame" src="snap/snap.html"></iframe>

<script>
const compileBtn = document.getElementById('compile-btn');
const errorBar = document.getElementById('error-bar');
const snapFrame = document.getElementById('snap-frame');

compileBtn.addEventListener('click', async () => {
    compileBtn.disabled = true;
    compileBtn.classList.add('loading');
    errorBar.classList.remove('show');

    try {
        const snapWin = snapFrame.contentWindow;

        // Wait for Snap! to be ready (poll for IDE_Morph)
        let ide = null;
        for (let attempt = 0; attempt < 50; attempt++) {
            const w = snapWin.world;
            if (w && w.children && w.children[0] && w.children[0].stage) {
                ide = w.children[0];
                break;
            }
            await new Promise(r => setTimeout(r, 200));
        }

        if (!ide) {
            throw new Error('Snap! editor not loaded yet. Please wait and try again.');
        }

        const xml = ide.serializer.serialize(ide.stage);
        if (!xml || xml.length < 100) {
            throw new Error('Project appears empty. Create something first!');
        }

        const form = new FormData();
        form.append('project', xml);

        const res = await fetch('compile.php', { method: 'POST', body: form });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || `Server error (${res.status})`);
        }

        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'game.kex';
        a.click();
        URL.revokeObjectURL(url);

    } catch (err) {
        errorBar.textContent = 'Error: ' + err.message;
        errorBar.classList.add('show');
    } finally {
        compileBtn.disabled = false;
        compileBtn.classList.remove('loading');
    }
});
</script>
</body>
</html>
