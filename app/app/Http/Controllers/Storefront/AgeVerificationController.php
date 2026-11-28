<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Services\AgeVerificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AgeVerificationController extends Controller
{
    public function show(Request $request, string $category, AgeVerificationService $ageVerificationService)
    {
        $categoryModel = ProductCategory::query()->where('slug', $category)->firstOrFail();

        $minAge = $categoryModel->min_age ?? 21;
        $ageStatus = $ageVerificationService->status($request->user('web'), $minAge);

        if ($categoryModel->isAgeRestricted() && $ageStatus === 'underage') {
            return redirect()->route('storefront.home')->withErrors([
                'birth_date' => "Maaf, usia pada profil akun Anda belum memenuhi syarat minimal {$minAge} tahun untuk mengakses kategori {$categoryModel->name}.",
            ]);
        }

        if (!$categoryModel->isAgeRestricted() || $ageStatus === 'verified') {
            $fallback = route('storefront.home', ['category' => $categoryModel->slug]);
            $returnUrl = $this->safeReturnUrl($request->query('return_url'), $fallback);

            return redirect()->to($returnUrl);
        }

        return view('storefront.age-verification', [
            'category' => $categoryModel,
            'minAge' => $minAge,
            'returnUrl' => $this->safeReturnUrl($request->query('return_url'), ''),
        ]);
    }

    public function verify(Request $request, string $category)
    {
        $categoryModel = ProductCategory::query()->where('slug', $category)->firstOrFail();
        $minAge = $categoryModel->min_age ?? 21;

        $validated = $request->validate([
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'return_url' => ['nullable', 'string'],
        ], [
            'birth_date.required' => 'Silakan masukkan tanggal lahir Anda.',
            'birth_date.date' => 'Format tanggal lahir tidak valid.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
        ]);

        $birthDate = Carbon::parse($validated['birth_date']);
        $age = $birthDate->age;

        if ($age < $minAge) {
            return back()->withInput()->withErrors([
                'birth_date' => "Maaf, Anda harus berusia minimal {$minAge} tahun untuk mengakses kategori {$categoryModel->name} (usia Anda: {$age} tahun).",
            ]);
        }

        session([
            "age_verified_{$minAge}" => true,
            'age_verified_birth_date' => $validated['birth_date'],
        ]);

        $fallback = route('storefront.home', ['category' => $categoryModel->slug]);
        $returnUrl = $this->safeReturnUrl($validated['return_url'] ?? null, $fallback);

        return redirect()->to($returnUrl)->with('status', "Verifikasi berhasil. Anda dapat mengakses kategori {$categoryModel->name}.");
    }

    private function safeReturnUrl(?string $returnUrl, string $fallback): string
    {
        if (empty($returnUrl)) {
            return $fallback;
        }

        $returnUrl = trim($returnUrl);

        if (str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, '//')) {
            return $returnUrl;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $targetHost = parse_url($returnUrl, PHP_URL_HOST);

        if ($appHost && $targetHost && strcasecmp($appHost, $targetHost) === 0) {
            return $returnUrl;
        }

        return $fallback;
    }
}

