import test from 'node:test';
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtemp, rm } from 'node:fs/promises';
import { createServer } from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

test('real multipart uploads sanitize before publication and retain the old avatar on failure', async () => {
    const root = fileURLToPath(new URL('../', import.meta.url));
    const directory = await mkdtemp(path.join(root, 'storage', 'avatar-regression-'));
    const socket = createServer();
    await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const child = spawn('php', ['-d', `upload_tmp_dir=${directory}`, '-S', `127.0.0.1:${port}`, path.join(root, 'tests', 'fixtures', 'avatar-upload.php')], {
        cwd: root, windowsHide: true,
        env: { ...process.env, TURKMOD_UPLOAD_TEST_DIRECTORY: directory }, stdio: ['ignore', 'pipe', 'pipe'],
    });
    let diagnostics = '';
    child.stderr.on('data', data => { diagnostics += data.toString(); });
    const base = `http://127.0.0.1:${port}`;
    try {
        let ready = false;
        for (let attempt = 0; attempt < 40; attempt++) {
            try { ready = (await fetch(base)).ok; } catch {}
            if (ready) break;
            await new Promise(resolve => setTimeout(resolve, 50));
        }
        assert.ok(ready, diagnostics);
        // Use GD to create a valid PNG, then append harmless data that must not survive re-encoding.
        const png = execFileSync('php', ['-r', '$image = imagecreatetruecolor(4, 4); imagepng($image);'], { windowsHide: true });
        for (const scenario of ['success', 'failure', 'invalid', 'generic']) {
            const bytes = scenario === 'invalid' ? Buffer.from('not an image') : Buffer.concat([png, Buffer.from('\0<?x\0payload-marker\0base64_decode')]);
            const form = new FormData();
            form.append('avatar', new Blob([bytes], { type: 'image/png' }), 'avatar.png');
            const response = await fetch(`${base}/?scenario=${scenario}`, { method: 'POST', body: form });
            const body = await response.text();
            assert.ok(response.ok, body + diagnostics);
            const result = JSON.parse(body);
            if (scenario === 'success') {
                assert.equal(result.error, '', body + diagnostics);
                assert.equal(result.old_exists, false);
                assert.equal(result.stored_exists, true);
                assert.equal(result.stored_clean, true);
                assert.equal(result.file_count, 1);
            } else if (scenario === 'generic') {
                assert.equal(result.success, true, body + diagnostics);
                assert.equal(result.clean, true);
            } else {
                assert.notEqual(result.error, '');
                assert.equal(result.old_exists, true);
                assert.equal(result.avatar, 'uploads/profil/old-avatar.png');
                assert.equal(result.file_count, 1);
            }
        }
    } finally {
        child.kill();
        await new Promise(resolve => child.once('exit', resolve));
        // Only remove the generated, resolved directory beneath this checkout's storage folder.
        assert.equal(path.dirname(path.resolve(directory)), path.resolve(root, 'storage'));
        assert.match(path.basename(directory), /^avatar-regression-/);
        await rm(directory, { recursive: true, force: true });
    }
});
