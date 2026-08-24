<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test verisi üreticisi. Testlerde 10 satır elle veri doldurmak yerine
 * `Customer::factory()->create()` yeterli olsun diye.
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'sifre1234',
            'phone' => fake()->numerify('05#########'),
            'accepts_marketing' => false,

            /*
            | ⚠️ VARSAYILAN DOĞRULANMIŞ (4.6W). Fabrika "sıradan, yerleşik
            | müşteri" üretiyor; doğrulama yorum yazmanın önkoşulu olduğu
            | için varsayılan doğrulanmamış bırakılsaydı yorumla ilgisi
            | olmayan 14 test doğrulama adımını taklit etmek zorunda
            | kalırdı — ve o taklit, ölçtükleri şeyi bulanıklaştırırdı.
            |
            | Doğrulanmamış müşteriyi ölçen testler `dogrulanmamis()`
            | durumunu AÇIKÇA istiyor; böylece niyet okunuyor.
            */
            'email_verified_at' => now(),
        ];
    }

    /** Doğrulanmamış müşteri — e-posta doğrulama akışı testleri için. (4.6W) */
    public function dogrulanmamis(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    /**
     * MİSAFİR müşteri — e-postası ve parolası yok (domain-model §6).
     * Sipariş akışı testlerinde bu durum sık kullanılacak.
     */
    public function misafir(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'password' => null,
        ]);
    }

    /** Pazarlama iznini açık kullanıcı — KVKK akışı testleri için. */
    public function pazarlamaIzinli(): static
    {
        return $this->state(fn () => ['accepts_marketing' => true]);
    }
}
