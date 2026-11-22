@extends('layouts.app')

@section('title', 'Status pesanan')

@section('content')
    @php
        $orderStatus = $order->status->value;
        $paymentStatus = $order->payment?->status?->value;
        $paymentMethod = $order->payment?->payment_method?->value;
        $latePayment = $orderStatus === 'expired' && $paymentStatus === 'paid';
    @endphp

    <section class="checkout-page page-container" aria-labelledby="order-result-title">
        <div class="section-heading">
            <div><p class="section-eyebrow">ANTAMART</p><h1 id="order-result-title">Status pesanan</h1></div>
            <a class="continue-shopping" href="{{ route('storefront.home') }}">← Kembali belanja</a>
        </div>

        <div class="order-result-layout">
            <div class="order-result-main">
                <section class="checkout-result-panel order-status-panel" aria-labelledby="order-status-title">
                    <p class="section-eyebrow">PESANAN {{ $order->id }}</p>
                    @if ($latePayment)
                        <h2 id="order-status-title">Pembayaran diterima setelah pesanan kedaluwarsa</h2>
                        <p>Pembayaran sudah dikonfirmasi, tetapi batas waktu pesanan telah lewat. Tim toko perlu meninjau pengembalian dana secara manual.</p>
                    @elseif ($orderStatus === 'awaiting_confirmation')
                        <h2 id="order-status-title">Menunggu konfirmasi toko</h2>
                        @if ($paymentMethod === 'cod')
                            <p>Pesanan COD kamu sudah tercatat. Toko sedang memeriksa ketersediaan stok fisik sebelum memproses pesananmu.</p>
                        @else
                            <p>Pesanan kamu sudah tercatat. Toko sedang memeriksa ketersediaan stok fisik terlebih dahulu. Setelah pesanan diterima, tombol <strong>Bayar Pesanan (QRIS)</strong> akan aktif di halaman ini.</p>
                        @endif
                    @elseif ($orderStatus === 'pending_payment')
                        <h2 id="order-status-title">Menunggu konfirmasi pembayaran</h2>
                        <p>Pesanan kamu sudah dikonfirmasi tersedia oleh toko! Silakan klik <strong>Bayar Pesanan</strong> untuk menyelesaikan pembayaran QRIS sebelum batas waktu berakhir.</p>
                    @elseif ($orderStatus === 'processing')
                        @if ($paymentMethod === 'cod' && $paymentStatus !== 'paid')
                            <h2 id="order-status-title">Pesanan COD sedang diproses</h2>
                            <p>Siapkan pembayaran tunai sebesar <x-price :amount="$order->total" /> saat pesanan diterima. Pembayaran dicatat setelah toko mengonfirmasi uang diterima.</p>
                        @else
                            <h2 id="order-status-title">Pembayaran dikonfirmasi</h2>
                            <p>Pembayaran telah dikonfirmasi oleh sistem. Pesanan sedang diproses oleh toko.</p>
                        @endif
                    @elseif ($orderStatus === 'expired')
                        <h2 id="order-status-title">Batas waktu pembayaran lewat</h2>
                        <p>Pesanan kedaluwarsa dan reservasi stok telah dilepas. Jika kamu sudah membayar, hubungi toko agar pembayaran dapat diperiksa.</p>
                    @elseif ($orderStatus === 'cancelled')
                        @if ($order->rejection_reason)
                            <h2 id="order-status-title">Pesanan ditolak oleh toko</h2>
                            <p>Mohon maaf, pesanan ini tidak dapat diproses oleh toko dan tidak ada dana yang ditarik.</p>
                        @else
                            <h2 id="order-status-title">Pesanan dibatalkan</h2>
                            <p>Pesanan ini telah dibatalkan. Jika pembayaran sudah dilakukan, proses pengembalian dana ditangani secara manual.</p>
                        @endif
                    @elseif ($orderStatus === 'shipped')
                        <h2 id="order-status-title">Pesanan dikirim</h2>
                        @if ($paymentMethod === 'cod' && $paymentStatus !== 'paid')
                            <p>Siapkan pembayaran tunai sebesar <x-price :amount="$order->total" /> saat pesanan diterima.</p>
                        @else
                            <p>Pesanan telah dikirim oleh toko.</p>
                        @endif
                    @elseif ($orderStatus === 'completed')
                        <h2 id="order-status-title">Pesanan selesai</h2>
                        <p>Pesanan telah ditandai selesai.</p>
                    @endif

                    <div class="order-result-badges">
                        <span>Status pesanan <x-status-badge :status="$orderStatus" /></span>
                        <span>Status pembayaran <x-status-badge :status="$paymentStatus ?? 'pending'" /></span>
                    </div>
                    @if ($paymentMethod)
                        <p class="order-result-note">Metode pembayaran: {{ $paymentMethod === 'cod' ? 'Bayar di tempat (COD)' : 'QRIS' }}</p>
                    @endif

                    @if ($latePayment)
                        <x-alert tone="warning" class="order-result-alert">
                            @if ($order->refund?->status?->value === 'refunded')
                                Pengembalian dana manual telah dicatat pada {{ $order->refund->refunded_at?->format('d M Y, H:i') }}.
                            @else
                                Pengembalian dana manual belum tercatat selesai. Simpan ID pesanan ini untuk pertanyaan kepada toko.
                            @endif
                        </x-alert>
                    @elseif ($order->refund?->status?->value === 'manual_refund_pending')
                        <x-alert tone="warning" class="order-result-alert">Pengembalian dana sedang ditangani secara manual oleh toko.</x-alert>
                    @elseif ($order->refund?->status?->value === 'refunded')
                        <x-alert tone="success" class="order-result-alert">Pengembalian dana manual telah dicatat selesai.</x-alert>
                    @endif

                    @if ($order->rejection_reason)
                        <x-alert tone="error" class="order-result-alert">
                            <strong>Alasan penolakan toko:</strong> {{ $order->rejection_reason }}
                        </x-alert>
                    @endif

                    @if ($orderStatus === 'pending_payment')
                        @if ($order->payment?->xendit_payment_url && $order->expires_at?->isFuture())
                            <p class="order-result-note">Batas pembayaran: {{ $order->expires_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}.</p>
                        @elseif ($order->expires_at && $order->expires_at->isPast())
                            <p class="order-result-note">Batas waktu pembayaran telah lewat. Muat ulang halaman ini untuk melihat status terbaru.</p>
                        @endif
                    @endif
                </section>

                <section class="checkout-form-section order-items-panel" aria-labelledby="order-items-title">
                    <h2 id="order-items-title">Rincian produk</h2>
                    @foreach ($order->items as $item)
                        <div class="order-result-item">
                            <div>
                                <strong>{{ $item->product_snapshot['name'] ?? 'Produk' }}</strong>
                                <span>{{ $item->variant_snapshot['name'] ?? $item->sku }} · {{ $item->quantity }} item</span>
                            </div>
                            <strong><x-price :amount="$item->subtotal" /></strong>
                        </div>
                    @endforeach
                    <div class="order-result-totals">
                        <div><span>Subtotal produk</span><strong><x-price :amount="$order->subtotal" /></strong></div>
                        <div><span>Ongkos kirim</span><strong><x-price :amount="$order->shipping_cost" /></strong></div>
                        <div><span>Total</span><strong><x-price :amount="$order->total" /></strong></div>
                    </div>
                </section>
            </div>

            <aside class="checkout-form-section order-result-meta">
                <h2>Informasi pesanan</h2>
                <div><span>Nomor pesanan</span><strong>{{ $order->id }}</strong></div>
                <div><span>Dibuat</span><strong>{{ $order->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</strong></div>
                @if ($order->shipment?->tracking_number)
                    <div><span>Nomor resi</span><strong>{{ $order->shipment->tracking_number }}</strong></div>
                    @if ($order->shipment->tracking_url)<a class="text-link" href="{{ $order->shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">Lacak pengiriman</a>@endif
                @endif
                @if ($order->latestCancellationRequest)
                    <div><span>Permintaan pembatalan</span><x-status-badge :status="$order->latestCancellationRequest->status->value" /></div>
                @endif
                @if ($order->refund)
                    <div><span>Status pengembalian dana</span><x-status-badge :status="$order->refund->status->value" /></div>
                @endif

                @if (in_array($orderStatus, ['awaiting_confirmation', 'pending_payment'], true))
                    <hr>
                    <h3>Aksi pesanan</h3>
                    <div class="sidebar-actions">
                        @if ($orderStatus === 'awaiting_confirmation')
                            @if (!empty($storeWhatsApp))
                                <a
                                    class="button"
                                    href="{{ $storeWhatsApp['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="background: #16a34a; border-color: #16a34a; color: #ffffff; text-decoration: none;"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/>
                                    </svg>
                                    <span>{{ $storeWhatsApp['label'] }}</span>
                                </a>
                            @endif
                            <button class="button button-danger" type="button" data-open-modal="modal-result-cancel">Batalkan Pesanan</button>
                            <a class="order-result-refresh" href="{{ request()->fullUrl() }}">Muat ulang status</a>
                        @elseif ($orderStatus === 'pending_payment')
                            @if ($order->payment?->xendit_payment_url && $order->expires_at?->isFuture())
                                <a class="button button-primary" href="{{ $payUrl }}">Bayar Pesanan</a>
                            @elseif (!$order->expires_at)
                                <form method="post" action="{{ $retryUrl }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Coba buat tautan pembayaran lagi</button>
                                </form>
                            @endif

                            @if ($paymentMethod === 'xendit' && (!$order->expires_at || $order->expires_at->isFuture()))
                                <form method="post" action="{{ $switchCodUrl }}" onsubmit="return confirm('Alihkan metode pembayaran pesanan ini ke Bayar di Tempat (COD)?');">
                                    @csrf
                                    <button class="button button-secondary" type="submit">Beralih ke Bayar di Tempat (COD)</button>
                                </form>
                                <button class="button button-danger" type="button" data-open-modal="modal-result-cancel">Batalkan Pesanan</button>
                            @endif
                            <a class="order-result-refresh" href="{{ request()->fullUrl() }}">Muat ulang status</a>
                        @endif
                    </div>
                @endif
            </aside>
        </div>

        @if (in_array($orderStatus, ['awaiting_confirmation', 'pending_payment'], true))
            <dialog id="modal-result-cancel" class="action-modal" aria-labelledby="modal-result-cancel-title">
                <div class="action-modal-header">
                    <h3 id="modal-result-cancel-title">Batalkan Pesanan</h3>
                    <button type="button" class="action-modal-close" data-close-modal aria-label="Tutup">&times;</button>
                </div>
                <form method="post" action="{{ $cancelUrl }}">
                    @csrf
                    <div class="action-modal-body">
                        <p>Pesanan yang belum dibayar akan langsung dibatalkan, cadangan stok dilepaskan, dan produk dikembalikan ke keranjang Anda.</p>
                        <div class="form-field">
                            <label class="field-label" for="modal-result-cancel-reason">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea id="modal-result-cancel-reason" name="reason" maxlength="2000" placeholder="Contoh: Ingin mengubah jumlah atau produk" required>{{ old('reason') }}</textarea>
                            @error('reason')<p class="field-error">{{ $message }}</p>@enderror
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
