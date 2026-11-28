@extends('layouts.owner')

@section('title', 'Tambah produk')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span>
    <a href="{{ route('owner.products.index') }}">Katalog</a><span aria-hidden="true">/</span><span>Tambah produk</span>
@endsection

@section('content')
    <section aria-labelledby="owner-product-create-title">
        <div class="owner-page-heading"><div><p class="section-eyebrow">KATALOG TOKO</p><h1 id="owner-product-create-title">Tambah produk</h1></div></div>
        <form class="owner-product-form" method="post" action="{{ route('owner.products.store') }}" enctype="multipart/form-data">
            @csrf
            <section class="owner-panel" aria-labelledby="new-product-info-title">
                <h2 id="new-product-info-title">Informasi produk</h2>
                <div class="owner-product-form-grid">
                    <div class="form-field"><label class="field-label" for="name">Nama produk <span class="required-mark" aria-hidden="true">*</span></label><input class="field-input" id="name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
                    <div class="form-field"><label class="field-label" for="category_id">Kategori <span class="required-mark" aria-hidden="true">*</span></label><select class="field-input" id="category_id" name="category_id" required><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="form-field owner-product-description"><label class="field-label" for="description">Deskripsi</label><textarea class="field-input" id="description" name="description" rows="5" maxlength="10000">{{ old('description') }}</textarea></div>
                    <div class="form-field owner-product-description"><label class="field-label" for="image">Foto produk <span>(opsional, maks. 5 MB)</span></label><input class="field-input" id="image" name="image" type="file" accept="image/*"><small>Gunakan gambar yang dapat dipakai di storefront.</small></div>
                </div>
            </section>
            @php
                $initialVariants = old('variants', [[
                    'name' => 'Per Pcs',
                    'sku' => '',
                    'price' => '0.00',
                    'weight_grams' => 500,
                    'stock_physical' => 0,
                    'is_active' => '1',
                ]]);
            @endphp
            <section class="owner-panel" aria-labelledby="new-product-variant-title">
                <div class="owner-panel-heading" style="align-items: center;">
                    <h2 id="new-product-variant-title">Satuan / Varian</h2>
                    <button type="button" class="button button-primary" id="btn-add-variant-row" style="min-height: 36px; padding: 6px 14px;">+ Tambah satuan</button>
                </div>
                <p class="owner-product-help">Kamu bisa langsung menambahkan lebih dari satu satuan (misal: Per Bungkus dan Per Slop) beserta berat masing-masing.</p>

                <div id="variant-rows" style="display: grid; gap: 16px;">
                    @foreach ($initialVariants as $idx => $variant)
                        <div class="owner-variant-form" data-variant-row style="padding: 16px; border: 1px solid #e5e7eb; border-radius: 10px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <h3 data-variant-row-title style="margin: 0;">Satuan #{{ $loop->iteration }}</h3>
                                <button type="button" class="button button-danger" data-remove-variant-row style="min-height: 32px; padding: 4px 10px; font-size: 0.85rem; {{ count($initialVariants) <= 1 ? 'display:none;' : '' }}">Hapus satuan</button>
                            </div>
                            <div class="owner-product-form-grid">
                                <div class="form-field">
                                    <label class="field-label" for="variants-{{ $idx }}-name">Satuan / Varian <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input class="field-input" id="variants-{{ $idx }}-name" name="variants[{{ $idx }}][name]" value="{{ $variant['name'] ?? '' }}" placeholder="Contoh: Per Bungkus, Per Slop, Per Pcs" maxlength="255" required>
                                    @error("variants.{$idx}.name")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="variants-{{ $idx }}-sku">SKU <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input class="field-input" id="variants-{{ $idx }}-sku" name="variants[{{ $idx }}][sku]" value="{{ $variant['sku'] ?? '' }}" maxlength="100" required>
                                    @error("variants.{$idx}.sku")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="variants-{{ $idx }}-price">Harga (Rp) <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input class="field-input" id="variants-{{ $idx }}-price" name="variants[{{ $idx }}][price]" type="number" min="0" step="0.01" value="{{ $variant['price'] ?? '0.00' }}" required>
                                    @error("variants.{$idx}.price")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="variants-{{ $idx }}-weight">Berat per satuan (gram) <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input class="field-input" id="variants-{{ $idx }}-weight" name="variants[{{ $idx }}][weight_grams]" type="number" min="1" max="1000000" step="1" value="{{ $variant['weight_grams'] ?? 500 }}" required>
                                    <small>Misal: 50 (per bungkus), 500 (per slop), 1000 (1 kg)</small>
                                    @error("variants.{$idx}.weight_grams")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="variants-{{ $idx }}-stock">Stok fisik <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input class="field-input" id="variants-{{ $idx }}-stock" name="variants[{{ $idx }}][stock_physical]" type="number" min="0" step="1" value="{{ $variant['stock_physical'] ?? 0 }}" required>
                                    @error("variants.{$idx}.stock_physical")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-field">
                                    <label class="field-label" for="variants-{{ $idx }}-active">Status satuan</label>
                                    <select class="field-input" id="variants-{{ $idx }}-active" name="variants[{{ $idx }}][is_active]">
                                        <option value="1" @selected((string) ($variant['is_active'] ?? '1') === '1')>Aktif</option>
                                        <option value="0" @selected((string) ($variant['is_active'] ?? '1') === '0')>Nonaktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
            <div class="owner-product-form-actions"><a class="button button-secondary" href="{{ route('owner.products.index') }}">Batal</a><button class="button button-primary" type="submit">Simpan produk</button></div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('variant-rows');
            const addBtn = document.getElementById('btn-add-variant-row');
            if (!container || !addBtn) return;

            let nextIndex = container.querySelectorAll('[data-variant-row]').length;

            function refreshRowState() {
                const rows = container.querySelectorAll('[data-variant-row]');
                rows.forEach(function (row, i) {
                    const title = row.querySelector('[data-variant-row-title]');
                    if (title) title.textContent = 'Satuan #' + (i + 1);
                    const removeBtn = row.querySelector('[data-remove-variant-row]');
                    if (removeBtn) removeBtn.style.display = rows.length > 1 ? '' : 'none';
                });
            }

            addBtn.addEventListener('click', function () {
                const idx = nextIndex++;
                const wrapper = document.createElement('div');
                wrapper.className = 'owner-variant-form';
                wrapper.setAttribute('data-variant-row', '');
                wrapper.style.cssText = 'padding: 16px; border: 1px solid #e5e7eb; border-radius: 10px;';
                wrapper.innerHTML = ''
                    + '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">'
                    + '  <h3 data-variant-row-title style="margin: 0;">Satuan</h3>'
                    + '  <button type="button" class="button button-danger" data-remove-variant-row style="min-height: 32px; padding: 4px 10px; font-size: 0.85rem;">Hapus satuan</button>'
                    + '</div>'
                    + '<div class="owner-product-form-grid">'
                    + '  <div class="form-field">'
                    + '    <label class="field-label" for="variants-' + idx + '-name">Satuan / Varian <span class="required-mark" aria-hidden="true">*</span></label>'
                    + '    <input class="field-input" id="variants-' + idx + '-name" name="variants[' + idx + '][name]" placeholder="Contoh: Per Bungkus, Per Slop, Per Pcs" maxlength="255" required>'
                    + '  </div>'
                    + '  <div class="form-field">'
                    + '    <label class="field-label" for="variants-' + idx + '-sku">SKU <span class="required-mark" aria-hidden="true">*</span></label>'
                    + '    <input class="field-input" id="variants-' + idx + '-sku" name="variants[' + idx + '][sku]" maxlength="100" required>'
                    + '  </div>'
                    + '  <div class="form-field">'
                    + '    <label class="field-label" for="variants-' + idx + '-price">Harga (Rp) <span class="required-mark" aria-hidden="true">*</span></label>'
                    + '    <input class="field-input" id="variants-' + idx + '-price" name="variants[' + idx + '][price]" type="number" min="0" step="0.01" value="0.00" required>'
                    + '  </div>'
                    + '  <div class="form-field">'
                    + '    <label class="field-label" for="variants-' + idx + '-weight">Berat per satuan (gram) <span class="required-mark" aria-hidden="true">*</span></label>'
                    + '    <input class="field-input" id="variants-' + idx + '-weight" name="variants[' + idx + '][weight_grams]" type="number" min="1" max="1000000" step="1" value="500" required>'
                    + '    <small>Misal: 50 (per bungkus), 500 (per slop), 1000 (1 kg)</small>'
                    + '  </div>'
                    + '  <div class="form-field">'
                    + '    <label class="field-label" for="variants-' + idx + '-stock">Stok fisik <span class="required-mark" aria-hidden="true">*</span></label>'
                    + '    <input class="field-input" id="variants-' + idx + '-stock" name="variants[' + idx + '][stock_physical]" type="number" min="0" step="1" value="0" required>'
                    + '  </div>'
                    + '  <div class="form-field">'
                    + '    <label class="field-label" for="variants-' + idx + '-active">Status satuan</label>'
                    + '    <select class="field-input" id="variants-' + idx + '-active" name="variants[' + idx + '][is_active]">'
                    + '      <option value="1" selected>Aktif</option>'
                    + '      <option value="0">Nonaktif</option>'
                    + '    </select>'
                    + '  </div>'
                    + '</div>';
                container.appendChild(wrapper);
                refreshRowState();
            });

            container.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-remove-variant-row]');
                if (!btn) return;
                const rows = container.querySelectorAll('[data-variant-row]');
                if (rows.length <= 1) return;
                btn.closest('[data-variant-row]')?.remove();
                refreshRowState();
            });

            refreshRowState();
        });
    </script>
@endsection
