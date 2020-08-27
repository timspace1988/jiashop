<?php

namespace App\Http\Controllers;

use App\Events\OrderReviewed;
use App\Exceptions\InvalidRequestException;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\SendReviewRequest;
use App\Jobs\CloseOrder;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\UserAddress;
use App\Services\CartService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    //Create order
    //Note: $request can only appear in controler and middleware, do not put in a package class
    public function store(OrderRequest $request, OrderService $orderService){
        $user = $request->user();

        $address = UserAddress::find($request->input('address_id'));

        // try{
        //     return $orderService->store($user, $address, $request->input('remark'), $request->input('items'));
        // }catch(\Throwable $t){
        //     return ['msg' => $t->getMessage()];
        // }

        return $orderService->store($user, $address, $request->input('remark'), $request->input('items'));

        /*

        //
        //open a database affair using \DB::tansaction(), all sql operations in the callback function will be included in this affair
        //if any exception is throwed by this callback, the whole of this affair will be rolled back, otherwise it will submit this affair to database
        //

        try{

        $order = \DB::transaction(function() use($user, $request, $cartService){
            //Get the address select by user, and update its last used time
            $address = UserAddress::find($request->input('address_id'));
            $address->update(['last_used_at' => Carbon::now()]);
            //create an order
            $order = new Order([
                //put the selected address in an array which will be saved as a json type data into database
                'address' => [
                    'address' => $address->full_address,
                    'zip' => $address->zip,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark' => $request->input('remark'),
                'total_amount' => 0,
            ]);
            //associate the order with current user
            $order->user()->associate($user);
            //write into database
            $order->save();

            $totalAmount = 0;
            $items = $request->input('items');
            //Do a iteration on each sku submited by user
            foreach($items as $data){
                $sku = ProductSku::find($data['sku_id']);

                //create an OrderItem and directly get it associate with this order (but not write into  database)
                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price' => $sku->price,
                ]);
                $item->product()->associate($sku->product_id);
                $item->productSku()->associate($sku);
                //write this order item into database
                $item->save();
                $totalAmount += $sku->price * $data['amount'];
                //decrease this item's sku stock
                if($sku->decreaseStock($data['amount']) <= 0){//decreaseStock($data['amount']) will return the number of affected lines, if it is not a positive num, it means decrease failed
                    throw new InvalidRequestException('This product does not have enough stock');
                }
            }

            //Update the total amount of this order
            $order->update(['total_amount' => $totalAmount]);

            //Remove the order items from your cart
            $skuIds = collect($items)->pluck('sku_id')->all();
            //$user->cartItems()->whereIn('product_sku_id', $skuIds)->delete();
            $cartService->remove($skuIds);
            
            return $order;
        });

        }catch(\Throwable $t){
            return ['msg' => $t->getMessage()];
        }

        //after an order is created(placed), we need to trigger a CloseOrder job (close order after some time if not paid) and dispatch it into queue
        $this->dispatch(new CloseOrder($order, config('app.order_ttl')));
        return $order;

        */
    }

    //show order list for customer
    public function index(Request $request){
        $orders = Order::query()->with(['items.product', 'items.productSku'])//avoid N+1 problem
                               ->where('user_id', $request->user()->id)
                               ->orderBy('created_at', 'desc')
                               ->paginate();
        
        return view('orders.index', ['orders' => $orders]);
    }

    //order details page
    public function show(Order $order, Request $request){
        //Only order's owner can see his order's details
        $this->authorize('own', $order);
        return view('orders.show', ['order' => $order->load(['items.product', 'items.productSku'])]);
    }

    //cusotomer get order received
    public function received(Order $order, Request $request){
        //dd('hello');
        //check if the order belongs to current user
        $this->authorize('own', $order);

        //Check if the order is currently at a in-delivery status(this is the only right status before a customer can click receive button)
        if($order->ship_status !==Order::SHIP_STATUS_DELIVERED){
            throw new InvalidRequestException('Incorrect shipping status.');
        }

        //update shipping status to received
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        //return to previous page
        //return redirect()->back();

        //As we changed to use ajax sending received request, we need to change the return
        return $order; 
    }

    //display order review page/form of an order for customer(to view/write review)
    public function review(Order $order){
        //check if order belongs to current user
        $this->authorize('own', $order);

        //check if order has been paid (only paid order can be given reviews and rate by customer)
        if(!$order->paid_at){
            throw new InvalidRequestException('This order is not paid yet, you can not give a review.');
        }

        //load method can help avoid n+1
        return view('orders.review', ['order' => $order->load(['items.productSku', 'items.product'])]);
    }

    //customer send their review for an order
    public function sendReview(Order $order, SendReviewRequest $request){
        //check if the order being reviewed belongs to current user
        $this->authorize('own', $order);

        //Only paid order can be given review ans rate
        if(!$order->paid_at){
            throw new InvalidRequestException('This order is not paid yet, you can not give a review');
        }

        //If the order has already been reviewed, cuustomer cannot re-submit review
        if($order->reviewed){
            throw new InvalidRequestException('You have already given a reivew to this order, do not submit again');
        }

        $reviews = $request->input('reviews');

        //Use tansaction if failed, roll back
        \DB::transaction(function() use($reviews, $order){
            //iteration on each review data submited by customer
            foreach($reviews as $review){
                //find order-item been reviewed on that order (using order_item id in review data )
                $orderItem = $order->items()->find($review['id']);
                //Save this customer's review and rate for this order item
                $orderItem->update([
                    'rating' => $review['rating'],
                    'review' => $review['review'],
                    'reviewed_at' => Carbon::now(),
                ]); 
            }

            //mark this order as reviewed
            $order->update(['reviewed' => true]);

            //trigger an OrderReviewed event to calculate and update this order's related products' average rating and review counts
            event(new OrderReviewed($order));
        });

        //After review been submited, redirect back to revew display page
        return redirect()->back();
    }
}
