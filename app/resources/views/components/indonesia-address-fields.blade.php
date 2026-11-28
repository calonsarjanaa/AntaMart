@props([
    'prefix' => '',
    'required' => false,
    'values' => [],
    'includeRecipient' => true,
    'showMap' => false,
])

@php
    $fieldName = fn (string $key): string => $prefix !== '' ? "{$prefix}[{$key}]" : $key;
    $fieldId = fn (string $key): string => $prefix !== '' ? "{$prefix}_{$key}" : $key;
    $errorKey = fn (string $key): string => $prefix !== '' ? "{$prefix}.{$key}" : $key;
    $oldVal = fn (string $key): string => (string) old($errorKey($key), $values[$key] ?? '');
    $storeLat = (float) config('services.store.latitude', -1.606188);
    $storeLng = (float) config('services.store.longitude', 103.521922);
@endphp

<div
    class="indonesia-address-group checkout-fields"
    data-indonesia-address
    data-prefix="{{ $prefix }}"
    data-store-lat="{{ $storeLat }}"
    data-store-lng="{{ $storeLng }}"
>
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

    <input type="hidden" id="{{ $fieldId('latitude') }}" name="{{ $fieldName('latitude') }}" value="{{ $oldVal('latitude') }}" data-coord-role="latitude">
    <input type="hidden" id="{{ $fieldId('longitude') }}" name="{{ $fieldName('longitude') }}" value="{{ $oldVal('longitude') }}" data-coord-role="longitude">

    @if ($showMap)
        <div class="form-field" style="grid-column: 1 / -1;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                <label class="field-label" style="margin: 0;">Titik Lokasi Pengiriman (untuk cek jarak Kurir AntaMart)</label>
                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <button type="button" class="button button-secondary" data-map-action="geolocate" style="min-height: 32px; padding: 4px 10px; font-size: 0.82rem;">
                        Gunakan Lokasi Saya (GPS)
                    </button>
                    <button type="button" class="button button-secondary" data-map-action="center-region" style="min-height: 32px; padding: 4px 10px; font-size: 0.82rem;">
                        Arahkan ke Kecamatan
                    </button>
                </div>
            </div>
            <div data-address-map style="height: 230px; width: 100%; border-radius: 10px; border: 1px solid #d1d5db; z-index: 1;"></div>
            <p data-map-distance-hint style="margin: 6px 0 0; font-size: 0.86rem; color: #4b5563;">
                Klik peta atau geser pin biru ke lokasi rumah Anda untuk menghitung jarak dari toko AntaMart (Mendalo Darat, Jambi).
            </p>
        </div>
    @endif
</div>

