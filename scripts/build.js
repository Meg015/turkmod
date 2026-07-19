import { build } from 'esbuild';
import { readFile, readdir } from 'node:fs/promises';
import { watch } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/(?:[A-Za-z]:)/, value => value.slice(1))), '..');
const args = new Set(process.argv.slice(2));
const buildJs = args.has('--js') || (!args.has('--css') && !args.has('--js'));
const buildCss = args.has('--css') || (!args.has('--css') && !args.has('--js'));
const watchMode = args.has('--watch');

const fromRoot = (...parts) => path.join(root, ...parts);

async function listCssSourceFiles(directory) {
    const files = [];
    const entries = await readdir(directory, { withFileTypes: true });

    for (const entry of entries) {
        const entryPath = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...await listCssSourceFiles(entryPath));
        } else if (entry.isFile() && entry.name.endsWith('.css') && !entry.name.endsWith('.min.css')) {
            files.push(entryPath);
        }
    }

    return files;
}

async function listJavaScriptSourceFiles(directory) {
    const files = [];
    const entries = await readdir(directory, { withFileTypes: true });

    for (const entry of entries) {
        const entryPath = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...await listJavaScriptSourceFiles(entryPath));
        } else if (entry.isFile() && entry.name.endsWith('.js') && !entry.name.endsWith('.min.js')) {
            files.push(entryPath);
        }
    }

    return files;
}

