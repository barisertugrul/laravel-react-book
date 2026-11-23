<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Packages\PluginZip;
use App\Tenancy\CurrentAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Paket kütüphanesi: ajansın wordpress.org'da olmayan (ör. ücretli) eklentileri */
class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('packages/index', [
            'packages' => Package::orderBy('name')->orderByDesc('created_at')
                ->get(['id', 'slug', 'name', 'version', 'sha256', 'size', 'created_at']),
        ]);
    }

    public function store(Request $request, CurrentAgency $current): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'extensions:zip', 'max:20480']]); // 20 MB

        $upload = $request->file('file');

        try {
            $plugin = PluginZip::inspect($upload->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if (Package::where('slug', $plugin['slug'])->where('version', $plugin['version'])->exists()) {
            return back()->withErrors(['file' => "{$plugin['name']} {$plugin['version']} kütüphanede zaten var."]);
        }

        // Dosya adı bizim: kullanıcının verdiği ad yola girmez. "local" disk storage/app/private'tır.
        $path = $upload->storeAs("packages/{$current->id()}", "{$plugin['slug']}-".Str::random(12).'.zip', 'local');

        Package::create([
            ...$plugin,
            'path' => $path,
            'sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
            'size' => $upload->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$plugin['name']} {$plugin['version']} kütüphaneye eklendi."]);

        return back();
    }

    public function destroy(Package $package): RedirectResponse
    {
        Storage::disk('local')->delete($package->path);
        $package->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$package->name} {$package->version} silindi."]);

        return back();
    }
}