@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        (function () {
            const cache = { provinces: null, regencies: {}, districts: {} };
            const mapRegistry = new WeakMap();

            function haversineKm(lat1, lng1, lat2, lng2) {
                const R = 6371;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLng = (lng2 - lng1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
                    + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
                    * Math.sin(dLng / 2) * Math.sin(dLng / 2);
                return Math.round((R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))) * 100) / 100;
            }

            function emitAddressChanged(group) {
                group.dispatchEvent(new CustomEvent('antamart:address-changed', {
                    bubbles: true,
                    detail: window.AntaMartAddress.snapshot(group),
                }));
            }

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
                    emitAddressChanged(group);
                });

                distSelect.addEventListener('change', async function () {
                    const mapState = mapRegistry.get(group);
                    if (mapState && !mapState.userPinned) {
                        await geocodeAndPin(group, false);
                    }
                    emitAddressChanged(group);
                });

                initMap(group);

                hydrateGroup(group, {
                    province: provSelect.dataset.initial || '',
                    city: citySelect.dataset.initial || '',
                    district: distSelect.dataset.initial || '',
                }).then(function () {
                    emitAddressChanged(group);
                });
            }

            function updateDistanceHint(group, lat, lng) {
                const hintEl = group.querySelector('[data-map-distance-hint]');
                if (!hintEl) return;
                const storeLat = parseFloat(group.dataset.storeLat || '-1.606188');
                const storeLng = parseFloat(group.dataset.storeLng || '103.521922');
                if (isNaN(lat) || isNaN(lng)) {
                    hintEl.textContent = 'Klik peta atau geser pin biru ke lokasi rumah Anda untuk menghitung jarak dari toko AntaMart (Mendalo Darat, Jambi).';
                    return;
                }
                const km = haversineKm(storeLat, storeLng, lat, lng);
                if (km <= 5) {
                    hintEl.innerHTML = 'Jarak dari toko AntaMart: <strong>' + km.toFixed(1) + ' km</strong> — masuk jangkauan <strong>Kurir AntaMart (Rp 5.000)</strong> &amp; ekspedisi.';
                } else if (km <= 10) {
                    hintEl.innerHTML = 'Jarak dari toko AntaMart: <strong>' + km.toFixed(1) + ' km</strong> — masuk jangkauan <strong>Kurir AntaMart (Rp 10.000)</strong> &amp; ekspedisi.';
                } else if (km <= 20) {
                    hintEl.innerHTML = 'Jarak dari toko AntaMart: <strong>' + km.toFixed(1) + ' km</strong> — masuk jangkauan <strong>Kurir AntaMart (Rp 20.000)</strong> &amp; ekspedisi.';
                } else {
                    hintEl.innerHTML = 'Jarak dari toko AntaMart: <strong>' + km.toFixed(1) + ' km</strong> — di luar jangkauan Kurir AntaMart (&gt; 20 km). Pengiriman menggunakan ekspedisi.';
                }
            }

            function setPin(group, lat, lng, isManual) {
                const latInput = group.querySelector('[data-coord-role="latitude"]');
                const lngInput = group.querySelector('[data-coord-role="longitude"]');
                const mapState = mapRegistry.get(group);

                if (latInput) latInput.value = isNaN(lat) ? '' : lat.toFixed(7);
                if (lngInput) lngInput.value = isNaN(lng) ? '' : lng.toFixed(7);

                if (mapState && !isNaN(lat) && !isNaN(lng)) {
                    if (isManual) mapState.userPinned = true;
                    if (!mapState.marker) {
                        mapState.marker = L.marker([lat, lng], { draggable: true }).addTo(mapState.map);
                        mapState.marker.bindPopup('Lokasi pengiriman Anda');
                        mapState.marker.on('dragend', function (e) {
                            const pos = e.target.getLatLng();
                            setPin(group, pos.lat, pos.lng, true);
                            emitAddressChanged(group);
                        });
                    } else {
                        mapState.marker.setLatLng([lat, lng]);
                    }
                    mapState.map.setView([lat, lng], Math.max(mapState.map.getZoom(), 13));
                }

                updateDistanceHint(group, lat, lng);
            }

            async function geocodeAndPin(group, forceZoom) {
                const snap = window.AntaMartAddress.snapshot(group);
                const queryParts = [snap.district, snap.city, snap.province].filter(Boolean);
                if (queryParts.length === 0) return;

                try {
                    const res = await fetch('/api/shipping/geocode?q=' + encodeURIComponent(queryParts.join(', ')), {
                        headers: { Accept: 'application/json' },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data && data.found && data.latitude && data.longitude) {
                        setPin(group, parseFloat(data.latitude), parseFloat(data.longitude), false);
                        if (forceZoom) emitAddressChanged(group);
                    }
                } catch (e) {}
            }

            function initMap(group) {
                const mapContainer = group.querySelector('[data-address-map]');
                if (!mapContainer || typeof L === 'undefined') return;

                const storeLat = parseFloat(group.dataset.storeLat || '-1.606188');
                const storeLng = parseFloat(group.dataset.storeLng || '103.521922');
                const latInput = group.querySelector('[data-coord-role="latitude"]');
                const lngInput = group.querySelector('[data-coord-role="longitude"]');

                const initialLat = latInput && latInput.value ? parseFloat(latInput.value) : NaN;
                const initialLng = lngInput && lngInput.value ? parseFloat(lngInput.value) : NaN;
                const hasInitial = !isNaN(initialLat) && !isNaN(initialLng);

                const map = L.map(mapContainer).setView(
                    hasInitial ? [initialLat, initialLng] : [storeLat, storeLng],
                    hasInitial ? 14 : 12
                );

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map);

                // Store location marker (AntaMart)
                L.circleMarker([storeLat, storeLng], {
                    radius: 8,
                    color: '#dc2626',
                    fillColor: '#ef4444',
                    fillOpacity: 0.9,
                }).addTo(map).bindPopup('<strong>Toko AntaMart</strong><br>Mendalo Darat, Jambi Luar Kota');

                const state = { map: map, marker: null, userPinned: hasInitial };
                mapRegistry.set(group, state);

                if (hasInitial) {
                    setPin(group, initialLat, initialLng, true);
                }

                map.on('click', function (e) {
                    setPin(group, e.latlng.lat, e.latlng.lng, true);
                    emitAddressChanged(group);
                });

                const geoBtn = group.querySelector('[data-map-action="geolocate"]');
                if (geoBtn) {
                    geoBtn.addEventListener('click', function () {
                        if (!navigator.geolocation) {
                            alert('Browser Anda tidak mendukung GPS / geolokasi.');
                            return;
                        }
                        geoBtn.disabled = true;
                        const origText = geoBtn.textContent;
                        geoBtn.textContent = 'Mencari lokasi...';
                        navigator.geolocation.getCurrentPosition(
                            function (pos) {
                                geoBtn.disabled = false;
                                geoBtn.textContent = origText;
                                setPin(group, pos.coords.latitude, pos.coords.longitude, true);
                                emitAddressChanged(group);
                            },
                            function () {
                                geoBtn.disabled = false;
                                geoBtn.textContent = origText;
                                alert('Tidak dapat mengakses lokasi GPS. Silakan klik langsung pada peta.');
                            },
                            { enableHighAccuracy: true, timeout: 10000 }
                        );
                    });
                }

                const centerBtn = group.querySelector('[data-map-action="center-region"]');
                if (centerBtn) {
                    centerBtn.addEventListener('click', function () {
                        geocodeAndPin(group, true);
                    });
                }
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

                    const lat = parseFloat(data.latitude);
                    const lng = parseFloat(data.longitude);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        setPin(group, lat, lng, true);
                    } else {
                        await geocodeAndPin(group, false);
                    }

                    emitAddressChanged(group);
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
                        latitude: getVal('latitude'),
                        longitude: getVal('longitude'),
                    };
                },
            };

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-indonesia-address]').forEach(bindGroup);
            });
        })();
    </script>
@endonce

