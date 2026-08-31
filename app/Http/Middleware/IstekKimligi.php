<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Her isteğe bir kimlik verir ve cevabın başlığına yazar.
 *
 * ★ NEDEN GEREKLİ: bir sipariş tek satırda bitmiyor. Ödeme akışında
 * vitrin isteği, sağlayıcı çağrısı, dönüş isteği ve kuyruk işi ayrı ayrı
 * günlüğe düşüyor; ortak bir kimlik olmadan bunların AYNI siparişe ait
 * olduğu anlaşılamıyor.
 *
 * ⚠️ `Context` KULLANILIYOR, `Log::withContext()` DEĞİL. İkisi de aynı
 * satırı üretir ama `Context` kuyruk işine de TAŞINIYOR — ve bu projede
 * worker ile app AYNI günlük dosyasına yazıyor (ölçüldü: inode aynı).
 * Taşınmasaydı isteğin kendisi kimlikli, tetiklediği iş kimliksiz olurdu.
 *
 * ⚠️ Başlık cevaba da yazılıyor: müşteri "ödeme başarısız" ekranı
 * gördüğünde destek ondan bir kimlik isteyebilsin diye. Kimlik rastgele
 * ve tek kullanımlık — kişisel veri taşımıyor.
 */
final class IstekKimligi
{
    public function handle(Request $istek, Closure $next): Response
    {
        $kimlik = (string) Str::uuid();

        Context::add('istek_id', $kimlik);

        $cevap = $next($istek);

        $cevap->headers->set('X-Istek-Id', $kimlik);

        return $cevap;
    }
}
