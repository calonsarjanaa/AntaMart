@extends('layouts.owner')

@section('title', 'Permintaan pembatalan')

@section('breadcrumbs')
    <a href="{{ route('owner.dashboard') }}">Dashboard</a><span aria-hidden="true">/</span><span>Permintaan pembatalan</span>
@endsection

@section('content')
    <section class="owner-order-page" aria-labelledby="owner-cancellations-title">
        <div class="owner-page-heading">
            <div><p class="section-eyebrow">TINDAKAN PEMESANAN</p><h1 id="owner-cancellations-title">Permintaan pembatalan</h1></div>
            <a class="button button-secondary" href="{{ route('owner.orders.index') }}">Lihat semua pesanan</a>
        </div>

        @if ($requests->isEmpty())
            <div class="owner-empty-panel">
                <h2>Tidak ada permintaan yang menunggu</h2>
                <p>Permintaan pembatalan untuk pesanan yang masih diproses akan muncul di sini.</p>
            </div>
        @else
            <div class="owner-panel">
                <div class="owner-table-wrap">
                    <table class="owner-table">
                        <thead><tr><th>Pesanan</th><th>Pembeli</th><th>Alasan</th><th>Diajukan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($requests as $cancellationRequest)
                                <tr>
                                    <td><a class="owner-order-id" href="{{ route('owner.orders.show', $cancellationRequest->order_id) }}">{{ $cancellationRequest->order_id }}</a><small>Total <x-price :amount="$cancellationRequest->order->total" /></small></td>
                                    <td>
                                        @if ($cancellationRequest->order->customer)
                                            <span class="owner-buyer-name">{{ $cancellationRequest->order->customer->name }}</span><small>{{ $cancellationRequest->order->customer->email }}</small>
                                        @else
                                            <span class="owner-buyer-type">Tamu</span><small>{{ $cancellationRequest->order->guest_email }}</small>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($cancellationRequest->reason, 120) }}</td>
                                    <td>{{ $cancellationRequest->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</td>
                                    <td><a class="owner-table-action" href="{{ route('owner.orders.show', $cancellationRequest->order_id) }}">Tinjau</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="owner-pagination">{{ $requests->links() }}</div>
            </div>
        @endif
    </section>
@endsection
