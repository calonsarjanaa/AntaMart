@props([
    'prefix' => '',
    'required' => false,
    'values' => [],
    'includeRecipient' => true,
])

@php
    $fieldName = fn (string $key): string => $prefix !== '' ? "{$prefix}[{$key}]" : $key;
    $fieldId = fn (string $key): string => $prefix !== '' ? "{$prefix}_{$key}" : $key;
    $errorKey = fn (string $key): string => $prefix !== '' ? "{$prefix}.{$key}" : $key;
    $oldVal = fn (string $key): string => (string) old($errorKey($key), $values[$key] ?? '');
@endphp

<div class="indonesia-address-group checkout-fields" data-indonesia-address data-prefix="{{ $prefix }}">
    @if ($includeRecipient)
        <div class="form-field">
            <label class="field-label" for="{{ $fieldId('recipient_name') }}">
                Nama penerima @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
            </label>
            <input
                id="{{ $fieldId('recipient_name') }}"
                name="{{ $fieldName('recipient_name') }}"
                type="text"
                value="{{ $oldVal('recipient_name') }}"
                autocomplete="name"
                class="field-input @error($errorKey('recipient_name')) field-input-invalid @enderror"
                @if ($required) required @endif
            >
            @error($errorKey('recipient_name'))<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="form-field">
            <label class="field-label" for="{{ $fieldId('phone') }}">
                Nomor telepon penerima @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
            </label>
            <input
                id="{{ $fieldId('phone') }}"
                name="{{ $fieldName('phone') }}"
                type="tel"
                value="{{ $oldVal('phone') }}"
                autocomplete="tel"
                class="field-input @error($errorKey('phone')) field-input-invalid @enderror"
                @if ($required) required @endif
            >
            @error($errorKey('phone'))<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @endif

    <div class="form-field" style="grid-column: auto;">
        <label class="field-label" for="{{ $fieldId('province') }}">
            Provinsi @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
        </label>
        <select
            id="{{ $fieldId('province') }}"
            name="{{ $fieldName('province') }}"
            class="field-input @error($errorKey('province')) field-input-invalid @enderror"
            data-region-role="province"
            data-initial="{{ $oldVal('province') }}"
            @if ($required) required @endif
        >
            <option value="">-- Pilih Provinsi --</option>
            @if ($oldVal('province') !== '')
                <option value="{{ $oldVal('province') }}" selected>{{ $oldVal('province') }}</option>
            @endif
        </select>
        @error($errorKey('province'))<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field" style="grid-column: auto;">
        <label class="field-label" for="{{ $fieldId('city') }}">
            Kota/Kabupaten @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
        </label>
        <select
            id="{{ $fieldId('city') }}"
            name="{{ $fieldName('city') }}"
            class="field-input @error($errorKey('city')) field-input-invalid @enderror"
            data-region-role="city"
            data-initial="{{ $oldVal('city') }}"
            @if ($required) required @endif
        >
            <option value="">-- Pilih Kota/Kabupaten --</option>
            @if ($oldVal('city') !== '')
                <option value="{{ $oldVal('city') }}" selected>{{ $oldVal('city') }}</option>
            @endif
        </select>
        @error($errorKey('city'))<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field" style="grid-column: 1 / -1;">
        <label class="field-label" for="{{ $fieldId('district') }}">
            Kecamatan @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
        </label>
        <select
            id="{{ $fieldId('district') }}"
            name="{{ $fieldName('district') }}"
            class="field-input @error($errorKey('district')) field-input-invalid @enderror"
            data-region-role="district"
            data-initial="{{ $oldVal('district') }}"
            @if ($required) required @endif
        >
            <option value="">-- Pilih Kecamatan --</option>
            @if ($oldVal('district') !== '')
                <option value="{{ $oldVal('district') }}" selected>{{ $oldVal('district') }}</option>
            @endif
        </select>
        @error($errorKey('district'))<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field" style="grid-column: 1 / -1;">
        <label class="field-label" for="{{ $fieldId('village') }}">
            Desa/Kelurahan @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
        </label>
        <textarea
            id="{{ $fieldId('village') }}"
            name="{{ $fieldName('village') }}"
            rows="2"
            maxlength="150"
            placeholder="Contoh: Kel. Kebon Melati / Desa Sukamaju"
            class="field-input @error($errorKey('village')) field-input-invalid @enderror"
            style="min-height: 60px; padding: 9px 12px; resize: vertical;"
            @if ($required) required @endif
        >{{ $oldVal('village') }}</textarea>
        @error($errorKey('village'))<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-field" style="grid-column: 1 / -1;">
        <label class="field-label" for="{{ $fieldId('address_line1') }}">
            Detail alamat (nama jalan, nomor rumah, RT/RW) @if ($required)<span class="required-mark" aria-hidden="true">*</span>@endif
        </label>
        <textarea
            id="{{ $fieldId('address_line1') }}"
            name="{{ $fieldName('address_line1') }}"
            rows="3"
            maxlength="255"
            placeholder="Contoh: Jl. Merdeka No. 12, RT 02/RW 05, patokan dekat masjid"
            class="field-input @error($errorKey('address_line1')) field-input-invalid @enderror"
            style="min-height: 82px; padding: 9px 12px; resize: vertical;"
            @if ($required) required @endif
        >{{ $oldVal('address_line1') }}</textarea>
        @error($errorKey('address_line1'))<p class="field-error">{{ $message }}</p>@enderror
    </div>
</div>

