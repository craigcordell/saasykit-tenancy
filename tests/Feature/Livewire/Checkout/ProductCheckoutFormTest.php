<?php

namespace Tests\Feature\Livewire\Checkout;

use App\Constants\OrderStatus;
use App\Dto\CartDto;
use App\Dto\CartItemDto;
use App\Livewire\Checkout\ProductCheckoutForm;
use App\Models\Currency;
use App\Models\OneTimeProduct;
use App\Models\OneTimeProductPrice;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentProviders\PaymentProviderInterface;
use App\Services\PaymentProviders\PaymentService;
use App\Services\SessionService;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\Feature\FeatureTest;

class ProductCheckoutFormTest extends FeatureTest
{
    public function test_can_checkout_new_user()
    {
        $product = OneTimeProduct::factory()->create([
            'slug' => 'product-slug-6',
            'is_active' => true,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $this->addPaymentProvider();

        $this->instance(SessionService::class, Mockery::mock(SessionService::class, function (MockInterface $mock) use ($product) {
            $cartDto = new CartDto;
            $cartItem = new CartItemDto;
            $cartItem->productId = $product->id;
            $cartDto->items = [$cartItem];
            $mock->shouldReceive('getCartDto')->andReturn($cartDto);

            $mock->shouldReceive('saveCartDto');
        }));

        // get number of orders before checkout
        $ordersBefore = Order::count();

        $tenantsBefore = Tenant::count();

        Livewire::test(ProductCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', 'something@gmail.com')
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => 'something@gmail.com',
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($ordersBefore + 1, Order::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());
    }

    public function test_can_checkout_existing_user()
    {
        $product = OneTimeProduct::factory()->create([
            'slug' => 'product-slug-7',
            'is_active' => true,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $user = User::factory()->create([
            'email' => 'existing@gmail.com',
            'password' => bcrypt('password'),
            'name' => 'Name',
        ]);

        $this->addPaymentProvider();

        $this->instance(SessionService::class, Mockery::mock(SessionService::class, function (MockInterface $mock) use ($product) {
            $cartDto = new CartDto;
            $cartItem = new CartItemDto;
            $cartItem->productId = $product->id;
            $cartDto->items = [$cartItem];
            $mock->shouldReceive('getCartDto')->andReturn($cartDto);

            $mock->shouldReceive('saveCartDto');
        }));

        // get number of orders before checkout
        $ordersBefore = Order::count();
        $tenantsBefore = Tenant::count();

        Livewire::test(ProductCheckoutForm::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertRedirect('http://paymore.com/checkout');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => 'something@gmail.com',
        ]);

        // assert order has been created
        $this->assertEquals($ordersBefore + 1, Order::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());
    }

    private function addOfflinePaymentProvider()
    {
        // find or create payment provider
        PaymentProvider::updateOrCreate([
            'slug' => 'paymore-offline',
        ], [
            'name' => 'Paymore Offline',
            'is_active' => true,
            'type' => 'any',
        ]);

        $mock = Mockery::mock(PaymentProviderInterface::class);
        $mock->shouldReceive('initProductCheckout')
            ->once()
            ->andReturn([]);

        $mock->shouldReceive('isRedirectProvider')
            ->andReturn(false);

        $mock->shouldReceive('getSlug')
            ->andReturn('paymore-offline');

        $mock->shouldReceive('getName')
            ->andReturn('Paymore Offline');

        $mock->shouldReceive('isOverlayProvider')
            ->andReturn(false);

        $this->app->instance(PaymentProviderInterface::class, $mock);

        $this->app->bind(PaymentService::class, function () use ($mock) {
            return new PaymentService($mock);
        });
    }

    public function test_can_checkout_overlay_payment()
    {
        $product = OneTimeProduct::factory()->create([
            'slug' => 'product-slug-8',
            'is_active' => true,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $this->addPaymentProvider(false);

        $this->instance(SessionService::class, Mockery::mock(SessionService::class, function (MockInterface $mock) use ($product) {
            $cartDto = new CartDto;
            $cartItem = new CartItemDto;
            $cartItem->productId = $product->id;
            $cartDto->items = [$cartItem];
            $mock->shouldReceive('getCartDto')->andReturn($cartDto);

            $mock->shouldReceive('saveCartDto');
        }));

        // get number of orders before checkout
        $ordersBefore = Order::count();
        $tenantsBefore = Tenant::count();

        Livewire::test(ProductCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', 'something2@gmail.com')
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore')
            ->call('checkout')
            ->assertDispatched('start-overlay-checkout');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => 'something2@gmail.com',
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($ordersBefore + 1, Order::count());
        $this->assertEquals($tenantsBefore + 1, Tenant::count());
    }

    private function addPaymentProvider(bool $isRedirect = true)
    {
        // find or create payment provider
        PaymentProvider::updateOrCreate([
            'slug' => 'paymore',
        ], [
            'name' => 'Paymore',
            'is_active' => true,
            'type' => 'any',
        ]);

        $mock = \Mockery::mock(PaymentProviderInterface::class);
        $mock->shouldReceive('initProductCheckout')
            ->once()
            ->andReturn([]);

        $mock->shouldReceive('isRedirectProvider')
            ->andReturn($isRedirect);

        $mock->shouldReceive('getSlug')
            ->andReturn('paymore');

        $mock->shouldReceive('getName')
            ->andReturn('Paymore');

        $mock->shouldReceive('isOverlayProvider')
            ->andReturn(! $isRedirect);

        if ($isRedirect) {
            $mock->shouldReceive('createProductCheckoutRedirectLink')
                ->andReturn('http://paymore.com/checkout');
        }

        $this->app->instance(PaymentProviderInterface::class, $mock);

        $this->app->bind(PaymentService::class, function () use ($mock) {
            return new PaymentService($mock);
        });

        return $mock;
    }

    public function test_can_checkout_offline_payment()
    {
        $slug = 'product-slug-'.str()->random(5);
        $product = OneTimeProduct::factory()->create([
            'slug' => $slug,
            'is_active' => true,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $this->addOfflinePaymentProvider();

        $this->instance(SessionService::class, Mockery::mock(SessionService::class, function (MockInterface $mock) use ($product) {
            $cartDto = new CartDto;
            $cartItem = new CartItemDto;
            $cartItem->productId = $product->id;
            $cartDto->items = [$cartItem];
            $mock->shouldReceive('getCartDto')->andReturn($cartDto);

            $mock->shouldReceive('saveCartDto');
        }));

        // get number of orders before checkout
        $ordersBefore = Order::count();

        Livewire::test(ProductCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', 'something3@gmail.com')
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore-offline')
            ->call('checkout')
            ->assertRedirectToRoute('checkout.product.success');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => 'something3@gmail.com',
        ]);

        // assert order has been created
        $this->assertDatabaseHas('orders', [
            'user_id' => auth()->id(),
            'status' => OrderStatus::PENDING->value,
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($ordersBefore + 1, Order::count());
    }

    public function test_can_checkout_quantity()
    {
        $slug = 'product-slug-'.str()->random(5);
        $product = OneTimeProduct::factory()->create([
            'slug' => $slug,
            'is_active' => true,
            'max_quantity' => 5,
        ]);

        OneTimeProductPrice::create([
            'one_time_product_id' => $product->id,
            'currency_id' => Currency::where('code', 'USD')->first()->id,
            'price' => 100,
        ]);

        $this->addOfflinePaymentProvider();

        $this->instance(SessionService::class, Mockery::mock(SessionService::class, function (MockInterface $mock) use ($product) {
            $cartDto = new CartDto;
            $cartItem = new CartItemDto;
            $cartItem->quantity = 3; // Set quantity to 3
            $cartItem->productId = $product->id;
            $cartDto->items = [$cartItem];
            $mock->shouldReceive('getCartDto')->andReturn($cartDto);

            $mock->shouldReceive('saveCartDto');
        }));

        // get number of orders before checkout
        $ordersBefore = Order::count();

        Livewire::test(ProductCheckoutForm::class)
            ->set('name', 'Name')
            ->set('email', 'something5@gmail.com')
            ->set('password', 'password')
            ->set('paymentProvider', 'paymore-offline')
            ->call('checkout')
            ->assertRedirectToRoute('checkout.product.success');

        // assert user has been created
        $this->assertDatabaseHas('users', [
            'email' => 'something5@gmail.com',
        ]);

        // assert order has been created
        $this->assertDatabaseHas('orders', [
            'user_id' => auth()->id(),
            'status' => OrderStatus::PENDING->value,
        ]);

        // assert user is logged in
        $this->assertAuthenticated();

        // assert order has been created
        $this->assertEquals($ordersBefore + 1, Order::count());

        $checkoutDto = app(SessionService::class)->getCartDto();

        $latestOrder = Order::find($checkoutDto->orderId);
        $this->assertEquals(3, $latestOrder->items->first()->quantity);
    }
}
