// Bölüm 9: Ajans Asistanı eklentisi kurulu üç WordPress Playground sitesi açar.
//   cd projects/09-ajans-paneli-2 && npm run wp-demo
// Siteler: http://kafe.localhost:9401, http://klinik.localhost:9402, http://magaza.localhost:9403
// (kullanıcı: admin / password). *.localhost adları ek ayar gerekmeden bu bilgisayara çıkar.
// Bölüm 8'den farkı: her sitenin KENDİ alan adı var. Lisans sunucusu siteleri alan adıyla sayar,
// portu dikkate almaz; hepsi 127.0.0.1 olsaydı üç site tek site sayılırdı.
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

const here = path.dirname(fileURLToPath(import.meta.url));
const plugin = path.join(here, 'wp-plugin/ajans-asistani');

// Lisans sunucusu: Bölüm 7'nin Anahtarcı'sı (php artisan serve --host=127.0.0.1 --port=8001)
const LICENSE_SERVER = process.env.LISANS_SUNUCUSU ?? 'http://127.0.0.1:8001';

/**
 * Anahtarcı'nın açık anahtarı. Gerçek üründe eklentiye gömülüdür; burada her okurun
 * Anahtarcı'sı kendi anahtarını ürettiği için 07-lisans/.env'deki gizli anahtardan türetiyoruz:
 * sodium'un 64 baytlık gizli anahtarı = 32 bayt tohum + 32 bayt AÇIK anahtar.
 */
function licensePublicKey() {
    const env = path.join(here, '../07-lisans/.env');
    const match = fs.existsSync(env) && fs.readFileSync(env, 'utf8').match(/^LICENSE_SIGNING_KEY=(\S+)/m);

    if (!match) {
        console.log('  (07-lisans/.env içinde LICENSE_SIGNING_KEY yok: lisans özellikleri bu sitelerde çalışmaz)');
        return '';
    }

    return Buffer.from(match[1], 'base64').subarray(32).toString('base64');
}

// Yalnızca yerel ortamda: eklenti açık anahtarın wp-config.php'den değiştirilmesine izin verir
const licenseConsts = {
    WP_ENVIRONMENT_TYPE: 'local',
    AJANS_ASISTANI_LISANS_SUNUCUSU: LICENSE_SERVER,
    AJANS_ASISTANI_LISANS_ACIK_ANAHTAR: licensePublicKey(),
};

// Her site farklı bir yapılandırma: panelde farklı bulgular görünsün
const sites = [
    { port: 9401, host: 'kafe.localhost', name: 'Demo Kafe', consts: { DISALLOW_FILE_EDIT: true, WP_DEBUG: false } },
    { port: 9402, host: 'klinik.localhost', name: 'Demo Klinik', consts: { WP_DEBUG: false } }, // dosya düzenleme açık → uyarı
    { port: 9403, host: 'magaza.localhost', name: 'Demo Mağaza', consts: { DISALLOW_FILE_EDIT: true, WP_DEBUG: true, WP_DEBUG_DISPLAY: true } }, // → kritik
];
const siteUrl = (site) => `http://${site.host}:${site.port}`;

const MAX_ATTEMPTS = 5;
const children = new Set();
process.on('exit', () => children.forEach((child) => child.kill()));
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
        // CLI 3.1.66'dan itibaren varsayılan min(6, çekirdek-1) işçi. Her işçi kendi rastgele adlı geçici
        // klasörünü açıyor; Windows'taki errno 28 hatası tam orada çıktığı için 6 işçiyle site neredeyse
        // hiç açılmıyordu. 2 işçi: biri meşgulken site kendine istek atarsa (wp-cron) ikincisi yanıtlar.
        workers: 2,
        'site-url': siteUrl(site), // WordPress'in home_url()'i: eklentinin panele ve lisans sunucusuna bildirdiği adres
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
                { step: 'defineWpConfigConsts', consts: { ...site.consts, ...licenseConsts } },
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
            children.add(child);
            // Yeni deneme, eski süreç portu bıraktıktan SONRA başlar (yoksa EADDRINUSE)
            const exited = new Promise((r) => child.once('exit', () => (children.delete(child), r())));

            let settled = false; // açılış sonuçlandı mı (başarılı ya da değil)
            let wasReady = false; // site hazır oldu mu: yalnızca o zaman "çalışırken durdu" denir
            let restarting = false;

            const finish = (ok) => {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                if (ok) {
                    wasReady = true;
                    resolve(true);
                } else {
                    child.kill();
                    exited.then(() => resolve(false));
                }
            };
            const timer = setTimeout(() => finish(false), BOOT_TIMEOUT_MS);

            // Hazır olduktan sonraki çöküş: süreç kapanınca TEK bir yeniden başlatma
            const crashed = () => {
                if (!wasReady || restarting || shuttingDown) return;
                restarting = true;
                child.kill();
                console.log(`  ✗ ${site.name} çalışırken durdu; yeniden başlatılıyor (panelde siteyi yeniden bağlamanız gerekecek)…`);
                exited.then(() => setTimeout(() => supervise(site, true), 2000));
            };

            // Playground hatayı worker iş parçacığında yazar ama süreci kapatmaz: çıktıdan yakalıyoruz
            const watch = (stream, out) =>
                stream.on('data', (chunk) => {
                    const text = chunk.toString();
                    if (text.includes('KUMANDA_READY')) return finish(true);
                    out.write(text);
                    if (text.includes('ErrnoError')) {
                        wasReady ? crashed() : finish(false);
                    }
                });
            watch(child.stdout, process.stdout);
            watch(child.stderr, process.stderr);

            child.on('exit', () => (wasReady ? crashed() : finish(false)));
        });

        if (ready) {
            console.log(`✓ ${site.name}${isRestart ? ' yeniden açıldı' : ''}: ${siteUrl(site)}/wp-admin/options-general.php?page=ajans-asistani`);
            return true;
        }

        console.log(`  ↻ ${site.name} açılamadı (deneme ${attempt}/${MAX_ATTEMPTS}), yeniden deneniyor…`);
        await new Promise((r) => setTimeout(r, 2000));
    }

    console.log(`  ✗ ${site.name} ${MAX_ATTEMPTS} denemede açılamadı.`);
    return false;
}
