<?php

namespace Webkul\RestApi\Http\Controllers\V1\Shop;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Webkul\API\Http\Resources\Checkout\Cart as CartResource;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartItemRepository;
use Webkul\Customer\Repositories\WishlistRepository;

class CustomerCartController extends Controller
{
    /**
     * Get the customer cart.
     *
     * @return \Illuminate\Http\Response
     */
    public function get()
    {
        $cart = Cart::getCart();

        return response([
            'data' => $cart ? new CartResource($cart) : null,
        ]);
    }

    /**
     * Add item to the cart.
     *
     * @param  int  $productId
     * @return \Illuminate\Http\Response
     */
    public function add(Request $request, WishlistRepository $wishlistRepository, $productId)
    {
        $customer = $request->user();

        try {
            Event::dispatch('checkout.cart.item.add.before', $productId);

            $result = Cart::addProduct($productId, $request->all());

            if (is_array($result) && isset($result['warning'])) {
                return response([
                    'message' => $result['warning'],
                ], 400);
            }

            $wishlistRepository->deleteWhere(['product_id' => $productId, 'customer_id' => $customer->id]);

            Event::dispatch('checkout.cart.item.add.after', $result);

            Cart::collectTotals();

            $cart = Cart::getCart();

            return response([
                'data'    => $cart ? new CartResource($cart) : null,
                'message' => __('shop::app.checkout.cart.item.success'),
            ]);
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update the cart.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CartItemRepository $cartItemRepository)
    {
        $this->validate($request, [
            'qty' => 'required|array',
        ]);

        foreach ($request->qty as $qty) {
            if ($qty <= 0) {
                return response([
                    'message' => __('shop::app.checkout.cart.quantity.illegal'),
                ], 400);
            }
        }

        foreach ($request->qty as $itemId => $qty) {
            $item = $cartItemRepository->findOneByField('id', $itemId);

            Event::dispatch('checkout.cart.item.update.before', $itemId);

            Cart::updateItems(['qty' => $request->qty]);

            Event::dispatch('checkout.cart.item.update.after', $item);
        }

        Cart::collectTotals();

        $cart = Cart::getCart();

        return response([
            'data'    => $cart ? new CartResource($cart) : null,
            'message' => __('shop::app.checkout.cart.quantity.success'),
        ]);
    }

    /**
     * Remove item from the cart.
     *
     * @param  int  $cartItemId
     * @return \Illuminate\Http\Response
     */
    public function removeItem($cartItemId)
    {
        Event::dispatch('checkout.cart.item.delete.before', $cartItemId);

        Cart::removeItem($cartItemId);

        Event::dispatch('checkout.cart.item.delete.after', $cartItemId);

        Cart::collectTotals();

        $cart = Cart::getCart();

        return response([
            'data'    => $cart ? new CartResource($cart) : null,
            'message' => __('shop::app.checkout.cart.item.success-remove'),
        ]);
    }

    /**
     * Empty the cart.
     *
     * @return \Illuminate\Http\Response
     */
    function empty() {
        Event::dispatch('checkout.cart.delete.before');

        Cart::deActivateCart();

        Event::dispatch('checkout.cart.delete.after');

        $cart = Cart::getCart();

        return response([
            'data'    => $cart ? new CartResource($cart) : null,
            'message' => __('shop::app.checkout.cart.item.success-remove'),
        ]);
    }

    /**
     * Apply the coupon code.
     *
     * @return \Illuminate\Http\Response
     */
    public function applyCoupon(Request $request)
    {
        $couponCode = $request->code;

        try {
            if (strlen($couponCode)) {
                Cart::setCouponCode($couponCode)->collectTotals();

                if (Cart::getCart()->coupon_code == $couponCode) {
                    return response([
                        'message' => trans('shop::app.checkout.total.success-coupon'),
                    ]);
                }
            }

            return response([
                'message' => trans('shop::app.checkout.total.invalid-coupon'),
            ], 400);
        } catch (\Exception $e) {
            report($e);

            return response([
                'message' => trans('shop::app.checkout.total.coupon-apply-issue'),
            ], 400);
        }
    }

    /**
     * Remove the coupon code.
     *
     * @return \Illuminate\Http\Response
     */
    public function removeCoupon()
    {
        Cart::removeCouponCode()->collectTotals();

        return response([
            'message' => trans('shop::app.checkout.total.remove-coupon'),
        ]);
    }

    /**
     * Move cart item to wishlist.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function moveToWishlist($cartItemId)
    {
        Event::dispatch('checkout.cart.item.move-to-wishlist.before', $cartItemId);

        Cart::moveToWishlist($cartItemId);

        Event::dispatch('checkout.cart.item.move-to-wishlist.after', $cartItemId);

        Cart::collectTotals();

        $cart = Cart::getCart();

        return response([
            'data'    => $cart ? new CartResource($cart) : null,
            'message' => __('shop::app.checkout.cart.move-to-wishlist-success'),
        ]);
    }
}