async function assertAdminAjaxUsesSharedHelpers() {
    const adminFiles = (await listJavaScriptSourceFiles(fromRoot('admin', 'assets')))
        .filter(file => path.basename(file) !== 'admin-ui.js');
    const eventAdminFile = fromRoot('includes', 'src', 'Modules', 'Events', 'assets', 'js', 'admin-ui.js');
    const files = [...adminFiles, eventAdminFile];
    const checks = [
        ['fetch(', /\bfetch\s*\(/g],
        ['response.json()', /\b(?:response|res|r)\s*\.\s*json\s*\(/g],
        ['response.text()', /\b(?:response|res|r)\s*\.\s*text\s*\(/g],
    ];
    const violations = [];

    for (const file of files) {
        const source = await readFile(file, 'utf8');
        const lines = source.split(/\r?\n/);

        for (const [label, pattern] of checks) {
            pattern.lastIndex = 0;
            let match;
            while ((match = pattern.exec(source)) !== null) {
                const lineNumber = source.slice(0, match.index).split(/\r?\n/).length;
                const line = lines[lineNumber - 1]?.trim() || '';
                violations.push(`${path.relative(root, file)}:${lineNumber} uses ${label}: ${line}`);
            }
        }
    }

    if (violations.length > 0) {
        throw new Error(
            'Direct admin AJAX usage detected. Use adminFetchJson/adminFetchText/adminFetchHtml from admin/assets/admin-ui.js.\n' +
            violations.join('\n')
        );
    }
}

async function assertPublicAjaxUsesSharedHelpers() {
    const publicFiles = [
        ...await listJavaScriptSourceFiles(fromRoot('assets', 'js')),
        ...await listJavaScriptSourceFiles(fromRoot('themes', 'turkmod', 'js')),
        fromRoot('includes', 'src', 'Modules', 'Events', 'assets', 'js', 'events.js'),
    ].filter(file => path.basename(file) !== 'public-api.js');
    const checks = [
        ['fetch(', /\bfetch\s*\(/g],
        ['response.json()', /\b(?:response|res|r)\s*\.\s*json\s*\(/g],
        ['response.text()', /\b(?:response|res|r)\s*\.\s*text\s*\(/g],
    ];
    const violations = [];

    for (const file of publicFiles) {
        const source = await readFile(file, 'utf8');
        const lines = source.split(/\r?\n/);

        for (const [label, pattern] of checks) {
            pattern.lastIndex = 0;
            let match;
            while ((match = pattern.exec(source)) !== null) {
                const lineNumber = source.slice(0, match.index).split(/\r?\n/).length;
                const line = lines[lineNumber - 1]?.trim() || '';
                violations.push(`${path.relative(root, file)}:${lineNumber} uses ${label}: ${line}`);
            }
        }
    }

    if (violations.length > 0) {
        throw new Error(
            'Direct public AJAX usage detected. Use publicFetchJson/publicFetchText/publicFetchHtml from assets/js/public-api.js.\n' +
            violations.join('\n')
        );
    }
}

async function assertNoDestructiveUniversalCssReset() {
    const sourceRoots = [
        fromRoot('assets', 'css'),
        fromRoot('themes', 'turkmod', 'css'),
    ];

    for (const sourceRoot of sourceRoots) {
        for (const cssPath of await listCssSourceFiles(sourceRoot)) {
            const css = await readFile(cssPath, 'utf8');
            const universalRulePattern = /(?:html\[data-public-theme\s*=\s*["']?turkmod["']?\]\s*)?\*\s*\{([^}]*)\}/gi;
            let match;

            while ((match = universalRulePattern.exec(css)) !== null) {
                const declarations = match[1];
                const resetsMargin = /(?:^|;)\s*margin\s*:\s*0(?:\s*!important)?\s*(?:;|$)/i.test(declarations);
                const resetsPadding = /(?:^|;)\s*padding\s*:\s*0(?:\s*!important)?\s*(?:;|$)/i.test(declarations);

                if (resetsMargin && resetsPadding) {
                    throw new Error(
                        `Destructive universal CSS reset detected in ${path.relative(root, cssPath)}. ` +
                        'Do not reset margin and padding through *; scope spacing resets to the exact component.'
                    );
                }
            }
        }
    }
}

async function buildJavaScript() {
    await assertAdminAjaxUsesSharedHelpers();
    await assertPublicAjaxUsesSharedHelpers();

    await build({
        entryPoints: [fromRoot('assets', 'js', 'app.js')],
        outfile: fromRoot('assets', 'dist', 'public.min.js'),
        bundle: true,
        minify: true,
        legalComments: 'none',
        sourcemap: false,
        target: ['es2020'],
    });

    await build({
        entryPoints: [fromRoot('themes', 'turkmod', 'js', 'bundle.js')],
        outfile: fromRoot('themes', 'turkmod', 'js', 'bundle.min.js'),
        bundle: false,
        minify: true,
        legalComments: 'none',
        sourcemap: false,
        target: ['es2020'],
    });
}

async function buildCssAssets() {
    await assertNoDestructiveUniversalCssReset();

    await build({
        entryPoints: [fromRoot('assets', 'css', 'general.css')],
        outfile: fromRoot('assets', 'dist', 'public.min.css'),
        bundle: true,
        minify: true,
        legalComments: 'none',
        loader: { '.woff': 'file', '.woff2': 'file' },
        assetNames: '[name]-[hash]',
    });

    await build({
        entryPoints: [fromRoot('assets', 'css', 'theme.css')],
        outfile: fromRoot('assets', 'dist', 'theme.min.css'),
        bundle: false,
        minify: true,
        legalComments: 'none',
    });

    await build({
        entryPoints: [fromRoot('themes', 'turkmod', 'css', 'bundle.css')],
        outfile: fromRoot('themes', 'turkmod', 'css', 'bundle.min.css'),
        bundle: false,
        minify: true,
        legalComments: 'none',
    });
}

async function runBuild() {
    if (buildJs) await buildJavaScript();
    if (buildCss) await buildCssAssets();
    process.stdout.write(`Build completed (${[buildJs && 'js', buildCss && 'css'].filter(Boolean).join(', ')}).\n`);
}

await runBuild();

if (watchMode) {
    let timer;
    const watched = [fromRoot('assets'), fromRoot('themes', 'turkmod')];
    for (const directory of watched) {
        watch(directory, { recursive: true }, (_event, filename) => {
            if (!filename || /(?:\.min\.(?:css|js)|assets[\\/]dist)/i.test(filename)) return;
            clearTimeout(timer);
            timer = setTimeout(() => runBuild().catch(error => process.stderr.write(error.stack + '\n')), 150);
        });
    }
    process.stdout.write('Watching asset sources...\n');
}
