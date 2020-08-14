<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Exception;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    //products list
    public function index(Request $request){
        //create a $builder for all query on products for sale
        $builder = Product::query()->where('on_sale', true);
        
        //check if there is parameter in input field named "search", if there is, assing that value to varible $search
        if ($search = $request->input('search', '')){
            //set the search pattern
            $like = '%' . $search . '%';
            //with above pattern, we do fuzz search on product titles, product deatails, SKU titles, SKU descriptions
            $builder->where(function($query)use($like){
                $query->where('title', 'like', $like)
                      ->orWhere('description', 'like', $like)
                      ->orWhereHas('skus', function($query)use($like){
                          $query->where('title', 'like', $like)
                                ->orWhere('description', 'like', $like);
                      });
            });
        }

        //Check if there is sorting parameter being selected by customer in 'order' field, if there is, assign value to $order
        if($order = $request->input('order', '')){
            //check if this sorting method ends with _asc or _desc
            if(preg_match('/^(.+)_(asc|desc)$/', $order, $m)){
                //if the sorting method starts with one of the 3 followings, it will be a legal sorting value(method)
                if(in_array($m[1], ['price', 'sold_count', 'rating'])){
                    //build the sorting parameters with the legal parts, and use them to sort
                    $builder->orderBy($m[1],$m[2]);
                }
            }
        }
        
        //$products = Product::query()->where('on_sale', true)->paginate(16);
        $products = $builder->paginate(16);
        return view('products.index', [
            'products' => $products, 
            'filters' =>[
                'search' => $search, 
                'order' => $order
                ]
            ]);
    }

    //show product details
    public function show (Product $product, Request $request){
        //check if the selected product is for sale, if not, throw an exception
        if(!$product->on_sale){
            throw new Exception('This product is not for sale.');
        }

        return view('products.show', ['product' => $product]);
    }
}