@once
    <script>
        (function () {
            const cache = { provinces: null, regencies: {}, districts: {} };

            async function fetchJson(url) {
                try {
                    const res = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (!res.ok) return [];
                    const data = await res.json();
                    return Array.isArray(data) ? data : [];
                } catch (e) {
                    return [];
                }
            }

            async function getProvinces() {
                if (!cache.provinces) cache.provinces = await fetchJson('/api/regions/provinces');
                return cache.provinces;
            }

            async function getRegencies(provinceId) {
                if (!provinceId) return [];
                if (!cache.regencies[provinceId]) {
                    cache.regencies[provinceId] = await fetchJson('/api/regions/regencies/' + encodeURIComponent(provinceId));
                }
                return cache.regencies[provinceId];
            }

            async function getDistricts(regencyId) {
                if (!regencyId) return [];
                if (!cache.districts[regencyId]) {
                    cache.districts[regencyId] = await fetchJson('/api/regions/districts/' + encodeURIComponent(regencyId));
                }
                return cache.districts[regencyId];
            }

            function normalize(str) {
                return (str || '').toString().trim().toLowerCase();
            }

            function populateSelect(select, items, placeholder, targetName) {
                select.innerHTML = '';
                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = placeholder;
                select.appendChild(defaultOpt);

                let matchedOption = null;
                const normTarget = normalize(targetName);

                items.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.name;
                    opt.textContent = item.name;
                    opt.dataset.regionId = item.id;
                    if (normTarget && normalize(item.name) === normTarget) {
                        opt.selected = true;
                        matchedOption = opt;
                    }
                    select.appendChild(opt);
                });

                if (normTarget && !matchedOption) {
                    const customOpt = document.createElement('option');
                    customOpt.value = targetName;
                    customOpt.textContent = targetName;
                    customOpt.selected = true;
                    select.appendChild(customOpt);
                    matchedOption = customOpt;
                }

                return matchedOption;
            }

            async function hydrateGroup(group, values) {
                const provSelect = group.querySelector('[data-region-role="province"]');
                const citySelect = group.querySelector('[data-region-role="city"]');
                const distSelect = group.querySelector('[data-region-role="district"]');
                if (!provSelect || !citySelect || !distSelect) return;

                const provinces = await getProvinces();
                const matchedProv = populateSelect(provSelect, provinces, '-- Pilih Provinsi --', values.province || '');

                if (matchedProv && matchedProv.dataset.regionId) {
                    const regencies = await getRegencies(matchedProv.dataset.regionId);
                    const matchedCity = populateSelect(citySelect, regencies, '-- Pilih Kota/Kabupaten --', values.city || '');

                    if (matchedCity && matchedCity.dataset.regionId) {
                        const districts = await getDistricts(matchedCity.dataset.regionId);
                        populateSelect(distSelect, districts, '-- Pilih Kecamatan --', values.district || '');
                    } else {
                        populateSelect(distSelect, [], '-- Pilih Kecamatan --', values.district || '');
                    }
                } else {
                    populateSelect(citySelect, [], '-- Pilih Kota/Kabupaten --', values.city || '');
                    populateSelect(distSelect, [], '-- Pilih Kecamatan --', values.district || '');
                }
            }

            function bindGroup(group) {
                const provSelect = group.querySelector('[data-region-role="province"]');
                const citySelect = group.querySelector('[data-region-role="city"]');
                const distSelect = group.querySelector('[data-region-role="district"]');
                if (!provSelect || !citySelect || !distSelect) return;

                provSelect.addEventListener('change', async function () {
                    const selected = provSelect.options[provSelect.selectedIndex];
                    const provId = selected ? selected.dataset.regionId : '';
                    populateSelect(citySelect, [], 'Memuat Kota/Kabupaten...', '');
                    populateSelect(distSelect, [], '-- Pilih Kecamatan --', '');

                    const regencies = await getRegencies(provId);
                    populateSelect(citySelect, regencies, '-- Pilih Kota/Kabupaten --', '');
                });

                citySelect.addEventListener('change', async function () {
                    const selected = citySelect.options[citySelect.selectedIndex];
                    const cityId = selected ? selected.dataset.regionId : '';
                    populateSelect(distSelect, [], 'Memuat Kecamatan...', '');

                    const districts = await getDistricts(cityId);
                    populateSelect(distSelect, districts, '-- Pilih Kecamatan --', '');
                });

                hydrateGroup(group, {
                    province: provSelect.dataset.initial || '',
                    city: citySelect.dataset.initial || '',
                    district: distSelect.dataset.initial || '',
                });
            }

            window.AntaMartAddress = {
                apply: async function (group, data) {
                    if (!group || !data) return;
                    const prefix = group.dataset.prefix || '';
                    const getEl = (key) => document.getElementById(prefix ? prefix + '_' + key : key);

                    ['recipient_name', 'phone', 'village', 'address_line1'].forEach(function (key) {
                        const el = getEl(key);
                        if (el && data[key] !== undefined && data[key] !== null) {
                            el.value = data[key];
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });

                    await hydrateGroup(group, {
                        province: data.province || '',
                        city: data.city || '',
                        district: data.district || '',
                    });
                },
                snapshot: function (group) {
                    const prefix = group.dataset.prefix || '';
                    const getVal = (key) => document.getElementById(prefix ? prefix + '_' + key : key)?.value || '';
                    return {
                        recipient_name: getVal('recipient_name'),
                        phone: getVal('phone'),
                        province: getVal('province'),
                        city: getVal('city'),
                        district: getVal('district'),
                        village: getVal('village'),
                        address_line1: getVal('address_line1'),
                    };
                },
            };

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-indonesia-address]').forEach(bindGroup);
            });
        })();
    </script>
@endonce

