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
                                @if ($order->status->value === 'shipped' && !$order->shipment->delivered_at)
                                    <form class="owner-shipment-action" method="post" action="{{ route('owner.orders.delivery.confirm', $order->id) }}" onsubmit="return confirm('Tandai pesanan ini telah diterima? Status akan berubah menjadi selesai.');">
                                        @csrf
                                        <button class="button" type="submit">Tandai telah diterima</button>
                                    </form>
                                @endif
                            @else
                                <span class="owner-muted">Belum dikirim</span>
                                @if ($order->status->value === 'processing')
                                    <form class="owner-shipment-action" method="post" action="{{ route('owner.orders.shipment.store', $order->id) }}" onsubmit="return confirm('Simpan resi dan tandai pesanan telah dikirim?');">
                                        @csrf
                                        <label class="field-label" for="tracking-number">Nomor resi <span class="required-mark" aria-hidden="true">*</span></label>
                                        <input id="tracking-number" name="tracking_number" type="text" maxlength="255" value="{{ old('tracking_number') }}" required>
                                        <label class="field-label" for="tracking-url">Tautan pelacakan <span>(opsional)</span></label>
                                        <input id="tracking-url" name="tracking_url" type="url" maxlength="2048" value="{{ old('tracking_url') }}" placeholder="https://">
                                        <button class="button" type="submit">Catat pengiriman</button>
                                    </form>
                                @endif
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
                                @if ($order->latestCancellationRequest->status->value === 'pending' && !$order->latestCancellationRequest->decided_at)
                                    <div class="owner-cancellation-actions">
                                        <form method="post" action="{{ route('owner.orders.cancellations.review', [$order->id, $order->latestCancellationRequest->id]) }}" onsubmit="return confirm('Setujui permintaan pembatalan ini? Pesanan akan dibatalkan.');">
                                            @csrf
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="button" type="submit">Setujui pembatalan</button>
                                        </form>
                                        <form method="post" action="{{ route('owner.orders.cancellations.review', [$order->id, $order->latestCancellationRequest->id]) }}" onsubmit="return confirm('Tolak permintaan pembatalan ini?');">
                                            @csrf
                                            <input type="hidden" name="decision" value="reject">
                                            <label class="field-label" for="rejection-reason">Alasan penolakan <span class="required-mark" aria-hidden="true">*</span></label>
                                            <textarea id="rejection-reason" name="rejection_reason" rows="3" maxlength="2000" required>{{ old('rejection_reason') }}</textarea>
                                            <button class="button button-secondary" type="submit">Tolak permintaan</button>
                                        </form>
                                    </div>
                                @endif
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
                                    @php($restockedItemIds = array_map('intval', $order->refund->resellable_item_ids ?? []))
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
                                    <p>Transfer dana harus dilakukan secara manual. Setelah transfer selesai, catat penyelesaiannya di bawah.</p>
                                @endif
                            @else
                                <span class="owner-muted">Tidak ada catatan pengembalian dana</span>
                            @endif
                        </div>
                    </div>
                    @if ($order->refund?->status->value === 'manual_refund_pending')
                        <div class="owner-refund-form">
                            <h3>Catat pengembalian dana selesai</h3>
                            <p>Pastikan transfer dana sudah dilakukan di luar sistem sebelum menyimpan catatan ini.</p>
                            <form method="post" action="{{ route('owner.orders.refund.mark-refunded', $order->id) }}" onsubmit="return confirm('Konfirmasi bahwa transfer dana sudah dilakukan? Sistem hanya mencatat penyelesaian ini.');">
                                @csrf
                                <fieldset class="owner-restock-options">
                                    <legend>Pilih barang yang layak dijual kembali</legend>
                                    @if (count($eligibleResellableItemIds))
                                        @foreach ($order->items->whereIn('id', $eligibleResellableItemIds) as $restockItem)
                                            <label class="owner-restock-option">
                                                <input type="checkbox" name="resellable_item_ids[]" value="{{ $restockItem->id }}" @checked(in_array($restockItem->id, old('resellable_item_ids', [])))>
                                                <span>{{ $restockItem->product_snapshot['name'] ?? 'Produk' }} · {{ $restockItem->variant_snapshot['name'] ?? 'Varian' }} <small>{{ $restockItem->quantity }} unit</small></span>
                                            </label>
                                        @endforeach
                                        <small>Stok hanya ditambahkan untuk barang yang dipilih.</small>
                                    @else
                                        <p class="owner-muted">Tidak ada barang yang memenuhi syarat stok ulang untuk pesanan ini.</p>
                                    @endif
                                </fieldset>
                                <label class="field-label" for="refund-notes">Catatan transfer manual <span>(opsional)</span></label>
                                <textarea id="refund-notes" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
                                <button class="button" type="submit">Simpan catatan pengembalian dana</button>
                            </form>
                        </div>
                    @endif
                    @if ($order->status->value === 'processing')
                        <div class="owner-cancel-order">
                            <h3>Batalkan pesanan sebagai pemilik</h3>
                            @if ($order->payment?->status->value === 'paid')
                                <x-alert tone="warning">Pembayaran sudah diterima. Setelah pembatalan, pengembalian dana perlu ditransfer dan dicatat secara manual.</x-alert>
                            @endif
                            <form method="post" action="{{ route('owner.orders.cancel', $order->id) }}" onsubmit="return confirm('Batalkan pesanan ini? Tindakan ini tidak dapat dibatalkan.');">
                                @csrf
                                <label class="field-label" for="owner-cancel-reason">Alasan pembatalan <span class="required-mark" aria-hidden="true">*</span></label>
                                <textarea id="owner-cancel-reason" name="reason" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea>
                                <button class="button" type="submit">Batalkan pesanan</button>
                            </form>
                        </div>
                    @endif
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
            </aside>
        </div>
    </section>
@endsection
