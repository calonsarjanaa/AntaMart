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

                    @if ($orderStatus === 'awaiting_confirmation')
                        <div class="order-result-secondary-actions" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; align-items: flex-start;">
                            @if (!empty($storeWhatsApp))
                                <a
                                    class="button"
                                    href="{{ $storeWhatsApp['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    style="display: inline-flex; align-items: center; gap: 8px; background: #16a34a; border-color: #16a34a; color: #ffffff; text-decoration: none;"
                                >
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/>
                                    </svg>
                                    <span>{{ $storeWhatsApp['label'] }}</span>
                                </a>
                            @endif
                        </div>
                        <form method="post" action="{{ $cancelUrl }}" style="margin-top: 14px;" onsubmit="return confirm('Batalkan pesanan ini dan kembalikan produk ke keranjang?');">
                            @csrf
                            <div class="form-field" style="margin-bottom: 8px;">
                                <label class="field-label" for="result-cancel-reason-awaiting">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                                <input id="result-cancel-reason-awaiting" name="reason" type="text" class="field-input" maxlength="2000" placeholder="Contoh: Ingin mengubah jumlah atau produk" required value="{{ old('reason') }}">
                            </div>
                            <button class="button button-danger" type="submit">Batalkan Pesanan</button>
                        </form>
                        <a class="order-result-refresh" href="{{ request()->fullUrl() }}">Muat ulang status</a>
                    @elseif ($orderStatus === 'pending_payment')
                        @if ($order->payment?->xendit_payment_url && $order->expires_at?->isFuture())
                            <a class="button button-primary order-result-action" href="{{ $payUrl }}">Bayar Pesanan</a>
                            <p class="order-result-note">Batas pembayaran: {{ $order->expires_at->timezone(config('app.timezone'))->format('d M Y, H:i') }}.</p>
                        @elseif (!$order->expires_at)
                            <form method="post" action="{{ $retryUrl }}">
                                @csrf
                                <button class="button button-primary order-result-action" type="submit">Coba buat tautan pembayaran lagi</button>
                            </form>
                        @else
                            <p class="order-result-note">Batas waktu pembayaran telah lewat. Muat ulang halaman ini untuk melihat status terbaru.</p>
                        @endif

                        @if ($paymentMethod === 'xendit' && (!$order->expires_at || $order->expires_at->isFuture()))
                            <div class="order-result-secondary-actions" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px;">
                                <form method="post" action="{{ $switchCodUrl }}" style="margin: 0;">
                                    @csrf
                                    <button class="button button-secondary" type="submit">Beralih ke Bayar di Tempat (COD)</button>
                                </form>
                            </div>
                            <form method="post" action="{{ $cancelUrl }}" style="margin-top: 14px;" onsubmit="return confirm('Batalkan pesanan ini dan kembalikan produk ke keranjang?');">
                                @csrf
                                <div class="form-field" style="margin-bottom: 8px;">
                                    <label class="field-label" for="result-cancel-reason-pending">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                                    <input id="result-cancel-reason-pending" name="reason" type="text" class="field-input" maxlength="2000" placeholder="Contoh: Berubah pikiran" required value="{{ old('reason') }}">
                                </div>
                                <button class="button button-danger" type="submit">Batalkan Pesanan</button>
                            </form>
                        @endif

                        <a class="order-result-refresh" href="{{ request()->fullUrl() }}">Muat ulang status</a>
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
            </aside>
        </div>
    </section>
@endsection
