import { build } from 'esbuild';
import { readFile, writeFile, rm } from 'node:fs/promises';
import { watch } from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/(?:[A-Za-z]:)/, value => value.slice(1))), '..');
const args = new Set(process.argv.slice(2));
const buildJs = args.has('--js') || (!args.has('--css') && !args.has('--js'));
const buildCss = args.has('--css') || (!args.has('--css') && !args.has('--js'));
const watchMode = args.has('--watch');

const fromRoot = (...parts) => path.join(root, ...parts);

async function concatenate(files, output) {
    const chunks = [];
    for (const file of files) {
        chunks.push(await readFile(fromRoot(file), 'utf8'));
    }
    await writeFile(fromRoot(output), chunks.join('\n'), 'utf8');
}

async function buildJavaScript() {
    const publicEntry = fromRoot('assets', 'dist', '.public-bundle-entry.js');
    await concatenate([
        'assets/js/app.js',
        'assets/js/ui.js',
        'assets/js/ui-foundation.js',
    ], 'assets/dist/.public-bundle-entry.js');

    try {
        await build({
            entryPoints: [publicEntry],
            outfile: fromRoot('assets', 'dist', 'public.min.js'),
            bundle: false,
            minify: true,
            legalComments: 'none',
            sourcemap: false,
            target: ['es2020'],
        });
    } finally {
        await rm(publicEntry, { force: true });
        await rm(fromRoot('assets', 'dist', 'public.min.js.map'), { force: true });
    }

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
