@extends('layouts.app')
@section('title', 'Order details')

@section('content')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header">
                <h4>Order details</h4>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-right item-amount">Subtotal</th>
                        </tr>
                    </thead>
                    @foreach($order->items as $index => $item)
                        <tr>
                            <td class="product-info">
                                <div class="preview">
                                    <a href="{{ route('products.show', [$item->product_id]) }}" target="_blank">
                                        <img src="{{ $item->product->image_url }}" alt="">
                                    </a>
                                </div>
                                <div>
                                    <span class="product-title">
                                        <a href="{{ route('products.show', [$item->product_id]) }}" target="_blank">{{ $item->product->title }}</a>
                                    </span>
                                    <span class="sku-title">{{ $item->productSku->title }}</span>
                                </div>
                            </td>
                            <td class="sku-price text-center vertical-middle">${{ $item->price }}</td>
                            <td class="sku-amount text-center vertical-middle">{{ $item->amount }}</td>
                            <td class="item-amount text-right vertical-middle">${{ number_format($item->price * $item->amount, 2, '.', '') }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="4"></td></tr>
                </table>
                <div class="order-bottom">
                    <div class="order-info">
                        <div class="line"><div class="line-label">Address :&nbsp;&nbsp;</div><div class="line-value">{{ join(' ', $order->address) }}</div></div>
                        <div class="line"><div class="line-label">Remark :&nbsp;&nbsp;&nbsp;</div><div class="line-value">{{ $order->remark ?: '-'  }}</div></div>
                        <div class="line"><div class="line-label">Order no :&nbsp;</div><div class="line-value">{{ $order->no }}</div></div> 
                    </div>
                    <div class="order-summary text-right">
                        <div class="total-amount">
                            <span>Total amount: </span>
                            <div class="value">${{ $order->total_amount }}</div>
                        </div>
                        <div>
                            <span>Order status: </span>
                            <div class="value">
                                @if($order->paid_at)
                                    @if($order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
                                        Paid
                                    @else
                                        {{ \App\Models\Order::refundStatusMap[$order->refund_status] }}
                                    @endif
                                @elseif($order->closed)
                                    Closed
                                @else
                                    Not paid
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection