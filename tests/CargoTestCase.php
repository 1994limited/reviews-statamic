<?php

namespace NineteenNinetyFour\Reviews\Tests;

use DuncanMcClean\Cargo\Contracts\Orders\Order as OrderContract;
use DuncanMcClean\Cargo\Discounts\DiscountServiceProvider;
use DuncanMcClean\Cargo\Facades\Cart;
use DuncanMcClean\Cargo\Facades\PaymentGateway;
use DuncanMcClean\Cargo\Orders\Actions\CreateOrderFromCart;
use DuncanMcClean\Cargo\Orders\OrderServiceProvider;
use DuncanMcClean\Cargo\Payments\PaymentServiceProvider;
use DuncanMcClean\Cargo\ServiceProvider as CargoServiceProvider;
use DuncanMcClean\Cargo\Shipping\ShippingServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Statamic\Addons\Manifest;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

/**
 * Reviews on a shop with Cargo installed. Tests using it run each test in
 * its own process (#[RunTestsInSeparateProcesses]): Cargo registers routes
 * and more in Statamic's static state, which would otherwise reach tests
 * without it. PHPUnit reads that attribute from the test class.
 */
abstract class CargoTestCase extends TestCase
{
    /** Where Cargo keeps carts and orders, and blueprints are read from, during a test. */
    protected static string $content = __DIR__.'/__fixtures__/cargo-tmp';

    protected function getPackageProviders($app)
    {
        return [
            CargoServiceProvider::class,
            DiscountServiceProvider::class,
            OrderServiceProvider::class,
            PaymentServiceProvider::class,
            ShippingServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('statamic.cargo', require __DIR__.'/../vendor/duncanmcclean/statamic-cargo/config/cargo.php');

        Route::get('checkout', fn () => 'Checkout')->name('checkout');
        Route::get('checkout/confirmation', fn () => 'Confirmation')->name('checkout.confirmation');
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Statamic boots only the addons in its manifest, and the test case
        // lists just this one. Cargo is installed alongside it on a site.
        $app->make(Manifest::class)->manifest['duncanmcclean/statamic-cargo'] = [
            'id' => 'duncanmcclean/statamic-cargo',
            'slug' => 'cargo',
            'version' => '1.15.0',
            'namespace' => 'DuncanMcClean\\Cargo',
            'autoload' => 'src',
            'provider' => CargoServiceProvider::class,
        ];

        foreach (['carts', 'orders', 'discounts'] as $store) {
            $app['config']->set("statamic.cargo.{$store}.directory", static::$content.'/'.$store);
        }
        $app['config']->set('statamic.cargo.taxes.tax_classes.directory', static::$content.'/tax-classes');
        $app['config']->set('statamic.cargo.taxes.tax_zones.directory', static::$content.'/tax-zones');
        $app['config']->set('mail.default', 'array');
    }

    protected function setUp(): void
    {
        $files = new Filesystem;
        $files->deleteDirectory(static::$content);
        $files->ensureDirectoryExists(static::$content.'/blueprints/collections/products');
        $files->put(static::$content.'/blueprints/collections/products/product.yaml', <<<'YAML'
title: Product
fields:
  - { handle: title, field: { type: text } }
  - { handle: price, field: { type: money } }
  - { handle: product_variants, field: { type: product_variants } }
YAML);

        parent::setUp();

        Site::setSites(['default' => ['name' => 'Dog-ear Books', 'url' => '/', 'locale' => 'en_GB', 'attributes' => ['currency' => 'GBP']]])->save();
        Blueprint::setDirectory(static::$content.'/blueprints');
    }

    protected function tearDown(): void
    {
        Cart::forgetCurrentCart();

        parent::tearDown();

        (new Filesystem)->deleteDirectory(static::$content);
    }

    /**
     * A book for sale: £12, or a hardback and paperback.
     */
    protected function book(string $id = 'book', string $title = 'The Long Field', bool $variants = false): void
    {
        $data = ['title' => $title, 'price' => 1200];

        if ($variants) {
            // Cargo looks up a variant's tax class on its product.
            \DuncanMcClean\Cargo\Facades\TaxClass::make()->handle('books')->set('title', 'Books')->save();
            $data['tax_class'] = 'books';
            unset($data['price']);
            $data['product_variants'] = [
                'variants' => [['name' => 'Format', 'values' => ['Hardback', 'Paperback']]],
                'options' => [
                    ['key' => 'Hardback', 'variant' => 'Hardback', 'price' => 2000],
                    ['key' => 'Paperback', 'variant' => 'Paperback', 'price' => 1000],
                ],
            ];
        }

        Entry::make()->collection('products')->blueprint('product')->id($id)->slug(str($title)->slug())->data($data)->save();
    }

    /**
     * An order, paid with Cargo's dummy gateway, by a signed-in customer or
     * a guest.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    protected function order(array $lineItems = [['product' => 'book']], mixed $customer = null): OrderContract
    {
        $cart = Cart::make()
            ->customer($customer ?? ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
            ->set('billing_address', ['line_1' => '1 High Street', 'city' => 'London', 'postcode' => 'N1 1AA', 'country' => 'GBR']);

        foreach ($lineItems as $lineItem) {
            $cart->lineItems()->create(['quantity' => 1] + $lineItem);
        }

        $cart->save();
        $cart = Cart::find($cart->id());

        return app(CreateOrderFromCart::class)->handle($cart, PaymentGateway::find('dummy'));
    }
}
