<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AgeVerificationController extends Controller
{
    public function show(Request $request, string $category)
    {
        $categoryModel = ProductCategory::query()->where('slug', $category)->firstOrFail();

        $minAge = $categoryModel->min_age ?? 21;

        if (!$categoryModel->isAgeRestricted() || session("age_verified_{$minAge}")) {
            $returnUrl = $request->query('return_url') ?: route('storefront.home', ['category' => $categoryModel->slug]);

            return redirect()->to($returnUrl);
        }

        return view('storefront.age-verification', [
            'category' => $categoryModel,
            'minAge' => $minAge,
            'returnUrl' => $request->query('return_url'),
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

        $returnUrl = !empty($validated['return_url'])
            ? $validated['return_url']
            : route('storefront.home', ['category' => $categoryModel->slug]);

        return redirect()->to($returnUrl)->with('status', "Verifikasi berhasil. Anda dapat mengakses kategori {$categoryModel->name}.");
    }
}
