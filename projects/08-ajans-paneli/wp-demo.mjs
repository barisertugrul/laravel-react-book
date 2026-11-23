// Bölüm 8: Ajans Asistanı eklentisi kurulu üç WordPress Playground sitesi açar.
//   cd projects/08-ajans-paneli && npm run wp-demo
// Siteler: http://127.0.0.1:9401, :9402, :9403 (kullanıcı: admin / password)
// Siteler geçici klasörde çalışır: betik kapanınca (Ctrl+C) sıfırlanır; panelde yeniden bağlamak gerekir.
//
// Windows notu: Playground Windows'ta ara sıra "ErrnoError { errno: 28 }" (EINVAL) ile takılır;
// özellikle bir klasör bağlandığında (mount). Bu yüzden:
//  1. Eklenti klasörü bağlanmaz; dosyaları blueprint ile sitenin İÇİNE yazılır
//     (eklentiyi değiştirdiyseniz wp-demo'yu yeniden başlatın).
//  2. Her site ayrı bir alt süreçte açılır; hata görülürse ya da site 3 dakikada hazır olmazsa
//     süreç kapatılıp site baştan denenir.
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const plugin = path.join(path.dirname(fileURLToPath(import.meta.url)), 'wp-plugin/ajans-asistani');

// Her site farklı bir yapılandırma: panelde farklı bulgular görünsün
const sites = [
    { port: 9401, name: 'Demo Kafe', consts: { DISALLOW_FILE_EDIT: true, WP_DEBUG: false } },
    { port: 9402, name: 'Demo Klinik', consts: { WP_DEBUG: false } }, // dosya düzenleme açık → uyarı
    { port: 9403, name: 'Demo Mağaza', consts: { DISALLOW_FILE_EDIT: true, WP_DEBUG: true, WP_DEBUG_DISPLAY: true } }, // → kritik
];

const MAX_ATTEMPTS = 5;
let shuttingDown = false; // Ctrl+C: kapanan alt süreçler "çöktü" sayılmasın
process.on('SIGINT', () => {
    shuttingDown = true;
    process.exit(0);
});
const BOOT_TIMEOUT_MS = 180_000;

/** Klasörü blueprint'in "literal:directory" kaynağına çevirir: { dosya: içerik, alt: { ... } } */
function readTree(dir) {
    const tree = {};
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        tree[entry.name] = entry.isDirectory() ? readTree(full) : fs.readFileSync(full, 'utf8');
    }
    return tree;
}
const workerPort = process.argv.find((a) => a.startsWith('--site='))?.slice(7);

if (workerPort) {
    await runSite(sites.find((s) => String(s.port) === workerPort));
} else {
    const failed = [];
    for (const site of sites) {
        if (!(await supervise(site))) failed.push(site.name);
    }
    const ready = sites.length - failed.length;
    console.log(
        failed.length === 0
            ? `\n${ready}/${sites.length} site hazır. Kapatmak için Ctrl+C.`
            : `\n${ready}/${sites.length} site hazır; açılamayanlar: ${failed.join(', ')}. Kapatmak için Ctrl+C.`,
    );
}

/** Alt süreç: tek bir siteyi açar. Yakalanmamış bir hata olursa çıkış koduyla haber verir. */
async function runSite(site) {
    process.on('unhandledRejection', (error) => {
        console.error(`  ! ${site.name}: ${error?.name ?? 'Hata'} errno=${error?.errno ?? '?'}`);
        process.exit(28);
    });

    const { runCLI } = await import('@wp-playground/cli');
    await runCLI({
        command: 'server',
        port: site.port,
        php: '8.3',
        wp: 'latest',
        // login: true KULLANMIYORUZ: çerezsiz her isteği otomatik girişe yönlendirir (302),
        // panelin imzalı istekleri de yönlendirme alır. Ekran görüntüsü betiği admin/password ile girer.
        blueprint: {
            steps: [
                {
                    step: 'writeFiles',
                    writeToPath: '/wordpress/wp-content/plugins/ajans-asistani',
                    filesTree: { resource: 'literal:directory', name: 'ajans-asistani', files: readTree(plugin) },
                },
                { step: 'defineWpConfigConsts', consts: site.consts },
                { step: 'setSiteOptions', options: { blogname: site.name, permalink_structure: '/%postname%/', timezone_string: 'Europe/Istanbul' } },
                { step: 'activatePlugin', pluginPath: 'ajans-asistani/ajans-asistani.php' },
            ],
        },
    });

    console.log('KUMANDA_READY'); // ana süreç bu satırı bekler
}

/**
 * Ana süreç: siteyi alt süreçte açar; hata görürse ya da süre dolarsa yeniden dener.
 * Site hazır olduktan SONRA çökerse (Playground'ın errno 28 hatası çalışırken de çıkabiliyor)
 * bunu bildirir ve siteyi yeniden başlatır. Açıldıysa true döner.
 */
async function supervise(site, isRestart = false) {
    for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
        const ready = await new Promise((resolve) => {
            const child = spawn(process.execPath, [fileURLToPath(import.meta.url), `--site=${site.port}`], {
                stdio: ['ignore', 'pipe', 'pipe'],
            });
            let settled = false;
            let crashedAfterReady = false;
            const finish = (ok) => {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                if (!ok) child.kill();
                resolve(ok);
            };
            const timer = setTimeout(() => finish(false), BOOT_TIMEOUT_MS);

            // Hazır olduktan sonraki çöküş: yeniden başlat
            const crashed = () => {
                if (!settled || crashedAfterReady || shuttingDown) return;
                crashedAfterReady = true;
                child.kill();
                console.log(`  ✗ ${site.name} çalışırken durdu; yeniden başlatılıyor (panelde siteyi yeniden bağlamanız gerekecek)…`);
                setTimeout(() => supervise(site, true), 2000);
            };

            // Playground hatayı worker iş parçacığında yazar ama süreci kapatmaz: çıktıdan yakalıyoruz
            const watch = (stream, out) =>
                stream.on('data', (chunk) => {
                    const text = chunk.toString();
                    if (text.includes('KUMANDA_READY')) return finish(true);
                    out.write(text);
                    if (text.includes('ErrnoError')) {
                        settled ? crashed() : finish(false);
                    }
                });
            watch(child.stdout, process.stdout);
            watch(child.stderr, process.stderr);

            child.on('exit', () => (settled ? crashed() : finish(false)));
            process.on('exit', () => child.kill());
        });

        if (ready) {
            console.log(`✓ ${site.name}${isRestart ? ' yeniden açıldı' : ''}: http://127.0.0.1:${site.port}/wp-admin/options-general.php?page=ajans-asistani`);
            return true;
        }

        console.log(`  ↻ ${site.name} açılamadı (deneme ${attempt}/${MAX_ATTEMPTS}), yeniden deneniyor…`);
        await new Promise((r) => setTimeout(r, 2000)); // portun boşalması için
    }

    console.log(`  ✗ ${site.name} ${MAX_ATTEMPTS} denemede açılamadı.`);
    return false;
}
