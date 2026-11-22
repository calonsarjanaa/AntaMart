@extends('layouts.owner')

@section('title', 'Detail pesanan')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span>
    <a href="{{ route('owner.orders.index') }}">Pesanan</a><span aria-hidden="true">/</span><span>{{ $order->id }}</span>
@endsection

@section('content')
    <section class="owner-order-page" aria-labelledby="owner-order-title">
        <div class="owner-page-heading">
            <div><p class="section-eyebrow">DETAIL PESANAN</p><h1 id="owner-order-title">{{ $order->id }}</h1></div>
            <x-status-badge :status="$order->status->value" />
        </div>

        <div class="owner-order-grid">
            <div class="owner-order-main">
                @if ($order->status->value === 'awaiting_confirmation')
                    <x-alert tone="info">
                        <strong>Menunggu Konfirmasi Pesanan:</strong> Periksa ketersediaan stok fisik di toko offline, lalu gunakan tombol <strong>Terima Pesanan</strong> atau <strong>Tolak Pesanan</strong> di panel kanan.
                    </x-alert>
                @endif

                @if ($order->rejection_reason)
                    <x-alert tone="error">
                        <strong>Pesanan Ditolak oleh Toko:</strong> {{ $order->rejection_reason }}
                    </x-alert>
                @endif

                <section class="owner-panel" aria-labelledby="owner-order-items-title">
                    <div class="owner-panel-heading"><h2 id="owner-order-items-title">Item pesanan</h2><span>{{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</span></div>
                    <div class="owner-table-wrap">
                        <table class="owner-table">
                            <thead><tr><th>Produk</th><th>SKU</th><th>Jumlah</th><th>Harga saat checkout</th><th>Subtotal</th></tr></thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td><span class="owner-buyer-name">{{ $item->product_snapshot['name'] ?? 'Produk' }}</span><small>{{ $item->variant_snapshot['name'] ?? 'Varian' }}</small></td>
                                        <td>{{ $item->sku }}</td>
                                        <td>{{ $item->quantity }}</td>
                                        <td><x-price :amount="$item->unit_price" /></td>
                                        <td><x-price :amount="$item->subtotal" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="owner-order-totals">
                        <div><span>Subtotal produk</span><strong><x-price :amount="$order->subtotal" /></strong></div>
                        <div><span>Ongkos kirim</span><strong><x-price :amount="$order->shipping_cost" /></strong></div>
                        <div><span>Total pesanan</span><strong><x-price :amount="$order->total" /></strong></div>
                    </div>
                </section>

                <section class="owner-panel" aria-labelledby="owner-order-state-title">
                    <h2 id="owner-order-state-title">Status pembayaran dan pengiriman</h2>
                    <div class="owner-state-grid">
                        <div class="owner-state-card">
                            <span>Pembayaran</span>
                            @if ($order->payment)
                                <x-status-badge :status="$order->payment->status->value" />
                                @if ($order->payment->payment_method->value === 'cod')<small>Metode: Bayar di tempat (COD)</small>@endif
                                @if ($order->payment->xendit_invoice_id)<small>Invoice {{ $order->payment->xendit_invoice_id }}</small>@endif
                                @if ($order->payment->paid_at)<small>Dibayar {{ $order->payment->paid_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->payment->expired_at)<small>Kedaluwarsa {{ $order->payment->expired_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->payment->late_payment_detected_at)<x-alert tone="warning">Pembayaran diterima setelah pesanan kedaluwarsa.</x-alert>@endif
                            @else
                                <span class="owner-muted">Belum ada catatan pembayaran</span>
                            @endif
                        </div>
                        <div class="owner-state-card">
                            <span>Pengiriman</span>
                            @if ($order->shipment)
                                <x-status-badge :status="$order->status->value" />
                                <small>Metode: {{ $order->shipment->method === 'manual' ? 'Manual' : $order->shipment->method }}</small>
                                @if ($order->shipment->tracking_number)<small>Resi: {{ $order->shipment->tracking_number }}</small>@endif
                                @if ($order->shipment->tracking_url)<a href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Buka tautan pelacakan</a>@endif
                                @if ($order->shipment->shipped_at)<small>Dikirim {{ $order->shipment->shipped_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->shipment->delivered_at)<small>Diterima {{ $order->shipment->delivered_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                            @if ($order->payment?->payment_method?->value === 'cod')
                                <small>Pembayaran: Bayar di tempat (COD)</small>
                                @if ($order->payment->status->value === 'pending')<small>Uang tunai belum dikonfirmasi diterima.</small>@endif
                            @endif
                            @else
                                <span class="owner-muted">Belum dikirim</span>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="owner-panel" aria-labelledby="owner-order-requests-title">
                    <h2 id="owner-order-requests-title">Pembatalan dan pengembalian dana</h2>
                    <div class="owner-state-grid">
                        <div class="owner-state-card">
                            <span>Permintaan pembatalan</span>
                            @if ($order->latestCancellationRequest)
                                <x-status-badge :status="$order->latestCancellationRequest->status->value" />
                                <small>Alasan: {{ $order->latestCancellationRequest->reason }}</small>
                                @if ($order->latestCancellationRequest->rejection_reason)<small>Catatan keputusan: {{ $order->latestCancellationRequest->rejection_reason }}</small>@endif
                                <small>Diajukan {{ $order->latestCancellationRequest->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>
                            @else
                                <span class="owner-muted">Tidak ada permintaan pembatalan</span>
                            @endif
                        </div>
                        <div class="owner-state-card">
                            <span>Pengembalian dana</span>
                            @if ($order->refund)
                                <x-status-badge :status="$order->refund->status->value" />
                                <small>Jumlah: <x-price :amount="$order->refund->amount" /></small>
                                @if ($order->refund->reason)<small>Alasan: {{ $order->refund->reason }}</small>@endif
                                @if ($order->refund->refunded_at)<small>Dicatat {{ $order->refund->refunded_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}</small>@endif
                                @if ($order->refund->status->value === 'refunded')
                                    <small>Dicatat oleh {{ $order->refund->refundedBy?->name ?? 'pemilik toko' }}</small>
                                    <p>Transfer dana dilakukan secara manual. Barang yang dipilih untuk stok ulang:</p>
                                    @php
                                        $restockedItemIds = array_map('intval', $order->refund->resellable_item_ids ?? []);
                                    @endphp
                                    @if (count($restockedItemIds))
                                        <ul class="owner-refund-item-list">
                                            @foreach ($order->items->whereIn('id', $restockedItemIds) as $refundedItem)
                                                <li>{{ $refundedItem->product_snapshot['name'] ?? 'Produk' }} · {{ $refundedItem->variant_snapshot['name'] ?? 'Varian' }} ({{ $refundedItem->quantity }} unit)</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <small>Tidak ada barang yang dipilih untuk stok ulang.</small>
                                    @endif
                                    @if ($order->refund->notes)<small>Catatan: {{ $order->refund->notes }}</small>@endif
                                @else
                                    <p>Transfer dana dilakukan secara manual di luar aplikasi. Setelah selesai, catat penyelesaiannya lewat panel aksi di kanan.</p>
                                @endif
                            @else
                                <span class="owner-muted">Tidak ada catatan pengembalian dana</span>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="owner-panel" aria-labelledby="owner-order-activity-title">
                    <h2 id="owner-order-activity-title">Aktivitas terbaru</h2>
                    @if ($auditLogs->isEmpty())
                        <p class="owner-muted">Belum ada aktivitas tercatat.</p>
                    @else
                        <ol class="owner-activity-list">
                            @foreach ($auditLogs as $log)
                                <li>
                                    <div>
                                        <strong>{{ $log->display_event }}</strong>
                                        <small>{{ $log->display_actor }}</small>
                                        @if ($log->display_summary)<small>{{ $log->display_summary }}</small>@endif
                                    </div>
                                    <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</time>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                    @if ($hasMoreAuditLogs)<small class="owner-activity-note">Menampilkan 20 aktivitas terbaru.</small>@endif
                </section>
            </div>

            <aside class="owner-panel owner-order-buyer" aria-labelledby="owner-order-buyer-title">
                <h2 id="owner-order-buyer-title">Pembeli</h2>
                @if ($order->customer)
                    <span class="owner-buyer-type">Pelanggan</span>
                    <strong>{{ $order->customer->name }}</strong>
                    <a href="mailto:{{ $order->customer->email }}">{{ $order->customer->email }}</a>
                @else
                    <span class="owner-buyer-type">Tamu</span>
                    <a href="mailto:{{ $order->guest_email }}">{{ $order->guest_email }}</a>
                    @if ($order->guest_phone)<a href="tel:{{ $order->guest_phone }}">{{ $order->guest_phone }}</a>@endif
                @endif

                <hr>
                <h3>Alamat pengiriman</h3>
                @if ($order->address)
                    <strong>{{ $order->address->recipient_name }}</strong>
                    <p>{{ $order->address->phone }}<br>{{ $order->address->address_line1 }}@if ($order->address->address_line2), {{ $order->address->address_line2 }}@endif<br>{{ $order->address->city }}, {{ $order->address->province }} {{ $order->address->postal_code }}<br>{{ $order->address->country }}</p>
                @else
                    <p class="owner-muted">Alamat tidak tersedia.</p>
                @endif

                @php
                    $canAcceptOrReject = $order->status->value === 'awaiting_confirmation';
                    $canShip = $order->status->value === 'processing' && !$order->shipment;
                    $canConfirmDelivery = $order->status->value === 'shipped' && $order->shipment && !$order->shipment->delivered_at;
                    $hasPendingCancelReview = $order->latestCancellationRequest && $order->latestCancellationRequest->status->value === 'pending' && !$order->latestCancellationRequest->decided_at;
                    $canMarkRefunded = $order->refund?->status->value === 'manual_refund_pending';
                    $canOwnerCancel = in_array($order->status->value, ['pending_payment', 'processing', 'shipped'], true);
                    $hasAnyOwnerAction = $canAcceptOrReject || $canShip || $canConfirmDelivery || $hasPendingCancelReview || $canMarkRefunded || $canOwnerCancel;
                @endphp

                @if ($hasAnyOwnerAction)
                    <hr>
                    <h3>Aksi pesanan</h3>
                    <div class="sidebar-actions">
                        @if ($canAcceptOrReject)
                            <form method="post" action="{{ route('owner.orders.accept', $order->id) }}" onsubmit="return confirm('Terima pesanan ini?');">
                                @csrf
                                <button class="button button-primary" type="submit">Terima Pesanan</button>
                            </form>
                            <button class="button button-danger" type="button" data-open-modal="modal-owner-reject">Tolak Pesanan</button>
                        @endif

                        @if ($canShip)
                            <button class="button button-primary" type="button" data-open-modal="modal-owner-shipment">Catat pengiriman</button>
                        @endif

                        @if ($canConfirmDelivery)
                            <button class="button button-primary" type="button" data-open-modal="modal-owner-delivery">Tandai telah diterima</button>
                        @endif

                        @if ($hasPendingCancelReview)
                            <form method="post" action="{{ route('owner.orders.cancellations.review', [$order->id, $order->latestCancellationRequest->id]) }}" onsubmit="return confirm('Setujui permintaan pembatalan ini? Pesanan akan dibatalkan.');">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <button class="button button-primary" type="submit">Setujui pembatalan</button>
                            </form>
                            <button class="button button-danger" type="button" data-open-modal="modal-owner-reject-cancel">Tolak permintaan</button>
                        @endif

                        @if ($canMarkRefunded)
                            <button class="button button-primary" type="button" data-open-modal="modal-owner-refund">Catat pengembalian dana</button>
                        @endif

                        @if ($canOwnerCancel)
                            <button class="button button-danger" type="button" data-open-modal="modal-owner-cancel">Batalkan pesanan</button>
                        @endif
                    </div>
                @endif

                @if (!empty($whatsAppNotification))
                    <hr>
                    <h3>Notifikasi WhatsApp</h3>
                    <p class="owner-muted" style="margin-bottom: 10px;">Template pesan otomatis menyesuaikan status pesanan saat ini.</p>
                    <a
                        class="button"
                        href="{{ $whatsAppNotification['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: #16a34a; border-color: #16a34a; color: #ffffff; text-decoration: none; border-radius: 6px;"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/>
                        </svg>
                        <span>{{ $whatsAppNotification['label'] }}</span>
                    </a>
                @endif
            </aside>
        </div>

        {{-- Modals for Owner Actions --}}
        @if ($canAcceptOrReject)
            <dialog id="modal-owner-reject" class="action-modal" aria-labelledby="modal-owner-reject-title">
                <div class="action-modal-header">
                    <h3 id="modal-owner-reject-title">Tolak Pesanan</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ route('owner.orders.reject', $order->id) }}">
                    @csrf
                    <div class="action-modal-body">
                        <p>Cadangan stok akan langsung dilepaskan dan alasan penolakan akan ditampilkan kepada pembeli.</p>
                        <div class="form-field">
                            <label class="field-label" for="order-rejection-reason">Alasan penolakan <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea id="order-rejection-reason" name="rejection_reason" maxlength="2000" placeholder="Contoh: Stok fisik di toko sedang habis." required>{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="action-modal-footer">
                        <button type="button" class="button button-secondary" data-close-modal>Batal</button>
                        <button type="submit" class="button button-danger">Konfirmasi Penolakan</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canShip)
            <dialog id="modal-owner-shipment" class="action-modal" aria-labelledby="modal-owner-shipment-title">
                <div class="action-modal-header">
                    <h3 id="modal-owner-shipment-title">Catat Pengiriman</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ route('owner.orders.shipment.store', $order->id) }}">
                    @csrf
                    <div class="action-modal-body">
                        <div class="form-field">
                            <label class="field-label" for="tracking-number">Nomor resi <span class="required-mark" aria-hidden="true">*</span></label>
                            <input id="tracking-number" name="tracking_number" type="text" maxlength="255" value="{{ old('tracking_number') }}" required>
                        </div>
                        <div class="form-field">
                            <label class="field-label" for="tracking-url">Tautan pelacakan <span>(opsional)</span></label>
                            <input id="tracking-url" name="tracking_url" type="url" maxlength="2048" value="{{ old('tracking_url') }}" placeholder="https://">
                        </div>
                    </div>
                    <div class="action-modal-footer">
                        <button type="button" class="button button-secondary" data-close-modal>Batal</button>
                        <button type="submit" class="button button-primary">Simpan & Kirim</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canConfirmDelivery)
            <dialog id="modal-owner-delivery" class="action-modal" aria-labelledby="modal-owner-delivery-title">
                <div class="action-modal-header">
                    <h3 id="modal-owner-delivery-title">Konfirmasi Pesanan Diterima</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ route('owner.orders.delivery.confirm', $order->id) }}">
                    @csrf
                    <div class="action-modal-body">
                        <p>Status pesanan akan berubah menjadi <strong>Completed (Selesai)</strong>.</p>
                        @if ($order->payment?->payment_method?->value === 'cod')
                            <label class="owner-cod-confirmation" style="cursor: pointer;">
                                <input type="checkbox" name="cash_received" value="1" required>
                                <span>Saya mengonfirmasi uang COD sebesar <x-price :amount="$order->payment->amount" /> telah diterima.</span>
                            </label>
                        @endif
                    </div>
                    <div class="action-modal-footer">
                        <button type="button" class="button button-secondary" data-close-modal>Batal</button>
                        <button type="submit" class="button button-primary">Konfirmasi Diterima</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($hasPendingCancelReview)
            <dialog id="modal-owner-reject-cancel" class="action-modal" aria-labelledby="modal-owner-reject-cancel-title">
                <div class="action-modal-header">
                    <h3 id="modal-owner-reject-cancel-title">Tolak Permintaan Pembatalan</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ route('owner.orders.cancellations.review', [$order->id, $order->latestCancellationRequest->id]) }}">
                    @csrf
                    <input type="hidden" name="decision" value="reject">
                    <div class="action-modal-body">
                        <div class="form-field">
                            <label class="field-label" for="rejection-reason">Alasan penolakan <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea id="rejection-reason" name="rejection_reason" maxlength="2000" required>{{ old('rejection_reason') }}</textarea>
                        </div>
                    </div>
                    <div class="action-modal-footer">
                        <button type="button" class="button button-secondary" data-close-modal>Batal</button>
                        <button type="submit" class="button button-danger">Konfirmasi Penolakan</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canMarkRefunded)
            <dialog id="modal-owner-refund" class="action-modal" aria-labelledby="modal-owner-refund-title">
                <div class="action-modal-header">
                    <h3 id="modal-owner-refund-title">Catat Pengembalian Dana Selesai</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ route('owner.orders.refund.mark-refunded', $order->id) }}">
                    @csrf
                    <div class="action-modal-body">
                        <p>Pastikan transfer dana sudah dilakukan di luar aplikasi sebelum menyimpan catatan ini.</p>
                        <fieldset class="owner-restock-options">
                            <legend>Pilih barang yang layak dijual kembali</legend>
                            @if (count($eligibleResellableItemIds))
                                @foreach ($order->items->whereIn('id', $eligibleResellableItemIds) as $restockItem)
                                    <label class="owner-restock-option" style="cursor: pointer;">
                                        <input type="checkbox" name="resellable_item_ids[]" value="{{ $restockItem->id }}" @checked(in_array($restockItem->id, old('resellable_item_ids', [])))>
                                        <span>{{ $restockItem->product_snapshot['name'] ?? 'Produk' }} · {{ $restockItem->variant_snapshot['name'] ?? 'Varian' }} <small>{{ $restockItem->quantity }} unit</small></span>
                                    </label>
                                @endforeach
                                <small>Stok hanya ditambahkan untuk barang yang dipilih.</small>
                            @else
                                <p class="owner-muted">Tidak ada barang yang memenuhi syarat stok ulang untuk pesanan ini.</p>
                            @endif
                        </fieldset>
                        <div class="form-field">
                            <label class="field-label" for="refund-notes">Catatan transfer manual <span>(opsional)</span></label>
                            <textarea id="refund-notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                    <div class="action-modal-footer">
                        <button type="button" class="button button-secondary" data-close-modal>Batal</button>
                        <button type="submit" class="button button-primary">Simpan Catatan Refund</button>
                    </div>
                </form>
            </dialog>
        @endif

        @if ($canOwnerCancel)
            <dialog id="modal-owner-cancel" class="action-modal" aria-labelledby="modal-owner-cancel-title">
                <div class="action-modal-header">
                    <h3 id="modal-owner-cancel-title">Batalkan Pesanan</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ route('owner.orders.cancel', $order->id) }}">
                    @csrf
                    <div class="action-modal-body">
                        @if ($order->status->value === 'pending_payment')
                            <x-alert tone="info" style="width: 100%; margin: 0;">Pesanan belum dibayar. Pembatalan akan langsung mengunci tautan pembayaran QRIS dan melepaskan cadangan stok.</x-alert>
                        @elseif ($order->payment?->status->value === 'paid')
                            <x-alert tone="warning" style="width: 100%; margin: 0;">Pembayaran sudah diterima. Proses pengembalian dana (refund) dilakukan di luar aplikasi.</x-alert>
                        @endif
                        <div class="form-field">
                            <label class="field-label" for="owner-cancel-reason">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea id="owner-cancel-reason" name="reason" maxlength="2000" required>{{ old('reason') }}</textarea>
                        </div>
                    </div>
                    <div class="action-modal-footer">
                        <button type="button" class="button button-secondary" data-close-modal>Batal</button>
                        <button type="submit" class="button button-danger">Konfirmasi Pembatalan</button>
                    </div>
                </form>
            </dialog>
        @endif
    </section>
@endsection
