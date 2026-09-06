<?php

namespace Tests\Feature;

use App\Livewire\Cart;
use App\Livewire\PurchaseCart;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/** TEMPORARY — qty + stepper in the row's right column. */
class TmpVerifyRowLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.database' => 'pos_duck',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function saleHtml(string $docType = 'NA'): string
    {
        return Livewire::actingAs($this->admin())->test(Cart::class)
            ->set('cart', [[
                'id' => 55, 'order_no' => 1, 'name' => 'ORGANIC KAMPOT', 'qty' => 4,
                'unit' => 'SMOK', 'price' => 6.5, 'discount_price' => 6.5,
                'discount_percent' => 0, 'stock' => 12, 'type' => 'product',
                'vat' => 0, 'track_stock' => 0,
            ]])
            ->set('document_type', $docType)
            ->html();
    }

    public function test_qty_and_stepper_sit_in_the_right_column(): void
    {
        $html = $this->saleHtml();

        $name  = strpos($html, 'ci-description');
        $total = strpos($html, '<div class="ci-total">');
        $qty   = strpos($html, '<div class="ci-qty">');
        $step  = strpos($html, '<div class="ci-qty-step"');

        $this->assertNotFalse($total, 'right column missing');
        $this->assertNotFalse($qty, 'qty block missing');
        $this->assertNotFalse($step, 'stepper missing');

        // qty is no longer inline in the product name
        $this->assertGreaterThan($name, $qty, 'qty is still before the name');
        $this->assertGreaterThan($total, $qty, 'qty is not inside the right column');
        // and the stepper sits below the amount
        $this->assertGreaterThan($qty, $step, 'stepper is above the qty');
        $this->assertMatchesRegularExpression('~<span\s+class="ci-qty-val">4</span>~', $html, 'qty value not rendered');
        $this->assertStringContainsString('SMOK', $html, 'unit missing');
    }

    public function test_dropdown_qty_is_a_plain_input_again(): void
    {
        $html = $this->saleHtml();

        $this->assertStringContainsString('id="qty_order_0"', $html, 'qty input missing');
        // the stepper must not be wrapped around the dropdown input any more:
        // count the markup form only, since .ci-qty-step is also a CSS rule
        $this->assertSame(1, substr_count($html, '<div class="ci-qty-step"'), 'stepper appears more than once per row');
    }

    public function test_locked_row_has_no_stepper(): void
    {
        $html = $this->saleHtml('Completed');

        $this->assertStringNotContainsString('stepCartQty(this', $html, 'locked row is still steppable');
        $this->assertStringContainsString('<div class="ci-qty">', $html, 'locked row lost its qty');
    }

    public function test_purchase_row_matches(): void
    {
        $html = Livewire::actingAs($this->admin())->test(PurchaseCart::class)
            ->set('cart', [[
                'id' => 77, 'order_no' => 1, 'name' => 'BUY ROW', 'qty' => 2,
                'unit' => 'pcs', 'cost_price' => 3.50, 'has_expire' => 0,
            ]])->html();

        $total = strpos($html, '<div class="ci-total">');
        $qty   = strpos($html, '<div class="ci-qty">');
        $step  = strpos($html, '<div class="ci-qty-step"');

        $this->assertGreaterThan($total, $qty, 'purchase qty not in the right column');
        $this->assertGreaterThan($qty, $step, 'purchase stepper above the qty');
        $this->assertMatchesRegularExpression('/<spans*class="ci-qty-val">2</span>/', $html);
    }

    public function test_stepper_js_reads_the_span(): void
    {
        foreach (['/Sale', '/Purchasing'] as $route) {
            $html = $this->actingAs($this->admin())->get($route)->assertOk()->getContent();

            $this->assertStringContainsString("card.querySelector('.ci-qty-val')", $html, "$route reads the wrong element");
            $this->assertStringContainsString("Livewire.dispatch('set-qty'", $html, "$route does not commit");
            $this->assertSame(1, substr_count($html, '.ci-step {'), "$route has duplicate .ci-step rules");
        }
    }
}
