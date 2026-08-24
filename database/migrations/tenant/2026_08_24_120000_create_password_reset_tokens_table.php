<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Şifre sıfırlama jetonları — MARKA şemasında. (4.6V)
 *
 * ★ İKİ AYRI TABLO ve bu bir GÜVENLİK kararı, üslup tercihi değil.
 *
 * ⚠️ Laravel'in `DatabaseTokenRepository`'si jetonu YALNIZCA E-POSTAYA
 * göre saklıyor (`delete where email = ?` + `insert`). Müşteri ve
 * personel tek tabloyu paylaşsaydı, aynı e-postaya sahip iki kayıt
 * birbirinin jetonunu ezerdi:
 *
 *   ayse@ornek.com hem MÜŞTERİ hem PERSONEL ise
 *   → müşteri sıfırlama jetonu personel parolasını değiştirebilirdi
 *
 * Bu yetki yükseltmedir: vitrinden herkesin açabildiği bir müşteri
 * hesabı, panel personelinin parolasını ele geçirmenin yolu olurdu.
 *
 * ⚠️ MARKA ŞEMASINDA (tenant/), merkezde değil: `Customer` ve `User`
 * ikisi de marka şemasında yaşıyor. Merkeze konsaydı A markasının
 * müşterisine üretilen jeton B markasında da geçerli olurdu.
 *
 * ⚠️ `PlatformUser` BİLEREK KAPSAM DIŞI: onun `tenant:create`
 * benzeri bir komut satırı kurtarma yolu zaten var
 * (`CreatePlatformUser`). Müşteri ve personelin HİÇBİR yolu yoktu —
 * bu bloğun sebebi o.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
        | ⚠️ `email` BİRİNCİL ANAHTAR: kişi başına tek açık jeton.
        | İkinci sıfırlama isteği öncekini geçersiz kılıyor — eski
        | postadaki bağlantı ölüyor. Birden çok jetona izin verilseydi
        | saldırgan, kurbanın gelen kutusunu doldurup eski bir jetonu
        | canlı tutabilirdi.
        */
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');

            // ⚠️ `timestampTz` — projede saat dilimi taşımayan damga YASAK
            // (docs/domain-model.md §0). Süre kontrolü buna bakıyor.
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('staff_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_password_reset_tokens');
        Schema::dropIfExists('password_reset_tokens');
    }
};
